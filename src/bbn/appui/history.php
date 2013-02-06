<?php
namespace bbn\appui;

use \bbn\str\text;

class history extends \bbn\db\connection implements \bbn\db\api
{
	
	private $hstructures = array();
	private
		$hcol = 'active',
		$htable,
		$admin_db,
		$huser,
		$prefix = 'bbn_',
		$primary = 'id';
	
	/**
	 * @return void 
	 */
	public function set_hcol($hcol)
	{
		// Sets the "active" column name 
		if ( text::check_name($hcol) ){
			$this->hcol = $hcol;
		}
		return $this;
	}
	
 /**
  * Sets the history table name
	* @return void 
	*/
	public function set_admin_db($db)
	{
		// Sets the history table name 
		if ( text::check_name($db) ){
			$this->admin_db = $db;
			$this->htable = $this->admin_db.'.'.$this->prefix.'history';
		}
		return $this;
	}
	
	/**
	 * Sets the user ID that will be used to fill the user_id field
	 * @return void 
	 */
	public function set_huser($huser)
	{
		// Sets the history table name 
		if ( is_int($huser) ){
			$this->huser = $huser;
		}
		return $this;
	}

 /**
  * This will make the script die if a user has not been configured
	* @return 1
	*/
	private function check_config()
	{
		if ( !isset($this->huser) ){
			die('No user has been configured');
		}
		if ( !isset($this->htable) ){
			if ( in_array($this->prefix.'columns', $this->get_tables()) ){
				$this->admin_db = $this->current;
				$this->htable = $this->admin_db.'.'.$this->prefix.'history';
			}
			else{
				die('No database has been configured');
			}
		}
		return 1;
	}
	
	public function get_history($table, $id){
		$r = [];
		$args = ['localhost.'.$this->current.'.'.$table.'.%', $id];
		$q = $this->get_row("
			SELECT `last_mod`, `id_user`
			FROM `bbn_history`
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
		$q = $this->get_row("
			SELECT `last_mod`, `id_user`
			FROM `bbn_history`
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
		$q = $this->get_row("
		SELECT `last_mod`, `id_user`
		FROM `bbn_history`
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
		
	public function get_full_history($table, $id){
		$r = [];
	}
	
	/**
	 * Gets all information about a given table
	 * @return table full name
	 */
	public function get_table_cfg($table){
		$parts = explode(".", $table);
		if ( count($parts) === 1 ){
			array_unshift($parts, $this->current);
		}
		if ( parent::get_full_name($table) ){
			$table = implode(".", $parts);
			if ( !isset($this->hstructures[$table]) ){
				if ( !isset($this->structures[$table]) ){
					parent::modelize($table);
					if ( !isset($this->structures[$table]['keys']['PRIMARY']['columns']) || count($this->structures[$table]['keys']['PRIMARY']['columns']) !== 1 ){
						die("You need to have a primary key on a single column in your table $table in order to use the history class");
					}
				}
				$this->hstructures[$table] = ['history'=>false, 'fields' => [], 'primary' => $primary = $this->structures[$table]['keys']['PRIMARY']['columns'][0]];
				$cols = $this->select_all($this->admin_db.'.'.$this->prefix.'columns',[],['table' =>$this->host.'.'.$table], 'position');
				$s =& $this->hstructures[$table];
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
	
	public function select($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
		if ( $this->check_config() ){
			return call_user_func_array(array($this, 'parent::select'), func_get_args());
		}
	}
	
	
	/**
	 * @return void 
	 */
	public function insert($table, array $values, $ignore = false, $date = false)
	{
		if ( $this->check_config() ){
			// This is the arguments that will be passed to the parent function
			$args = func_get_args();
			// If date is spcified it has to be removed
			if ( $date ){
				array_pop($args);
			}
			else{
				// One single date for all operations
				$date = date('c');
			}
			// Inserting first, historizing after
			$r = call_user_func_array(array($this, 'parent::insert'), $args);
			if ( ( $table = $this->get_full_name($table) ) && $table !== $this->htable && $r ){
				$id = $this->last_id();
				if ( !isset($this->hstructures[$table]) ){
					$this->get_table_cfg($table);
				}
				if ( $this->hstructures[$table]['history'] ){
					$this->insert($this->htable, [
						'operation' => 'INSERT',
						'line' => $id,
						'column' => $this->host.'.'.$table.'.'.$this->primary,
						'old' => '',
						'last_mod' => $date,
						'id_user' => $this->huser]);
					$this->last_insert_id = $id;
				}
			}
			return $r;
		}
	}
	
	/**
	 * @return void 
	 */
	public function insert_ignore($table, array $values, $date = false)
	{
		return $this->insert($table, $values, 1, $date);
	}
	
	/**
	 * @return void 
	 */
	public function insert_update($table, array $values, $date = false)
	{
		if ( $this->check_config() ){
			// This is the arguments that will be passed to the parent function
			$args = func_get_args();
			// If date is spcified it has to be removed from the arguments
			if ( $date ){
				array_pop($args);
			}
			else{
				// One single date for all operations
				$date = date('c');
			}
			if ( ( $table = $this->get_full_name($table) ) && $table !== $this->htable ){
				if ( !isset($this->hstructures[$table]) ){
					$this->get_table_cfg($table);
				}
				$s = $this->hstructures[$table];
				if ( !$s['history'] ){
					return call_user_func_array(array($this, 'parent::insert_update'), $args);
				}
				$update = false;
				foreach ( $s['fields'] as $f ){
					if ( !$update ){
						if ( isset($f['config']['keys']) ){
							foreach ( $f['config']['keys'] as $k => $inf ){
								if ( $inf->unique == 1 ){
									$has_key = true;
									$where = [];
									foreach ( $inf->columns as $col ){
										if ( !isset($values[$col]) ){
											$has_key = false;
											break;
										}
										else{
											$where[$col] = $values[$col];
										}
									}
									if ( $has_key && $update = (array) $this->select($table, [], $where) ){
										break;
									}
								}
							}
						}
					}
				}
				if ( $update ){
					if ( $r = call_user_func_array(array($this, 'parent::insert_update'), $args) ){
						foreach ( $values as $c => $v ){
							if ( $v !== $update[$c] && isset($s['fields'][$c]['config']['history']) ){
								$this->insert($this->htable, [
									'operation' => 'UPDATE',
									'line' => $update[$s['primary']],
									'column' => $this->host.'.'.$table.'.'.$c,
									'old' => $update[$c],
									'last_mod' => $date ? $date : date('c'),
									'id_user' => $this->huser]);
							}
						}
					}
				}
				else if ( $r = call_user_func_array(array($this, 'parent::insert_update'), $args) ){
					$id = $this->last_id();
					$this->insert($this->htable, [
						'operation' => 'INSERT',
						'line' => $id,
						'column' => $this->host.'.'.$table.'.'.$this->primary,
						'old' => '',
						'last_mod' => date('c'),
						'id_user' => $this->huser]);
					$this->last_insert_id = $id;
				}
			}
			return $r;
		}
	}
	
	/**
	 * @return void 
	 */
	public function update($table, array $values, array $where, $date = false)
	{
		if ( $this->check_config() ){
			$r = false;
			// This is the arguments that will be passed to the parent function
			$args = func_get_args();
			// If date is spcified it has to be removed from the arguments
			if ( $date ){
				array_pop($args);
			}
			else{
				// One single date for all operations
				$date = date('c');
			}
			if ( count($values) === 1 && isset($values[$this->hcol]) ){
				return call_user_func_array(array($this, 'parent::update'), $args);
			}
			// No update in the history table
			if ( ( $table = $this->get_full_name($table) ) && $table !== $this->htable ){
				if ( !isset($this->hstructures[$table]) ){
					$this->get_table_cfg($table);
				}
				$s = $this->hstructures[$table];
				if ( !$s['history'] ){
					return call_user_func_array(array($this, 'parent::update'), $args);
				}
				$fields = array_keys($values);
				array_push($fields, $s['primary']);
				$fields = array_unique($fields);

				$update = $this->select_all($table, $fields, $where);
				
				if ( $r = call_user_func_array(array($this, 'parent::update'), $args) ){
					foreach ( $update as $upd ){
						$upd = (array) $upd;
						foreach ( $values as $c => $v ){
							if ( $v !== $upd[$c] && isset($s['fields'][$c]['config']['history']) ){
								$this->insert($this->htable, [
									'operation' => 'UPDATE',
									'line' => $upd[$s['primary']],
									'column' => $this->host.'.'.$table.'.'.$c,
									'old' => $upd[$c],
									'last_mod' => $date ? $date : date('c'),
									'id_user' => $this->huser]);
							}
						}
					}
				}
			}
			return $r;
		}
	}
	
	/**
	 * @return void 
	 */
	public function delete($table, array $where, $date = false)
	{
		if ( $this->check_config() ){
			$r = false;
			// This is the arguments that will be passed to the parent function
			$args = func_get_args();
			// If date is specified it has to be removed from the arguments
			if ( $date ){
				array_pop($args);
			}
			else{
				// So we only have one single date for all operations
				$date = date('c');
			}
			// If it is the history's table we just don't proceed (no programmatical delete!!)
			if ( ( $table = $this->get_full_name($table) ) && $table !== $this->htable ){
				// Grabbing the structure if not already stored in hstructures
				if ( !isset($this->hstructures[$table]) ){
					$this->get_table_cfg($table);
				}
				$s =& $this->hstructures[$table];
				// Looking for foreign constraints 
				$to_check = $this->get_rows("
					SELECT k.`column` AS id, c1.`column` AS to_change, c2.`column` AS from_change,
					c1.`null`, t.`table`
					FROM `{$this->admin_db}`.`{$this->prefix}keys` AS k
						JOIN `{$this->prefix}columns` AS c1
							ON c1.`id` LIKE k.`column`
						JOIN `{$this->prefix}columns` AS c2
							ON c2.`id` LIKE k.`ref_column`
						JOIN `{$this->prefix}tables` AS t
							ON t.`id` LIKE c1.`table`
					WHERE k.`ref_column` LIKE ?",
					$this->host.'.'.$table.'.%%');
				$to_select = [$this->primary];
				foreach ( $to_check as $c ){
					array_push($to_select,$c['from_change']);
				}
				// Nothing is really deleted, the hcol is just set to 0
				if ( $r = $this->update($table, [$this->hcol => '0'], $where) ){
					// The values from the constrained rows that should have been deleted
					$delete = $this->select_all($table, array_unique($to_select), $where);
					// For each value of this key which is deleted (hopefully one)
					foreach ( $delete as $del ){
						$del = (array) $del;
						// For each table having a constrain
						foreach ( $to_check as $c ){
							// If it's nullable we set it to null
							if ( $c['null'] == 1 ){
								$this->update($c['table'], [ $c['to_change'] => null ], [ $c['to_change'] => $del[$c['from_change']] ]);
							}
							// Otherwise we "delete" it on the same manner
							else{
								$this->delete($c['table'], [ $c['to_change'] => $del[$c['from_change']] ]);
							}
						}
						// Inserting a new history row for each deleted value
						$this->insert($this->htable, [
							'operation' => 'DELETE',
							'line' => $del[$s['primary']],
							'column' => $this->host.'.'.$table.'.'.$this->hcol,
							'old' => 1,
							'last_mod' => $date,
							'id_user' => $this->huser]);
					}
				}
			}
			return $r;
		}
	}
}
?>