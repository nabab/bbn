<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use bbn\Cache;
use bbn\Db;
use bbn\X;
use bbn\Mvc;
use bbn\Mvc\Controller;
use bbn\Mvc\Model;
use bbn\Util\InternalEvent;
use ReflectionProperty;
use Exception;
use function array_key_exists;
use function count;
use function is_string;
use function is_array;
use function in_array;

/**
 * Provides Cache helpers built on top of DbActions, with row-level caching:
 * - Row cache: table/<table_name>/<id> -> full row array
 *
 * Notes:
 * - The cache is designed to reduce repeated SELECTs, especially in long-lived workers.
 * - Query results are cached as lists of ids, then rows are cached by id.

 */
trait DbCache
{
  use DbOps;

  /** @var Cache The cache engine used by this trait (shared across instances). */
  protected static Cache $dbTraitCache;

  /**
   * Ensures the cache engine is initialized.
   *
   * @return void
   */
  protected static function dbTraitGlobalCacheInit(): void
  {
    if (!isset(self::$dbTraitCache)) {
      self::$dbTraitCache = Cache::getEngine();
    }
  }

  protected function dbTraitCacheInit(): void
  {
    static::dbTraitGlobalCacheInit();
    if ($this->class_cfg["cache"] ?? false) {
      $this->on("beforeselect", function (InternalEvent $o): InternalEvent {
        $filter = $o->getData()[0];
        if (is_string($filter) && $this->dbTraitCacheGet($filter)) {
          $o->setResponse($this->dbTraitCacheGet($filter));
          $o->preventDefault();
        }

        return $o;
      });
      $this->on("afterinsert", function (InternalEvent $o): InternalEvent {
        $id = $o->getData()[0];
        $this->dbTraitCacheSet($id);
        return $o;
      });
      $this->on("beforeupdate", function (InternalEvent $o): InternalEvent {
        $filter = $o->getData()[0];
        $ids = $this->dbTraitGetIds($filter);
        $o->setResponse($ids);
        return $o;
      });
      $this->on("afterupdate", function (InternalEvent $o): InternalEvent {
        $id = $o->getData()[0];
        $this->dbTraitCacheSet($id);
        return $o;
      });
      $this->on("beforedelete", function (InternalEvent $o): InternalEvent {
        $filter = $o->getData()[0];
        $ids =
          $this->class_cfg["cache"] ?? false
            ? $this->dbTraitGetIds($filter)
            : [];
        foreach ($ids as $id) {
          $this->dbTraitCacheDelete($id);
        }
        return $o;
      });
    }
  }

  /**
   * Returns the cache key for a row.
   *
   * @param string $id The row's id.
   * @return string
   */
  protected function dbTraitRowCacheKey(string $id): string
  {
    return "table/{$this->class_table}/{$id}";
  }

  /**
   * Retrieves a row from cache.
   *
   * If $fields is provided, returns only the requested fields (with optional aliases).
   *
   * @param string $id The row's id.
   * @param array  $fields List of fields to return. Can be:
   *                       - ['col1', 'col2']
   *                       - ['alias1' => 'col1', 'alias2' => 'col2']
   * @return array|null The cached row (possibly projected), or null if missing.
   */
  protected function dbTraitCacheGet(
    string $id,
    array $fields = [],
    bool $autoExclude = false,
  ): ?array {
    static::dbTraitGlobalCacheInit();
    $res = null;
    if ($res = self::$dbTraitCache->get($this->dbTraitRowCacheKey($id))) {
      $cfg = $this->getClassCfg();
      $todo = [];
      if (
        $excluded =
          !$autoExclude &&
          is_array($cfg["cache"]) &&
          isset($cfg["cache"]["excluded"])
            ? $cfg["cache"]["excluded"]
            : []
      ) {
        if (empty($fields)) {
          $fields = $cfg["arch"][$this->class_table_index];
        }

        foreach ($fields as $alias => $field) {
          if (in_array($field, $excluded)) {
            $todo[$alias] = $field;
          }
        }

        if (!empty($todo)) {
          $res = X::mergeArrays(
            $res,
            $this->dbTraitSingleSelection($id, [], "array", $todo),
          );
        }
      }

      if (count($fields)) {
        $arr = [];
        foreach ($fields as $alias => $field) {
          if (array_key_exists($field, $res)) {
            $arr[is_int($alias) ? $field : $alias] = $res[$field];
          }
        }

        return $arr;
      }
    }

    return $res ?: null;
  }

  /**
   * Loads a row from DB and stores it into cache.
   *
   * If $fields is provided, returns only the requested fields (with optional aliases).
   *
   * @param string $id The row's id.
   * @param array  $fields List of fields to return (same format as dbTraitCacheGet()).
   * @return array|null The row fetched from DB (possibly projected), or null if not found.
   */
  protected function dbTraitCacheSet(string $id, array $fields = []): ?array
  {
    static::dbTraitGlobalCacheInit();
    $cfg = $this->getClassCfg();
    $f = array_values($cfg["arch"][$this->class_table_index]);
    if (is_array($cfg["cache"]) && isset($cfg["cache"]["excluded"])) {
      $excluded = $cfg["cache"]["excluded"];
      foreach ($excluded as $col) {
        if (in_array($col, $f)) {
          unset($f[array_search($col, $f)]);
        }
      }
    }

    if (
      $data = $this->dbTraitSingleSelection(
        ["id" => $id],
        [],
        "array",
        array_values($f),
      )
    ) {
      self::$dbTraitCache->set($this->dbTraitRowCacheKey($id), $data);
      return $this->dbTraitCacheGet($id, $fields);
    }

    return null;
  }

  /**
   * Deletes a row from cache.
   *
   * @param string $id The row's id.
   * @return void
   */
  protected function dbTraitCacheDelete(string $id): void
  {
    static::dbTraitGlobalCacheInit();
    self::$dbTraitCache->delete($this->dbTraitRowCacheKey($id));
  }

  public function dbTraitCacheGetHash(string $id): ?string
  {
    $res = $this->dbTraitCacheGetSetFull($id);
    return $res["hash"] ?? null;
  }

  public function dbTraitCacheGetSetFull(string $id): ?array
  {
    static::dbTraitGlobalCacheInit();
    $res = null;
    $cn = $this->dbTraitRowCacheKey($id);
    if (!($res = self::$dbTraitCache->getFull($cn))) {
      $this->dbTraitCacheSet($id);
      $res = self::$dbTraitCache->getFull($cn);
    }

    return $res;
  }

  public function dbTraitCacheGetFull(string $id): ?array
  {
    static::dbTraitGlobalCacheInit();
    $cn = $this->dbTraitRowCacheKey($id);
    return self::$dbTraitCache->getFull($cn);
  }

  public static function dbTraitCacheInitTrigger(Db $db): void {
    if (!defined("BBN_DBACTIONS_CACHE_INIT")) {
      define("BBN_DBACTIONS_CACHE_INIT", true);
      $cache = Cache::getEngine();
      $arr = self::dbConfigGetTableClasses();
      $db->setTrigger(function ($cfg) use ($cache, $db, $arr) {
        if (!empty($cfg["write"]) && $cfg["moment"] === "after") {
          $table = $db->tsn(array_values($cfg["tables"])[0]);
          if (isset($arr[$table])) {
            if (!empty($arr[$table]['cache'])) {
              if ($cfg["kind"] === "INSERT") {
              } else {
                $idx1 = X::search($cfg['values_desc'], ['primary' => true]);
                if ($idx1 !== null) {
                  $id = $cfg['values'][$idx1];
                  $cache->delete("table/$table/$id");
                }
              }
            }
            elseif (isset($arr[$table]['deps'])) {
              foreach ($arr[$table]['deps'] as $dep) {
                $idx1 = X::search($cfg['values_desc'], ['primary' => true]);
                if ($idx1 !== null) {
                  $id = $cfg['values'][$idx1];
                  $ids = $db->getColumnValues($dep, 'id', [$dep['field'] => $id]);
                  foreach ($ids as $id) {
                    $cache->delete("table/$dep/$id");
                  }
                }
              }

            }
          }
        }

        return $cfg;
      });
    }
  }
}
