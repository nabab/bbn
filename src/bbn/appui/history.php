<?php
namespace bbn\appui;

use \bbn\str\text;

class history
{

	private static
          /** @var \bbn\db\connection $db The DB connection */
          $db,
          /** @var array A collection of the  */
          $dbs = [],
          $hstructures = array(),
          $admin_db = '',
          $huser = false,
          $prefix = 'bbn_',
          $primary = 'id',
          $date = false,
          $last_rows = false,
          $ok = false,
          $enabled = 1;

  public static
          $htable = false,
          $hcol = 'active',
          $is_used = false;

	/**
	 * @return void
	 */
  public static function disable()
  {
    self::$enabled = false;
  }

	/**
	 * @return void
	 */
  public static function enable()
  {
    self::$enabled = 1;
  }

	/**
	 * @return bool
	 */
  public static function is_enabled()
  {
    return self::$enabled === 1;
  }

  /**
   * @return number|false
   */
  public static function valid_date($d){
    if ( !\bbn\str\text::is_number($d) ){
      $d = strtotime($d);
    }
    if ( \bbn\str\text::is_number($d) && ($d > 0) ){
      return $d;
    }
    return false;
  }

  /**
	 * @return void
	 */
	public static function init(\bbn\db\connection $db, $cfg = [])
	{
    $hash = $db->get_hash();
    if ( !in_array($hash, self::$dbs) ){
      array_push(self::$dbs, $hash);
      self::$db = $db;
      self::$db->set_trigger('\\bbn\\appui\\history::trigger');
      $vars = get_class_vars('\\bbn\\appui\\history');
      foreach ( $cfg as $cf_name => $cf_value ){
        if ( array_key_exists($cf_name, $vars) ){
          self::$$cf_name = $cf_value;
        }
      }
      if ( !self::$admin_db ){
        self::$admin_db = self::$db->current;
      }
      self::$htable = self::$admin_db.'.'.self::$prefix.'history';
      self::$ok = 1;
      self::$is_used = 1;
    }
	}

	/**
	 * @return void
	 */
  public static function is_init(){
    return self::$ok;
  }

	/**
	 * @return bool
	 */
  public static function has_history($db){
    $hash = $db->get_hash();
    return in_array($hash, self::$dbs) && self::$enabled;
  }

	/**
	 * @return void
	 */
	public static function delete($table, $id)
	{
		// Sets the "active" column name
		if ( self::is_init() && text::check_name($table) && \bbn\str\text::is_integer($id) ){
      self::$db->query("
        DELETE FROM ".self::$db->escape(self::$htable)."
        WHERE ".self::$db->escape('column')." LIKE '".self::$db->table_full_name($table).".%'
        AND ".self::$db->escape('line')." = $id");
		}
	}

	/**
   * Sets the "active" column name
   *
	 * @return void
	 */
	public static function set_hcol($hcol)
	{
		if ( text::check_name($hcol) ){
			self::$hcol = $hcol;
		}
	}

	/**
   * Gets the "active" column name
   *
	 * @return string the "active" column name
	 */
	public static function get_hcol()
	{
		if ( text::check_name(self::$hcol) ){
			self::$hcol = self::$hcol;
		}
	}

	/**
	 * @return void
	 */
	public static function set_date($date)
	{
		// Sets the current date
		if ( !\bbn\str\text::is_number($date) ){
      if ( !($date = strtotime($date)) ){
        return false;
      }
    }
    $t = time();
    // Impossible to write history in the future
    if ( $date > $t ){
      $date = $t;
    }
		self::$date = $date;
	}

	/**
	 * @return date
	 */
	public static function get_date()
	{
		return self::$date;
	}

	/**
	 * @return void
	 */
	public static function unset_date()
	{
		self::$date = false;
	}

 /**
  * Sets the history table name
	* @return void
	*/
	public static function set_admin_db($db)
	{
		// Sets the history table name
		if ( text::check_name($db) ){
			self::$admin_db = $db;
			self::$htable = self::$admin_db.'.'.self::$prefix.'history';
		}
	}

	/**
	 * Sets the user ID that will be used to fill the user_id field
	 * @return void
	 */
	public static function set_huser($huser)
	{
		// Sets the history table name
		if ( \bbn\str\text::is_number($huser) ){
			self::$huser = $huser;
		}
	}

	/**
	 * Gets the user ID that is being used to fill the user_id field
	 * @return int
	 */
	public static function get_huser()
	{
		return self::$huser;
	}

  public static function get_all_history($table, $start=0, $limit=20, $dir=false){
    $r = [];
    if ( \bbn\str\text::check_name($table) && is_int($start) && is_int($limit) ){
      $r = self::$db->get_rows("
        SELECT DISTINCT(`line`)
        FROM ".self::$db->escape(self::$htable)."
        WHERE `column` LIKE ?
        ORDER BY chrono ".(
                is_string($dir) &&
                        (\bbn\str\text::change_case($dir, 'lower') === 'asc') ?
                  'ASC' : 'DESC' )."
        LIMIT $start, $limit",
        self::$db->table_full_name($table).'.%');
    }
    return $r;
  }

  public static function get_last_modified_lines($table, $start=0, $limit=20){
    $r = [];
    if ( \bbn\str\text::check_name($table) && is_int($start) && is_int($limit) ){
      $r = self::$db->get_rows("
        SELECT DISTINCT(".self::$db->escape('line').")
        FROM ".self::$db->escape(self::$htable)."
        WHERE ".self::$db->escape('column')." LIKE ?
        AND ( ".self::$db->escape('operation')." LIKE 'INSERT'
                OR ".self::$db->escape('operation')." LIKE 'UPDATE' )
        ORDER BY ".self::$db->escape('chrono')." DESC
        LIMIT $start, $limit",
        self::$db->table_full_name($table).'.%');
    }
    return $r;
  }

  public static function get_next_update($table, $date, $id, $column=''){
    if ( \bbn\str\text::check_name($table) &&
      ($date = self::valid_date($date)) &&
      is_int($id) &&
      (empty($column) || \bbn\str\text::check_name($column))
    ){
      $table = self::$db->table_full_name($table).'.'.( empty($column) ? '%' : $column );
      return self::$db->get_row("
        SELECT *
        FROM ".self::$db->escape(self::$htable)."
        WHERE ".self::$db->escape('column')." LIKE ?
        AND ".self::$db->escape('line')." = ?
        AND ".self::$db->escape('operation')." LIKE 'UPDATE'
        AND ".self::$db->escape('chrono')." > ?
        ORDER BY ".self::$db->escape('chrono')." ASC
        LIMIT 1",
        $table,
        $id,
        $date);
    }
    return false;
  }

  public static function get_prev_update($table, $date, $id, $column=''){
    if ( \bbn\str\text::check_name($table) &&
      ($date = self::valid_date($date)) &&
      is_int($id) &&
      (empty($column) || \bbn\str\text::check_name($column))
    ){
      $table = self::$db->table_full_name($table).'.'.( empty($column) ? '%' : $column );
      return self::$db->get_row("
        SELECT *
        FROM ".self::$db->escape(self::$htable)."
        WHERE ".self::$db->escape('column')." LIKE ?
        AND ".self::$db->escape('line')." = ?
        AND ".self::$db->escape('operation')." LIKE 'UPDATE'
        AND ".self::$db->escape('chrono')." < ?
        ORDER BY ".self::$db->escape('chrono')." DESC
        LIMIT 1",
        $table,
        $id,
        $date);
    }
    return false;
  }

  public static function get_prev_value($table, $date, $id, $column){
    if ( \bbn\str\text::check_name($table) &&
      ($date = self::valid_date($date)) &&
      is_int($id) &&
      \bbn\str\text::check_name($column)
    ){
      $table = self::$db->table_full_name($table).'.'.$column;
      return self::$db->get_one("
        SELECT old
        FROM ".self::$db->escape(self::$htable)."
        WHERE ".self::$db->escape('column')." LIKE ?
        AND ".self::$db->escape('line')." = ?
        AND ".self::$db->escape('operation')." LIKE 'UPDATE'
        AND ".self::$db->escape('chrono')." < ?
        ORDER BY ".self::$db->escape('chrono')." DESC
        LIMIT 1",
        $table,
        $id,
        $date);
    }
    return false;
  }

  public static function get_row_back($table, array $columns, array $where, $when){
    if ( !($when = self::valid_date($when)) ){
      die("The date $when is incorrect");
    }
    if ( !\bbn\str\text::check_name($table) ){
			die("The table $table has an incorrect name");
		}
		if ( !($primary = self::$db->get_unique_primary($table)) ){
			die("The table $table doesn't have a unique primary");
		}
		if ( !isset($where[$primary]) ){
			die("The primary key $primary needs to be sent");
		}
    if ( count($columns) === 0 ){
      $columns = array_keys(self::$db->get_columns($table));
    }
		if ( $when >= time() ){
			return self::$db->rselect($table, $columns, [
				$primary => $where[$primary]
			]);
		}
		if ( $when < self::get_creation_date($table, $where[$primary]) ){
			return false;
		}
    foreach ( $columns as $col ){
      $fc = self::$db->current.'.'.self::$db->col_full_name($col, $table);
      $r[$col] = self::$db->get_one("
        SELECT old
        FROM bbn_history
        WHERE ".self::$db->escape('column')." LIKE ?
        AND ".self::$db->escape('line')." = ?
        AND ".self::$db->escape('operation')." LIKE 'UPDATE'
        AND chrono >= ?
        ORDER BY chrono ASC
        LIMIT 1",
        $fc,
        $where[$primary],
        $when);
			if ( $r[$col] === false ){
				$r[$col] = self::$db->get_val($table, $col, [
					$primary => $where[$primary]
				]);
			}
    }
    return $r;
  }

  public static function get_val_back($table, $column, array $where, $when){
    if ( $row = self::get_row_back($table, [$column], $where, $when) ){
      return $row[$column];
    }
    return false;
  }

  public static function get_creation_date($table, $id){
    if ( self::check($table) ) {
      return self::$db->select_one(self::$htable, 'chrono', [
        'column' => self::$db->table_full_name($table) . ".%",
        'line' => $id,
        'operation' => 'INSERT'
      ]);
    }
    return false;
  }

  public static function get_creation($table, $id){
    if ( self::check($table) ) {
      return self::$db->rselect(self::$htable, ['date' => 'chrono', 'user' => 'id_user'], [
        ['column', 'LIKE', self::$db->table_full_name($table) . ".%"],
        'line' => $id,
        'operation' => 'INSERT'
      ]);
    }
    return false;
  }

  public static function get_last_date($table, $id, $column = null){
    if ( is_string($column) ){
      return self::$db->select_one(
              self::$htable,
              'chrono', [
                ['column', 'LIKE', self::$db->table_full_name($table).".".$column],
                ['line', '=', $id]
              ],
              ['chrono' => 'DESC']);
    }
    return self::$db->select_one(
            self::$htable,
            'chrono', [
              ['column', 'LIKE', self::$db->table_full_name($table).'.%'],
              ['line', '=', $id],
              ['operation', 'NOT LIKE', 'DELETE']
            ],
            ['chrono' => 'DESC']);
  }

	public static function get_history($table, $id, $col=''){
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
          self::$htable,
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

	public static function get_full_history($table, $id){
    if ( self::check($table) ){
      $r = [];
    }
	}

  public static function get_column_history($table, $id, $column){
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
	 * @return table full name
	 */
	public static function get_table_cfg($table, $force = false){

    // Check history is enabled and table's name correct
    if ( self::$enabled && self::check($table) ){
      $table = self::$db->tfn($table);

      // Looking for the config of the table
      if ( !isset(self::$hstructures[$table]) || $force ){
        self::$hstructures[$table] = [
          'history'=>false,
          'fields' => []
        ];
        $s =& self::$hstructures[$table];
        $model = self::$db->modelize($table);
        if ( isset($model['keys']['PRIMARY']) &&
                (count($model['keys']['PRIMARY']['columns']) === 1) ){
          $s['primary'] = $model['keys']['PRIMARY']['columns'][0];
        }
        $cols = self::$db->rselect_all(
                self::$admin_db.'.'.self::$prefix.'columns',
                [],
                ['table' => $table],
                'position');
        foreach ( $cols as $col ){
          $c = $col['column'];
          if ( $col['default'] === 'NULL' ){
            $col['default'] = null;
          }
          $s['fields'][$c] = $col;
          $s['fields'][$c]['config'] = json_decode($col['config'], 1);
          /*
          // Per field approach
          if ( isset($s['fields'][$c]['config']['history']) && $s['fields'][$c]['config']['history'] == 1 ){
            $s['history'] = 1;
          }
          */
        }
        if ( isset($s['fields'][self::$hcol]) ){
          $s['history'] = 1;
        }
      }

      // The table exists and has history
      if ( isset(self::$hstructures[$table], self::$hstructures[$table]['history']) &&
        self::$hstructures[$table]['history']
      ){
        return $table;
      }
    }
    return false;
	}

  public static function add($table, $operation, $date, $values=[], $where=[])
  {
    if ( self::check($table) ){

    }
  }

 /**
  * This checks if the table is not part of the system's tables and makes the script die if a user has not been configured
  *
	* @return 1
	*/
  private static function check($table=null){
    if ( !isset(self::$huser, self::$htable, self::$db) ){
      die('One of the key elements has not been configured in history (user? database?)');
    }
		/*
    if ( !empty($table) ){
      $table = self::$db->tsn($table);
      if ( strpos($table, self::$prefix) === 0 ){
        return false;
      }
    }
		*/
    return 1;
  }

  private static function fcol($col, $table){
    if ( self::check() && ($table = self::$db->tfn($table)) ){
      return $table.'.'.self::$db->csn($col);
    }
    return false;
  }

  private static function _insert(array $cfg){
    if ( self::$enabled && isset($cfg['operation'], $cfg['column'], $cfg['line']) ){
      $id = self::$db->last_id();
      $res = self::$db->insert(self::$htable, [
        'operation' => $cfg['operation'],
        'line' => $cfg['line'],
        'column' => $cfg['column'],
        'old' => isset($cfg['old']) ? $cfg['old'] : null,
        'chrono' => self::$date ? self::$date : microtime(1),
        'id_user' => self::$huser]);
      self::$db->set_last_insert_id($id);
      return $res;
    }
  }

	/**
	 * The function used by the \bbn\db\connection trigger
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
    if ( !self::$enabled ){
      return $cfg;
    }
    $tables = is_array($cfg['table']) ? $cfg['table'] : [$cfg['table']];
    // Will return false if disabled, the table doesn't exist, or doesn't have history
    if ( $table = self::get_table_cfg($tables[0]) ){

      /** @var array $s The table's structure and configuration */
      $s =& self::$hstructures[$table];

      // Need to have a single primary key, otherwise the script dies
      if ( !isset($s['primary']) ){
        self::$db->error("You need to have a primary key on a single column in your table $table in order to use the history class");
        die(\bbn\x::hdump("You need to have a primary key on a single column in your table $table in order to use the history class"));
      }

      // This happens before the query is executed
      if ( $cfg['moment'] === 'before' ){

        // We will add a verification on the history field to not interfere with deleted entries
        if ( $cfg['kind'] === 'where' ){
          foreach ( $tables as $t ){
            $cfn = self::$db->cfn(self::$hcol, $t);
            // Only if the history col is neither in the where config nor in the update values
            if ( !in_array($cfn, $cfg['where']['fields']) &&
              !isset($cfg['values'][self::$hcol])
            ){
              array_push($cfg['where']['fields'], $cfn);
              array_push($cfg['where']['values'], 1);
              array_push($cfg['where']['final'], [$cfn, '=', 1]);
              $cfg['where']['keyval'][$cfn] = 1;
              array_push($cfg['where']['unique'], [$cfn, '=']);
            }
          }
        }
        // Queries to be executed after
        else if ( !isset($cfg['history']) ){
          $cfg['history'] = [];
        }

        /** @var bool $primary_defined */
        $primary_defined = false;
        $primary = self::$db->cfn($s['primary'], $table);
        if ( isset($cfg['where']['keyval'][$primary]) ){
          foreach ( $cfg['where']['final'] as $ar ){
            if ( self::$db->csn($ar[0]) === $s['primary'] ){
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
              self::$db->select_one($table, $s['primary'], [
                $s['primary'] => $cfg['values'][$s['primary']],
                self::$hcol => 0
              ])
            ){
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run'] = false;
              // We restore the element
              $cfg['value'] = self::$db->update($table, [ self::$hcol => 1], [
                $s['primary'] => $cfg['values'][$s['primary']],
                self::$hcol => 0
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
              if ( count($update) > 0 ) {
                self::$db->update($table, $update, [$s['primary'] => $cfg['values'][$s['primary']]]);
              }
            }
            else {
              array_push($cfg['history'], [
                'operation' => 'INSERT',
                'column' => self::fcol($s['primary'], $table)
              ]);
            }
            break;

          case 'update':
            if ( $primary_defined ){
              // If the only update regards the history field
              if ( isset($cfg['values'][self::$hcol]) ){
                $history_value = self::$db->select_one($table, self::$hcol, $cfg['where']['final']);
                if ( $cfg['values'][self::$hcol] !== $history_value ){
                  array_push($cfg['history'], [
                    'operation' => $history_value === 1 ? 'DELETE' : 'RESTORE',
                    'column' => self::fcol(self::$hcol, $table),
                    'line' => $cfg['where']['keyval'][$primary],
                    'old' => $history_value
                  ]);
                }
              }
              $row = self::$db->rselect($table, array_keys($cfg['values']), $cfg['where']['final']);
              foreach ( $cfg['values'] as $k => $v ){
                if ( ($k !== self::$hcol) && ($row[$k] !== $v) ){
                  array_push($cfg['history'], [
                    'operation' => 'UPDATE',
                    'column' => self::fcol($k, $table),
                    'line' => $cfg['where']['keyval'][$primary],
                    'old' => $row[$k]
                  ]);
                }
              }
              //\bbn\x::dump($cfg);
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
              self::$db->get_update($table, [self::$hcol], [
                  [$s['primary'], '=', $cfg['where']['keyval'][$primary]],
                  [self::$hcol, '=', 1]
                ]
              ), [0, $cfg['where']['keyval'][$primary], 1])
            ){
              $cfg['trig'] = 1;
              // And we insert into the history table
              array_push($cfg['history'], [
                'operation' => 'DELETE',
                'column' => self::fcol(self::$hcol, $table),
                'line' => $cfg['where']['keyval'][$primary],
                'old' => 1
              ]);
            }
            break;
        }
      }
      else if ( $cfg['moment'] === 'after' ){
        if ( isset($cfg['history']) ){
          if ( $cfg['kind'] === 'insert' ) {
            $id = self::$db->last_id();
            foreach ($cfg['history'] as $i => $h) {
              $cfg['history'][$i]['line'] = $id;
            }
          }
          $last = self::$db->last();
          foreach ($cfg['history'] as $i => $h) {
            self::_insert($h);
          }
          self::$db->last_query = $last;
        }
      }
    }
    return $cfg;
  }
}
