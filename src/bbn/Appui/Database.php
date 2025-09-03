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
use bbn\Db;
use bbn\Appui\Passwords;
use bbn\Appui\History;
use Exception;

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
   * The current db connection object
   *
   * @var Db
   */
  private $currentConn;

  /**
   * The last alternative connection made with the connection function.
   * This is a longer description.
   * <code>
   * I can put code in it
   * </code>
   *
   * @var Db
   */
  protected $connections = [
    'mysql' => [],
    'pgsql' => [],
    'sqlite' => []
  ];


  public static $dbProps = [
    'id' => null,
    'name' => null,
    'text' => null,
    'engine' => null,
    'id_engine' => null,
    'id_host' => null,
    'num_tables' => 0,
    'num_connections' => 0,
    'num_procedures' => 0,
    'num_functions' => 0,
    'pcolumns' => [],
  ];


  public static $tableProps = [
    'id' => null,
    'name' => null,
    'text' => null,
    'engine' => null,
    'id_engine' => null,
    'id_host' => null,
    'database' => null,
    'id_database' => null,
    'num_columns' => 0,
    'num_keys' => 0,
    'num_constraints' => 0
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
   * @param Db $db The main database connection (where options are)
   */
  public function __construct(Db $db)
  {
    parent::__construct($db);
    self::optionalInit();
    $this->o = bbn\Appui\Option::getInstance();
    $this->currentConn = $db;
  }


  /**
   * Returns a connection with the given user@host selecting the given database.
   *
   * @param string $host A string user@host
   * @param string $db   The database name
   * @return Db|null
   */
  public function connection(string|null $host = null, string $engine = 'mysql', string $db = ''): Db
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

      if (!isset($this->connections[$parent['code']][$cfg['code'] . $db])
        || !$this->connections[$parent['code']][$cfg['code'] . $db]->check()
      ) {
        switch ($parent['code']) {
          case 'mysql':
          case 'pgsql':
            if (strpos($cfg['code'], '@')) {
              $bits = bbn\X::split($cfg['code'], '@');
              if (count($bits) === 2) {
                if (!($password = $this->getPassword($id_host))) {
                  throw new \Exception(X::_("No password for %s", $cfg['code']));
                }
                $db_cfg = [
                  'engine' => $parent['code'],
                  'host' => $bits[1],
                  'port' => !empty($cfg['port']) ? $cfg['port'] : null,
                  'db' => $db,
                  'user' => $bits[0],
                  'pass' => $password
                ];
              }
              else {
                $db_cfg = [
                  'engine' => $parent['code'],
                  'host' => $cfg['code'],
                  'port' => !empty($cfg['port']) ? $cfg['port'] : null,
                  'db' => $db
                ];
              }

              try {
                $this->connections[$parent['code']][$cfg['code'] . $db] = new Db($db_cfg);
              }
              catch (\Exception $e) {
                throw new \Exception($e->getMessage());
              }
            }
            break;

          case 'sqlite':
            $pbits = X::split($cfg['code'], '/');
            foreach ($pbits as &$bit) {
              if (str_starts_with($bit, 'BBN_') && defined($bit)) {
                $bit = constant($bit);
                if (str_ends_with($bit, '/')) {
                  $bit = rtrim($bit, '/');
                }
              }
            }

            $cfg['path'] = X::join($pbits, '/');
            if (empty($db) || empty($cfg['path']) || !file_exists($cfg['path'].'/'.$db)) {
              throw new \Exception(X::_('db or path empty'));
            }

            $db_cfg = [
              'engine' => 'sqlite',
              'db' => $cfg['path'].'/'.$db
            ];
            try {
              $this->connections[$parent['code']][$cfg['code'] . $db] = new Db($db_cfg);
            }
            catch (\Exception $e) {
              throw new \Exception($e->getMessage());
            }
            break;

          default:
            throw new \Exception(X::_('Impossible to find the engine').' '.$cfg['engines']);
        }
      }

      if (isset($this->connections[$parent['code']][$cfg['code'] . $db])) {
        $this->currentConn = $this->connections[$parent['code']][$cfg['code'] . $db];
        return $this->currentConn;
      }
    }

    throw new \Exception(X::_("Impossible to get a connection for").' '.$cfg['code']);
  }


  public function engineId(string $engine): ?string
  {
    return self::getOptionId($engine, 'engines');
  }


  public function engineCode(string $engineId): ?string
  {
    if (bbn\Str::isUid($engineId)) {
      return $this->o->code($engineId) ?: null;
    }

    return null;
  }


  public function engineIdFromHost(string $hostId): ?string
  {
    if (($idEngineTemplate = $this->o->getTemplateId('engine'))
      && bbn\Str::isUid($hostId)
      && ($idParent = $this->o->getIdParent($hostId))
      && ($idEngines = self::getOptionId('engines'))
    ) {
      while (!empty($idParent) && ($idParent !== $idEngines)) {
        $idParent = $this->o->getIdParent($idParent);
        if (!($o = $this->o->option($idParent))) {
          return null;
        }

        if ($o['id_alias'] === $idEngineTemplate) {
          return $o['id'];
        }
      }
    }

    return null;
  }


  public function enginePcolumns(string $engineId): array
  {
    if (!Str::isUid($engineId)) {
      $engineId = $this->engineId($engineId);
    }

    if (!empty($engineId)
      && ($idPcolumns = $this->o->fromCode('pcolumns', $engineId))
    ) {
      return array_map(function($item) {
        unset($item['id_parent'], $item['id_alias'], $item['num'], $item['num_children']);
        return $item;
      }, $this->o->fullOptions($idPcolumns));
    }

    return [];
  }


  public function engineDataTypes(string $engineCode): array
  {
    if (Str::isUid($engineCode)) {
      $engineCode = $this->engineCode($engineCode);
    }

    if (!empty($engineCode)) {
      $c = "bbn\\Db\\Languages\\". ucfirst($engineCode);
      if (class_exists($c)) {
        return $c::getTypes();
      }
    }

    return [];
  }


  public function engines(): array
  {
    if (($engines = self::getOptionId('engines'))
      && ($codes = $this->o->getCodes($engines))
    ) {
      return array_values($codes);
    }

    return [];
  }


  /**
   * Returns the ID of a connection.
   *
   * @param string $host The connection code (user@host or host)
   * @return null|string
   */
  public function hostId(string|null $host, string $engine = 'mysql'): ?string
  {
    if (bbn\Str::isUid($host)) {
      return $host;
    }

    if (empty($host)) {
      $host = $this->db->getConnectionCode();
    }

    $r = self::getOptionId($host, 'connections', $engine, 'engines');
    return $r ?: null;
  }


  /**
   * Returns the code of a connection.
   *
   * @param string $hostId The connection ID
   * @return null|string
   */
  public function hostCode(string $hostId): ?string
  {
    return $this->o->code($hostId) ?: null;
  }


  /**
   * Returns the number of connections in the options.
   *
   * @return int|null
   */
  public function countHosts(string $engine = 'mysql'): ?int
  {
    if (($id_parent = self::getOptionId('connections', $engine, 'engines'))
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
    if (($id_parent = self::getOptionId('connections', $engine, 'engines'))
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
    if (($id_parent = self::getOptionId('connections', $engine, 'engines'))
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

    if (($id_parent = self::getOptionId('dbs', $engine, 'engines'))
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
      $num = $this->o->count(self::getOptionId('dbs', $engine, 'engines'));
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
      $arr = $this->o->fullOptions(self::getOptionId('dbs', $engine, 'engines'));
    }
    elseif (!bbn\Str::isUid($host)) {
      $host = $this->hostId($host, $engine);
    }

    if ($host) {
      $arr = array_map(
        fn($a) => $this->o->parent($a['id_parent']),
        $this->o->getAliases($host)
      );
    }
    if (!empty($arr)) {
      return array_map(
        fn($a) => [
          'id' => $a['id'],
          'text' => $a['text'],
          'name' => $a['code']
        ],
        $arr
      );
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
    if (!bbn\Str::isUid($engine)) {
      $engineId = $this->engineId($engine);
    }
    else {
      $engineId = $engine;
      $engine = $this->engineCode($engineId);
    }

    if ($dbs = $this->dbs($host, $engine)) {
      $hostId = $this->hostId($host, $engine);
      foreach ($dbs as &$db) {
        $db = $this->fullDb($db, $hostId, $engineId);
      }

      return $dbs;
    }

    return [];
  }


  public function fullDb(string|array $db, ?string $host = null, ?string $engine = null): ?array
  {

    if (empty($engine)) {
      $engine = $this->db->getEngine();
    }

    if (bbn\Str::isUid($engine)) {
      $engineId = $engine;
      $engine = $this->engineCode($engineId);
    }
    else {
      $engineId = $this->engineId($engine);
    }

    if (empty($host)) {
      $host = $this->db->getConnectionCode();
    }

    $hostId = $this->hostId($host, $engine);
    if (is_array($db)) {
      $dbData = $db;
    }
    else {
      if (!Str::isUid($db)) {
        $db = $this->dbId($db, $hostId, $engine);
      }

      $dbData = $this->o->option($db);
    }

    if (empty($dbData)) {
      $dbData = static::$dbProps;
    }

    if (!empty($dbData)) {
      $r =  X::mergeArrays(static::$dbProps, [
        'id' => $dbData['id'],
        'text' => $dbData['text'],
        'name' => $dbData['code'] ?? $dbData['name'],
        'engine' => $engine,
        'id_engine' => $engineId,
        'id_host' => $hostId
      ]);
      if ($idTables = $this->o->fromCode('tables', $r['id'])) {
        $r['num_tables'] = $this->o->count($idTables);
      }

      if ($idConnections = $this->o->fromCode('connections', $r['id'])) {
        $r['num_connections'] = $this->o->count($idConnections);
      }

      if ($idProcedures = $this->o->fromCode('procedures', $r['id'])) {
        $r['num_procedures'] = $this->o->count($idProcedures);
      }

      if ($idFunctions = $this->o->fromCode('functions', $r['id'])) {
        $r['num_functions'] = $this->o->count($idFunctions);
      }

      return $r;
    }

    return null;
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
  public function fullTables(string $db = '', string $host = '', string $engine = 'mysql'): array
  {
    if (!bbn\Str::isUid($engine)) {
      $engineId = $this->engineId($engine);
    }
    else {
      $engineId = $engine;
      $engine = $this->engineCode($engineId);
    }

    $hostId = $this->hostId($host, $engine);
    if ($tables = $this->tables($db, $host, $engine)) {
      foreach ($tables as &$table) {
        $table = $this->fullTable($table, $db, $hostId, $engineId);
      }

      return $tables;
    }

    return [];
  }


  public function fullTable(
    string|array $table,
    ?string $db = null,
    ?string $host = null,
    ?string $engine = null
  ): ?array
  {
    if (empty($engine)) {
      $engine = $this->db->getEngine();
    }

    if (bbn\Str::isUid($engine)) {
      $engineId = $engine;
      $engine = $this->engineCode($engineId);
    }
    else {
      $engineId = $this->engineId($engine);
    }

    if (empty($host)) {
      $host = $this->db->getConnectionCode();
    }

    $hostId = $this->hostId($host, $engine);
    if (empty($db)) {
      $db = $this->db->getCurrent();
    }

    $dbId = $this->dbId($db, $hostId, $engine);
    if (is_array($table)) {
      $tableData = $table;
    }
    else if ($tableId = Str::isUid($table) ? $table : $this->tableId($table, $dbId, $hostId, $engine)) {
      $tableData = $this->o->option($tableId);
    }

    if (empty($tableData)) {
      $tableData = static::$tableProps;
    }

    if (!empty($tableData)) {
      $r =  X::mergeArrays(static::$tableProps, [
        'id' => $tableData['id'],
        'text' => $tableData['text'] ?? (!Str::isUid($table) ? $table : null),
        'name' => $tableData['code'] ?? $tableData['name'] ?? (!Str::isUid($table) ? $table : null),
        'engine' => $engine,
        'id_engine' => $engineId,
        'id_host' => $hostId,
        'database' => $db,
        'id_database' => $dbId
      ]);
      if ($idColumns = $this->o->fromCode('columns', $r['id'])) {
        $r['num_columns'] = $this->o->count($idColumns);
      }

      if ($idKeys = $this->o->fromCode('keys', $r['id'])) {
        $r['num_keys'] = $this->o->count($idKeys);
        $r['num_constraints'] = 0;
        if ($keys = $this->o->fullOptions($idKeys)) {
          $r['num_constraints'] = count(array_filter($keys, fn($k) => !empty($k['constraint']) && !empty($k['ref_table']) && !empty($k['ref_column'])));
        }
      }

      return $r;
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
    if (($id_db = $this->dbIdFromItem($id_keycol))
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
    if (Str::isUid($table)) {
      $res = $this->o->fromCode($key, 'keys', $table);
      return $res;
    }

    $t = $this->db->tsn($table);
    if (!Str::isUid($db)) {
      $db = $this->dbId($db);
    }

    if (Str::isUid($db) && ($tmp = $this->o->fromCode($key, 'keys', $t, 'tables', $db))) {
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
        $db = $this->o->code($db);
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

    if (empty($table)) {
      $res = [];
      foreach ($conn->getTables() as $t) {
        $tsn = $conn->tsn($t);
        $res[$tsn] = $this->modelize($tsn, $db, $host, $engine);
      }
      return $res;
    }

    $table_id = '';
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
    if (($id_parent = self::getOptionId('connections', $engine, 'engines'))
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
    if (!bbn\Str::isUid($host)) {
      throw new \Exception(_("Invalid host ID"));
    }
    else if (!$this->o->exists($host)) {
      throw new \Exception(X::_("Impossible to find the host with ID \"%s\"", $host));
    }

    if (!($engineId = $this->engineIdFromHost($host))) {
      throw new \Exception(X::_("Impossible to find the engine ID for the host \"%s\"", $host));
    }

    if (!($engine = $this->engineCode($engineId))) {
      throw new \Exception(X::_("Impossible to find the engine code for the host \"%s\"", $host));
    }

    if (!($idTemplate = $this->o->getTemplateId('database'))) {
      throw new \Exception(X::_("Impossible to find the template \"%s\"", 'database'));
    }

    if (Str::isUid($host)
      && ($id_dbs = $this->o->fromCode('dbs', $engineId))
    ) {
      if (!($id_db = $this->o->fromCode($db, $id_dbs))) {
        $id_db = $this->addDatabase($db, $host);
      }
      else if (($this->o->getIdAlias($id_db) !== $idTemplate)
        && $this->o->setAlias($id_db, $idTemplate)
      ) {
        $this->o->applyTemplate($id_db);
      }

      if ($id_db) {
        if (($id_connections = $this->o->fromCode('connections', $id_db))
          && $this->o->fromCode('procedures', $id_db)
          && $this->o->fromCode('functions', $id_db)
          && $this->o->fromCode('tables', $id_db)
        ) {
          $optCfg = $this->o->getClassCfg();
          $optFields = $optCfg['arch']['options'];
          if (!$this->db->count($optCfg['table'], [
            $optFields['id_parent'] => $id_connections,
            $optFields['id_alias'] => $host
          ])) {
            $this->o->add([
              $optFields['id_parent'] => $id_connections,
              $optFields['id_alias'] => $host
            ]);
          }
          if ($full) {
            if (!empty($host)) {
              try {
                $conn = $this->connection($host, $engine, $db);
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
        if ($c['alias']['code'] === constant('BBN_DB_USER') . '@' . constant('BBN_DB_HOST')) {
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
   * @param bool   $full  If true will connect to the database and import its structure
   * @return string|null The ID of the generated table entry
   */
  public function importTable(
    string $table,
    string $id_db,
    string $host = '',
    bool $full = true
  ): ?array
  {
    if (empty($host)) {
      $host_id = $this->retrieveHost($id_db);
    }
    else{
      $host_id = bbn\Str::isUid($host) ? $host : $this->hostId($host);
    }

    if (!bbn\Str::isUid($host_id)) {
      throw new \Exception(_("Invalid host ID"));
    }
    else if (!$this->o->exists($host_id)) {
      throw new \Exception(X::_("Impossible to find the host with ID \"%s\"", $host_id));
    }

    if (!($engineId = $this->engineIdFromHost($host_id))) {
      throw new \Exception(X::_("Impossible to find the engine ID for the host \"%s\"", $host_id));
    }

    if (!($engine = $this->engineCode($engineId))) {
      throw new \Exception(X::_("Impossible to find the engine code for the host \"%s\"", $host_id));
    }

    if (!($idTemplate = $this->o->getTemplateId('table'))) {
      throw new \Exception(X::_("Impossible to find the template \"%s\"", 'table'));
    }

    if (!empty($host_id)
      && ($id_tables = $this->o->fromCode('tables', $id_db))
    ) {
      if (!($id_table = $this->o->fromCode($table, $id_tables))) {
        $id_table = $this->addTable($table, $id_db, $host_id);
      }
      else {
        if (($this->o->getIdAlias($id_table) !== $idTemplate)
          && $this->o->setAlias($id_table, $idTemplate)
        ) {
          $this->o->applyTemplate($id_table);
        }
      }

      if (!empty($id_table)
        && !empty($full)
        && ($id_columns = $this->o->fromCode('columns', $id_table))
        && ($id_keys = $this->o->fromCode('keys', $id_table))
        && ($db = $this->o->code($id_db))
        && ($conn = $this->connection($host_id, $engine, $db))
        && ($m = $conn->modelize($db.'.'.$table))
        && !empty($m['fields'])
      ) {
        $num_cols     = 0;
        $num_cols_rem = 0;
        $fields       = [];
        $ocols = $this->o->codeIds($id_columns);
        if (!is_array($ocols)) {
          X::ddump(
            $ocols,
            $this->o->option($id_columns),
            $this->o->option($id_keys),
            $db
          );
        }
        foreach ($m['fields'] as $col => $cfg) {
          if ($optColId = $this->importColumn($col, $id_table, $host_id, $cfg)) {
            $num_cols++;
            $fields[$col] = $optColId;
          }
          /* if ($opt_col = $this->o->option($col, $id_columns)) {
            $num_cols += (int)$this->o->set($opt_col['id'], bbn\X::mergeArrays($opt_col, $cfg, [
              'text' => $opt_col['text'] === $opt_col['code'] ? $col : $opt_col['text'],
              'code' => $col,
              'num' => $cfg['position']
            ]));
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
          } */

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
              'relations' => 'alias'
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

              $num_keys_rem += (int)$this->o->removeFull($id);
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


  public function importColumn(string $column, string $tableId, string $hostId, ?array $cfg = null): ?string
  {
    if (!bbn\Str::isUid($tableId)) {
      throw new \Exception(_("Invalid table ID"));
    }
    else if (!$this->o->exists($tableId)) {
      throw new \Exception(X::_("Impossible to find the table with ID \"%s\"", $tableId));
    }

    if (!($table = $this->o->code($tableId))) {
      throw new \Exception(_("Invalid code for table ID \"%s\"", $tableId));
    }

    if (!($dbId = $this->dbIdFromTable($tableId))) {
      throw new \Exception(X::_("Impossible to find the database from the table with ID \"%s\"", $tableId));
    }

    if (!($db = $this->o->code($dbId))) {
      throw new \Exception(X::_("Impossible to get the database's code with ID \"%s\"", $dbId));
    }

    if (!bbn\Str::isUid($hostId)) {
      throw new \Exception(_("Invalid host ID"));
    }
    else if (!$this->o->exists($hostId)) {
      throw new \Exception(X::_("Impossible to find the host with ID \"%s\"", $hostId));
    }

    if (!($engineId = $this->engineIdFromHost($hostId))) {
      throw new \Exception(X::_("Impossible to find the engine ID for the host \"%s\"", $hostId));
    }

    if (!($engine = $this->engineCode($engineId))) {
      throw new \Exception(X::_("Impossible to find the engine code for the engine \"%s\"", $engineId));
    }

    if (!($columnsId = $this->o->fromCode('columns', $tableId))) {
      throw new \Exception(X::_("Impossible to find the columns ID for the table \"%s\"", $tableId));
    }

    if ((empty($cfg) || !is_array($cfg))
      && ($conn = $this->connection($hostId, $engine, $db))
      && ($m = $conn->modelize($table))
      && !empty($m['fields'][$column])
    ) {
      $cfg = $m['fields'][$column];
    }

    if (!empty($cfg)) {
      if ($opt = $this->o->option($column, $columnsId)) {
        if ($this->o->set($opt['id'], X::mergeArrays($opt, $cfg, [
          'text' => $opt['text'] === $opt['code'] ? $column : $opt['text'],
          'code' => $column,
          'num' => $cfg['position']
        ]))) {
          return $opt['id'];
        }
      }
      elseif ($id = $this->o->add(
        X::mergeArrays(
          $cfg,
          [
            'id_parent' => $columnsId,
            'text' => $column,
            'code' => $column,
            'num' => $cfg['position']
          ]
        )
      )) {
        return $id;
      }
    }

    return null;
  }


  public function importKey()
  {}


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

      if (($id_host = $this->importHost($this->db->getHost(), $this->db->getEngine(), $this->db->getCfg()))
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

  private string $alias;

  /**
   * Generate a new alias in the alias property
   */
  private function generateNewAlias()
  {
    $this->alias = Str::genpwd(5);
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
          'table' => $table,
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
        $field = $table.'.'.$col;

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
            }
            // Case where it is a foreign key
            elseif (!empty($model['keys'][$c]['ref_table'])) {
              // Incrementing the alias indexes as we'll use them
              $tIdx++;
              // Getting the model from the foreign table
              $tmodel = $this->modelize($model['keys'][$c]['ref_table'], $model['keys'][$c]['ref_db'], $host, $engine);
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
              // Looking for displayed columns configured
              if (isset($tmodel['option']) && !empty($tmodel['option']['dcolumns'])) {
                $dcols = [];
                $dcIdx = 0;
                foreach ($tmodel['option']['dcolumns'] as $dcol) {
                  if (strpos($dcol, ':')) {
                    [$tmp1, $tmp2] = X::split($dcol, ':');
                    [$originField, $extPrimary] = X::split($tmp1, '.');
                    [$extTable, $extField] = X::split($tmp2, '.');
                    $dcIdx++;
                    // Adding the JOIN part to the query
                    $res['php']['join'][] = [
                      'type' => 'left',
                      'table' => $extTable,
                      'alias' => $alias.'_t'.$tIdx.'_dc'.$dcIdx,
                      'on' => [
                        [
                          'field' => $alias.'_t'.$tIdx.'_dc'.$dcIdx.'.'.$extPrimary,
                          'exp' => $table.'.'.$originField
                        ]
                      ]
                    ];
                    $dc = $this->db->cfn($extField, $alias.'_t'.$tIdx.'_dc'.$dcIdx);
                  }
                  else {
                    $dc = $this->db->cfn($dcol, $alias.'_t'.$tIdx);
                  }

                  $dcols[] = $dc;
                  if (!$displayColumn) {
                    //$displayColumn = $dcol;
                    $displayColumn = $dc;
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
                if (!isset($f['option']['editor'])) {
                  $f['option']['editor'] = 'appui-database-table-browser';
                  $f['option']['options'] = [
                    'table' => $model['keys'][$c]['ref_table'],
                    'column' => $model['keys'][$c]['ref_column']
                  ];
                }
              }

              break;
            }
          }
        }

        $res['php']['fields'][$col] = $field;
        if (!empty($f['option'])) {
          $f = $f['option'];
        }
      }

      return $res;
    }

    return null;
  }


  public function addDatabase(string $name, string $hostId): ?string
  {
    if (!bbn\Str::isUid($hostId)) {
      throw new \Exception(_("Invalid host ID"));
    }
    else if (!$this->o->exists($hostId)) {
      throw new \Exception(X::_("Impossible to find the host with ID \"%s\"", $hostId));
    }

    if (!($idTemplate = $this->o->getTemplateId('database'))) {
      throw new \Exception(X::_("Impossible to find the template \"%s\"", 'database'));
    }

    if (!($engineId = $this->engineIdFromHost($hostId))) {
      throw new \Exception(X::_("Impossible to find the engine ID for the host \"%s\"", $hostId));
    }

    if ($idDbs = $this->o->fromCode('dbs', $engineId)) {
      if ($this->o->fromCode($name, $idDbs)) {
        throw new \Exception(X::_("The database \"%s\" already exists in the options", $name));
      }

      $optCfg = $this->o->getClassCfg();
      $optFields = $optCfg['arch']['options'];
      if ($idDb = $this->o->add([
        $optFields['id_parent'] => $idDbs,
        $optFields['id_alias'] => $idTemplate,
        $optFields['text'] => $name,
        $optFields['code'] => $name
      ])) {
        $this->o->applyTemplate($idDb);
        if ($idConnections = $this->o->fromCode('connections', $idDb)) {
          if (!$this->db->count($optCfg['table'], [
              $optFields['id_parent'] => $idConnections,
              $optFields['id_alias'] => $hostId
            ])
            && ($idConn = $this->o->add([
              $optFields['id_parent'] => $idConnections,
              $optFields['id_alias'] => $hostId,
            ]))
            && ($pass = new Passwords($this->db))
            && ($p = $pass->get($hostId))
          ) {
            $pass->store($p, $idConn);
          }

          return $idDb;
        }
      }
    }

    return null;
  }


  public function removeDatabase(string $id): int
  {
    if (!bbn\Str::isUid($id)) {
      throw new \Exception(_("Invalid database ID"));
    }
    else if (!$this->o->exists($id)) {
      throw new \Exception(X::_("Impossible to find the database with ID \"%s\"", $id));
    }

    return $this->o->removeFull($id);
  }


  public function renameDatabase(string $id, string $name): bool
  {
    if (!bbn\Str::isUid($id)) {
      throw new \Exception(_("Invalid database ID"));
    }
    else if (!$this->o->exists($id)) {
      throw new \Exception(X::_("Impossible to find the database with ID \"%s\"", $id));
    }

    if (empty($name)) {
      throw new \Exception(_("The database name cannot be empty"));
    }

    $r1 = $this->o->setText($id, $name);
    $r2 = $this->o->setCode($id, $name);
    return $r1 && $r2;
  }


  public function duplicateDatabase(string $id, string $name): bool
  {
    if (!bbn\Str::isUid($id)) {
      throw new \Exception(_("Invalid database ID"));
    }
    else if (!$this->o->exists($id)) {
      throw new \Exception(X::_("Impossible to find the database with ID \"%s\"", $id));
    }

    if (empty($name)) {
      throw new \Exception(_("The database name cannot be empty"));
    }

    if (($idParent = $this->o->getIdParent($id))
      && ($idNew = $this->o->duplicate($id, $idParent, true, false, true))
    ) {
      $r1 = $this->o->setText($idNew, $name);
      $r2 = $this->o->setCode($idNew, $name);
      return $r1 && $r2;
    }

    return false;
  }


  public function addTable(string $name, string $dbId, string $hostId): ?string
  {
    if (!bbn\Str::isUid($hostId)) {
      throw new \Exception(_("Invalid host ID"));
    }
    else if (!$this->o->exists($hostId)) {
      throw new \Exception(X::_("Impossible to find the host with ID \"%s\"", $hostId));
    }

    if (!($idTemplate = $this->o->getTemplateId('table'))) {
      throw new \Exception(X::_("Impossible to find the template \"%s\"", 'table'));
    }

    if ($idTables = $this->o->fromCode('tables', $dbId)) {
      if ($this->o->fromCode($name, $idTables)) {
        throw new \Exception(X::_("The table \"%s\" already exists in the options", $name));
      }

      $optCfg = $this->o->getClassCfg();
      $optFields = $optCfg['arch']['options'];
      if ($idTable = $this->o->add([
        $optFields['id_parent'] => $idTables,
        $optFields['id_alias'] => $idTemplate,
        $optFields['text'] => $name,
        $optFields['code'] => $name
      ])) {
        $this->o->applyTemplate($idTable);
        return $idTable;
      }
    }

    return null;
  }


  /**
   * Renames a table
   *
   * @param string $id The table ID
   * @param string $name The new table name
   * @return bool True on success, false on failure
   */
  public function renameTable(string $id, string $name): bool
  {
    if (!bbn\Str::isUid($id)) {
      throw new \Exception(_("Invalid table ID"));
    }
    else if (!$this->o->exists($id)) {
      throw new \Exception(X::_("Impossible to find the table with ID \"%s\"", $id));
    }

    if (empty($name)) {
      throw new \Exception(_("The table name cannot be empty"));
    }

    $r1 = $this->o->setText($id, $name);
    $r2 = $this->o->setCode($id, $name);
    return $r1 && $r2;
  }


  /**
   * Removes a table
   *
   * @param string $id The table ID
   * @return int The number of affected rows
   */
  public function removeTable(string $id): int
  {
    if (!bbn\Str::isUid($id)) {
      throw new \Exception(_("Invalid table ID"));
    }
    else if (!$this->o->exists($id)) {
      throw new \Exception(X::_("Impossible to find the table with ID \"%s\"", $id));
    }

    return $this->o->removeFull($id);
  }


  public function infoDb(string $dbName, string $hostId, string $engine): ?array
  {
    if (!Str::isUid($hostId)) {
      $hostId = $this->hostId($hostId, $engine);
    }

    if (!empty($hostId)
      && ($engine = Str::isUid($engine) ? $this->engineCode($engine) : $engine)
      && ($engineId = $this->engineId($engine))
    ) {
      $dbId = $this->dbId($dbName, $hostId, $engine);
      try {
        $conn = $this->connection($hostId, $engine, $dbName);
      }
      catch (\Exception $e) {}

      $r = X::mergeArrays(
        !empty($dbId) ? ($this->fullDb($dbId, $hostId, $engineId) ?: []) : static::$dbProps,
        [
          'id' => $dbId,
          'name' => $dbName,
          'text' => $dbName,
          'engine' => $engine,
          'id_engine' => $engineId,
          'host' => $this->hostCode($hostId),
          'id_host' => $hostId,
          'is_real' => !empty($conn),
          'is_virtual' => !empty($dbId),
          'num_real_tables' => $conn ? count($conn->getTables($dbName)) : 0,
          'num_real_procedures' => 0,
          'num_real_functions' => 0,
          'size' => $conn ? $conn->dbSize($dbName) : 0,
          'charset' => $conn ? $conn->getDatabaseCharset($dbName) : '',
          'collation' => $conn ? $conn->getDatabaseCollation($dbName) : '',
          'last_check' => date('Y-m-d H:i:s')
        ]
      );
      if ($conn) {
        $conn->close();
      }

      return $r;
    }

    return null;
  }


  public function infoTable(string $tableName, string $dbName, string $hostId, string $engine): ?array
  {
    if (!Str::isUid($hostId)) {
      $hostId = $this->hostId($hostId, $engine);
    }

    if (!empty($hostId)
      && ($engine = Str::isUid($engine) ? $this->engineCode($engine) : $engine)
      && ($engineId = $this->engineId($engine))
    ) {
      $dbId = $this->dbId($dbName, $hostId, $engine);
      $tableId = $this->tableId($tableName, $dbId ?: $dbName, $hostId, $engine);
      $isReal = false;
      try {
        if ($conn = $this->connection($hostId, $engine, $dbName)) {
          $isReal = $conn->tableExists($tableName);
        }
      }
      catch (\Exception $e) {}

      $keys = $isReal ? $conn->getKeys($tableName) : [];
      $constrainsts = isset($keys['keys']) ?
        array_filter(
          $keys['keys'],
          fn($k) => !empty($k['constraint']) && !empty($k['ref_table']) && !empty($k['ref_column'])
        ) :
        [];

      $r = X::mergeArrays(
        !empty($tableId) ? ($this->fullTable($tableId, $dbId ?: $dbName, $hostId, $engineId) ?: []) : static::$tableProps,
        [
          'id' => $tableId,
          'name' => $tableName,
          'text' => $tableName,
          'engine' => $engine,
          'id_engine' => $engineId,
          'host' => $this->hostCode($hostId),
          'id_host' => $hostId,
          'database' => $dbName,
          'id_database' => $dbId,
          'is_real' => $isReal,
          'is_virtual' => !empty($tableId),
          'num_real_columns' => $isReal ? count($conn->getColumns($tableName)) : 0,
          'num_real_keys' => isset($keys['keys']) ? count($keys['keys']) : 0,
          'num_real_constraints' =>  count($constrainsts),
          'size' => $isReal ? $conn->tableSize($tableName) : 0,
          'charset' => $isReal ? $conn->getTableCharset($tableName) : '',
          'collation' => $isReal ? $conn->getTableCollation($tableName) : '',
          'last_check' => date('Y-m-d H:i:s'),
          'keys' => $isReal ? $conn->getKeys($tableName) : [],
        ]
      );
      if ($conn) {
        $conn->close();
      }

      return $r;
    }

    return null;
  }


  public function getDisplayConfig(string $table, int $level = 0): array
  {
    $opt =& $this->o;
    $db =& $this->currentConn;
    $ocf = $opt->getClassCfg();
    $res = ['table' => $table];
    $cfg = $this->modelize($table);
    if ($level && !empty($cfg['option']) && !empty($cfg['option']['viewer'])) {
      $res = [
        'table' => $table,
        'component' => $cfg['option']['viewer'],
        'componentOptions' => $cfg['option']['componentOptions'] ?? $cfg['option']['options'] ?? [],
        'columns' => empty($cfg['option']['dcolumns']) ? array_keys($cfg['fields']) : $cfg['option']['dcolumns'],
        'labels' => []
      ];
      
      foreach ($res['columns'] as $c) {
        $res['labels'][$c] = $cfg['fields'][$c]['text'] ?? $c;
      }

      return $res;
    }

    foreach ($cfg['fields'] as $name => $f) {
      if ($level && !empty($cfg['option']['dcolumns']) && !in_array($name, $cfg['option']['dcolumns'])) {
        continue;
      }
      $isDef = false;
      if (!empty($f['option']) && !empty($f['option']['viewer'])) {
        $def = [
          'component' => $f['option']['viewer'],
          'componentOptions' => $f['option']['componentOptions'] ?? $f['option']['options'] ?? []
        ];
        $isDef = true;
      }
      elseif ($f['key']) {
        foreach ($cfg['cols'][$name] as $k) {
          if ($k === 'PRIMARY') {
            $def = [
              'component' => 'appui-database-data-binary',
              'componentOptions' => [
                'table' => $table,
                'column' => $name
              ]
            ];
            $isDef = true;
            break;
          }

          if (!empty($cfg['keys'][$k]['ref_column'])) {
            if ($cfg['keys'][$k]['ref_table'] === $ocf['table']) {
              $def = ['option' => true];
            }
            else if ($level < 3) {
              $def = $this->getDisplayConfig($cfg['keys'][$k]['ref_table'], $level + 1);
              $def['column'] = $cfg['keys'][$k]['ref_column'];
            }
            else {
              $def = [
                'component' => 'appui-database-data-binary',
                'componentOptions' => [
                  'table' => $cfg['keys'][$k]['ref_table'],
                  'column' => $cfg['keys'][$k]['ref_column']
                ]
              ];
              $def['table'] = $cfg['keys'][$k]['ref_table'];
              $def['column'] = $cfg['keys'][$k]['ref_column'];
            }
            $isDef = 1;
            break;
          }
        }
      }

      if (!$isDef) {
        if ($db->isNumericType($f['type'])) {
          $def = ['type' => 'number', 'length' => $f['maxlength'] ?? null, 'signed' => $f['signed'] ?? true];
        }
        elseif ($db->isTextType($f['type'])) {
          $def = ['type' => 'text', 'length' => $f['maxlength'] ?? null];
        }
        elseif ($db->isBinaryType($f['type'])) {
          $def = ['type' => 'binary', 'length' => $f['maxlength'] ?? null];
        }
        elseif ($db->isDateType($f['type'])) {
          $def = ['type' => 'date'];
        }
        else {
          $def = ['type' => 'unknown'];
        }
      }

      $def['label'] = isset($f['option']) && !empty($f['option']['text']) ? $f['option']['text'] : $name;
      $res['fields'][$name] = $def;
    }

    return $res;
  }


  public function getDisplayRecord(
    string $table,
    array $cfg,
    array $where,
    $when = null,
    array &$alreadyShown = []
  ): array
  {
    $opt =& $this->o;
    $db =& $this->currentConn;
    $res = ['table' => $cfg['table']];
    $dbCfg = [
      'tables' => [$table],
      'where' => $where
    ];
    $swhere = [];
    foreach ($where as $k => $v) {
      if (!is_int($k)) {
        $swhere[$db->csn($k)] = $v;
      }
    }
    $alreadyShown[] = md5($db->tsn($table) . json_encode($swhere));
    if (!empty($cfg['columns'])) {
      $dbCfg['fields'] = [];
      foreach ($cfg['columns'] as $c) {
        if (strpos($c, ':')) {
          [$tmp1, $tmp2] = X::split($c, ':');
          [$originField, $extPrimary] = X::split($tmp1, '.');
          [$extTable, $extField] = X::split($tmp2, '.');
          $dbCfg['fields'][] = $originField;
        }
        else {
          $dbCfg['fields'][] = $c;
        }
      }
    }
    elseif (!empty($cfg['fields'])) {
      $dbCfg['fields'] = array_keys($cfg['fields']);
    }
    elseif (!isset($cfg['component'])) {
      X::ddump($cfg);
      throw new Exception("No data!");
    }

    if (!empty($cfg['record'])) {
      $data = $cfg['record'];
    }
    else {
      if (empty($when)) {
        $data = $db->rselect($dbCfg);
      }
      else {
        $primaryKey = $db->getPrimary($cfg['table']);
        $idRow = $db->selectOne($cfg['table'], $primaryKey, $dbCfg['where']);
        $data = History::getRowBack($cfg['table'], $idRow, $when, $dbCfg['fields']);
        if (empty($data)) {
          $data = $db->rselect($dbCfg);
        }
      }

      if (!empty($cfg['columns'])) {
        $fieldsToUnset = [];
        foreach ($cfg['columns'] as $c) {
          if (strpos($c, ':')) {
            [$tmp1, $tmp2] = X::split($c, ':');
            [$originField, $extPrimary] = X::split($tmp1, '.');
            [$extTable, $extField] = X::split($tmp2, '.');
            if (array_key_exists($originField, $data)
              && !in_array($originField, $fieldsToUnset)
            ) {
              $fieldsToUnset[] = $originField;
            }

            $data[$extField] = !empty($data[$originField]) ?
              $db->selectOne($extTable, $extField, [$extPrimary => $data[$originField]]) :
              null;
          }
        }

        if (!empty($fieldsToUnset)) {
          foreach ($fieldsToUnset as $u) {
            unset($data[$u]);
          }
        }
      }
    }

    if (!empty($cfg['component'])) {
      $res = $cfg;
      if (empty($res['componentOptions']['source'])) {
        $res['componentOptions']['source'] = $data;
      }
    }
    else {
      $res['fields'] = [];
      foreach ($cfg['fields'] as $name => $f) {
        if (!empty($cfg['columns']) && !in_array($name, $cfg['columns'])) {
          continue;
        }
        if (is_null($data[$name])) {
          $tmp = ['value' => $data[$name]];
        }
        elseif (!empty($f['option'])) {
          $tmp = ['value' => $opt->text($data[$name])];
        }
        elseif (!empty($f['table'])) {
          if (in_array(md5($db->tsn($f['table']) . json_encode([$db->csn($f['column']) => $data[$name]])), $alreadyShown)) {
            continue;
          }

          $tmp = $this->getDisplayRecord($f['table'], $f, [$f['column'] => $data[$name]], $when, $alreadyShown);
        }
        elseif (!empty($f['component'])) {
          $tmp = array_merge($f, [
            'value' => $data[$name]
          ]);
        }
        elseif (!empty($f['type'])) {
          if (($f['type'] === 'text') && Str::isJson($data[$name])) {
            $f['type'] = 'json';
          }
          $tmp = array_merge($f, [
            'value' => $data[$name]
          ]);
        }
        else {
          throw new Exception("Unrecognizable field!");
        }

        $tmp['name'] = $name;
        $res['fields'][] = $tmp;
      }

      $res['source'] = $data;
    }
  
    return $res;

  }


  public function integrateHistoryIntoTable(
    string $table,
    string $db,
    string $host,
    ?string $user = null,
    ?string $date = null,
    ?string $activeColumn = null
  ): bool
  {
    if (!Str::isUid($host)) {
      throw new Exception(_("Invalid host ID"));
    }

    if (($engineId = $this->engineIdFromHost($host))
      && ($engine = $this->engineCode($engineId))
      && ($conn = $this->connection($host, $engine, $db))
      && class_exists('bbn\\Appui\\History')
      && History::hasHistory($conn)
      && $conn->tableExists($table)
      && ($mod = $conn->modelize($table))
    ) {
      // Check if the table has a primary key
      if (empty($mod['keys'])
        || empty($mod['keys']['PRIMARY'])
        || empty($mod['keys']['PRIMARY']['columns'])
      ) {
        throw new Exception(_("The table does not have a primary key, please add one before integrating the history system."));
      }

      $primary = $mod['keys']['PRIMARY'];
      // Check if the primary key is a single column
      if (count($primary['columns']) !== 1) {
        throw new Exception(_("The table has a primary key with multiple columns, please reduce it to a single column before integrating the history system."));
      }

      // Check if the primary key has a constraint
      if (!empty($primary['constraint'])) {
        throw new Exception(_("The table has a primary key with a constraint, please remove it before integrating the history system."));
      }

      // Check if the primary key has a relation with another table
      if (array_filter(
        $mod['keys'],
        fn($v, $k) => ($k !== 'PRIMARY')
          && ($primary['columns'] === $v['columns'])
          && !empty($v['constraint'])
          && !empty($v['ref_table'])
          && !empty($v['ref_column']),
        ARRAY_FILTER_USE_BOTH
      )) {
        throw new Exception(_("The table already has a foreign key with the primary key columns, please remove it before integrating the history system."));
      }
    }

    if (!($dbId = $this->dbId($db, $host, $engine))) {
      $dbId = $this->importDb($db, $host);
    }

    if (empty($dbId)) {
      throw new Exception(X::_("Impossible to find the database \"%s\" in the options", $db));
    }

    $tableId = false;
    $columnId = false;
    $primaryField = $primary['columns'][0];
    if ($this->importTable($table, $dbId, $host)) {
      $tableId = $this->tableId($table, $dbId, $host, $engine);
      $columnId = $this->columnId($primaryField, $tableId);
    }

    if (empty($tableId)) {
      throw new Exception(X::_("Impossible to find the table \"%s\" in the options", $table));
    }

    if (empty($columnId)) {
      throw new Exception(X::_("Impossible to find the column \"%s\" in the options", $primaryField));
    }

    if ($conn->count($table)) {
      if (empty($user)) {
        if (defined('BBN_EXTERNAL_USER_ID')) {
          $user = BBN_EXTERNAL_USER_ID;
        }
        else if (class_exists('bbn\\User')
          && ($uclass = bbn\User::getInstance())
        ) {
          $user = $uclass->getId();
        }

        if (empty($user)) {
          throw new Exception(_("No user ID provided for the history system integration."));
        }
      }

      if (empty($date)) {
        $date = X::microtime();
      }
      else {
        $t = strtotime($date);
        $date = date('U', $t) . '.' . substr(date('u', $t), 0, 4);
      }

      $sql = "SELECT " . $conn->escape($primaryField) . (!empty($activeColumn) ? ", " . $conn->escape($activeColumn) : "") . " FROM " . $conn->escape($table);
      $q = $conn->query($sql);
      while ($row = $q->getRow()) {
        if ($conn->insertIgnore('bbn_history_uids', [
          'bbn_uid' => $row[$primaryField],
          'bbn_table' => $tableId,
          'bbn_active' => !empty($activeColumn) ? (int)!empty($row[$activeColumn]) : 1,
        ])) {
          $conn->insertIgnore('bbn_history', [
            'opr' => 'INSERT',
            'uid' => $row[$primaryField],
            'col' => $columnId,
            'tst' => $date,
            'usr' => $user
          ]);
          if (!empty($activeColumn) && empty($row[$activeColumn])) {
            $conn->insertIgnore('bbn_history', [
              'opr' => 'DELETE',
              'uid' => $row[$primaryField],
              'col' => $columnId,
              'tst' => $date,
              'usr' => $user
            ]);

          }
        }
      }
    }

    // To add primary key -> history table relation
    if ($conn->createConstraints($table, [
      'keys' => [[
        'constraint' => 'bbn_history_uids_fk',
        'columns' => $primary['columns'],
        'ref_table' => $conn->tableSimpleName(History::$table_uids),
        'ref_column' => 'bbn_uid',
        'update' => 'CASCADE',
        'delete' => 'CASCADE'
      ]]
    ])) {
      $conn->clearCache($table, 'tables');
      return true;
    }

    return false;
  }
}