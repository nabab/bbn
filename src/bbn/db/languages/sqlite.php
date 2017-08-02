<?php
/**
 * @package db
 */
namespace bbn\db\languages;
use bbn;

/**
 * Database Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.3
 * @todo Finishing the get_where method and implement it in all the get functions
 */
class sqlite implements bbn\db\engines
{
  private $db;
	public static $operators=array('!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between', 'not like');
  public $qte = '"';
  /**
   * Constructor
   * @param bbn\db $db
   */
  public function __construct(bbn\db $db = null){
    if ( !extension_loaded('pdo_sqlite') ){
      die("The SQLite driver for PDO is not installed...");
    }
    $this->db = $db;
  }


  /**
	 * @return void 
	 */
  public function get_connection($cfg=[])
  {
    $cfg['engine'] = 'sqlite';
    if ( !isset($cfg['db']) && defined('BBN_DATABASE') ){
      $cfg['db'] = BBN_DATABASE;
    }
    if ( isset($cfg['db']) && strlen($cfg['db']) > 1 ){
      if ( is_file($cfg['db']) ){
        $pathinfo = pathinfo($cfg['db']);
        $cfg['host'] = $pathinfo['dirname'].DIRECTORY_SEPARATOR;
        $cfg['db'] = $pathinfo['basename'];
      }
      else if ( defined("BBN_DATA_PATH") && is_dir(BBN_DATA_PATH.'db') && strpos($cfg['db'], "/") === false ){
        $cfg['host'] = BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR;
        if ( !is_file(BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR.$cfg['db']) && strpos($cfg['db'], ".") === false ){
          $cfg['db'] .= '.sqlite';
        }
      }
      else{
        $pathinfo = pathinfo($cfg['db']);
        if ( is_writable($pathinfo['dirname']) ){
          $cfg['host'] = $pathinfo['dirname'].DIRECTORY_SEPARATOR;
          $cfg['db'] = isset($pathinfo['extension']) ? $pathinfo['basename'] : $pathinfo['basename'].'.sqlite';
        }
      }
      if ( isset($cfg['host']) ){
        $cfg['args'] = ['sqlite:'.$cfg['host'].$cfg['db']];
        $cfg['db'] = 'main';
        return $cfg;
      }
    }
    return false;
	}
	
	/**
	 * @return string | false
	 */
	public function change($db)
	{
    if ( strpos($db, '.') === false ){
      $db .= '.sqlite';
    }
    $pathinfo = pathinfo($db);
    if ( ( $pathinfo['filename'] !== $this->db->current ) && file_exists($this->db->host.$db) && strpos($db, "'") === false ){
      $this->db->raw_query("ATTACH '".$this->db->host.$db."' AS ".$pathinfo['filename']);
      return 1;
    }
		return false;
	}
	
	/**
	 * Returns a database item expression escaped like database, table, column, key names
	 * 
	 * @param string $item The item's name (escaped or not)
	 * @return string | false
	 */
	public function escape($item)
	{
    if ( is_string($item) && ($item = trim($item)) ){
      $items = explode('.', str_replace('"', '', $item));
      $r = [];
      foreach ( $items as $m ){
        if ( !bbn\str::check_name($m) ){
          return false;
        }
        array_push($r, '"'.$m.'"');
      }
      return implode('.', $r);
    }
		return false;
	}

  /**
	 * Returns a table's full name i.e. database.table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
	public function table_full_name($table, $escaped=false)
	{
    if ( is_string($table) && ($table = trim($table)) ){
      $mtable = explode('.', str_replace('"', '', $table));
      if ( count($mtable) === 2 ){
        $db = trim($mtable[0]);
        $table = trim($mtable[1]);
      }
      else{
        $db = $this->db->current;
        $table = trim($mtable[0]);
      }
      if ( bbn\str::check_name($db,$table) ){
        if ( $db === 'main' ){
          return $escaped ? '"'.$table.'"' : $table;
        }
        else{
          return $escaped ? '"'.$db.'"."'.$table.'"' : $db.'.'.$table;
        }
      }
    }
		return false;
	}
	
	/**
	 * Returns a table's simple name i.e. table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function table_simple_name($table, $escaped=false)
  {
    if ( is_string($table) && ($table = trim($table)) ){
      $mtable = explode('.', str_replace('"', '', $table));
      $table = end($mtable);
      if ( bbn\str::check_name($table) ){
        return $escaped ? '"'.$table.'"' : $table;
      }
    }
		return false;
  }
  
	/**
	 * Returns a column's full name i.e. table.column
	 * 
	 * @param string $col The column's name (escaped or not)
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function col_full_name($col, $table='', $escaped=false)
  {
    if ( is_string($col) && ($col = trim($col)) ){
      $mcol = explode('.', str_replace('"', '', $col));
      if ( !empty($table) ){
        $table = $this->table_simple_name($table);
        $col = end($mcol);
        $ok = 1;
      }
      else if ( count($mcol) > 1 ){
        $col = array_pop($mcol);
        $table = array_pop($mcol);
        $ok = 1;
      }
      if ( isset($ok) && bbn\str::check_name($table, $col) ){
        return $escaped ? '"'.$table.'"."'.$col.'"' : $table.'.'.$col;
      }
    }
		return false;
  }

	/**
	 * Returns a column's simple name i.e. column
	 * 
	 * @param string $col The column's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function col_simple_name($col, $escaped=false)
  {
    if ( is_string($col) && ($col = trim($col)) ){
      $mcol = explode('.', str_replace('"', '', $col));
      $col = end($mcol);
      if ( bbn\str::check_name($col) ){
        return $escaped ? '"'.$col.'"' : $col;
      }
    }
    return false;
  }
	
	/**
	 * @return array | false
	 */
	public function get_databases()
	{
    $x = [];
    $fs = scandir($this->db->host);
    foreach ( $fs as $f ){
      if ( is_file($this->db->host.$f) ){
        array_push($x, pathinfo($f, PATHINFO_FILENAME));
      }
    }
    sort($x);
    return $x;
	}

	/**
	 * @return array | false
	 */
	public function get_tables($database='')
	{
		if ( empty($database) || !bbn\str::check_name($database) ){
			$database = $this->db->current === 'main' ? '' : '"'.$this->db->current.'".';
		}
    else if ( $database === 'main' ){
      $database = '';
    }
		$t2 = [];
    if ( ( $r = $this->db->raw_query('
      SELECT "tbl_name"
      FROM '.$database.'"sqlite_master"
        WHERE type = \'table\'') ) &&
      $t1 = $r->fetchAll(\PDO::FETCH_NUM) ){
      foreach ( $t1 as $t ){
        if ( strpos($t[0], 'sqlite') !== 0 ){
          array_push($t2, $t[0]);
        }
      }
    }
		return $t2;
	}

	/**
	 * @return array | false
	 */
	public function get_columns($table)
	{
    $r = [];

		if ( ( $table = $this->table_full_name($table) ) ){

      $p = 1;
      if ( $rows = $this->db->get_rows("PRAGMA table_info($table)") ){
        foreach ( $rows as $row ){
          $f = $row['name'];
          $r[$f] = [
            'position' => $p++,
            'null' => $row['notnull'] == 0 ? 1 : 0,
            'key' => $row['pk'] == 1 ? 'PRI' : null,
            'default_value' => $row['dflt_value'],
            'extra' => null,
            'maxlength' => null,
            'signed' => 1
          ];
          $type = strtolower($row['type']);
          if ( strpos($type, 'blob') !== false ){
            $r[$f]['type'] = 'BLOB';
          }
          else if ( ( strpos($type, 'int') !== false ) || ( strpos($type, 'bool') !== false ) || ( strpos($type, 'timestamp') !== false ) ){
            $r[$f]['type'] = 'INTEGER';
          }
          else if ( ( strpos($type, 'floa') !== false ) || ( strpos($type, 'doub') !== false ) || ( strpos($type, 'real') !== false ) ){
            $r[$f]['type'] = 'REAL';
          }
          else if ( ( strpos($type, 'char') !== false ) || ( strpos($type, 'text') !== false ) ){
            $r[$f]['type'] = 'TEXT';
          }
          if ( preg_match_all('/\((.*?)\)/', $row['type'], $matches) ){
            $r[$f]['maxlength'] = $matches[1][0];
          }
          if ( !isset($r[$f]['type']) ){
            $r[$f]['type'] = 'TEXT';
          }
        }
      }
		}
		return $r;
	}
	
	/**
	 * @return string
	 */
	public function get_keys($table)
	{
    $r = [];
		if ( $full = $this->table_full_name($table) ){
      $r = [];
      $keys = [];
      $cols = [];
      $database = $this->db->current === 'main' ? '' : '"'.$this->db->current.'".';
      if ( $indexes = $this->db->get_rows('PRAGMA index_list('.$table.')') ){
        foreach ( $indexes as $d ){
          if ( $fields = $this->db->get_rows('PRAGMA index_info('.$database.'"'.$d['name'].'")') ){
            /** @todo Redo, $a is false! */
            foreach ( $fields as $d2 ){
              $a = false;
              if ( !isset($keys[$d['name']]) ){
                $keys[$d['name']] = [
                  'columns' => [$d2['name']],
                  'ref_db' => $a ? $a['ref_db'] : null,
                  'ref_table' => $a ? $a['ref_table'] : null,
                  'ref_column' => $a ? $a['ref_column'] : null,
                  'unique' => $d['unique'] == 1 ? 1 : 0
                ];
              }
              else{
                array_push($keys[$d['name']]['columns'], $d2['name']);
              }
              if ( !isset($cols[$d2['name']]) ){
                $cols[$d2['name']] = [$d['name']];
              }
              else{
                array_push($cols[$d2['name']], $d['name']);
              }
            }
          }
        }
      }
      $r['keys'] = $keys;
      $r['cols'] = $cols;
		}
    return $r;
	}
	
	/**
	 * @return string
	 */
  public function get_order($order, $table = '', $aliases = []){
    if ( is_string($order) ){
      $order = [$order];
    }
    $r = '';
    if ( is_array($order) && count($order) > 0 ){
      $r .= PHP_EOL . "ORDER BY ";
      if ( !empty($table) ){
        $cfg = $this->db->modelize($table);
      }
      foreach ( $order as $col => $direction ){
        if ( is_numeric($col) ){
          if ( isset($cfg, $cfg['fields'][$direction]) ){
            $dir = stripos($cfg['fields'][$direction]['type'],'date') !== false ? 'DESC' : 'ASC';
          }
          else{
            $dir = 'ASC';
          }
          if ( !isset($cfg) || isset($cfg['fields'][$this->col_simple_name($direction)]) || in_array($this->col_simple_name($direction), $aliases) ){
            $r .= $this->escape($direction)." COLLATE NOCASE $dir," . PHP_EOL;
          }
        }
        else if ( !isset($cfg) || isset($cfg['fields'][$this->col_simple_name($col)]) || in_array($this->col_simple_name($col), $aliases) ){
          $r .= "`$col` COLLATE NOCASE " . ( strtolower($direction) === 'desc' ? 'DESC' : 'ASC' ) . "," . PHP_EOL;
        }
      }
      $r = substr($r,0,strrpos($r,','));
    }
    return $r;
  }
  
	/**
	 * @return string
	 */
  public function get_limit($limit, $start = 0){
    if ( is_array($limit) ){
      $args = $limit;
    }
    else{
      $args = func_get_args();
      if ( is_array($args[0]) ){
        $args = $args[0];
      }
    }
    if ( count($args) === 2 && bbn\str::is_number($args[0], $args[1]) ){
      return " LIMIT $args[1], $args[0]";
    }
    if ( bbn\str::is_number($args[0]) ){
      return " LIMIT $args[0]";
    }
    return '';
  }
  
	/**
	 * @return string | false
	 */
	public function get_create($table)
	{
    if ( $table = $this->table_full_name($table) ){
      if ( strpos($table, '.') ){
        $t = explode('.', $table);
        $database = $t[0];
        $table = $t[1];
      }
      else{
        $database = '';
      }
      return $this->db->get_one('
        SELECT "sql"
        FROM '.$database.'"sqlite_master"
          WHERE type = \'table\'
          AND name = \''.$table.'\'');
		}
		return false;
	}
	
	/**
	 * @return string | false
	 */
	public function get_delete($table, array $where, $ignore = false, $php = false)
	{
		if ( ( $table = $this->table_full_name($table, 1) ) && ( $m = $this->db->modelize($table) ) ){
			$r = '';
			if ( $php ){
				$r .= '$db->query(\'';
			}
			$r .= 'DELETE '.( $ignore ? 'OR IGNORE ' : '' ).
              'FROM '.$table.$this->db->get_where($where, $table);
			if ( $php ){
				$r .= '\');';
			}
			return $r;
		}
		return false;
	}

	/**
	 * @return string
	 */
  public function get_select($table, array $fields = [], array $where = [], $order = [], $limit = false, $start = 0, $php = false){
    // Tables are an array
    if ( !is_array($table) ){
      $table = [$table];
    }
    /** @var array $tables_fields List of all the fields' names indexed by table */
    $tables_fields = [];
    foreach ( $table as $i => $tab ){
      if ( $fn = $this->table_full_name($tab, 1) ){
        $table[$i] = $fn;
        $tables_fields[$table[$i]] = array_keys($this->db->modelize($table[$i])['fields']);
      }
    }
    if ( !empty($tables_fields) ){
      /** @var string $r The SELECT resulting string */
      $r = '';
      if ( $php ){
        $r .= '$db->query("';
      }
      $aliases = [];
      $r .= "SELECT \n";
      // Columns are specified
      if ( count($fields) > 0 ){
        foreach ( $fields as $k => $c ){
          // Here there is no full name
          if ( !strpos($c, '.') ){
            // So we look into the tables to check if there is the field
            $tab = [];
            foreach ( $tables_fields as $t => $f ){
              if ( in_array($c, $f) ){
                array_push($tab, $t);
              }
            }
            // If the same column is passed twice in its short form
            if ( count($tab) === 1 ){
              $c = $this->col_full_name($c, $tab[0]);
            }
            else if ( count($tab) > 1 ){
              $this->db->error('Error! Duplicate field name, you must insert the fields with their fullname.');
            }
            else {
              $this->db->error("Error! The column '$c' doesn't exist in '".implode(", ", array_keys($tables_fields))."' table(s)");
            }
          }
          if ( !is_numeric($k) && bbn\str::check_name($k) && ($k !== $c) ){
            array_push($aliases, $k);
            $r .= "{$this->escape($c)} AS {$this->escape($k)},".PHP_EOL;
          }
          else {
            $r .= $this->escape($c).",".PHP_EOL;
          }
        }
      }
      // All the columns are selected
      else{
        foreach ( $tables_fields as $t => $f ){
          foreach ( $f as $v ){
            $r .= $this->col_full_name($v, $t, 1).",".PHP_EOL;
          }
        }
      }
      $r = substr($r, 0, strrpos($r,',')).PHP_EOL."FROM ".implode(', ', $table).PHP_EOL.
        $this->db->get_where($where, $table, $aliases).PHP_EOL.
        $this->get_order($order, $table, $aliases);
      if ( $limit ){
        $r .= PHP_EOL . $this->get_limit([$limit, $start]);
      }
      if ( $php ){
        $r .= '");';
      }
      return $r;
    }
    return false;
  }
	
	/**
	 * @return string
	 */
	public function get_insert($table, array $fields = [], $ignore = false, $php = false)
	{
    if ( ($table = $this->table_full_name($table, 1)) &&
      ($m = $this->db->modelize($table)) &&
      (count($m['fields']) > 0)
    ){
      $r = '';
      if ( $php ){
        $r .= '$db->query(\'';
      }
			$r .= 'INSERT ';
			if ( $ignore ){
				$r .= 'OR IGNORE ';
			}
			$r .= 'INTO '.$table.' ('.PHP_EOL;
			$i = 0;
			
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
            $this->db->error("Error in Insert query creation: the column $k doesn't exist in $table");
					}
					else{
						$r .= '"'.$k.'", ';
						$i++;
						if ( $i % 4 === 0 ){
							$r .= PHP_EOL;
						}
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
          $r .= '"'.$k.'", ';
					$i++;
					if ( $i % 4 === 0 ){
            $r .= PHP_EOL;
					}
				}
			}
			$r = substr($r,0,strrpos($r,',')).')'.PHP_EOL.'VALUES ('.PHP_EOL;
			$i = 0;
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
          $r .= '?, ';
					$i++;
					if ( $i % 4 === 0 ){
						$r .= PHP_EOL;
					}
				}
			}
			else{
				foreach ( $m['fields'] as $k => $f ){
          $r .= '?, ';
					$i++;
					if ( $i % 4 === 0 ){
						$r .= PHP_EOL;
					}
				}
			}
			$r = substr($r,0,strrpos($r,',')).')';
			if ( $php ){
				$r .= '\''.PHP_EOL;
				$i = 0;
				foreach ( array_keys($m['fields']) as $k ){
					$r .= '$d[\''.$k.'\'], ';
					$i++;
					if ( $i % 4 === 0 ){
						$r .= PHP_EOL;
					}
				}
				$r = substr($r,0,strrpos($r,',')).');';
			}
			return $r;
		}
		return false;
	}
	
	/**
	 * @return string
	 */
	public function get_update($table, array $fields = [], array $where = [], $ignore = false, $php = false)
	{
    if ( ($table = $this->table_full_name($table, 1)) &&
      ($m = $this->db->modelize($table)) &&
      (count($m['fields']) > 0)
    ){
      $r = '';
      if ( $php ){
        $r .= '$db->query(\'';
      }
      $r .= "UPDATE ";
      if ( $ignore ){
        $r .= "OR IGNORE ";
      }
      $r .= "$table SET ";

			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
          if ( !isset($m['fields'][$this->db->csn($k)]) ){
            $this->db->error("Error in Update query creation: the column $k doesn't exist in $table");
          }
          else{
            $r .= $this->col_simple_name($k, 1)." = ?,".PHP_EOL;
          }
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
          $r .= $this->col_simple_name($k, 1)." = ?,".PHP_EOL;
				}
			}

      $where = $this->db->where_cfg($where, $table);
			$r = substr($r,0,strrpos($r,',')).$this->db->get_where($where, $table);

			if ( $php ){
				$r .= '\','.PHP_EOL;
				foreach ( array_keys($m['fields']) as $k ){
					if ( !in_array($k, $where) && ( count($fields) === 0 || in_array($k,$fields) ) ){
						$r .= '$d[\''.$k.'\'],'.PHP_EOL;
					}
				}
				foreach ( $where as $f ){
					$r .= '$d[\''.$f.'\'],'.PHP_EOL;
				}
				$r = substr($r,0,strrpos($r,',')).');';
			}
			return $r;
		}
		return false;
	}
	
	/**
	* Return an array of each values of the field $field in the table $table
	* 
	* @return false|array
	*/
	public function get_column_values($table, $field,  array $where = [],  array $order = [], $limit = false, $start = 0, $php = false)
  {

    $csn = $this->db->csn($field);
    $cfn = $this->db->cfn($field, $table, 1);
    if ( bbn\str::check_name($csn) &&
      ($table = $this->table_full_name($table, 1)) &&
      ($m = $this->db->modelize($table)) &&
      (count($m['fields']) > 0)
    ){
      if ( !isset($m['fields'][$csn]) ){
        $this->db->error("Error in collecting values: the column $field doesn't exist in $table");
      }
      else{
        return ($php ? '$db->query(\'' : '').
        "SELECT DISTINCT $cfn FROM $table".PHP_EOL.
        (empty($where) ? '' : $this->db->get_where($where, $table).PHP_EOL).
        (empty($order) ? '' : $this->db->get_order($order, $table).PHP_EOL).
        (empty($limit) ? '' : $this->db->get_limit([$limit, $start]).PHP_EOL).
        ($php ? '\');' : '');
      }
		}
		return false;
  }
	
	/**
	* Return an array of double values arrays: each value of the field $field in the table $table and the number of instances
	* 
	* @return false|array
	*/
	public function get_values_count($table, $field, array $where = [], $limit, $start, $php = false)
  {
		if ( ( $table = $this->table_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
        $r .= '$db->query(\'';
			}
      if ( !isset($m['fields'][$field]) ){
        $this->db->error("Error in values' count: the column $field doesn't exist in $table");
      }
			$r .= 'SELECT COUNT(*) AS num, "'.$field.'" AS val FROM '.$table;
			if ( count($where) > 0 ){
        $r .= PHP_EOL . $this->db->get_where($where, $table);
      }
      $r .= PHP_EOL . 'GROUP BY "'.$field.'"';
      $r .= PHP_EOL . 'ORDER BY "'.$field.'"';
      if ( $limit ){
  			$r .= PHP_EOL . $this->get_limit([$limit, $start]);
      }
			if ( $php ){
				$r .= '\');';
			}
			return $r;
		}
		return false;
  }
	
	/**
	 * @return void 
	 */
	public function create_db_index($table, $column, $unique = false, $length = null)
	{
    if ( !is_array($column) ){
      $column = [$column];
    }
    if ( !is_null($length) ){
      if ( !is_array($length) ){
        $length = [$length];
      }
    }
    $iname = bbn\str::encode_filename($table);
    foreach ( $column as $i => $c ){
      if ( !bbn\str::check_name($c) ){
        $this->db->error("Illegal column $c");
      }
      $iname .= '_'.$c;
      $column[$i] = "`".$column[$i]."`";
      if ( is_int($length[$i]) && $length[$i] > 0 ){
        $column[$i] .= "(".$length[$i].")";
      }
    }
    $iname = bbn\str::cut($iname, 50);
		if ( ( $table = $this->table_full_name($table, 1) ) ){
			$this->db->raw_query("
			CREATE ".( $unique ? "UNIQUE " : "" )."INDEX `$iname`
      ON $table ( ".implode(", ", $column)." )");
		}
		return $this;
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_index($table, $column)
	{
		if ( ( $table = $this->table_full_name($table, 1) ) && bbn\str::check_name($column) ){
			$this->db->raw_query("
				ALTER TABLE $table
				DROP INDEX `$column`");
		}
		return $this;
	}
	
	/**
	 * @return void 
	 */
	public function create_db_user($user, $pass, $db)
	{
		return 1;
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_user($user)
	{
		return 1;
	}
  
	/**
	 * @return database chainable 
	 */
	public function disable_keys()
	{
		$this->db->raw_query("PRAGMA foreign_keys = OFF;");
		return $this;
	}

	/**
	 * @return database chainable
	 */
	public function enable_keys()
	{
		$this->db->raw_query("PRAGMA foreign_keys = ON;");
		return $this;
	}
  
  public function get_users($user='', $host='')
  {
    return [];
  }

  public function db_size(string $database = '', string $type = ''){
    return @filesize($database) ?: 0;
  }

  public function table_size(string $table, string $type = ''){
    return 0;
  }

  public function status(string $table = '', string $database = ''){
    if ( $database && ($this->db->current !== $database) ){
      $cur = $this->db->current;
      $this->db->change($database);
    }
    $r = $this->db->get_row('SHOW TABLE STATUS WHERE Name LIKE ?', $table);
    if ( isset($cur) ){
      $this->db->change($cur);
    }
    return $r;
  }
}