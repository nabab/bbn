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
    $enabled = true;

  public static
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
    if ( isset($cfg['operation'], $cfg['column'], $cfg['line']) && self::check() ){
      // Recording the last ID
      $db = self::_get_db();
      $id = $db->last_id();
      // New row in the history table
      if ( $res = $db->insert(self::$table, [
        'operation' => $cfg['operation'],
        'line' => $cfg['line'],
        'column' => $cfg['column'],
        'old' => $cfg['old'] ?? null,
        'chrono' => self::$date ?: microtime(1),
        'id_user' => self::$user
      ]) ){
        // Set back the original last ID
        $db->set_last_insert_id($id);
      }
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
      $col = $db->escape('column');
      $where_ar = [];
      foreach ( $model['fields'] as $k => $f ){
        if ( !empty($f['id_option']) ){
          $where_ar[] = $col.' = UNHEX("'.$db->escape_value($f['id_option']).'")';
        }
      }
      if ( count($where_ar) ){
        return implode(' OR ', $where_ar);
      }
    }
    return null;
  }

  /**
   * Returns the column's corresponding option's ID
   * @param $column string
   * @param $table string
   * @return bool|string
   */
  public static function get_id_column(string $column, string $table): string
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
   * @param bbn\db $db
   * @param array $cfg
   * @return void
   */
  public static function init(bbn\db $db, array $cfg = []): void
  {
    $hash = $db->get_hash();
    if ( !in_array($hash, self::$dbs, true) && $db->check() ){
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
      self::$ok = true;
      self::$is_used = true;
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
    return in_array($hash, self::$dbs, true);
  }

  /**
   * Effectively deletes a row (deletes the row, the history row and the ID row)
   *
   * @param string $id
   * @return bool
   */
	public static function delete(string $id): bool
  {
		if (
		  $id &&
      ($db = self::_get_db()) &&
      $db->delete(self::$prefix.self::$uids, ['uid' => $id])
    ){
      $tab = $db->escape(self::$table);
      $line = $db->escape('line');
      return $db->query(<<< MYSQL
      DELETE FROM $tab
      WHERE $line = ?
MYSQL
      , $id);
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
		if ( bbn\str::is_number($user) ){
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
      ($where = self::_get_table_where($table))
    ){
      $tab = $db->escape(self::$table);
      $line = $db->escape('line');
      $chrono = $db->escape('chrono');
      $order = $dir && (bbn\str::change_case($dir, 'lower') === 'asc') ? 'ASC' : 'DESC';
      $sql = <<< MYSQL
SELECT DISTINCT($line)
FROM $tab
WHERE $where
ORDER BY $chrono $order
LIMIT $start, $limit
MYSQL;
      $r = $db->get_rows($sql);
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
      ($where = self::_get_table_where($table)) &&
      ($db = self::_get_db())
    ){
      $tab = $db->escape(self::$table);
      $line = $db->escape('line');
      $operation = $db->escape('operation');
      $chrono = $db->escape('chrono');
      $sql = <<< MYSQL
SELECT DISTINCT($line)
FROM $tab
WHERE ($where)
AND (
  $operation LIKE 'INSERT'
  OR $operation LIKE 'UPDATE'
)
ORDER BY $chrono DESC
LIMIT $start, $limit
MYSQL;
      $r = $db->get_rows($sql);
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
  public static function get_next_update(string $table, string $id, $from_when, $column = null): ?array
  {
    if (
      bbn\str::check_name($table) &&
      ($date = self::valid_date($from_when)) &&
      ($databases_class = self::_get_databases()) &&
      ($db = self::_get_db())
    ){
      $tab = $db->escape(self::$table);
      $line = $db->escape('line');
      $operation = $db->escape('operation');
      $chrono = $db->escape('chrono');
      if ( $column ){
        $where = $db->escape('column').
          ' = UNHEX("'.$db->escape_value(
            bbn\str::is_uid($id) ? $id : $databases_class->column_id($column, $table, $db->current)
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
AND $chrono > ?
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
   * @param $id
   * @param null $column
   * @return null|array
   */
  public static function get_prev_update(string $table, string $id, $from_when, $column = null): ?array
  {
    if (
      bbn\str::check_name($table) &&
      ($date = self::valid_date($from_when)) &&
      ($databases_class = self::_get_databases()) &&
      ($db = self::_get_db())
    ){
      $tab = $db->escape(self::$table);
      $line = $db->escape('line');
      $operation = $db->escape('operation');
      $chrono = $db->escape('chrono');
      if ( $column ){
        $where = $db->escape('column').
          ' = UNHEX("'.$db->escape_value(
            bbn\str::is_uid($column) ? $column : $databases_class->column_id($column, $table)
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
      return $r['old'];
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
      return $r['old'];
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
      ($databases_class = self::_get_databases()) &&
      ($model = $databases_class->modelize($table)) &&
      ($table = self::get_table_cfg($table))
    ){
      if ( $when >= time() ){
        return $db->rselect($table, $columns, [
          self::$structures[$table]['primary'] => $id
        ]);
      }
      if ( $when < self::get_creation_date($table, $id) ){
        return null;
      }
      if ( count($columns) === 0 ){
        $columns = array_keys($model['fields']);
      }
      $r = [];
      foreach ( $columns as $col ){
        if ( isset($model['fields'][$col], $model['fields'][$col]['id_option']) ){
          $r[$col] = $db->select_one(self::$table, 'old', [
            'line' => $id,
            'column' => $model['fields'][$col]['id_option'],
            'operation' => 'UPDATE',
            ['chrono', '>', $when]
          ]);
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
      if ( $r = $db->rselect(self::$table, ['date' => 'chrono', 'user' => 'id_user'], [
        'line' => $id,
        'column' => $id_col,
        'operation' => 'INSERT'
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
  public static function get_last_date(string $table, string $id, $column = null): ?float
  {
    if ( $db = self::_get_db() ){
      if (
        $column &&
        ($id_col = self::get_id_column($column, $table))
      ){
        return self::$db->select_one(self::$table, 'chrono', [
          'line' => $id,
          'column' => $id_col
        ], [
          'chrono' => 'DESC'
        ]);
      }
      else if ( !$column && ($where = self::_get_table_where($table)) ){
        $tab = $db->escape(self::$table);
        $chrono = $db->escape('chrono');
        $line = $db->escape('line');
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
            'date' => 'chrono',
            'user' => 'id_user',
            'old',
            'column',
            'id'
          ],[
            ['line', '=', $id],
            ['column', 'LIKE', $table.'.'.( $col ? $col : '%' )],
            ['operation', 'LIKE', $p]
          ],[
            'chrono' => 'desc'
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
      $line = $db->escape('line');
      $chrono = $db->escape('chrono');
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
      ($table = $db->tfn($table))
    ){
      if ( $force || !isset(self::$structures[$table]) ){
        self::$structures[$table] = [
          'history' => false,
          'primary' => false,
          'fields' => []
        ];
        if (
          ($model = $db->modelize($table)) &&
          isset($model['keys']['PRIMARY'], $model['keys']['PRIMARY']['columns'], $model['fields'][self::$column]) &&
          (count($model['keys']['PRIMARY']['columns']) === 1) &&
          ($primary = $model['keys']['PRIMARY']['columns'][0]) &&
          !empty($model['fields'][$primary])
        ){
          // Looking for the config of the table
          self::$structures[$table]['history'] = 1;
          self::$structures[$table]['primary'] = $primary;
        }
      }
      // The table exists and has history
      if ( isset(self::$structures[$table]) && !empty(self::$structures[$table]['history']) ){
        return $table;
      }
    }
    return null;
	}

	/**
	 * The function used by the db trigger
   * This will basically execute the history query if it's configured for.
   *
   * @param string $table The table for which the history is called
   * @param string $kind The type of action: select|update|insert|delete
   * @param string $moment The moment according to the db action: before|after
   * @param array $values key/value array of fields names and fields values selected/inserted/updated
   * @param array $where key/value array of fields names and fields values identifying the row
   *
   * @return bool returns true
	 */
  public static function trigger(array $cfg){
    if ( !self::check() ){
      return $cfg;
    }
    $tables = (array)$cfg['table'];
    // Will return false if disabled, the table doesn't exist, or doesn't have history
    //die(var_dump(self::get_table_cfg($tables[0]), $tables[0], $cfg));
    if (
      ($db = self::_get_db()) &&
      ($table = self::get_table_cfg($tables[0]))
    ){
      //die("hjhjhhjhj");

      /** @var array $s The table's structure and configuration */
      $s =& self::$structures[$table];

      // This happens before the query is executed
      if ( $cfg['moment'] === 'before' ){

        // We will add a verification on the history field to not interfere with deleted entries
        if ( $cfg['kind'] === 'where' ){
          foreach ( $tables as $t ){
            $cfn = $db->cfn(self::$column, $t);
            // Only if the history col is neither in the where config nor in the update values
            if (
              !isset($cfg['values'][self::$column]) &&
              !in_array($cfn, $cfg['where']['fields'], true)
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

        /** @var bool $primary_defined */
        $primary_defined = false;
        $primary = $db->cfn($s['primary'], $table);
        if ( isset($cfg['where']['keyval'][$primary]) ){
          foreach ( $cfg['where']['final'] as $ar ){
            if ( $db->csn($ar[0]) === $s['primary'] ){
              if ( $ar[1] === '=' ){
                $primary_defined = true;
              }
              break;
            }
          }
        }

        switch ( $cfg['kind'] ){

          case 'insert':
            // If the primary is specified and already exists in a row in deleted state
            // (if it exists in active state, DB will return its standard error but it's not this class' problem)
            if ( isset($cfg['values'][$s['primary']]) &&
              $db->select_one($table, $s['primary'], [
                $s['primary'] => $cfg['values'][$s['primary']],
                self::$column => 0
              ])
            ){
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run'] = false;
              // We restore the element
              $cfg['value'] = self::$db->update($table, [self::$column => 1], [
                $s['primary'] => $cfg['values'][$s['primary']],
                self::$column => 0
              ]);
              // We get the content of the row
              $all = self::$db->rselect($table, [], [$s['primary'] => $cfg['values'][$s['primary']]]);
              /** @var array $update The values to be updated */
              $update = [];
              // We update each element which needs to (the new ones different from the old, and the old ones different from the default)
              foreach ( $all as $k => $v ){
                if ( isset($cfg['values'][$k]) &&
                  ($cfg['values'][$k] !== $v)
                ){
                  $update[$k] = $v;
                }
                else if ( !isset($cfg['values'][$k]) &&
                  ($v != $s['fields'][$k]['default'])
                ){
                  $update[$k] = $s['fields'][$k]['default'];
                }
              }
              if ( count($update) > 0 ){
                self::$db->update($table, $update, [$s['primary'] => $cfg['values'][$s['primary']]]);
              }
            }
            else {
              array_push($cfg['history'], [
                'operation' => 'INSERT',
                'column' => self::get_id_column($s['primary'], $table)
              ]);
            }
            break;

          case 'update':
            if ( $primary_defined ){
              // If the only update regards the history field
              if ( isset($cfg['values'][self::$column]) ){
                $history_value = self::$db->select_one($table, self::$column, $cfg['where']['final']);
                if ( $cfg['values'][self::$column] !== $history_value ){
                  array_push($cfg['history'], [
                    'operation' => $history_value === 1 ? 'DELETE' : 'RESTORE',
                    'column' => self::get_id_column(self::$column, $table),
                    'line' => $cfg['where']['keyval'][$primary],
                    'old' => $history_value
                  ]);
                }
              }
              $row = self::$db->rselect($table, array_keys($cfg['values']), $cfg['where']['final']);
              foreach ( $cfg['values'] as $k => $v ){
                if ( ($k !== self::$column) && ($row[$k] !== $v) ){
                  array_push($cfg['history'], [
                    'operation' => 'UPDATE',
                    'column' => self::get_id_column($k, $table),
                    'line' => $cfg['where']['keyval'][$primary],
                    'old' => $row[$k]
                  ]);
                }
              }
              //bbn\x::dump($cfg);
            }
            // Case where the primary is not defined, we'll update each primary instead
            else{
              $ids = self::$db->get_column_values($table, $s['primary'], $cfg['where']['final']);
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run'] = false;
              $cfg['value'] = 0;
              foreach ( $ids as $id ){
                $cfg['value'] += self::$db->update($table, $cfg['values'], array_merge([[$s['primary'], '=', $id]], $cfg['where']['final']));
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
            else if ( $cfg['value'] = self::$db->query(
              self::$db->get_update($table, [self::$column], [
                  [$s['primary'], '=', $cfg['where']['keyval'][$primary]],
                  [self::$column, '=', 1]
                ]
              ), [0, $cfg['where']['keyval'][$primary], 1])
            ){
              $cfg['trig'] = 1;
              // And we insert into the history table
              array_push($cfg['history'], [
                'operation' => 'DELETE',
                'column' => self::get_id_column(self::$column, $table),
                'line' => $cfg['where']['keyval'][$primary],
                'old' => 1
              ]);
            }
            break;
        }
      }
      else if ( $cfg['moment'] === 'after' ){
        if ( isset($cfg['history']) ){
          if ( $cfg['kind'] === 'insert' ){
            $id = self::$db->last_id();
            foreach ($cfg['history'] as $i => $h){
              $cfg['history'][$i]['line'] = $id;
            }
          }
          $last = self::$db->last();
          foreach ($cfg['history'] as $i => $h){
            self::_insert($h);
          }
          self::$db->last_query = $last;
        }
      }
    }
    return $cfg;
  }
}
