<?php
namespace bbn\appui;

use \bbn\str\text;

class history
{
	
	private static
          /* @var \bbn\db\connection The DB connection */
          $db = false,
          /* @var array A collection of the  */
          $dbs = [],
          $hstructures = array(),
          $admin_db = '',
          $huser = false,
          $prefix = 'bbn_',
          $primary = 'id',
          $date = false,
          $last_rows = false,
          $ok = false,
          $enabled = true;
	
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
		self::$date = date('Y-m-d H:i:s', $date);
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
        ORDER BY last_mod ".(
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
        AND ( ".self::$db->escape('operation')." LIKE ?
                OR ".self::$db->escape('operation')." LIKE ? )
        ORDER BY ".self::$db->escape('last_mod')." DESC
        LIMIT $start, $limit",
        self::$db->table_full_name($table).'.%',
        'INSERT',
        'UPDATE');
    }
    return $r;
  }
  
  public static function get_row_back($table, array $columns, array $where, $when){
    if ( !is_int($when) ){
      $when = strtotime($when);
    }
    $when = (int) $when;
    if ( \bbn\str\text::check_name($table) && ($when > 0) && (count($where) === 1) ){
      $when = date('Y-m-d H:i:s', $when);
      if ( count($columns) === 0 ){
        $columns = array_keys(self::$db->get_columns($table));
      }
      foreach ( $columns as $col ){
        $fc = self::$db->current.'.'.self::$db->col_full_name($col, $table);
        if ( !($r[$col] = self::$db->get_one("
          SELECT old
          FROM bbn_history
          WHERE ".self::$db->escape('column')." LIKE ?
          AND ".self::$db->escape('line')." = ?
          AND (
            ".self::$db->escape('operation')." LIKE ?
            OR ".self::$db->escape('operation')." LIKE ?
          )
          AND last_mod >= ?
          ORDER BY last_mod ASC",
          $fc,
          end($where),
          "UPDATE",
          "DELETE",
          $when)) ){
          $r[$col] = self::$db->get_val($table, $col, $where);
        }
      }
      return $r;
    }
    return false;
  }
  
  public static function get_first_date($table, $id, $column = null){
    if ( is_string($column) ){
      return self::$db->select_one(
              self::$htable,
              'last_mod', [
                ['column', 'LIKE', self::$db->table_full_name($table).".".$column],
                ['line', '=', $id]
              ],
              ['last_mod' => 'ASC']);
    }
    return self::$db->select_one(
            self::$htable,
            'last_mod', [
              ['column', 'LIKE', self::$db->table_full_name($table).'.%'],
              ['line', '=', $id],
              ['operation', 'LIKE', 'INSERT']
            ],
            ['last_mod' => 'ASC']);
  }
  
  public static function get_last_date($table, $id, $column = null){
    if ( is_string($column) ){
      return self::$db->select_one(
              self::$htable,
              'last_mod', [
                ['column', 'LIKE', self::$db->table_full_name($table).".".$column],
                ['line', '=', $id]
              ],
              ['last_mod' => 'DESC']);
    }
    return self::$db->select_one(
            self::$htable,
            'last_mod', [
              ['column', 'LIKE', self::$db->table_full_name($table).'.%'],
              ['line', '=', $id],
              ['operation', 'NOT LIKE', 'DELETE']
            ],
            ['last_mod' => 'DESC']);
  }
	
	public static function get_history($table, $id){
    if ( self::check($table) ){
      $pat = [
        'ins' => 'INSERT',
        'upd' => 'UPDATE',
        'del' => 'DELETE'
      ];
      $r = [];
      $table = self::$db->table_full_name($table);
      foreach ( $pat as $k => $p ){
        if ( $q = self::$db->rselect(
          self::$htable,
          [
            'date' => 'last_mod',
            'user' => 'id_user'
          ],[
            ['line', '=', $id],
            ['column', 'LIKE', "$table.%"],
            ['operation', 'LIKE', $p]
          ],[
            'last_mod' => 'desc'
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
	
	/**
	 * Gets all information about a given table
	 * @return table full name
	 */
	public static function get_table_cfg($table){
    if ( self::check($table) ){
      $table = self::$db->table_full_name($table);
      if ( isset(self::$hstructures[$table]) ){
        return self::$hstructures[$table];
      }
      else if ( $cfg = self::$db->modelize($table) ){
        self::$hstructures[$table] = [
          'history'=>false,
          'fields' => []
        ];
        $s =& self::$hstructures[$table];
        if ( isset($cfg['keys']['PRIMARY']) && 
                (count($cfg['keys']['PRIMARY']['columns']) === 1) ){
          $s['primary'] = $cfg['keys']['PRIMARY']['columns'][0];
        }
        $cols = self::$db->select_all(
                self::$admin_db.'.'.self::$prefix.'columns',
                [],
                ['table' => $table],
                'position');
        foreach ( $cols as $col ){
          $col = (array) $col;
          $c = $col['column'];
          $s['fields'][$c] = $col;
          $s['fields'][$c]['config'] = (array)json_decode($col['config']);
          if ( isset($s['fields'][$c]['config']['history']) && $s['fields'][$c]['config']['history'] == 1 ){
            $s['history'] = 1;
          }
          if ( isset($s['fields'][$c]['config']['keys']) ){
            $s['fields'][$c]['config']['keys'] = (array) $s['fields'][$c]['config']['keys'];
          }
        }
        return self::$hstructures[$table];
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
    if ( !empty($table) ){
      if ( strpos($table, '.') ){
        $ts = explode('.', $table);
        $table = end($ts);
      }
      if ( strpos($table, self::$prefix) === 0 ){
        return false;
      }
    }
		if ( !isset(self::$huser, self::$htable, self::$db) ){
      die('One of the key elements has not been configured in history (user? database?)');
		}
    return 1;
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
  public static function trigger($table, $kind, $moment, $values=[], $where=[])
  {
    if ( self::$enabled && self::check($table) ){
      $table = self::$db->table_full_name($table);
      
      if ( !isset(self::$hstructures[$table]) ){
        self::get_table_cfg($table);
      }
      if ( isset(self::$hstructures[$table], self::$hstructures[$table]['history']) && self::$hstructures[$table]['history'] ){
        $s =& self::$hstructures[$table];
        if ( !isset($s['primary']) ){
          die(\bbn\tools::dump("You need to have a primary key on a single column in your table $table in order to use the history class", $s));
        }

        $date = self::$date ? self::$date : date('Y-m-d H:i:s');
        
        if ( (count($values) === 1) && (array_keys($values)[0] === self::$hcol) ){
          $kind = array_values($values)[0] === 1 ? 'restore' : 'delete';
        }
          
        switch ( $kind ){
          case 'select':
            break;
          case 'insert':
            if ( $moment === 'before' && isset($values[$s['primary']]) ){
              if ( self::$db->select_one($table, self::$hcol, [$s['primary'] => $values[$s['primary']]]) === 0 ){
                self::$db->update($table, [self::$hcol=1], [$s['primary'] => $values[$s['primary']]]);
              }
            }
            else if ( $moment === 'after' ){
              $id = self::$db->last_id();
              self::$db->insert(self::$htable, [
                'operation' => 'INSERT',
                'line' => $id,
                'column' => $table.'.'.$s['primary'],
                'old' => null,
                'last_mod' => $date,
                'id_user' => self::$huser
              ]);
              self::$db->set_last_insert_id($id);
            }
            break;
          case 'restore':
            if ( $moment === 'after' ){
              self::$db->insert(self::$htable, [
                'operation' => 'RESTORE',
                'line' => $where[$s['primary']],
                'column' => $table.'.'.self::$hcol,
                'old' => '0',
                'last_mod' => $date,
                'id_user' => self::$huser
              ]);
            }
            break;
          case 'update':
            if ( $moment === 'before' ){
              self::$last_rows = self::$db->rselect_all($table, array_keys($values), $where);
            }
            else if ( $moment === 'after' ){
              if ( is_array(self::$last_rows) ){
                foreach ( self::$last_rows as $upd ){
                  foreach ( $values as $c => $v ){
                    if ( !isset($upd[$c]) ){
                      $upd[$c] = null;
                    }
                    if ( ( $v !== $upd[$c] ) && ( $c !== self::$hcol ) && isset($s['fields'][$c]['config']['history']) ){
                      self::$db->insert(self::$htable, [
                        'operation' => 'UPDATE',
                        'line' => $where[$s['primary']],
                        'column' => $table.'.'.$c,
                        'old' => $upd[$c],
                        'last_mod' => $date,
                        'id_user' => self::$huser]);
                    }
                  }
                }
              }
              // insert_update case
              else{
                $id = self::$db->last_id();
                self::$db->insert(self::$htable, [
                  'operation' => 'INSERT',
                  'line' => $id,
                  'column' => $table.'.'.$s['primary'],
                  'old' => '',
                  'last_mod' => $date,
                  'id_user' => self::$huser
                ]);
                self::$db->set_last_insert_id($id);
              }
              self::$last_rows = false;
            }
            break;
          case 'delete':
            if ( $moment === 'before' ){
              // Looking for foreign constraints 
              // Nothing is really deleted, the hcol is just set to 0
              $values = array_values($where);
              array_unshift($values, 0);
              if ( $r = self::$db->query(
                      self::$db->get_update(
                        $table,
                        [self::$hcol],
                        $where), $values) ){
                self::$db->insert(self::$htable, [
                  'operation' => 'DELETE',
                  'line' => $where[$s['primary']],
                  'column' => $table.'.'.self::$hcol,
                  'old' => 1,
                  'last_mod' => $date,
                  'id_user' => self::$huser]);
                return ['trig' => false, 'value' => $r];
                /* For each value of this key which is deleted (hopefully one)
                $to_check = self::$db->get_rows("
                  SELECT k.`column` AS id, c1.`column` AS to_change, c2.`column` AS from_change,
                  c1.`null`, t.`table`
                  FROM `".self::$admin_db."`.`".self::$prefix."keys` AS k
                    JOIN `".self::$prefix."columns` AS c1
                      ON c1.`id` LIKE k.`column`
                    JOIN `".self::$prefix."columns` AS c2
                      ON c2.`id` LIKE k.`ref_column`
                    JOIN `".self::$prefix."tables` AS t
                      ON t.`id` LIKE c1.`table`
                  WHERE k.`ref_column` LIKE ?",
                  $table.'.%%');
                $to_select = [self::$primary];
                foreach ( $to_check as $c ){
                  array_push($to_select, $c['from_change']);
                }
                // The values from the constrained rows that should have been deleted
                $delete = self::$db->select_all($table, array_unique($to_select), $where);
                foreach ( $delete as $del ){
                  $del = (array) $del;
                  // For each table having a constrain
                  foreach ( $to_check as $c ){
                    // If it's nullable we set it to null
                    if ( $c['null'] == 1 ){
                      self::$db->query("
                        UPDATE `$c[table]`
                        SET `$c[to_change]` = NULL
                        WHERE `$c[to_change]` = ?",
                        $del[$c['from_change']]);
                    }
                    // Then we "delete" it on the same manner
                    self::$db->delete($c['table'], [ $c['to_change'] => $del[$c['from_change']] ], $date);
                  }
                  // Inserting a new history row for each deleted value
                  self::$db->insert(self::$htable, [
                    'operation' => 'DELETE',
                    'line' => $del[$s['primary']],
                    'column' => $table.'.'.self::$hcol,
                    'old' => 1,
                    'last_mod' => $date,
                    'id_user' => self::$huser]);
                }
                 * 
                 */
              }
            }
            break;
        }
      }
    }
    return 1;
  }
	
}