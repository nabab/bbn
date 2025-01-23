<?php

namespace bbn\Db\Languages;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use bbn\Str;
use bbn\X;
use bbn\Models\Tts\Cache;
use bbn\Db\Engines;
use bbn\Db\HasError;
use bbn\Db\EnginesApi;
use bbn\Db\Query;
use bbn\Db\SqlEngines;
use bbn\Db\SqlFormatters;
use PHPSQLParser\PHPSQLParser;

abstract class Sql implements SqlEngines, Engines, EnginesApi, SqlFormatters
{
  use HasError;
  use Cache;

  /** @var string The quote character */
  public $qte = '`';

  /**
   * @var mixed $cache
   */
  protected $cache = [];

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

  /**
   * @var integer $cache_renewal
   */
  protected $cache_renewal = 3600;

  /**
   * @var PDO
   */
  protected ?PDO $pdo;

  /**
   * @var array $queries
   */
  protected $queries = [];

  /**
   * @var array $list_queries
   */
  protected $list_queries = [];

  /**
   * @var bool
   */
  protected $_triggers_disabled = false;

  /**
   * @var mixed $id_just_inserted
   */
  protected $id_just_inserted;

  /**
   * @var mixed $last_insert_id
   */
  protected $last_insert_id;

  /**
   * The information that will be accessed by Db\Query as the current statement's options
   * @var array $last_params
   */
  protected $last_params = ['sequences' => false, 'values' => false];

  /**
   * @var string \$last_query
   */
  protected $last_query;

  /**
   * @var string \$last_query
   */
  protected $last_real_query;

  /**
   * @var array $last_real_params
   */
  protected $last_real_params = ['sequences' => false, 'values' => false];

  /**
   * When set to true last_query will be filled with the latest statement.
   * @var bool
   */
  protected $_last_enabled = true;

  /**
   * @var int $max_queries
   */
  protected $max_queries = 50;

  /**
   * @var int $length_queries
   */
  protected $length_queries = 60;

  /**
   * @var mixed $hash_contour
   */
  protected $hash_contour = '__BBN__';

  /**
   * Unique string identifier for current connection
   * @var string
   */
  protected $hash;

  /**
   * @var array $cfgs The configs recorded for helpers functions
   */
  protected $cfgs = [];

  /**
   * An array of functions for launching triggers on actions
   * @var array
   */
  protected $_triggers = [
    'SELECT' => [
      'before' => [],
      'after' => []
    ],
    'INSERT' => [
      'before' => [],
      'after' => []
    ],
    'UPDATE' => [
      'before' => [],
      'after' => []
    ],
    'DELETE' => [
      'before' => [],
      'after' => []
    ]
  ];

  /** @var array The 'kinds' of writing statement */
  protected static $write_kinds = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'RENAME'];

  /** @var array The 'kinds' of reading statement */
  protected static $read_kinds = ['SELECT', 'SHOW'];

  /** @var array The 'kinds' of structure alteration statement */
  protected static $structure_kinds = ['DROP', 'ALTER', 'CREATE', 'RENAME'];

  /**
   * @var array $last_cfg
   */
  protected $last_cfg;

  /**
   * If set to false, Query will return a regular PDOStatement
   * Use stopFancyStuff() to set it to false
   * And use startFancyStuff to set it back to true
   * @var int $fancy
   */
  protected $_fancy = 1;

  /**
   * The currently selected database
   * @var mixed $current
   */
  protected $current;

  /**
   * A PHPSQLParser object
   * @var PHPSQLParser
   */
  private $_parser;

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
   * Destructor; setting the property holding the PDO object to null will close the connection.
   */
  public function __destruct()
  {
    $this->close();
  }


  /**
   * Returns the list of operators in the current language
   *
   * @return array
   */
  public function getOperators(): array
  {
    return self::$operators;
  }


  /**
   * Returns the list of all types
   *
   * @return array
   */
  public function getTypes(): array
  {
    return self::$types;
  }


  /**
   * Returns the list of numeric types in the current language
   *
   * @return array
   */
  public function getNumericTypes(): array
  {
    return self::$numeric_types;
  }


  /**
   * Returns the list of date types in the current language
   *
   * @return array
   */
  public function getDateTypes(): array
  {
    return self::$date_types;
  }


  /**
   * Closes the connection definitely, making the object unusable.
   */
  public function close()
  {
    if ($this->pdo) {
      $this->pdo = null;
    }
  }


  public function initCache()
  {
    $this->cache = [];
  }


  /**
   * Returns the engine class that extends the base Sql class.
   *
   * @return string
   */
  public function getEngine()
  {
    $class = static::class;
    return strtolower(X::basename(str_replace('\\', '/', $class)));
  }


  /**
   * Returns the current database selected by the current connection.
   *
   * @return string|null
   */
  public function getCurrent(): ?string
  {
    return $this->current;
  }


  /**
   * Return the last config for this connection.
   *
   * @return array|null
   */
  public function getLastCfg(): ?array
  {
    return $this->last_cfg;
  }

  /**
   * Changes the current database to the given one.
   *
   * @param string $db The database name or file
   * @return bool
   */
  public function change(string $db): bool
  {
    if (($this->getCurrent() !== $db) && Str::checkName($db)) {
      $this->rawQuery("USE `$db`");
      $this->current = $db;
      return true;
    }

    return false;
  }

  /**
   * Returns a database item expression escaped like database, table, column, key names
   *
   * @param string $item The item's name (escaped or not)
   * @return string
   * @throws Exception
   */
  public function escape(string $item): string
  {
    $items = explode('.', str_replace($this->qte, '', $item));
    $r     = [];

    foreach ($items as $m) {
      if (!Str::checkName($m)) {
        throw new Exception(X::_("Illegal name %s for the column", $m));
      }

      $r[] = $this->qte . $m . $this->qte;
    }

    return implode('.', $r);
  }


  /**
   * Returns a table's full name i.e. database.table
   *
   * @param string $table The table's name (escaped or not)
   * @param bool $escaped If set to true the returned string will be escaped
   * @return string|null
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
      $db    = $this->getCurrent();
      $table = trim($bits[0], ' ' . $this->qte);
    }

    if (Str::checkName($db) && Str::checkName($table)) {
      return $escaped
        ? $this->escape("$db.$table")
        : "$db.$table";
    }

    return null;
  }

  /**
   * Returns a table's simple name i.e. table
   *
   * @param string $table The table's name (escaped or not)
   * @param bool $escaped If set to true the returned string will be escaped
   * @return string|null
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
        case 3:
          $table = trim($bits[1], ' ' . $this->qte);
          break;
      }

      if (Str::checkName($table)) {
        return $escaped ? $this->escape($table) : $table;
      }
    }

    return null;
  }

  /**
   * Returns a column's full name i.e. table.column
   *
   * @param string $col The column's name (escaped or not)
   * @param string|null $table The table's name (escaped or not)
   * @param false $escaped If set to true the returned string will be escaped
   * @return string|null
   */
  public function colFullName(string $col, ?string $table = null, bool $escaped = false): ?string
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

      if ((null !== $ok) && Str::checkName($table) && Str::checkName($col)) {
        return $escaped
          ? $this->escape("$table.$col")
          : "$table.$col";
      }
    }

    return null;
  }

  /**
   * Returns a column's simple name i.e. column
   *
   * @param string $col     The column's name (escaped or not)
   * @param bool $escaped   If set to true the returned string will be escaped
   * @return string|null
   */
  public function colSimpleName(string $col, bool $escaped = false): ?string
  {
    if ($bits = explode('.', $col)) {
      $col = trim(end($bits), ' ' . $this->qte);
      if ($col && Str::checkName($col)) {
        return $escaped ? $this->escape($col) : $col;
      }
    }

    return null;
  }

  /**
   * Returns true if the given string is the full name of a table ('database.table').
   *
   * @param string $table
   * @return bool
   */
  public function isTableFullName(string $table): bool
  {
    return (bool)strpos($table, '.');
  }

  /**
   * Returns true if the given string is the full name of a column ('table.column').
   *
   * @param string $col
   * @return bool
   */
  public function isColFullName(string $col): bool
  {
    return (bool)strpos($col, '.');
  }

  /**
   * Executes the original PDO query function
   *
   * @return false|PDOStatement
   */
  public function rawQuery()
  {
    if ($this->_fancy) {
      $this->stopFancyStuff();
      $switch_to_fancy = true;
    }

    $result = $this->pdo->query(...func_get_args());

    if (!empty($switch_to_fancy)) {
      $this->startFancyStuff();
    }

    return $result;
  }

  /**
   * Parses an SQL query and return an array.
   *
   * @param string $statement
   * @return null|array
   */
  public function parseQuery(string $statement): ?array
  {
    if ($this->_parser === null) {
      $this->_parser = new PHPSQLParser();
    }

    $done = false;
    try {
      $r    = $this->_parser->parse($statement);
      $done = 1;
    }
    catch (Exception $e){
      $this->log('Error while parsing the query '.$statement);
    }

    if ($done) {
      if (!$r || !count($r)) {
        $this->log('Impossible to parse the query '.$statement);
        return null;
      }

      if (isset($r['BRACKET']) && (\count($r) === 1)) {
        /** @todo Is it impossible to parse queries with brackets ? */
        //throw new Exception('Bracket in the query '.$statement);
        return null;
      }

      return $r;
    }

    return null;
  }

  /**
   * @param array $cfg
   * @return array
   */
  public function getQueryValues(array $cfg): array
  {
    $res = [];
    if (!empty($cfg['values'])) {
      // Only for the insert / update part (at the start of the query), before the where (but not before the join in insert / update beware!)
      $num_types = in_array($cfg['kind'], ['INSERT', 'UPDATE']) && !empty($cfg['values_types']) ? count($cfg['values_types']) : 0;
      foreach ($cfg['values'] as $i => $v) {
        if (!isset($cfg['values_desc'][$i])) {
          X::log([$i, $cfg['values_desc'], $v], 'no_desc_in_sql');
        }
        if ($num_types && ($i < $num_types) && ($cfg['values_desc'][$i]['type'] === 'exp')) {
          continue;
        }
        // Transforming the values if needed
        if (($cfg['values_desc'][$i]['type'] === 'binary')
          && ($cfg['values_desc'][$i]['maxlength'] === 16)
          && Str::isUid($v)
        ) {
          $res[] = hex2bin($v);
        }
        elseif (is_string($v)
          && ((($cfg['values_desc'][$i]['type'] === 'date') && (\strlen($v) < 10))
            || (($cfg['values_desc'][$i]['type'] === 'time') && (\strlen($v) < 8))
            || (($cfg['values_desc'][$i]['type'] === 'datetime') && (\strlen($v) < 19)))
          && (!isset($cfg['values_desc'][$i]['operator'])
            || (($cfg['values_desc'][$i]['operator'] !== 'gten')
              && ($cfg['values_desc'][$i]['operator'] !== 'gtn')
              && ($cfg['values_desc'][$i]['operator'] !== 'lten')
              && ($cfg['values_desc'][$i]['operator'] !== 'ltn')))
        ) {
          $res[] = $v.'%';
        }
        elseif (!empty($cfg['values_desc'][$i]['operator'])) {
          switch ($cfg['values_desc'][$i]['operator']){
            case 'contains':
            case 'doesnotcontain':
              $res[] = '%'.$v.'%';
              break;
            case 'startswith':
              $res[] = $v.'%';
              break;
            case 'endswith':
              $res[] = '%'.$v;
              break;
            default:
              $res[] = $v;
          }
        }
        else{
          $res[] = $v;
        }
      }
    }

    return $res;
  }

  /**
   * Returns an array of fields for the given table(s).
   *
   * ```php
   * X::dump($db->getFieldsList("table_users"));
   * // (array) ['table_users.username', 'table_users.name']
   * ```
   *
   * @param $tables
   * @return array
   * @throws Exception
   */
  public function getFieldsList($tables): array
  {
    $res = [];
    if (!is_array($tables)) {
      $tables = [$tables];
    }

    foreach ($tables as $t){
      $model = $this->getColumns($t);
      if (!is_array($model)) {
        $this->error('Impossible to find the table '.$t);
        throw new Exception(X::_('Impossible to find the table ').$t);
      }

      foreach (array_keys($model) as $f){
        $res[] = $this->colFullName($f, $t);
      }
    }

    return $res;
  }

  /**
   * Return primary keys of a table as a numeric array.
   *
   * ```php
   * X::dump($db-> get_primary('table_users'));
   * // (array) ["id"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getPrimary(string $table): array
  {
    if (($keys = $this->getKeys($table)) && isset($keys['keys']['PRIMARY'])) {
      return $keys['keys']['PRIMARY']['columns'];
    }

    return [];
  }

  /**
   * Return the unique primary key of the given table.
   *
   * ```php
   * X::dump($db->getUniquePrimary('table_users'));
   * // (string) id
   * ```
   *
   * @param string $table The table's name
   * @return null|string
   */
  public function getUniquePrimary(string $table): ?string
  {
    if (($keys = $this->getKeys($table))
      && isset($keys['keys']['PRIMARY'])
      && (\count($keys['keys']['PRIMARY']['columns']) === 1)
    ) {
      return $keys['keys']['PRIMARY']['columns'][0];
    }

    return null;
  }

  /**
   * Return the unique keys of a table as a numeric array.
   *
   * ```php
   * X::dump($db->getUniqueKeys('table_users'));
   * // (array) ["userid", "userdataid"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getUniqueKeys(string $table): array
  {
    if ($ks = $this->getKeys($table)) {
      foreach ($ks['keys'] as $k){
        if ($k['unique']) {
          return $k['columns'];
        }
      }
    }

    return [];
  }

  /**
   * @param array $conditions
   * @param array $cfg
   */
  public function arrangeConditions(array &$conditions, array $cfg): void
  {
    if (!empty($cfg['available_fields']) && isset($conditions['conditions'])) {
      foreach ($conditions['conditions'] as &$c){
        if (array_key_exists('conditions', $c) && is_array($c['conditions'])) {
          $this->arrangeConditions($c, $cfg);
        }
        elseif (isset($c['field']) && empty($cfg['available_fields'][$c['field']]) && !$this->isColFullName($c['field'])) {
          foreach ($cfg['tables'] as $t => $o){
            if (isset($cfg['available_fields'][$this->colFullName($c['field'], $t)])) {
              $c['field'] = $this->colFullName($c['field'], $t);
              break;
            }
          }
        }
      }
    }
  }

  /**
   * @param array $res
   * @return array
   */
  public function removeVirtual(array $res): array
  {
    if (isset($res['fields'])) {
      $to_remove = [];
      foreach ($res['fields'] as $i => $f) {
        if (!empty($res['available_fields'][$f])) {
          $model = $this->modelize($res['available_fields'][$f]);
          if (isset($model['fields'][$this->colSimpleName($f)])
            && !empty($model['fields'][$this->colSimpleName($f)]['virtual'])
          ) {
            array_unshift($to_remove, $i);
          }
        }
      }

      $res['removed_virtual'] = $to_remove;
      foreach ($to_remove as $i) {
        array_splice($res['fields'], $i, 1);
        array_splice($res['values'], $i, 1);
      }
    }

    return $res;
  }

  /**
   * Returns a linear array 
   * @param array $where
   * @param array $cfg
   * @return array
   */
  public function getValuesDesc(array $where, array $cfg, &$others = []): array
  {
    if (!empty($where['conditions'])) {
      foreach ($where['conditions'] as &$f){
        // It's an imbricated condition
        if (isset($f['logic'], $f['conditions']) && is_array($f['conditions'])) {
          $this->getValuesDesc($f, $cfg, $others);
        }
        // Value is set
        elseif (array_key_exists('value', $f)) {
          $desc = [
            'primary' => false,
            'type' => null,
            'maxlength' => null,
            'operator' => $f['operator'] ?? null
          ];
          if (isset($f['field'], $cfg['available_fields'][$f['field']])) {
            $t = $cfg['available_fields'][$f['field']];
            if (isset($f['field'], $cfg['tables_full'][$t])
              && ($model = $this->modelize($cfg['tables_full'][$t]))
              && ($fname = $this->colSimpleName($f['field']))
            ) {
              $desc['name'] = $fname;
              if (!empty($model['fields'][$fname]['type'])) {
                $desc['type'] = $model['fields'][$fname]['type'];
                $desc['maxlength'] = $model['fields'][$fname]['maxlength'] ?? null;
                $desc['operator'] = $f['operator'] ?? null;
              }
              // Fixing filters using alias
              elseif (isset($cfg['fields'][$f['field']])
                && ($fname = $this->colSimpleName($cfg['fields'][$f['field']]))
                && !empty($model['fields'][$fname]['type'])
              ) {
                $desc['type'] = $model['fields'][$fname]['type'];
                $desc['maxlength'] = $model['fields'][$fname]['maxlength'] ?? null;
                $desc['operator'] = $f['operator'] ?? null;
              }

              if (!empty($desc['type'])
                && isset($model['keys']['PRIMARY'])
                && (count($model['keys']['PRIMARY']['columns']) === 1)
                && ($model['keys']['PRIMARY']['columns'][0] === $fname)
              ) {
                $desc['primary'] = true;
              }
            }
          }

          $others[] = $desc;
        }
      }
    }

    return $others;
  }


  /**
   * Returns a string with the conditions for the ON, WHERE, or HAVING part of the query if there is, empty otherwise.
   *
   * @param array $conditions
   * @param array $cfg
   * @param bool $is_having
   * @param int $indent
   * @return string
   */
  public function getConditions(array $conditions, array $cfg = [], bool $is_having = false, int $indent = 0): string
  {
    $res = '';
    if (isset($conditions['conditions'], $conditions['logic'])) {
      $logic = isset($conditions['logic']) && ($conditions['logic'] === 'OR') ? 'OR' : 'AND';
      foreach ($conditions['conditions'] as $key => $f) {
        if (is_array($f) && isset($f['logic']) && isset($f['conditions'])) {
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
          $model     = null;
          if ($is_having) {
            $res .= PHP_EOL . str_repeat(' ', $indent) . (empty($res) ? '' : "$logic ") . $field . ' ';
          }
          elseif (isset($cfg['available_fields'][$field])) {
            $realField = $cfg['fields'][$field] ?? $field;
            $isFunction = strpos($realField, '(') !== false;
            $table  = $cfg['tables_full'][$cfg['available_fields'][$realField]] ?? false;
            if ($isFunction) {
              $res .= PHP_EOL . str_repeat(' ', $indent) . (empty($res) ? '' : "$logic ") . $realField . ' ';
            }
            else {
              if (!$table) {
                X::ddump($cfg['tables_full'], $cfg['available_fields'][$field], $field, $cfg['fields']);
                throw new Exception(X::_("Impossible to find the table for the field %s", $field));
              }
  
              $column = $this->colSimpleName($cfg['fields'][$field] ?? $field);
              $model  = $this->modelize($table);
              if (!$model || !array_key_exists('fields', $model)) {
                throw new Exception(X::_("Impossible to modelize the table %s", $table));
              }
              else {
                $model = $model['fields'];
                $model = array_key_exists($column, $model) ? $model[$column] : null;
              }
  
              if ($table && $column && $model) {
                $res  .= PHP_EOL . 
                    str_repeat(' ', $indent) . 
                    (empty($res) ? '' : "$logic ") .
                    (
                      !empty($cfg['available_fields'][$field]) ?
                        $this->colFullName(
                          $cfg['fields'][$field] ?? $field,
                          $cfg['available_fields'][$field],
                          true
                        )
                        : $this->colSimpleName($column, true)
                  ) . ' ';
              }
              else {
                // Remove the alias from where and join but not in having except if it's a count
                if (!$is_having && empty($table) && isset($cfg['fields'][$field])) {
                  $field = $cfg['fields'][$field];
                  // Same for exp in case it's an alias
                  if (!empty($f['exp']) && isset($cfg['fields'][$f['exp']])) {
                    $f['exp'] = $cfg['fields'][$f['exp']];
                  }
                }
  
                $res .= (empty($res) ? '' : PHP_EOL . str_repeat(' ', $indent) . $logic . ' ') . $field . ' ';
              }
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
            // Always use LIKE as booleans and 1 and 0 are interpreted badly by MySQL
            $is_bool = true;
          }

          switch (strtolower($f['operator'])) {
            case '=':
              if ($is_uid || $is_bool || $is_number|| $is_date) {
                $res .= isset($f['exp']) ? '= ' . $f['exp'] : '= ?';
              }
              else {
                $res .= isset($f['exp']) ? 'LIKE ' . $f['exp'] : 'LIKE ?';
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
              $res .= ($is_number ? '!= ' : 'NOT LIKE ') . ($f['exp'] ?? '?');
              break;

            case 'endswith':
            case 'startswith':
            case 'contains':
              $res .= 'LIKE ' . ($f['exp'] ?? '?');
              break;

            case 'gte':
            case '>=':
              $res .= '>= ' . ($f['exp'] ?? '?');
              break;

            case 'gten':
              $res .= '>= DATE_ADD(NOW(), INTERVAL ' . ($f['exp'] ?? '?');
              break;

            case 'gt':
            case '>':
              $res .= '> ' . ($f['exp'] ?? '?');
              break;

            case 'gtn':
              $res .= '> DATE_ADD(NOW(), INTERVAL '.($f['exp'] ?? '?').')';
              break;

            case 'lte':
            case '<=':
              $res .= '<= ' . ($f['exp'] ?? '?');
              break;

            case 'lten':
              $res .= '<= DATE_SUB(NOW(), INTERVAL '.($f['exp'] ?? '?').')';
              break;

            case 'lt':
            case '<':
              $res .= '< ' . ($f['exp'] ?? '?');
              break;

            case 'ltn':
              $res .= '< DATE_SUB(NOW(), INTERVAL '.($f['exp'] ?? '?').')';
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

            default:
              $res .= $is_uid || $is_bool || $is_number|| $is_date ? '= ?' : 'LIKE ?';
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
   * @throws Exception
   */
  public function getSelect(array $cfg): string
  {
    $res = '';
    if (is_array($cfg['tables']) && !empty($cfg['tables'])) {
      $res = 'SELECT ';
      if (!empty($cfg['count'])) {
        if (!empty($cfg['group_by'])) {
          $indexes = [];
          $idxs    = [];
          foreach ($cfg['group_by'] as $g) {
            // Alias
            if (isset($cfg['fields'][$g])) {
              $g = $cfg['fields'][$g];
            }

            if ((!empty($cfg['available_fields'][$g]) && $t = $cfg['available_fields'][$g])
              && ($cfn = $this->colFullName($g, $t))
            ) {
              $indexes[] = $cfn;
              //$idxs[] = $this->colSimpleName($g, true);
              // Changed by Mirko
              $idxs[] = $this->colSimpleName($cfg['aliases'][$g] ?? $g, true);
            } else {
              $indexes[] = $g;
              $idxs[] = $cfg['aliases'][$g] ?? $g;
            }
          }

          if (!empty($cfg['having'])) {
            if (count($indexes) === count($cfg['group_by'])) {
              $res .= 'COUNT(*) FROM ( SELECT ';
              $tmp  = [];
              if ($extracted_fields = $this->extractFields($cfg, $cfg['having']['conditions'])) {
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
            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '') . $f . (is_string($alias) ? ' AS ' . $this->escape($alias) : '');
          }
          elseif (is_string($alias) && ((strpos($f, "'") === 0) || (strpos($f, '"') === 0))) {
            $fields_to_put[] = $f . ' AS ' . $this->escape($alias);
          }
          elseif (isset($cfg['available_fields']) && array_key_exists($f, $cfg['available_fields'])) {
            $idx    = $cfg['available_fields'][$f];
            if ($idx && isset($cfg['tables_full'][$idx])) {
              $idx = $cfg['tables_full'][$idx];
            }

            $csn    = $this->colSimpleName($f);
            $is_uid = false;
            //die(var_dump($idx, $f, $tables[$idx]));
            $model = $this->modelize($idx);
            if (($idx !== false) && isset($model['fields'][$csn])) {
              $column = $model['fields'][$csn];
              if (($column['type'] === 'binary') && ($column['maxlength'] === 16)) {
                $is_uid = true;
                if (!is_string($alias)) {
                  $alias = $csn;
                }
              }
            }

            //$res['fields'][$alias] = $this->cfn($f, $fields[$f]);
            if ($is_uid) {
              if (method_exists($this, 'getHexStatement')) {
                $st = 'LOWER(' . $this->getHexStatement($this->colFullName($csn, $cfg['available_fields'][$f], true)) . ')';
              }
              else {
                $st = 'LOWER(HEX(' . $this->colFullName($csn, $cfg['available_fields'][$f], true) . '))';
              }
            }
            // For JSON fields
            elseif ($cfg['available_fields'][$f] === false) {
              $st = $f;
            }
            else {
              $st = $this->colFullName($csn, $cfg['available_fields'][$f], true);
            }

            if (is_string($alias)) {
              $st .= ' AS ' . $this->escape($alias);
            }

            $fields_to_put[] = ($is_distinct ? 'DISTINCT ' : '') . $st;
          } elseif (isset($cfg['available_fields'][$f]) && ($cfg['available_fields'][$f] === false)) {
            $this->error("Error! The column '$f' exists on several tables in '" . implode(', ', $cfg['tables']), false);
          } else {
            $this->error("Error! The column '$f' doesn't exist in '" . implode(', ', $cfg['tables']), false);
          }
        }

        $res .= implode(', ', $fields_to_put);
      }

      $res          .= PHP_EOL;
      $tables_to_put = [];
      foreach ($cfg['tables'] as $alias => $tfn) {
        $st = $this->tableFullName($tfn, true);
        if (is_string($alias) && $alias !== $tfn) {
          $st .= ' AS ' . $this->escape($alias);
        }

        $tables_to_put[] = $st;
      }

      $res .= 'FROM ' . implode(', ', $tables_to_put) . PHP_EOL;
      return $res;
    }

    return $res;
  }


  public function getUnion(array $cfg): string
  {
    $sql  = 'SELECT ';
    if (empty($cfg['fields'])) {
      $sql .= '* ';
    }
    else{
      foreach ($cfg['fields'] as $i => $f){
        if ($i) {
          $sql .= ', ';
        }

        $sql .= strpos($f, '(') === false ? $this->colSimpleName($f, true) : $f;
      }
    }

    $sql .= ' FROM (('.PHP_EOL;
    $vals = [];
    $i    = 0;
    foreach ($cfg['union'] as $u) {
      $subcfg = $this->processCfg($u);
      if ($subcfg && $subcfg['sql']) {
        /** @todo From here needs to analyze the where array to the light of the tables' config */
        if (!empty($where)) {
          if (empty($fields)) {
            $fields = $subcfg['fields'];
          }

          foreach ($fields as $k => $f){
            if (!empty($subcfg['available_fields'][$f])) {
              $model = $this->modelize($subcfg['available_fields'][$f]);
              if ($model) {
                throw new Exception("Impossible to create the where in union for the following request: ".PHP_EOL.$subcfg['sql']);
                //die(var_dump($t['fields'][$cfg['fields'][$f] ?? $this->csn($f)]));
              }
            }
          }
        }

        if ($i) {
          $sql .= PHP_EOL.') UNION ('.PHP_EOL;
        }

        $sql .= $subcfg['sql'];
        array_push($vals, ...$subcfg['values']);
        $i++;
      }
    }

    $sql .= PHP_EOL.')) AS t' . PHP_EOL;
    return $sql;
  }


  /**
   * Generates a string for the insert from a cfg array.
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getInsert(array $cfg): string
  {
    $fields_to_put = [
      'values' => [],
      'fields' => [],
    ];
    $i             = 0;
    foreach ($cfg['fields'] as $i => $f) {
      if (!empty($cfg['available_fields'][$f])) {
        $model  = $this->modelize($cfg['available_fields'][$f]);
        $csn    = $this->colSimpleName($f);
        if (isset($model['fields'][$csn])) {

          $fields_to_put['fields'][] = $this->colSimpleName($f, true);
          if ($cfg['values_desc'][$i] && ($cfg['values_desc'][$i]['type'] === 'exp')) {
            $fields_to_put['values'][] = $cfg['values'][$i][1];
          }
          else {
            $fields_to_put['values'][] = '?';
          }
        }
        else {
          $this->error(X::_("Error! Impossible to find the model for %s", implode(', ', $cfg['tables'])), false);
        }
      } else {
        $this->error(X::_("Error! The column '%s' doesn't exist in %s", $f, implode(', ', $cfg['tables'])), false);
      }

      $i++;
    }

    if (count($fields_to_put['fields']) && (count($cfg['tables']) === 1)) {
      return 'INSERT ' . (!empty($cfg['ignore']) ? $this->getIgnore() : '') . 'INTO ' . $this->tableFullName(current($cfg['tables']), true) . PHP_EOL .
        '(' . implode(', ', $fields_to_put['fields']) . ')' . PHP_EOL . ' VALUES (' .
        implode(', ', $fields_to_put['values']) . ')' . PHP_EOL;
    }

    return '';
  }


  /**
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getUpdate(array $cfg): string
  {
    $res           = '';
    $fields_to_put = [
      'values' => [],
      'fields' => [],
    ];
    foreach ($cfg['fields'] as $i => $f) {
      if (!empty($cfg['available_fields'][$f])) {
        $model  = $this->modelize($cfg['available_fields'][$f]);
        $csn    = $this->colSimpleName($f);
        if (isset($model['fields'][$csn])) {
          $fields_to_put['fields'][] = $this->colSimpleName($f, true);
          if ($cfg['values_desc'][$i] && ($cfg['values_desc'][$i]['type'] === 'exp')) {
            $fields_to_put['values'][] = $cfg['values'][$i][1];
          }
          else {
            $fields_to_put['values'][] = '?';
          }
        }
        else {
          $this->error(X::_("Impossible to retrieve the column %s", $f), false);
        }
      }
      else {
        $this->error(X::_("The column '%s' doesn't exist in %s", $f, implode(', ', $cfg['tables'])), false);
      }
    }

    if (count($fields_to_put['fields']) && (count($cfg['tables']) === 1)) {
      $res .= 'UPDATE ' . (!empty($cfg['ignore']) ? $this->getIgnore() : '') . $this->tableFullName(current($cfg['tables']), true) . ' SET ';
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
   * X::dump($db->getDelete(['tables' => 'users']);
   * // (string) DELETE FROM `db_example`.`table_users`
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   *
   */
  public function getDelete(array $cfg): string
  {
    $res = '';
    if (count($cfg['tables']) === 1) {
      $res = 'DELETE ' . (!empty($cfg['ignore']) ? $this->getIgnore() : '') .
        (count($cfg['join'] ?? []) ? current($cfg['tables']) . ' ' : '') .
        'FROM ' . $this->tableFullName(current($cfg['tables']), true) . PHP_EOL;
    }

    return $res;
  }


  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getJoin(array $cfg, array $joins = null): string
  {
    $res = '';
    if (!$joins) {
      $joins = $cfg['join'];
    }

    if (!empty($joins)) {
      foreach ($joins as $join) {
        if (isset($join['table'], $join['on']) && ($cond = $this->getConditions($join['on'], $cfg, false, 4))) {
          $res .= '  ' .
            (isset($join['type']) && (strtolower($join['type']) === 'left') ? 'LEFT ' : '') .
            'JOIN ';
          if (isset($join['join'])) {
            $res .= '(';
          }

          $res .= $this->tableFullName($join['table'], true) .
          (!empty($join['alias']) ? ' AS ' . $this->escape($join['alias']) : '');

          if (isset($join['join'])) {
            $res .= $this->getJoin($cfg, $join['join']);
            $res .= ')';
          }

          $res .= PHP_EOL . '    ON ' . $cond;
        }
      }
    }

    return $res;
  }


  /**
   * Returns a string with the WHERE part of the query if there is, empty otherwise
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
   * @throws Exception
   */
  public function getGroupBy(array $cfg): string
  {
    $res          = '';
    $group_to_put = [];
    if (!empty($cfg['group_by'])) {
      foreach ($cfg['group_by'] as $g) {
        if (isset($cfg['available_fields'][$g])) {
          $group_to_put[] = $this->escape($g);
        } else {
          $this->error("Error! The column '$g' doesn't exist for group by " . print_r($cfg, true), false);
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
      if (!empty($cfg['count'])) {
        $res .= ' WHERE ' . $cond . PHP_EOL;
      } else {
        $res .= '  HAVING ' . $cond . PHP_EOL;
      }
    }

    return $res;
  }


  /**
   * Get a string starting with ORDER BY with corresponding parameters to $order.
   *
   * @param array $cfg
   * @return string
   */
  public function getOrder(array $cfg): string
  {
    $res = '';
    if (!empty($cfg['order'])) {
      foreach ($cfg['order'] as $col => $dir) {
        if (is_array($dir) && isset($dir['field'])) {
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
    if (!empty($cfg['limit']) && Str::isInteger($cfg['limit'])) {
      $res .= 'LIMIT ' . (!empty($cfg['start']) && Str::isInteger($cfg['start']) ? (string)$cfg['start'] : '0') . ', ' . $cfg['limit'];
    }

    return $res;
  }

  /**
   * @return string
   */
  private function getIgnore()
  {
    return ($this->getEngine() === 'sqlite' ? 'OR ' : '') . 'IGNORE ';
  }

  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws Exception
   */
  public function getCreateConstraints(string $table, array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->modelize($table);
    }

    if ($model && !empty($model['keys'])) {
      $constraints = array_filter(
        $model['keys'], function ($a) {
        return !empty($a['ref_table']) && isset($a['columns']) && (count($a['columns']) === 1)
          && !empty($a['constraint']) && !empty($a['ref_column']);
      }
      );
      if ($last = count($constraints)) {
        $st .= 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
        $i   = 0;
        foreach ($constraints as $name => $key) {
          $i++;
          $st .= '  ADD ' .
            'CONSTRAINT ' . $this->escape($key['constraint']) . ' FOREIGN KEY (' . $this->escape($key['columns'][0]) . ') ' .
            'REFERENCES ' . $this->escape($key['ref_table']) . ' (' . $this->escape($key['ref_column']) . ')' .
            (!empty($key['delete']) ? ' ON DELETE ' . $key['delete'] : '') .
            (!empty($key['update']) ? ' ON UPDATE ' . $key['update'] : '') .
            ($i === $last ? ';' : ',' . PHP_EOL);
        }
      }
    }

    return $st;
  }


  /**
   * Returns true if the given table exists
   *
   * @param string $table
   * @param string $database. or currently selected if none
   * @return boolean
   */
  public function tableExists(string $table, string $database = ''): bool
  {
    $q = "SHOW tables ";
    if ($database) {
      $q .= "FROM " . $this->escape($database) . " ";
    }

    return (bool)$this->getOne($q . "LIKE ?", $table);
  }


  /**
   * Drops a table
   *
   * @param string $table
   * @param string $database. or currently selected if none
   * @return boolean
   */
  public function dropTable(string $table, string $database = ''): bool
  {
    $tfn = $this->tableFullName(($database ? $database . '.' : '') . $table, true);
    if (!$tfn) {
      throw new Exception(X::_("Invalid table name to drop"));
    }

    if (!$this->tableExists($table, $database)) {
      throw new Exception(X::_("The table %s does not exist", $table));
    }

    $this->query("DROP table $tfn");
    return !$this->tableExists($table, $database);

  }



  /**
   * @param string $table
   * @param string $column
   * @return bool
   */
  public function dropColumn(string $table, string $column): bool
  {
    if (($table = $this->tableFullName($table, true)) && Str::checkName($column)) {
      return (bool)$this->rawQuery("ALTER TABLE $table DROP COLUMN $column");
    }

    return false;
  }

  /**
   * @param array $where
   * @param bool  $full
   * @return array|bool
   */
  public function treatConditions(array $where, bool $full = true)
  {
    if (!isset($where['conditions'])) {
      $where['conditions'] = $where;
    }

    if (isset($where['conditions']) && is_array($where['conditions'])) {
      if (!isset($where['logic']) || (strtoupper($where['logic']) !== 'OR')) {
        $where['logic'] = 'AND';
      }

      $res = [
        'conditions' => [],
        'logic' => $where['logic']
      ];
      foreach ($where['conditions'] as $key => $f){
        $is_array = is_array($f);
        if ($is_array
          && array_key_exists('conditions', $f)
          && is_array($f['conditions'])
        ) {
          $res['conditions'][] = $this->treatConditions($f, false);
        }
        else {
          if (is_string($key)) {
            // 'id_user' => [1, 2] Will do OR
            if (!$is_array) {
              if (null === $f) {
                $f = [
                  'field' => $key,
                  'operator' => 'isnull'
                ];
              }
              else{
                $f = [
                  'field' => $key,
                  'operator' => is_string($f) && !Str::isUid($f) && !Str::isNumber($f) ? 'LIKE' : '=',
                  'value' => $f
                ];
              }
            }
            elseif (isset($f[0])) {
              $tmp = [
                'conditions' => [],
                'logic' => 'OR'
              ];
              foreach ($f as $v){
                if (null === $v) {
                  $tmp['conditions'][] = [
                    'field' => $key,
                    'operator' => 'isnull'
                  ];
                }
                else{
                  $tmp['conditions'][] = [
                    'field' => $key,
                    'operator' => is_string($f) && !Str::isUid($f) ? 'LIKE' : '=',
                    'value' => $v
                  ];
                }
              }

              $res['conditions'][] = $tmp;
            }
          }
          elseif ($is_array && !X::isAssoc($f) && count($f) >= 2) {
            $tmp = [
              'field' => $f[0],
              'operator' => $f[1]
            ];
            if (isset($f[3])) {
              $tmp['exp'] = $f[3];
            }
            elseif (array_key_exists(2, $f)) {
              if (is_array($f[2])) {
                $tmp = [
                  'conditions' => [],
                  'logic' => 'AND'
                ];
                foreach ($f[2] as $v){
                  if (null === $v) {
                    $tmp['conditions'][] = [
                      'field' => $f[0],
                      'operator' => 'isnotnull'
                    ];
                  }
                  else{
                    $tmp['conditions'][] = [
                      'field' => $f[0],
                      'operator' => $f[1],
                      'value' => $v
                    ];
                  }
                }

                $res['conditions'][] = $tmp;
              }
              elseif ($f[2] === null) {
                $tmp['operator'] = $f[2] === '!=' ? 'isnotnull' : 'isnull';
              }
              else{
                $tmp['value'] = $f[2];
              }
            }

            $f = $tmp;
          }

          if (isset($f['field'])) {
            if (!isset($f['operator'])) {
              $f['operator'] = 'eq';
            }

            $res['conditions'][] = $f;
          }
        }
      }

      if ($full) {
        $tmp = $this->_remove_conditions_value($res);
        $res = [
          'hashed' => $tmp['hashed'],
          'values' => $tmp['values'],
          'where' => $res
        ];
      }

      return $res;
    }

    return false;
  }

  /**
   * Removes values from the given conditions array and returns an array with values and hashed.
   *
   * @param array $where  Conditions
   * @param array $values Values
   * @return array
   */
  private function _remove_conditions_value(array $where, array &$values = []): array
  {
    if (isset($where['conditions'])) {
      foreach ($where['conditions'] as &$f){
        ksort($f);
        if (isset($f['logic'], $f['conditions']) && is_array($f['conditions'])) {
          $tmp = $this->_remove_conditions_value($f, $values);
          $f   = $tmp['hashed'];
        }
        elseif (array_key_exists('value', $f)) {
          $values[] = $f['value'];
          unset($f['value']);
        }
      }
    }

    return [
      'hashed' => $where,
      'values' => $values
    ];
  }

  /**
   * find_references
   *
   * @param $column
   * @param string $db
   * @return array|null
   *
   */
  public function findReferences($column, string $db = ''): ?array
  {
    $changed = false;
    if ($db && ($db !== $this->getCurrent())) {
      $changed = $this->getCurrent();
      $this->change($db);
    }

    $column = $this->colFullName($column);
    $bits   = explode('.', $column);
    if (\count($bits) === 2) {
      array_unshift($bits, $this->getCurrent());
    }

    if (\count($bits) !== 3) {
      return null;
    }

    $refs   = [];
    $schema = $this->modelize();
    $test   = function ($key) use ($bits) {
      return ($key['ref_db'] === $bits[0]) && ($key['ref_table'] === $bits[1]) && ($key['ref_column'] === $bits[2]);
    };
    foreach ($schema as $table => $cfg){
      foreach ($cfg['keys'] as $k){
        if ($test($k)) {
          $refs[] = $table.'.'.$k['columns'][0];
        }
      }
    }

    if ($changed) {
      $this->change($changed);
    }

    return $refs;
  }

  /**
   * find_relations
   *
   * @param $column
   * @param string $db
   * @return array|bool
   */
  public function findRelations($column, string $db = ''): ?array
  {
    $changed = false;
    if ($db && ($db !== $this->getCurrent())) {
      $changed = $this->getCurrent();
      $this->change($db);
    }

    $column = $this->colFullName($column);
    $bits   = explode('.', $column);
    if (\count($bits) === 2) {
      array_unshift($bits, $db ?: $this->current);
    }

    if (\count($bits) !== 3) {
      return null;
    }

    $table = $bits[1];
    if ($schema = $this->modelize()) {
      $refs = [];
      $test = function ($key) use ($bits) {
        return ($key['ref_db'] === $bits[0]) && ($key['ref_table'] === $bits[1]) && ($key['ref_column'] === $bits[2]);
      };
      foreach ($schema as $tf => $cfg) {
        $t = $this->tableSimpleName($tf);
        if ($t !== $table) {
          foreach ($cfg['keys'] as $k) {
            if ($test($k)) {
              foreach ($cfg['keys'] as $k2) {
                // Is not the same table
                if (!$test($k2)
                  // Has a reference
                  && !empty($k2['ref_column'])
                  // and refers to a single column
                  && (\count($k['columns']) === 1)
                  // A unique reference
                  && (\count($k2['columns']) === 1)
                  // To a table with a primary
                  && isset($schema[$this->tableFullName($k2['ref_table'])]['cols'][$k2['ref_column']])
                  // which is a sole column
                  && (\count($schema[$this->tableFullName($k2['ref_table'])]['cols'][$k2['ref_column']]) === 1)
                  // We retrieve the key name
                  && ($key_name = $schema[$this->tableFullName($k2['ref_table'])]['cols'][$k2['ref_column']][0])
                  // which is unique
                  && !empty($schema[$this->tableFullName($k2['ref_table'])]['keys'][$key_name]['unique'])
                ) {
                  if (!isset($refs[$t])) {
                    $refs[$t] = ['column' => $k['columns'][0], 'refs' => []];
                  }

                  $refs[$t]['refs'][$k2['columns'][0]] = $k2['ref_table'].'.'.$k2['ref_column'];
                }
              }
            }
          }
        }
      }

      if ($changed) {
        $this->change($changed);
      }

      return $refs;
    }
  }

  /**
   * Executes a writing statement and return the number of affected rows or return a query object for the reading * statement
   *
   * @param $statement
   * @return false|PDOStatement
   * @throws Exception
   */
  public function query($statement)
  {
    if (!$this->pdo) {
      return null;
    }

    $args = func_get_args();
    // If fancy is false we just use the regular PDO query function
    if (!$this->_fancy) {
      return $this->pdo->query(...$args);
    }

    // The function can be called directly with func_get_args()
    while ((\count($args) === 1) && is_array($args[0])){
      $args = $args[0];
    }

    if (!empty($args[0]) && is_string($args[0])) {
      // The first argument is the statement
      $statement = trim(array_shift($args));

      // Sending a hash as second argument from helper functions will bind it to the saved statement
      if (count($args)
        && is_string($args[0])
        && isset($this->queries[$args[0]])
      ) {
        $hash      = is_string($this->queries[$args[0]]) ? $this->queries[$args[0]] : $args[0];
        $hash_sent = array_shift($args);
      }
      else {
        $hash = $this->makeHash($statement);
      }

      $driver_options = [];
      if (count($args)
        && is_array($args[0])
      ) {
        // Case where drivers are arguments
        if (!array_key_exists(0, $args[0])) {
          $driver_options = array_shift($args);
        }
        // Case where values are in a single argument
        elseif (\count($args) === 1) {
          $args = $args[0];
        }
      }

      /** @var array $params Will become the property last_params each time a query is executed */
      $params     = [
        'statement' => $statement,
        'values' => [],
        'last' => microtime(true)
      ];
      $num_values = 0;
      foreach ($args as $i => $arg){
        if (!is_array($arg)) {
          $params['values'][] = $arg;
          $num_values++;
        }
        elseif (isset($arg[2])) {
          $params['values'][] = $arg[2];
          $num_values++;
        }
      }

      if (!isset($this->queries[$hash])) {
        /** @var int $placeholders The number of placeholders in the statement */
        $placeholders = 0;
        if ($sequences = $this->parseQuery($statement)) {
          /* Or looking for question marks */
          $sequences = array_keys($sequences);
          preg_match_all('/(\?)/', $statement, $exp);
          $placeholders = isset($exp[1]) && is_array($exp[1]) ? \count($exp[1]) : 0;
          while ($sequences[0] === 'OPTIONS'){
            array_shift($sequences);
          }

          $params['kind']      = $sequences[0];
          $params['union']     = isset($sequences['UNION']);
          $params['write']     = !\in_array($params['kind'], self::$read_kinds, true);
          $params['structure'] = \in_array($params['kind'], self::$structure_kinds, true);
        }
        elseif (($this->getEngine() === 'sqlite') && (strpos($statement, 'PRAGMA') === 0)) {
          $params['kind'] = 'PRAGMA';
        }
        else {
          throw new Exception(
            \defined('BBN_IS_DEV') && BBN_IS_DEV
              ? "Impossible to parse the query $statement"
              : 'Impossible to parse the query'
          );
        }

        // This will add to the queries array
        $this->_add_query(
          $hash,
          $statement,
          $params['kind'],
          $placeholders,
          $driver_options
        );
        if (!empty($hash_sent)) {
          $this->queries[$hash_sent] = $hash;
        }
      }
      // The hash of the hash for retrieving a query based on the helper's config's hash
      elseif (is_string($this->queries[$hash])) {
        $hash = $this->queries[$hash];
      }

      $this->_update_query($hash);
      $q =& $this->queries[$hash];
      /* If the number of values is inferior to the number of placeholders we fill the values with the last given value */
      if (!empty($params['values']) && ($num_values < $q['placeholders'])) {
        $this->error(X::_("Placeholders and values don't match"), false);
        $params['values'] = array_merge(
          $params['values'],
          array_fill($num_values, $q['placeholders'] - $num_values, end($params['values']))
        );
        $num_values       = \count($params['values']);
      }

      /* The number of values must match the number of placeholders to bind */
      if ($num_values !== $q['placeholders']) {
        $this->error(
          X::_('Incorrect arguments count (your values: %u, in the statement: %u)', $num_values, $q['placeholders'])."\n\n"
            . $statement."\n\n".'Number of values'.count($params['values']).'Arguments:'
            . print_r(func_get_args(), true)
            . print_r($q, true),
          false
        );
        exit;
      }

      if ($q['exe_time'] === 0) {
        $time = $q['last'];
      }

      // That will always contain the parameters of the last query done

      $this->addStatement($q['sql'], $params);
      // If the statement is a structure modifier we need to clear the cache
      if ($q['structure']) {
        $tmp                = $q;
        $this->queries      = [$hash => $tmp];
        $this->list_queries = [[
          'hash' => $hash,
          'last' => $tmp['last']
        ]];
        unset($tmp);
        /** @todo Clear the cache */
      }

      try{
        // This is a writing statement, it will execute the statement and return the number of affected rows
        if ($q['write']) {
          // A prepared query already exists for the writing
          /** @var \bbn\Db\Query */
          if ($q['prepared']) {
            $r = $q['prepared']->init($params['values'])->execute();
          }
          // If there are no values we can assume the statement doesn't need to be prepared and is just executed
          elseif ($num_values === 0) {
            // Native PDO function which returns the number of affected rows
            $r = $this->pdo->exec($q['sql']);
          }
          // Preparing the query
          else{
            // Native PDO function which will use Db\Query as base class
            /** @var \bbn\Db\Query */
            $q['prepared'] = $this->pdo->prepare($q['sql'], $q['options']);
            $r             = $q['prepared']->execute();
          }
        }
        // This is a reading statement, it will prepare the statement and return a query object
        else {
          if (!$q['prepared']) {
            // Native PDO function which will use Db\Query as base class
            $q['prepared'] = $this->pdo->prepare($q['sql'], $driver_options);
          }
          else {
            // Returns the same Db\Query object
            $q['prepared']->init($params['values']);
          }
        }

        if (!empty($time) && ($q['exe_time'] === 0)) {
          $q['exe_time'] = microtime(true) - $time;
        }
      }
      catch (PDOException $e) {
        $this->error($e);
      }

      if ($this->check()) {
        // So if read statement returns the query object
        if (!$q['write']) {
          return $q['prepared'];
        }

        // If it is a write statement returns the number of affected rows
        if ($q['prepared'] && $q['write']) {
          $r = $q['prepared']->rowCount();
        }

        // If it is an insert statement we (try to) set the last inserted ID
        if (($q['kind'] === 'INSERT') && $r) {
          $this->setLastInsertId();
        }

        if (!isset($r)) {
          X::log($q, 'no_r_in_sql');
          return false;
        }

        return $r ?: false;
      }
    }
  }


  public function executeStatement($statement)
  {
    return $this->pdo->exec($statement);
  }


  public function correctTypes($st)
  {
    if (is_string($st)) {
      if (Str::isBuid($st)) {
        $st = \bin2hex($st);
      }
      else{
        if (Str::isJson($st)) {
          if (\strpos($st, '": ') && ($json = \json_decode($st))) {
            return \json_encode($json);
          }

          return $st;
        }

        $st = \trim(\trim($st, " "), "\t");
        if (Str::isInteger($st)
            && ((substr((string)$st, 0, 1) !== '0') || ($st === '0'))
        ) {
          $tmp = (int)$st;
          if (($tmp < PHP_INT_MAX) && ($tmp > -PHP_INT_MAX)) {
            return $tmp;
          }
        }
        // If it is a decimal, not starting or ending with a zero
        elseif (Str::isDecimal($st)) {
          return (float)$st;
        }

        return \normalizer_normalize($st);
      }
    }
    elseif (is_array($st)) {
      foreach ($st as $k => $v) {
        $st[$k] = $this->correctTypes($v);
      }
    }
    elseif (\is_object($st)) {
      $vs = get_object_vars($st);
      foreach ($vs as $k => $v) {
        $st->$k = $this->correctTypes($v);
      }
    }

    return $st;
  }

  /**
   * Adds the specs of a query to the $queries object.
   *
   * @param string $hash         The hash of the statement.
   * @param string $statement    The SQL full statement.
   * @param string $kind         The type of statement.
   * @param int    $placeholders The number of placeholders.
   * @param array  $options      The driver options.
   */
  private function _add_query(string $hash, string $statement, string $kind, int $placeholders, array $options)
  {
    $now                  = microtime(true);
    $this->queries[$hash] = [
      'sql' => $statement,
      'kind' => $kind,
      'write' => \in_array($kind, self::$write_kinds, true),
      'structure' => \in_array($kind, self::$structure_kinds, true),
      'placeholders' => $placeholders,
      'options' => $options,
      'num' => 0,
      'exe_time' => 0,
      'first' => $now,
      'last' => 0,
      'prepared' => false
    ];
    $this->list_queries[] = [
      'hash' => $hash,
      'last' => $now
    ];
    $num                  = count($this->list_queries);
    while ($num > $this->max_queries) {
      $num--;
      $this->_remove_query($this->list_queries[0]['hash']);
      array_shift($this->list_queries);
    }
  }

  /**
   * @param string $hash
   */
  private function _remove_query(string $hash): void
  {
    if (X::hasProp($this->queries, $hash)) {
      unset($this->queries[$hash]);
      while ($idx = \array_search($hash, $this->queries, true)) {
        unset($this->queries[$idx]);
      }
    }
  }

  /**
   * @param $hash
   * @throws Exception
   * @return void
   */
  private function _update_query($hash)
  {
    if (isset($this->queries[$hash]) && is_array(($this->queries[$hash]))) {
      $last_index                   = count($this->list_queries) - 1;
      $now                          = \microtime(true);
      $this->queries[$hash]['last'] = $now;
      $this->queries[$hash]['num']++;
      if ($this->list_queries[$last_index]['hash'] !== $hash) {
        if (($idx = X::find($this->list_queries, ['hash' => $hash])) !== null) {
          $this->list_queries[$idx]['last'] = $now;
          X::move($this->list_queries, $idx, $last_index);
        }
        else {
          throw new Exception(X::_("Impossible to find the corresponding hash"));
        }
      }
      else {
        $this->list_queries[$last_index]['last'] = $now;
      }

      $num = count($this->list_queries) - 1;
      while (($num > 0)
        && ($now > ($this->list_queries[0]['last'] + $this->length_queries))
      ) {
        $num--;
        if (!is_string($this->list_queries[0]['hash'])) {
          X::log($this->list_queries);
          X::log(count($this->list_queries));
        }

        $this->_remove_query($this->list_queries[0]['hash']);
        array_shift($this->list_queries);
      }

      if (empty($this->queries)) {
        $debug = debug_backtrace();
        X::log($debug, 'db_explained');
        throw new Exception(X::_("The queries object is empty!"));
      }
    }
    else {
      throw new Exception(X::_("Impossible to find the query corresponding to this hash"));
    }
  }

  /**
   * Returns the table's structure's array, either from the cache or from _modelize().
   *
   * @param string $item The item to get
   * @param string $mode The type of item to get (columns, tables, Databases)
   * @param bool $force If true the cache is recreated even if it exists
   * @return array|null
   * @throws Exception
   */
  private function _get_cache(string $item, string $mode = 'columns', bool $force = false): ?array
  {
    $cache_name = $this->_db_cache_name($item, $mode);

    if ($force && isset($this->cache[$cache_name])) {
      unset($this->cache[$cache_name]);
    }

    if (!isset($this->cache[$cache_name])) {
      if ($force || !($tmp = $this->cacheGet($cache_name))) {
        switch ($mode){
          case 'columns':
            $keys = $this->getKeys($item);
            $cols = $this->getColumns($item);
            if (is_array($keys) && is_array($cols)) {
              $tmp = [
                'keys' => $keys['keys'],
                'cols' => $keys['cols'],
                'fields' => $cols
              ];
            }
            break;
          case 'tables':
            $tmp = $this->getTables($item);
            break;
          case 'databases':
            $tmp = $this->getDatabases();
            break;
        }

        if (!isset($tmp) || !is_array($tmp)) {
          $st = "Error while creating the cache for the table $item in mode $mode";
          $this->log($st);
          throw new Exception($st);
        }

        $this->cacheSet($cache_name, '', $tmp, $this->cache_renewal);
      }

      if ($tmp) {
        $this->cache[$cache_name] = $tmp;
      }
    }

    return $this->cache[$cache_name] ?? null;
  }

  /**
   * Gets the cache name of a database structure or part.
   *
   * @param string $item 'db_name' or 'table'
   * @param string $mode 'columns','tables' or 'databases'
   *
   * @return bool|string
   */
  private function _db_cache_name(string $item, string $mode)
  {
    $r = false;
    if ($this->getEngine() === 'sqlite') {
      $h = md5($this->getHost().X::dirname($this->getCurrent() ?? ''));
    }
    else {
      $h = str_replace('/', '-', $this->getConnectionCode() . '-' . $this->getCurrent());
    }

    switch ($mode){
      case 'columns':
        $r = $this->getEngine().'/'.$h.'/'.str_replace('.', '/', $this->tableFullName($item));
        break;
      case 'tables':
        $r = $this->getEngine().'/'.$h.'/' . ($item ?: X::dirname($this->getCurrent()));
        break;
      case 'databases':
        $r = $this->getEngine().'/'.$h.'/_bbn-database';
        break;
    }

    return $r;
  }

  /**
   * Return the table's structure as an indexed array.
   *
   * ```php
   * X::dump($db->modelize("table_users"));
   * // (array) [keys] => Array ( [PRIMARY] => Array ( [columns] => Array ( [0] => userid [1] => userdataid ) [ref_db] => [ref_table] => [ref_column] => [unique] => 1 )     [table_users_userId_userdataId_info] => Array ( [columns] => Array ( [0] => userid [1] => userdataid [2] => info ) [ref_db] => [ref_table] => [ref_column] =>     [unique] => 0 ) ) [cols] => Array ( [userid] => Array ( [0] => PRIMARY [1] => table_users_userId_userdataId_info ) [userdataid] => Array ( [0] => PRIMARY [1] => table_users_userId_userdataId_info ) [info] => Array ( [0] => table_users_userId_userdataId_info ) ) [fields] => Array ( [userid] => Array ( [position] => 1 [null] => 0 [key] => PRI [default] => [extra] => [signed] => 1 [maxlength] => 11 [type] => int ) [userdataid] => Array ( [position] => 2 [null] => 0 [key] => PRI [default] => [extra] => [signed] => 1 [maxlength] => 11 [type] => int ) [info] => Array ( [position] => 3 [null] => 1 [key] => [default] => NULL [extra] => [signed] => 0 [maxlength] => 200 [type] => varchar ) )
   * ```
   *
   * @param null|array|string $table The table's name
   * @param bool $force If set to true will force the modernization to re-perform even if the cache exists
   * @return null|array
   * @throws Exception
   */
  public function modelize($table = null, bool $force = false): ?array
  {
    $r      = [];
    $tables = false;
    $single = false;
    if (empty($table) || ($table === '*')) {
      $tables = $this->getTables();
    }
    elseif (is_string($table)) {
      $single = true;
      $tables = [$table];
    }
    elseif (is_array($table)) {
      $tables = $table;
    }

    if (is_array($tables)) {
      foreach ($tables as $t) {
        if ($full = $this->tableFullName($t)) {
          $r[$full] = $this->_get_cache($full, 'columns', $force);
        }
      }

      if ($single) {
        return end($r);
      }

      return $r;
    }

    return null;
  }

  /****************************************************************
   *                                                              *
   *                                                              *
   *                          TRIGGERS                            *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Enable the triggers' functions
   *
   * @return self
   */
  public function enableTrigger(): self
  {
    $this->_triggers_disabled = false;
    return $this;
  }


  /**
   * Disable the triggers' functions
   *
   * @return self
   */
  public function disableTrigger(): self
  {
    $this->_triggers_disabled = true;
    return $this;
  }


  public function isTriggerEnabled(): bool
  {
    return !$this->_triggers_disabled;
  }


  public function isTriggerDisabled(): bool
  {
    return $this->_triggers_disabled;
  }


  /**
   * Apply a function each time the methods $kind are used
   *
   * @param callable            $function
   * @param array|string|null   $kind     select|insert|update|delete
   * @param array|string|null   $moment   before|after
   * @param null|string|array   $tables   database's table(s) name(s)
   * @return self
   */
  public function setTrigger(callable $function, $kind = null, $moment = null, $tables = '*' ): self
  {
    $kinds   = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
    $moments = ['before', 'after'];
    if (empty($kind)) {
      $kind = $kinds;
    }
    elseif (!is_array($kind)) {
      $kind = (array)strtoupper($kind);
    }
    else{
      $kind = array_map('strtoupper', $kind);
    }

    if (empty($moment)) {
      $moment = $moments;
    }
    else {
      $moment = !is_array($moment) ? (array)strtolower($moment) : array_map('strtolower', $moment);
    }

    foreach ($kind as $k){
      if (\in_array($k, $kinds, true)) {
        foreach ($moment as $m){
          if (array_key_exists($m, $this->_triggers[$k]) && \in_array($m, $moments, true)) {
            if ($tables === '*') {
              $tables = $this->getTables();
            }
            elseif (Str::checkName($tables)) {
              $tables = [$tables];
            }

            if (is_array($tables)) {
              foreach ($tables as $table){
                $t = $this->tableFullName($table);
                if (!isset($this->_triggers[$k][$m][$t])) {
                  $this->_triggers[$k][$m][$t] = [];
                }

                $this->_triggers[$k][$m][$t][] = $function;
              }
            }
          }
        }
      }
    }

    return $this;
  }


  /**
   * @return array
   */
  public function getTriggers(): array
  {
    return $this->_triggers;
  }

  /**
   * Launches a function before or after
   *
   * @param array $cfg
   * @return array
   */
  protected function _trigger(array $cfg): array
  {
    if ($this->_triggers_disabled) {
      if ($cfg['moment'] === 'after') {
        return $cfg;
      }

      $cfg['run']  = 1;
      $cfg['trig'] = 1;
      return $cfg;
    }

    if (!isset($cfg['trig'])) {
      $cfg['trig'] = 1;
    }

    if (!isset($cfg['run'])) {
      $cfg['run'] = 1;
    }

    if (!empty($cfg['tables']) && !empty($this->_triggers[$cfg['kind']][$cfg['moment']])) {
      $table = $this->tableFullName(is_array($cfg['tables']) ? current($cfg['tables']) : $cfg['tables']);
      // Specific to a table
      if (isset($this->_triggers[$cfg['kind']][$cfg['moment']][$table])) {
        foreach ($this->_triggers[$cfg['kind']][$cfg['moment']][$table] as $i => $f){
          if ($f && \is_callable($f)) {
            if (!($tmp = $f($cfg))) {
              $cfg['run']  = false;
              $cfg['trig'] = false;
            }
            else{
              $cfg = $tmp;
            }
          }
        }
      }
    }

    return $cfg;
  }

  /**
   * @param array  $args
   * @param string $kind
   * @return array
   */
  protected function _add_kind(array $args, string $kind = 'SELECT'): ?array
  {
    $kind = strtoupper($kind);
    if (!isset($args[0])) {
      return null;
    }

    if (!is_array($args[0])) {
      array_unshift($args, $kind);
    }
    else {
      $args[0]['kind'] = $kind;
    }

    return $args;
  }

  /**
   * Adds a random primary value when it is absent from the set and present in the fields
   *
   * @param array $cfg
   * @return void
   */
  protected function _add_primary(array &$cfg): void
  {
    // Inserting a row without primary when primary is needed and no auto-increment
    if (!empty($cfg['primary'])
      && empty($cfg['auto_increment'])
      && (($idx = array_search($cfg['primary'], $cfg['fields'], true)) > -1)
      && (count($cfg['values']) === (count($cfg['fields']) - 1))
    ) {
      $val = false;
      switch ($cfg['primary_type']){
        case 'int':
          $val = random_int(
            ceil(10 ** ($cfg['primary_length'] > 3 ? $cfg['primary_length'] - 3 : 1) / 2),
            ceil(10 ** ($cfg['primary_length'] > 3 ? $cfg['primary_length'] : 1) / 2)
          );
          break;
        case 'binary':
          if ($cfg['primary_length'] === 16) {
            $val = $this->getUid();
          }
          break;
      }

      if ($val) {
        array_splice($cfg['values'], $idx, 0, $val);
        $this->setLastInsertId($val);
      }
    }
  }

  /**
   * @returns null|\bbn\Db\Query|int A selection query or the number of affected rows by a writing query
   */
  protected function _exec()
  {
    if ($this->pdo
      && $this->check()
      && ($cfg = $this->processCfg(func_get_args()))
      && !empty($cfg['sql'])
    ) {
      //die(var_dump('0exec cfg', $cfg, func_get_args()));
      $cfg['moment'] = 'before';
      $cfg['trig']   = null;
      if ($cfg['kind'] === 'INSERT') {
        // Add generated primary when inserting a row without primary when primary is needed and no auto-increment
        $this->_add_primary($cfg);
      }

      if (count($cfg['values']) !== count($cfg['values_desc'])) {
        $this->error(X::_('Database error in values count'));
      }

      // Launching the trigger BEFORE execution
      if ($cfg = $this->_trigger($cfg)) {
        if (!empty($cfg['run'])) {
          //$this->log(["TRIGGER OK", $cfg['run'], $cfg['fields']]);
          // Executing the query
          /** @todo Put hash back! */
          //$cfg['run'] = $this->query($cfg['sql'], $cfg['hash'], $cfg['values'] ?? []);
          /** @var \bbn\Db\Query */

          $cfg['run'] = $this->query($cfg['sql'], $this->getQueryValues($cfg));
        }

        if (!empty($cfg['force'])) {
          $cfg['trig'] = 1;
        }
        elseif (null === $cfg['trig']) {
          $cfg['trig'] = (bool)$cfg['run'];
        }

        if ($cfg['trig']) {
          $cfg['moment'] = 'after';
          $cfg           = $this->_trigger($cfg);
        }

        $this->last_cfg = $cfg;
        if (!\in_array($cfg['kind'], self::$write_kinds, true)) {
          return $cfg['run'] ?? null;
        }

        if (isset($cfg['value'])) {
          return $cfg['value'];
        }

        if (isset($cfg['run'])) {
          return $cfg['run'];
        }
      }
    }

    return null;
  }

  /**
   *
   * @param array $args
   * @param bool $force
   * @return array|null
   * @throws Exception
   */
  public function processCfg(array $args, bool $force = false): ?array
  {
    // Avoid confusion when
    while (isset($args[0]) && is_array($args[0])) {
      $args = $args[0];
    }

    if (!empty($args['bbn_db_processed'])) {
      return $args;
    }

    if (empty($args['bbn_db_treated'])) {
      $args = $this->_treat_arguments($args);
    }

    if (isset($args['hash'])) {
      if (isset($this->cfgs[$args['hash']])) {
        $fromHash = array_merge(
          $this->cfgs[$args['hash']], [
            'values' => $args['values'] ?? [],
            'where' => $args['where'] ?? [],
            'filters' => $args['filters'] ?? []
          ]
        );
        if (!empty($fromHash['values'])
          && !empty($fromHash['removed_virtual'])
          &&(($fromHash['kind'] === 'INSERT')
            || ($fromHash['kind'] === 'UPDATE'))
        ) {
          foreach ($fromHash['removed_virtual'] as $i) {
            array_splice($fromHash['values'], $i, 1);
          }
        }

        return $fromHash;
      }

      $tables_full = [];
      $res         = array_merge(
        $args, [
          'tables' => [],
          'values_desc' => [],
          'bbn_db_processed' => true,
          'available_fields' => [],
          'generate_id' => false
        ]
      );
      $models      = [];

      foreach ($args['tables'] as $key => $tab) {
        if (empty($tab)) {
          $this->log(\debug_backtrace());
          throw new Exception("$key is not defined");
        }

        $tfn = $this->tableFullName($tab);

        // 2 tables in the same statement can't have the same idx
        $idx = is_string($key) ? $key : $tfn;
        // Error if they do
        if (isset($tables_full[$idx])) {
          $this->error('You cannot use twice the same table with the same alias'.PHP_EOL.X::getDump($args['tables']), false);
          return null;
        }

        $tables_full[$idx]   = $tfn;
        $res['tables'][$idx] = $tfn;
        if (!isset($models[$tfn]) && ($model = $this->modelize($tfn))) {
          $models[$tfn] = $model;
        }
      }

      if ((\count($res['tables']) === 1)
        && ($tfn = array_values($res['tables'])[0])
        && isset($models[$tfn]['keys']['PRIMARY'])
        && (\count($models[$tfn]['keys']['PRIMARY']['columns']) === 1)
        && ($res['primary'] = $models[$tfn]['keys']['PRIMARY']['columns'][0])
      ) {
        $p                     = $models[$tfn]['fields'][$res['primary']];
        $res['auto_increment'] = isset($p['extra']) && ($p['extra'] === 'auto_increment');
        $res['primary_length'] = $p['maxlength'] ?? null;
        $res['primary_type']   = $p['type'];
        if (($res['kind'] === 'INSERT')
          && !$res['auto_increment']
          && !\in_array($this->colSimpleName($res['primary']), $res['fields'], true)
        ) {
          $res['generate_id'] = true;
          $res['fields'][]    = $res['primary'];
        }
      }

      foreach ($args['join'] as $key => $join){
        if (!empty($join['table'])) {
          $tfn = $this->tableFullName($join['table']);
          if (!isset($models[$tfn]) && ($model = $this->modelize($tfn))) {
            $models[$tfn] = $model;
          }

          $idx               = $join['alias'] ?? $tfn;
          $tables_full[$idx] = $tfn;
          if (isset($join['join'])) {
            foreach ($join['join'] as $j){
              if (!empty($j['table'])) {
                $tfn = $this->tableFullName($j['table']);
                if (!isset($models[$tfn]) && ($model = $this->modelize($tfn))) {
                  $models[$tfn] = $model;
                }
                $idx               = $j['alias'] ?? $tfn;
                $tables_full[$idx] = $tfn;
              }
            }
          }
        }
        else{
          $this->error('Error! The join array must have on and table defined'.PHP_EOL.X::getDump($join), false);
        }
      }

      foreach ($tables_full as $idx => $tfn){
        foreach ($models[$tfn]['fields'] as $col => $cfg){
          $res['available_fields'][$this->colFullName($col, $idx)] = $idx;
          $csn                                             = $this->colSimpleName($col);
          if (!isset($res['available_fields'][$csn])) {
            /*
            $res['available_fields'][$csn] = false;
            }
            else{
            */
            $res['available_fields'][$csn] = $idx;
          }
        }
      }

      foreach ($res['fields'] as $idx => &$col){
        if (!empty($res['union'])
          || strpos($col, '(')
          || strpos($col, '-')
          || strpos($col, "+")
          || strpos($col, '*')
          || strpos($col, "/")
          /*
        strpos($col, '->"$.')  ||
        strpos($col, "->'$.") ||
        strpos($col, '->>"$.')  ||
        strpos($col, "->>'$.") ||
        */
          // string as value
          || preg_match('/^[\\\'\"]{1}[^\\\'\"]*[\\\'\"]{1}$/', $col)
        ) {
          $res['available_fields'][$col] = false;
        }

        if (is_string($idx)) {
          if (!isset($res['available_fields'][$col])) {
            //$this->log($res);
            //$this->log(json_encode($res['available_fields'], JSON_PRETTY_PRINT));
            X::hddump($this->tableFullName('versions'), $this->modelize('versions'), $this->getTables(), $this->getColumns('versions'));
            $this->error("Impossible to find the column $col", false);
            return null;
          }

          $res['available_fields'][$idx] = $res['available_fields'][$col];
        }
      }

      // From here the available fields are defined
      if (!empty($res['filters'])) {
        $this->arrangeConditions($res['filters'], $res);
      }

      unset($col);

      $res['tables_full'] = $tables_full;

      if (($res['kind'] === 'SELECT') && empty($res['fields'])) {
        foreach (array_keys($res['available_fields']) as $f){
          if ($this->isColFullName($f)) {
            $res['fields'][] = $f;
          }
        }
      }

      if (!empty($res['union'])) {
        $res['values'] = [];
        foreach ($res['union'] as $i => $u) {
          $res['union'][$i] = $this->processCfg($u);
          foreach ($res['union'][$i]['join'] as $j){
            if (!empty($j['on'])) {
              $this->getValuesDesc($j['on'], $res['union'][$i], $res['values_desc']);
            }
          }
          $this->getValuesDesc($res['union'][$i]['filters'], $res['union'][$i], $res['values_desc']);
          $this->getValuesDesc($res['union'][$i]['having'], $res['union'][$i], $res['values_desc']);
          array_push($res['values'], ...$res['union'][$i]['values']);
        }
      }

      if (in_array($res['kind'], ['INSERT', 'UPDATE'])) {
        $res = $this->removeVirtual($res);
        foreach ($res['fields'] as $i => $name) {
          $desc = [
            'name' => $name
          ];
          if (isset($models, $res['available_fields'][$name])) {
            $t = $res['available_fields'][$name];
            if (isset($tables_full[$t])
              && ($model = $models[$tables_full[$t]]['fields'])
              && ($fname = $this->colSimpleName($name))
              && !empty($model[$fname]['type'])
            ) {
              $desc['type']      = $model[$fname]['type'];
              $desc['maxlength'] = $model[$fname]['maxlength'] ?? null;
            }
          }
          if (isset($res['values'][$i]) && is_array($res['values'][$i])) {
            if ((count($res['values'][$i]) !== 2) || !$res['values'][$i][1]) {
              X::log([$res['tables'], $res['values']], 'arrays_in_db_write');
              throw new Exception(X::_("Using an array for insert/update value is allowed only for expressions with a 2 value array, the second value being the expression"));
            }

            $desc['type'] = 'exp';
          }

          $res['values_desc'][] = $desc;
        }
      }

      foreach ($res['join'] as $r){
        if (!empty($r['join'])) {
          foreach ($r['join'] as $j){
            if (!empty($j['on'])) {
              $this->getValuesDesc($j['on'], $res, $res['values_desc']);
            }
          }
        }

        if (!empty($r['on'])) {
          $this->getValuesDesc($r['on'], $res, $res['values_desc']);
        }
      }

      $this->getValuesDesc($res['filters'], $res, $res['values_desc']);
      $this->getValuesDesc($res['having'], $res, $res['values_desc']);

      switch ($res['kind']){
        case 'SELECT':
          if (!empty($res['union'])) {
            $res['select_st'] = $this->getUnion($res);
            $res['sql'] = $res['select_st'];
            //die(json_encode([$res, $this->processCfg(['kind' => 'select', 'table' => 'bbn_users', 'fields' => [], 'where' => ['id' => 2]])]));
          }
          elseif ($res['select_st'] = $this->getSelect($res)) {
            $res['sql'] = $res['select_st'];
          }
          break;
        case 'INSERT':
          if ($res['insert_st'] = $this->getInsert($res)) {
            $res['sql'] = $res['insert_st'];
          }

          //var_dump($res);
          break;
        case 'UPDATE':
          if ($res['update_st'] = $this->getUpdate($res)) {
            $res['sql'] = $res['update_st'];
          }
          break;
        case 'DELETE':
          if ($res['delete_st'] = $this->getDelete($res)) {
            $res['sql'] = $res['delete_st'];
          }
          break;
      }

      $res['join_st']   = $this->getJoin($res);
      $res['where_st']  = $this->getWhere($res);
      $res['group_st']  = $this->getGroupBy($res);
      $res['having_st'] = $this->getHaving($res);

      if (empty($res['count'])
        && (count($res['fields']) === 1)
        && (self::isAggregateFunction(reset($res['fields'])))
      ) {
        $res['order_st'] = '';
        $res['limit_st'] = '';
      }
      else {
        $res['order_st'] = $res['count'] ? '' : $this->getOrder($res);
        $res['limit_st'] = $res['count'] ? '' : $this->getLimit($res);
      }

      if (!empty($res['sql'])) {
        $res['sql'] .= $res['join_st'].$res['where_st'].$res['group_st'];
        if ($res['count'] && $res['group_by']) {
          $res['sql'] .= ') AS t '.PHP_EOL;
        }

        $res['sql']           .= $res['having_st'].$res['order_st'].$res['limit_st'];
        $res['statement_hash'] = $this->makeHash($res['sql']);
        $this->cfgs[$res['hash']] = $res;
      }

      return $res;
    }

    $this->error('Impossible to process the config (no hash)'.PHP_EOL.print_r($args, true), false);
    return null;
  }

  /**
   * @param array $cfg
   * @return array|null
   * @throws Exception
   */
  public function reprocessCfg(array $cfg): ?array
  {
    unset($cfg['bbn_db_processed']);
    unset($cfg['bbn_db_treated']);

    if (isset($cfg['hash'])) {
      unset($this->cfgs[$cfg['hash']]);
    }

    $tmp = $this->processCfg($cfg, true);

    if (!empty($cfg['values']) && (count($cfg['values']) === count($tmp['values']))) {
      $tmp = array_merge($tmp, ['values' => $cfg['values']]);
    }

    return $tmp;
  }

  /**
   * Normalizes arguments by making it a uniform array.
   *
   * <ul><h3>The array will have the following indexes:</h3>
   * <li>fields</li>
   * <li>where</li>
   * <li>filters</li>
   * <li>order</li>
   * <li>limit</li>
   * <li>start</li>
   * <li>join</li>
   * <li>group_by</li>
   * <li>having</li>
   * <li>values</li>
   * <li>hashed_join</li>
   * <li>hashed_where</li>
   * <li>hashed_having</li>
   * <li>php</li>
   * <li>done</li>
   * </ul>
   *
   * @todo Check for the tables and column names legality!
   *
   * @param $cfg
   * @return array
   */
  protected function _treat_arguments($cfg): array
  {
    while (isset($cfg[0]) && is_array($cfg[0])){
      $cfg = $cfg[0];
    }

    if (is_array($cfg)
      && array_key_exists('bbn_db_treated', $cfg)
      && ($cfg['bbn_db_treated'] === true)
    ) {
      return $cfg;
    }

    $res = [
      'kind' => 'SELECT',
      'fields' => [],
      'where' => [],
      'order' => [],
      'limit' => 0,
      'start' => 0,
      'group_by' => [],
      'having' => [],
    ];

    if (X::isAssoc($cfg)) {
      if (isset($cfg['table']) && !isset($cfg['tables'])) {
        $cfg['tables'] = $cfg['table'];
        unset($cfg['table']);
      }

      $res = array_merge($res, $cfg);
    }
    elseif (count($cfg) > 1) {
      $res['kind']   = strtoupper($cfg[0]);
      $res['tables'] = $cfg[1];
      if (isset($cfg[2])) {
        $res['fields'] = $cfg[2];
      }

      if (isset($cfg[3])) {
        $res['where'] = $cfg[3];
      }

      if (isset($cfg[4])) {
        $res['order'] = is_string($cfg[4]) ? [$cfg[4] => 'ASC'] : $cfg[4];
      }

      if (isset($cfg[5]) && Str::isInteger($cfg[5])) {
        $res['limit'] = $cfg[5];
      }

      if (isset($cfg[6]) && !empty($res['limit'])) {
        $res['start'] = $cfg[6];
      }
    }

    $res           = array_merge(
      $res, [
        'aliases' => [],
        'values' => [],
        'filters' => [],
        'join' => [],
        'hashed_join' => [],
        'hashed_where' => [],
        'hashed_having' => [],
        'bbn_db_treated' => true
      ]
    );
    $res['kind']   = strtoupper($res['kind']);
    $res['write']  = \in_array($res['kind'], self::$write_kinds, true);
    $res['ignore'] = $res['write'] && !empty($res['ignore']);
    $res['count']  = !$res['write'] && !empty($res['count']);

    
    if (!empty($res['tables'])) {
      if (!is_array($res['tables'])) {
        $res['tables'] = is_string($res['tables']) ? [$res['tables']] : [];
      }

      foreach ($res['tables'] as $i => $t){
        if (!is_string($t)) {
          X::log([$cfg, debug_backtrace()], 'db_explained');
          throw new Exception("Impossible to identify the tables, check the log");
        }

        $res['tables'][$i] = $this->tableFullName($t);
      }
    }
    elseif (empty($res['union'])) {
      throw new \Error(X::_('No table given'));
    }

    if (!empty($res['fields'])) {
      if (is_string($res['fields'])) {
        $res['fields'] = [$res['fields']];
      }
    }
    elseif (!empty($res['columns'])) {
      $res['fields'] = (array)$res['columns'];
    }
    elseif (!empty($res['field'])) {
      $res['fields'] = is_string($res['field']) ? [$res['field']] : $res['field'];
    }

    if (!empty($res['fields'])) {
      if ($res['kind'] === 'SELECT') {
        foreach ($res['fields'] as $k => $col) {
          if (is_string($k)) {
            $res['aliases'][$col] = $k;
          }
        }
      }
      elseif ((($res['kind'] === 'INSERT') || ($res['kind'] === 'UPDATE'))
        && is_string(array_keys($res['fields'])[0])
      ) {
        $res['values'] = array_values($res['fields']);
        $res['fields'] = array_keys($res['fields']);
      }
    }

    if (!is_array($res['group_by'])) {
      $res['group_by'] = empty($res['group_by']) ? [] : [$res['group_by']];
    }

    if (!is_array($res['where'])) {
      $res['where'] = [];
    }

    if (!is_array($res['order'])) {
      $res['order'] = is_string($res['order']) ? [$res['order'] => 'ASC'] : [];
    }

    if (!Str::isInteger($res['limit'])) {
      unset($res['limit']);
    }

    if (!Str::isInteger($res['start'])) {
      unset($res['start']);
    }

    if (!empty($cfg['join'])) {
      foreach ($cfg['join'] as $k => $join){
        if (is_array($join)) {
          if (is_string($k)) {
            if (empty($join['table'])) {
              $join['table'] = $k;
            }
            elseif (empty($join['alias'])) {
              $join['alias'] = $k;
            }
          }

          if (!isset($join['type'])) {
            $join['type'] = 'right';
          }

          if (isset($join['table'], $join['on']) && ($tmp = $this->treatConditions($join['on'], false))) {

            $res['join'][] = array_merge($join, ['on' => $tmp]);
          }
        }
      }
    }

    if ($tmp = $this->treatConditions($res['where'], false)) {
      $res['filters'] = $tmp;
    }

    if (!empty($res['having']) && ($tmp = $this->treatConditions($res['having'], false))) {
      $res['having'] = $tmp;
    }

    if (!empty($res['group_by'])) {
      $this->_adapt_filters($res);
    }

    if (!empty($res['join'])) {
      $new_join = [];
      foreach ($res['join'] as $k => $join ){
        $new_item = $join;
        if (!empty($join['join'])) {
          $new_join2 = [];
          foreach ($join['join'] as $join2 ){
            if ($tmp = $this->treatConditions($join2['on'])) {
              $new_item2 = $join2;
              $new_item2['on']       = $tmp['where'];
              $res['hashed_join'][] = $tmp['hashed'];
              if (!empty($tmp['values'])) {
                foreach ($tmp['values'] as $v){
                  $res['values'][] = $v;
                }
              }
    
              $new_join2[] = $new_item2;
            }
          }

          $new_item['join'] = $new_join2;
        }

        if ($tmp = $this->treatConditions($new_item['on'])) {
          $new_item['on']       = $tmp['where'];
          $res['hashed_join'][] = $tmp['hashed'];
          if (!empty($tmp['values'])) {
            foreach ($tmp['values'] as $v){
              $res['values'][] = $v;
            }
          }

          $new_join[] = $new_item;
        }
      }

      $res['join'] = $new_join;
    }

    if (!empty($res['filters']) && ($tmp = $this->treatConditions($res['filters']))) {
      $res['filters']      = $tmp['where'];
      $res['hashed_where'] = $tmp['hashed'];
      if (is_array($tmp) && isset($tmp['values'])) {
        foreach ($tmp['values'] as $v){
          $res['values'][] = $v;
        }
      }
    }

    if (!empty($res['having']) && ($tmp = $this->treatConditions($res['having']))) {
      $res['having']        = $tmp['where'];
      $res['hashed_having'] = $tmp['hashed'];
      foreach ($tmp['values'] as $v){
        $res['values'][] = $v;
      }
    }

    $res['hash'] = $cfg['hash'] ?? $this->makeHash(
        $res['kind'],
        $res['ignore'],
        $res['count'],
        $res['tables'],
        $res['fields'],
        $res['hashed_join'],
        $res['hashed_where'],
        $res['hashed_having'],
        $res['group_by'],
        $res['order'],
        $res['limit'] ?? 0,
        $res['start'] ?? 0
      );
    return $res;
  }

  /**
   * @param $cfg
   * @return void
   */
  protected function _adapt_filters(&$cfg): void
  {
    if (!empty($cfg['filters'])) {
      [$cfg['filters'], $having] = $this->_adapt_bit($cfg, $cfg['filters']);
      if (empty($cfg['having']['conditions'])) {
        $cfg['having'] = $having;
      }
      else {
        $cfg['having'] = [
          'logic' => 'AND',
          'conditions' => [
            $cfg['having'],
            $having
          ]
        ];
      }
    }
  }

  /**
   * @param $cfg
   * @param $where
   * @param array $having
   * @return array|void
   */
  protected function _adapt_bit($cfg, $where, array $having = [])
  {
    if (X::hasProps($where, ['logic', 'conditions'])) {
      $new = [
        'logic' => $where['logic'],
        'conditions' => []
      ];
      foreach ($where['conditions'] as $c) {
        $is_aggregate = false;
        if (isset($c['field'])) {
          $is_aggregate = self::isAggregateFunction($c['field']);
          if (!$is_aggregate && isset($cfg['fields'][$c['field']])) {
            $is_aggregate = self::isAggregateFunction($cfg['fields'][$c['field']]);
          }
        }

        if (!$is_aggregate && isset($c['exp'])) {
          $is_aggregate = self::isAggregateFunction($c['exp']);
          if (!$is_aggregate && isset($cfg['fields'][$c['exp']])) {
            $is_aggregate = self::isAggregateFunction($cfg['fields'][$c['exp']]);
          }
        }

        if (!$is_aggregate) {
          if (X::hasProps($c, ['conditions', 'logic'])) {
            $tmp = $this->_adapt_bit($cfg, $c, $having);
            if (!empty($tmp[0]['conditions'])) {
              $new['conditions'][] = $tmp[0];
            }

            if (!empty($tmp[1]['conditions'])) {
              $having = $tmp[1];
            }

          }
          else {
            $new['conditions'][] = $c;
          }
        }
        else {
          if (!isset($having['conditions'])) {
            $having = [
              'logic' => $where['logic'],
              'conditions' => []
            ];
          }

          if (isset($cfg['aliases'][$c['field']])) {
            $c['field'] = $cfg['aliases'][$c['field']];
          }
          elseif (isset($c['exp'], $cfg['aliases'][$c['exp']])) {
            $c['exp'] = $cfg['aliases'][$c['exp']];
          }

          $having['conditions'][] = $c;
        }
      }

      return [$new, $having];
    }
  }

  /**
   * @param array $args
   * @return array
   */
  protected function _set_limit_1(array $args): array
  {
    if (is_array($args[0])
      && (isset($args[0]['tables']) || isset($args[0]['table']))
    ) {
      $args[0]['limit'] = 1;
    }
    else {
      $start = $args[4] ?? 0;
      $num   = count($args);
      // Adding fields
      if ($num === 1) {
        $args[] = [];
        $num++;
      }

      // Adding where
      if ($num === 2) {
        $args[] = [];
        $num++;
      }

      // Adding order
      if ($num === 3) {
        $args[] = [];
        $num++;
      }

      if ($num === 4) {
        $args[] = 1;
        $num++;
      }

      $args   = array_slice($args, 0, 5);
      $args[] = $start;
    }

    return $args;
  }

  /**
   * Return an object with all the properties of the statement and where it is carried out.
   *
   * ```php
   * X::dump($db->addStatement('SELECT name FROM table_users'));
   * // (self)
   * ```
   *
   * @param string $statement
   * @param $params
   * @return self
   */
  protected function addStatement(string $statement, $params): self
  {
    $this->last_real_query  = $statement;
    $this->last_real_params = $params;

    if ($this->_last_enabled) {
      $this->last_query  = $statement;
      $this->last_params = $params;
    }

    return $this;
  }

  public function getRealLastParams(): array
  {
    return $this->last_real_params;
  }

  /**
   * @return string|null
   */
  public function realLast(): ?string
  {
    return $this->last_real_query;
  }

  public function getLastValues(): ?array
  {
    return $this->last_params ? $this->last_params['values'] : null;
  }

  public function getLastParams(): ?array
  {
    return $this->last_params;
  }

  /**
   * @return void
   */
  public function enableLast()
  {
    $this->_last_enabled = true;
  }

  /**
   * @return void
   */
  public function disableLast()
  {
    $this->_last_enabled = false;
  }

  /****************************************************************
   *                                                              *
   *                                                              *
   *                           UTILITIES                          *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Changes the value of last_insert_id (used by history).
   *
   * @param string $id
   * @return $this
   */
  public function setLastInsertId($id = ''): self
  {
    if ($id === '') {
      if ($this->id_just_inserted) {
        $id                     = $this->id_just_inserted;
        $this->id_just_inserted = null;
      }
      else{
        $id = $this->pdo->lastInsertId();
        if (is_string($id) && Str::isInteger($id) && ((int)$id != PHP_INT_MAX)) {
          $id = (int)$id;
        }
      }
    }
    else{
      $this->id_just_inserted = $id;
    }

    $this->last_insert_id = $id;
    return $this;
  }

  /**
   * Return the last inserted ID.
   *
   * @return false|mixed|string
   */
  public function lastId()
  {
    if ($this->last_insert_id) {
      return Str::isBuid($this->last_insert_id) ? bin2hex($this->last_insert_id) : $this->last_insert_id;
    }

    return false;
  }

  /**
   * Return the last query for this connection.
   *
   * ```php
   * X::dump($db->last());
   * // (string) INSERT INTO `db_example.table_user` (`name`) VALUES (?)
   * ```
   *
   * @return string
   */
  public function last(): ?string
  {
    return $this->last_query;
  }

  /**
   * @return int
   */
  public function countQueries(): int
  {
    return \count($this->queries);
  }

  /**
   * Deletes all the queries recorded and returns their (ex) number.
   *
   * @return int
   */
  public function flush(): int
  {
    $num                = \count($this->queries);
    $this->queries      = [];
    $this->list_queries = [];
    return $num;
  }

  /****************************************************************
   *                                                              *
   *                                                              *
   *                      INTERNAL METHODS                        *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Makes a string that will be the id of the request.
   *
   * @return string
   *
   */
  protected function makeHash(): string
  {
    $args = func_get_args();
    if ((\count($args) === 1) && is_array($args[0])) {
      $args = $args[0];
    }

    $st = '';
    foreach ($args as $a){
      $st .= is_array($a) ? serialize($a) : '--'.$a.'--';
    }

    return $this->hash_contour.md5($st).$this->hash_contour;
  }

  /**
   * Makes and sets the hash.
   *
   * @return void
   */
  protected function setHash()
  {
    $this->hash = $this->makeHash(...func_get_args());
  }

  /**
   * Gets the created hash.
   *
   * ```php
   * X::dump($db->getHash());
   * // (string) 3819056v431b210daf45f9b5dc2
   * ```
   * @return string
   */
  public function getHash(): string
  {
    return $this->hash;
  }

  /**
   * Starts fancy stuff.
   *
   * ```php
   * $db->startFancyStuff();
   * // (self)
   * ```
   * @return self
   */
  public function startFancyStuff(): self
  {
    $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [\bbn\Db\Query::class, [$this]]);
    $this->_fancy = 1;

    return $this;
  }

  /**
   * Stops fancy stuff.
   *
   * ```php
   *  $db->stopFancyStuff();
   * // (self)
   * ```
   *
   * @return self
   */
  public function stopFancyStuff(): self
  {
    $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
    $this->_fancy = false;

    return $this;
  }

  /**
   * @param array $args
   * @param int $start
   * @return array
   */
  protected function _set_start(array $args, int $start): array
  {
    if (is_array($args[0])
      && (isset($args[0]['tables']) || isset($args[0]['table']))
    ) {
      $args[0]['start'] = $start;
    }
    else {
      if (isset($args[5])) {
        $args[5] = $start;
      }
      else{
        while (count($args) < 6){
          switch (count($args)){
            case 1:
            case 2:
            case 3:
              $args[] = [];
              break;
            case 4:
              $args[] = 1;
              break;
            case 5:
              $args[] = $start;
              break;
          }
        }
      }
    }

    return $args;
  }

  /**
   * Retrieves a query array based on its hash.
   *
   * @param string $hash
   * @return array|null
   */
  public function retrieveQuery(string $hash): ?array
  {
    if (isset($this->queries[$hash])) {
      if (is_string($this->queries[$hash])) {
        $hash = $this->queries[$hash];
      }

      return $this->queries[$hash];
    }

    return null;
  }

  /**
   * @param array $cfg
   * @param array $conditions
   * @param array|null $res
   * @return array
   */
  public function extractFields(array $cfg, array $conditions, array &$res = null)
  {
    if (null === $res) {
      $res = [];
    }

    if (isset($conditions['conditions'])) {
      $conditions = $conditions['conditions'];
    }

    foreach ($conditions as $c) {
      if (isset($c['conditions'])) {
        $this->extractFields($cfg, $c['conditions'], $res);
      }
      else {
        if (isset($c['field'], $cfg['available_fields'][$c['field']])) {
          $res[] = $cfg['available_fields'][$c['field']] ? $this->colFullName($c['field'], $cfg['available_fields'][$c['field']]) : $c['field'];
        }

        if (isset($c['exp'], $cfg['available_fields'][$c['exp']])) {
          $res[] = $cfg['available_fields'][$c['exp']] ? $this->colFullName($c['exp'], $cfg['available_fields'][$c['exp']]) : $c['exp'];
        }
      }
    }

    return $res;
  }

  /**
   * Retrieve an array of specific filters among the existing ones.
   *
   * @param array $cfg
   * @param $field
   * @param null  $operator
   * @return array|null
   */
  public function filterFilters(array $cfg, $field, $operator = null): ?array
  {
    if (isset($cfg['filters'])) {
      $f = function ($cond, &$res = []) use (&$f, $field, $operator) {
        foreach ($cond as $c){
          if (isset($c['conditions'])) {
            $f($c['conditions'], $res);
          }
          elseif (($c['field'] === $field) && (!$operator || ($operator === $c['operator']))) {
            $res[] = $c;
          }
        }

        return $res;
      };
      return isset($cfg['filters']['conditions']) ? $f($cfg['filters']['conditions']) : [];
    }

    return null;
  }

  /****************************************************************
   *                                                              *
   *                                                              *
   *                       QUERY HELPERS                          *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Executes the given query with given vars, and extracts the first cell's result.
   *
   * ```php
   * X::dump($db->getOne("SELECT name FROM table_users WHERE id>?", 138));
   * // (string) John
   * ```
   *
   * @param string query
   * @param mixed values
   * @return mixed
   */
  public function getOne()
  {
    /** @var \bbn\Db\Query $r */
    if ($r = $this->query(...func_get_args())) {
      return $r->fetchColumn(0);
    }

    return false;
  }

  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   *
   * ```php
   * X::dump($db->getKeyVal("SELECT name,id_group FROM table_users"));
   * /*
   * (array)[
   *      "John" => 1,
   *      "Michael" => 1,
   *      "Barbara" => 1
   *        ]
   *
   * X::dump($db->getKeyVal("SELECT name, surname, id FROM table_users WHERE id > 2 "));
   * /*
   * (array)[
   *         "John" => [
   *          "surname" => "Brown",
   *          "id" => 3
   *         ],
   *         "Michael" => [
   *          "surname" => "Smith",
   *          "id" => 4
   *         ]
   *        ]
   * ```
   *
   * @param string query
   * @param mixed values
   * @return null|array
   */
  public function getKeyVal(): ?array
  {
    if ($r = $this->query(...func_get_args())) {
      /** @var Query $r */
      if ($rows = $r->getRows()) {
        return X::indexByFirstVal($rows);
      }

      return [];
    }

    return null;
  }

  /**
   * Return an array with the values of single field resulting from the query.
   *
   * ```php
   * X::dump($db->getColArray("SELECT id FROM table_users"));
   * /*
   * (array)[1, 2, 3, 4]
   * ```
   *
   * @param string query
   * @param mixed values
   * @return array
   */
  public function getColArray(): array
  {
    if ($r = $this->getByColumns(...func_get_args())) {
      return array_values(current($r));
    }

    return [];
  }

  /****************************************************************
   *                                                              *
   *                                                              *
   *                 READ HELPERS WITH TRIGGERS                   *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Returns the first row resulting from the query as an object.
   *
   * ```php
   * X::dump($db->select('table_users', ['name', 'surname'], [['id','>','2']]));
   * /*
   * (object){
   *   "name": "John",
   *   "surname": "Smith",
   * }
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $fields The fields' name
   * @param array $where The "where" condition
   * @param array $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return null|\stdClass
   * @throws Exception
   */
  public function select($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?\stdClass
  {
    $args = $this->_add_kind($this->_set_limit_1(func_get_args()));
    if ($r = $this->_exec(...$args)) {
      if (!is_object($r)) {
        $this->log([$args, $this->processCfg($args)]);
      }
      else{
        return $r->getObject();
      }
    }

    return null;
  }

  /**
   * Return table's rows resulting from the query as an array of objects.
   *
   * ```php
   * X::dump($db->selectAll("tab_users", ["id", "name", "surname"],[["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *        Object stdClass: df {
   *          "id" => 2,
   *          "name" => "John",
   *          "surname" => "Smith",
   *          },
   *        Object stdClass: df {
   *          "id" => 3,
   *          "name" => "Thomas",
   *          "surname" => "Jones",
   *         }
   *        ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $fields The fields' name
   * @param array $where The "where" condition
   * @param array $order The "order" condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return null|array
   * @throws Exception
   */
  public function selectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind(func_get_args()))) {
      return $r->getObjects();
    }

    return null;
  }

  /**
   * Return the first row resulting from the query as a numeric array.
   *
   * ```php
   * X::dump($db->iselect("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *          4,
   *         "Jack",
   *          "Stewart"
   *        ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $fields The fields' name
   * @param array $where The "where" condition
   * @param array $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return array|null
   * @throws Exception
   */
  public function iselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind($this->_set_limit_1(func_get_args())))) {
      return $r->getIrow();
    }

    return null;
  }


  /**
   * Return the searched rows as an array of numeric arrays.
   *
   * ```php
   * X::dump($db->iselectAll("tab_users", ["id", "name", "surname"], [["id", ">", 1]],["id" => "ASC"],2));
   * /*
   * (array)[
   *          [
   *            2,
   *            "John",
   *            "Smith",
   *          ],
   *          [
   *            3,
   *            "Thomas",
   *            "Jones",
   *          ]
   *        ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $fields The fields's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return array|null
   * @throws Exception
   */
  public function iselectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind(func_get_args()))) {
      return $r->getIrows();
    }

    return null;
  }

  public function countUnion(array $union, array $where = []): ?int
  {
    if (!X::isAssoc($union)) {
      $union = [
        'kind' => 'SELECT',
        'union' => $union,
        'where' => $where,
        'fields' => ['COUNT(*)']
      ];
    }

    if (!empty($union['union']) && is_object($r = $this->_exec($union))) {
      $a = $r->getIrow();
      return $a ? (int)$a[0] : null;
    }

    return null;
  }

  public function selectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    if ($r = $this->_exec($this->adaptUnionParams($union, $fields, $where, $order, $limit, $start))) {
      return $r->getObjects();
    }

    return [];
  }

  public function iselectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    if ($r = $this->_exec($this->adaptUnionParams($union, $fields, $where, $order, $limit, $start))) {
      return $r->getIrows();
    }

    return [];
  }

  public function rselectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    if ($r = $this->_exec($this->adaptUnionParams($union, $fields, $where, $order, $limit, $start))) {
      return $r->getRows();
    }

    return [];
  }

  protected function adaptUnionParams(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0)
  {
    if (!X::isAssoc($union)) {
      $union = [
        'kind' => 'SELECT',
        'union' => $union,
        'fields' => $fields,
        'where' => $where,
        'order' => $order,
        'limit' => $limit,
        'start' => $start
      ];
    }

    if (!empty($union['union'])) {
      return $union;
    }

    return null;
  }


  /**
   * Return the first row resulting from the query as an indexed array.
   *
   * ```php
   * X::dump($db->rselect("tab_users", ["id", "name", "surname"], ["id", ">", 1], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          "id" => 4,
   *          "name" => "John",
   *          "surname" => "Smith"
   *         ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $fields The fields' name
   * @param array $where The "where" condition
   * @param array $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return null|array
   * @throws Exception
   */
  public function rselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind($this->_set_limit_1(func_get_args())))) {
      return $r->getRow();
    }

    return null;
  }

  /**
   * Return table's rows as an array of indexed arrays.
   *
   * ```php
   * X::dump($db->rselectAll("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          [
   *          "id" => 2,
   *          "name" => "John",
   *          "surname" => "Smith",
   *          ],
   *          [
   *          "id" => 3,
   *          "name" => "Thomas",
   *          "surname" => "Jones",
   *          ]
   *        ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $fields The fields' name
   * @param array $where The "where" condition
   * @param array $order condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return null|array
   * @throws Exception
   */
  public function rselectAll($table, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind(func_get_args()))) {
      return $r->getRows();
    }

    return [];
  }


  /**
   * Return a single value
   *
   * ```php
   * X::dump($db->selectOne("tab_users", "name", [["id", ">", 1]], ["id" => "DESC"], 2));
   *  (string) 'Michael'
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param null $field The field's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return mixed
   * @throws Exception
   */
  public function selectOne($table, $field = null, array $where = [], array $order = [], int $start = 0)
  {
    if ($r = $this->_exec(...$this->_add_kind($this->_set_limit_1(func_get_args())))) {
      if (method_exists($r, 'getIrow')) {
        return ($a = $r->getIrow()) ? $a[0] : false;
      }

      $this->log('ERROR IN SELECT_ONE', $this->last_cfg, $r, $this->_add_kind($this->_set_limit_1(func_get_args())));
    }

    return false;
  }


  /**
   * Return the number of records in the table corresponding to the $where condition (non mandatory).
   *
   * ```php
   * X::dump($db->count('table_users', ['name' => 'John']));
   * // (int) 2
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array        $where The "where" condition
   * @return int|null
   */
  public function count($table, array $where = []): ?int
  {
    $args          = is_array($table) && (isset($table['tables']) || isset($table['table'])) ? $table : [
      'tables' => [$table],
      'where' => $where
    ];
    $args['count'] = true;
    if (!empty($args['bbn_db_processed'])) {
      unset($args['bbn_db_processed']);
    }

    if (\is_object($r = $this->_exec($args))) {
      $a = $r->getIrow();
      return $a ? (int)$a[0] : null;
    }

    return null;
  }

  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   * Return the same value as "get_key_val".
   *
   * ```php
   * X::dump($db->selectAllByKeys("table_users", ["name","id","surname"], [["id", ">", "1"]], ["id" => "ASC"]);
   * /*
   * (array)[
   *        "John" => [
   *          "surname" => "Brown",
   *          "id" => 3
   *          ],
   *        "Michael" => [
   *          "surname" => "Smith",
   *          "id" => 4
   *        ]
   *      ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $fields The fields's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition
   * @param int $limit The $limit condition, default: 0
   * @param int $start The $limit condition, default: 0
   * @return array|null
   * @throws Exception
   */
  public function selectAllByKeys($table, array $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    if ($rows = $this->rselectAll($table, $fields, $where, $order, $limit, $start)) {
      return X::indexByFirstVal($rows);
    }

    return $this->check() ? [] : null;
  }


  /**
   * Return an array with the count of values corresponding to the where conditions.
   *
   * ```php
   * X::dump($db->stat('table_user', 'name', ['name' => '%n']));
   * /* (array)
   * [
   *  [
   *      "num" => 1,
   *      "name" => "alan",
   *  ], [
   *      "num" => 1,
   *      "name" => "karen",
   *  ],
   * ]
   * ```
   *
   * @param string $table The table's name or a configuration array.
   * @param string $column The field's name.
   * @param array $where The "where" condition.
   * @param array $order The "order" condition.
   * @return array|null
   * @throws Exception
   */
  public function stat(string $table, string $column, array $where = [], array $order = []): ?array
  {
    if ($this->check()) {
      return $this->rselectAll(
        [
          'tables' => [$table],
          'fields' => [
            $column,
            'num' => 'COUNT(*)'
          ],
          'where' => $where,
          'order' => $order,
          'group_by' => [$column]
        ]
      );
    }

    return null;
  }

  /**
   * Return a count of identical values in a field as array, Reporting a structure type 'num' - 'val'.
   *
   * ```php
   * X::dump($db->countFieldValues('table_users','surname',[['name','=','John']]));
   * // (array) ["num" => 2, "val" => "John"]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param null|string  $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array|null
   */
  public function countFieldValues($table, string $field = null, array $where = [], array $order = []): ?array
  {
    if (is_array($table) && is_array($table['fields']) && count($table['fields'])) {
      $args  = $table;
      $field = array_values($table['fields'])[0];
    }
    else{
      $args = [
        'tables' => [$table],
        'where' => $where,
        'order' => $order
      ];
    }

    $args = array_merge(
      $args, [
        'kind' => 'SELECT',
        'fields' => [
          'val' => $field,
          'num' => 'COUNT(*)'
        ],
        'group_by' => [$field]
      ]
    );
    return $this->rselectAll($args);
  }

  /**
   * Return a numeric indexed array with the values of the unique column ($field) from the selected $table
   *
   * ```php
   * X::dump($db->getColumnValues('table_users','surname',['id','>',1]));
   * /*
   * array [
   *    "Smith",
   *    "Jones",
   *    "Williams",
   *    "Taylor"
   * ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|null $field The field's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition
   * @param int $limit
   * @param int $start
   * @return array
   */
  public function getColumnValues($table, string $field = null,  array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    $res = null;
    if ($this->check()) {
      $res = [];
      if (is_array($table) && isset($table['fields']) && is_array($table['fields']) && !empty($table['fields'][0])) {
        array_splice($table['fields'], 0, 1, 'DISTINCT '.(string)$table['fields'][0]);
      }
      elseif (is_string($table) && is_string($field) && (stripos($field, 'DISTINCT') !== 0)) {
        $field = 'DISTINCT '.$field;
      }

      if ($rows = $this->iselectAll($table, $field, $where, $order, $limit, $start)) {
        foreach ($rows as $row){
          $res[] = $row[0];
        }
      }
    }

    return $res;
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                 WRITE HELPERS WITH TRIGGERS                  *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Inserts row(s) in a table.
   *
   * <code>
   * $db->insert("table_users", [
   *    ["name" => "Ted"],
   *    ["surname" => "McLow"]
   *  ]);
   * </code>
   *
   * <code>
   * $db->insert("table_users", [
   *    ["name" => "July"],
   *    ["surname" => "O'neill"]
   *  ], [
   *    ["name" => "Peter"],
   *    ["surname" => "Griffin"]
   *  ], [
   *    ["name" => "Marge"],
   *    ["surname" => "Simpson"]
   *  ]);
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The values to insert.
   * @param bool $ignore If true, controls if the row is already existing and ignores it.
   *
   * @return int|null Number affected rows.
   * @throws Exception
   */
  public function insert($table, array $values = null, bool $ignore = false): ?int
  {
    if (empty($table)) {
      throw new Exception(X::_('Table name is not specified'));
    }

    if (is_array($table) && isset($table['values'])) {
      $values = $table['values'];
    }

    // Array of arrays
    if (is_array($values)
      && count($values)
      && !X::isAssoc($values)
      && is_array($values[0])
    ) {
      $res = 0;

      foreach ($values as $v){
        $res += $this->insert(
          is_array($table)
            ? ($table['tables'][0] ?? '')
            : $table,
          $v,
          is_array($table)
            ? ($table['ignore'] ?? $ignore)
            : $ignore
        );
      }

      return $res;
    }

    $cfg         = is_array($table) ? $table : [
      'tables' => [$table],
      'fields' => $values,
      'ignore' => $ignore
    ];
    $cfg['kind'] = 'INSERT';
    return $this->_exec($cfg);
  }


  /**
   * If not exist inserts row(s) in a table, else update.
   *
   * <code>
   * $db->insertUpdate(
   *  "table_users",
   *  [
   *    'id' => '12',
   *    'name' => 'Frank'
   *  ]
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The values to insert.
   *
   * @return int|null The number of rows inserted or updated.
   * @throws Exception
   */
  public function insertUpdate($table, array $values = null): ?int
  {
    if (empty($table)) {
      throw new Exception(X::_('Table name is not specified'));
    }
    // Twice the arguments
    if (is_array($table) && isset($table['values'])) {
      $values = $table['values'];
    }

    if (!X::isAssoc($values)) {
      $res = 0;
      foreach ($values as $v){
        $res += $this->insertUpdate(
          is_array($table)
            ? ($table['tables'][0] ?? $table['table'][0] ?? '')
            : $table,
          $v
        );
      }

      return $res;
    }

    $keys   = $this->getKeys($table);
    $unique = [];
    foreach ($keys['keys'] as $k){
      // Checking each unique key
      if ($k['unique']) {
        $i = 0;
        foreach ($k['columns'] as $c){
          if (isset($values[$c])) {
            $unique[$c] = $values[$c];
            $i++;
          }
        }

        // Only if the number of known field values matches the number of columns
        // which are parts of the unique key
        // If a value is null it won't pass isset and so won't be used
        if (($i === \count($k['columns'])) && $this->count($table, $unique)) {
          // Removing unique matching fields from the values (as it is the where)
          foreach ($unique as $f => $v){
            unset($values[$f]);
          }

          // For updating
          return $this->update($table, $values, $unique);
        }
      }
    }

    // No need to update, inserting
    return $this->insert($table, $values);
  }

  /**
   * Updates row(s) in a table.
   *
   * <code>
   * $db->update(
   *  "table_users",
   *  [
   *    ['name' => 'Frank'],
   *    ['surname' => 'Red']
   *  ],
   *  ['id' => '127']
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The new value(s).
   * @param array|null $where The "where" condition.
   * @param boolean $ignore If IGNORE should be added to the statement
   *
   * @return int|null The number of rows updated.
   * @throws Exception
   */
  public function update($table, array $values = null, array $where = null, bool $ignore = false): ?int
  {
    $cfg         = is_array($table) ? $table : [
      'tables' => [$table],
      'where' => $where,
      'fields' => $values,
      'ignore' => $ignore
    ];
    $cfg['kind'] = 'UPDATE';
    return $this->_exec($cfg);
  }

  /**
   * Deletes row(s) in a table.
   *
   * <code>
   * $db->delete("table_users", ['id' => '32']);
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $where The "where" condition.
   * @param bool $ignore default: false.
   *
   * @return int|null The number of rows deleted.
   * @throws Exception
   */
  public function delete($table, array $where = null, bool $ignore = false): ?int
  {
    $cfg         = is_array($table) ? $table : [
      'tables' => [$table],
      'where' => $where,
      'ignore' => $ignore
    ];
    $cfg['kind'] = 'DELETE';
    return $this->_exec($cfg);
  }

  /****************************************************************
   *                                                              *
   *                                                              *
   *                      NATIVE FUNCTIONS                        *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Return an indexed array with the first result of the query or false if there are no results.
   *
   * ```php
   * X::dump($db->fetch("SELECT name FROM users WHERE id = 10"));
   * /* (array)
   * [
   *  "name" => "john",
   *  0 => "john",
   * ]
   * ```
   *
   * @param string $query
   * @return array|false
   */
  public function fetch(string $query)
  {
    if ($r = $this->query(...func_get_args())) {
      return $r->fetch();
    }

    return false;
  }


  /**
   * Return an array of indexed array with all results of the query or empty array if there are no results.
   *
   * ```php
   * X::dump($db->fetchAll("SELECT 'surname', 'name', 'id' FROM users WHERE name = 'john'"));
   * /* (array)
   *  [
   *    [
   *    "surname" => "White",
   *    0 => "White",
   *    "name" => "Michael",
   *    1 => "Michael",
   *    "id"  => 1,
   *    2 => 1,
   *    ],
   *    [
   *    "surname" => "Smith",
   *    0 => "Smith",
   *    "name" => "John",
   *    1  =>  "John",
   *    "id" => 2,
   *    2 => 2,
   *    ],
   *  ]
   * ```
   *
   * @param string $query
   * @return array|false
   */
  public function fetchAll(string $query)
  {
    if ($r = $this->query(...func_get_args())) {
      return $this->fetchAllResults($r);
    }

    return false;
  }


  /**
   * Transposition of the original fetchColumn method, but with the query included. Return an array or false if no result
   *
   * ```php
   * X::dump($db->fetchColumn("SELECT `name`, `id` FROM users WHERE name = ?", 0, 'john'));
   * // (string) "john"
   *
   * X::dump($db->fetchColumn("SELECT `name`, `email` FROM users WHERE name = ?", 1, 'john'));
   * // (string) "john@mail.com"
   * ```
   *
   * @param $query
   * @param int   $num
   * @return mixed
   */
  public function fetchColumn($query, int $num = 0)
  {
    $args = func_get_args();

    if (isset($args[1])) {
      unset($args[1]);
    }

    if ($r = $this->query(...$args)) {
      return $r->fetchColumn($num);
    }

    return false;
  }

  /**
   * Return stdClass object or false if no result.
   *
   * ```php
   * X::dump($db->fetchObject("SELECT * FROM table_users WHERE name = 'john'"));
   * // stdClass Object {
   *                    "id"  =>  1,
   *                    "name"  =>  "John",
   *                    "surname"  =>  "Smith",
   *                    }
   * ```
   *
   * @param string $query
   * @return bool|\stdClass
   */
  public function fetchObject($query)
  {
    if ($r = $this->query(...func_get_args())) {
      return $r->fetchObject();
    }

    return false;
  }

  /**
   * Return an array that includes indexed arrays for every row resultant from the query.
   *
   * ```php
   * X::dump($db->getRows("SELECT id, name FROM table_users WHERE id > ? LIMIT ?", 2));
   * /* (array)[
   *            [
   *            "id" => 3,
   *            "name" => "john",
   *            ],
   *            [
   *            "id" => 4,
   *            "name" => "barbara",
   *            ],
   *          ]
   * ```
   *
   * @param string
   * @param int The var ? value
   * @return array|false
   * @throws Exception
   */
  public function getRows(): ?array
  {
    if ($r = $this->query(...func_get_args())) {
      /** @var Query $r */
      return $r->getRows();
    }

    return null;
  }

  /**
   * Return the first row resulting from the query as an array indexed with the fields' name.
   *
   * ```php
   * X::dump($db->getRow("SELECT id, name FROM table_users WHERE id > ? ", 2));;
   *
   * /* (array)[
   *        "id" => 3,
   *        "name" => "thomas",
   *        ]
   * ```
   *
   * @param string query.
   * @param int The var ? value.
   * @return array|false
   * @throws Exception
   */
  public function getRow(): ?array
  {
    if ($r = $this->query(...func_get_args())) {
      /** @var Query $r */
      return $r->getRow();
    }

    return null;
  }

  /**
   * Return a row as a numeric indexed array.
   *
   * ```php
   * X::dump($db->getIrow("SELECT id, name, surname FROM table_users WHERE id > ?", 2));
   * /* (array) [
   *              3,
   *              "john",
   *              "brown",
   *             ]
   * ```
   *
   * @param string query
   * @param int The var ? value
   * @return array|false
   * @throws Exception
   */
  public function getIrow(): ?array
  {
    if ($r = $this->query(...func_get_args())) {
      /** @var Query $r */
      return $r->getIrow();
    }

    return null;
  }

  /**
   * Return an array of numeric indexed rows.
   *
   * ```php
   * X::dump($db->getIrows("SELECT id, name FROM table_users WHERE id > ? LIMIT ?", 2, 2));
   * /*
   * (array)[
   *         [
   *          3,
   *         "john"
   *         ],
   *         [
   *         4,
   *         "barbara"
   *        ]
   *       ]
   * ```
   *
   * @return null|array
   * @throws Exception
   */
  public function getIrows(): ?array
  {
    if ($r = $this->query(...func_get_args())) {
      /** @var Query $r */
      return $r->getIrows();
    }

    return null;
  }

  /**
   * Return an array indexed on the searched field's in which there are all the values of the column.
   *
   * ```php
   * X::dump($db->getByColumns("SELECT name, surname FROM table_users WHERE id > 2"));
   * /*
   * (array) [
   *      "name" => [
   *       "John",
   *       "Michael"
   *      ],
   *      "surname" => [
   *        "Brown",
   *        "Smith"
   *      ]
   *     ]
   * ```
   *
   * @param string query
   * @return null|array
   * @throws Exception
   */
  public function getByColumns(): ?array
  {
    if ($r = $this->query(...func_get_args())) {
      /** @var Query $r */
      return $r->getByColumns();
    }

    return null;
  }

  /**
   * Return the first row resulting from the query as an object.
   * Synonym of get_obj.
   *
   * ```php
   * X::dump($db->getObject("SELECT name FROM table_users"));
   * /*
   * (obj){
   *       "name" => "John"
   *       }
   * ```
   *
   * @return null|\stdClass
   * @throws Exception
   */
  public function getObject(): ?\stdClass
  {
    if ($r = $this->query(...func_get_args())) {
      /** @var Query $r */
      return $r->getObject();
    }

    return null;
  }


  /**
   * Return an array of stdClass objects.
   *
   * ```php
   * X::dump($db->getObjects("SELECT name FROM table_users"));
   *
   * /*
   * (array) [
   *          Object stdClass: df {
   *            "name" => "John",
   *          },
   *          Object stdClass: df {
   *            "name" => "Michael",
   *          },
   *          Object stdClass: df {
   *            "name" => "Thomas",
   *          },
   *          Object stdClass: df {
   *            "name" => "William",
   *          },
   *          Object stdClass: df {
   *            "name" => "Jake",
   *          },
   *         ]
   * ```
   *
   * @return null|array
   * @throws Exception
   */
  public function getObjects(): ?array
  {
    if ($r = $this->query(...func_get_args())) {
      /** @var Query $r */
      return $r->getObjects();
    }

    return [];
  }

  /**
   * Return an array with tables and fields related to the searched foreign key.
   *
   * ```php
   * X::dump($db->getForeignKeys('id', 'table_users', 'db_example'));
   * // (Array)
   * ```
   *
   * @param string $col The column's name
   * @param string $table The table's name
   * @param string|null $db The database name if different from the current one
   * @return array with tables and fields related to the searched foreign key
   */
  public function getForeignKeys(string $col, string $table, string $db = null): array
  {
    if (!$db) {
      $db = $this->getCurrent();
    }

    $res   = [];
    $model = $this->modelize();
    foreach ($model as $tn => $m){
      foreach ($m['keys'] as $k => $t){
        if (($t['ref_table'] === $table)
          && ($t['ref_column'] === $col)
          && ($t['ref_db'] === $db)
          && (\count($t['columns']) === 1)
        ) {
          if (!isset($res[$tn])) {
            $res[$tn] = [$t['columns'][0]];
          }
          else{
            $res[$tn][] = $t['columns'][0];
          }
        }
      }
    }

    return $res;
  }

  /**
   * Return true if in the table there are fields with auto-increment.
   * Working only on mysql.
   *
   * ```php
   * X::dump($db->hasIdIncrement('table_users'));
   * // (bool) 1
   * ```
   *
   * @param string $table The table's name
   * @return bool
   */
  public function hasIdIncrement(string $table): bool
  {
    return ($model = $this->modelize($table)) &&
      isset($model['keys']['PRIMARY']) &&
      (\count($model['keys']['PRIMARY']['columns']) === 1) &&
      ($model['fields'][$model['keys']['PRIMARY']['columns'][0]]['extra'] === 'auto_increment');
  }


  /**
   * @param string $table
   * @param bool   $force
   * @return null|array
   */
  public function fmodelize(string $table = '', bool $force = false): ?array
  {
    if ($res = $this->modelize(...func_get_args())) {
      foreach ($res['fields'] as $n => $f){
        $res['fields'][$n]['name'] = $n;
        $res['fields'][$n]['keys'] = [];
        if (isset($res['cols'][$n])) {
          foreach ($res['cols'][$n] as $key){
            $res['fields'][$n]['keys'][$key] = $res['keys'][$key];
          }
        }
      }

      return $res['fields'];
    }

    return null;
  }

  /**
   * @param PDOStatement $PDOStatement
   * @param ...$args
   * @return bool|array
   */
  public function fetchAllResults(PDOStatement $PDOStatement, ...$args)
  {
    if (method_exists($PDOStatement, '_fetchAll')) {
      /** @var Query $PDOStatement */
      return $PDOStatement->_fetchAll(...$args);
    }

    return $PDOStatement->fetchAll(...$args);
  }
}