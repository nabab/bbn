<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 20/02/2017
 * Time: 01:39
 */

namespace bbn\appui;
use bbn;

class databases extends bbn\models\cls\cache
{
  use bbn\models\tts\optional;

  /**
   * The options object.
   *
   * @var options
   */
  protected $o;

  /**
   * The options object.
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
   * @var bbn\db
   */
  protected $db_alt;

  protected function get_password($id_option)
  {
    if (!$this->pw) {
      $this->pw = new passwords($this->db);
    }
    return $this->pw->get($id_option);
  }

  /**
   * Constructor
   *
   * @param bbn\db $db The main database connection (where options are)
   */
  public function __construct(bbn\db $db)
  {
    parent::__construct($db);
    self::optional_init();
    $this->o = bbn\appui\options::get_instance();
  }

  /**
   * Returns a connection with the given user@host selecting the given database.
   *
   * @param string $host A string user@host
   * @param string $db   The database name
   * @return bbn\db|null
   */
  public function connection(string $host, string $db): ?bbn\db
  {
    $id_host = !bbn\str::is_uid($host) ? $this->host_id($host) : $host;
    if ($id_host && ($cfg = $this->o->option($id_host))) {
      $cfg = $this->o->option($id_host);
      if (strpos($cfg['code'], '@')) {
        $bits = bbn\x::split($cfg['code'], '@');
        if ((count($bits) === 2) && ($password = $this->get_password($id_host))) {
          $db = [
            'user' => $bits[0],
            'host' => $bits[1],
            'db' => $db,
            'pass' => $password
          ];
        }
        else {
          $db = [
            'host' => $cfg['code'],
            'db' => $db
          ];
        }
        $this->db_alt = new bbn\db($db);
        return $this->db_alt;
      }
    }
    return null;
  }

  /**
   * Returns the ID of a connection.
   * 
   * @param string $host The connection code (user@host or host)
   * @return null|string
   */
  public function host_id(string $host = ''): ?string
  {
    if (!$host) {
      if ($this->username) {
        $host .= $this->username.'@';
      }
      $host .= $this->host;
    }
    
    $r = self::get_option_id($host, 'connections');
    return $r ?: null;
  }

  /**
   * Returns the number of connections in the options.
   *
   * @return int|null
   */
  public function count_hosts(): ?int
  {
    if (($id_parent = self::get_option_id('connections')) 
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
  public function hosts(): array
  {
    if (($id_parent = self::get_option_id('connections')) 
        && ($co = array_values($this->o->code_options($id_parent)))
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
  public function full_hosts(): ?array
  {
    if (($id_parent = self::get_option_id('connections'))
        && ($opt = $this->o->full_options($id_parent))
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
  public function db_id(string $db = ''): ?string
  { 
    if (($id_parent = self::get_option_id('dbs'))
        && ($res = $this->o->from_code($db ?: $this->db->current, $id_parent))
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
  public function count_dbs(string $host = ''): int
  {
    if (!$host) {
      $num = $this->o->count(self::get_option_id('dbs'));
      return $num;
    }
    elseif (!bbn\str::is_uid($host)) {
      $host = $this->host_id($host);
    }
    $all = $this->o->get_aliases($host);
    $num = $all ? count($all) : 0;
    return $num;
  }

  /**
   * Returns the list of DBs available for the given connection.
   * 
   * @param string $host The connection's code, all DBs are returned if empty.
   * @return array|null
   */
  public function dbs(string $host = ''): array
  {
    if (!$host) {
      $arr = $this->o->full_options(self::get_option_id('dbs'));
    }
    elseif (!bbn\str::is_uid($host)) {
      $host = $this->host_id($host);
    }
    if ($host) {
      $o = &$this->o;
      $arr = array_map(
        function ($a) use ($o) {
          return $o->parent($a['id_parent']);
        },
        $this->o->get_aliases($host)
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
  public function full_dbs(string $host = ''): array
  {
    $o =& $this->o;
    if ($dbs = $this->dbs($host)) {
      $res = array_map(
        function ($a) use ($o) {
          $r = [
            'id' => $a['id'],
            'text' => $a['text'],
            'name' => $a['name'],
            'num_tables' => 0,
            'num_connections' => 0,
            'num_procedures' => 0,
            'num_functions' => 0
          ];
          //die(var_dump( $o->from_code('tables', $a['id'])));
          if ($id_tables = $o->from_code('tables', $a['id'])) {
            $r['num_tables'] = $o->count($id_tables);
          }
          if ($id_connections = $o->from_code('connections', $a['id'])) {
            $r['num_connection'] = $o->count($id_connections);
          }
          if ($id_procedures = $o->from_code('procedures', $a['id'])) {
            $r['num_procedures'] = $o->count($id_procedures);
          }
          if ($id_functions = $o->from_code('functions', $a['id'])) {
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
  public function table_id(string $table, string $db = '', string $host = ''): ?string
  {
    if (!bbn\str::is_uid($db)) {
      $db = $this->db_id($db, $host);
    }
    if (bbn\str::is_uid($db)
        && ($id_parent = $this->o->from_code('tables', $db))
        && ($id = $this->o->from_code($table, $id_parent))
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
  public function count_tables(string $db, string $host = ''): ?int
  {
    if (!bbn\str::is_uid($db)) {
      $db = $this->db_id($db, $host);
    }
    if (bbn\str::is_uid($db) && ($id_parent = $this->o->from_code('tables', $db))) {
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
  public function tables(string $db = '', string $host = ''): ?array
  {
    if (!bbn\str::is_uid($db)) {
      $db = $this->db_id($db, $host);
    }
    if (bbn\str::is_uid($db)
        && ($id_parent = $this->o->from_code('tables', $db))
        && ($fo = array_values($this->o->code_options($id_parent)))
    ) {
      $res = array_map(function ($a) {
        return [
          'id' => $a['id'],
          'text' => $a['text'],
          'name' => $a['code']
        ];
      }, $fo);
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
  public function full_tables(string $db = '', string $host = ''): ?array
  {
    if (!bbn\str::is_uid($db)) {
      $db = $this->db_id($db ?: $this->db->current, $host);
    }
    if (bbn\str::is_uid($db) && ($id_parent = $this->o->from_code('tables', $db))) {
      $o =& $this->o;
      if ($fo = $this->o->full_options($id_parent)) {
        $res = array_map(
          function ($a) use ($o) {
            $r = [
              'id' => $a['id'],
              'text' => $a['text'],
              'name' => $a['code'],
              'num_columns' => 0,
              'num_keys' => 0
            ];
            if ($id_columns = $o->from_code('columns', $a['id'])) {
              $r['num_columns'] = $o->count($id_columns);
            }
            if ($id_keys = $o->from_code('keys', $a['id'])) {
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
  public function table_from_item(string $id_keycol): ?string
  {
    if (($table = $this->table_id_from_item($id_keycol))
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
  public function table_id_from_item(string $id_keycol): ?string
  {
    if (bbn\str::is_uid($id_keycol)
        && ($id_cols = $this->o->get_id_parent($id_keycol))
        && ($id_table = $this->o->get_id_parent($id_cols))
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
  public function db_from_table(string $id_table): ?string
  {
    if (($id_db = $this->db_id_from_table($id_table))
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
  public function db_id_from_table(string $id_table): ?string
  {
    if (bbn\str::is_uid($id_table)
        && ($id_tables = $this->o->get_id_parent($id_table))
        && ($id_db = $this->o->get_id_parent($id_tables))
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
  public function db_from_item(string $id_keycol): ?string
  {
    if ($id_db = $this->db_id_from_item($id_keycol)
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
  public function db_id_from_item(string $id_keycol): ?string
  {
    if (($id_table = $this->table_id_from_item($id_keycol))
        && ($id_db = $this->db_id_from_table($id_table))
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
  public function column_id(string $column, string $table, string $db = ''): ?string
  {
    $res = null;
    if (bbn\str::is_uid($table)) {
      $res = $this->o->from_code($this->db->csn($column), 'columns', $table);
      return $res;
    }
    $c = $this->db->csn($column);
    $t = $this->db->tsn($table);
    if ($tmp = self::get_option_id($c, 'columns', $t, 'tables', $db ?: $this->db->current, 'dbs')) {
      $res = $tmp;
    }
    return $res;
  }

  /**
   * Returns the number of columns for the given DB.
   *
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return int
   */
  public function count_columns(string $table, string $db = ''): int
  {
    $num = 0;
    if (!bbn\str::is_uid($table)) {
      $table = $this->table_id($table, $db, $host);
    }
    if (bbn\str::is_uid($table)
        && ($id_parent = $this->o->from_code('columns', $table))
    ) {
      $num = $this->o->count($id_parent);
    }
    return $num;
  }

  /**
   * Returns a list of the columns for the given table.
   * 
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return array|false
   */
  public function columns(string $table, string $db = ''): ?array
  {
    if (!bbn\str::is_uid($table)) {
      $table = $this->table_id($this->db->tsn($table), $db, $host);
    }
    if (bbn\str::is_uid($table)
        && ($id_parent = $this->o->from_code('columns', $table))
        && ($res = $this->o->options($id_parent))
    ) {
      return $res;
    }
    return null;
  }

  /**
   * Returns a list of the columns for the given table with all their characteristics.
   * 
   * @param string $table The table's name
   * @param string $db    The database name
   * @return array|false
   */
  public function full_columns(string $table, string $db = ''): array
  {
    if (!bbn\str::is_uid($table)) {
      $table = $this->table_id($table, $db, $host);
    }
    if (bbn\str::is_uid($table)
        && ($id_parent = $this->o->from_code('columns', $table))
        && ($res = $this->o->full_options($id_parent))
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
  public function key_id(string $key, string $table, string $db = ''): ?string
  {
    $res = null;
    if (bbn\str::is_uid($key)) {
      $res = $this->o->from_code($key, $table);
    }
    elseif ($tmp = self::get_option_id($key, 'keys', $table, 'tables', $db ?: $this->db->current, 'dbs')) {
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
  public function count_keys(string $table, string $db = ''): int
  {
    $num = 0;
    if (!bbn\str::is_uid($table)) {
      $table = $this->table_id($table, $db, $host);
    }
    if (bbn\str::is_uid($table)
        && ($id_parent = $this->o->from_code('keys', $table))
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
    if (!bbn\str::is_uid($table)) {
      $table = $this->table_id($table, $db, $host);
    }
    if (bbn\str::is_uid($table)
        && ($id_parent = $this->o->from_code('keys', $table))
        && ($tree = $this->o->full_tree($id_parent))
        && $tree['items']
    ) {
      $t =& $this;
      $res = array_map(
        function ($a) use ($t) {
          $key = [
            'name' => $a['code'],
            'unique' => $a['unique'],
            'columns' => [],
            'ref_column' => $a['id_alias'] ? $a['alias']['code'] : null,
            'ref_table' => $a['id_alias'] &&
                          ($id_table = $t->o->get_id_parent($a['alias']['id_parent'])) ?
                          $t->o->code($id_table) : null,
            'ref_db' => !empty($id_table) &&
                        ($id_db = $t->o->get_id_parent($t->o->get_id_parent($id_table))) ?
                        $t->o->code($id_db) : null
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
  public function full_keys(string $table, string $db = ''): array
  {
    return $this->keys($table, $db, $host);
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
    $id = $this->table_id($table, $db);
    return $this->o->remove_full($id);
  }

  /**
   * Deletes a database and all its descendants from the options table.
   *
   * @param string $db The database's name
   * @return int
   */
  public function remove_all(string $db = ''): int
  {
    $id = $this->db_id($db);
    return $this->o->remove_full($id);
  }

  /**
   * Deletes a connection from the options table.
   *
   * @param string $connection The connection's code
   * @return int
   */
  public function remove_host(string $connection): int
  {
    $id = $this->host_id($connection);
    return $this->o->remove_full($id);
  }

  /**
   * Returns a database model as bbn\db::modelize but with options IDs.
   *
   * @param string $table The table's name
   * @param string $db    The database's name
   * @return array|null
   */
  public function modelize(string $table = '', string $db = ''): ?array
  {
    if (($mod = $this->db->modelize($table)) && \is_array($mod)) {
      $keys = function (&$a) use (&$table, &$db) {
        if (\is_array($a['keys'])) {
          array_walk(
            $a['keys'],
            function (&$w, $k) use (&$table, &$db) {
              $w['id_option'] = $this->key_id($k, $table, $db);
            }
          );
        }
      };
      $fields = function (&$a) use (&$table, &$db) {
        if (\is_array($a['fields'])) {
          array_walk(
            $a['fields'],
            function (&$w, $k) use (&$table, &$db) {
              $w['id_option'] = $this->column_id($k, $table, $db);
            }
          );
        }
      };
      if (empty($table)) {
        array_walk(
          $mod,
          function (&$w, $k) use (&$table, &$db, $keys, $fields) {
            $table = $this->db->tsn($k);
            $db = substr($k, 0, strrpos($k, $table)-1);
            $w['id_option'] = $this->table_id($table, $db);
            $keys($w);
            $fields($w);
          }
        );
      }
      else {
        $keys($mod);
        $fields($mod);
      }
      return $mod;
    }
    return null;
  }

  /**
   * Imports a database's structure into the options table.
   *
   * @param string $host The connection's code
   * @param bool   $full If true will connect to the database and get its structure
   * @return string|null The ID of the generated (or existing) database entry
   */
  public function import_host(string $host, bool $full = false): ?string
  {
    if (!($id_host = self::get_option_id($host, 'connections'))){
      $id_host = $this->o->add([
        'id_parent' => self::get_option_id('connections'),
        'text' => $host,
        'code' => $host,
      ]);
    }
    if ($id_host && $full) {
      /** @todo but might be heavy */
    }
    return $id_host ?: null;
  }

  /**
   * Imports a database's structure into the options table.
   *
   * @param string $db   The database's name
   * @param string $host The connection's code
   * @param bool   $full If true will connect to the database and get its structure
   * @return string|null The ID of the generated (or existing) database entry
   */
  public function import_db(string $db, string $host = '', $full = false): ?string
  {
    $id_db = null;
    if ($id_dbs = self::get_option_id('dbs')) {
      if (!($id_db = $this->o->from_code($db, $id_dbs))) {
        if ($id_db = $this->o->add([
          'id_parent' => $id_dbs,
          'text' => $db,
          'code' => $db,
        ])) {
          $this->o->set_cfg($id_db, ['allow_children' => 1, 'show_code' => 1]);
        }
      }
      if ($id_db) {
        if (!($id_procedures = $this->o->from_code('procedures', $id_db))
            && ($id_procedures = $this->o->add([
              'id_parent' => $id_db,
              'text' => _('Procedures'),
              'code' => 'procedures',
            ]))
        ) {
          $this->o->set_cfg($id_procedures, [
            'show_code' => 1,
            'show_value' => 1,
            'allow_children' => 1
          ]);
        }
        if (!($id_connections = $this->o->from_code('connections', $id_db))
            && ($id_connections = $this->o->add([
              'id_parent' => $id_db,
              'text' => _('Connections'),
              'code' => 'connections',
            ]))
        ) {
          $this->o->set_cfg($id_connections, [
            'show_alias' => 1,
            'notext' => 1,
            'id_root_alias' => self::get_option_id('connections'),
            'root_alias' => 'Connections'
          ]);
        }
        if (!($id_functions = $this->o->from_code('functions', $id_db))
            && ($id_functions = $this->o->add([
              'id_parent' => $id_db,
              'text' => _('Function'),
              'code' => 'functions',
            ]))
        ) {
          $this->o->set_cfg($id_functions, [
            'show_code' => 1,
            'show_value' => 1,
            'allow_children' => 1
          ]);
        }
        if (!($id_tables = $this->o->from_code('tables', $id_db))
            && ($id_tables = $this->o->add([
              'id_parent' => $id_db,
              'text' => _('Tables'),
              'code' => 'tables',
            ]))
        ) {
          $this->o->set_cfg($id_tables, [
            'show_code' => 1,
            'show_value' => 1,
            'allow_children' => 1
          ]);
        }
        if ( $host ){
          $host_id = bbn\str::is_uid($host) ? $host : $this->host_id($host);
        }
        else {
          $host_id = $this->retrieve_host($id_db);
        }
        if ($host_id && $id_connections && $id_functions && $id_procedures && $id_tables) {
          if (!$this->db->count('bbn_options', [
            'id_parent' => $id_connections,
            'id_alias' => $host_id
          ])) {
            $this->o->add([
              'id_parent' => $id_connections,
              'id_alias' => $host_id
            ]);
          }
          if ($full) {
            if (!empty($host_id)) {
              $tables = $this->connection($host_id, $db)->get_tables();
              if (!empty($tables)) {
                foreach ($tables as $t) {
                  $this->import_table($t, $id_db, $host_id);
                }
              }
            }
          }
        }
        else{
          bbn\x::log("Impossible to find an host ID for DB ".$this->o->code($id_db));
        }
      }
    }
    return $id_db;
  }

  public function retrieve_host(string $id_db): ?string{
    
    if ($this->check()
        && defined('BBN_DB_USER')
        && defined('BBN_DB_HOST')
        && ($connections = $this->o->full_options('connections', $id_db))
    )
    {
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
  public function import_table(string $table, string $id_db, string $host = ''): ?array
  {
    if (empty($host)) {
      $host_id = $this->retrieve_host($id_db);
    }
    else{
      $host_id = bbn\str::is_uid($host) ? $host : $this->host_id($host);
    }
    if ($host_id && ($id_tables = $this->o->from_code('tables', $id_db))) {
      if (!($id_table = $this->o->from_code($table, $id_tables))
          && ($id_table = $this->o->add(
            [
              'id_parent' => $id_tables,
              'text' => $table,
              'code' => $table,
            ]
          ))
      ) {
        $this->o->set_cfg($id_table, ['allow_children' => 1]);
        if ($id_columns = $this->o->add(
          [
            'id_parent' => $id_table,
            'text' => _("Columns"),
            'code' => 'columns'
          ]
        )
        ) {
          $this->o->set_cfg(
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
            'text' => _("Keys"),
            'code' => 'keys',
          ]
        )
        ) {
          $this->o->set_cfg(
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
        $id_columns = $this->o->from_code('columns', $id_table);
        $id_keys = $this->o->from_code('keys', $id_table);
      }
      $db = $this->o->code($id_db);
      if ($id_table
          && $id_columns
          && $id_keys
          && $db
          && ($conn = $this->connection($host_id, $db))
          && ($m = $conn->modelize($table))
          && !empty($m['fields'])
      ) {
        $num_cols = 0;
        $num_cols_rem = 0;
        $fields = [];
        $ocols = array_flip($this->o->options($id_columns));
        foreach ($m['fields'] as $col => $cfg) {
          if ($opt_col = $this->o->option($col, $id_columns)) {
            $num_cols += (int)$this->o->set($opt_col['id'], bbn\x::merge_arrays($opt_col, $cfg));
          }
          elseif ($id = $this->o->add(
            bbn\x::merge_arrays(
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
            $opt_col = $cfg;
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
            if (bbn\str::is_uid($id)) {
              $num_cols_rem += (int)$this->o->remove($id);
            }
          }
        }
        $num_keys = 0;
        $num_keys_rem = 0;
        $okeys = array_flip($this->o->options($id_keys));
        foreach ($m['keys'] as $key => $cfg) {
          $cols = $cfg['columns'] ?? [];
          unset($cfg['columns']);
          if (isset($cfg['ref_db'], $cfg['ref_table'], $cfg['ref_column']) 
              && ($id_alias = $this->column_id($cfg['ref_column'], $cfg['ref_table'], $cfg['ref_db']))
          ) {
            $cfg['id_alias'] = $id_alias;
            unset($cfg['ref_db'], $cfg['ref_table'], $cfg['ref_column']);
          }
          if ($opt_key = $this->o->option($key, $id_keys)) {
            $num_keys += (int)$this->o->set($opt_key['id'], bbn\x::merge_arrays($opt_key, $cfg));
          }
          elseif ($id = $this->o->add(
            bbn\x::merge_arrays(
              $cfg, [
              'id_parent' => $id_keys,
              'text' => $key,
              'code' => $key
              ]
            )
          ) 
          ) {
            $this->o->set_cfg(
              $id, [
              'show_code' => 1,
              'show_alias' => 1
              ]
            );
            $num_keys++;
            $opt_key = $cfg;
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
                    $opt['id'], bbn\x::merge_arrays(
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
            if (bbn\str::is_uid($id)) {
              $children = $this->o->items($id);
              foreach ($children as $cid) {
                $num_keys_rem += (int)$this->o->remove_full($cid);
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
      $tf = explode('.', $this->db->tfn($table));
      $db = $tf[0];
      $table = $tf[1];

      if (($id_host = $this->import_host($this->db->host)) 
          && ($id_db = $this->import_db($db, $id_host))
      ) {
        $res = $this->import_table($table, $id_db);
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
  public function import_all(string $db = ''): ?array
  {
    $res = null;
    if ($tables = $this->db->get_tables($db)) {
      $res = [
        'tables' => 0,
        'columns' => 0,
        'keys' => 0
      ];
      foreach ($tables as $t){
        if ($tmp = $this->import(($db ?: $this->db->current).'.'.$t)) {
          $res['tables']++;
          $res['columns'] += $tmp['columns'];
          $res['keys'] += $tmp['keys'];
        }
      }
    }
    return $res;
  }
}