<?php

namespace bbn\Db\Sub;

use bbn\X;
use bbn\Db;
use bbn\Db\Models\Cls\Sub;
use bbn\Db\Models\Itf\Internal as ItfInternal;

class Internal extends Sub implements ItfInternal
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
   * @return Db
   */
  public function log($st): Db
  {
    $args = \func_get_args();
    foreach ($args as $a){
      X::log($a, 'db');
    }

    return $this->db;
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
   * @return Db
   */
  public function setErrorMode(string $mode): Db
  {
    $this->language->setErrorMode($mode);
    return $this->db;
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
   * @return Db
   */
  public function clearCache(string $item, string $mode): Db
  {
    if ($this->cacheHas($item, $mode)) {
      $this->cacheDelete($item, $mode);
    }

    return $this->db;
  }


  /**
   * Clears the cache.
   *
   * ```php
   * X::dump($db->clearAllCache());
   * // (db)
   * ```
   *
   * @return Db
   */
  public function clearAllCache(): Db
  {
    $this->cacheDeleteAll();
    $this->language->initCache();
    return $this->db;
  }


  /**
   * Stops fancy stuff.
   *
   * ```php
   *  $db->stopFancyStuff();
   * // (self)
   * ```
   *
   * @return Db
   */
  public function stopFancyStuff(): Db
  {
    if ($this->language) {
      $this->language->stopFancyStuff();
    }

    return $this->db;
  }


  /**
   * Starts fancy stuff.
   *
   * ```php
   * $db->startFancyStuff();
   * // (self)
   * ```
   * 
   * @return Db
   */
  public function startFancyStuff(): Db
  {
    if ($this->language) {
      $this->language->startFancyStuff();
    }

    return $this->db;
  }

}