<?php
namespace bbn\appui;

use \bbn\str\text;

class history
{
	
	private static
          /**
           * @var \bbn\db\connection The DB connection
           */
          $db = false,
          $hstructures = array(),
          $hcol = 'active',
          $htable = false,
          $admin_db = '',
          $huser = false,
          $prefix = 'bbn_',
          $primary = 'id',
          $date = false,
          $last_rows = false;
	
	/**
	 * @return void 
	 */
	public static function init(\bbn\db\connection $db, $cfg = [])
	{
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
	}
	
	/**
	 * @return void 
	 */
	public static function set_hcol($hcol)
	{
		// Sets the "active" column name 
		if ( text::check_name($hcol) ){
			self::$hcol = $hcol;
		}
	}
	
	/**
	 * @return void 
	 */
	public static function set_date($date)
	{
		// Sets the "active" column name 
		if ( !is_int($date) ){
      $date = strtotime($date);
    }
		self::$date = $date;
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
		if ( is_int($huser) ){
			self::$huser = $huser;
		}
	}

 /**
  * This will make the script die if a user has not been configured
	* @return 1
	*/
	private static function check_config()
	{
		if ( !isset(self::$huser, self::$htable, self::$db) ){
      die('One of the key elements has not been configured in history (user? database?)');
		}
    return 1;
	}
  
  public function get_all_history($table, $start=0, $limit=20){
    $r = [];
    if ( \bbn\str\text::check_name($table) && is_int($start) && is_int($limit) ){
      $r = self::$db->get_rows("
        SELECT DISTINCT(`line`)
        FROM ".self::$db->escape_name(self::$htable)."
        WHERE `column` LIKE ?
        ORDER BY last_mod DESC
        LIMIT $start, $limit",
        self::$db->table_full_name($table).'.%');
    }
    return $r;
  }
  
  public function get_mod_history($table, $start=0, $limit=20){
    $r = [];
    if ( \bbn\str\text::check_name($table) && is_int($start) && is_int($limit) ){
      $r = self::$db->get_rows("
        SELECT DISTINCT(`line`)
        FROM ".self::$db->escape_name(self::$htable)."
        WHERE `column` LIKE ?
        AND ( `operation` LIKE 'INSERT' OR `operation` LIKE 'UPDATE' )
        ORDER BY last_mod DESC
        LIMIT $start, $limit",
        self::$db->table_full_name($table).'.%');
    }
    return $r;
  }
	
	public static function get_history($table, $id){
    if ( self::check_config() ){
      $r = [];
      $args = [self::$db->table_full_name($table).'.%', $id];
      $q = self::$db->get_row("
        SELECT `last_mod`, `id_user`
        FROM ".self::$db->escape_name(self::$htable)."
        WHERE `column` LIKE ?
        AND `line` = ?
        AND `operation` LIKE 'INSERT'
        ORDER BY `last_mod` ASC
        LIMIT 1",
        $args);
      if ( $q ){
        $r['ins'] = [
          'date' => $q['last_mod'],
          'user' => $q['id_user']
        ];
      }
      $q = self::$db->get_row("
        SELECT `last_mod`, `id_user`
        FROM ".self::$db->escape_name(self::$htable)."
        WHERE `column` LIKE ?
        AND `line` = ?
        AND `operation` LIKE 'UPDATE'
        ORDER BY `last_mod` DESC
        LIMIT 1",
        $args);
      if ( $q ){
        $r['upd'] = [
          'date' => $q['last_mod'],
          'user' => $q['id_user']
        ];
      }
      $q = self::$db->get_row("
      SELECT `last_mod`, `id_user`
      FROM ".self::$db->escape_name(self::$htable)."
      WHERE `column` LIKE ?
      AND `line` = ?
      AND `operation` LIKE 'DELETE'
      ORDER BY `last_mod` DESC
      LIMIT 1",
      $args);
      if ( $q ){
        $r['del'] = [
          'date' => $q['last_mod'],
          'user' => $q['id_user']
        ];
      }
      return $r;
    }
	}
		
	public static function get_full_history($table, $id){
    if ( self::check_config() ){
      $r = [];
    }
	}
	
	/**
	 * Gets all information about a given table
	 * @return table full name
	 */
	public static function get_table_cfg($table){
    if ( self::check_config() ){
      $table = self::$db->table_full_name($table);
      if ( !isset(self::$hstructures[$table]) ){
        if ( !isset(self::$db->cache['structures'][$table]) ){
          self::$db->modelize($table);
        }
        if ( !isset(self::$db->cache['structures'][$table]) ){
          die("The table $table doesn't seem to exist");
        }
        if ( !isset(self::$db->cache['structures'][$table]['keys']['PRIMARY']['columns']) || count(self::$db->cache['structures'][$table]['keys']['PRIMARY']['columns']) !== 1 ){
          //die("You need to have a primary key on a single column in your table $table in order to use the history class");
        }
        self::$hstructures[$table] = ['history'=>false, 'fields' => [], 'primary' => $primary = self::$db->cache['structures'][$table]['keys']['PRIMARY']['columns'][0]];
        $cols = self::$db->select_all(self::$admin_db.'.'.self::$prefix.'columns',[],['table' => $table], 'position');
        $s =& self::$hstructures[$table];
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
      }
    }
	}
	
	/**
	 * Gets all information about a given table
	 * @return table full name
	 */
  public static function trigger($table, $kind, $moment, $values=[], $where=[])
  {
    if ( self::check_config() && self::$htable !== $table ){
      $table = self::$db->table_full_name($table);
      if ( !isset(self::$hstructures[$table]) ){
        self::get_table_cfg($table);
      }
      if ( isset(self::$hstructures[$table], self::$hstructures[$table]['history']) && self::$hstructures[$table]['history'] === 1 ){
        $s =& self::$hstructures[$table];
        $date = self::$date ? self::$date : date('c');
        switch ( $kind ){
          case 'select':
            break;
          case 'insert':
            if ( $moment === 'after' ){
              $id = self::$db->last_id();
              self::$db->insert(self::$htable, [
                'operation' => 'INSERT',
                'line' => $id,
                'column' => $table.'.'.self::$primary,
                'old' => '',
                'last_mod' => $date,
                'id_user' => self::$huser
              ]);
              self::$db->last_insert_id = $id;
            }
            break;
          case 'update':
            if ( $moment === 'before' ){
              self::$last_rows = self::$db->select_all($table, array_keys($values), $where);
            }
            else if ( $moment === 'after' ){
              foreach ( self::$last_rows as $upd ){
                $upd = (array) $upd;
                foreach ( $values as $c => $v ){
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
              self::$last_rows = false;
            }
            break;
          case 'delete':
            if ( $moment === 'before' ){
              // Looking for foreign constraints 
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
              // Nothing is really deleted, the hcol is just set to 0
              if ( $r = self::$db->update($table, [self::$hcol => '0'], $where) ){
                // The values from the constrained rows that should have been deleted
                $delete = self::$db->select_all($table, array_unique($to_select), $where);
                // For each value of this key which is deleted (hopefully one)
                foreach ( $delete as $del ){
                  $del = (array) $del;
                  // For each table having a constrain
                  foreach ( $to_check as $c ){
                    // If it's nullable we set it to null
                    if ( $c['null'] == 1 ){
                      self::$db->query("
                        UPDATE `$c[table]`
                        SET `$c[to_change]` = NULL ]
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
              }
              return false;
            }
            break;
        }
      }
    }
    return 1;
  }
	
}
?>