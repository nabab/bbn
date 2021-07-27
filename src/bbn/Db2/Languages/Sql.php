<?php

namespace bbn\Db2\Languages;

use bbn\Db2\Engines;
use bbn\Db2\EnginesApi;
use bbn\Db2\SqlEngines;
use bbn\Db2\SqlFormatters;
use bbn\X;

abstract class Sql implements SqlEngines, Engines, EnginesApi, SqlFormatters
{
  /**
   * @var mixed $cache
   */
  private $cache = [];


  final public function getEngine()
  {
    $class = static::class;
    return strtolower(basename(str_replace('\\', '/', $class)));
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
          die(\defined('BBN_IS_DEV') && BBN_IS_DEV ? "Impossible to parse the query $statement" : 'Impossible to parse the query');
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

      // That will always contains the parameters of the last query done

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

  private function _remove_query(string $hash): void
  {
    if (X::hasProp($this->queries, $hash)) {
      unset($this->queries[$hash]);
      while ($idx = \array_search($hash, $this->queries, true)) {
        unset($this->queries[$idx]);
      }
    }
  }

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
   * @param string $mode The type of item to get (columns, rables, Databases)
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
        $r = $this->db->getEngine().'/'.$h.'/_bbn-database';
        break;
    }

    return $r;
  }
}