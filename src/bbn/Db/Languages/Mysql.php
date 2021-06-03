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
class Mysql implements bbn\Db\Engines
{

  /** @var bbn\Db The connection object */
  protected $db;

  /** @var array Allowed operators */
  public static $operators = ['!=', '=', '<>', '<', '<=', '>', '>=', 'like', 'clike', 'slike', 'not', 'is', 'is not', 'in', 'between', 'not like'];

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
    'json',
  ];

  public static $interoperability = [
    'integer' => 'int',
    'real' => 'decimal',
    'text' => 'text',
    'blob' => 'blob'
  ];

  public static $aggr_functions = [
    'AVG',
    'BIT_AND',
    'BIT_OR',
    'COUNT',
    'GROUP_CONCAT',
    'MAX',
    'MIN',
    'STD',
    'STDDEV_POP',
    'STDDEV_SAMP',
    'STDDEV',
    'SUM',
    'VAR_POP',
    'VAR_SAMP',
    'VARIANCE',
  ];

  /** @var string The quote character */
  public $qte = '`';


  /**
   * Returns true if the column name is an aggregate function
   *
   * @param string $f The string to check
   * @return bool
   */
  public static function isAggregateFunction(string $f): bool
  {
    foreach (self::$aggr_functions as $a) {
      if (preg_match('/' . $a . '\\s*\\(/i', $f)) {
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
    if (!\extension_loaded('pdo_mysql')) {
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
  public function getConnection(array $cfg = []): array
  {
    if (!X::hasProps($cfg, ['host', 'user'])) {
      if (!defined('BBN_DB_HOST')) {
        die("No DB host defined");
      }

      $cfg = [
        'host' => BBN_DB_HOST,
        'user' => defined('BBN_DB_USER') ? BBN_DB_USER : '',
        'pass' => defined('BBN_DB_PASS') ? BBN_DB_PASS : '',
        'db' => defined('BBN_DATABASE') ? BBN_DATABASE : '',
      ];
    }

    $cfg['engine'] = 'mysql';
    if (empty($cfg['host'])) {
      $cfg['host'] = '127.0.0.1';
    }

    if (empty($cfg['user'])) {
      $cfg['user'] = 'root';
    }

    if (!isset($cfg['pass'])) {
      $cfg['pass'] = '';
    }

    if (empty($cfg['port']) || !is_int($cfg['port'])) {
      $cfg['port'] = 3306;
    }

    $cfg['code_db']   = $cfg['db'] ?? '';
    $cfg['code_host'] = $cfg['user'].'@'.$cfg['host'];
    $cfg['args']      = ['mysql:host='
        .(in_array($cfg['host'], ['localhost', '127.0.0.1']) ? gethostname() : $cfg['host'])
        .';port='.$cfg['port']
        .(empty($cfg['db']) ? '' : ';dbname=' . $cfg['db']),
      $cfg['user'],
      $cfg['pass'],
      [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'],
    ];
    return $cfg;
  }


  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function postCreation()
  {
    return;
  }


  /**
   * Changes the current database to the given one.
   * @param string $db The database name or file
   * @return bool
   */
  public function change(string $db): bool
  {
    if (($this->db->getCurrent() !== $db) && bbn\Str::checkName($db)) {
      $this->db->rawQuery("USE `$db`");
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
    foreach ($items as $m) {
      if (!bbn\Str::checkName($m)) {
        throw new \Exception(X::_("Illegal name %s for the column", $m));
      }

      $r[] = $this->qte . $m . $this->qte;
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
    $bits = explode('.', $table);
    if (\count($bits) === 3) {
      $db    = trim($bits[0], ' ' . $this->qte);
      $table = trim($bits[1]);
    } elseif (\count($bits) === 2) {
      $db    = trim($bits[0], ' ' . $this->qte);
      $table = trim($bits[1], ' ' . $this->qte);
    } else {
      $db    = $this->db->getCurrent();
      $table = trim($bits[0], ' ' . $this->qte);
    }

    if (bbn\Str::checkName($db, $table)) {
      return $escaped ? $this->qte . $db . $this->qte . '.' . $this->qte . $table . $this->qte : $db . '.' . $table;
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
      $bits = explode('.', $table);
      switch (\count($bits)) {
        case 1:
          $table = trim($bits[0], ' ' . $this->qte);
          break;
        case 2:
          $table = trim($bits[1], ' ' . $this->qte);
          break;
        case 3:
          $table = trim($bits[1], ' ' . $this->qte);
          break;
      }

      if (bbn\Str::checkName($table)) {
        return $escaped ? $this->qte . $table . $this->qte : $table;
      }
    }

    return null;
  }


  /**
   * Returns a column's full name i.e. table.column
   *
   * @param string      $col     The column's name (escaped or not)
   * @param null|string $table   The table's name (escaped or not)
   * @param bool        $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function colFullName(string $col, $table = null, $escaped = false): ?string
  {
    if ($col = trim($col)) {
      $bits = explode('.', $col);
      $ok   = null;
      $col  = trim(array_pop($bits), ' ' . $this->qte);
      if ($table && ($table = $this->tableSimpleName($table))) {
        $ok = 1;
      } elseif (\count($bits)) {
        $table = trim(array_pop($bits), ' ' . $this->qte);
        $ok    = 1;
      }

      if ((null !== $ok) && bbn\Str::checkName($table, $col)) {
        return $escaped ? $this->qte . $table . $this->qte . '.' . $this->qte . $col . $this->qte : $table . '.' . $col;
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
  public function colSimpleName(string $col, bool $escaped = false): ?string
  {
    if ($bits = explode('.', $col)) {
      $col = trim(end($bits), ' ' . $this->qte);
      if (bbn\Str::checkName($col)) {
        return $escaped ? $this->qte . $col . $this->qte : $col;
      }
    }

    return null;
  }


  /**
   * Returns true if the given string is the full name of a table ('database.table').
   * @param string $table
   * @return bool
   */
  public function isTableFullName(string $table): bool
  {
    return strpos($table, '.') ? true : false;
  }


  /**
   * Returns true if the given string is the full name of a column ('table.column').
   * @param string $col
   * @return bool
   */
  public function isColFullName(string $col): bool
  {
    return (bool)strpos($col, '.');
  }


  /**
   * Disables foreign keys check.
   *
   * @return bbn\Db
   */
  public function disableKeys(): bbn\Db
  {
    $this->db->rawQuery('SET FOREIGN_KEY_CHECKS=0;');
    return $this->db;
  }


  /**
   * Enables foreign keys check.
   *
   * @return bbn\Db
   */
  public function enableKeys(): bbn\Db
  {
    $this->db->rawQuery('SET FOREIGN_KEY_CHECKS=1;');
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

    $x = [];
    if ($r = $this->db->rawQuery('SHOW DATABASES')) {
      $x = array_map(
        function ($a) {
          return $a['Database'];
        }, array_filter(
          $r->fetchAll(\PDO::FETCH_ASSOC), function ($a) {
            return ($a['Database'] === 'information_schema') || ($a['Database'] === 'mysql') ? false : 1;
          }
        )
      );
      sort($x);
    }

    return $x;
  }


  /**
   * @param string $database Database name
   * @return null|array
   */
  public function getTables(string $database = ''): ?array
  {
    if (!$this->db->check()) {
      return null;
    }

    if (empty($database) || !bbn\Str::checkName($database)) {
      $database = $this->db->getCurrent();
    }

    $t2 = [];
    if (($r = $this->db->rawQuery("SHOW TABLES FROM `$database`"))
        && ($t1 = $r->fetchAll(\PDO::FETCH_NUM))
    ) {
      foreach ($t1 as $t) {
        $t2[] = $t[0];
      }
    }

    return $t2;
  }


  /**
   * Returns the columns' configuration of the given table.
   * @param null|string $table The table's name
   * @return null|array
   */
  public function getColumns(string $table): ?array
  {
    if (!$this->db->check()) {
      return null;
    }

    $r = [];
    if ($full = $this->tableFullName($table)) {
      $t            = explode('.', $full);
      [$db, $table] = $t;
      $sql          = <<<MYSQL
        SELECT *
        FROM `information_schema`.`COLUMNS`
        WHERE `TABLE_NAME` LIKE ?
        AND `TABLE_SCHEMA` LIKE ?
        ORDER BY `ORDINAL_POSITION` ASC
MYSQL;
      if ($rows = $this->db->getRows($sql, $table, $db)) {
        $p = 1;
        foreach ($rows as $row) {
          $f          = $row['COLUMN_NAME'];
          $has_length = (stripos($row['DATA_TYPE'], 'text') === false)
            && (stripos($row['DATA_TYPE'], 'blob') === false)
            && ($row['EXTRA'] !== 'VIRTUAL GENERATED');
          $r[$f]      = [
            'position' => $p++,
            'type' => $row['DATA_TYPE'],
            'null' => $row['IS_NULLABLE'] === 'NO' ? 0 : 1,
            'key' => \in_array($row['COLUMN_KEY'], ['PRI', 'UNI', 'MUL']) ? $row['COLUMN_KEY'] : null,
            'extra' => $row['EXTRA'],
            'signed' => strpos($row['COLUMN_TYPE'], ' unsigned') === false,
            'virtual' => $row['EXTRA'] === 'VIRTUAL GENERATED',
            'generation' => $row['GENERATION_EXPRESSION'],
          ];
          if (($row['COLUMN_DEFAULT'] !== null) || ($row['IS_NULLABLE'] === 'YES')) {
            $r[$f]['default'] = \is_null($row['COLUMN_DEFAULT']) ? 'NULL' : $row['COLUMN_DEFAULT'];
          }

          if (($r[$f]['type'] === 'enum') || ($r[$f]['type'] === 'set')) {
            if (preg_match_all('/\((.*?)\)/', $row['COLUMN_TYPE'], $matches)
                && !empty($matches[1])
                && \is_string($matches[1][0])
                && ($matches[1][0][0] === "'")
            ) {
              $r[$f]['values'] = explode("','", substr($matches[1][0], 1, -1));
              $r[$f]['extra']  = $matches[1][0];
            } else {
              $r[$f]['values'] = [];
            }
          } elseif (preg_match_all('/\((\d+)?(?:,)|(\d+)\)/', $row['COLUMN_TYPE'], $matches)) {
            if (empty($matches[1][0])) {
              if (!empty($matches[2][0])) {
                $r[$f]['maxlength'] = (int)$matches[2][0];
              }
            } else {
              $r[$f]['maxlength'] = (int)$matches[1][0];
              $r[$f]['decimals']  = (int)$matches[2][1];
            }
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
   * Returns the keys of the given table.
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
      $t            = explode('.', $full);
      [$db, $table] = $t;
      $r            = [];
      $indexes      = $this->db->getRows('SHOW INDEX FROM ' . $this->tableFullName($full, 1));
      $keys         = [];
      $cols         = [];
      foreach ($indexes as $i => $index) {
        $a = $this->db->getRow(
          <<<MYSQL
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
          $index['Seq_in_index']
        );
        if ($a) {
          $b = $this->db->getRow(
            <<<MYSQL
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
            $table
          );
        } elseif (isset($b)) {
          unset($b);
        }

        if (!isset($keys[$index['Key_name']])) {
          $keys[$index['Key_name']] = [
            'columns' => [$index['Column_name']],
            'ref_db' => isset($a, $a['ref_db']) ? $a['ref_db'] : null,
            'ref_table' => isset($a, $a['ref_table']) ? $a['ref_table'] : null,
            'ref_column' => isset($a, $a['ref_column']) ? $a['ref_column'] : null,
            'constraint' => isset($b, $b['name']) ? $b['name'] : null,
            'update' => isset($b, $b['update']) ? $b['update'] : null,
            'delete' => isset($b, $b['delete']) ? $b['delete'] : null,
            'unique' => $index['Non_unique'] ? 0 : 1,
          ];
        } else {
          $keys[$index['Key_name']]['columns'][] = $index['Column_name'];
          $keys[$index['Key_name']]['ref_db']    = $keys[$index['Key_name']]['ref_table'] = $keys[$index['Key_name']]['ref_column'] = null;
        }

        if (!isset($cols[$index['Column_name']])) {
          $cols[$index['Column_name']] = [$index['Key_name']];
        } else {
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
   * @param bool  $is_having
   * @return string
   */
  public function getConditions(array $conditions, array $cfg = [], bool $is_having = false, int $indent = 0): string
  {
    $res = '';
    if (isset($conditions['conditions'], $conditions['logic'])) {
      $logic = isset($conditions['logic']) && ($conditions['logic'] === 'OR') ? 'OR' : 'AND';
      foreach ($conditions['conditions'] as $key => $f) {
        if (\is_array($f) && isset($f['logic']) && isset($f['conditions'])) {
          if ($tmp = $this->getConditions($f, $cfg, $is_having, $indent + 2)) {
            $res .= (empty($res) ? '(' : PHP_EOL . str_repeat(' ', $indent) . "$logic (") .
            $tmp . PHP_EOL . str_repeat(' ', $indent) . ")";
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
          $is_bool   = false;
          $is_json   = false;
          $model     = null;
          // Dealing with JSON fields and null filter value
          if (in_array($f['operator'], ['isnull', 'isnotnull'])
              && (
                strpos($field, '->>')
                || (isset($cfg['fields'][$field]) && strpos($cfg['fields'][$field], '->>'))
                || (isset($cfg['ofields'][$field]) && strpos($cfg['ofields'][$field], '->>'))
              )
          ) {
            $field   = 'JSON_TYPE('.($cfg['fields'][$field] ?? ($cfg['ofields'][$field] ?? $field)).')';
            $is_json = true;
          }

          if ($is_having) {
            $res .= PHP_EOL . str_repeat(' ', $indent) . (empty($res) ? '' : "$logic ") . $field . ' ';
          }
          elseif (isset($cfg['available_fields'][$field])) {
            $table  = $cfg['available_fields'][$field];
            $column = $this->colSimpleName($cfg['fields'][$field] ?? $field);
            if ($table && $column && isset($cfg['models'][$table]['fields'][$column])) {
              $model = $cfg['models'][$table]['fields'][$column];
              $res  .= PHP_EOL . str_repeat(' ', $indent) . (empty($res) ? '' : "$logic ") .
                (!empty($cfg['available_fields'][$field]) ? $this->colFullName($cfg['fields'][$field] ?? $field, $cfg['available_fields'][$field], true) : $this->colSimpleName($column, true)
              ) . ' ';
            }
            else {
              // Remove the alias from where and join but not in having execpt if it's a count
              if (!$is_having && ($table === false) && isset($cfg['fields'][$field])) {
                $field = $cfg['fields'][$field];
                // Same for exp in case it's an alias
                if (!empty($f['exp']) && isset($cfg['fields'][$f['exp']])) {
                  $f['exp'] = $cfg['fields'][$f['exp']];
                }
              }

              $res .= (empty($res) ? '' : PHP_EOL . str_repeat(' ', $indent) . $logic . ' ') . $field . ' ';
            }

            if (!empty($model)) {
              $is_null = (bool)$model['null'];
              if ($model['type'] === 'binary') {
                $is_number = true;
                if (($model['maxlength'] === 16) && !empty($model['key'])) {
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
            elseif ($f['value'] && Str::isUid($f['value'])) {
              $is_uid = true;
            }
            elseif (\is_int($f['value']) || \is_float($f['value'])) {
              $is_number = true;
            }
          }
          else {
            $res .= (empty($res) ? '' : PHP_EOL . str_repeat(' ', $indent) . $logic . ' ') . $field . ' ';
          }

          if (empty($f['exp']) && isset($f['value']) && in_array($f['value'], [1, 0, true, false], true)) {
            // Always use LIKE as booleans and 1 and 0 are interpretated badly by MySQL
            $is_bool = true;
          }

          switch (strtolower($f['operator'])) {
            case '=':
              if ($is_uid && $is_bool) {
                $res .= isset($f['exp']) ? 'LIKE ' . $f['exp'] : 'LIKE ?';
              }
              else {
                $res .= isset($f['exp']) ? '= ' . $f['exp'] : '= ?';
              }
              break;
            case '!=':
              if (isset($f['exp'])) {
                $res .= '!= ' . $f['exp'];
              }
              else {
                $res .= '!= ?';
              }
              break;
            case 'like':
              if (isset($f['exp'])) {
                $res .= 'LIKE ' . $f['exp'];
              }
              else {
                $res .= 'LIKE ?';
              }
              break;
            case 'not like':
              if (isset($f['exp'])) {
                $res .= 'NOT LIKE ' . $f['exp'];
              }
              else {
                $res .= 'NOT LIKE ?';
              }
              break;
            case 'eq':
            case 'is':
              if ($is_uid && $is_bool) {
                $res .= isset($f['exp']) ? 'LIKE ' . $f['exp'] : 'LIKE ?';
              }
              elseif ($is_uid) {
                $res .= isset($f['exp']) ? '= ' . $f['exp'] : '= ?';
              }
              else {
                $res .= isset($f['exp']) ? '= ' . $f['exp'] : ($is_number ? '= ?' : 'LIKE ?');
              }
              break;
            case 'neq':
            case 'isnot':
              if ($is_uid) {
                $res .= isset($f['exp']) ? '!= ' . $f['exp'] : '!= ?';
              }
              else {
                $res .= isset($f['exp']) ? '!= ' . $f['exp'] : ($is_number ? '!= ?' : 'NOT LIKE ?');
              }
              break;

            case 'doesnotcontains':
            case 'doesnotcontain':
              $res .= 'NOT LIKE ' . ($f['exp'] ?? '?');
              break;

            case 'endswith':
            case 'startswith':
            case 'contains':
              $res .= 'LIKE ' . ($f['exp'] ?? '?');
              break;

            case 'gte':
            case '>=':
              if (isset($f['exp'])) {
                $res .= '>= ' . $f['exp'];
              }
              else {
                $res .= '>= ?';
              }
              break;

            case 'gt':
            case '>':
              if (isset($f['exp'])) {
                $res .= '> ' . $f['exp'];
              }
              else {
                $res .= '> ?';
              }
              break;

            case 'lte':
            case '<=':
              if (isset($f['exp'])) {
                $res .= '<= ' . $f['exp'];
              }
              else {
                $res .= '<= ?';
              }
              break;

            case 'lt':
            case '<':
              if (isset($f['exp'])) {
                $res .= '< ' . $f['exp'];
              }
              else {
                $res .= '< ?';
              }
              break;

            /** @todo Check if it is working with an array */
            case 'isnull':
              $res .= $is_json ? '= \'NULL\'' : 'IS NULL';
              break;

            case 'isnotnull':
              $res .= $is_json ? '!= \'NULL\'' : 'IS NOT NULL';
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
              $res .= $is_uid && $is_bool ? 'LIKE ?' : '= ?';
              break;
          }
        }
      }
    }

    if (!empty($res)) {
      return str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $res . PHP_EOL);
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
    $res = '';
    if (\is_array($cfg['tables']) && !empty($cfg['tables'])) {
      $res = 'SELECT ';
      if (!empty($cfg['count'])) {
        if ($cfg['group_by']) {
          $indexes = [];
          $idxs    = [];
          foreach ($cfg['group_by'] as $g) {
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
            } else {
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
                    } else {
                      $tmp[] = $ef;
                    }
                  }
                }
              }

              $cfg['fields'] = $indexes;
              foreach ($tmp as $k => $v) {
                if (is_string($k)) {
                  $cfg['fields'][$k] = $v;
                } else {
                  $cfg['fields'][] = $v;
                }
              }
            } else {
              $res .= 'COUNT(*) FROM ( SELECT ';
            }
          } else {
            if (count($indexes) === count($cfg['group_by'])) {
              $res .= 'COUNT(*) FROM ( SELECT ';
              //$cfg['fields'] = $indexes;
              // Changed by Mirko
              $cfg['fields'] = array_combine($idxs, $indexes);
            } else {
              $res .= 'COUNT(*) FROM ( SELECT ';
            }
          }
        } else {
          $res          .= 'COUNT(*)';
          $cfg['fields'] = [];
        }
      }

      if (!empty($cfg['fields'])) {
        $fields_to_put = [];
        // Checking the selected fields
        foreach ($cfg['fields'] as $alias => $f) {
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
            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '') . $f . (\is_string($alias) ? ' AS ' . $this->escape($alias) : '');
          } elseif (array_key_exists($f, $cfg['available_fields'])) {
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
              $st = 'LOWER(HEX(' . $this->colFullName($csn, $cfg['available_fields'][$f], true) . '))';
            }
            // For JSON fields
            elseif ($cfg['available_fields'][$f] === false) {
              $st = $f;
            } else {
              $st = $this->colFullName($csn, $cfg['available_fields'][$f], true);
            }

            if (\is_string($alias)) {
              $st .= ' AS ' . $this->escape($alias);
            }

            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '') . $st;
          } elseif (isset($cfg['available_fields'][$f]) && ($cfg['available_fields'][$f] === false)) {
            $this->db->error("Error! The column '$f' exists on several tables in '" . implode(', ', $cfg['tables']));
          } else {
            $this->db->error("Error! The column '$f' doesn't exist in '" . implode(', ', $cfg['tables']));
          }
        }

        $res .= implode(', ', $fields_to_put);
      }

      $res          .= PHP_EOL;
      $tables_to_put = [];
      foreach ($cfg['tables'] as $alias => $tfn) {
        $st = $this->tableFullName($tfn, true);
        if ($alias !== $tfn) {
          $st .= ' AS ' . $this->escape($alias);
        }

        $tables_to_put[] = $st;
      }

      $res .= 'FROM ' . implode(', ', $tables_to_put) . PHP_EOL;
      return $res;
    }

    return $res;
  }


  /**
   * Generates a string for the insert from a cfg array.
   * @param array $cfg The configuration array
   * @return string
   */
  public function getInsert(array $cfg): string
  {
    $fields_to_put = [
      'values' => [],
      'fields' => [],
    ];
    $i             = 0;
    foreach ($cfg['fields'] as $alias => $f) {
      if (isset($cfg['available_fields'][$f], $cfg['models'][$cfg['available_fields'][$f]])) {
        $model  = $cfg['models'][$cfg['available_fields'][$f]];
        $csn    = $this->colSimpleName($f);
        $is_uid = false;
        //X::hdump('---------------', $idx, $f, $tables[$idx]['model']['fields'][$csn], $args['values'],
        // $res['values'], '---------------');
        if (isset($model['fields'][$csn])) {
          $column = $model['fields'][$csn];
          if (($column['type'] === 'binary') && ($column['maxlength'] === 16)) {
            $is_uid = true;
          }

          $fields_to_put['fields'][] = $this->colSimpleName($f, true);
          $fields_to_put['values'][] = '?';
        }
      } else {
        $this->db->error("Error! The column '$f' doesn't exist in '" . implode(', ', $cfg['tables']));
      }

      $i++;
    }

    if (count($fields_to_put['fields']) && (count($cfg['tables']) === 1)) {
      return 'INSERT ' . ($cfg['ignore'] ? 'IGNORE ' : '') . 'INTO ' . $this->tableFullName(current($cfg['tables']), true) . PHP_EOL .
      '(' . implode(', ', $fields_to_put['fields']) . ')' . PHP_EOL . ' VALUES (' .
      implode(', ', $fields_to_put['values']) . ')' . PHP_EOL;
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
      'fields' => [],
    ];
    foreach ($cfg['fields'] as $alias => $f) {
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
          $fields_to_put['values'][] = '?';
        }
      } else {
        $this->db->error("Error!! The column '$f' doesn't exist in '" . implode(', ', $cfg['tables']));
      }
    }

    if (count($fields_to_put['fields'])) {
      $res .= 'UPDATE ' . ($cfg['ignore'] ? 'IGNORE ' : '') . $this->tableFullName(current($cfg['tables']), true) . ' SET ';
      $last = count($fields_to_put['fields']) - 1;
      foreach ($fields_to_put['fields'] as $i => $f) {
        $res .= $f . ' = ' . $fields_to_put['values'][$i];
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
      $res = 'DELETE ' . ($cfg['ignore'] ? 'IGNORE ' : '') .
      (count($cfg['join']) ? current($cfg['tables']) . ' ' : '') .
      'FROM ' . $this->tableFullName(current($cfg['tables']), true) . PHP_EOL;
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
      foreach ($cfg['join'] as $join) {
        if (isset($join['table'], $join['on']) && ($cond = $this->db->getConditions($join['on'], $cfg, false, 4))) {
          $res .= '  ' .
          (isset($join['type']) && (strtolower($join['type']) === 'left') ? 'LEFT ' : '') .
          'JOIN ' . $this->tableFullName($join['table'], true) .
            (!empty($join['alias']) ? ' AS ' . $this->escape($join['alias']) : '')
            . PHP_EOL . '    ON ' . $cond;
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
      $res = 'WHERE ' . $res;
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
      foreach ($cfg['group_by'] as $g) {
        if (isset($cfg['available_fields'][$g])) {
          $group_to_put[] = $this->escape($g);
          //$group_to_put[] = $this->colFullName($g, $cfg['available_fields'][$g], true);
        } else {
          $this->db->error("Error! The column '$g' doesn't exist for group by " . print_r($cfg, true));
        }
      }

      if (count($group_to_put)) {
        $res .= 'GROUP BY ' . implode(', ', $group_to_put) . PHP_EOL;
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
    if (!empty($cfg['group_by'])
        && !empty($cfg['having'])
        && ($cond = $this->getConditions($cfg['having'], $cfg, true, 2))
    ) {
      if ($cfg['count']) {
        $res .= ' WHERE ' . $cond . PHP_EOL;
      } else {
        $res .= '  HAVING ' . $cond . PHP_EOL;
      }
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
      foreach ($cfg['order'] as $col => $dir) {
        if (\is_array($dir) && isset($dir['field'])) {
          $col = $dir['field'];
          $dir = $dir['dir'] ?? 'ASC';
        }

        if (isset($cfg['available_fields'][$col])) {
          // If it's an alias we use the simple name
          if (isset($cfg['fields'][$col])) {
            $f = $this->colSimpleName($col, true);
          } elseif ($cfg['available_fields'][$col] === false) {
            $f = $col;
          } else {
            $f = $this->colFullName($col, $cfg['available_fields'][$col], true);
          }

          $res .= $f . ' ' . (strtolower($dir) === 'desc' ? 'DESC' : 'ASC') . ',' . PHP_EOL;
        }
      }

      if (!empty($res)) {
        return 'ORDER BY ' . substr($res, 0, Strrpos($res, ',')) . PHP_EOL;
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
      $res .= 'LIMIT ' . (!empty($cfg['start']) && bbn\Str::isInteger($cfg['start']) ? (string)$cfg['start'] : '0') . ', ' . $cfg['limit'];
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
        else {
          throw new \Exception(X::_("Impossible to recognize the column type")." $col[type]");
        }
      }
      else {
        $st .= $col['type'];
      }

      if (($col['type'] === 'enum') || ($col['type'] === 'set')) {
        $st .= ' (' . $col['extra'] . ')';
      }
      elseif (!empty($col['maxlength'])) {
        $st .= '(' . $col['maxlength'];
        if (!empty($col['decimals'])) {
          $st .= ',' . $col['decimals'];
        }

        $st .= ')';
      }

      if (in_array($col['type'], self::$numeric_types)
          && empty($col['signed'])
      ) {
        $st .= ' UNSIGNED';
      }

      if (empty($col['null'])) {
        $st .= ' NOT NULL';
      }

      if (!empty($col['virtual'])) {
        $st .= ' GENERATED ALWAYS AS (' . $col['generation'] . ') VIRTUAL';
      } elseif (array_key_exists('default', $col)) {
        $st .= ' DEFAULT ';
        if (($col['default'] === 'NULL')
            || bbn\Str::isNumber($col['default'])
            || strpos($col['default'], '(')
            || in_array(strtoupper($col['default']), ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'])
        ) {
          $st .= (string)$col['default'];
        } else {
          $st .= "'" . bbn\Str::escapeSquotes($col['default']) . "'";
        }
      }
    }

    $st .= PHP_EOL . ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
    return $st;
  }


  public function getCreateKeys(string $table, array $model = null)
  {
    $st = '';
    if (!$model) {
      $model = $this->db->modelize($table);
    }

    if ($model && !empty($model['keys'])) {
      $st   .= 'ALTER TABLE ' . $this->db->escape($table) . PHP_EOL;
      $last  = count($model['keys']) - 1;
      $dbcls = &$this->db;
      $i     = 0;
      foreach ($model['keys'] as $name => $key) {
        $st .= '  ADD ';
        if ($key['unique']
            && isset($model['fields'][$key['columns'][0]])
            && ($model['fields'][$key['columns'][0]]['key'] === 'PRI')
        ) {
          $st .= 'PRIMARY KEY';
        } elseif ($key['unique']) {
          $st .= 'UNIQUE KEY ' . $this->db->escape($name);
        } else {
          $st .= 'KEY ' . $this->db->escape($name);
        }

        $st .= ' (' . implode(
          ',', array_map(
            function ($a) use (&$dbcls) {
              return $dbcls->escape($a);
            }, $key['columns']
          )
        ) . ')';
        $st .= $i === $last ? ';' : ',' . PHP_EOL;
        $i++;
      }
    }

    return $st;
  }


  public function getCreateConstraints(string $table, array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->db->modelize($table);
    }

    if ($model && !empty($model['keys'])) {
      $constraints = array_filter(
        $model['keys'], function ($a) {
          return !empty($a['ref_table']) && isset($a['columns']) && (count($a['columns']) === 1);
        }
      );
      if ($last = count($constraints)) {
        $st .= 'ALTER TABLE ' . $this->db->escape($table) . PHP_EOL;
        $i   = 0;
        foreach ($constraints as $name => $key) {
          $i++;
          $st .= '  ADD ' .
          'CONSTRAINT ' . $this->db->escape($key['constraint']) . ' FOREIGN KEY (' . $this->db->escape($key['columns'][0]) . ') ' .
          'REFERENCES ' . $this->db->escape($key['ref_table']) . ' (' . $this->db->escape($key['ref_column']) . ')' .
            ($key['delete'] ? ' ON DELETE ' . $key['delete'] : '') .
            ($key['update'] ? ' ON UPDATE ' . $key['update'] : '') .
            ($i === $last ? ';' : ',' . PHP_EOL);
        }
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
    if ($tmp = $this->tableFullName($table)) {
      $bits = X::split($tmp, '.');
      return $this->db->getOne(
        "SELECT table_comment
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE table_schema = ?
        AND table_name = ?",
        $bits[0],
        $bits[1]
      );
    }

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
      $res = $this->db->query(sprintf("RENAME TABLE %s TO %s", $table, $newName));
      return !!$res;
    }

    return false;
  }


  public function getCreate(string $table, array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->db->modelize($table);
    }

    if ($st = $this->getCreateTable($table, $model)) {
      $lines = X::split($st, PHP_EOL);
      $end   = array_pop($lines);
      $st    = X::join($lines, PHP_EOL);
      foreach ($model['keys'] as $name => $key) {
        $st .= ',' . PHP_EOL . '  ';
        if ($key['unique'] && (count($key['columns']) === 1) && isset($model['fields'][$key['columns'][0]]) && ($model['fields'][$key['columns'][0]]['key'] === 'PRI')) {
          $st .= 'PRIMARY KEY';
        } elseif ($key['unique']) {
          $st .= 'UNIQUE KEY ' . $this->db->escape($name);
        } else {
          $st .= 'KEY ' . $this->db->escape($name);
        }

        $dbcls = &$this->db;
        $st   .= ' (' . implode(
          ',', array_map(
            function ($a) use (&$dbcls) {
              return $dbcls->escape($a);
            }, $key['columns']
          )
        ) . ')';
      }

      // For avoiding constraint names conflicts
      $keybase = strtolower(bbn\Str::genpwd(8, 4));
      $i       = 1;
      foreach ($model['keys'] as $name => $key) {
        if (!empty($key['ref_table'])) {
          $st .= ',' . PHP_EOL . '  ' .
          'CONSTRAINT ' . $this->db->escape($keybase.$i) . ' FOREIGN KEY (' . $this->db->escape($key['columns'][0]) . ') ' .
          'REFERENCES ' . $this->db->escape($key['ref_table']) . ' (' . $this->db->escape($key['ref_column']) . ')' .
            ($key['delete'] ? ' ON DELETE ' . $key['delete'] : '') .
            ($key['update'] ? ' ON UPDATE ' . $key['update'] : '');
          $i++;
        }
      }

      $st .= PHP_EOL . $end;
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
  public function createIndex(string $table, $column, bool $unique = false, $length = null): bool
  {
    $column = (array)$column;
    if ($length) {
      $length = (array)$length;
    }

    $name = bbn\Str::encodeFilename($table);
    if ($table = $this->tableFullName($table, true)) {
      foreach ($column as $i => $c) {
        if (!bbn\Str::checkName($c)) {
          $this->db->error("Illegal column $c");
        }

        $name      .= '_' . $c;
        $column[$i] = $this->escape($column[$i]);
        if (\is_int($length[$i]) && $length[$i] > 0) {
          $column[$i] .= '(' . $length[$i] . ')';
        }
      }

      $name = bbn\Str::cut($name, 50);
      return (bool)$this->db->query(
        'CREATE ' . ($unique ? 'UNIQUE ' : '') . "INDEX `$name` ON $table ( " .
        implode(', ', $column) . ' )'
      );
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
    if (($table = $this->tableFullName($table, true))
        && bbn\Str::checkName($key)
    ) {
      return (bool)$this->db->query(
        <<<MYSQL
ALTER TABLE $table
DROP INDEX `$key`
MYSQL
      );
    }

    return false;
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @param string $enc
   * @param string $collation
   * @return bool
   */
  public function createMysqlDatabase(string $database, string $enc = 'utf8', string $collation = 'utf8_general_ci'): bool
  {
    if (bbn\Str::checkName($database, $enc, $collation)) {
      return (bool)$this->db->rawQuery("CREATE DATABASE IF NOT EXISTS `$database` DEFAULT CHARACTER SET $enc COLLATE $collation;");
    }

    return false;
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @param string $enc
   * @param string $collation
   * @return bool
   */
  public function createDatabase(string $database): bool
  {
    return $this->createMysqlDatabase($database);
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   */
  public function dropDatabase(string $database): bool
  {
    if ($this->db->check()) {
      if (!Str::checkName($database)) {
        throw new \Exception(X::_("Wrong database name")." $database");
      }

      try {
        $this->db->rawQuery("DROP DATABASE `$database`");
      }
      catch (\Exception $e) {
        return false;
      }
    }

    return $this->db->check();
  }


  /**
   * Creates a database user
   *
   * @param string $user
   * @param string $pass
   * @param string $db
   * @return bool
   */
  public function createUser(string $user, string $pass, string $db = null): bool
  {
    if (null === $db) {
      $db = $this->db->getCurrent();
    }

    if (($db = $this->escape($db))
        && bbn\Str::checkName($user, $db)
        && (strpos($pass, "'") === false)
    ) {
      return (bool)$this->db->rawQuery(
        <<<MYSQL
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
   * There's an error in the query REVOKE ALL PRIVILEGES ON *.*
   *   FROM $user
   * Deletes a database user
   *
   * @param string $user
   * @return bool
   */
  public function deleteUser(string $user): bool
  {
    if (bbn\Str::checkName($user)) {
      $this->db->rawQuery(
        "
			REVOKE ALL PRIVILEGES ON *.*
      FROM $user"
      );
      return (bool)$this->db->query("DROP USER $user");
    }

    return false;
  }


  /**
   * @param string $user
   * @param string $host
   * @return array|null
   */
  public function getUsers(string $user = '', string $host = ''): ?array
  {
    if ($this->db->check()) {
      $cond = '';
      if (!empty($user) && bbn\Str::checkName($user)) {
        $cond .= " AND  user LIKE '$user' ";
      }

      if (!empty($host) && bbn\Str::checkName($host)) {
        $cond .= " AND  host LIKE '$host' ";
      }

      $us = $this->db->getRows(
        <<<MYSQL
SELECT DISTINCT host, User
FROM mysql.user
WHERE 1
$cond
MYSQL
      );
      $q  = [];
      foreach ($us as $u) {
        $gs = $this->db->getColArray("SHOW GRANTS FOR '$u[user]'@'$u[host]'");
        foreach ($gs as $g) {
          $q[] = $g;
        }
      }

      return $q;
    }

    return null;
  }


  public function dbSize(string $database = '', string $type = ''): int
  {
    $cur = null;
    if ($database && ($this->db->getCurrent() !== $database)) {
      $cur = $this->db->getCurrent();
      $this->db->change($database);
    }

    $q    = $this->db->query('SHOW TABLE STATUS');
    $size = 0;
    while ($row = $q->getRow()) {
      if (!$type || ($type === 'data')) {
        $size += $row['Data_length'];
      }

      if (!$type || ($type === 'index')) {
        $size += $row['Index_length'];
      }
    }

    if ($cur !== null) {
      $this->db->change($cur);
    }

    return $size;
  }


  public function tableSize(string $table, string $type = ''): int
  {
    $size = 0;
    if (bbn\Str::checkName($table)) {
      $row = $this->db->getRow('SHOW TABLE STATUS WHERE Name LIKE ?', $table);
      if (!$type || (strtolower($type) === 'index')) {
        $size += $row['Index_length'];
      }

      if (!$type || (strtolower($type) === 'data')) {
        $size += $row['Data_length'];
      }
    }

    return $size;
  }


  public function status(string $table = '', string $database = '')
  {
    $cur = null;
    if ($database && ($this->db->getCurrent() !== $database)) {
      $cur = $this->db->getCurrent();
      $this->db->change($database);
    }

    $r = $this->db->getRow('SHOW TABLE STATUS WHERE Name LIKE ?', $table);
    if (null !== $cur) {
      $this->db->change($cur);
    }

    return $r;
  }


  public function getUid(): string
  {
    //return $this->db->getOne("SELECT replace(uuid(),'-','')");
    $uid = null;
    while (!bbn\Str::isBuid(hex2bin($uid))) {
      $uid = $this->db->getOne("SELECT replace(uuid(),'-','')");
    }

    return $uid;
  }


  public function createTable($table_name, array $columns, array $keys = null, bool $with_constraints = false, string $charset = 'utf8', $engine = 'InnoDB')
  {
    $lines = [];
    $sql   = '';
    foreach ($columns as $n => $c) {
      $name = $c['name'] ?? $n;
      if (isset($c['type']) && bbn\Str::checkName($name)) {
        $st = $this->colSimpleName($name, true) . ' ' . $c['type'];
        if (!empty($c['maxlength'])) {
          $st .= '(' . $c['maxlength'] . ')';
        } elseif (!empty($c['values']) && \is_array($c['values'])) {
          $st .= '(';
          foreach ($c['values'] as $i => $v) {
            $st .= "'" . bbn\Str::escapeSquotes($v) . "'";
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
          $st .= ' DEFAULT ' . ($c['default'] === 'NULL' ? 'NULL' : "'" . bbn\Str::escapeSquotes($c['default']) . "'");
        }

        $lines[] = $st;
      }
    }

    if (count($lines)) {
      $sql = 'CREATE TABLE ' . $this->tableSimpleName($table_name, true) . ' (' . PHP_EOL . implode(',' . PHP_EOL, $lines) .
        PHP_EOL . ') ENGINE=' . $engine . ' DEFAULT CHARSET=' . $charset . ';';
    }

    return $sql;
  }


}
