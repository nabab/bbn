<?php
namespace bbn\appui;

use bbn;

class history
{
  use bbn\models\tts\report;

  /** @var \bbn\db The DB connection */
  private static $db;
  /** @var array A collection of DB connections  */
	private static $dbs = [];
  /** @var array A collection of DB connections  */
	private static $structures = [];
  /** @var databases The databases class which collects the columns IDs */
	private static $databases_obj;
  /** @var string Name of the database where the history table is */
	private static $admin_db = '';
  /** @var string User's ID  */
	private static $user;
  /** @var string Prefix of the history table */
	private static $prefix = 'bbn_';
  /** @var float The current date can be overwritten if this variable is set */
	private static $date;
  /** @var boolean Set to true once the initial configuration has been checked */
  private static $ok = false;
  /** @var boolean Setting it to false avoid execution of history triggers */
	private static $enabled = true;
  /** @var array The foregin links atytached to history UIDs' table */
	private static $links;

  /** @var boolean|string The history table's name */
  public static $table_uids = false;
  /** @var boolean|string The history table's name */
  public static $table = false;
  /** @var string The UIDs table */
  public static $uids = 'uids';
  /** @var string The history default column's name */
  public static $column = 'active';
  /** @var boolean */
  public static $is_used = false;

  /**
   * Returns the database connection object.
   *
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
   * Returns an instance of the appui\database class.
   *
   * @return databases
   */
  private static function _get_databases(): ?databases
  {
    if ( self::check() ){
      return self::$databases_obj;
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
    if (
      isset($cfg['column'], $cfg['line'], $cfg['chrono']) &&
      self::check() &&
      ($db = self::_get_db())
    ){
      // Recording the last ID
      $id = $db->last_id();
      $last = self::$db->last();
      if ( !array_key_exists('old', $cfg) ){
        $cfg['ref'] = null;
        $cfg['val'] = null;
      }
      else if (
        bbn\str::is_uid($cfg['old']) &&
        self::$db->count(self::$table_uids, ['uid' => $cfg['old']])
      ){
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
      ($databases_obj = self::_get_databases()) &&
      ($model = $databases_obj->modelize($table))
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
      ($databases_obj = self::_get_databases())
    ){
      [$database, $table] = explode('.', $full_table);
      return $databases_obj->column_id($column, $table, $database, self::$db->host);
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
      self::$table = self::$admin_db.'.'.self::$prefix.'history';
      self::$table_uids = self::$admin_db.'.'.self::$prefix.'history_uids';
      self::$ok = true;
      self::$is_used = true;
      self::$links = self::$db->get_foreign_keys('uid', self::$prefix.'history_uids', self::$admin_db);
      self::$db->set_trigger('\\bbn\\appui\\history::trigger');
      if ( !self::$databases_obj ){
        self::$databases_obj = new bbn\appui\databases($db);
      }
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
    /** @todo To be redo totally with all the fields' IDs instead of the history column */
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
      $uid = $db->cfn('uid', self::$table_uids);
      $id_tab = $db->cfn('id_table', self::$table_uids);
      $id_col = $db->cfn('col', self::$table);
      $line = $db->cfn('uid', self::$table, true);
      $chrono = $db->escape('tst');
      $where = [
        $uid => $id,
        $id_tab => $id_table,
        [$chrono, '>', $date]
      ];
      if ( $column ){
        $where[$id_col] = bbn\str::is_uid($column) ? $column : $dbc->column_id($column, $id_table);
      }
      else {
        $w = self::_get_table_where($table);
        //$where = $id_col." != UNHEX('$id_column') " . ($w ?: '');
      }
      return $db->rselect_all([
        'tables' => [$tab_uids],
        'fields' => [$line, $id_col, $chrono, 'val' => 'IFNULL(val, ref)', $db->csn('usr', true)],
        'join' => [
          [
            'table' => $tab,
            'on' => [$uid => $id]
          ]
        ],
        'where' => $where,
        'order' => [$chrono => 'ASC'],
        'limit' => 1
      ]);
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
  public static function get_prev_update(string $table, string $id, $from_when, $column = null): ?array
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
  public static function get_row_back(string $table, string $id, $when, array $columns = []): ?array
  {
    if ( !($when = self::valid_date($when)) ){
      self::_report_error("The date $when is incorrect", __CLASS__, __LINE__);
    }
    else if (
      ($db = self::_get_db()) &&
      ($dbc = self::_get_databases()) &&
      ($model = $dbc->modelize($table)) &&
      ($cfg = self::get_table_cfg($table))
    ){

      if ( $when >= time() ){
        return $db->rselect($table, $columns, [
          $cfg['primary'] => $id
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
          $r[$col] = $db->select_one($table, $col, [
            $cfg['primary'] => $id
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
      ($cfg = self::get_table_cfg($table)) &&
      ($id_col = self::get_id_column($cfg['primary'], $table))
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
              $r[] = [
                'date' => $crea['date'],
                'val' => $h['old'],
                'user' => $crea['user']
              ];
            }
            else{
              $r[] = [
                'date' => $hist['upd'][$i-1]['date'],
                'val' => $h['old'],
                'user' => $hist['upd'][$i-1]['user']
              ];
            }
          }
          $r[] = [
            'date' => $hist['upd'][$i]['date'],
            'val' => $current,
            'user' => $hist['upd'][$i]['user']
          ];
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
   * @return null|array Table's full name
   */
	public static function get_table_cfg(string $table, bool $force = false): ?array
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
          'primary_type' => null,
          'primary_length' => 0,
          'auto_increment' => false,
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
          self::$structures[$table]['primary_type'] = $model['fields'][$primary]['type'];
          self::$structures[$table]['primary_length'] = $model['fields'][$primary]['maxlength'];
          self::$structures[$table]['auto_increment'] = isset($model['fields'][$primary]['extra']) && ($model['fields'][$primary]['extra'] === 'auto_increment');
          self::$structures[$table]['id'] = $dbc->table_id($db->tsn($table), $db->current);
          self::$structures[$table]['fields'] = array_filter($model['fields'], function($a){
            return $a['id_option'] !== null;
          });
        }
      }
      // The table exists and has history
      if ( isset(self::$structures[$table]) && !empty(self::$structures[$table]['history']) ){
        return self::$structures[$table];
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
   * @internal param string "table" The table for which the history is called
   * @internal param string "kind" The type of action: select|update|insert|delete
   * @internal param string "moment" The moment according to the db action: before|after
   * @internal param array "values" key/value array of fields names and fields values selected/inserted/updated
   * @internal param array "where" key/value array of fields names and fields values identifying the row
   * @return array The $cfg array, modified or not
   *
   */
  public static function trigger(array $cfg){
    if ( !self::check() || !($db = self::_get_db()) ){
      return $cfg;
    }
    $tables = $cfg['tables'] ?? (array)$cfg['table'];
    // Will return false if disabled, the table doesn't exist, or doesn't have history
    //die(var_dump(self::get_table_cfg($tables[0]), $tables[0], $cfg));
    if (
      ($cfg['kind'] === 'SELECT') &&
      ($cfg['moment'] === 'before') &&
      !empty($cfg['tables']) &&
      (bbn\x::find($cfg['join'], ['table' => self::$table_uids]) === false)
    ){
      $change = 0;
      if ( empty($cfg['history']) ){
        foreach ( $cfg['join'] as $t ){
          $model = $db->modelize($t['table']);
          if (
            isset($model['keys']['PRIMARY']) &&
            ($model['keys']['PRIMARY']['ref_table'] === $db->csn(self::$table_uids))
          ){
            $change++;
            $cfg['join'][] = [
              'table' => self::$table_uids,
              'alias' => $db->tsn(self::$table_uids).$change,
              'type' => $t['type'] ?? 'right',
              'on' => [
                'conditions' => [
                  [
                    'field' => $db->cfn(self::$table_uids.$change.'.uid'),
                    'operator' => 'eq',
                    'exp' => $db->cfn(
                      $model['keys']['PRIMARY']['columns'][0],
                      !empty($t['alias']) ? $t['alias'] : $t['table'],
                      true
                    )
                  ], [
                    'field' => $db->cfn(self::$table_uids.$change.'.active'),
                    'operator' => '=',
                    'exp' => '1'
                  ]
                ],
                'logic' => 'AND'
              ]
            ];
          }
        }
        foreach ( $cfg['tables'] as $alias => $table ){
          $model = $db->modelize($table);
          if (
            isset($model['keys']['PRIMARY']['ref_table']) &&
            ($db->tfn($model['keys']['PRIMARY']['ref_db'].'.'.$model['keys']['PRIMARY']['ref_table']) === self::$table_uids)
          ){
            $change++;
            $cfg['join'][] = [
              'table' => self::$table_uids,
              'alias' => $db->tsn(self::$table_uids).$change,
              'on' => [
                'conditions' => [
                  [
                    'field' => $db->cfn(self::$table_uids.$change.'.uid'),
                    'operator' => 'eq',
                    'exp' => $db->cfn($model['keys']['PRIMARY']['columns'][0], \is_string($alias) ? $alias : $table, true)
                  ], [
                    'field' => $db->cfn(self::$table_uids.$change.'.active'),
                    'operator' => '=',
                    'exp' => '1'
                  ]
                ],
                'logic' => 'AND'
              ]
            ];
          }
        }
        $cfg['history'] = 1;
        if ( $change ){
          $cfg = $db->reprocess_cfg($cfg);
        }
      }
    }

    if (
      ($table = $db->tfn(current($tables))) &&
      ($s = self::get_table_cfg($table))
    ){
      // This happens before the query is executed
      if ( $cfg['moment'] === 'before' ){

        $primary_defined = false;
        $primary_value = false;
        $idx = array_search($s['primary'], $cfg['fields'], true);
        if ( ($idx > -1) && isset($cfg['values'][$idx]) ){
          $primary_defined = true;
          $primary_value = $cfg['values'][$idx];
        }

        switch ( $cfg['kind'] ){

          case 'INSERT':
            // If the primary is specified and already exists in a row in deleted state
            // (if it exists in active state, DB will return its standard error but it's not this class' problem)
            if ( $primary_defined ){
              // We check if a row exists and get its content
              self::disable();
              $all = self::$db->rselect($table, [$s['primary'] => $primary_value]);
              self::enable();
            }
            if ( empty($all) ){
              // Checks if there is a unique value (non based on UID)
              $modelize = $db->modelize($table);
              $keys = $modelize['keys'];
              unset($keys['PRIMARY']);
              foreach ( $keys as $key ){
                if ( !empty($key['unique']) && !empty($key['columns']) ){
                  $fields = [];
                  $exit = false;
                  foreach ( $key['columns'] as $col ){
                    $col_idx = array_search($col, $cfg['fields'], true);
                    if ( ($col_idx > -1) ){
                      if ( \is_null($cfg['values'][$col_idx]) ){
                        $exit = true;
                        break;
                      }
                      $fields[] = [
                        'field' => $col,
                        'operator' => 'eq',
                        'value' => $cfg['values'][$col_idx]
                      ];
                    }
                  }
                  if ( $exit ){
                    continue;
                  }
                  if ( $all = self::$db->rselect([
                    'tables' => [$table],
                    'where' => [
                      'conditions' => $fields
                    ]
                  ]) ){
                    break;
                  }
                }
              }
            }
            // A record already exists
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
              self::disable();
              //die(var_dump($update, $cfg));
              if ( \count($update) > 0 ){
                self::$db->update($table, $update, [
                  $s['primary'] => !empty($all) ? $all[$s['primary']] : $cfg['values'][$s['primary']]
                ]);
                //die("jjjj");
              }
              if ( $cfg['value'] = self::$db->update(self::$table_uids, ['active' => 1], [
                'uid' => $primary_value
              ]) ){
                self::$db->set_last_insert_id(!empty($all) ? $all[$s['primary']] : $cfg['values'][$s['primary']]);
                $cfg['history'][] = [
                  'operation' => 'RESTORE',
                  'column' => $s['fields'][$s['primary']]['id_option'],
                  'line' => $primary_value,
                  'chrono' => microtime(true)
                ];
              }
              self::enable();
            }
            else {
              self::disable();
              //die("INSERT");
              if ( self::$db->insert(self::$table_uids, [
                'uid' => $primary_value,
                'id_table' => $s['id']
              ]) ){
                $cfg['history'][] = [
                  'operation' => 'INSERT',
                  'column' => $s['fields'][$s['primary']]['id_option'],
                  'line' => $primary_value,
                  'chrono' => microtime(true)
                ];
              }
              self::$db->set_last_insert_id($primary_value);
              self::enable();
            }
            break;

          case 'UPDATE':
            if ( $primary_defined ){
              self::disable();
              $where = [$s['primary'] => $primary_value];
              // If the only update regards the history field
              $row = self::$db->rselect($table, array_keys($cfg['values']), $where);
              $time = microtime(true);
              foreach ( $cfg['values'] as $k => $v ){
                if (
                  ($row[$k] !== $v) &&
                  isset($s['fields'][$k])
                ){
                  $cfg['history'][] = [
                    'operation' => 'UPDATE',
                    'column' => $s['fields'][$k]['id_option'],
                    'line' => $primary_value,
                    'old' => $row[$k],
                    'chrono' => $time
                  ];
                }
              }
              self::enable();
            }
            // Case where the primary is not defined, we'll update each primary instead
            else {
              $ids = self::$db->get_column_values($table, $s['primary'], $cfg['filters']);
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
          case 'DELETE':
            // We won't execute the after trigger
            $cfg['trig'] = false;
            // Real query's execution will be prevented
            $cfg['run'] = false;
            $cfg['value'] = 0;
            // Case where the primary is not defined, we'll delete based on each primary instead
            if ( !$primary_defined ){
              $ids = self::$db->get_column_values($table, $s['primary'], $cfg['filters']);
              foreach ( $ids as $id ){
                $cfg['value'] += self::$db->delete($table, [$s['primary'] => $id]);
              }
            }
            else {
              self::disable();
              $cfg['value'] = self::$db->update(self::$table_uids, [
                self::$column => 0
              ], [
                'uid' => $primary_value
              ]);
              self::enable();
              if ( $cfg['value'] ){
                $cfg['trig'] = 1;
                // And we insert into the history table
                $cfg['history'][] = [
                  'operation' => 'DELETE',
                  'column' => $s['fields'][$s['primary']]['id_option'],
                  'line' => $primary_value,
                  'old' => NULL,
                  'tst' => microtime(true)
                ];
              }
            }
            break;
        }
      }
      else if (
        ($cfg['moment'] === 'after') &&
        !empty($cfg['history']) &&
        !empty($cfg['run']) &&
        \is_array($cfg['history'])
      ){
        self::disable();
        foreach ($cfg['history'] as $h){
          self::_insert($h);
        }
        self::enable();
      }
    }
    return $cfg;
  }
}
