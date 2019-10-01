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
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.4
 */
class mysql implements bbn\db\engines
{
  /** @var bbn\db The connection object */
  private $db;

  /** @var array Allowed operators */
	public static $operators = ['!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between', 'not like'];

  /** @var array Numeric column types */
  public static $numeric_types = ['integer', 'int', 'smallint', 'tinyint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double'];

  /** @var array Time and date column types */
  public static $date_types = ['date', 'time', 'datetime'];

  public static $types = [
    'tinyint',
    'smallint',
    'mediumint',
    'int',
    'bigint',
    'decimal',
    'float',
    'double',
    'bit',
    'char',
    'varchar',
    'binary',
    'varbinary',
    'tinyblob',
    'blob',
    'mediumblob',
    'longblob',
    'tinytext',
    'text',
    'mediumtext',
    'longtext',
    'enum',
    'set',
    'date',
    'time',
    'datetime',
    'timestamp',
    'year',
    'geometry',
    'point',
    'linestring',
    'polygon',
    'geometrycollection',
    'multilinestring',
    'multipoint',
    'multipolygon',
    'json'
  ];
  /** @var string The quote character */
  public $qte = '`';

  /**
   * Constructor
   * @param bbn\db $db
   */
  public function __construct(bbn\db $db = null){
    if ( !\extension_loaded('pdo_mysql') ){
      die('The MySQL driver for PDO is not installed...');
    }
    $this->db = $db;
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
    $cfg['engine'] = 'mysql';
    if ( !isset($cfg['host']) ){
      $cfg['host'] = \defined('BBN_DB_HOST') ? BBN_DB_HOST : '127.0.0.1';
    }
    if ( \defined('BBN_DB_USER') && !isset($cfg['user']) ){
      $cfg['user'] = \defined('BBN_DB_USER') ? BBN_DB_USER : 'root';
    }
    if ( !isset($cfg['pass']) ){
      $cfg['pass'] = \defined('BBN_DB_PASS') ? BBN_DB_PASS : '';
    }
    if ( \defined('BBN_DATABASE') && !isset($cfg['db']) ){
      $cfg['db'] = BBN_DATABASE;
    }
		if ( !empty($cfg['db']) )
		{
      $cfg['args'] = [
        'mysql:host='.
          ( $cfg['host'] === 'localhost' ? '127.0.0.1' : $cfg['host'] ).
          ';dbname='.$cfg['db'],
        $cfg['user'],
        $cfg['pass'],
        [
          \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
        ]
      ];
      return $cfg;
    }
    return null;
	}

  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function post_creation(){
    return;
  }

  /**
   * @param string $db The database name or file
	 * @return bool
	 */
	public function change(string $db): bool
	{
		if ( ($this->db->current !== $db) && bbn\str::check_name($db) ){
			$this->db->raw_query("USE `$db`");
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
      $r[] = $this->qte.$m.$this->qte;
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
    $bits = explode('.', $table);
    if ( \count($bits) === 3 ){
      $db = trim($bits[0], ' '.$this->qte);
      $table = trim($bits[1]);
    }
    else if ( \count($bits) === 2 ){
      $db = trim($bits[0], ' '.$this->qte);
      $table = trim($bits[1], ' '.$this->qte);
    }
    else{
      $db = $this->db->current;
      $table = trim($bits[0], ' '.$this->qte);
    }
    if ( bbn\str::check_name($db, $table) ){
      return $escaped ? $this->qte.$db.$this->qte.'.'.$this->qte.$table.$this->qte : $db.'.'.$table;
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
    if ( $table = trim($table) ){
      $bits = explode('.', $table);
      switch ( \count($bits) ){
        case 1:
          $table = trim($bits[0], ' '.$this->qte);
          break;
        case 2:
          $table = trim($bits[1], ' '.$this->qte);
          break;
        case 3:
          $table = trim($bits[1], ' '.$this->qte);
          break;
      }
      if ( bbn\str::check_name($table) ){
        return $escaped ? $this->qte.$table.$this->qte : $table;
      }
    }
		return null;
  }

	/**
	 * Returns a column's full name i.e. table.column
	 *
	 * @param string $col The column's name (escaped or not)
	 * @param null|string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function col_full_name(string $col, $table = null, $escaped = false): ?string
  {
    if ( $col = trim($col) ){
      $bits = explode('.', $col);
      $ok = null;
      $col = trim(array_pop($bits), ' '.$this->qte);
      if ( $table && ($table = $this->table_simple_name($table)) ){
        $ok = 1;
      }
      else if ( \count($bits) ){
        $table = trim(array_pop($bits), ' '.$this->qte);
        $ok = 1;
      }
      if ( (null !== $ok) && bbn\str::check_name($table, $col) ){
        return $escaped ? $this->qte.$table.$this->qte.'.'.$this->qte.$col.$this->qte : $table.'.'.$col;
      }
    }
		return null;
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
    if ( $bits = explode('.', $col) ){
      $col = trim(end($bits), ' '.$this->qte);
      if ( bbn\str::check_name($col) ){
        return $escaped ? $this->qte.$col.$this->qte : $col;
      }
    }
    return null;
  }

  /**
   * @param string $table
   * @return bool
   */
  public function is_table_full_name(string $table): bool
  {
    return strpos($table, '.') ? true : false;
  }

  /**
   * @param string $col
   * @return bool
   */
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
    $this->db->raw_query('SET FOREIGN_KEY_CHECKS=0;');
    return $this->db;
  }

  /**
   * Enable foreign keys check
   *
   * @return bbn\db
   */
  public function enable_keys(): bbn\db
  {
    $this->db->raw_query('SET FOREIGN_KEY_CHECKS=1;');
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
    if ( $r = $this->db->raw_query('SHOW DATABASES') ){
      $x = array_map( function($a){
        return $a['Database'];
      }, array_filter($r->fetchAll(\PDO::FETCH_ASSOC),function($a){
        return ( $a['Database'] === 'information_schema' ) || ( $a['Database'] === 'mysql' ) ? false : 1;
      }));
      sort($x);
    }
    return $x;
	}

	/**
   * @param string $database Database name
   * @return null|array
	 */
	public function get_tables(string $database = ''): ?array
	{
    if ( !$this->db->check() ){
      return null;
    }
		if ( empty($database) || !bbn\str::check_name($database) ){
			$database = $this->db->current;
		}
		$t2 = [];
    if (
      ($r = $this->db->raw_query("SHOW TABLES FROM `$database`")) &&
      ($t1 = $r->fetchAll(\PDO::FETCH_NUM) )
    ){
      foreach ( $t1 as $t ){
        $t2[] = $t[0];
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
		if ( $full = $this->table_full_name($table) ){
			$t = explode('.', $full);
			[$db, $table] = $t;
      $sql = <<<MYSQL
        SELECT *
        FROM `information_schema`.`COLUMNS`
        WHERE `TABLE_NAME` LIKE ?
        AND `TABLE_SCHEMA` LIKE ?
        ORDER BY `ORDINAL_POSITION` ASC
MYSQL;
      if ( $rows = $this->db->get_rows($sql, $table, $db) ){
        $p = 1;
        foreach ( $rows as $row ){
          $f = $row['COLUMN_NAME'];
          $r[$f] = [
            'position' => $p++,
            'type' => $row['DATA_TYPE'],
            'null' => $row['IS_NULLABLE'] === 'NO' ? 0 : 1,
            'key' => \in_array($row['COLUMN_KEY'], ['PRI', 'UNI', 'MUL']) ? $row['COLUMN_KEY'] : null,
            'default' => ($row['COLUMN_DEFAULT'] === null) && ($row['IS_NULLABLE'] !== 'NO') ? 'NULL' : $row['COLUMN_DEFAULT'],
            'extra' => $row['EXTRA'],
            'signed' => strpos($row['COLUMN_TYPE'], ' unsigned') === false,
            'maxlength' => $row['CHARACTER_MAXIMUM_LENGTH'],
            'virtual' => $row['EXTRA'] === 'VIRTUAL GENERATED',
            'generation' => $row['GENERATION_EXPRESSION']
          ];
          if ( ($r[$f]['type'] === 'enum') || ($r[$f]['type'] === 'set') ){
            if (
              preg_match_all('/\((.*?)\)/', $row['COLUMN_TYPE'], $matches) &&
              !empty($matches[1]) &&
              \is_string($matches[1][0]) &&
              ($matches[1][0][0] === "'")
            ){
              $r[$f]['values'] = explode("','", substr($matches[1][0], 1, -1));
              $r[$f]['extra'] = $matches[1][0];
            }
            else{
              $r[$f]['values'] = [];
            }
          }
          else if ( preg_match_all('/\((.*?)\)/', $row['COLUMN_TYPE'], $matches) ){
            $r[$f]['maxlength'] = (int)$matches[1][0];
          }
        }
        /*
        else{
          preg_match_all('/(.*?)\(/', $row['Type'], $real_type);
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
            $r[$f]['maxlength'] = (int)$matches[1][0];
          }
          if ( !isset($r[$f]['type']) ){
            $r[$f]['type'] = strpos($row['Type'], '(') ? substr($row['Type'],0,strpos($row['Type'], '(')) : $row['Type'];
          }
        }
        */
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
			$t = explode('.', $full);
      [$db, $table] = $t;
      $r = [];
      $indexes = $this->db->get_rows('SHOW INDEX FROM '.$this->table_full_name($full, 1));
      $keys = [];
      $cols = [];
      foreach ( $indexes as $i => $index ){
        $a = $this->db->get_row(<<<MYSQL
SELECT `CONSTRAINT_NAME` AS `name`,
`ORDINAL_POSITION` AS `position`,
`REFERENCED_TABLE_SCHEMA` AS `ref_db`,
`REFERENCED_TABLE_NAME` AS `ref_table`,
`REFERENCED_COLUMN_NAME` AS `ref_column`
FROM `information_schema`.`KEY_COLUMN_USAGE`
WHERE `TABLE_SCHEMA` LIKE ?
AND `TABLE_NAME` LIKE ?
AND `COLUMN_NAME` LIKE ?
AND (
  `CONSTRAINT_NAME` LIKE ? OR
  (`REFERENCED_TABLE_NAME` IS NOT NULL OR `ORDINAL_POSITION` = ?)
)
ORDER BY `KEY_COLUMN_USAGE`.`REFERENCED_TABLE_NAME` DESC
LIMIT 1
MYSQL
          ,
          $db,
          $table,
          $index['Column_name'],
          $index['Key_name'],
          $index['Seq_in_index']);
        if ( $a ){
          $b = $this->db->get_row(<<<MYSQL
          SELECT `CONSTRAINT_NAME` AS `name`,
          `UPDATE_RULE` AS `update`,
          `DELETE_RULE` AS `delete`
          FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
          WHERE `CONSTRAINT_NAME` LIKE ?
          AND `CONSTRAINT_SCHEMA` LIKE ?
          AND `TABLE_NAME` LIKE ?
          LIMIT 1
MYSQL
                    ,
                    $a['name'],
                    $db,
                    $table);
        }
        if ( !isset($keys[$index['Key_name']]) ){
          $keys[$index['Key_name']] = [
            'columns' => [$index['Column_name']],
            'ref_db' => isset($a, $a['ref_db']) ? $a['ref_db'] : null,
            'ref_table' => isset($a, $a['ref_table']) ? $a['ref_table'] : null,
            'ref_column' => isset($a, $a['ref_column']) ? $a['ref_column'] : null,
            'constraint' => isset($b, $b['name']) ? $b['name'] : null,
            'update' => isset($b, $b['update']) ? $b['update'] : null,
            'delete' => isset($b, $b['delete']) ? $b['delete'] : null,
            'unique' => $index['Non_unique'] ? 0 : 1
          ];
        }
        else{
          $keys[$index['Key_name']]['columns'][] = $index['Column_name'];
          $keys[$index['Key_name']]['ref_db'] = $keys[$index['Key_name']]['ref_table'] = $keys[$index['Key_name']]['ref_column'] = null;
        }
        if ( !isset($cols[$index['Column_name']]) ){
          $cols[$index['Column_name']] = [$index['Key_name']];
        }
        else{
          $cols[$index['Column_name']][] = $index['Key_name'];
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
   * @param bool $is_having
   * @return string
   */
  public function get_conditions(array $conditions, array $cfg = [], bool $is_having = false): string
  {   
    $res = '';
    if ( isset($conditions['conditions'], $conditions['logic']) ){
      $logic = isset($conditions['logic']) && ($conditions['logic'] === 'OR') ? 'OR' : 'AND';
      foreach ( $conditions['conditions'] as $key => $f ){
        if ( \is_array($f) && isset($f['logic']) && isset($f['conditions']) ){
          if ( $tmp = $this->get_conditions($f, $cfg, $is_having) ){
            $res .= (empty($res) ? '(' : PHP_EOL."$logic (").$tmp.")";
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
          $is_date = false;
          $model = null;
          if ( isset($cfg['available_fields'][$field]) ){
            $table = $cfg['available_fields'][$field];
            $column = $this->col_simple_name($cfg['fields'][$field] ?? $field);
            if ( isset($cfg['models'][$table]['fields'][$column]) ){
              $model = $cfg['models'][$table]['fields'][$column];
              $res .= (empty($res) ?
                  '' :
                  " $logic ").(
                !empty($cfg['available_fields'][$field]) ?
                  $this->col_full_name($cfg['fields'][$field] ?? $field, $cfg['available_fields'][$field], true).' ' :
                  $this->col_simple_name($column, true)
                );
            }
            else{
              // Remove the alias from where and join but not in having
              if ( !$is_having && ($table === false) && isset($cfg['fields'][$field]) ){
                $field = $cfg['fields'][$field];
              }
              $res .= (empty($res) ? '' : PHP_EOL."$logic ").$field.' ';
            }
            if ( !empty($model) ){
              $is_null = (bool)$model['null'];
              if ( $model['type'] === 'binary' ){
                $is_number = true;
                if ( ($model['maxlength'] === 16) && $model['key'] ){
                  $is_uid = true;
                }
              }
              else if ( \in_array($model['type'], self::$numeric_types, true) ){
                $is_number = true;
              }
              else if ( \in_array($model['type'], self::$date_types, true) ){
                $is_date = true;
              }
            }
            else if ( $f['value'] && \bbn\str::is_uid($f['value']) ){
              $is_uid = true;
            }
            else if ( is_int($f['value']) || is_float($f['value']) ){
              $is_number = true;
            }
          }
          else{
            $res .= (empty($res) ? '' : " $logic ").$field.' ';
          }
          switch ( strtolower($f['operator']) ){
            case 'like':
              $res .= 'LIKE ?';
              break;
            case 'not like':
              $res .= 'NOT LIKE ?';
              break;
            case 'eq':
            case '=':
            case 'is':
              if ( isset($f['exp']) ){
                $res .= '= '.$f['exp'];
              }
              else if ( $is_uid || $is_number ){
                $res .= '= ?';
              }
              else{
                $res .= 'LIKE ?';
              }
              break;
            case 'neq':
            case '!=':
            case 'isnot':
              if ( isset($f['exp']) ){
                $res .= '!= '.$f['exp'];
              }
              else if ( $is_uid || $is_number ){
                $res .= '!= ?';
              }
              else{
                $res .= 'NOT LIKE ?';
              }
              break;

            case 'doesnotcontains':
            case 'doesnotcontain':
              $res .= 'NOT LIKE '.($f['exp'] ?? '?');
              break;

            case 'endswith':
            case 'startswith':
            case 'contains':
              $res .= 'LIKE '.($f['exp'] ?? '?');
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
              $res .= 'IS NULL';
              break;

            case 'isnotnull':
              $res .= 'IS NOT NULL';
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
              $res .= 'LIKE ?';
              break;

            default:
              $res .= $is_number ? '= ?' : 'LIKE ?';
              break;
          }
        }
      }
      if ( !empty($res) ){
        $res .= PHP_EOL;
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
      if ( !empty($cfg['fields']) ){
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
            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '').$f.(\is_string($alias) ? ' AS '.$this->escape($alias) : '');
          }
          else if ( array_key_exists($f, $cfg['available_fields']) ){
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
            // For JSON fields
            else if ( $cfg['available_fields'][$f] === false ){
              $st = $f;
            }
            else{
              $st = $this->col_full_name($csn, $cfg['available_fields'][$f], true);
            }
            if ( \is_string($alias) ){
              $st .= ' AS '.$this->escape($alias);
            }
            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '').$st;
          }
          else if ( isset($cfg['available_fields'][$f]) && ($cfg['available_fields'][$f] === false) ){
            $this->db->error("Error! The column '$f' exists on several tables in '".implode(', ', $cfg['tables']));
          }
          else{
            $this->db->error("Error! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']).' ('.implode(' - ', array_keys($cfg['available_fields'])).')');
          }
        }
        $res .= implode(', ', $fields_to_put);
      }
      $res .= PHP_EOL;
      $tables_to_put = [];
      foreach ( $cfg['tables'] as $alias => $tfn ){
        $st = $this->table_full_name($tfn, true);
        if ( $alias !== $tfn ){
          $st .= ' AS '.$this->escape($alias);
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
        //x::hdump('---------------', $idx, $f, $tables[$idx]['model']['fields'][$csn], $args['values'],
        // $res['values'], '---------------');
        if ( isset($model['fields'][$csn]) ){
          $column = $model['fields'][$csn];
          if ( ($column['type'] === 'binary') && ($column['maxlength'] === 16) ){
            $is_uid = true;
          }
          $fields_to_put['fields'][] = $this->col_simple_name($f, true);
          $fields_to_put['values'][] = '?';
        }
      }
      else{
        $this->db->error("Error! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']));
      }
      $i++;
    }
    if ( count($fields_to_put['fields']) && (count($cfg['tables']) === 1) ){
      return 'INSERT '.($cfg['ignore'] ? 'IGNORE ' : '').'INTO '.$this->table_full_name(current($cfg['tables']), true).PHP_EOL.
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
          $fields_to_put['values'][] = '?';
        }
      }
      else{
        $this->db->error("Error!! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']));
      }
    }
    if ( count($fields_to_put['fields']) ){
      $res .= 'UPDATE '.($cfg['ignore'] ? 'IGNORE ' : '').$this->table_full_name(current($cfg['tables']), true).' SET ';
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
            (!empty($join['alias']) ? ' AS '.$this->escape($join['alias']) : '').PHP_EOL.'ON '.$cond;
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
    $res = $this->get_conditions($cfg['filters'] ?? [], $cfg);
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
          $group_to_put[] = $this->escape($g);
          //$group_to_put[] = $this->col_full_name($g, $cfg['available_fields'][$g], true);
        }
        else{
          $this->db->error("Error! The column '$g' doesn't exist for group by ".print_r($cfg, true));
        }
      }
      if ( count($group_to_put) ){
        $res .= 'GROUP BY '.implode(', ', $group_to_put).PHP_EOL;
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
    if ( !empty($cfg['group_by']) && !empty($cfg['having']) && ($cond = $this->get_conditions($cfg['having'], $cfg, true)) ){
      $res .= 'HAVING '.$cond.PHP_EOL;
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
        if ( \is_array($dir) && isset($dir['field']) ){
          $col = $dir['field'];
          $dir = $dir['dir'] ?? 'ASC';
        }
        if ( isset($cfg['available_fields'][$col]) ){
          // If it's an alias we use the simple name
          if ( isset($cfg['fields'][$col]) ){
            $f = $this->col_simple_name($col, true);
          }
          else if ( $cfg['available_fields'][$col] === false ){
            $f = $col;
          }
          else {
            $f = $this->col_full_name($col, $cfg['available_fields'][$col], true);
          }
          $res .= $f.' '.(strtolower($dir) === 'desc' ? 'DESC' : 'ASC' ).','.PHP_EOL;
        }
      }
      if ( !empty($res) ){
        return 'ORDER BY '.substr($res,0, strrpos($res,',')).PHP_EOL;
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
    if ( !empty($cfg['limit']) && bbn\str::is_integer($cfg['limit']) ){
      $res .= 'LIMIT '.(!empty($cfg['start']) && bbn\str::is_integer($cfg['start']) ? (string)$cfg['start'] : '0').', '.$cfg['limit'];
    }
    return $res;
  }

  /**
   * @param null|string $table The table for which to create the statement
   * @return string
   */
  public function get_raw_create(string $table): string
  {
    if (
      ($table = $this->table_full_name($table, true)) &&
      ($r = $this->db->raw_query("SHOW CREATE TABLE $table"))
    ){
      return $r->fetch(\PDO::FETCH_ASSOC)['Create Table'];
    }
    return '';
  }

  public function get_create(string $table, array $model = null): string
  {
    if ( !$model ){
      $model = $this->db->modelize($table);
    }
    $st = 'CREATE TABLE '.$this->db->escape(table).' ('.PHP_EOL;
    $done = false;
    foreach ( $model['fields'] as $name => $col ){
      if ( !$done ){
        $done = true;
      }
      else{
        $st .= ','.PHP_EOL;
      }
      $st .= '  '.$this->db->escape($name).' ';
      $st .= $col['type'];
      if ( !empty($col['maxlength']) ){
        $st .= '('.$col['maxlength'].')';
      }
      if ( (strpos($col['type'], 'int') !== false) && !$col['signed'] ){
        $st .= ' UNSIGNED';
      }
      if ( empty($col['null']) ){
        $st .= ' NOT NULL';
      }
      if ( $col['virtual'] ){
        $st .= ' GENERATED ALWAYS AS ('.$col['generation'].') VIRTUAL';
      }
      else if ( array_key_exists('default', $col) ){
        $st .= ' DEFAULT ';
        if ( ($col['default'] === 'NULL') || bbn\str::is_number($col['default']) ){
          $st .= (string)$col['default'];
        }
        else{
          $st .= "'".bbn\str::escape_squotes($col['default'])."'";
        }
      }
    }
    foreach ( $model['keys'] as $name => $key ){
      $st .= ','.PHP_EOL.'  ';
      if ( $key['unique'] && (count($key['columns']) === 1) && isset($model['fields'][$key['columns'][0]]) && ($model['fields'][$key['columns'][0]]['key'] === 'PRI') ){
        $st .= 'PRIMARY KEY';
      }
      else if ($key['unique'] ){
        $st .= 'UNIQUE KEY '.$this->db->escape($name);    
      }
      else{
        $st .= 'KEY '.$this->db->escape($name);
      }
      $dbcls =& $this->db;
      $st .= ' ('.implode(',', array_map(function($a)use(&$dbcls){
        return $dbcls->escape($a);
      }, $key['columns'])).')';
    }
    foreach ( $model['keys'] as $name => $key ){
      if ( !empty($key['constraint']) ){
        $st .= ','.PHP_EOL.'  '.
          'CONSTRAINT '.$this->db->escape($key['constraint']).' FOREIGN KEY ('.$this->db->escape($name).') '.
          'REFERENCES '.$this->db->escape($key['ref_table']).' ('.$this->db->escape($key['ref_column']).')'.
          ($key['delete'] ? ' ON DELETE '.$key['delete'] : '').
          ($key['update'] ? ' ON UPDATE '.$key['update'] : '');
      }
    }
    $st .= PHP_EOL.') ENGINE=InnoDB DEFAULT CHARSET=utf8';
    return $st;
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
	  $column = (array)$column;
    if ( $length ){
      $length = (array)$length;
    }
    $name = bbn\str::encode_filename($table);
		if ( $table = $this->table_full_name($table, true) ){
      foreach ( $column as $i => $c ){
        if ( !bbn\str::check_name($c) ){
          $this->db->error("Illegal column $c");
        }
        $name .= '_'.$c;
        $column[$i] = $this->escape($column[$i]);
        if ( \is_int($length[$i]) && $length[$i] > 0 ){
          $column[$i] .= '('.$length[$i].')';
        }
      }
      $name = bbn\str::cut($name, 50);
			return (bool)$this->db->query('CREATE '.( $unique ? 'UNIQUE ' : '' )."INDEX `$name` ON $table ( ".
        implode(', ', $column).' )');
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
    if (
		  ($table = $this->table_full_name($table, true)) &&
      bbn\str::check_name($key)
    ){
			return (bool)$this->db->query(<<<MYSQL
ALTER TABLE $table
DROP INDEX `$key`
MYSQL
);
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
	  if ( null === $db ){
	    $db = $this->db->current;
    }
		if (
		  ($db = $this->escape($db)) &&
      bbn\str::check_name($user, $db) &&
      (strpos($pass, "'") === false)
    ){
			return (bool)$this->db->raw_query(<<<MYSQL
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER
ON $db . *
TO '$user'@'{$this->db->host}'
IDENTIFIED BY '$pass'
MYSQL
);
		}
		return false;
	}

  /**
   * Deletes a database user
   *
   * @param string $user
   * @return bool
   */
	public function delete_user(string $user): bool
	{
		if ( bbn\str::check_name($user) ){
			$this->db->raw_query("
			REVOKE ALL PRIVILEGES ON *.*
			FROM $user");
			return (bool)$this->db->query("DROP USER $user");
		}
		return false;
	}

  /**
   * @param string $user
   * @param string $host
   * @return array|null
   */
  public function get_users(string $user = '', string $host = ''): ?array
  {
    if ( $this->db->check() ){
      $cond = '';
      if ( !empty($user) && bbn\str::check_name($user) ){
        $cond .= " AND  user LIKE '$user' ";
      }
      if ( !empty($host) && bbn\str::check_name($host) ){
        $cond .= " AND  host LIKE '$host' ";
      }
      $us = $this->db->get_rows(<<<MYSQL
SELECT DISTINCT host, user
FROM mysql.user
WHERE 1
$cond
MYSQL
);
      $q = [];
      foreach ( $us as $u ){
        $gs = $this->db->get_col_array("SHOW GRANTS FOR '$u[user]'@'$u[host]'");
        foreach ( $gs as $g ){
          $q[] = $g;
        }
      }
      return $q;
    }
    return null;
  }

  public function db_size(string $database = '', string $type = ''): int
  {
	  $cur = null;
    if ( $database && ($this->db->current !== $database) ){
      $cur = $this->db->current;
      $this->db->change($database);
    }
    $q = $this->db->query('SHOW TABLE STATUS');
    $size = 0;
    while ( $row = $q->get_row() ){
      if ( !$type || ($type === 'data') ){
        $size += $row['Data_length'];
      }
      if ( !$type || ($type === 'index') ){
        $size += $row['Index_length'];
      }
    }
    if ( $cur !== null ){
      $this->db->change($cur);
    }
    return $size;
  }

  public function table_size(string $table, string $type = ''): int
  {
    $size = 0;
    if ( bbn\str::check_name($table) ){
      $row = $this->db->get_row('SHOW TABLE STATUS WHERE Name LIKE ?', $table);
      if ( !$type || (strtolower($type) === 'index') ){
        $size += $row['Index_length'];
      }
      if ( !$type || (strtolower($type) === 'data') ){
        $size += $row['Data_length'];
      }
    }
    return $size;
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
    //return $this->db->get_one("SELECT replace(uuid(),'-','')");
    $uid = null;
    while ( !bbn\str::is_buid(hex2bin($uid)) ){
      $uid = $this->db->get_one("SELECT replace(uuid(),'-','')");
    }
    return $uid;
  }

  public function create_table($table_name, array $columns, array $keys = null, string $charset = 'utf8', $engine = 'InnoDB')
  {
    $lines = [];
    $sql = '';
    foreach ( $columns as $n => $c ){
      $name = $c['name'] ?? $n;
      if ( isset($c['type']) && bbn\str::check_name($name) ){
        $st = $this->col_simple_name($name, true).' '.$c['type'];
        if ( !empty($c['maxlength']) ){
          $st .= '('.$c['maxlength'].')';
        }
        else if ( !empty($c['values']) && \is_array($c['values']) ){
          $st .= '(';
          foreach ( $c['values'] as $i => $v ){
            $st .= "'".bbn\str::escape_squotes($v)."'";
            if ( $i < count($c['values']) - 1 ){
              $st .= ',';
            }
          }
          $st .= ')';
        }
        if ( (strpos($c['type'], 'int') !== false) && empty($c['signed']) ){
          $st .= ' UNSIGNED';
        }
        if ( empty($c['null']) ){
          $st .= ' NOT NULL';
        }
        if ( isset($c['default']) ){
          $st .= ' DEFAULT '.($c['default'] === 'NULL' ? 'NULL' : "'".bbn\str::escape_squotes($c['default'])."'");
        }
        $lines[] = $st;
      }
    }
    if ( count($lines) ){
      $sql = 'CREATE TABLE '.$this->table_simple_name($table_name, true).' ('.PHP_EOL.implode(','.PHP_EOL, $lines).
        PHP_EOL.') ENGINE='.$engine.' DEFAULT CHARSET='.$charset.';';
    }
    return $sql;
  }
}
