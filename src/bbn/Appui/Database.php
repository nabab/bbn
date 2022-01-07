<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 20/02/2017
 * Time: 01:39
 */

namespace bbn\Appui;

use bbn;
use bbn\Str;
use bbn\X;

class Database extends bbn\Models\Cls\Cache
{
  use bbn\Models\Tts\Optional;


  /**
   * The option object.
   *
   * @var option
   */
  protected $o;


  /**
   * The passwords object.
   *
   * @var passwords
   */
  protected $pw;


  /**
   * The last alternative connection made with the connection function.
   * This is a longer description.
   * <code>
   * I can put code in it
   * </code>
   *
   * @var bbn\Db
   */
  protected $connections = [
    'mysql' => [],
    'postgre' => [],
    'sqlite' => []
  ];


  protected function getPassword($id_option)
  {
    if (!$this->pw) {
      $this->pw = new Passwords($this->db);
    }

    return $this->pw->get($id_option);
  }


  /**
   * Constructor
   *
   * @param bbn\Db $db The main database connection (where options are)
   */
  public function __construct(bbn\Db $db)
  {
    parent::__construct($db);
    self::optionalInit();
    $this->o = bbn\Appui\Option::getInstance();

  }


  /**
   * Returns a connection with the given user@host selecting the given database.
   *
   * @param string $host A string user@host
   * @param string $db   The database name
   * @return bbn\Db|null
   */
  public function connection(string $host = null, string $engine = 'mysql', string $db = ''): bbn\Db
  {
    if (bbn\Str::isUid($host)) {
      $id_host = $host;
    }
    elseif (!($id_host = $this->hostId($host, $engine))) {
      throw new \Exception(X::_("Impossible to find the host").' '."$host ($engine)");
    }

    if (!($cfg = $this->o->option($id_host))) {
      throw new \Exception(X::_("Impossible to find the option corresponding to host").' '."$host ($engine)");
    }

    if ($id_host && ($parent = $this->o->parent($this->o->getIdParent($id_host)))) {
      if (!isset($this->connections[$parent['code']])) {
        throw new \Exception(X::_("Unknown engine")." ".$parent['code']);
      }

      if (!isset($this->connections[$parent['code']][$cfg['code']])) {
        switch ($parent['code']) {
          case 'mysql':
            if (strpos($cfg['code'], '@')) {
              $bits = bbn\X::split($cfg['code'], '@');
              if ((count($bits) === 2) && ($password = $this->getPassword($id_host))) {
                $db_cfg = [
                  'engine' => 'mysql',
                  'user' => $bits[0],
                  'host' => $bits[1],
                  'db' => $db,
                  'pass' => $password
                ];
              }
              else {
                $db_cfg = [
                  'engine' => 'mysql',
                  'host' => $cfg['code'],
                  'db' => $db
                ];
              }

              try {
                $this->connections[$parent['code']][$cfg['code']] = new bbn\Db($db_cfg);
              }
              catch (\Exception $e) {
                throw new \Exception($e->getMessage());
              }
            }
            break;

          case 'postgre':
            if (empty($db) || empty($cfg['path'])) {
              throw new \Exception(X::_('db or path empty'));
            }
            break;

          case 'sqlite':
            if (empty($db) || empty($cfg['path']) || !file_exists($cfg['path'].'/'.$db)) {
              throw new \Exception(X::_('db or path empty'));
            }
            
            $db_cfg = [
              'engine' => 'sqlite',
              'db' => $cfg['path'].'/'.$db
            ];
            try {
              $this->connections[$parent['code']][$cfg['code']] = new bbn\Db($db_cfg);
            }
            catch (\Exception $e) {
              throw new \Exception($e->getMessage());
            }
            break;

          default:
            throw new \Exception(X::_('Impossible to find the engine').' '.$cfg['engine']);
        }
      }

      if (isset($this->connections[$parent['code']][$cfg['code']])) {
        return $this->connections[$parent['code']][$cfg['code']];
      }
    }

    throw new \Exception(X::_("Impossible to get a connection for").' '.$cfg['code']);
  }


  /**
   * Returns the ID of a connection.
   *
   * @param string $host The connection code (user@host or host)
   * @return null|string
   */
  public function hostId(string $host = null, string $engine = 'mysql'): ?string
  {
    if (empty($host)) {
      $host = $this->db->getConnectionCode();
    }

    $r = self::getOptionId($host, $engine === 'sqlite' ? 'paths' : 'connections', $engine);
    return $r ?: null;
  }


  /**
   * Returns the number of connections in the options.
   *
   * @return int|null
   */
  public function countHosts(string $engine = 'mysql'): ?int
  {
    if (($id_parent = self::getOptionId($engine === 'sqlite' ? 'paths' : 'connections', $engine))
        && ($num = $this->o->count($id_parent))
    ) {
      return $num;
    }

    return 0;
  }


  /**
   * Returns a list of the connections available.
   *
   * @return array
   */
  public function hosts(string $engine = 'mysql'): array
  {
    if (($id_parent = self::getOptionId($engine === 'sqlite' ? 'paths' : 'connections', $engine))
        && ($co = array_values($this->o->codeOptions($id_parent)))
    ) {
      return $co;
    }

    return [];
  }


  /**
   * Returns the list of the connections
   *
   * @return array|null
   */
  public function fullHosts(string $engine = 'mysql'): ?array
  {
    if (($id_parent = self::getOptionId($engine === 'sqlite' ? 'paths' : 'connections', $engine))
        && ($opt = $this->o->fullOptions($id_parent))
    ) {
      return $opt;
    }

    return null;
  }


  /**
   * Returns the option's ID of a database.
   *
   * @param string $db The database's name
   * @return null|string
   */
  public function dbId(string $db = '', string $host = '', string $engine = 'mysql'): ?string
  {
    if (!\bbn\Str::isUid($host)) {
      $host = $this->hostId($host, $engine);
    }
    if (($id_parent = self::getOptionId('dbs', $engine))
        && ($res = $this->o->fromCode($db ?: $this->db->getCurrent(), $id_parent))
    ) {
      return $res;
    }

    return null;
  }


  /**
   * Returns the number of DBs available for the given connection.
   *
   * @param string $host The connection's code
   * @return int
   */
  public function countDbs(string $host = '', string $engine = 'mysql'): int
  {
    if (!$host) {
      $num = $this->o->count(self::getOptionId('dbs', $engine));
      return $num;
    }
    elseif (!bbn\Str::isUid($host)) {
      $host = $this->hostId($host, $engine);
    }

    $all = $this->o->getAliases($host);
    $num = $all ? count($all) : 0;
    return $num;
  }


  /**
   * Returns the list of DBs available for the given connection.
   *
   * @param string $host The connection's code, all DBs are returned if empty.
   * @return array|null
   */
  public function dbs(string $host = '', string $engine = 'mysql'): array
  {
    if (!$host) {
      $arr = $this->o->fullOptions(self::getOptionId('dbs', $engine));
    }
    elseif (!bbn\Str::isUid($host)) {
      $host = $this->hostId($host, $engine);
    }

    if ($host) {
      $o   = &$this->o;
      $arr = array_map(
        function ($a) use ($o) {
          return $o->parent($a['id_parent']);
        },
        $this->o->getAliases($host)
      );
    }

    if (!empty($arr)) {
      $res = array_map(
        function ($a) {
          return [
            'id' => $a['id'],
            'text' => $a['text'],
            'name' => $a['code']
          ];
        },
        $arr
      );
      return $res;
    }

    return [];
  }


  /**
   * Returns the list of DBs available for the given connection with statistics.
   *
   * @param string $host The connection's code, all DBs are returned if empty.
   * @return array
   */
  public function fullDbs(string $host = '', string $engine = 'mysql'): array
  {
    $o =& $this->o;
    if ($dbs = $this->dbs($host, $engine)) {
      $res = array_map(
        function ($a) use ($o, $engine) {
          $r = [
            'id' => $a['id'],
            'text' => $a['text'],
            'name' => $a['name'],
            'num_tables' => 0,
            'num_connections' => 0,
            'num_procedures' => 0,
            'num_functions' => 0
          ];
          //die(var_dump( $o->fromCode('tables', $a['id'])));
          if ($id_tables = $o->fromCode('tables', $a['id'])) {
            $r['num_tables'] = $o->count($id_tables);
          }

          if ($id_connections = $o->fromCode($engine === 'sqlite' ? 'paths' : 'connections', $a['id'])) {
            $r['num_connection'] = $o->count($id_connections);
          }

          if ($id_procedures = $o->fromCode('procedures', $a['id'])) {
            $r['num_procedures'] = $o->count($id_procedures);
          }

          if ($id_functions = $o->fromCode('functions', $a['id'])) {
            $r['num_functions'] = $o->count($id_functions);
          }

          return $r;
        },
        $dbs
      );
      return $res ?: [];
    }

    return [];
  }


  /**
   * Returns the ID of a table from the options table.
   *
   * @param string $table The name of the table
   * @param mixed  $db    The name of the DB
   * @param string $host  The connection's code
   * @return string|null
   */
  public function tableId(string $table, string $db = '', string $host = '', string $engine = 'mysql'): ?string
  {
    if (!bbn\Str::isUid($db)) {
      if (Str::isUid($host)) {
        if (!($parent = $this->o->parent($this->o->getIdParent($host)))) {
          throw new \Exception(X::_("Impossible to find the host engine"));
        }

        $engine = $parent['code'];
        $db     = $this->dbId($db, $host, $engine);
      }
      else {
        $db = $this->dbId($db, $host, $engine);
      }
    }

    if (bbn\Str::isUid($db)
        && ($id_parent = $this->o->fromCode('tables', $db))
        && ($id = $this->o->fromCode($table, $id_parent))
    ) {
      return $id;
    }

    return null;
  }


  /**
   * Returnms the number of tables in the given database.
   *
   * @param string $db   The database name
   * @param string $host The connection's code
   * @return int|null
   */
  public function countTables(string $db, string $host = '', string $engine = 'mysql'): ?int
  {
    if (!bbn\Str::isUid($db)) {
      if (Str::isUid($host)) {
        $db = $this->dbId($db, $host);
      }
      else {
        $db = $this->dbId($db, $host, $engine);
      }
    }

    if (bbn\Str::isUid($db) && ($id_parent = $this->o->fromCode('tables', $db))) {
      $num = $this->o->count($id_parent);
      return $num ?: 0;
    }

    return null;
  }


  /**
   * Returns a list of tables in the given database.
   *
   * @param mixed  $db   The database name
   * @param string $host The connection's code
   * @return array|null
   */
  public function tables(string $db = '', string $host = '', string $engine = 'mysql'): ?array
  {
    if (!bbn\Str::isUid($db)) {
      if (Str::isUid($host)) {
        $db = $this->dbId($db, $host);
      }
      else {
        $db = $this->dbId($db, $host, $engine);
      }
    }

    if (bbn\Str::isUid($db)
        && ($id_parent = $this->o->fromCode('tables', $db))
        && ($fo = array_values($this->o->codeOptions($id_parent)))
    ) {
      $res = array_map(
        function ($a) {
          return [
            'id' => $a['id'],
            'text' => $a['text'],
            'name' => $a['code']
          ];
        },
        $fo
      );
      return $res ?: [];
    }

    return null;
  }


  /**
   * Returns a list of tables in the given database with its statistics.
   *
   * @param mixed  $db   The database name
   * @param string $host The connection's code
   * @return array|null
   */
  public function fullTables(string $db = '', string $host = '', string $engine = 'mysql'): ?array
  {
    if (!bbn\Str::isUid($db)) {
      if (Str::isUid($host)) {
        $db = $this->dbId($db, $host);
      }
      else {
        $db = $this->dbId($db, $host, $engine);
      }
    }

    if (bbn\Str::isUid($db) && ($id_parent = $this->o->fromCode('tables', $db))) {
      $o =& $this->o;
      if ($fo = $this->o->fullOptions($id_parent)) {
        $res = array_map(
          function ($a) use ($o) {
            $r = array_merge(
              $a,
              [
                'name' => $a['code'],
                'num_columns' => 0,
                'num_keys' => 0
              ]
            );
            if ($id_columns = $o->fromCode('columns', $a['id'])) {
              $r['num_columns'] = $o->count($id_columns);
            }

            if ($id_keys = $o->fromCode('keys', $a['id'])) {
              $r['num_keys'] = $o->count($id_keys);
            }

            return $r;
          },
          $fo
        );
        return $res ?: [];
      }
    }

    return null;
  }


  /**
   * Gets the name of a table from an item's ID (key or column).
   *
   * @param string $id_keycol The ID of the item
   * @return string|null
   */
  public function tableFromItem(string $id_keycol): ?string
  {
    if (($table = $this->tableIdFromItem($id_keycol))
        && ($r = $this->o->code($table))
    ) {
      return $r;
    }

    return null;
  }


  /**
   * Retrieves the ID of a table from an item's ID (key or column).
   *
   * @param string $id_keycol The ID of the item
   * @return string|null
   */
  public function tableIdFromItem(string $id_keycol): ?string
  {
    if (bbn\Str::isUid($id_keycol)
        && ($id_cols = $this->o->getIdParent($id_keycol))
        && ($id_table = $this->o->getIdParent($id_cols))
    ) {
      return $id_table;
    }

    return null;
  }


  /**
   * Retrieves a database name from the ID of a table.
   *
   * @param string $id_table The table's ID.
   * @return string|null
   */
  public function dbFromTable(string $id_table): ?string
  {
    if (($id_db = $this->dbIdFromTable($id_table))
        && ($r = $this->o->code($id_db))
    ) {
      return $r;
    }

    return null;
  }


  /**
   * Returns the ID of a DB through the given table.
   *
   * @param string $id_table The table's ID.
   * @return string|null
   */
  public function dbIdFromTable(string $id_table): ?string
  {
    if (bbn\Str::isUid($id_table)
        && ($id_tables = $this->o->getIdParent($id_table))
        && ($id_db = $this->o->getIdParent($id_tables))
    ) {
      return $id_db;
    }

    return null;
  }


  /**
   * Returns the name of a DB through the ID of an item (key or column).
   *
   * @param string $id_keycol The ID of the item
   * @return string|null
   */
  public function dbFromItem(string $id_keycol): ?string
  {
    if ($id_db = $this->dbIdFromItem($id_keycol)
        && ($r = $this->o->code($id_db))
    ) {
      return $r;
    }

    return null;
  }


  /**
   * Returns the ID of a DB through the ID of an item (key or column).
   *
   * @param string $id_keycol The ID of the item
   * @return string|null
   */
  public function dbIdFromItem(string $id_keycol): ?string
  {
    if (($id_table = $this->tableIdFromItem($id_keycol))
        && ($id_db = $this->dbIdFromTable($id_table))
    ) {
      return $id_db;
    }

    return null;
  }


  /**
   * Returns the given column's ID.
   *
   * @param string $column The column's name
   * @param string $table  The table's name
   * @param string $db     The DB's name
   * @return string|null
   */
  public function columnId(string $column, string $table, string $db = ''): ?string
  {
    $res = null;
    if (Str::isUid($table)) {
      $res = $this->o->fromCode($this->db->csn($column), 'columns', $table);
      return $res;
    }

    $c = $this->db->csn($column);
    $t = $this->db->tsn($table);
    if (!Str::isUid($db)) {
      $db = $this->dbId($db);
    }

    if (Str::isUid($db) && ($tmp = $this->o->fromCode($c, 'columns', $t, 'tables', $db))) {
      $res = $tmp;
    }

    return $res;
  }


  /**
   * Returns the number of columns for the given DB.
   *
   * @param string $table The table's name or UID
   * @param string $db    The database's UID
   * @return int
   */
  public function countColumns(string $table, string $db = ''): int
  {
    $num = 0;
    if (!Str::isUid($table) && Str::isUid($db)) {
      $table = $this->tableId($table, $db);
    }

    if (Str::isUid($table)
        && ($id_parent = $this->o->fromCode('columns', $table))
    ) {
      $num = $this->o->count($id_parent);
    }

    return $num;
  }


  /**
   * Returns a list of the columns for the given table.
   *
   * @param string $table The table's name or UID
   * @param string $db    The database's UID
   * @return array|false
   */
  public function columns(string $table, string $db = ''): ?array
  {
    if (!bbn\Str::isUid($table) && Str::isUid($db)) {
      $table = $this->tableId($this->db->tsn($table), $db);
    }

    if (bbn\Str::isUid($table)
        && ($id_parent = $this->o->fromCode('columns', $table))
        && ($res = $this->o->options($id_parent))
    ) {
      return $res;
    }

    return null;
  }


  /**
   * Returns a list of the columns for the given table with all their characteristics.
   *
   * @param string $table The table's name or UID
   * @param string $db    The database UID
   * @return array|false
   */
  public function fullColumns(string $table, string $db = ''): array
  {
    if (!bbn\Str::isUid($table) && Str::isUid($db)) {
      $table = $this->tableId($table, $db);
    }

    if (bbn\Str::isUid($table)
        && ($id_parent = $this->o->fromCode('columns', $table))
        && ($res = $this->o->fullOptions($id_parent))
    ) {
      return $res;
    }

    return [];
  }


  /**
   * Returns the ID of a key in the given table.
   *
   * @param string $key   The key;s name.
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return null|string
   */
  public function keyId(string $key, string $table, string $db = ''): ?string
  {
    $res = null;
    if (bbn\Str::isUid($key)) {
      $res = $this->o->fromCode($key, $table);
    }
    elseif (Str::isUid($table) && ($tmp = $this->o->fromCode($key, 'keys', $table))) {
      $res = $tmp;
    }
    elseif (Str::isUid($db) && ($tmp = $this->o->fromCode($key, 'keys', $table, 'tables', $db))) {
      $res = $tmp;
    }

    return $res;
  }


  /**
   * Returns the number of keys in the given table.
   *
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return int
   */
  public function countKeys(string $table, string $db = ''): int
  {
    $num = 0;
    if (!bbn\Str::isUid($table) && Str::isUid($db)) {
      $table = $this->tableId($table, $db);
    }

    if (bbn\Str::isUid($table)
        && ($id_parent = $this->o->fromCode('keys', $table))
    ) {
      $num = $this->o->count($id_parent);
    }

    return $num;
  }


  /**
   * Returns a list of keys for the giuven table.
   *
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return array
   */
  public function keys(string $table, string $db = ''): array
  {
    $res = [];
    if (!bbn\Str::isUid($table) && bbn\Str::isUid($db)) {
      $table = $this->tableId($table, $db);
    }

    if (bbn\Str::isUid($table)
        && ($id_parent = $this->o->fromCode('keys', $table))
        && ($tree = $this->o->fullTree($id_parent))
        && $tree['items']
    ) {
      $t   =& $this;
      $res = array_map(
        function ($a) use ($t) {
          $key = [
            'name' => $a['code'],
            'unique' => $a['unique'],
            'columns' => [],
            'ref_column' => $a['id_alias'] ? $a['alias']['code'] : null,
            'ref_table' => $a['id_alias'] &&
                          ($id_table = $t->o->getIdParent($a['alias']['id_parent'])) ? $t->o->code($id_table) : null,
            'ref_db' => !empty($id_table) &&
                        ($id_db = $t->o->getIdParent($t->o->getIdParent($id_table))) ? $t->o->code($id_db) : null
          ];
          foreach ($a['items'] as $col){
            $key['columns'][] = $col['code'];
          }

          return $key;
        },
        $tree['items']
      );
    }

    return $res;
  }


  /**
   * For the moment an alias of get_keys.
   *
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return array
   */
  public function fullKeys(string $table, string $db = ''): array
  {
    return $this->keys($table, $db);
  }


  /**
   * Deletes a table and all its descendants from the options table.
   *
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return int
   */
  public function remove(string $table, string $db = ''): int
  {
    $id = $this->tableId($table, $db);
    return $this->o->removeFull($id);
  }


  /**
   * Deletes a database and all its descendants from the options table.
   *
   * @param string $db The database's name
   * @return int
   */
  public function removeAll(string $db = ''): int
  {
    $id = $this->dbId($db);
    return $this->o->removeFull($id);
  }


  /**
   * Deletes a connection from the options table.
   *
   * @param string $connection The connection's code
   * @return int
   */
  public function removeHost(string $connection): int
  {
    $id = $this->hostId($connection);
    return $this->o->removeFull($id);
  }


  /**
   * Returns a database model as bbn\Db::modelize but with options IDs.
   *
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return array|null
   */
  public function modelize(string $table = '', string $db = '', string $host = '', string $engine = 'mysql'): ?array
  {
    $model = null;
    if (!$host) {
      $conn = $this->db;
      $host = $this->db->getConnectionCode();
      $old_db = $conn->getCurrent();
      if (Str::isUid($db)) {
        $db = $this->o->getCode($db);
      }

      if ($db && ($old_db !== $db)) {
        try {
          $conn->change($db);
        }
        catch (\Exception $e) {
          throw new \Exception(X::_("Impossible to use the database")." $db");
        }
      }
      elseif (!$db) {
        $db = $this->db->getCurrent();
      }
    }
    elseif ($db) {
      try {
        $conn = $this->connection($host, $engine, $db);
      }
      catch (\Exception $e) {
        throw new \Exception($e->getMessage());
      }
    }

    if (!$conn || !$conn->check()) {
      throw new \Exception(X::_("Impossible to connect"));
    }

    $table_id = null;
    $table    = $conn->tsn($table);
    $ftable   = $conn->tfn($db.'.'.$table);
    $keys     = function (&$a) use (&$table_id, $table, &$conn) {
      if (\is_array($a['keys'])) {
        array_walk(
          $a['keys'],
          function (&$w, $k) use ($table_id, $table) {
            $w['id_option'] = $this->keyId($k, $table_id);
          }
        );
      }
    };
    $fields   = function (&$a) use (&$table_id, $table, &$conn) {
      if (\is_array($a['fields'])) {
        array_walk(
          $a['fields'],
          function (&$w, $k) use ($table_id, $table) {
            if (!$table_id) {
              throw new \Exception(X::_("Table undefined")." $table");
            }

            $w['id_option'] = $this->columnId($k, $table_id);
            $w['option']    = $w['id_option'] ? $this->o->option($w['id_option']) : [];
          }
        );
      }
    };

    if ($model = $conn->modelize($ftable)) {
      if ($table
          && ($table_id = $this->tableId($table, $db, $host, $engine))
      ) {
        $keys($model);
        $fields($model);
        $model['id_option'] = $table_id;
        $model['option']    = $this->o->option($table_id);
}
      elseif (empty($table)) {
        array_walk(
          $model,
          function (&$w, $k) use (&$table_id, &$keys, &$fields, $host, $engine, $db) {
            $table = $this->db->tsn($k);
            if ($table_id = $this->tableId($table, $db, $host, $engine)) {
              $w['id_option'] = $table_id;
              $w['option']    = $this->o->option($w['id_option']);
              $keys($w);
              $fields($w);
            }
          }
        );
      }
    }

    if (!empty($old_db) && ($old_db !== $db)) {
      $conn->change($old_db);
    }

    return $model;
  }


  /**
   * Imports a database's structure into the options table.
   *
   * @param string $host   The connection's code
   * @param string $engine The connection's engine
   * @param array  $cfg    The connection's config
   * @param bool   $full   If true will connect to the database and get its structure
   * @return string|null The ID of the generated (or existing) database entry
   */
  public function importHost(string $host, string $engine, array $cfg, bool $full = false): ?string
  {
    if (($id_parent = self::getOptionId('connections', $engine))
        && !($id_host = $this->o->fromCode($host, $id_parent))
    ) {
      $id_host = $this->o->add(
        [
          'id_parent' => $id_parent,
          'text' => $cfg['name'] ?? $host,
          'code' => $host
        ]
      );
    }

    if ($id_host) {
      if (!empty($cfg['password'])) {
        if (!$this->pw) {
          $this->pw = new Passwords($this->db);
        }

        $this->pw->store($cfg['password'], $id_host);
      }
      /** @todo but might be heavy */
      /* if ($full) {

      }*/
    }

    return $id_host ?: null;
  }


  /**
   * Imports a database's structure into the options table.
   *
   * @param string $db   The database's name
   * @param string $host The connection's UID
   * @param bool   $full If true will connect to the database and get its structure
   * @return string|null The ID of the generated (or existing) database entry
   */
  public function importDb(string $db, string $host = '', $full = false): ?string
  {
    $id_db = null;
    if (Str::isUid($host)
        && ($engine = $this->o->parent($this->o->getIdParent($host)))
        && ($id_dbs = $this->o->fromCode('dbs', $engine['id']))
    ) {
      if (!($id_db = $this->o->fromCode($db, $id_dbs))) {
        if ($id_db = $this->o->add(
          [
            'id_parent' => $id_dbs,
            'text' => $db,
            'code' => $db,
          ]
        )
        ) {
          $this->o->setCfg($id_db, ['allow_children' => 1, 'show_code' => 1]);
        }
      }

      if ($id_db) {
        if (!($id_procedures = $this->o->fromCode('procedures', $id_db))
            && ($id_procedures = $this->o->add(
              [
                'id_parent' => $id_db,
                'text' => X::_('Procedures'),
                'code' => 'procedures',
              ]
            ))
        ) {
          $this->o->setCfg(
            $id_procedures,
            [
              'show_code' => 1,
              'show_value' => 1,
              'allow_children' => 1
            ]
          );
        }

        if (!($id_connections = $this->o->fromCode('connections', $id_db))
            && ($id_connections = $this->o->add(
              [
                'id_parent' => $id_db,
                'text' => X::_('Connections'),
                'code' => 'connections',
              ]
            ))
        ) {
          $this->o->setCfg(
            $id_connections,
            [
              'show_alias' => 1,
              'notext' => 1,
              'id_root_alias' => self::getOptionId('connections'),
              'root_alias' => 'Connections'
            ]
          );
        }

        if (!($id_functions = $this->o->fromCode('functions', $id_db))
            && ($id_functions = $this->o->add(
              [
                'id_parent' => $id_db,
                'text' => X::_('Function'),
                'code' => 'functions',
              ]
            ))
        ) {
          $this->o->setCfg(
            $id_functions,
            [
              'show_code' => 1,
              'show_value' => 1,
              'allow_children' => 1
            ]
          );
        }

        if (!($id_tables = $this->o->fromCode('tables', $id_db))
            && ($id_tables = $this->o->add(
              [
                'id_parent' => $id_db,
                'text' => X::_('Tables'),
                'code' => 'tables',
              ]
            ))
        ) {
          $this->o->setCfg(
            $id_tables,
            [
              'show_code' => 1,
              'show_value' => 1,
              'allow_children' => 1
            ]
          );
        }

        if ($id_connections && $id_functions && $id_procedures && $id_tables) {
          if (!$this->db->count(
            'bbn_options', [
              'id_parent' => $id_connections,
              'id_alias' => $host
            ]
          )) {
            $this->o->add([
              'id_parent' => $id_connections,
              'id_alias' => $host
            ]);
          }
          if ($full) {
            if (!empty($host)) {
              try {
                $conn = $this->connection($host, $engine['code'], $db);
              }
              catch (\Exception $e) {
                throw new \Exception(X::_("Impossible to connect"));
              }
              $tables = $conn->getTables($db);
              if (!empty($tables)) {
                foreach ($tables as $t) {
                  $this->importTable($t, $id_db, $host);
                }
              }
            }
          }
        }
        else{
          throw new \Exception(X::_("Impossible to find an host ID for DB")." ".$this->o->code($id_db));
        }
      }
    }

    return $id_db;
  }


  /**
   * Returns the ID of the current host for the given DB.
   *
   * @param string $id_db
   * @return string|null
   */
  public function retrieveHost(string $id_db): ?string
  {
    if ($this->check()
        && defined('BBN_DB_USER')
        && defined('BBN_DB_HOST')
        && ($connections = $this->o->fullOptions('connections', $id_db))
    ) {
      foreach ($connections as $c) {
        if ($c['alias']['code'] === BBN_DB_USER.'@'.BBN_DB_HOST) {
          return $c['alias']['id'];
        }
      }
    }

    return null;
  }


  /**
   * Imports a table's structure into the options table.
   *
   * @param string $table The table's name
   * @param bool   $id_db The database to which import the table (its id_parent)
   * @param string $host  The connection's code
   * @return string|null The ID of the generated table entry
   */
  public function importTable(string $table, string $id_db, string $host = ''): ?array
  {
    if (empty($host)) {
      $host_id = $this->retrieveHost($id_db);
    }
    else{
      $host_id = bbn\Str::isUid($host) ? $host : $this->hostId($host);
    }

    if ($host_id && ($id_tables = $this->o->fromCode('tables', $id_db))) {
      $engine = $this->o->parent($this->o->getIdParent($host_id));
      if (!($id_table = $this->o->fromCode($table, $id_tables))
          && ($id_table = $this->o->add(
            [
              'id_parent' => $id_tables,
              'text' => $table,
              'code' => $table,
            ]
          ))
      ) {
        $this->o->setCfg($id_table, ['allow_children' => 1]);
        if ($id_columns = $this->o->add(
          [
            'id_parent' => $id_table,
            'text' => X::_("Columns"),
            'code' => 'columns'
          ]
        )
        ) {
          $this->o->setCfg(
            $id_columns,
            [
              'show_code' => 1,
              'show_value' => 1,
              'sortable' => 1
            ]
          );
        }

        if ($id_keys = $this->o->add(
          [
            'id_parent' => $id_table,
            'text' => X::_("Keys"),
            'code' => 'keys',
          ]
        )
        ) {
          $this->o->setCfg(
            $id_keys,
            [
              'show_code' => 1,
              'show_value' => 1,
              'show_alias' => 1,
              'allow_children' => 1
            ]
          );
        }
      }
      else{
        $id_columns = $this->o->fromCode('columns', $id_table);
        $id_keys    = $this->o->fromCode('keys', $id_table);
      }

      $db = $this->o->code($id_db);
      if ($id_table
          && $id_columns
          && $id_keys
          && $db
          && ($conn = $this->connection($host_id, $engine['code'], $db))
          && ($m = $conn->modelize($db.'.'.$table))
          && !empty($m['fields'])
      ) {
        $num_cols     = 0;
        $num_cols_rem = 0;
        $fields       = [];
        $ocols        = array_flip($this->o->options($id_columns));
        foreach ($m['fields'] as $col => $cfg) {
          if ($opt_col = $this->o->option($col, $id_columns)) {
            $num_cols += (int)$this->o->set($opt_col['id'], bbn\X::mergeArrays($opt_col, $cfg));
          }
          elseif ($id = $this->o->add(
            bbn\X::mergeArrays(
              $cfg,
              [
                'id_parent' => $id_columns,
                'text' => $col,
                'code' => $col,
                'num' => $cfg['position']
              ]
            )
          )
          ) {
            $num_cols++;
            $opt_col       = $cfg;
            $opt_col['id'] = $id;
          }

          if ($opt_col) {
            $fields[$col] = $opt_col['id'];
          }

          if (isset($ocols[$col])) {
            unset($ocols[$col]);
          }
        }

        if (!empty($ocols)) {
          foreach ($ocols as $col => $id) {
            if (bbn\Str::isUid($id)) {
              $num_cols_rem += (int)$this->o->remove($id);
            }
          }
        }

        $num_keys     = 0;
        $num_keys_rem = 0;
        $okeys        = array_flip($this->o->options($id_keys));
        foreach ($m['keys'] as $key => $cfg) {
          $cols = $cfg['columns'] ?? [];
          unset($cfg['columns']);
          if (isset($cfg['ref_db'], $cfg['ref_table'], $cfg['ref_column'])
              && ($id_alias = $this->columnId($cfg['ref_column'], $cfg['ref_table'], $cfg['ref_db']))
          ) {
            $cfg['id_alias'] = $id_alias;
            unset($cfg['ref_db'], $cfg['ref_table'], $cfg['ref_column']);
          }

          if ($opt_key = $this->o->option($key, $id_keys)) {
            $num_keys += (int)$this->o->set($opt_key['id'], bbn\X::mergeArrays($opt_key, $cfg));
          }
          elseif ($id = $this->o->add(
            bbn\X::mergeArrays(
              $cfg, [
              'id_parent' => $id_keys,
              'text' => $key,
              'code' => $key
              ]
            )
          )
          ) {
            $this->o->setCfg(
              $id, [
              'show_code' => 1,
              'show_alias' => 1
              ]
            );
            $num_keys++;
            $opt_key       = $cfg;
            $opt_key['id'] = $id;
          }

          if (isset($okeys[$key])) {
            unset($okeys[$key]);
          }

          if ($opt_key && $cols) {
            foreach ($cols as $col){
              if (isset($fields[$col])) {
                if ($opt = $this->o->option($col, $opt_key['id'])) {
                  $this->o->set(
                    $opt['id'], bbn\X::mergeArrays(
                      $opt, [
                      'id_alias' => $fields[$col]
                      ]
                    )
                  );
                }
                else{
                  $tmp = [
                    'id_parent' => $opt_key['id'],
                    'id_alias' => $fields[$col],
                    'code' => $col,
                    'text' => $col
                  ];
                  if ($this->o->add($tmp)) {
                    $opt = $tmp;
                  }
                }
              }
            }
          }
        }

        if (!empty($okeys)) {
          foreach (array_values($okeys) as $id) {
            if (bbn\Str::isUid($id)) {
              $children = $this->o->items($id);
              foreach ($children as $cid) {
                $num_keys_rem += (int)$this->o->removeFull($cid);
              }

              $num_keys_rem += (int)$this->o->remove($id);
            }
          }
        }

        return [
          'columns' => $num_cols,
          'keys' => $num_keys,
          'columns_removed' => $num_cols_rem,
          'keys_removed' => $num_keys_rem
        ];
      }
    }

    return null;
  }


  /**
   * Import a table structure in the options table.
   *
   * @param string $table The table's name
   * @return string|null
   */
  public function import(string $table): ?array
  {
    $res = null;
    if ($m = $this->db->modelize($table)) {
      $tf    = explode('.', $this->db->tfn($table));
      $db    = $tf[0];
      $table = $tf[1];

      if (($id_host = $this->importHost($this->db->host))
          && ($id_db = $this->importDb($db, $id_host))
      ) {
        $res = $this->importTable($table, $id_db);
      }
    }

    return $res;
  }


  /**
   * Imports a whole database structure in the options table.
   *
   * @param string $db The database's name
   * @return array|null The database's model
   */
  public function importAll(string $db = ''): ?array
  {
    $res = null;
    if ($tables = $this->db->getTables($db)) {
      $res = [
        'tables' => 0,
        'columns' => 0,
        'keys' => 0
      ];
      foreach ($tables as $t){
        if ($tmp = $this->import(($db ?: $this->db->getCurrent()).'.'.$t)) {
          $res['tables']++;
          $res['columns'] += $tmp['columns'];
          $res['keys']    += $tmp['keys'];
        }
      }
    }

    return $res;
  }


  /**
   * Generates a grid configuration based on the table structure and columns options.
   *
   * @param string $table
   * @param string $db
   * @param string $host
   * @param string $engine
   *
   * @return array|null
   */
  public function getGridConfig(string $table, string $db = '', string $host = '', string $engine = 'mysql'): ?array
  {
    if ($model = $this->modelize($table, $db, $host, $engine)) {
      /** @var array The empty config, js for bbn-table, php for bbn\appui\grid */
      $res = [
        'js' => [
          'columns' => []
        ],
        'php' => [
          'tables' => [$table],
          'fields' => [],
          'join' => [],
          'order' => []
        ]
      ];
      if (!$db) {
        $db = $this->db->getCurrent();
      }

      /** @var string An alias which will be use as prefix for all aliases */
      $alias = Str::genpwd(5);
      /** @var int An incremental index for the tables alias */
      $tIdx  = 0;
      /** @var int An incremental index for the columns alias */
      $cIdx  = 0;
      foreach ($model['fields'] as $col => $f) {
        /** @var array The javascript column configuration */
        $js = [
          'text' => $col,
          'field' => $col
        ];
        $field = $table.'.'.$col;
        // Text should be defined before the option is changed in case of a single foreign key
        if (!empty($f['option'])) {
          // Taking the text from the option (which will be the col name if not defined)
          $js['text'] = $f['option']['text'];
        }

        /** @var bool|string The simple name of the unique column to display for the key  */
        $displayColumn = false;
        // Case where the column is part of a key
        if (!empty($model['cols'][$col])) {
          foreach ($model['cols'][$col] as $c) {
            if ($c === 'PRIMARY') {
              if (empty($f['option'])) {
                $f['editable'] = false;
              }
              else {
                $f['option']['editable'] = false;
              }
              $js['component'] = 'appui-database-data-binary';
              $js['cls'] = 'bbn-c';
              $js['width'] = 'bbn-c';
            }
            // Case where it is a foreign key
            elseif (!empty($model['keys'][$c]['ref_table'])) {
              // Incrementing the alias indexes as we'll use them
              $tIdx++;
              // Getting the model from the foreign table
              $tmodel = $this->modelize($model['keys'][$c]['ref_table']);
              // Looking for displayed columns configured
              if (isset($tmodel['option']) && !empty($tmodel['option']['dcolumns'])) {
                $dcols = [];
                foreach ($tmodel['option']['dcolumns'] as $dcol) {
                  $dcols[] = $this->db->cfn($dcol, $alias.'_t'.$tIdx, true);
                  if (!$displayColumn) {
                    $displayColumn = $dcol;
                  }
                }

                // Adding a single display column to the query
                if (count($dcols) === 1) {
                  $field = $displayColumn;
                }
                // Adding more display column as concat in the query
                else {
                  $field = "CONCAT(".X::join($dcols, ', ').")";
                }
              }
              else {
                // Otherwise looking for the first varchar
                foreach ($tmodel['fields'] as $tcol => $tf) {
                  if ($tf['type'] === 'varchar') {
                    $cIdx++;
                    // Adding the column to the query
                    $field = $alias.'_t'.$tIdx.'.'.$tcol;
                    $displayColumn = $tcol;
                    break;
                  }
                }
              }

              if ($displayColumn && (strpos($field, 'CONCAT(') !== 0)) {
                $f['option'] = $tf['option'];
                $f['option']['editor'] = 'appui-database-table-browser';
                $f['option']['options'] = [
                  'table' => $model['keys'][$c]['ref_table'],
                  'column' => $model['keys'][$c]['ref_column']
                ];
              }

              // Adding the JOIN part to the query
              $res['php']['join'][] = [
                'type' => $f['null'] ? 'left' : '',
                'table' => $model['keys'][$c]['ref_db'].'.'.$model['keys'][$c]['ref_table'],
                'alias' => $alias.'_t'.$tIdx,
                'on' => [
                  [
                    'field' => $alias.'_t'.$tIdx.'.'.$model['keys'][$c]['ref_column'],
                    'exp' => $table.'.'.$col
                  ]
                ]
              ];
              break;
            }
          }
        }

        $res['php']['fields'][$col] = $field;
        if (!empty($f['option'])) {
          $f = $f['option'];
        }

        // Taking all possible properties defined
        // Width
        if (empty($f['width'])) {
          if ($f['type'] === 'date') {
            $js['width'] = 100;
          }
          elseif ($f['type'] === 'datetime') {
            $js['width'] = 140;
          }
          elseif ($f['type'] === 'binary') {
            $js['width'] = 60;
          }
          elseif (!empty($f['maxlength']) && ($f['maxlength'] < 40)) {
            $js['width'] = $this->length2Width($f['maxlength']);
          }
          else {
            $js['minWidth'] = '40em';
          }
        }
        else {
          $js['width'] = $f['width'];
        }

        // For the cell view
        if (!empty($f['component'])) {
          $js['component'] = $f['component'];
        }

        // The editor/filter component
        if (!empty($f['editor'])) {
          $js['editor'] = $f['editor'];
        }
        elseif (empty($js['editor']) && (!isset($f['editable']) || $f['editable'])) {
          switch ($f['type']) {
            case 'int':
            case 'smallint':
            case 'tinyint':
            case 'bigint':
            case 'mediumint':
            case 'real':
            case 'double':
            case 'decimal':
            case 'float':
              $js['editor'] = 'bbn-numeric';
              $max = pow(10, $f['maxlength']) - 1;
              $js['options'] = [
                'max' => $max,
                'min' => $f['signed'] ? -$max : 0
              ];
              break;
            case 'date':
              $js['editor'] = 'bbn-datepicker';
              break;
            case 'datetime':
              $js['editor'] = 'bbn-datetimepicker';
              break;
            case 'json':
              $js['editor'] = 'bbn-json-editor';
              break;
            case 'enum':
            case 'set':
              $js['editor'] = 'bbn-dropdown';
              $src = [];
              if (!empty($f['extra'])) {
                $src = X::split(substr($f['extra'], 1, -1), "','");
              }

              // Calculating the length based on the longest enum value
              if (empty($js['width'])) {
                $maxlength = 1;
                foreach ($src as $s) {
                  $len = strlen($s);
                  if ($len > $maxlength) {
                    $maxlength = $len;
                  }
                }
              }

              $js['options'] = [
                'source' => $src
              ];
              break;
            case 'binary':
            case 'varbinary':
              $js['component'] = 'appui-database-data-binary';
              $js['cls'] = 'bbn-c';
              $js['editor'] = 'bbn-upload';
              break;
            case 'text':
            case 'bigtext':
            case 'smalltext':
            case 'tinytext':
            case 'mediumtext':
              $js['editor'] = 'bbn-textarea';
              break;
          }
        }


        $res['js']['columns'][] = $js;
        
      }

      return $res;
    }

    return null;
  }


  public function length2Width(int $length, $max = '30em'): string
  {
    if ($length > 32) {
      return $max;
    }
    elseif ($length > 25) {
      return '25em';
    }
    elseif ($length > 20) {
      return '20em';
    }
    elseif ($length > 15) {
      return '17em';
    }
    elseif ($length > 10) {
      return '13em';
    }
    elseif ($length > 5) {
      return '9em';
    }
    elseif ($length > 3) {
      return '5em';
    }

    return '3em';

  }


}
