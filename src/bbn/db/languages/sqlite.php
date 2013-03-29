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
	protected static $operators=array('!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between');
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
      if ( defined("BBN_DATA_PATH") && is_dir(BBN_DATA_PATH.'db') && strpos($cfg['db'], "/") === false ){
        $cfg['host'] = BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR;
        if ( !is_file(BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR.$cfg['db']) && strpos($cfg['db'], ".") === false ){
          $cfg['db'] .= '.sqlite';
        }
      }
      else {
        $pathinfo = pathinfo($cfg['db']);
        if ( is_file($cfg['db']) ){
          $cfg['host'] = $pathinfo['dirname'].DIRECTORY_SEPARATOR;
          $cfg['db'] = $pathinfo['basename'];
        }
        else if ( is_writable($pathinfo['dirname']) ){
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
	 * @return string | false
	 */
	public function get_full_name($table, $escaped=false)
	{
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
    
		if ( ( $table = $this->get_full_name($table, 1) ) ){

      $p = 1;
      foreach ( $this->db->raw_query('PRAGMA table_info('.$table.')') as $row ){
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
		return $r;
	}
	
	/**
	 * @return string
	 */
	public function get_keys($table)
	{
		if ( $full = $this->get_full_name($table, 1) ){
      $keys = array();
      $cols = array();
      $database = $this->db->current === 'main' ? '' : '"'.$this->db->current.'".';
      foreach ( $this->db->raw_query('PRAGMA index_list('.$table.')') as $d ){
        foreach ( $this->db->raw_query('PRAGMA index_info('.$database.'"'.$d['name'].'")') as $d2 ){
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
      return array('keys'=>$keys, 'cols'=>$cols);
		}
	}
	
	/**
	 * @return string
	 */
  public function get_where(array $where, $table='')
  {
    $st = '';
    foreach ( $where as $key => $w ){
      if ( is_numeric($key) && is_array($w) && isset($w[0], $w[1]) ){
        // 2 parameters, we use equal
        if ( count($w) === 2 ){
          $st .= 'AND "'.$w[0].'" = ? ';
        }
        else if ( count($w) >= 3 && in_array (strtolower($w[1]), self::$operators) ){
          // 4 parameters, it's a SQL function, no escaping no binding
          if ( isset($w[3]) ){
            $st .= 'AND "'.$w[0].'" '.$w[1].' '.$w[2].' ';
          }
          // 3 parameters, the operator is second item
          else{
            $st .= 'AND "'.$w[0].'" '.$w[1].' ? ';
          }
        }
      }
      else if (is_string($key) ){
        $st .= 'AND "'.$w[0].'" = ? ';
      }
      $st .= PHP_EOL;
    }
    if ( !empty($st) ){
      return ' WHERE 1'.PHP_EOL.$st;
    }
    return '';
  }
  
	/**
	 * @return string
	 */
  public function get_order($order, $table = '') {
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
        if ( is_numeric($col) && ( !isset($cfg) || isset($cfg['fields'][$direction]) ) ){
          $r .= '"' . $direction . '" ' . ( stripos($m['fields'][$direction]['type'],'date') !== false ? 'DESC' : 'ASC' ) . "," . PHP_EOL;
        }
        else if ( !isset($cfg) || isset($cfg['fields'][$col])  ){
          $r .= '"' . $col . '" ' . ( strtolower($direction) === 'desc' ? 'DESC' : 'ASC' ) . "," . PHP_EOL;
        }
      }
      $r = substr($r,0,strrpos($r,','));
    }
    return $r;
  }
  
	/**
	 * @return string
	 */
  public function get_limit($limit) {
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
    if ( $table = $this->get_full_name($table) ){
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
	public function get_delete($table, array $where, $php = false)
	{
		if ( ( $table = $this->get_full_name($table, 1) ) && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 && count($where) > 0 ){
			$r = '';
			if ( $php ){
				$r .= '$db->query(\'';
			}
			$r .= 'DELETE FROM '.$table.' WHERE 1 ';
			foreach ( $where as $f ){
				if ( !isset($m['fields'][$f]) ){
					die("The fields to search for in get_delete don't correspond to the table");
				}
				$r .= PHP_EOL.'AND "'.$f.'" = ? ';
			}
			if ( $php ){
				$r .= '\')';
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
		if ( ( $table = $this->get_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
				$r .= '$db->query(\'';
			}
			$r .= 'SELECT ';
			if ( count($fields) > 0 ){
				foreach ( $fields as $k => $c ){
					if ( !isset($m['fields'][$c]) ){
						die("The column $c doesn't exist in $table");
					}
					else{
            if ( !is_numeric($k) && \bbn\str\text::check_name($k) ){
              $r .= '"'.$c.'" AS '.$k.','.PHP_EOL;
            }
            else{
              $r .= '"$c",'.PHP_EOL;
            }
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $c ){
          $r .= '"'.$c.'",'.PHP_EOL;
				}
			}
			$r = substr($r,0,strrpos($r,',')).PHP_EOL.'FROM '.$table;
			if ( count($where) > 0 ){
				$r .= $this->get_where($where, $table);
			}
      $r .= $this->get_order($order, $table);
			$directions = ['desc', 'asc'];
      
			if ( is_array($order) && count($order) > 0 ){
				$r .= PHP_EOL.'ORDER BY ';
				foreach ( $order as $col => $direction ){
					if ( is_numeric($col) && isset($m['fields'][$direction]) ){
						$r .= '"'.$direction.'"'.
                    ( stripos($m['fields'][$direction]['type'],'date') !== false ? 'DESC' : 'ASC' ).
                    ','.PHP_EOL;
					}
					else if ( isset($m['fields'][$col])  ){
						$r .= '"'.$col.'" '.
                    ( strtolower($direction) === 'desc' ? 'DESC' : 'ASC' ).
                    ','.PHP_EOL;
					}
				}
				$r = substr($r,0,strrpos($r,','));
			}
			if ( $limit && is_numeric($limit) && is_numeric($start) ){
				$r .= PHP_EOL.'LIMIT '.$start.', '.$limit;
			}
			if ( $php ){
				$r .= '\')';
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
		if ( ( $table = $this->get_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 ){
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
	public function get_update($table, array $fields = array(), array $where = array(), $php = false)
	{
		$r = '';
		if ( $php ){
			$r .= '$db->query(\'';
		}
		if ( ( $table = $this->get_full_name($table, 1) ) && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 ){
			if ( is_string($where) ){
				$where = array($where);
			}
			$r .= 'UPDATE '.$table.' SET ';
			$i = 0;
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= '"'.$k.'" = ?,'.PHP_EOL;
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= '"'.$k.'" = ?,'.PHP_EOL;
				}
			}

			$r = substr($r,0,strrpos($r,',')).PHP_EOL.'WHERE 1 ';
			foreach ( $where as $f ){
				if ( !isset($m['fields'][$f]) ){
					die("The fields to search for in get_update don't correspond to the table");
				}
				$r .= PHP_EOL.'AND "'.$f.'" = ?';
			}

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
		if ( ( $table = $this->get_full_name($table, 1) ) ){
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
		if ( ( $table = $this->get_full_name($table, 1) ) && text::check_name($column) ){
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
}
?>