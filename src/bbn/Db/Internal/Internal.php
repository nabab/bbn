<?php

namespace bbn\Db\Internal;
use bbn\X;

trait Internal
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                      INTERNAL METHODS                        *
   *                                                              *
   *                                                              *
   ****************************************************************/

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
    return $this->language->getHash();
  }


  /**
   * @param array $conditions
   * @param $old_name
   * @param $new_name
   * @return array
   */
  public function replaceTableInConditions(array $conditions, $old_name, $new_name): array
  {
    return X::map(
      function ($a) use ($old_name, $new_name) {
        if (!empty($a['field'])) {
          $a['field'] = preg_replace("/(\\W|^)$old_name([\\`\\']*\\s*)\\./", '$1'.$new_name.'$2.', $a['field']);
        }

        if (!empty($a['exp'])) {
          $a['exp'] = preg_replace("/(\\W|^)$old_name([\\`\\']*\\s*)\\./", '$1'.$new_name.'$2.', $a['exp']);
        }

        return $a;
      }, $conditions, 'conditions'
    );
  }


  /**
   * @param array $where
   * @param bool  $full
   * @return array|bool
   */
  public function treatConditions(array $where, bool $full = true)
  {
    return $this->language->treatConditions($where, $full);
  }


  /**
   * @param array $cfg
   * @return array|null
   */
  public function reprocessCfg(array $cfg): ?array
  {
    return $this->language->reprocessCfg($cfg);
  }

  /**
   *
   * @param array $args
   * @param bool $force
   * @return array|null
   */
  public function processCfg(array $args, bool $force = false): ?array
  {
    return $this->language->processCfg($args, $force);
  }

  /**
   * Checks if the database is ready to process a query.
   *
   * ```php
   * X::dump($db->check());
   * // (bool)
   * ```
   * 
   * @return bool
   */
  public function check(): bool
  {
    return $this->language->check();
  }

  /**
   * Writes in data/logs/db.log.
   *
   * ```php
   * $db->$db->log('test');
   * ```
   * 
   * @param mixed $st
   * @return self
   */
  public function log($st): self
  {
    $args = \func_get_args();
    foreach ($args as $a){
      X::log($a, 'db');
    }

    return $this;
  }


  /**
   * Sets the error mode.
   *
   * ```php
   * $db->setErrorMode('continue'|'die'|'stop_all|'stop');
   * // (self)
   * ```
   *
   * @param string $mode The error mode: "continue", "die", "stop", "stop_all".
   * @return self
   */
  public function setErrorMode(string $mode): self
  {
    $this->language->setErrorMode($mode);
    return $this;
  }


  /**
   * Gets the error mode.
   *
   * ```php
   * X::dump($db->getErrorMode());
   * // (string) stop_all
   * ```
   * 
   * @return string
   */
  public function getErrorMode(): string
  {
    return $this->language->getErrorMode();
  }


  /**
   * Deletes a specific item from the cache.
   *
   * ```php
   * X::dump($db->clearCache('db_example','tables'));
   * // (db)
   * ```
   *
   * @param string $item 'db_name' or 'table_name'
   * @param string $mode 'columns','tables' or 'databases'
   * @return self
   */
  public function clearCache(string $item, string $mode): self
  {
    if ($this->cacheHas($item, $mode)) {
      $this->cacheDelete($item, $mode);
    }

    return $this;
  }


  /**
   * Clears the cache.
   *
   * ```php
   * X::dump($db->clearAllCache());
   * // (db)
   * ```
   *
   * @return self
   */
  public function clearAllCache(): self
  {
    $this->cacheDeleteAll();
    $this->language->initCache();
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
    if ($this->language) {
      $this->language->stopFancyStuff();
    }

    return $this;
  }


  /**
   * Starts fancy stuff.
   *
   * ```php
   * $db->startFancyStuff();
   * // (self)
   * ```
   * 
   * @return self
   */
  public function startFancyStuff(): self
  {
    if ($this->language) {
      $this->language->startFancyStuff();
    }

    return $this;
  }

}