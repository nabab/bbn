<?php
/**
 * @package db
 */
namespace bbn\Db\Languages;

use bbn;
use bbn\Str;
use bbn\X;

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
class Sqlite implements bbn\Db\Engines
{

  private $sqlite_keys_enabled = false;

  /** @var bbn\Db The connection object */
  protected $db;

  /** @var array Allowed operators */
  public static $operators = ['!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between', 'not like'];

    /** @var array Numeric column types */
  public static $numeric_types = ['integer', 'real'];

  /** @var array Time and date column types don't exist in SQLite */
  public static $date_types = [];

  public static $types = [
    'integer',
    'real',
    'text',
    'blob'
  ];

  public static $interoperability = [
    'tinyint' => 'integer',
    'smallint' => 'integer',
    'mediumint' => 'integer',
    'int' => 'integer',
    'bigint' => 'integer',
    'decimal' => 'real',
    'float' => 'real',
    'double' => 'real',
    'bit' => '',
    'char' => '',
    'varchar' => 'text',
    'binary' => 'blob',
    'varbinary' => 'blob',
    'tinyblob' => 'blob',
    'blob' => 'blob',
    'mediumblob' => 'blob',
    'longblob' => 'blob',
    'tinytext' => 'text',
    'text' => 'text',
    'mediumtext' => 'text',
    'longtext' => 'text',
    'enum' => 'text',
    'set' => 'text',
    'date' => 'text',
    'time' => 'text',
    'datetime' => 'text',
    'timestamp' => 'integer',
    'year' => 'integer',
    'json' => 'text'
  ];

  public static $aggr_functions = [
    'AVG',
    'COUNT',
    'GROUP_CONCAT',
    'MAX',
    'MIN',
    'SUM',
  ];

  /** @var string The quote character */
  public $qte = '"';


  /**
   * Returns true if the column name is an aggregate function
   *
   * @param string $f The string to check
   * @return bool
   */
  public static function isAggregateFunction(string $f): bool
  {
    foreach (self::$aggr_functions as $a) {
      if (preg_match('/^'.$a.'\\s*\\(/i', $f)) {
        return true;
      }
    }

    return false;
  }


  /**
   * Constructor
   * @param bbn\Db $db
   */
  public function __construct(bbn\Db $db = null)
  {
    if (!\extension_loaded('pdo_sqlite')) {
      die('The SQLite driver for PDO is not installed...');
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
  public function getConnection(array $cfg = []): ?array
  {
    $cfg['engine'] = 'sqlite';
    if (!isset($cfg['db']) && \defined('BBN_DATABASE')) {
      $cfg['db'] = BBN_DATABASE;
    }

    if (!empty($cfg['db']) && \is_string($cfg['db'])) {
      if (is_file($cfg['db'])) {
        $info        = pathinfo($cfg['db']);
        $cfg['host'] = $info['dirname'].DIRECTORY_SEPARATOR;
        $cfg['db']   = $info['basename'];
      }
      elseif (\defined('BBN_DATA_PATH')
          && is_dir(BBN_DATA_PATH.'db')
          && (strpos($cfg['db'], '/') === false)
      ) {
        $cfg['host'] = BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR;
        if (!is_file(BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR.$cfg['db'])
            && (strpos($cfg['db'], '.') === false)
        ) {
          $cfg['db'] .= '.sqlite';
        }
      }
      else{
        $info = pathinfo($cfg['db']);
        if (is_writable($info['dirname'])) {
          $cfg['host'] = $info['dirname'].DIRECTORY_SEPARATOR;
          $cfg['db']   = isset($info['extension']) ? $info['basename'] : $info['basename'].'.sqlite';
        }
      }

      if (isset($cfg['host'])) {
        $cfg['args'] = ['sqlite:'.$cfg['host'].$cfg['db']];
        $cfg['code_db'] = $cfg['db'];
        $cfg['code_host'] = $cfg['host'];
        $cfg['db']   = 'main';
        return $cfg;
      }
    }

    return null;
  }


  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function postCreation()
  {
    // Obliged to do that  if we want to use foreign keys with SQLite
    $this->enableKeys();
    return;
  }


    /**
   * @param string $db The database name or file
     * @return string | false
     */
  public function change(string $db): bool
  {
    if (strpos($db, '.') === false) {
      $db .= '.sqlite';
    }

    $info = pathinfo($db);
    if (( $info['filename'] !== $this->db->getCurrent() ) && file_exists($this->db->host.$db) && strpos($db, $this->qte) === false) {
      $this->db->rawQuery("ATTACH '".$this->db->host.$db."' AS ".$info['filename']);
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
    $r     = [];
    foreach ($items as $m){
      if (!bbn\Str::checkName($m)) {
        return false;
      }

      $r[] = $this->qte.$m.$this->qte;
    }

    return implode('.', $r);
  }


  /**
     * Returns a table's full name i.e. database.table
     *
     * @param string $table   The table's name (escaped or not)
     * @param bool   $escaped If set to true the returned string will be escaped
     * @return null|string
     */
  public function tableFullName(string $table, bool $escaped = false): ?string
  {
    $bits = explode('.', str_replace($this->qte, '', $table));
    if (\count($bits) === 2) {
      $db    = trim($bits[0]);
      $table = trim($bits[1]);
    }
    else{
      $db    = $this->db->getCurrent();
      $table = trim($bits[0]);
    }

    if (bbn\Str::checkName($db, $table)) {
      if ($db === 'main') {
        return $escaped ? $this->qte.$table.$this->qte : $table;
      }

      return $escaped ? $this->qte.$db.$this->qte.'.'.$this->qte.$table.$this->qte : $db.'.'.$table;
    }

      return null;
  }


    /**
     * Returns a table's simple name i.e. table
     *
     * @param string $table   The table's name (escaped or not)
     * @param bool   $escaped If set to true the returned string will be escaped
     * @return null|string
     */
  public function tableSimpleName(string $table, bool $escaped = false): ?string
  {
    if ($table = trim($table)) {
      $bits  = explode('.', str_replace($this->qte, '', $table));
      $table = end($bits);
      if (bbn\Str::checkName($table)) {
        return $escaped ? $this->qte.$table.$this->qte : $table;
      }
    }

        return false;
  }


    /**
     * Returns a column's full name i.e. table.column
     *
     * @param string      $col     The column's name (escaped or not)
     * @param null|string $table   The table's name (escaped or not)
     * @param bool        $escaped If set to true the returned string will be escaped
     * @return null|string
     */
  public function colFullName(string $col, $table = null, $escaped = false): ?string
  {
    if ($col = trim($col)) {
      $bits = explode('.', str_replace($this->qte, '', $col));
      $ok   = null;
      $col  = array_pop($bits);
      if ($table && ($table = $this->tableSimpleName($table))) {
        $ok = 1;
      }
      elseif (\count($bits)) {
        $table = array_pop($bits);
        $ok    = 1;
      }

      if ((null !== $ok) && bbn\Str::checkName($table, $col)) {
        return $escaped ? '"'.$table.'"."'.$col.'"' : $table.'.'.$col;
      }
    }

        return null;
  }


    /**
     * Returns a column's simple name i.e. column
     *
     * @param string $col     The column's name (escaped or not)
     * @param bool   $escaped If set to true the returned string will be escaped
     * @return null|string
     */
  public function colSimpleName(string $col, bool $escaped=false): ?string
  {
    if ($col = trim($col)) {
      $bits = explode('.', str_replace($this->qte, '', $col));
      $col  = end($bits);
      if (bbn\Str::checkName($col)) {
        return $escaped ? $this->qte.$col.$this->qte : $col;
      }
    }

    return null;
  }


  /**
   * @param string $table
   * @return bool
   */
  public function isTableFullName(string $table): bool
  {
    //return true;
    return strpos($table, '.') ? true : false;
  }


  /**
   * @param string $col
   * @return bool
   */
  public function isColFullName(string $col): bool
  {
    return (bool)strpos($col, '.');
  }


  /**
   * Disable foreign keys check
   *
   * @return bbn\Db
   */
  public function disableKeys(): bbn\Db
  {
    $this->db->rawQuery('PRAGMA foreign_keys = OFF;');
    return $this->db;
  }


  /**
   * Enable foreign keys check
   *
   * @return bbn\Db
   */
  public function enableKeys(): bbn\Db
  {
    $this->db->rawQuery('PRAGMA foreign_keys = ON;');
    return $this->db;
  }


  /**
     * @return null|array
     */
  public function getDatabases(): ?array
  {
    if (!$this->db->check()) {
      return null;
    }

    $x  = [];
    $fs = bbn\File\Dir::scan($this->db->host);
    foreach ($fs as $f){
      if (is_file($f)) {
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
  public function getTables(string $database=''): ?array
  {
    if (!$this->db->check()) {
      return null;
    }

    if (empty($database) || !bbn\Str::checkName($database)) {
        $database = $this->db->getCurrent() === 'main' ? '' : '"'.$this->db->getCurrent().'".';
    }
    elseif ($database === 'main') {
      $database = '';
    }

      $t2 = [];
    if (( $r = $this->db->rawQuery(
      '
      SELECT "tbl_name"
      FROM '.$database.'"sqlite_master"
        WHERE type = \'table\''
    ) )
        && $t1 = $r->fetchAll(\PDO::FETCH_NUM)
    ) {
      foreach ($t1 as $t){
        if (strpos($t[0], 'sqlite') !== 0) {
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
  public function getColumns(string $table): ?array
  {
    if (!$this->db->check()) {
      return null;
    }

    $r = [];
    if ($table = $this->tableFullName($table)) {
      $p = 1;
      if ($rows = $this->db->getRows("PRAGMA table_info($table)")) {
        foreach ($rows as $row){
          $f     = $row['name'];
          $r[$f] = [
            'position' => $p++,
            'null' => $row['notnull'] == 0 ? 1 : 0,
            'key' => $row['pk'] == 1 ? 'PRI' : null,
            'default' => $row['dflt_value'],
            'extra' => null,
            'maxlength' => null,
            'signed' => 1
          ];
          $type  = strtolower($row['type']);
          if (strpos($type, 'blob') !== false) {
            $r[$f]['type'] = 'BLOB';
          }
          elseif (( strpos($type, 'int') !== false ) || ( strpos($type, 'bool') !== false ) || ( strpos($type, 'timestamp') !== false )) {
            $r[$f]['type'] = 'INTEGER';
          }
          elseif (( strpos($type, 'floa') !== false ) || ( strpos($type, 'doub') !== false ) || ( strpos($type, 'real') !== false )) {
            $r[$f]['type'] = 'REAL';
          }
          elseif (( strpos($type, 'char') !== false ) || ( strpos($type, 'text') !== false )) {
            $r[$f]['type'] = 'TEXT';
          }

          if (preg_match_all('/\((.*?)\)/', $row['type'], $matches)) {
            $r[$f]['maxlength'] = (int)$matches[1][0];
          }

          if (!isset($r[$f]['type'])) {
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
  public function getKeys(string $table): ?array
  {
    if (!$this->db->check()) {
      return null;
    }

    $r = [];
    if ($full = $this->tableFullName($table)) {
      $r        = [];
      $keys     = [];
      $cols     = [];
      $database = $this->db->getCurrent() === 'main' ? '' : '"'.$this->db->getCurrent().'".';
      if ($indexes = $this->db->getRows('PRAGMA index_list('.$table.')')) {
        foreach ($indexes as $d){
          if ($fields = $this->db->getRows('PRAGMA index_info('.$database.'"'.$d['name'].'")')) {
            /** @todo Redo, $a is false! */
            foreach ($fields as $d2){
              $a = false;
              if (!isset($keys[$d['name']])) {
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

              if (!isset($cols[$d2['name']])) {
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
   * @param bool  $is_having
   * @return string
   */
  public function getConditions(array $conditions, array $cfg = [], bool $is_having = false, int $indent = 0): string
  {
    $res = '';
    if (isset($conditions['conditions'], $conditions['logic'])) {
      $logic = isset($conditions['logic']) && ($conditions['logic'] === 'OR') ? 'OR' : 'AND';
      foreach ($conditions['conditions'] as $key => $f){
        if (\is_array($f) && isset($f['logic']) && isset($f['conditions'])) {
          if ($tmp = $this->getConditions($f, $cfg, $is_having, $indent + 2)) {
            $res .= (empty($res) ? '(' : PHP_EOL.str_repeat(' ', $indent)."$logic (").
                    $tmp.PHP_EOL.str_repeat(' ', $indent).")";
          }
        }
        elseif (isset($f['operator'], $f['field'])) {
          $field = $f['field'];
          if (!array_key_exists('value', $f)) {
            $f['value'] = false;
          }

          $is_number = false;
          $is_null   = true;
          $is_uid    = false;
          $is_date   = false;
          $model     = null;
          if ($is_having) {
            $res .= PHP_EOL.str_repeat(' ', $indent).(empty($res) ? '' : "$logic ").$field.' ';
          }
          elseif (isset($cfg['available_fields'][$field])) {
            $table  = $cfg['available_fields'][$field];
            $column = $this->colSimpleName($cfg['fields'][$field] ?? $field);
            if ($table && $column && isset($cfg['models'][$table]['fields'][$column])) {
              $model = $cfg['models'][$table]['fields'][$column];
              $res  .= PHP_EOL.str_repeat(' ', $indent).(empty($res) ? '' : "$logic ").
                      (!empty($cfg['available_fields'][$field]) ? $this->colFullName($cfg['fields'][$field] ?? $field, $cfg['available_fields'][$field], true) : $this->colSimpleName($column, true)
                      ).' ';
            }
            else{
              // Remove the alias from where and join but not in having execpt if it's a count
              if (!$is_having && ($table === false) && isset($cfg['fields'][$field])) {
                $field = $cfg['fields'][$field];
                // Same for exp in case it's an alias
                if (!empty($f['exp']) && isset($cfg['fields'][$f['exp']])) {
                  $f['exp'] = $cfg['fields'][$f['exp']];
                }
              }

              $res .= (empty($res) ? '' : PHP_EOL.str_repeat(' ', $indent).$logic.' ').$field.' ';
            }

            if (!empty($model)) {
              $is_null = (bool)$model['null'];
              if ($model['type'] === 'binary') {
                $is_number = true;
                if (($model['maxlength'] === 16) && $model['key']) {
                  $is_uid = true;
                }
              }
              elseif (\in_array($model['type'], self::$numeric_types, true)) {
                $is_number = true;
              }
              elseif (\in_array($model['type'], self::$date_types, true)) {
                $is_date = true;
              }
            }
            elseif ($f['value'] && \bbn\Str::isUid($f['value'])) {
              $is_uid = true;
            }
            elseif (is_int($f['value']) || is_float($f['value'])) {
              $is_number = true;
            }
          }
          else{
            $res .= (empty($res) ? '' : PHP_EOL.str_repeat(' ', $indent).$logic.' ').$field.' ';
          }

          switch (strtolower($f['operator'])){
            case '=':
              if (isset($f['exp'])) {
                $res .= '= '.$f['exp'];
              }
              else {
                $res .= '= ?';
              }
              break;
            case '!=':
              if (isset($f['exp'])) {
                $res .= '!= '.$f['exp'];
              }
              else {
                $res .= '!= ?';
              }
              break;
            case 'like':
              if (isset($f['exp'])) {
                $res .= 'LIKE '.$f['exp'];
              }
              else {
                $res .= 'LIKE ?';
              }
              break;
            case 'not like':
              if (isset($f['exp'])) {
                $res .= 'NOT LIKE '.$f['exp'];
              }
              else {
                $res .= 'NOT LIKE ?';
              }
              break;
            case 'eq':
            case 'is':
              if (isset($f['exp'])) {
                $res .= '= '.$f['exp'];
              }
              elseif ($is_uid || $is_number) {
                $res .= '= ?';
              }
              else{
                $res .= 'LIKE ?';
              }
              break;
            case 'neq':
            case 'isnot':
              if (isset($f['exp'])) {
                $res .= '!= '.$f['exp'];
              }
              elseif ($is_uid || $is_number) {
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
              if (isset($f['exp'])) {
                $res .= '>= '.$f['exp'];
              }
              else{
                $res .= '>= ?';
              }
              break;

            case 'gt':
            case '>':
              if (isset($f['exp'])) {
                $res .= '> '.$f['exp'];
              }
              else{
                $res .= '> ?';
              }
              break;

            case 'lte':
            case '<=':
              if (isset($f['exp'])) {
                $res .= '<= '.$f['exp'];
              }
              else{
                $res .= '<= ?';
              }
              break;

            case 'lt':
            case '<':
              if (isset($f['exp'])) {
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
              $res .= '= ?';
              break;
          }
        }
      }
    }

    if (!empty($res)) {
      return str_replace(PHP_EOL.PHP_EOL, PHP_EOL, $res.PHP_EOL);
    }

    return $res;
  }


  /**
   * Generates a string starting with SELECT ... FROM with corresponding parameters
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function getSelect(array $cfg): string
  {
    // 22/06/2020 imported from mysql.php by Mirko
    $res = '';
    if (\is_array($cfg['tables']) && !empty($cfg['tables'])) {
      $res = 'SELECT ';
      if (!empty($cfg['count'])) {
        if ($cfg['group_by']) {
          $indexes = [];
          $idxs    = [];
          foreach ($cfg['group_by'] as $g){
            // Alias
            if (isset($cfg['fields'][$g])) {
              $g = $cfg['fields'][$g];
            }

            if (($t = $cfg['available_fields'][$g])
                && ($cfn = $this->colFullName($g, $t))
            ) {
              $indexes[] = $cfn;
              //$idxs[] = $this->colSimpleName($g, true);
              // Changed by Mirko
              $idxs[] = $this->colSimpleName($cfg['aliases'][$g] ?? $g, true);
            }
            else {
              $indexes[] = $g;
              $idxs[]    = $g;
            }
          }

          if (!empty($cfg['having'])) {
            if (count($indexes) === count($cfg['group_by'])) {
              $res .= 'COUNT(*) FROM ( SELECT ';
              $tmp  = [];
              if ($extracted_fields = $this->db->extractFields($cfg, $cfg['having']['conditions'])) {
                //die(var_dump($extracted_fields));
                foreach ($extracted_fields as $ef) {
                  if (!in_array($ef, $indexes)) {
                    if (!empty($cfg['fields'][$ef])) {
                      $tmp[$ef] = $cfg['fields'][$ef];
                    }
                    else {
                      $tmp[] = $ef;
                    }
                  }
                }
              }

              $cfg['fields'] = $indexes;
              foreach ($tmp as $k => $v) {
                if (is_string($k)) {
                  $cfg['fields'][$k] = $v;
                }
                else {
                  $cfg['fields'][] = $v;
                }
              }
            }
            else{
              $res .= 'COUNT(*) FROM ( SELECT ';
            }
          }
          else{
            if (count($indexes) === count($cfg['group_by'])) {
              $res .= 'COUNT(*) FROM ( SELECT ';
              //$cfg['fields'] = $indexes;
              // Changed by Mirko
              $cfg['fields'] = array_combine($idxs, $indexes);
            }
            else{
              $res .= 'COUNT(*) FROM ( SELECT ';
            }
          }
        }
        else{
          $res          .= 'COUNT(*)';
          $cfg['fields'] = [];
        }
      }

      if (!empty($cfg['fields'])) {
        $fields_to_put = [];
        // Checking the selected fields
        foreach ($cfg['fields'] as $alias => $f){
          $is_distinct = false;
          $f           = trim($f);
          $bits        = explode(' ', $f);
          if ((count($bits) > 1) && (strtolower($bits[0]) === 'distinct')) {
            $is_distinct = true;
            array_shift($bits);
            $f = implode(' ', $bits);
          }

          // Adding the alias in $fields
          if (strpos($f, '(')) {
            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '').$f.(\is_string($alias) ? ' AS '.$this->escape($alias) : '');
          }
          elseif (array_key_exists($f, $cfg['available_fields'])) {
            $idx    = $cfg['available_fields'][$f];
            $csn    = $this->colSimpleName($f);
            $is_uid = false;
            //die(var_dump($idx, $f, $tables[$idx]));
            if (($idx !== false) && isset($cfg['models'][$idx]['fields'][$csn])) {
              $column = $cfg['models'][$idx]['fields'][$csn];
              if (($column['type'] === 'binary') && ($column['maxlength'] === 16)) {
                $is_uid = true;
                if (!\is_string($alias)) {
                  $alias = $csn;
                }
              }
            }

            //$res['fields'][$alias] = $this->cfn($f, $fields[$f]);
            if ($is_uid) {
              $st = 'LOWER(HEX('.$this->colFullName($csn, $cfg['available_fields'][$f], true).'))';
            }
            // For JSON fields
            elseif ($cfg['available_fields'][$f] === false) {
              $st = $f;
            }
            else{
              $st = $this->colFullName($csn, $cfg['available_fields'][$f], true);
            }

            if (\is_string($alias)) {
              $st .= ' AS '.$this->escape($alias);
            }

            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '').$st;
          }
          elseif (isset($cfg['available_fields'][$f]) && ($cfg['available_fields'][$f] === false)) {
            $this->db->error("Error! The column '$f' exists on several tables in '".implode(', ', $cfg['tables']));
          }
          else{
            $this->db->error("Error! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']));
          }
        }

        $res .= implode(', ', $fields_to_put);
      }

      $res          .= PHP_EOL;
      $tables_to_put = [];
      foreach ($cfg['tables'] as $alias => $tfn){
        $st = $this->tableFullName($tfn, true);
        if ($alias !== $tfn) {
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
  public function getInsert(array $cfg): string
  {
    $fields_to_put = [
      'values' => [],
      'fields' => []
    ];
    $i             = 0;
    foreach ($cfg['fields'] as $alias => $f){
      if (isset($cfg['available_fields'][$f], $cfg['models'][$cfg['available_fields'][$f]])) {
        $model  = $cfg['models'][$cfg['available_fields'][$f]];
        $csn    = $this->colSimpleName($f);
        $is_uid = false;
        if (isset($model['fields'][$csn])) {
          $column = $model['fields'][$csn];
          if (($column['type'] === 'binary') && ($column['maxlength'] === 16)) {
            $is_uid = true;
          }

          $fields_to_put['fields'][] = $this->colSimpleName($f, true);
          $fields_to_put['values'][] = $is_uid && (!$column['null'] || (null !== $cfg['values'][$i])) ? 'UNHEX(?)' : '?';
        }
      }
      else{
        $this->db->error("Error! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']));
      }

      $i++;
    }

    if (count($fields_to_put['fields']) && (count($cfg['tables']) === 1)) {
      return 'INSERT '.($cfg['ignore'] ? 'IGNORE ' : '').'INTO '.$this->tableSimpleName(current($cfg['tables']), true).PHP_EOL.
        '('.implode(', ', $fields_to_put['fields']).')'.PHP_EOL.' VALUES ('.
        implode(', ', $fields_to_put['values']).')'.PHP_EOL;
    }

    return '';
  }


  /**
   * @param array $cfg The configuration array
   * @return string
   */
  public function getUpdate(array $cfg): string
  {
    $res           = '';
    $fields_to_put = [
      'values' => [],
      'fields' => []
    ];
    foreach ($cfg['fields'] as $alias => $f){
      if (isset($cfg['available_fields'][$f], $cfg['models'][$cfg['available_fields'][$f]])) {
        $model  = $cfg['models'][$cfg['available_fields'][$f]];
        $csn    = $this->colSimpleName($f);
        $is_uid = false;
        if (isset($model['fields'][$csn])) {
          $column = $model['fields'][$csn];
          if (($column['type'] === 'binary') && ($column['maxlength'] === 16)) {
            $is_uid = true;
          }

          $fields_to_put['fields'][] = $this->colSimpleName($f, true);
          $fields_to_put['values'][] = $is_uid ? 'UNHEX(?)' : '?';
        }
      }
      else{
        $this->db->error("Error! The column '$f' doesn't exist in '".implode(', ', $cfg['tables']));
      }
    }

    if (count($fields_to_put['fields'])) {
      $res .= 'UPDATE '.($cfg['ignore'] ? 'IGNORE ' : '').$this->tableSimpleName(current($cfg['tables']), true).' SET ';
      $last = count($fields_to_put['fields']) - 1;
      foreach ($fields_to_put['fields'] as $i => $f){
        $res .= $f.' = '.$fields_to_put['values'][$i];
        if ($i < $last) {
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
   * X::dump($db->getDelete('table_users',['id'=>1]));
   * // (string) DELETE FROM `db_example`.`table_users` * WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function getDelete(array $cfg): string
  {
    $res = '';
    if (count($cfg['tables']) === 1) {
      $res = 'DELETE '.( $cfg['ignore'] ? 'IGNORE ' : '' ).
        'FROM '.$this->tableFullName(current($cfg['tables']), true).PHP_EOL;
    }

    return $res;
  }


  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function getJoin(array $cfg): string
  {
    $res = '';
    if (!empty($cfg['join'])) {
      foreach ($cfg['join'] as $join){
        if (isset($join['table'], $join['on']) && ($cond = $this->db->getConditions($join['on'], $cfg, false, 4))) {
          $res .= '  '.
            (isset($join['type']) && (strtolower($join['type']) === 'left') ? 'LEFT ' : '').
            'JOIN '.$this->tableFullName($join['table'],true).
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
  public function getWhere(array $cfg): string
  {
    $res = $this->getConditions($cfg['filters'] ?? [], $cfg);
    if (!empty($res)) {
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
  public function getGroupBy(array $cfg): string
  {
    $res          = '';
    $group_to_put = [];
    if (!empty($cfg['group_by'])) {
      foreach ($cfg['group_by'] as $g){
        if (isset($cfg['available_fields'][$g])) {
          $group_to_put[] = $this->escape($g);
          /*
          if ( isset($cfg['available_fields'][$this->isColFullName($g) ? $this->colFullName($g) : $this->colSimpleName($g)]) ){
          $group_to_put[] = $this->escape($g);
          //$group_to_put[] = $this->colFullName($g, $cfg['available_fields'][$g], true);
          */
        }
        else{
          $this->db->error("Error! The column '$g' doesn't exist for group by ".print_r($cfg, true));
        }
      }

      if (count($group_to_put)) {
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
  public function getHaving(array $cfg): string
  {
    $res = '';
    if (!empty($cfg['group_by']) && !empty($cfg['having']) && ($cond = $this->getConditions($cfg['having'], $cfg, !$cfg['count'], 2))) {
      $res .= '  HAVING '.$cond.PHP_EOL;
    }

    return $res;
  }


  /**
   * @param array $cfg
   * @return string
   */
  public function getOrder(array $cfg): string
  {
    $res = '';
    if (!empty($cfg['order'])) {
      foreach ($cfg['order'] as $col => $dir){
        if (\is_array($dir) && isset($dir['field'], $cfg['available_fields'][$dir['field']])) {
          $res .= $this->escape($dir['field']).' COLLATE NOCASE '.
            (!empty($dir['dir']) && strtolower($dir['dir']) === 'desc' ? 'DESC' : 'ASC' ).','.PHP_EOL;
        }
        elseif (isset($cfg['available_fields'][$col])) {
          $res .= $this->escape($col).' COLLATE NOCASE '.
            (strtolower($dir) === 'desc' ? 'DESC' : 'ASC' ).','.PHP_EOL;
        }
      }

      if (!empty($res)) {
        return 'ORDER BY '.substr($res,0, Strrpos($res,',')).PHP_EOL;
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
  public function getLimit(array $cfg): string
  {
    $res = '';
    if (!empty($cfg['limit']) && bbn\Str::isInteger($cfg['limit'])) {
      $res .= 'LIMIT '.(!empty($cfg['start']) && bbn\Str::isInteger($cfg['start']) ? (string)$cfg['start'] : '0').', '.$cfg['limit'];
    }

    return $res;
  }


  /**
   * @param null|string $table The table for which to create the statement
   * @return string
   */
  public function getRawCreate(string $table): string
  {
    if (($table = $this->tableFullName($table, true))
        && ($r = $this->db->rawQuery("SHOW CREATE TABLE $table"))
    ) {
      return $r->fetch(\PDO::FETCH_ASSOC)['Create Table'];
    }

    return '';
  }


  public function getCreateTable(string $table, array $model = null): string
  {
    if (!$model) {
      $model = $this->db->modelize($table);
    }

    $st   = 'CREATE TABLE ' . $this->db->escape($table) . ' (' . PHP_EOL;
    $done = false;
    foreach ($model['fields'] as $name => $col) {
      if (!$done) {
        $done = true;
      }
      else {
        $st .= ',' . PHP_EOL;
      }

      $st .= '  ' . $this->db->escape($name) . ' ';
      if (!in_array($col['type'], self::$types)) {
        if (isset(self::$interoperability[$col['type']])) {
          $st .= self::$interoperability[$col['type']];
        }
        // No error: no type is fine
      }
      else {
        $st .= $col['type'];
      }

      if (empty($col['null'])) {
        $st .= ' NOT NULL';
      }

      if (array_key_exists('default', $col)) {
        $st .= ' DEFAULT ';
        if (($col['default'] === 'NULL')
            || bbn\Str::isNumber($col['default'])
            || strpos($col['default'], '(')
            || in_array(strtoupper($col['default']), ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'])
        ) {
          $st .= (string)$col['default'];
        }
        else {
          $st .= "'" . bbn\Str::escapeSquotes($col['default']) . "'";
        }
      }
    }

    if (isset($model['keys']['PRIMARY'])) {
      $db  = &$this->db;
      $st .= ','.PHP_EOL.'  PRIMARY KEY ('.X::join(
        array_map(
          function ($a) use ($db) {
            return $db->escape($a);
          },
          $model['keys']['PRIMARY']['columns']
        ),
        ', '
      ).')';
    }

    $st .= PHP_EOL . ')';
    return $st;
  }


  public function getCreateKeys(string $table, array $model = null)
  {
    $st = '';
    if (!$model) {
      $model = $this->db->modelize($table);
    }

    if ($model && !empty($model['keys'])) {
      $last  = count($model['keys']) - 1;
      $dbcls = &$this->db;
      foreach ($model['keys'] as $name => $key) {
        if ($name === 'PRIMARY') {
          continue;
        }

        $st .= 'CREATE ';
        if (!empty($key['unique'])) {
          $st .= 'UNIQUE ';
        }

        $st .= 'INDEX \''.Str::escapeSquotes($name).'\' ON ' . $this->db->escape($table);
        $db  = &$this->db;
        $st .= ' ('.X::join(
          array_map(
            function ($a) use ($db) {
              return $db->escape($a);
            },
            $key['columns']
          ),
          ', '
        ).')';
        $st .= ';' . PHP_EOL;
      }
    }

    return $st;
  }


  /**
   * Returns the comment (or an empty string if none) for a given table.
   *
   * @param string $table The table's name
   *
   * @return string The table's comment
   */
  public function getTableComment(string $table): string
  {
    return '';
  }


  /**
   * Renames the given table to the new given name.
   * 
   * @param string $table   The current table's name
   * @param string $newName The new name.
   * @return bool  True if it succeeded
   */
  public function renameTable(string $table, string $newName): bool
  {
    if ($this->db->check() && Str::checkName($table, $newName)) {
      $t1 = strpos($table, '.') ? $this->tableFullName($table, true) : $this->tableSimpleName($table, true);
      $t2 = strpos($newName, '.') ? $this->tableFullName($newName, true) : $this->tableSimpleName($newName, true);
      $res = $this->db->query(sprintf("ALTER TABLE %s RENAME TO %s", $table, $newName));
      return !!$res;
    }

    return false;
  }


  /**
   * @param null|string $table The table for which to create the statement
   * @return string
     */
  public function getCreate(string $table, array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->db->modelize($table);
    }

    if ($st = $this->getCreateTable($table, $model)) {
      $st .= ';'.PHP_EOL . $this->getCreateKeys($table, $model);
    }

    return $st;
  }


  /**
   * Creates an index
   *
   * @param null|string  $table
   * @param string|array $column
   * @param bool         $unique
   * @param null         $length
   * @return bool
   */
  public function createIndex(string $table, $column, bool $unique = false, $length = null, $order = null): bool
  {
    if (!\is_array($column)) {
      $column = [$column];
    }

    if (!\is_null($length)) {
      if (!\is_array($length)) {
        $length = [$length];
      }
    }

    $name = bbn\Str::encodeFilename($table);
    foreach ($column as $i => $c){
      if (!bbn\Str::checkName($c)) {
        $this->db->error("Illegal column $c");
      }

      $name      .= '_'.$c;
      $column[$i] = '`'.$column[$i].'`';
      if (\is_int($length[$i]) && $length[$i] > 0) {
        $column[$i] .= '('.$length[$i].')';
      }
    }

    $name = bbn\Str::cut($name, 50);
    if ($table = $this->tableFullName($table, 1)) {
      $query = 'CREATE '.( $unique ? 'UNIQUE ' : '' )."INDEX `$name` ON $table ( ".implode(', ', $column);
      if (($order === "ASC") || ($order === "DESC")) {
        $query .= ' '. $order .' );';
      }
      else{
        $query .= ' );';
      }

      X::log(['index', $query],'vito');
      return (bool)$this->db->rawQuery($query);
    }

        return false;
  }


  /**
   * Deletes an index
   *
   * @param null|string $table
   * @param string      $key
   * @return bool
   */
  public function deleteIndex(string $table, string $key): bool
  {
    if (( $table = $this->tableFullName($table, 1) ) && bbn\Str::checkName($key)) {
      //changed the row above because if the table has no rows query() returns 0
      //return (bool)$this->db->query("ALTER TABLE $table DROP INDEX `$key`");
      return $this->db->query('DROP INDEX IF EXISTS '.$key) !== false;
    }

        return false;
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @return bool
   */


  public function createDatabase(string $database): bool
  {
    if (bbn\Str::checkFilename($database)) {
      if(empty(strpos($database, '.sqlite'))) {
        $database = $database.'.sqlite';
      }

      if(empty(file_exists($this->db->host.$database))) {
        fopen($this->db->host.$database, 'w');
        return file_exists($this->db->host.$database);
      }
    }

    return false;
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   */
  public function dropDatabase(string $database): bool
  {
    if (bbn\Str::checkFilename($database)) {
      if(empty(strpos($database, '.sqlite'))) {
        $database = $database.'.sqlite';
      }

      if(file_exists($this->db->host.$database)) {
        unlink($this->db->host.$database);
        return file_exists($this->db->host.$database);
      }
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
  public function createUser(string $user = null, string $pass = null, string $db = null): bool
  {
    return true;
  }


  /**
   * Deletes a database user
   *
   * @param string $user
   * @return bool
   */
  public function deleteUser(string $user = null): bool
  {
    return true;
  }


  /**
   * @param string $user
   * @param string $host
   * @return array|null
   */
  public function getUsers(string $user = '', string $host = ''): ?array
  {
    return [];
  }


  public function dbSize(string $database = '', string $type = ''): int
  {
    return @filesize($database) ?: 0;
  }


  public function tableSize(string $table, string $type = ''): int
  {
    return 0;
  }


  public function status(string $table = '', string $database = '')
  {
    $cur = null;
    if ($database && ($this->db->getCurrent() !== $database)) {
      $cur = $this->db->getCurrent();
      $this->db->change($database);
    }

    //$r = $this->db->getRow('SHOW TABLE STATUS WHERE Name LIKE ?', $table);
    $r = $this->db->getRow('SELECT * FROM dbstat WHERE Name LIKE ?', $table);
    if (null !== $cur) {
      $this->db->change($cur);
    }

    return $r;
  }


  public function getUid(): string
  {
    return bbn\X::makeUid();
  }


  public function createTable($table_name, array $columns, array $keys = null, bool $with_constraints = false, string $charset = 'UTF-8')
  {
    $lines = [];
    $sql   = '';
    foreach ($columns as $n => $c){
      $name = $c['name'] ?? $n;
      if (isset($c['type']) && bbn\Str::checkName($name)) {
        $st = $this->colSimpleName($name, true).' '.$c['type'];
        if (!empty($c['maxlength'])) {
          $st .= '('.$c['maxlength'].')';
        }
        elseif (!empty($c['values']) && \is_array($c['values'])) {
          $st .= '(';
          foreach ($c['values'] as $i => $v){
            $st .= "'".bbn\Str::escapeSquotes($v)."'";
            if ($i < count($c['values']) - 1) {
              $st .= ',';
            }
          }

          $st .= ')';
        }

        if ((strpos($c['type'], 'int') !== false) && empty($c['signed'])) {
          $st .= ' UNSIGNED';
        }

        if (empty($c['null'])) {
          $st .= ' NOT NULL';
        }

        if (isset($c['default'])) {
          $st .= ' DEFAULT '.($c['default'] === 'NULL' ? 'NULL' : "'".bbn\Str::escapeSquotes($c['default'])."'");
        }

        $lines[] = $st;
      }
    }

    if (count($lines)) {
      $sql = 'CREATE TABLE '.$this->tableSimpleName($table_name, false).' ('.PHP_EOL.implode(','.PHP_EOL, $lines).
        PHP_EOL.'); PRAGMA encoding='.$this->qte.$charset.$this->qte.';';
    }

    return $sql;
  }


  public function createTableSqlite($table_name, array $columns, array $keys = null, bool $with_constraints = false, string $charset = 'UTF-8')
  {
    $str = $this->createTable($table_name, $columns, $keys, $with_constraints, $charset);
    if ($str !== '') {
      return (bool)$this->db->rawQuery($str);
    }

    return false;
  }


  public function getCreateConstraints(string $table, array $model = null): string
  {
    $st = '';
    if (!empty($model)) {
      if ($last = count($model)) {
        $st .= 'ALTER TABLE '.$this->db->escape($table).PHP_EOL;
        $i   = 0;

        if (!is_array($model[0])) {
          $constraints[] = $model;
        }
        else{
          $constraints = $model;
        }

        foreach ($constraints as $name => $key) {
          X::log($key, 'vito');
          $i++;
          $st .= '  ADD '.
            'CONSTRAINT '.$this->db->escape($key['constraint']).
            ($key['foreign_key'] ? ' FOREIGN KEY ('.$this->db->escape($key['columns'][0]).') ' : '').
            ($key['unique'] ? ' UNIQUE ('.$this->db->escape($key['ref_table'].'_'.$key['columns'][0]).') ' : '').
            ($key['primary_key'] ? ' PRIMARY KEY ('.$this->db->escape($key['ref_table'].'_'.$key['columns'][0]).') ' : '').
            ' FOREIGN KEY ('.$this->db->escape($key['columns'][0]).') '.
            'REFERENCES '.$this->db->escape($table).'('.$this->db->escape($key['columns'][0]).') '.
            ($key['delete'] ? ' ON DELETE '.$key['delete'] : '').
            ($key['update'] ? ' ON UPDATE '.$key['update'] : '').
            ($i === $last ? ';' : ','.PHP_EOL);
        }
      }
    }

    return $st;
  }


  public function createConstraintsSqlite(string $table, array $model = null): bool
  {
    $str = $this->getCreateConstraints($table,  $model);
    if ($str !== '') {
      return (bool)$this->db->rawQuery($str);
    }

    return false;
  }


}
