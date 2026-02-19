<?php

namespace bbn\Db\Languages\Models\Sql;

use Exception;
use bbn\Str;
use bbn\X;

trait QueryLog
{
  /** @var array<string, array|string> Queries registry (hash => query meta OR alias => hash). */
  protected array $queries = [];

  /** @var array<int, array{hash:string,last:float}> Ordered query list. */
  protected array $list_queries = [];

  /** @var int Max number of recorded queries. */
  protected int $max_queries = 50;

  /** @var int Max age (seconds) before old queries are removed. */
  protected int $length_queries = 60;

  /** @var array Last params (public to Query). */
  protected array $last_params = ['sequences' => false, 'values' => false];

  /** @var string|null Last “statement” (after formatting). */
  protected ?string $last_query = null;

  /** @var string|null Last “real” SQL statement actually executed. */
  protected ?string $last_real_query = null;

  /** @var array Last real params. */
  protected array $last_real_params = ['sequences' => false, 'values' => false];

  /** @var array|null Last cfg processed by _exec(). */
  protected ?array $last_cfg = null;

  /**
   * Adds statement metadata for last()/getLastParams() and realLast()/getRealLastParams().
   *
   * @method addStatement
   * @param string $statement
   * @param mixed $params
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

  /**
   * Returns last real params (always updated).
   *
   * @method getRealLastParams
   * @return array
   */
  public function getRealLastParams(): array
  {
    return $this->last_real_params;
  }

  /**
   * Returns last real SQL statement (always updated).
   *
   * @method realLast
   * @return string|null
   */
  public function realLast(): ?string
  {
    return $this->last_real_query;
  }

  /**
   * Returns the values bound for the last executed statement (if enabled).
   *
   * @method getLastValues
   * @return array|null
   */
  public function getLastValues(): ?array
  {
    return $this->last_params ? ($this->last_params['values'] ?? null) : null;
  }

  /**
   * Returns last params (if enabled).
   *
   * @method getLastParams
   * @return array|null
   */
  public function getLastParams(): ?array
  {
    return $this->last_params;
  }

  /**
   * Enables last-query recording.
   *
   * @method enableLast
   * @return void
   */
  public function enableLast(): void
  {
    $this->_last_enabled = true;
  }

  /**
   * Disables last-query recording.
   *
   * @method disableLast
   * @return void
   */
  public function disableLast(): void
  {
    $this->_last_enabled = false;
  }

  /**
   * Returns the last recorded statement (if enabled).
   *
   * @method last
   * @return string|null
   */
  public function last(): ?string
  {
    return $this->last_query;
  }

  /**
   * Returns number of currently tracked queries.
   *
   * @method countQueries
   * @return int
   */
  public function countQueries(): int
  {
    return count($this->queries);
  }

  /**
   * Clears queries registry, returns number of removed entries.
   *
   * @method flush
   * @return int
   */
  public function flush(): int
  {
    $num                = count($this->queries);
    $this->queries      = [];
    $this->list_queries = [];
    return $num;
  }

  /**
   * Retrieves a tracked query metadata by hash.
   *
   * @method retrieveQuery
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
   * Adds query metadata to registry.
   *
   * @method _add_query
   * @param string $hash
   * @param string $statement
   * @param string $kind
   * @param int $placeholders
   * @param array $options
   * @return void
   */
  private function _add_query(string $hash, string $statement, string $kind, int $placeholders, array $options): void
  {
    $now                  = microtime(true);
    $this->queries[$hash] = [
      'sql' => $statement,
      'kind' => $kind,
      'write' => in_array($kind, self::$write_kinds, true),
      'structure' => in_array($kind, self::$structure_kinds, true),
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

    $num = count($this->list_queries);
    while ($num > $this->max_queries) {
      $num--;
      $this->_remove_query($this->list_queries[0]['hash']);
      array_shift($this->list_queries);
    }
  }

  /**
   * Removes a query from registry.
   *
   * @method _remove_query
   * @param string $hash
   * @return void
   */
  private function _remove_query(string $hash): void
  {
    if (X::hasProp($this->queries, $hash)) {
      unset($this->queries[$hash]);
      while ($idx = array_search($hash, $this->queries, true)) {
        unset($this->queries[$idx]);
      }
    }
  }

  /**
   * Updates query access metadata and performs TTL cleanup.
   *
   * @method _update_query
   * @param string $hash
   * @return void
   * @throws Exception
   */
  private function _update_query($hash): void
  {
    if (isset($this->queries[$hash]) && is_array(($this->queries[$hash]))) {
      $last_index                   = count($this->list_queries) - 1;
      $now                          = microtime(true);
      $this->queries[$hash]['last'] = $now;
      $this->queries[$hash]['num']++;

      if ($this->list_queries[$last_index]['hash'] !== $hash) {
        if (($idx = X::search($this->list_queries, ['hash' => $hash])) !== null) {
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
}
