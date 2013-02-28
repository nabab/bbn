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
 * @version 0.2r89
 */
class mysql implements \bbn\db\engines
{
  /**
   * 
   */
  public function __construct(\bbn\db\connection $db = null) {
    if ( !extension_loaded('pdo_mysql') ){
      die("The SQLite driver for PDO is not installed...");
    }
    $this->db = $db;
  }


  /**
	 * @return void 
	 */
  public function get_connection($cfg=array())
  {
    $cfg['engine'] = 'mysql';
    if ( !isset($cfg['host']) ){
      $cfg['host'] = defined('BBN_DB_HOST') ? BBN_DB_HOST : 'localhost';
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
        'mysql:host='.$cfg['host'].';dbname='.$cfg['db'],
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
		if ( ($this->db->current !== $db) && text::check_name($db) ){
			$this->db->raw_query("USE `$db`");
      return 1;
		}
		return false;
	}
	
	/**
	 * @return string | false
	 */
	public function get_full_name($table, $escaped=false)
	{
		$mtable = explode(".", str_replace("`", "", $table));
		if ( count($mtable) === 2 ){
			$db = trim($mtable[0]);
			$table = trim($mtable[1]);
		}
		else{
			$db = $this->db->current;
			$table = trim($mtable[0]);
		}
		if ( text::check_name($db,$table) ){
			return $escaped ? "`".$db."`.`".$table."`" : $db.".".$table;
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
		if ( empty($database) || !text::check_name($database) ){
			$database = $this->db->current;
		}
		$t2 = array();
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
		if ( $table = $this->get_full_name($table, 1) ){
			if ( $rows = $this->db->get_rows("SHOW COLUMNS FROM $table") ){
        $p = 1;
        foreach ( $rows as $row ){
          $f = $row['Field'];
          $r[$f] = array(
            'position' => $p++,
            'null' => $row['Null'] === 'NO' ? 0 : 1,
            'key' => in_array($row['Key'], array('PRI', 'UNI', 'MUL')) ? $row['Key'] : null,
            'default' => is_null($row['Default']) && $row['Null'] !== 'NO' ? 'NULL' : $row['Default'],
            'extra' => $row['Extra'],
            'maxlength' => 0
          );
          if ( strpos($row['Type'], 'enum') === 0 ){
            $r[$f]['type'] = 'enum';
            if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
              $r[$f]['extra'] = $matches[1][0];
            }
          }
          else{
            if ( strpos($row['Type'], 'unsigned') ){
              $r[$f]['signed'] = 0;
              $row['Type'] = trim(str_replace('unsigned','',$row['Type']));
            }
            else{
              $r[$f]['signed'] = 1;
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
		//var_dump("I get the keys");
		if ( $full = $this->get_full_name($table, 1) ){
			$t = explode(".", $table);
			$db = $t[0];
			$table = $t[1];
      if ( $r = $this->db->query("SHOW INDEX FROM `$db`.`$table`") ){
        $b = $r->get_rows();
        $keys = array();
        $cols = array();
        foreach ( $b as $i => $d ){
          $a = $this->db->get_row("
          SELECT `ORDINAL_POSITION` as `position`,
          `REFERENCED_TABLE_SCHEMA` as `ref_db`, `REFERENCED_TABLE_NAME` as `ref_table`, `REFERENCED_COLUMN_NAME` as `ref_column`
          FROM `information_schema`.`KEY_COLUMN_USAGE`
          WHERE `TABLE_SCHEMA` LIKE ?
          AND `TABLE_NAME` LIKE ?
          AND `COLUMN_NAME` LIKE ?
          AND ( `CONSTRAINT_NAME` LIKE ? OR ORDINAL_POSITION = ? OR 1 )
          LIMIT 1",
          $db,
          $table,
          $d['Column_name'],
          $d['Key_name'],
          $d['Seq_in_index']);
          if ( !isset($keys[$d['Key_name']]) ){
            $keys[$d['Key_name']] = array(
            'columns' => array($d['Column_name']),
            'ref_db' => $a ? $a['ref_db'] : null,
            'ref_table' => $a ? $a['ref_table'] : null,
            'ref_column' => $a ? $a['ref_column'] : null,
            'unique' => $d['Non_unique'] == 0 ? 1 : 0
            );
          }
          else{
            array_push($keys[$d['Key_name']]['columns'], $d['Column_name']);
          }
          if ( !isset($cols[$d['Column_name']]) ){
            $cols[$d['Column_name']] = array($d['Key_name']);
          }
          else{
            array_push($cols[$d['Column_name']], $d['Key_name']);
          }
        }
        return array('keys'=>$keys, 'cols'=>$cols);
      }
		}
	}
	
	/**
	 * @return string | false
	 */
	public function get_create($table)
	{
		if ( ( $table = $this->get_full_name($table, 1) ) && $r = $this->db->raw_query("SHOW CREATE TABLE $table") ){
			return $r->fetch(\PDO::FETCH_ASSOC)['Create Table'];
		}
		return false;
	}
	
	/**
	 * @return string | false
	 */
	public function get_delete($table, array $where)
	{
		if ( ( $table = $this->get_full_name($table, 1) ) && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 && count($where) > 0 ){
			$r = "DELETE FROM $table WHERE 1 ";

			foreach ( $where as $f ){
				if ( !isset($m['fields'][$f]) ){
					die("The fields to search for in get_delete don't correspond to the table");
				}
				$r .= "\nAND `$f` ";
				if ( stripos($m['fields'][$f]['type'],'int') !== false ){
					$r .= "= %u ";
				}
				else{
					$r .= "= %s ";
				}
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
				$r .= '$db->query("';
			}
			$r .= "SELECT \n";
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k`,\n";
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k`,\n";
				}
			}
			$r = substr($r,0,strrpos($r,','))."\nFROM $table";
			if ( count($where) > 0 ){
				$r .= "\nWHERE 1 ";
				foreach ( $where as $f ){
					if ( !isset($m['fields'][$f]) ){
						die("The field $f to search for in get_select don't correspond to the table");
					}
					$r .= "\nAND `$f` ";
					if ( stripos($m['fields'][$f]['type'],'int') !== false ){
						$r .= "= %u";
					}
					else{
						$r .= "= %s";
					}
				}
			}
			$directions = ['desc', 'asc'];
			if ( is_string($order) ){
				$order = [$order];
			}
			if ( is_array($order) && count($order) > 0 ){
				$r .= "\nORDER BY ";
				foreach ( $order as $col => $direction ){
					if ( is_numeric($col) && isset($m['fields'][$direction]) ){
						$r .= "`$direction` ".( stripos($m['fields'][$direction]['type'],'date') !== false ? 'DESC' : 'ASC' ).",\n";
					}
					else if ( isset($m['fields'][$col])  ){
						$r .= "`$col` ".( strtolower($direction) === 'desc' ? 'DESC' : 'ASC' ).",\n";
					}
				}
				$r = substr($r,0,strrpos($r,','));
			}
			if ( $limit && is_numeric($limit) && is_numeric($start) ){
				$r .= "\nLIMIT $start, $limit";
			}
			if ( $php ){
				$r .= '")';
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
			$r .= '$db->query("';
		}
		if ( ( $table = $this->get_full_name($table, 1) )  && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
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
	public function get_update($table, array $fields = array(), array $where = array(), $php = false)
	{
		$r = '';
		if ( $php ){
			$r .= '$db->query("';
		}
		if ( ( $table = $this->get_full_name($table, 1) ) && ( $m = $this->db->modelize($table) ) && count($m['fields']) > 0 )
		{
			if ( is_string($where) ){
				$where = array($where);
			}
			$r .= "UPDATE $table SET ";
			$i = 0;

			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k` = ";
						if ( stripos($m['fields'][$k]['type'],'int') !== false ){
							$r .= "%u";
						}
						else{
							$r .= "%s";
						}
						$r .= ",\n";
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k` = ";
					if ( stripos($m['fields'][$k]['type'],'int') !== false ){
						$r .= "%u";
					}
					else{
						$r .= "%s";
					}
					$r .= ",\n";
				}
			}

			$r = substr($r,0,strrpos($r,','))."\nWHERE 1 ";
			foreach ( $where as $f ){
				if ( !isset($m['fields'][$f]) ){
					die("The fields to search for in get_update don't correspond to the table");
				}
				$r .= "\nAND `$f` ";
				if ( stripos($m['fields'][$f]['type'],'int') !== false ){
					$r .= "= %u ";
				}
				else{
					$r .= "= %s ";
				}
			}

			if ( $php ){
				$r .= "\",\n";
				$i = 0;
				foreach ( array_keys($m['fields']) as $k ){
					if ( !in_array($k, $where) && ( count($fields) === 0 || in_array($k,$fields) ) ){
						$r .= "\$d['$k'],\n";
					}
				}
				foreach ( $where as $f ){
					$r .= "\$d['$f'],\n";
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
		if ( text::check_name($user, $db) && strpos($pass, "'") === false ){
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
		if ( text::check_name($user) ){
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
}
?>