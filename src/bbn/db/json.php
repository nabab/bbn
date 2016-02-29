<?php
/**
 * @package bbn\db
 */
namespace bbn\db\languages;

use \bbn\str;
/**
 * Database Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class json implements \bbn\db\api
{
  private $db;
	public static
          $operators=['!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between', 'not like'],
          $numeric_types=['integer', 'int', 'smallint', 'tinyint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double'];

  public $qte = '`';
  /**
   * 
   */
  public function __construct($file) {
    $this->data = $db;
  }


  /**
	 * @return void 
	 */
  public function get_connection($cfg=[])
  {
    $cfg['engine'] = 'json';
    if ( !isset($cfg['host']) ){
      $cfg['host'] = defined('BBN_DB_HOST') ? BBN_DB_HOST : '127.0.0.1';
    }
    if ( !isset($cfg['user']) && defined('BBN_DB_USER') ){
      $cfg['user'] = defined('BBN_DB_USER') ? BBN_DB_USER : 'root';
    }
    if ( !isset($cfg['pass']) ){
      $cfg['pass'] = defined('BBN_DB_PASS') ? BBN_DB_PASS : '';
    }
    if ( !isset($cfg['db']) && defined('BBN_DATABASE') ){
      $cfg['db'] = BBN_DATABASE;
    }
		if ( isset($cfg['db']) )
		{
      $cfg['args'] = [
        'mysql:host='.
          ( $cfg['host'] === 'localhost' ? '127.0.0.1' : $cfg['host'] ).
          ';dbname='.$cfg['db'],
        $cfg['user'],
        $cfg['pass'],
        [
          \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
          \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]
      ];
      return $cfg;
    }
    return false;
	}
	
	/**
	 * @return string | false
	 */
	public function change($db)
	{
		if ( ($this->db->current !== $db) && str::check_name($db) ){
			$this->db->raw_query("USE `$db`");
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
      $items = explode(".", str_replace("`", "", $item));
      $r = [];
      foreach ( $items as $m ){
        if ( !str::check_name($m) ){
          return false;
        }
        array_push($r, "`".$m."`");
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
      $mtable = explode(".", str_replace("`", "", $table));
      if ( count($mtable) === 3 ){
        $db = trim($mtable[0]);
        $table = trim($mtable[1]);
      }
      else if ( count($mtable) === 2 ){
        $db = trim($mtable[0]);
        $table = trim($mtable[1]);
      }
      else{
        $db = $this->db->current;
        $table = trim($mtable[0]);
      }
      if ( str::check_name($db,$table) ){
        return $escaped ? "`".$db."`.`".$table."`" : $db.".".$table;
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
      $mtable = explode(".", str_replace("`", "", $table));
      switch ( count($mtable) ){
        case 1:
          $table = $mtable[0];
          break;
        case 2:
          $table = $mtable[1];
          break;
        case 3:
          $table = $mtable[1];
          break;
      }
      if ( str::check_name($table) ){
        return $escaped ? "`".$table."`" : $table;
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
      $mcol = explode(".", str_replace("`", "", $col));
      if ( count($mcol) > 1 ){
        $col = array_pop($mcol);
        $table = array_pop($mcol);
        $ok = 1;
      }
      else if ( !empty($table) ){
        $table = $this->table_simple_name($table);
        $col = end($mcol);
        $ok = 1;
      }
      if ( isset($ok) && str::check_name($table, $col) ){
        return $escaped ? "`".$table."`.`".$col."`" : $table.".".$col;
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
      $mcol = explode(".", str_replace("`", "", $col));
      $col = end($mcol);
      if ( str::check_name($col) ){
        return $escaped ? "`".$col."`" : $col;
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
    if ( $r = $this->db->raw_query("SHOW DATABASES") ){
      $x = array_map( function($a){
        return $a['Database'];
      }, array_filter($r->fetchAll(\PDO::FETCH_ASSOC),function($a){
        return ( $a['Database'] === 'information_schema' ) || ( $a['Database'] === 'mysql' ) ? false : 1;
      }));
      sort($x);
      return $x;
    }
	}

	/**
	 * @return array | false
	 */
	public function get_tables($database='')
	{
		if ( empty($database) || !str::check_name($database) ){
			$database = $this->db->current;
		}
		$t2 = [];
    if ( $r = $this->db->raw_query("SHOW TABLES FROM `$database`") ){
      if ( $t1 = $r->fetchAll(\PDO::FETCH_NUM) ){
        foreach ( $t1 as $t ){
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
		if ( $table = $this->table_full_name($table, 1) ){
			if ( $rows = $this->db->get_rows("SHOW COLUMNS FROM $table") ){
        $p = 1;
        foreach ( $rows as $row ){
          $f = $row['Field'];
          $r[$f] = [
            'position' => $p++,
            'null' => $row['Null'] === 'NO' ? 0 : 1,
            'key' => in_array($row['Key'], ['PRI', 'UNI', 'MUL']) ? $row['Key'] : null,
            'default' => is_null($row['Default']) && $row['Null'] !== 'NO' ? 'NULL' : $row['Default'],
            'extra' => $row['Extra'],
            'signed' => 0,
            'maxlength' => 0
          ];
          if ( strpos($row['Type'], 'enum') === 0 ){
            $r[$f]['type'] = 'enum';
            if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
              $r[$f]['extra'] = $matches[1][0];
            }
          }
          else{
            preg_match_all('/(.*?)\(/', $row['Type'], $real_type);
            if ( isset($real_type[1][0]) &&
                    in_array($real_type[1][0], self::$numeric_types) ){
              if ( strpos($row['Type'], 'unsigned') ){
                $row['Type'] = trim(str_replace('unsigned','',$row['Type']));
              }
              else{
                $r[$f]['signed'] = 1;
              }
            }
            if ( strpos($row['Type'],'text') !== false ){
              $r[$f]['type'] = 'text';
            }
            else if ( strpos($row['Type'],'blob') !== false ){
              $r[$f]['type'] = 'blob';
            }
            else if ( strpos($row['Type'],'int(') !== false ){
              $r[$f]['type'] = 'int';
            }
            else if ( strpos($row['Type'],'char(') !== false ){
              $r[$f]['type'] = 'varchar';
            }
            if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
              $r[$f]['maxlength'] = $matches[1][0];
            }
            if ( !isset($r[$f]['type']) ){
              $r[$f]['type'] = ( strpos($row['Type'], '(') ) ? substr($row['Type'],0,strpos($row['Type'], '(')) : $row['Type'];
            }

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
			$t = explode(".", $full);
			$db = $t[0];
			$table = $t[1];
      $r = [];
      $b = $this->db->get_rows("SHOW INDEX FROM ".$this->table_full_name($full, 1));
      $keys = [];
      $cols = [];
      foreach ( $b as $i => $d ){
        $a = $this->db->get_row("
          SELECT `ORDINAL_POSITION` as `position`,
          `REFERENCED_TABLE_SCHEMA` as `ref_db`, `REFERENCED_TABLE_NAME` as `ref_table`, `REFERENCED_COLUMN_NAME` as `ref_column`
          FROM `information_schema`.`KEY_COLUMN_USAGE`
          WHERE `TABLE_SCHEMA` LIKE ?
          AND `TABLE_NAME` LIKE ?
          AND `COLUMN_NAME` LIKE ?
          AND ( `CONSTRAINT_NAME` LIKE ? OR 
            ( `REFERENCED_TABLE_NAME` IS NOT NULL OR ORDINAL_POSITION = ? )
          )
          ORDER BY `REFERENCED_TABLE_NAME` DESC
          LIMIT 1",
          $db,
          $table,
          $d['Column_name'],
          $d['Key_name'],
          $d['Seq_in_index']);
        if ( !isset($keys[$d['Key_name']]) ){
          $keys[$d['Key_name']] = [
            'columns' => [$d['Column_name']],
            'ref_db' => $a && $a['ref_db'] ? $a['ref_db'] : null,
            'ref_table' => $a && $a['ref_table'] ? $a['ref_table'] : null,
            'ref_column' => $a && $a['ref_column'] ? $a['ref_column'] : null,
            'unique' => $d['Non_unique'] == 0 ? 1 : 0
          ];
        }
        else{
          array_push($keys[$d['Key_name']]['columns'], $d['Column_name']);
          $keys[$d['Key_name']]['ref_db'] = $keys[$d['Key_name']]['ref_table'] = $keys[$d['Key_name']]['ref_column'] = null;
        }
        if ( !isset($cols[$d['Column_name']]) ){
          $cols[$d['Column_name']] = [$d['Key_name']];
        }
        else{
          array_push($cols[$d['Column_name']], $d['Key_name']);
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
            $r .= $this->escape($direction)." $dir," . PHP_EOL;
          }
        }
        else if ( !isset($cfg) || isset($cfg['fields'][$this->col_simple_name($col)]) || in_array($this->col_simple_name($col), $aliases) ){
          $r .= "`$col` " . ( strtolower($direction) === 'desc' ? 'DESC' : 'ASC' ) . "," . PHP_EOL;
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
    if ( count($args) === 2 &&
            \bbn\str::is_number($args[0], $args[1]) &&
            ($args[0] > 0) ){
      return " LIMIT $args[1], $args[0]";
    }
    if ( \bbn\str::is_number($args[0]) &&
            ($args[0] > 0) ){
      return " LIMIT $args[0]";
    }
    return ' ';
  }

    /**
	 * @return string | false
	 */
	public function get_create($table)
	{
		if ( ( $table = $this->table_full_name($table, 1) ) && $r = $this->db->raw_query("SHOW CREATE TABLE $table") ){
			return $r->fetch(\PDO::FETCH_ASSOC)['Create Table'];
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
				$r .= '$db->query("';
			}
			$r .= "DELETE ".( $ignore ? "IGNORE " : "" ).
              "FROM $table ".$this->db->get_where($where, $table);
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
	public function get_select($table, array $fields = [], array $where = [], $order = [], $limit = false, $start = 0, $php = false)
	{
		if ( ( $table = $this->table_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
				$r .= '$db->query("';
			}
      $aliases = [];
			$r .= "SELECT \n";
			if ( count($fields) > 0 ){
				foreach ( $fields as $k => $c ){
					if ( !isset($m['fields'][$c]) ){
						die("The column $c doesn't exist in $table");
					}
					else{
            if ( !is_numeric($k) && \bbn\str::check_name($k) && ($k !== $c) ){
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
					$r .= "`$c`,\n";
				}
			}
			$r = substr($r,0,strrpos($r,','))."\nFROM $table";
			if ( count($where) > 0 ){
        $r .= $this->db->get_where($where, $table, $aliases);
      }
      $r .= PHP_EOL . $this->get_order($order, $table, $aliases);
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
		$r = '';
		if ( $php ){
			$r .= '$db->query("';
		}
		if ( ( $table = $this->table_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 ){
			$r .= "INSERT ";
			if ( $ignore ){
				$r .= "IGNORE ";
			}
			$r .= "INTO $table (\n";
			$i = 0;
			
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k`, ";
						$i++;
						if ( $i % 4 === 0 ){
							$r .= "\n";
						}
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k`, ";
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			$r = substr($r,0,strrpos($r,',')).")\nVALUES (\n";
			$i = 0;
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( stripos($m['fields'][$k]['type'],'INT') !== false ){
						$r .= "%u, ";
					}
					else{
						$r .= "%s, ";
					}
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			else{
				foreach ( $m['fields'] as $k => $f ){
					if ( stripos($f['type'],'INT') !== false ){
						$r .= "%u, ";
					}
					else{
						$r .= "%s, ";
					}
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			$r = substr($r,0,strrpos($r,',')).')';
			if ( $php ){
				$r .= "\",\n";
				$i = 0;
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "\$d['$k'], ";
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
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
	public function get_update($table, array $fields = [], array $where = [], $php = false)
	{
		if ( ( $table = $this->table_full_name($table, 1) ) && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 ){
      $r = '';
      if ( $php ){
        $r .= '$db->query("';
      }
			$r .= "UPDATE $table SET ";
			$i = 0;

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
				$r .= "\",\n";
				$i = 0;
				foreach ( array_keys($m['fields']) as $k ){
					if ( !in_array($k, $where) && ( count($fields) === 0 || in_array($k,$fields) ) ){
						$r .= "\$d['$k'],\n";
					}
				}
				foreach ( $where as $f ){
					$r .= "\$d['$f[0]'],\n";
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
	public function get_column_values($table, $field,  array $where = [], $limit = false, $start = 0, $php = false)
  {
		if ( str::check_name($field) && ( $table = $this->table_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
				$r .= '$db->query("';
			}
      if ( !isset($m['fields'][$field]) ){
        die("The column $field doesn't exist in $table");
      }
			$r .= "SELECT DISTINCT `$field` FROM $table".PHP_EOL.
        $this->db->get_where($where, $table).PHP_EOL.
        "ORDER BY `$field`";
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
				$r .= '$db->query("';
			}
      if ( !isset($m['fields'][$field]) ){
        die("The column $field doesn't exist in $table");
      }
			$r .= "SELECT COUNT(*) AS num, `$field` AS val FROM $table";
			if ( count($where) > 0 ){
        $r .= PHP_EOL . $this->db->get_where($where, $table);
      }
      $r .= PHP_EOL . "GROUP BY `$field`";
      $r .= PHP_EOL . "ORDER BY `$field`";
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
    $iname = str::encode_filename($table);
    foreach ( $column as $i => $c ){
      if ( !str::check_name($c) ){
        die("Illegal column $c");
      }
      $iname .= '_'.$c;
      $column[$i] = "`".$column[$i]."`";
      if ( is_int($length[$i]) && $length[$i] > 0 ){
        $column[$i] .= "(".$length[$i].")";
      }
    }
    $iname = str::cut($iname, 50);
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
		if ( ( $table = $this->table_full_name($table, 1) ) && str::check_name($column) ){
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
		if ( str::check_name($user, $db) && strpos($pass, "'") === false ){
			$this->db->raw_query("
				GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER
				ON `$db` . *
				TO '$user'@'$host'
				IDENTIFIED BY '$pass'");
		}
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_user($user)
	{
		if ( str::check_name($user) ){
			$this->db->raw_query("
			REVOKE ALL PRIVILEGES ON *.* 
			FROM $user");
			$this->query("DROP USER $user");
		}
		return $this;
	}
  
	/**
	 * @return database chainable 
	 */
	public function disable_keys()
	{
		$this->db->raw_query("SET FOREIGN_KEY_CHECKS=0;");
		return $this;
	}

	/**
	 * @return database chainable
	 */
	public function enable_keys()
	{
		$this->db->raw_query("SET FOREIGN_KEY_CHECKS=1;");
		return $this;
	}
  
  public function get_users($user='', $host='')
  {
    $cond = '';
    if ( !empty($user) && \bbn\str::check_name($user) ){
      $cond .= " AND  user LIKE '$user' ";
    }
    if ( !empty($host) && \bbn\str::check_name($host) ){
      $cond .= " AND  host LIKE '$host' ";
    }
    $us = $this->db->get_rows("
      SELECT DISTINCT host, user
      FROM mysql.user
      WHERE 1
      $cond");
    $q = [];
    foreach ( $us as $u ){
      $gs = $this->db->get_col_array("SHOW GRANTS FOR '$u[user]'@'$u[host]'");
      foreach ( $gs as $g ){
        array_push($q, $g);
      }
    }
    return $q;
  }
}
?>