<?php

namespace bbn\Db2\Languages;

use bbn\Db2\Engines;
use bbn\Db2\EnginesApi;
use bbn\Db2\SqlEngines;
use bbn\Db2\SqlFormatters;
use bbn\Str;
use bbn\X;

abstract class Sql implements SqlEngines, Engines, EnginesApi, SqlFormatters
{
  use \bbn\Db2\HasError, \bbn\Models\Tts\Cache;

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
   * @var \PDO
   */
  protected \PDO $pdo;

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
  protected static $write_kinds = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE'];

  /** @var array The 'kinds' of structure alteration statement */
  protected static $structure_kinds = ['DROP', 'ALTER', 'CREATE'];

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
   * Returns the engine class that extends the base Sql class.
   *
   * @return string
   */
  public function getEngine()
  {
    $class = static::class;
    return strtolower(basename(str_replace('\\', '/', $class)));
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
   * Executes a writing statement and return the number of affected rows or return a query object for the reading * statement
   *
   * @param $statement
   * @return false|\PDOStatement
   * @throws \Exception
   */
  public function query($statement)
  {
    $args = \func_get_args();
    // If fancy is false we just use the regular PDO query function
    if (!$this->_fancy) {
      return $this->pdo->query(...$args);
    }

    // The function can be called directly with func_get_args()
    while ((\count($args) === 1) && \is_array($args[0])){
      $args = $args[0];
    }

    if (!empty($args[0]) && \is_string($args[0])) {
      // The first argument is the statement
      $statement = trim(array_shift($args));

      // Sending a hash as second argument from helper functions will bind it to the saved statement
      if (count($args)
        && \is_string($args[0])
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
        && \is_array($args[0])
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
        if (!\is_array($arg)) {
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
          $placeholders = isset($exp[1]) && \is_array($exp[1]) ? \count($exp[1]) : 0;
          while ($sequences[0] === 'OPTIONS'){
            array_shift($sequences);
          }

          $params['kind']      = $sequences[0];
          $params['union']     = isset($sequences['UNION']);
          $params['write']     = \in_array($params['kind'], self::$write_kinds, true);
          $params['structure'] = \in_array($params['kind'], self::$structure_kinds, true);
        }
        elseif (($this->getEngine() === 'sqlite') && (strpos($statement, 'PRAGMA') === 0)) {
          $params['kind'] = 'PRAGMA';
        }
        else{
          throw new \Exception(
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
      elseif (\is_string($this->queries[$hash])) {
        $hash = $this->queries[$hash];
      }

      $this->_update_query($hash);
      $q =& $this->queries[$hash];
      /* If the number of values is inferior to the number of placeholders we fill the values with the last given value */
      if (!empty($params['values']) && ($num_values < $q['placeholders'])) {
        $params['values'] = array_merge(
          $params['values'],
          array_fill($num_values, $q['placeholders'] - $num_values, end($params['values']))
        );
        $num_values       = \count($params['values']);
      }

      /* The number of values must match the number of placeholders to bind */
      if ($num_values !== $q['placeholders']) {
        $this->error(
          'Incorrect arguments count (your values: '.$num_values.', in the statement: '.$q['placeholders'].")\n\n"
          .$statement."\n\n".'start of values'.print_r($params['values'], 1).'Arguments:'
          .print_r(\func_get_args(), true)
          .print_r($q, true)
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
          /** @var \bbn\Db2\Query */
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
        else{
          if (!$q['prepared']) {
            // Native PDO function which will use Db\Query as base class
            $q['prepared'] = $this->pdo->prepare($q['sql'], $driver_options);
          }
          else{
            // Returns the same Db\Query object
            $q['prepared']->init($params['values']);
          }
        }

        if (!empty($time) && ($q['exe_time'] === 0)) {
          $q['exe_time'] = microtime(true) - $time;
        }
      }
      catch (\PDOException $e){
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

        return $r ?? false;
      }
    }
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
   * @throws \Exception
   * @return void
   */
  private function _update_query($hash)
  {
    if (isset($this->queries[$hash]) && \is_array(($this->queries[$hash]))) {
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
          throw new \Exception(X::_("Impossible to find the corresponding hash"));
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
        throw new \Exception(X::_("The queries object is empty!"));
      }
    }
    else {
      throw new \Exception(X::_("Impossible to find the query corresponding to this hash"));
    }
  }

  /**
   * Returns the table's structure's array, either from the cache or from _modelize().
   *
   * @param string $item The item to get
   * @param string $mode The type of item to get (columns, tables, Databases)
   * @param bool $force If true the cache is recreated even if it exists
   * @return array|null
   * @throws \Exception
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
            if (\is_array($keys) && \is_array($cols)) {
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

        if (!isset($tmp) || !\is_array($tmp)) {
          $st = "Error while creating the cache for the table $item in mode $mode";
          $this->log($st);
          throw new \Exception($st);
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
      $h = md5($this->getHost().dirname($this->getCurrent()));
    }
    else {
      $h = str_replace('/', '-', $this->getConnectionCode());
    }

    switch ($mode){
      case 'columns':
        $r = $this->getEngine().'/'.$h.'/'.str_replace('.', '/', $this->tableFullName($item));
        break;
      case 'tables':
        $r = $this->getEngine().'/'.$h.'/'.($item ?: $this->getCurrent());
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
   * @throws \Exception
   */
  public function modelize($table = null, bool $force = false): ?array
  {
    $r      = [];
    $tables = false;
    if (empty($table) || ($table === '*')) {
      $tables = $this->getTables();
    }
    elseif (\is_string($table)) {
      $tables = [$table];
    }
    elseif (\is_array($table)) {
      $tables = $table;
    }

    if (\is_array($tables)) {
      foreach ($tables as $t) {
        if ($full = $this->tableFullName($t)) {
          $r[$full] = $this->_get_cache($full, 'columns', $force);
        }
      }

      if (\count($r) === 1) {
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
    elseif (!\is_array($kind)) {
      $kind = (array)strtoupper($kind);
    }
    else{
      $kind = array_map('strtoupper', $kind);
    }

    if (empty($moment)) {
      $moment = $moments;
    }
    else {
      $moment = !\is_array($moment) ? (array)strtolower($moment) : array_map('strtolower', $moment);
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

            if (\is_array($tables)) {
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
      $table = $this->tableFullName(\is_array($cfg['tables']) ? current($cfg['tables']) : $cfg['tables']);
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

    if (!\is_array($args[0])) {
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
   * @returns null|\bbn\Db2\Query|int A selection query or the number of affected rows by a writing query
   */
  protected function _exec()
  {
    if ($this->check()
      && ($cfg = $this->processCfg(\func_get_args()))
      && !empty($cfg['sql'])
    ) {
      //die(var_dump('0exec cfg', $cfg, \func_get_args()));
      $cfg['moment'] = 'before';
      $cfg['trig']   = null;
      if ($cfg['kind'] === 'INSERT') {
        // Add generated primary when inserting a row without primary when primary is needed and no auto-increment
        $this->_add_primary($cfg);
      }

      if (count($cfg['values']) !== count($cfg['values_desc'])) {
        X::dump($cfg);
        throw new \Exception('Database error in values count');
      }

      // Launching the trigger BEFORE execution
      if ($cfg = $this->_trigger($cfg)) {
        if (!empty($cfg['run'])) {
          //$this->log(["TRIGGER OK", $cfg['run'], $cfg['fields']]);
          // Executing the query
          /** @todo Put hash back! */
          //$cfg['run'] = $this->query($cfg['sql'], $cfg['hash'], $cfg['values'] ?? []);
          /** @var \bbn\Db2\Query */

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
   * @throws \Exception
   */
  public function processCfg(array $args, bool $force = false): ?array
  {
    // Avoid confusion when
    while (isset($args[0]) && \is_array($args[0])) {
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
        return array_merge(
          $this->cfgs[$args['hash']], [
            'values' => $args['values'] ?? [],
            'where' => $args['where'] ?? [],
            'filters' => $args['filters'] ?? []
          ]
        );
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
          throw new \Exception("$key is not defined");
        }

        $tfn = $this->tableFullName($tab);

        // 2 tables in the same statement can't have the same idx
        $idx = \is_string($key) ? $key : $tfn;
        // Error if they do
        if (isset($tables_full[$idx])) {
          $this->error('You cannot use twice the same table with the same alias'.PHP_EOL.X::getDump($args['tables']));
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
        if (!empty($join['table']) && !empty($join['on'])) {
          $tfn = $this->tableFullName($join['table']);
          if (!isset($models[$tfn]) && ($model = $this->modelize($tfn))) {
            $models[$tfn] = $model;
          }

          $idx               = $join['alias'] ?? $tfn;
          $tables_full[$idx] = $tfn;
        }
        else{
          $this->error('Error! The join array must have on and table defined'.PHP_EOL.X::getDump($join));
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
        if (strpos($col, '(')
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

        if (\is_string($idx)) {
          if (!isset($res['available_fields'][$col])) {
            //$this->log($res);
            $this->error("Impossible to find the column $col");
            $this->error(json_encode($res['available_fields'], JSON_PRETTY_PRINT));
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
      $res['models']      = $models;
      $res['tables_full'] = $tables_full;
      switch ($res['kind']){
        case 'SELECT':
          if (empty($res['fields'])) {
            foreach (array_keys($res['available_fields']) as $f){
              if ($this->isColFullName($f)) {
                $res['fields'][] = $f;
              }
            }
          }

          //X::log($res, 'sql');
          if ($res['select_st'] = $this->getSelect($res)) {
            $res['sql'] = $res['select_st'];
          }
          break;
        case 'INSERT':
          $res = $this->removeVirtual($res);
          if ($res['insert_st'] = $this->getInsert($res)) {
            $res['sql'] = $res['insert_st'];
          }

          //var_dump($res);
          break;
        case 'UPDATE':
          $res = $this->removeVirtual($res);
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

        foreach ($res['join'] as $r){
          $this->getValuesDesc($r['on'], $res, $res['values_desc']);
        }

        if (($res['kind'] === 'INSERT') || ($res['kind'] === 'UPDATE')) {
          foreach ($res['fields'] as $name){
            $desc = [];
            if (isset($res['models'], $res['available_fields'][$name])) {
              $t = $res['available_fields'][$name];
              if (isset($tables_full[$t])
                && ($model = $res['models'][$tables_full[$t]]['fields'])
                && ($fname = $this->colSimpleName($name))
                && !empty($model[$fname]['type'])
              ) {
                $desc['type']      = $model[$fname]['type'];
                $desc['maxlength'] = $model[$fname]['maxlength'] ?? null;
              }
            }

            $res['values_desc'][] = $desc;
          }
        }

        $this->getValuesDesc($res['filters'], $res, $res['values_desc']);
        $this->getValuesDesc($res['having'], $res, $res['values_desc']);
        $this->cfgs[$res['hash']] = $res;
      }

      return $res;
    }

    $this->error('Impossible to process the config (no hash)'.PHP_EOL.print_r($args, true));
    return null;
  }

  /**
   * @param array $cfg
   * @return array|null
   * @throws \Exception
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
    while (isset($cfg[0]) && \is_array($cfg[0])){
      $cfg = $cfg[0];
    }

    if (\is_array($cfg)
      && array_key_exists('tables', $cfg)
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
        $res['order'] = \is_string($cfg[4]) ? [$cfg[4] => 'ASC'] : $cfg[4];
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
      if (!\is_array($res['tables'])) {
        $res['tables'] = \is_string($res['tables']) ? [$res['tables']] : [];
      }

      foreach ($res['tables'] as $i => $t){
        if (!is_string($t)) {
          X::log([$cfg, debug_backtrace()], 'db_explained');
          throw new \Exception("Impossible to identify the tables, check the log");
        }

        $res['tables'][$i] = $this->tableFullName($t);
      }
    }
    else{
      throw new \Error(X::_('No table given'));
    }

    if (!empty($res['fields'])) {
      if (\is_string($res['fields'])) {
        $res['fields'] = [$res['fields']];
      }
    }
    elseif (!empty($res['columns'])) {
      $res['fields'] = (array)$res['columns'];
    }

    if (!empty($res['fields'])) {
      if ($res['kind'] === 'SELECT') {
        foreach ($res['fields'] as $k => $col) {
          if (\is_string($k)) {
            $res['aliases'][$col] = $k;
          }
        }
      }
      elseif ((($res['kind'] === 'INSERT') || ($res['kind'] === 'UPDATE'))
        && \is_string(array_keys($res['fields'])[0])
      ) {
        $res['values'] = array_values($res['fields']);
        $res['fields'] = array_keys($res['fields']);
      }
    }

    if (!\is_array($res['group_by'])) {
      $res['group_by'] = empty($res['group_by']) ? [] : [$res['group_by']];
    }

    if (!\is_array($res['where'])) {
      $res['where'] = [];
    }

    if (!\is_array($res['order'])) {
      $res['order'] = \is_string($res['order']) ? [$res['order'] => 'ASC'] : [];
    }

    if (!Str::isInteger($res['limit'])) {
      unset($res['limit']);
    }

    if (!Str::isInteger($res['start'])) {
      unset($res['start']);
    }

    if (!empty($cfg['join'])) {
      foreach ($cfg['join'] as $k => $join){
        if (\is_array($join)) {
          if (\is_string($k)) {
            if (empty($join['table'])) {
              $join['table'] = $k;
            }
            elseif (empty($join['alias'])) {
              $join['alias'] = $k;
            }
          }

          if (isset($join['table'], $join['on']) && ($tmp = $this->treatConditions($join['on'], false))) {
            if (!isset($join['type'])) {
              $join['type'] = 'right';
            }

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
      foreach ($res['join'] as $k => $join){
        if ($tmp = $this->treatConditions($join['on'])) {
          $new_item             = $join;
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
      if (\is_array($tmp) && isset($tmp['values'])) {
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
              $new['conditions'][] = $c;
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
    if (\is_array($args[0])
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
        if (\is_string($id) && Str::isInteger($id) && ((int)$id != PHP_INT_MAX)) {
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
    $args = \func_get_args();
    if ((\count($args) === 1) && \is_array($args[0])) {
      $args = $args[0];
    }

    $st = '';
    foreach ($args as $a){
      $st .= \is_array($a) ? serialize($a) : '--'.$a.'--';
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
    $this->hash = $this->makeHash(...\func_get_args());
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
    $this->pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\bbn\Db2\Query::class, [$this]]);
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
    $this->pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
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
    if (\is_array($args[0])
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
      if (\is_string($this->queries[$hash])) {
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
}