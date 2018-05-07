<?php
namespace bbn\appui;

use bbn;

class history
{
  use bbn\models\tts\report;

	private static
    /** @var \bbn\db The DB connection */
    $db,
    /** @var array A collection of DB connections  */
    $dbs = [],
    /** @var array A collection of DB connections  */
    $structures = [],
    /** @var databases The databases class which collects the columns IDs */
    $databases_class,
    /** @var string Name of the database where the history table is */
    $admin_db = '',
    /** @var string User's ID  */
    $user,
    /** @var string Prefix of the history table */
    $prefix = 'bbn_',
    /** @var float The current date can be overwritten if this variable is set */
    $date,
    /** @var boolean Set to true once the initial configuration has been checked */
    $ok = false,
    /** @var boolean Setting it to false avoid execution of history triggers */
    $enabled = true,
    /** @var array The foregin links atytached to history UIDs' table */
    $links;

  public static
    /** boolean|string The history table's name */
    $table_uids = false,
    /** boolean|string The history table's name */
    $table = false,
    /** string The UIDs table */
    $uids = 'uids',
    /** string The history default column's name */
    $column = 'active',
    /** boolean */
    $is_used = false;

  /**
   * @return bbn\db
   */
  private static function _get_db(): ?bbn\db
  {
    if ( self::$db && self::$db->check() ){
      return self::$db;
    }
    return null;
  }

  /**
   * @return databases
   */
  private static function _get_databases(): ?databases
  {
    if ( self::check() ){
      return self::$databases_class;
    }
    return null;
  }

  /**
   * Adds a row in the history table
   * 
   * @param array $cfg
   * @return int
   */
  private static function _insert(array $cfg): int
  {
    if ( isset($cfg['column'], $cfg['line'], $cfg['chrono']) && self::check() ){
      // Recording the last ID
      $db = self::_get_db();
      $id = $db->last_id();
      $last = self::$db->last();
      if ( !array_key_exists('old', $cfg) ){
        $cfg['ref'] = null;
        $cfg['val'] = null;
      }
      else if ( \bbn\str::is_uid($cfg['old']) && self::$db->count(self::$table_uids, ['uid' => $cfg['old']]) ){
        $cfg['ref'] = $cfg['old'];
        $cfg['val'] = null;
      }
      else{
        $cfg['ref'] = null;
        $cfg['val'] = $cfg['old'];
      }
      // New row in the history table
      if ( $res = $db->insert(self::$table, [
        'opr' => $cfg['operation'],
        'uid' => $cfg['line'],
        'col' => $cfg['column'],
        'val' => $cfg['val'],
        'ref' => $cfg['ref'],
        'tst' => self::$date ?: $cfg['chrono'],
        'usr' => self::$user
      ]) ){
        // Set back the original last ID
        $db->set_last_insert_id($id);
      }
      self::$db->last_query = $last;
      return $res;
    }
    return 0;
  }

  /**
   * Get a string for the WHERE in the query with all the columns selection
   * @param string $table
   * @return string|null
   */
  private static function _get_table_where(string $table): ?string
  {
    if (
      bbn\str::check_name($table) &&
      ($db = self::_get_db()) &&
      ($databases_class = self::_get_databases()) &&
      ($model = $databases_class->modelize($table))
    ){
      $col = $db->escape('col');
      $where_ar = [];
      foreach ( $model['fields'] as $k => $f ){
        if ( !empty($f['id_option']) ){
          $where_ar[] = $col.' = UNHEX("'.$db->escape_value($f['id_option']).'")';
        }
      }
      if ( \count($where_ar) ){
        return implode(' OR ', $where_ar);
      }
    }
    return null;
  }

  /**
   * Returns the column's corresponding option's ID
   * @param $column string
   * @param $table string
   * @return null|string
   */
  public static function get_id_column(string $column, string $table): ?string
  {
    if (
      ($db = self::_get_db()) &&
      ($full_table = $db->tfn($table)) &&
      ($databases_class = self::_get_databases())
    ){
      [$database, $table] = explode('.', $full_table);
      return $databases_class->column_id($column, $table, $database, self::$db->host);
    }
    return false;
  }

  /**
   * Initializes
   * @param bbn\db $db
   * @param array $cfg
   * @return void
   */
  public static function init(bbn\db $db, array $cfg = []): void
  {
    /** @var string $hash Unique hash for this DB connection (so we don't init twice a same connection) */
    $hash = $db->get_hash();
    if ( !\in_array($hash, self::$dbs, true) && $db->check() ){
      // Adding the connection to the list of connections
      self::$dbs[] = $hash;
      /** @var bbn\db db */
      self::$db = $db;
      $vars = get_class_vars(__CLASS__);
      foreach ( $cfg as $cf_name => $cf_value ){
        if ( array_key_exists($cf_name, $vars) ){
          self::$$cf_name = $cf_value;
        }
      }
      if ( !self::$admin_db ){
        self::$admin_db = self::$db->current;
      }
      if ( !self::$databases_class ){
        self::$databases_class = new bbn\appui\databases($db);
      }
      self::$table = self::$admin_db.'.'.self::$prefix.'history';
      self::$table_uids = self::$admin_db.'.'.self::$prefix.'history_uids';
      self::$ok = true;
      self::$is_used = true;
      self::$links = self::$db->get_foreign_keys('uid', self::$prefix.'history_uids', self::$admin_db);
      self::$db->set_trigger('\\bbn\appui\\history::trigger');
    }
  }

  /**
   * @return bool
   */
  public static function is_init(): bool
  {
    return self::$ok;
  }

  /**
	 * @return void
	 */
  public static function disable(): void
  {
    self::$enabled = false;
  }

	/**
	 * @return void
	 */
  public static function enable(): void
  {
    self::$enabled = true;
  }

	/**
	 * @return bool
	 */
  public static function is_enabled(): bool
  {
    return self::$enabled === true;
  }

  /**
   * @param $d
   * @return null|float
   */
  public static function valid_date($d): ?float
  {
    if ( !bbn\str::is_number($d) ){
      $d = strtotime($d);
    }
    if ( ($d > 0) && bbn\str::is_number($d) ){
      return (float)$d;
    }
    return null;
  }

  /**
   * Checks if all history parameters are set in order to read and write into history
   * @return bool
   */
  public static function check(): bool
  {
	  return
      isset(self::$user, self::$table, self::$db) &&
      self::is_init() &&
      self::is_enabled() &&
      ($db = self::_get_db());
  }

  /**
   * Returns true if the given DB connection is configured for history
   *
   * @param bbn\db $db
   * @return bool
   */
  public static function has_history(bbn\db $db): bool
  {
    $hash = $db->get_hash();
    return \in_array($hash, self::$dbs, true);
  }

  /**
   * Effectively deletes a row (deletes the row, the history row and the ID row)
   *
   * @param string $id
   * @return bool
   */
	public static function delete(string $id): bool
  {
		if ( $id && ($db = self::_get_db()) ){
      return $db->delete(self::$table_uids, ['uid' => $id]);
    }
		return false;
	}

  /**
   * Sets the "active" column name
   *
   * @param string $column
   * @return void
   */
	public static function set_column(string $column): void
  {
		if ( bbn\str::check_name($column) ){
			self::$column = $column;
		}
	}

	/**
   * Gets the "active" column name
   *
	 * @return string the "active" column name
	 */
	public static function get_column(): string
  {
    return self::$column;
	}

  /**
   * @param $date
   * @return void
   */
	public static function set_date($date): void
	{
		// Sets the current date
		if ( !bbn\str::is_number($date) && !($date = strtotime($date)) ){
      return;
    }
    $t = time();
    // Impossible to write history in the future
    if ( $date > $t ){
      $date = $t;
    }
		self::$date = $date;
	}

	/**
	 * @return float
	 */
	public static function get_date(): ?float
	{
		return self::$date;
	}

	/**
	 * @return void
	 */
	public static function unset_date(): void
	{
		self::$date = null;
	}

  /**
   * Sets the history table name
   * @param string $db_name
   * @return void
   */
	public static function set_admin_db(string $db_name): void
	{
		// Sets the history table name
		if ( bbn\str::check_name($db_name) ){
			self::$admin_db = $db_name;
			self::$table = self::$admin_db.'.'.self::$prefix.'history';
		}
	}

  /**
   * Sets the user ID that will be used to fill the user_id field
   * @param $user
   * @return void
   */
	public static function set_user($user): void
	{
		// Sets the history table name
		if ( bbn\str::is_uid($user) ){
			self::$user = $user;
		}
	}

  /**
   * Gets the user ID that is being used to fill the user_id field
   * @return null|string
   */
	public static function get_user(): ?string
	{
		return self::$user;
	}

  /**
   * @param string $table
   * @param int $start
   * @param int $limit
   * @param string|null $dir
   * @return array
   */
  public static function get_all_history(string $table, int $start = 0, int $limit = 20, string $dir = null): array
  {
    $r = [];
    if (
      ($db = self::_get_db()) &&
      ($dbc = self::_get_databases()) &&
      ($id_table = $dbc->table_id($table, self::$db->current))
    ){
      $tab = $db->escape(self::$table);
      $tab_uids = $db->escape(self::$table_uids);
      $uid = $db->cfn('uid', self::$table_uids, true);
      $id_tab = $db->cfn('id_table', self::$table_uids, true);
      $uid2 = $db->cfn('uid', self::$table, true);
      $chrono = $db->cfn('tst', self::$table, true);
      $order = $dir && (bbn\str::change_case($dir, 'lower') === 'asc') ? 'ASC' : 'DESC';
      $sql = <<< MYSQL
SELECT DISTINCT($uid)
FROM $tab_uids
  JOIN $tab
    ON $uid = $uid2
WHERE $id_tab = ? 
ORDER BY $chrono $order
LIMIT $start, $limit
MYSQL;
      $r = $db->get_col_array($sql, hex2bin($id_table));
    }
    return $r;
  }

  /**
   * @param $table
   * @param int $start
   * @param int $limit
   * @return array
   */
  public static function get_last_modified_lines(string $table, int $start = 0, int $limit = 20): array
  {
    $r = [];
    if (
      ($db = self::_get_db()) &&
      ($dbc = self::_get_databases()) &&
      ($id_table = $dbc->table_id($table, self::$db->current))
    ){
      $tab = $db->escape(self::$table);
      $tab_uids = $db->escape(self::$table_uids);
      $uid = $db->cfn('uid', self::$table_uids, true);
      $deletion = $db->cfn('deletion', self::$table_uids, true);
      $id_tab = $db->cfn('id_table', self::$table_uids, true);
      $line = $db->escape('uid', self::$table);
      $chrono = $db->escape('tst');
      $sql = <<< MYSQL
SELECT DISTINCT($line)
FROM $tab_uids
  JOIN $tab
    ON $uid = $line
WHERE $id_tab = ? 
AND $deletion IS NULL
ORDER BY $chrono
LIMIT $start, $limit
MYSQL;
      $r = $db->get_col_array($sql, hex2bin($id_table));
    }
    return $r;
  }

  /**
   * @param string $table
   * @param $from_when
   * @param $id
   * @param null $column
   * @return null|array
   */
  public static function get_next_update(string $table, string $id, $from_when, $column = null)
  {
    if (
      bbn\str::check_name($table) &&
      ($date = self::valid_date($from_when)) &&
      ($db = self::_get_db()) &&
      ($dbc = self::_get_databases()) &&
      ($id_table = $dbc->table_id($table)) &&
      ($id_column = $dbc->column_id(self::$column, $id_table))
    ){
      $tab = $db->escape(self::$table);
      $tab_uids = $db->escape(self::$table_uids);
      $uid = $db->cfn('uid', self::$table_uids, true);
      $id_tab = $db->cfn('id_table', self::$table_uids, true);
      $id_col = $db->cfn('col', self::$table, true);
      $line = $db->cfn('uid', self::$table, true);
      $chrono = $db->escape('tst');
      if ( $column ){
        $where = $id_col. ' = UNHEX("'.$db->escape_value(
          bbn\str::is_uid($column) ? $column : $dbc->column_id($column, $id_table)
        ).'")';
      }
      else {
        $w = self::_get_table_where($table);
        $where = $id_col." != UNHEX('$id_column') " . ($w ?: '');
      }
      $sql = <<< MYSQL
SELECT $tab.*
FROM $tab_uids
  JOIN $tab
    ON $uid = $line
WHERE $where
  AND $uid = ?
  AND $id_tab = ? 
  AND $chrono > ?
ORDER BY $chrono ASC
LIMIT 1
MYSQL;
      return $db->get_row($sql, hex2bin($id), hex2bin($id_table), $date);
    }
    return null;
  }

  /**
   * @param string $table
   * @param $from_when
   * @param $id
   * @param null $column
   * @return null|array
   */
  public static function get_prev_update(string $table, string $id, $from_when, $column = null):? array
  {
    if (
      bbn\str::check_name($table) &&
      ($date = self::valid_date($from_when)) &&
      ($dbc = self::_get_databases()) &&
      ($db = self::_get_db())
    ){
      $tab = $db->escape(self::$table);
      $line = $db->escape('uid');
      $operation = $db->escape('opr');
      $chrono = $db->escape('tst');
      if ( $column ){
        $where = $db->escape('col').
          ' = UNHEX("'.$db->escape_value(
            bbn\str::is_uid($column) ? $column : $dbc->column_id($column, $table)
          ).'")';
      }
      else{
        $where = self::_get_table_where($table);
      }
      $sql = <<< MYSQL
SELECT *
FROM $tab
WHERE $line = ?
AND ($where)
AND $operation LIKE 'UPDATE'
AND $chrono < ?
ORDER BY $chrono ASC
LIMIT 1
MYSQL;
      return $db->get_row($sql, hex2bin($id), $date);
    }
    return null;
  }

  /**
   * @param string $table
   * @param $from_when
   * @param string $id
   * @param $column
   * @return bool|mixed
   */
  public static function get_next_value(string $table, string $id, $from_when, $column){
    if ( $r = self::get_next_update($table, $id, $from_when, $column) ){
      return $r['ref'] ?: $r['val'];
    }
    return false;
  }

  /**
   * @param string $table
   * @param string $id
   * @param $from_when
   * @param $column
   * @return bool|mixed
   */
  public static function get_prev_value(string $table, string $id, $from_when, $column){
    if ( $r = self::get_prev_update($table, $id, $from_when, $column) ){
      return $r['ref'] ?: $r['val'];
    }
    return false;
  }

  /**
   * @param string $table
   * @param string $id
   * @param $when
   * @param array $columns
   * @return array|null
   */
  public static function get_row_back(string $table, string $id, $when, array $columns = []):? array
  {
    if ( !($when = self::valid_date($when)) ){
      self::_report_error("The date $when is incorrect", __CLASS__, __LINE__);
    }
    else if (
      ($db = self::_get_db()) &&
      ($dbc = self::_get_databases()) &&
      ($model = $dbc->modelize($table)) &&
      ($table = self::get_table_cfg($table))
    ){

      if ( $when >= time() ){
        return $db->rselect($table, $columns, [
          self::$structures[$table]['primary'] => $id
        ]) ?: null;
      }
      if ( $when < self::get_creation_date($table, $id) ){
        return null;
      }
      if ( \count($columns) === 0 ){
        $columns = array_keys($model['fields']);
      }
      $r = [];
      foreach ( $columns as $col ){
        if ( isset($model['fields'][$col], $model['fields'][$col]['id_option']) ){
          $r[$col] = $db->rselect(self::$table, ['val', 'ref'], [
            'uid' => $id,
            'col' => $model['fields'][$col]['id_option'],
            'opr' => 'UPDATE',
            ['tst', '>', $when]
          ]);
          $r[$col] = $r[$col]['ref'] ?: ($r[$col]['val'] ?: false);
        }
        if ( $r[$col] === false ){
          $r[$col] = $db->get_val($table, $col, [
            self::$structures[$table]['primary'] => $id
          ]);
        }
      }
      return $r;
    }
    return null;
  }

  /**
   * @param $table
   * @param $id
   * @param $when
   * @param $column
   * @return bool|mixed
   */
  public static function get_val_back(string $table, string $id, $when, $column)
  {
    if ( $row = self::get_row_back($table, $id, $when, [$column]) ){
      return $row[$column];
    }
    return false;
  }

  public static function get_creation_date(string $table, string $id): ?float
  {
    if ( $res = self::get_creation($table, $id) ){
      return $res['date'];
    }
    return null;
  }

  /**
   * @param $table
   * @param $id
   * @return array|null
   */
  public static function get_creation(string $table, string $id): ?array
  {
    if (
      ($db = self::_get_db()) &&
      ($table = self::get_table_cfg($table)) &&
      ($id_col = self::get_id_column(self::$structures[$table]['primary'], $table))
    ){
      if ( $r = $db->rselect(self::$table, ['date' => 'tst', 'user' => 'usr'], [
        'uid' => $id,
        'col' => $id_col,
        'opr' => 'INSERT'
      ]) ){
        return $r;
      }
    }
    return null;
  }

  /**
   * @param string $table
   * @param string $id
   * @param null $column
   * @return float|null
   */
  public static function get_last_date(string $table, string $id, $column = null): float
  {
    if ( $db = self::_get_db() ){
      if (
        $column &&
        ($id_col = self::get_id_column($column, $table))
      ){
        return self::$db->select_one(self::$table, 'tst', [
          'uid' => $id,
          'col' => $id_col
        ], [
          'tst' => 'DESC'
        ]);
      }
      else if ( !$column && ($where = self::_get_table_where($table)) ){
        $tab = $db->escape(self::$table);
        $chrono = $db->escape('tst');
        $line = $db->escape('uid');
        $sql = <<< MYSQL
SELECT $chrono
FROM $tab
WHERE $line = ?
AND ($where)
ORDER BY $chrono DESC
MYSQL;
        return $db->get_one($sql, $id);
      }
    }
    return null;
  }

  /**
   * @param string $table
   * @param string $id
   * @param string $col
   * @return array
   */
  public static function get_history(string $table, string $id, $col = ''){
    if ( self::check($table) ){
      $pat = [
        'ins' => 'INSERT',
        'upd' => 'UPDATE',
        'res' => 'RESTORE',
        'del' => 'DELETE'
      ];
      $r = [];
      $table = self::$db->table_full_name($table);
      foreach ( $pat as $k => $p ){
        if ( $q = self::$db->rselect_all(
          self::$table,
          [
            'date' => 'tst',
            'user' => 'usr',
            'val',
            'col',
            //'id'
          ],[
            ['uid', '=', $id],
            //['col', 'LIKE', $table.'.'.( $col ? $col : '%' )],
            ['col', '=', $col],
            ['opr', 'LIKE', $p]
          ],[
            'tst' => 'desc'
          ]
        ) ){
          $r[$k] = $q;
        }
      }
      return $r;
    }
	}

  /**
   * @param string $table
   * @param string $id
   * @return array
   */
  public static function get_full_history(string $table, string $id): array
  {
    $r = [];
    if (
      ($db = self::_get_db()) &&
      ($where = self::_get_table_where($table))
    ){
      $tab = $db->escape(self::$table);
      $line = $db->escape('uid');
      $chrono = $db->escape('tst');
      $sql = <<< MYSQL
SELECT *
FROM $tab
WHERE $line = ?
AND ($where)
ORDER BY $chrono ASC
MYSQL;
      $r = $db->get_rows($sql, $id);
    }
    return $r;
	}

  public static function get_column_history($table, $id, $column)
  {
    if ( self::check($table)&& ($primary = self::$db->get_primary($table)) ){
      $current = self::$db->select_one($table, $column, [
        $primary[0] => $id
      ]);
      $hist = self::get_history($table, $id, $column);
      $r = [];
      if ( $crea = self::get_creation($table, $id) ){
        if ( !empty($hist['upd']) ){
          $hist['upd'] = array_reverse($hist['upd']);
          foreach ( $hist['upd'] as $i => $h ){
            if ( $i === 0 ){
              array_push($r, [
                'date' => $crea['date'],
                'val' => $h['old'],
                'user' => $crea['user']
              ]);
            }
            else{
              array_push($r, [
                'date' => $hist['upd'][$i-1]['date'],
                'val' => $h['old'],
                'user' => $hist['upd'][$i-1]['user']
              ]);
            }
          }
          array_push($r, [
            'date' => $hist['upd'][$i]['date'],
            'val' => $current,
            'user' => $hist['upd'][$i]['user']
          ]);
        }
        else if (!empty($hist['ins']) ){
          $r[0] = [
            'date' => $hist['ins'][0]['date'],
            'val' => $current,
            'user' => $hist['ins'][0]['user']
          ];
        }
      }
      return $r;
    }
  }

  /**
   * Gets all information about a given table
   * @param string $table
   * @param bool $force
   * @return null|string Table's full name
   */
	public static function get_table_cfg(string $table, bool $force = false): ?string
  {
    // Check history is enabled and table's name correct
    if (
      ($db = self::_get_db()) &&
      ($dbc = self::_get_databases()) &&
      ($table = $db->tfn($table))
    ){
      if ( $force || !isset(self::$structures[$table]) ){
        self::$structures[$table] = [
          'history' => false,
          'primary' => false,
          'id' => null,
          'fields' => []
        ];
        if (
          ($model = $dbc->modelize($table)) &&
          self::is_linked($table) &&
          isset($model['keys']['PRIMARY']) &&
          (\count($model['keys']['PRIMARY']['columns']) === 1) &&
          ($primary = $model['keys']['PRIMARY']['columns'][0]) &&
          !empty($model['fields'][$primary])
        ){
          // Looking for the config of the table
          self::$structures[$table]['history'] = 1;
          self::$structures[$table]['primary'] = $primary;
          self::$structures[$table]['id'] = $dbc->table_id($db->tsn($table), $db->current);
          self::$structures[$table]['fields'] = array_filter($model['fields'], function($a){
            return $a['id_option'] !== null;
          });
        }
      }
      // The table exists and has history
      if ( isset(self::$structures[$table]) && !empty(self::$structures[$table]['history']) ){
        return $table;
      }
    }
    return null;
	}

	public static function is_linked(string $table): bool
  {
    return ($db = self::_get_db()) &&
      ($ftable = $db->tfn($table)) &&
      isset(self::$links[$ftable]);
  }

  public static function get_links(){
	  return self::$links;
  }

  /**
   * The function used by the db trigger
   * This will basically execute the history query if it's configured for.
   *
   * @param array $cfg
   * @return bool returns true
   * @internal param string "table" The table for which the history is called
   * @internal param string "kind" The type of action: select|update|insert|delete
   * @internal param string "moment" The moment according to the db action: before|after
   * @internal param array "values" key/value array of fields names and fields values selected/inserted/updated
   * @internal param array "where" key/value array of fields names and fields values identifying the row
   *
   */
  public static function trigger(array $cfg){
    if ( !self::check() ){
      return $cfg;
    }
    $tables = (array)$cfg['table'];
    // Will return false if disabled, the table doesn't exist, or doesn't have history
    //die(var_dump(self::get_table_cfg($tables[0]), $tables[0], $cfg));
    if ( ($db = self::_get_db()) && ($table = $db->tfn($tables[0])) && self::get_table_cfg($table) ){
      /** @var array $s The table's structure and configuration */
      $s =& self::$structures[$table];

      // This happens before the query is executed
      if ( $cfg['moment'] === 'before' ){

        $primary_defined = false;
        $primary_value = false;

        // We will add a verification on the history field to not interfere with deleted entries
        if ( $cfg['kind'] === 'where' ){
          foreach ( $tables as $t ){
            $cfn = $db->cfn(self::$column, $t);
            // Only if the history col is neither in the where config nor in the update values
            if (
              !isset($cfg['values'][self::$column]) &&
              !\in_array($cfn, $cfg['where']['fields'], true)
            ){
              $cfg['where']['fields'][] = $cfn;
              $cfg['where']['values'][] = 1;
              $cfg['where']['final'][] = [$cfn, '=', 1];
              $cfg['where']['keyval'][$cfn] = 1;
              $cfg['where']['unique'][] = [$cfn, '='];
            }
          }
        }
        // Queries to be executed after
        else if ( !isset($cfg['history']) ){
          $cfg['history'] = [];
        }

        $primary = $db->cfn($s['primary'], $table);

        /** @var bool $primary_defined */
        if ( isset($cfg['where']['keyval'][$primary]) ){
          foreach ( $cfg['where']['final'] as $ar ){
            if ( ($ar[0] === $primary) && ($ar[1] === '=') ){
              $primary_defined = true;
              $primary_value = $ar[2];
              break;
            }
          }
        }

        switch ( $cfg['kind'] ){

          case 'insert':
            // If the primary is specified and already exists in a row in deleted state
            // (if it exists in active state, DB will return its standard error but it's not this class' problem)
            if ( isset($cfg['values'][$s['primary']]) ){
              // We check if a row exists and get its content
              $all = self::$db->rselect($table, [], [
                $s['primary'] => \bbn\str::is_buid($cfg['values'][$s['primary']]) ?
                  bin2hex($cfg['values'][$s['primary']]) :
                  $cfg['values'][$s['primary']],
                self::$column => 0
              ]);
              if ( empty($all) ){
                $modelize = $db->modelize(self::get_table_cfg($table));
                $keys = $modelize['keys'];
                unset($keys['PRIMARY']);
                foreach ( $keys as $key ){
                  if ( !empty($key['unique']) && !empty($key['columns']) ){
                    $fields = [];
                    $exit = false;
                    foreach ( $key['columns'] as $col ){
                      if ( is_null($cfg['values'][$col]) ){
                        $exit = true;
                      }
                      $fields[$col] = \bbn\str::is_buid($cfg['values'][$col]) ?
                        bin2hex($cfg['values'][$col]) :
                        $cfg['values'][$col];
                    }
                    if ( $exit ){
                      continue;
                    }
                    $fields[self::$column] = 0;
                    if ( $all = self::$db->rselect($table, [], $fields) ){
                      break;
                    }
                  }
                }
              }
              if ( !empty($all) ){
                // We won't execute the after trigger
                $cfg['trig'] = false;
                // Real query's execution will be prevented
                $cfg['run'] = false;
                $cfg['value'] = 0;
                /** @var array $update The values to be updated */
                $update = [];
                // We update each element which needs to (the new ones different from the old, and the old ones different from the default)
                foreach ( $all as $k => $v ){
                  $cfn = self::$db->cfn($k, $table);
                  if ( $k !== $s['primary'] ){
                    if ( isset($cfg['values'][$k]) &&
                      ($cfg['values'][$k] !== $v)
                    ){
                      $update[$k] = $cfg['values'][$k];
                    }
                    else if ( !isset($cfg['values'][$k]) && ($v !== $s['fields'][$k]['default']) ){
                      $update[$k] = $s['fields'][$k]['default'];
                    }
                  }
                }
                $update[self::$column] = 1;
                if ( \count($update) > 0 ){
                  $cfg['value'] = self::$db->update($table, $update, [
                    $s['primary'] => !empty($all) ? $all[$s['primary']] : $cfg['values'][$s['primary']],
                    self::$column => 0
                  ]);
                  self::$db->set_last_insert_id(!empty($all) ? $all[$s['primary']] : $cfg['values'][$s['primary']]);
                }
              }
              else {
                if ( self::$db->insert(self::$table_uids, [
                  'uid' => $cfg['values'][$s['primary']],
                  'id_table' => $s['id']
                ]) ){
                  $cfg['history'][] = [
                    'operation' => 'INSERT',
                    'column' => $s['fields'][$s['primary']]['id_option'],
                    'line' => $cfg['values'][$s['primary']],
                    'chrono' => microtime(true)
                  ];
                }
                self::$db->set_last_insert_id($cfg['values'][$s['primary']]);
              }
            }
            break;

          case 'update':
            if ( $primary_defined ){
              $where = [$s['primary'] => $primary_value];
              // If the only update regards the history field
              if (
                isset($cfg['values'][self::$column]) &&
                self::$db->count($table, [
                  $s['primary'] => $primary_value,
                  self::$column => $cfg['values'][self::$column] ? 0 : 1
                ])
              ){
                $cfg['special'] = !empty($cfg['values'][self::$column]) ? 'RESTORE' : 'DELETE';
                if ( !empty($cfg['values'][self::$column]) ){
                  $where[self::$column] = 0;
                }
              }
              $row = self::$db->rselect($table, array_keys($cfg['values']), $where);
              $time = microtime(true);
              foreach ( $cfg['values'] as $k => $v ){
                if (
                  ($row[$k] !== $v) &&
                  isset($s['fields'][$k])
                ){
                  if ( $k === self::$column ){
                    $cfg['history'][] = [
                      'operation' => $cfg['special'],
                      'column' => $s['fields'][self::$column]['id_option'],
                      'line' => $cfg['where']['keyval'][$primary],
                      'old' => NULL,
                      'chrono' => $time
                    ];
                  }
                  else {
                    $cfg['history'][] = [
                      'operation' => 'UPDATE',
                      'column' => $s['fields'][$k]['id_option'],
                      'line' => $cfg['where']['keyval'][$primary],
                      'old' => $row[$k],
                      'chrono' => $time
                    ];
                  }
                }
              }
            }
            // Case where the primary is not defined, we'll update each primary instead
            else {
              $ids = self::$db->get_column_values($table, $s['primary'], $cfg['where']['final']);
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run'] = false;
              $cfg['value'] = 0;
              foreach ( $ids as $id ){
                $cfg['value'] += self::$db->update($table, $cfg['values'], [$s['primary'] => $id]);
              }
            }
            break;

          // Nothing is really deleted, the hcol is just set to 0
          case 'delete':
            // We won't execute the after trigger
            $cfg['trig'] = false;
            // Real query's execution will be prevented
            $cfg['run'] = false;
            $cfg['value'] = 0;
            // Case where the primary is not defined, we'll delete based on each primary instead
            if ( !$primary_defined ){
              $ids = self::$db->get_column_values($table, $s['primary'], $cfg['where']['final']);
              foreach ( $ids as $id ){
                $cfg['value'] += self::$db->delete($table, [$s['primary'] => $id]);
              }
            }
            else if ( $cfg['value'] = self::$db->update($table, [
              self::$column => 0
            ], [
              $s['primary'] => $cfg['where']['keyval'][$db->cfn($s['primary'], $table)]
            ]) ){
              $cfg['trig'] = 1;
              // And we insert into the history table
              $cfg['history'][] = [
                'operation' => 'DELETE',
                'column' => $s['fields'][self::$column]['id_option'],
                'line' => $cfg['where']['keyval'][$db->cfn($s['primary'], $table)],
                'old' => NULL,
                'chrono' => microtime(true)
              ];
            }
            break;
        }
      }
      else if ( ($cfg['moment'] === 'after') && !empty($cfg['history']) && !empty($cfg['run']) ){
        foreach ($cfg['history'] as $h){
          self::_insert($h);
        }
      }
    }
    return $cfg;
  }
}
