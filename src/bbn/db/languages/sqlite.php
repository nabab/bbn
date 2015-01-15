<?php
/**
 * @package bbn\db
 */
namespace bbn\db\languages;

use \bbn\str\text;
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
class sqlite implements \bbn\db\engines
{
  private $db;
	public static $operators=array('!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between', 'not like');
  public $qte = '"';
  /**
   * 
   */
  public function __construct(\bbn\db\connection $db = null) {
    if ( !extension_loaded('pdo_sqlite') ){
      die("The SQLite driver for PDO is not installed...");
    }
    $this->db = $db;
  }


  /**
	 * @return void 
	 */
  public function get_connection($cfg=array())
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
        if ( !text::check_name($m) ){
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
      if ( text::check_name($db,$table) ){
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
      if ( text::check_name($table) ){
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
      if ( isset($ok) && text::check_name($table, $col) ){
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
      if ( text::check_name($col) ){
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
		if ( empty($database) || !text::check_name($database) ){
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
    
		if ( ( $table = $this->table_full_name($table, 1) ) ){

      $p = 1;
      if ( $rows = $this->db->get_rows('PRAGMA table_info('.$table.')') ){
        foreach ( $rows as $row ){
          $f = $row['name'];
          $r[$f] = array(
            'position' => $p++,
            'null' => $row['notnull'] == 0 ? 1 : 0,
            'key' => $row['pk'] == 1 ? 'PRI' : null,
            'default' => $row['dflt_value'],
            'extra' => null,
            'maxlength' => null,
            'signed' => 1
          );
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
            foreach ( $fields as $d2 ){
              $a = false;
              if ( !isset($keys[$d['name']]) ){
                $keys[$d['name']] = array(
                'columns' => array($d2['name']),
                'ref_db' => $a ? $a['ref_db'] : null,
                'ref_table' => $a ? $a['ref_table'] : null,
                'ref_column' => $a ? $a['ref_column'] : null,
                'unique' => $d['unique'] == 1 ? 1 : 0
                );
              }
              else{
                array_push($keys[$d['name']]['columns'], $d2['name']);
              }
              if ( !isset($cols[$d2['name']]) ){
                $cols[$d2['name']] = array($d['name']);
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
  public function get_order($order, $table = '', $aliases = []) {
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
  public function get_limit($limit, $start = 0) {
    if ( is_array($limit) ){
      $args = $limit;
    }
    else{
      $args = func_get_args();
      if ( is_array($args[0]) ){
        $args = $args[0];
      }
    }
    if ( count($args) === 2 && \bbn\str\text::is_number($args[0], $args[1]) ){
      return " LIMIT $args[1], $args[0]";
    }
    if ( \bbn\str\text::is_number($args[0]) ){
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
		if ( ( $table = $this->table_full_name($table, 1) ) && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 && count($where) > 0 ){
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
	public function get_select($table, array $fields = array(), array $where = array(), $order = array(), $limit = false, $start = 0, $php = false)
	{
		if ( ( $table = $this->table_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
				$r .= '$db->query(\'';
			}
      $aliases = [];
			$r .= 'SELECT '.PHP_EOL;
			if ( count($fields) > 0 ){
				foreach ( $fields as $k => $c ){
					if ( !isset($m['fields'][$c]) ){
						die("The column $c doesn't exist in $table");
					}
					else{
            if ( !is_numeric($k) && \bbn\str\text::check_name($k) && ($k !== $c) ){
              array_push($aliases, $k);
              $r .= "{$this->escape($c)} AS {$this->escape($k)},".PHP_EOL;
            }
            else{
              $r .= $this->escape($c).",".PHP_EOL;
            }
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $c ){
					$r .= "`$c`,".PHP_EOL;
				}
			}
			$r = substr($r,0,strrpos($r,',')).PHP_EOL."FROM $table";
			if ( count($where) > 0 ){
        $r .= $this->db->get_where($where, $table, $aliases);
      }
      $r .= PHP_EOL . $this->get_order($order, $table, $aliases);
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
	 * @return string
	 */
	public function get_insert($table, array $fields = array(), $ignore = false, $php = false)
	{
		$r = '';
		if ( $php ){
			$r .= '$db->query(\'';
		}
		if ( ( $table = $this->table_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 ){
			$r .= 'INSERT ';
			if ( $ignore ){
				$r .= 'OR IGNORE ';
			}
			$r .= 'INTO '.$table.' ('.PHP_EOL;
			$i = 0;
			
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
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
	public function get_update($table, array $fields = array(), array $where = array(), $ignore = false, $php = false)
	{
		if ( ( $table = $this->table_full_name($table, 1) ) && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
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
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k` = ?,".PHP_EOL;
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k` = ?,".PHP_EOL;
				}
			}

			$r = substr($r,0,strrpos($r,',')).$this->db->get_where($where, $table);

			if ( $php ){
				$r .= '\','.PHP_EOL;
				$i = 0;
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
	public function get_column_values($table, $field,  array $where = array(), $limit = false, $start = 0, $php = false)
  {
		if ( text::check_name($field) && ( $table = $this->table_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
  			$r .= '$db->query(\'';
			}
      if ( !isset($m['fields'][$field]) ){
        die("The column $field doesn't exist in $table");
      }
			$r .= 'SELECT DISTINCT "'.$field.'" FROM '.$table;
			if ( count($where) > 0 ){
        $r .= PHP_EOL . $this->db->get_where($where, $table);
      }
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
	* Return an array of double values arrays: each value of the field $field in the table $table and the number of instances
	* 
	* @return false|array
	*/
	public function get_values_count($table, $field, array $where = array(), $limit, $start, $php = false)
  {
		if ( ( $table = $this->table_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
        $r .= '$db->query(\'';
			}
      if ( !isset($m['fields'][$field]) ){
        die("The column $field doesn't exist in $table");
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
    $iname = text::encode_filename($table);
    foreach ( $column as $i => $c ){
      if ( !text::check_name($c) ){
        die("Illegal column $c");
      }
      $iname .= '_'.$c;
      $column[$i] = "`".$column[$i]."`";
      if ( is_int($length[$i]) && $length[$i] > 0 ){
        $column[$i] .= "(".$length[$i].")";
      }
    }
    $iname = text::cut($iname, 50);
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
		if ( ( $table = $this->table_full_name($table, 1) ) && text::check_name($column) ){
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
}
?>