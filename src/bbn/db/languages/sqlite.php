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
  /** @var bbn\db The connection object */
  private $db;

  /** @var array Allowed operators */
	public static $operators = ['!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between', 'not like'];

	/** @var array Numeric column types */
  public static $numeric_types = ['integer', 'numeric', 'real'];

  /** @var string The quote character */
  public $qte = '"';

  /**
   * Constructor
   * @param bbn\db $db
   */
  public function __construct(bbn\db $db = null){
    if ( !\extension_loaded('pdo_sqlite') ){
      die('The SQLite driver for PDO is not installed...');
    }
    $this->db = $db;
    // Obliged to do that  if we want to use foreign keys with SQLite
    $this->enable_keys();
  }

  /*****************************************************************************************************************
   *                                                                                                                *
   *                                                                                                                *
   *                                               ENGINES INTERFACE                                                *
   *                                                                                                                *
   *                                                                                                                *
   *****************************************************************************************************************/

  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function get_connection(array $cfg = []): ?array
  {
    $cfg['engine'] = 'sqlite';
    if ( !isset($cfg['db']) && \defined('BBN_DATABASE') ){
      $cfg['db'] = BBN_DATABASE;
    }
    if ( isset($cfg['db']) && \strlen($cfg['db']) > 1 ){
      if ( is_file($cfg['db']) ){
        $info = pathinfo($cfg['db']);
        $cfg['host'] = $info['dirname'].DIRECTORY_SEPARATOR;
        $cfg['db'] = $info['basename'];
      }
      else if (
        \defined('BBN_DATA_PATH') &&
        is_dir(BBN_DATA_PATH.'db') &&
        (strpos($cfg['db'], '/') === false)
      ){
        $cfg['host'] = BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR;
        if (
          !is_file(BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR.$cfg['db']) &&
          (strpos($cfg['db'], '.') === false)
        ){
          $cfg['db'] .= '.sqlite';
        }
      }
      else{
        $info = pathinfo($cfg['db']);
        if ( is_writable($info['dirname']) ){
          $cfg['host'] = $info['dirname'].DIRECTORY_SEPARATOR;
          $cfg['db'] = isset($info['extension']) ? $info['basename'] : $info['basename'].'.sqlite';
        }
      }
      if ( isset($cfg['host']) ){
        $cfg['args'] = ['sqlite:'.$cfg['host'].$cfg['db']];
        $cfg['db'] = 'main';
        return $cfg;
      }
    }
    return null;
	}
	
	/**
   * @param string $db The database name or file
	 * @return string | false
	 */
	public function change(string $db): bool
	{
    if ( strpos($db, '.') === false ){
      $db .= '.sqlite';
    }
    $info = pathinfo($db);
    if ( ( $info['filename'] !== $this->db->current ) && file_exists($this->db->host.$db) && strpos($db, "'") === false ){
      $this->db->raw_query("ATTACH '".$this->db->host.$db."' AS ".$info['filename']);
      return true;
    }
		return false;
	}
	
	/**
	 * Returns a database item expression escaped like database, table, column, key names
	 * 
	 * @param string $item The item's name (escaped or not)
	 * @return string
	 */
	public function escape(string $item): string
	{
    $items = explode('.', str_replace($this->qte, '', $item));
    $r = [];
    foreach ( $items as $m ){
      if ( !bbn\str::check_name($m) ){
        return false;
      }
      $r[] = '"'.$m.'"';
    }
    return implode('.', $r);
	}

  /**
	 * Returns a table's full name i.e. database.table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return null|string
	 */
	public function table_full_name(string $table, bool $escaped = false): ?string
	{
    $bits = explode('.', str_replace('"', '', $table));
    if ( \count($bits) === 2 ){
      $db = trim($bits[0]);
      $table = trim($bits[1]);
    }
    else{
      $db = $this->db->current;
      $table = trim($bits[0]);
    }
    if ( bbn\str::check_name($db, $table) ){
      if ( $db === 'main' ){
        return $escaped ? '"'.$table.'"' : $table;
      }
      return $escaped ? '"'.$db.'"."'.$table.'"' : $db.'.'.$table;
    }
		return null;
	}
	
	/**
	 * Returns a table's simple name i.e. table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return null|string
	 */
  public function table_simple_name(string $table, bool $escaped = false): ?string
  {
    if ( \is_string($table) && ($table = trim($table)) ){
      $bits = explode('.', str_replace('"', '', $table));
      $table = end($bits);
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
	 * @param null|string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return null|string
	 */
  public function col_full_name(string $col, $table = null, $escaped = false): ?string
  {
    if ( \is_string($col) && ($col = trim($col)) ){
      $bits = explode('.', str_replace('"', '', $col));
      $ok = null;
      if ( null !== $table ){
        $table = $this->table_simple_name($table);
        $col = end($bits);
        $ok = 1;
      }
      else if ( \count($bits) > 1 ){
        $col = array_pop($bits);
        $table = array_pop($bits);
        $ok = 1;
      }
      if ( (null !== $ok) && bbn\str::check_name($table, $col) ){
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
	 * @return null|string
	 */
  public function col_simple_name(string $col, bool $escaped=false): ?string
  {
    if ( \is_string($col) && ($col = trim($col)) ){
      $bits = explode('.', str_replace('"', '', $col));
      $col = end($bits);
      if ( bbn\str::check_name($col) ){
        return $escaped ? '"'.$col.'"' : $col;
      }
    }
    return false;
  }

  public function is_table_full_name(string $table): bool
  {
    return true;
  }

  public function is_col_full_name(string $col): bool
  {
    return (bool)strpos($col, '.');
  }

  /**
   * Disable foreign keys check
   *
   * @return bbn\db
   */
  public function disable_keys(): bbn\db
  {
    $this->db->raw_query('PRAGMA foreign_keys = OFF;');
    return $this->db;
  }

  /**
   * Enable foreign keys check
   *
   * @return bbn\db
   */
  public function enable_keys(): bbn\db
  {
    $this->db->raw_query('PRAGMA foreign_keys = ON;');
    return $this->db;
  }

  /**
	 * @return null|array
	 */
	public function get_databases(): ?array
	{
    if ( !$this->db->check() ){
      return null;
    }
    $x = [];
    $fs = bbn\file\dir::scan($this->db->host);
    foreach ( $fs as $f ){
      if ( is_file($f) ){
        $x[] = pathinfo($f, PATHINFO_FILENAME);
      }
    }
    sort($x);
    return $x;
	}

  /**
   * @param string $database Database name
   * @return null|array
   */
	public function get_tables(string $database=''): ?array
	{
    if ( !$this->db->check() ){
      return null;
    }
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
   * @param null|string $table The table's name
   * @return null|array
   */
	public function get_columns(string $table): ?array
	{
    if ( !$this->db->check() ){
      return null;
    }
    $r = [];
		if ( $table = $this->table_full_name($table) ){
      $p = 1;
      if ( $rows = $this->db->get_rows("PRAGMA table_info($table)") ){
        foreach ( $rows as $row ){
          $f = $row['name'];
          $r[$f] = [
            'position' => $p++,
            'null' => $row['notnull'] == 0 ? 1 : 0,
            'key' => $row['pk'] == 1 ? 'PRI' : null,
            'default' => $row['dflt_value'],
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
            $r[$f]['maxlength'] = (int)$matches[1][0];
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
   * @param string $table The table's name
   * @return null|array
   */
	public function get_keys(string $table): ?array
	{
    if ( !$this->db->check() ){
      return null;
    }
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
                $keys[$d['name']]['columns'][] = $d2['name'];
              }
              if ( !isset($cols[$d2['name']]) ){
                $cols[$d2['name']] = [$d['name']];
              }
              else{
                $cols[$d2['name']][] = $d['name'];
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
   * Returns a string with the conditions for the ON, WHERE, or HAVING part of the query if there is, empty otherwise
   *
   * @param array $conditions
   * @param array $cfg
   * @return string
   */
  public function get_conditions(array $conditions, array $cfg = []): string
  {
    $res = '';
    if ( isset($conditions['conditions'], $conditions['logic']) ){
      $logic = isset($conditions['logic']) && ($conditions['logic'] === 'OR') ? 'OR' : 'AND';
      foreach ( $conditions['conditions'] as $key => $f ){
        if ( \is_array($f) && isset($f['logic']) && !empty($f['conditions']) ){
          if ( $tmp = $this->get_conditions($f, $cfg) ){
            $res .= (empty($res) ? '(' : PHP_EOL."$logic ").$tmp;
          }
        }
        else if ( isset($f['operator'], $f['field']) ){
          $field = $f['field'];
          if ( !array_key_exists('value', $f) ){
            $f['value'] = false;
          }
          $is_number = false;
          $is_null = true;
          $is_uid = false;
          $model = null;
          if ( isset($cfg['available_fields'][$field]) ){
            $table = $cfg['available_fields'][$field];
            $column = $this->col_simple_name($cfg['fields'][$field] ?? $field);
            if ( isset($cfg['models'][$table]['fields'][$column]) ){
              $model = $cfg['models'][$table]['fields'][$column];
              $res .= (empty($res) ?
                  '(' :
                  " $logic ").(
                isset($cfg['available_fields'][$field]) ?
                  $this->col_full_name($field, $cfg['available_fields'][$field], true).' ' :
                  $this->col_simple_name($column, true)
                );
            }
            else{
              $res .= (empty($res) ? '(' : PHP_EOL."$logic ").$this->escape($field).' ';
            }
            if ( !empty($model) ){
              $is_null = (bool)$model['null'];
              if ( $model['type'] === 'binary' ){
                $is_number = true;
                if ( ($model['maxlength'] === 16) && $model['key'] ){
                  $is_uid = true;
                }
              }
            }
          }
          else{
            $res .= (empty($res) ? '(' : " $logic ").$this->escape($field).' ';
          }
          switch ( $f['operator'] ){
            case 'eq':
            case '=':
              if ( isset($f['exp']) ){
                $res .= '= '.$f['exp'];
              }
              else if ( $is_uid ){
                $res .= '= X\'?\'';
              }
              else if ( $is_number ){
                $res .= '= ?';
              }
              else{
                $res .= 'LIKE ?';
              }
              break;
            case 'neq':
            case '!=':
              if ( isset($f['exp']) ){
                $res .= '!= '.$f['exp'];
              }
              else if ( $is_uid ){
                $res .= '!= X\'?\'';
              }
              else if ( $is_number ){
                $res .= '!= ?';
              }
              else{
                $res .= 'NOT LIKE ?';
              }
              break;

            case 'startswith':
              $res .= 'LIKE ?';
              break;

            case 'endswith':
              $res .= 'LIKE ?';
              break;

            case 'gte':
            case '>=':
              if ( isset($f['exp']) ){
                $res .= '>= '.$f['exp'];
              }
              else{
                $res .= '>= ?';
              }
              break;

            case 'gt':
            case '>':
              if ( isset($f['exp']) ){
                $res .= '> '.$f['exp'];
              }
              else{
                $res .= '> ?';
              }
              break;

            case 'lte':
            case '<=':
              if ( isset($f['exp']) ){
                $res .= '<= '.$f['exp'];
              }
              else{
                $res .= '<= ?';
              }
              break;

            case 'lt':
            case '<':
              if ( isset($f['exp']) ){
                $res .= '< '.$f['exp'];
              }
              else{
                $res .= '< ?';
              }
              break;

            /** @todo Check if it is working with an array */
            case 'isnull':
              $res .= $is_null ? 'IS NULL' : " = ''";
              break;

            case 'isnotnull':
              $res .= $is_null ? 'IS NOT NULL' : " != ''";
              break;

            case 'isempty':
              $res .= $is_number ? '= 0' : "LIKE ''";
              break;

            case 'isnotempty':
              $res .= $is_number ? '!= 0' : "NOT LIKE ''";
              break;

            case 'doesnotcontain':
              $res .= $is_number ? '!= ?' : 'NOT LIKE ?';
              break;

            case 'contains':
            default:
              $res .= $is_number ? '= ?' : 'LIKE ?';
              break;
          }
        }
      }
      if ( !empty($res) ){
        $res .= ')'.PHP_EOL;
      }
    }
    return $res;
  }

  /**
   * Generates a string starting with SELECT ... FROM with corresponding parameters
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function get_select(array $cfg): string
  {
    $res = '';
    if ( \is_array($cfg['tables']) && !empty($cfg['tables']) ){
      $res = 'SELECT ';
      if ( !empty($cfg['count']) ){
        $res .= 'COUNT(';
      }
      if ( empty($cfg['fields']) ){
        $res .= '* ';
      }
      else{
        $fields_to_put = [];
        // Checking the selected fields
        foreach ( $cfg['fields'] as $alias => $f ){
          $is_distinct = false;
          $f = trim($f);
          $bits = explode(' ', $f);
          if ( (count($bits) > 1) && (strtolower($bits[0]) === 'distinct') ){
            $is_distinct = true;
            array_shift($bits);
            $f = implode(' ', $bits);
          }
          // Adding the alias in $fields
          if ( strpos($f, '(') ){
            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '').$f.(\is_string($alias) ? ' AS '.$alias : '');
          }
          else if ( !empty($cfg['available_fields'][$f]) ){
            $idx = $cfg['available_fields'][$f];
            $csn = $this->col_simple_name($f);
            $is_uid = false;
            //die(var_dump($idx, $f, $tables[$idx]));
            if ( ($idx !== false) && isset($cfg['models'][$idx]['fields'][$csn]) ){
              $column = $cfg['models'][$idx]['fields'][$csn];
              if ( ($column['type'] === 'binary') && ($column['maxlength'] === 16) ){
                $is_uid = true;
                if ( !\is_string($alias) ){
                  $alias = $csn;
                }
              }
            }
            //$res['fields'][$alias] = $this->cfn($f, $fields[$f]);
            if ( $is_uid ){
              $st = 'LOWER(HEX('.$this->col_full_name($csn, $cfg['available_fields'][$f], true).'))';
            }
            else{
              $st = $this->col_full_name($csn, $cfg['available_fields'][$f], true);
            }
            if ( \is_string($alias) ){
              $st .= ' AS '.$alias;
            }
            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '').$st;
          }
          else if ( isset($cfg['available_fields'][$f]) && ($cfg['available_fields'][$f] === false) ){
            $this->db->error("Error! The column '$f' exists on several tables in '".implode(', ', $cfg['tables']));
          }
          else{
            $this->db->error("Error! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']));
          }
        }
        $res .= implode(', ', $fields_to_put).PHP_EOL;
      }
      if ( !empty($cfg['count']) ){
        $res .= ') AS num'.PHP_EOL;
      }
      $tables_to_put = [];
      foreach ( $cfg['tables'] as $alias => $tfn ){
        $st = $this->table_full_name($tfn, true);
        if ( $alias !== $tfn ){
          $st .= ' AS '.$alias;
        }
        $tables_to_put[] = $st;
      }
      $res .= 'FROM '.implode(', ', $tables_to_put).PHP_EOL;
      return $res;
    }
    return $res;
  }

  /**
   * @param array $cfg The configuration array
   * @return string
   */
  public function get_insert(array $cfg): string
  {
    $fields_to_put = [
      'values' => [],
      'fields' => []
    ];
    $i = 0;
    foreach ( $cfg['fields'] as $alias => $f ){
      if ( isset($cfg['available_fields'][$f], $cfg['models'][$cfg['available_fields'][$f]]) ){
        $model = $cfg['models'][$cfg['available_fields'][$f]];
        $csn = $this->col_simple_name($f);
        $is_uid = false;
        if ( isset($model['fields'][$csn]) ){
          $column = $model['fields'][$csn];
          if ( ($column['type'] === 'binary') && ($column['maxlength'] === 16) ){
            $is_uid = true;
          }
          $fields_to_put['fields'][] = $this->col_simple_name($f, true);
          $fields_to_put['values'][] = $is_uid && (!$column['null'] || (null !== $cfg['values'][$i])) ? 'UNHEX(?)' : '?';
        }
      }
      else{
        $this->db->error("Error! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']));
      }
      $i++;
    }
    if ( count($fields_to_put['fields']) && (count($cfg['tables']) === 1) ){
      return 'INSERT '.($cfg['ignore'] ? 'IGNORE ' : '').'INTO '.$this->table_simple_name(current($cfg['tables']), true).PHP_EOL.
        '('.implode(', ', $fields_to_put['fields']).')'.PHP_EOL.' VALUES ('.
        implode(', ', $fields_to_put['values']).')'.PHP_EOL;
    }
    return '';
  }

  /**
   * @param array $cfg The configuration array
   * @return string
   */
  public function get_update(array $cfg): string
  {
    $res = '';
    $fields_to_put = [
      'values' => [],
      'fields' => []
    ];
    foreach ( $cfg['fields'] as $alias => $f ){
      if ( isset($cfg['available_fields'][$f], $cfg['models'][$cfg['available_fields'][$f]]) ){
        $model = $cfg['models'][$cfg['available_fields'][$f]];
        $csn = $this->col_simple_name($f);
        $is_uid = false;
        if ( isset($model['fields'][$csn]) ){
          $column = $model['fields'][$csn];
          if ( ($column['type'] === 'binary') && ($column['maxlength'] === 16) ){
            $is_uid = true;
          }
          $fields_to_put['fields'][] = $this->col_simple_name($f, true);
          $fields_to_put['values'][] = $is_uid ? 'UNHEX(?)' : '?';
        }
      }
      else{
        $this->db->error("Error! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']));
      }
    }
    if ( count($fields_to_put['fields']) ){
      $res .= 'UPDATE '.($cfg['ignore'] ? 'IGNORE ' : '').$this->table_simple_name(current($cfg['tables']), true).' SET ';
      $last = count($fields_to_put['fields']) - 1;
      foreach ( $fields_to_put['fields'] as $i => $f ){
        $res .= $f.' = '.$fields_to_put['values'][$i];
        if ( $i < $last ){
          $res .= ',';
        }
        $res .= PHP_EOL;
      }
    }
    return $res;
  }

  /**
   * Return SQL code for row(s) DELETE.
   *
   * ```php
   * \bbn\x::dump($db->get_delete('table_users',['id'=>1]));
   * // (string) DELETE FROM `db_example`.`table_users` * WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function get_delete(array $cfg): string
  {
    $res = '';
    if ( count($cfg['tables']) === 1 ){
      $res = 'DELETE '.( $cfg['ignore'] ? 'IGNORE ' : '' ).
        'FROM '.$this->table_full_name(current($cfg['tables']), true).PHP_EOL;
    }
    return $res;
  }

  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function get_join(array $cfg): string
  {
    $res = '';
    if ( !empty($cfg['join']) ){
      foreach ( $cfg['join'] as $join ){
        if ( isset($join['table'], $join['on']) && ($cond = $this->db->get_conditions($join['on'], $cfg)) ){
          $res .=
            (isset($join['type']) && ($join['type'] === 'left') ? 'LEFT ' : '').
            'JOIN '.$this->table_full_name($join['table'],true).
            (!empty($join['alias']) ? ' AS '.$join['alias'] : '').PHP_EOL.'ON '.$cond;
        }
      }
    }
    return $res;
  }

  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function get_where(array $cfg): string
  {
    $res = $this->get_conditions($cfg['filters'] ?: [], $cfg);
    if ( !empty($res) ){
      $res = 'WHERE '.$res;
    }
    return $res;
  }

  /**
   * Returns a string with the GROUP BY part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function get_group_by(array $cfg): string
  {
    $res = '';
    $group_to_put = [];
    if ( !empty($cfg['group_by']) ){
      foreach ( $cfg['group_by'] as $g ){
        if ( isset($cfg['available_fields'][$g]) ){
          $group_to_put[] = $this->col_full_name($g, $cfg['available_fields'][$g], true);
        }
        else{
          $this->db->error("Error! The column '$g' doesn't exist for group by");
        }
      }
      if ( count($group_to_put) ){
        $res .= 'GROUP BY '.implode(', ', $group_to_put).PHP_EOL;
        if ( !empty($cfg['having']) ){
          $res .= 'HAVING '.$this->get_conditions($cfg['having'], $cfg);
        }
      }
    }
    return $res;
  }

  /**
   * Returns a string with the HAVING part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function get_having(array $cfg): string
  {
    $res = '';
    if ( !empty($cfg['group_by']) && !empty($cfg['having']) && ($cond = $this->get_conditions($cfg['having'])) ){
      $res .= PHP_EOL.'HAVING '.$cond;
    }
    return $res;
  }

  /**
   * @param array $cfg
   * @return string
   */
  public function get_order(array $cfg): string
  {
    $res = '';
    if ( !empty($cfg['order']) ){
      foreach ( $cfg['order'] as $col => $dir ){
        if ( isset($cfg['available_fields'][$col]) ){
          $res .= $this->escape($col).' COLLATE NOCASE '.
            (strtolower($dir) === 'desc' ? 'DESC' : 'ASC' ).','.PHP_EOL;
        }
      }
      if ( !empty($res) ){
        return 'ORDER BY '.substr($res,0, strrpos($res,','));
      }
    }
    return $res;
  }

  /**
   * Get a string starting with LIMIT with corresponding parameters to $where
   *
   * @param array $cfg
   * @return string
   */
  public function get_limit(array $cfg): string
  {
    $res = '';
    if ( $cfg['limit'] && bbn\str::is_integer($cfg['limit']) ){
      $res .= 'LIMIT '.(bbn\str::is_integer($cfg['start']) ? (string)$cfg['start'] : '0').', '.$cfg['limit'];
    }
    return $res;
  }
  
	/**
	 * @return null|string
	 */
	public function get_create(string $table): string
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
		return '';
	}


  /**
   * Creates an index
   *
   * @param null|string $table
   * @param string|array $column
   * @param bool $unique
   * @param null $length
   * @return bool
   */
  public function create_index(string $table, $column, bool $unique = false, $length = null): bool
	{
    if ( !\is_array($column) ){
      $column = [$column];
    }
    if ( !\is_null($length) ){
      if ( !\is_array($length) ){
        $length = [$length];
      }
    }
    $name = bbn\str::encode_filename($table);
    foreach ( $column as $i => $c ){
      if ( !bbn\str::check_name($c) ){
        $this->db->error("Illegal column $c");
      }
      $name .= '_'.$c;
      $column[$i] = '`'.$column[$i].'`';
      if ( \is_int($length[$i]) && $length[$i] > 0 ){
        $column[$i] .= '('.$length[$i].')';
      }
    }
    $name = bbn\str::cut($name, 50);
		if ( $table = $this->table_full_name($table, 1) ){
			return (bool)$this->db->query('CREATE '.( $unique ? 'UNIQUE ' : '' )."INDEX `$name` ON $table ( ".implode(', ', $column).' )');
		}
		return false;
	}

  /**
   * Deletes an index
   *
   * @param null|string $table
   * @param string $key
   * @return bool
   */
  public function delete_index(string $table, string $key): bool
	{
		if ( ( $table = $this->table_full_name($table, 1) ) && bbn\str::check_name($key) ){
			return (bool)$this->db->query("ALTER TABLE $table DROP INDEX `$key`");
		}
		return false;
	}

  /**
   * Creates a database user
   *
   * @param string $user
   * @param string $pass
   * @param string $db
   * @return bool
   */
  public function create_user(string $user, string $pass, string $db = null): bool
  {
    return true;
  }

  /**
   * Deletes a database user
   *
   * @param string $user
   * @return bool
   */
  public function delete_user(string $user): bool
  {
    return true;
  }

  /**
   * @param string $user
   * @param string $host
   * @return array|null
   */
  public function get_users(string $user = '', string $host = ''): ?array
  {
    return [];
  }

  public function db_size(string $database = '', string $type = ''): int
  {
    return @filesize($database) ?: 0;
  }

  public function table_size(string $table, string $type = ''): int
  {
    return 0;
  }

  public function status(string $table = '', string $database = ''){
    $cur = null;
    if ( $database && ($this->db->current !== $database) ){
      $cur = $this->db->current;
      $this->db->change($database);
    }
    $r = $this->db->get_row('SHOW TABLE STATUS WHERE Name LIKE ?', $table);
    if ( null !== $cur ){
      $this->db->change($cur);
    }
    return $r;
  }

  public function get_uid(): string
  {
    return bbn\x::make_uid();
  }
}