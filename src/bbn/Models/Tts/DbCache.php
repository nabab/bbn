<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use Exception;
use bbn\Cache;
use bbn\Db;
use bbn\X;
use bbn\Util\InternalEvent;
use Mpdf\Tag\A;

use function array_key_exists;
use function count;
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
      /*
      $this->on("beforeselect", function (InternalEvent $o): InternalEvent {
        $filter = $o->getData()[0];
        X::ddump($filter, "cache beforeselect filter");
        if (is_string($filter) && $this->dbTraitCacheGet($filter)) {
          $o->setResponse($this->dbTraitCacheGet($filter));
          $o->preventDefault();
        }

        return $o;
      });
      */
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
        if ($ids = $o->getResponse()) {
          foreach ($ids as $id) {
            $this->dbTraitCacheSet($id);
          }
          $res = $o->getData()[2] ?? null;
          $o->setResponse($res);
        }

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
    $sep = Cache::getSeparator();
    if (!isset($this->class_table)) {
      $cfg = self::getDefaultClassCfg();
      while ($cfg && empty($cfg['table'])) {
        $cls = get_parent_class($this);
        if (!$cls) {
          throw new Exception(X::_("Impossible to find a table for class %s", self::class));
        }

        $cfg = $cls::getDefaultClassCfg();
      }

      return "table{$sep}{$cfg['table']}{$sep}{$id}";
    }

    return "table{$sep}{$this->class_table}{$sep}{$id}";
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

  protected function dbTraitCacheRetrieveRecord(string $id): ?array
  {
    static::dbTraitGlobalCacheInit();
    $cfg = $this->getClassCfg();
    $f = array_values($cfg["arch"][$this->class_table_index]);
    $tableCfg = self::dbConfigGetTableClasses($this->db);
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
      if (!empty($tableCfg[$this->class_table]['junctions'])) {
        $this->dbTraitCacheApplyJunctions(
          $data,
          $tableCfg[$this->class_table]['junctions'],
          $tableCfg
        );
      }

      return $data;
    }

    return null;
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
    if (!isset($this->class_table)) {
      throw new Exception(X::_("The class %s is not properly configured for DbCache: missing table", self::class));
    }

    if ($data = $this->dbTraitCacheRetrieveRecord($id)) {
      self::$dbTraitCache->set($this->dbTraitRowCacheKey($id), $data);
      return $this->dbTraitCacheGet($id, $fields);
    }

    return null;
  }

  /**
   * Applies junction enrichment recursively to a row.
   *
   * Each junction can:
   * - fetch one linked row from another table
   * - attach it under `property`
   * - or merge it into the current row if no property is given
   * - recursively apply nested junctions through `junctions`
   *
   * Supported junction config keys:
   * - table      : linked table name
   * - field      : local field containing the linked row id
   * - property   : optional property name for embedding
   * - filter     : optional extra filter
   * - fields     : optional selected fields
   * - mode       : optional, defaults to 'one'
   * - junctions  : optional nested junction definitions
   *
   * @param array $data
   * @param array $junctions
   * @param array $tableCfg
   * @return array
   */
  protected function dbTraitCacheApplyJunctions(
    array &$data,
    array $junctions,
    array $tableCfg
  ): array {
    foreach ($junctions as $j) {
      if (
        !X::hasProps($j, ['table', 'field'], true) ||
        empty($data[$j['field']])
      ) {
        continue;
      }

      $mode = $j['mode'] ?? 'one';

      // Not implemented yet, kept here for later extension
      if ($mode === 'many') {
        continue;
      }

      $where = $j['filter'] ?? [];

      if (isset($tableCfg[$j['table']]['primary'][0])) {
        $where[$tableCfg[$j['table']]['primary'][0]] = $data[$j['field']];
      }

      if (empty($where)) {
        continue;
      }

      $jdata = $this->db->rselect(
        $j['table'],
        $j['fields'] ?? [],
        $where
      );

      if (!$jdata) {
        continue;
      }

      // Apply nested junctions on the fetched row
      if (!empty($j['junctions']) && is_array($j['junctions'])) {
        $this->dbTraitCacheApplyJunctions(
          $jdata,
          $j['junctions'],
          $tableCfg
        );
      }

      if (!empty($j['property'])) {
        $data[$j['property']] = $jdata;
      }
      else {
        X::extendOut($data, $jdata);
      }
    }

    return $data;
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


  public function dbTraitCacheHash(string $id): ?string
  {
    static::dbTraitGlobalCacheInit();
    if ($r = $this->dbTraitCacheInfo($id)) {
      return $r['hash'] ?? null;
    }

    return null;
  }

  public function dbTraitCacheInfo(string $id): ?array
  {
    static::dbTraitGlobalCacheInit();
    $cn = $this->dbTraitRowCacheKey($id);
    return self::$dbTraitCache->info($cn);
  }

  public function dbTraitCacheImport(): ?int
  {
    static::dbTraitGlobalCacheInit();
    if (!isset($this->class_table)) {
      throw new Exception(X::_("The class %s is not properly configured for DbCache: missing table", static::class));
    }
    $cfg = $this->getClassCfg();
    $f = array_values($cfg["arch"][$this->class_table_index]);
    $tableCfg = self::dbConfigGetTableClasses($this->db);
    if (is_array($cfg["cache"]) && isset($cfg["cache"]["excluded"])) {
      $excluded = $cfg["cache"]["excluded"];
      foreach ($excluded as $col) {
        if (in_array($col, $f)) {
          unset($f[array_search($col, $f)]);
        }
      }
    }
    $start = 0;
    $limit = 10000;
    $num = 0;
    $cache = self::$dbTraitCache;
    $idCol = $tableCfg[$this->class_table]['primary'][0];
    while (
      $data = $this->dbTraitSelection(
        [],
        [$idCol => 'ASC'],
        $limit,
        $start,
        "array",
        array_values($f),
      )
    ) {
      if (!empty($tableCfg[$this->class_table]['junctions'])) {
        foreach ($data as &$row) {
          $this->dbTraitCacheApplyJunctions(
            $row,
            $tableCfg[$this->class_table]['junctions'],
            $tableCfg
          );
        }
      }

      $toCache = [];
      foreach ($data as $d) {
        $toCache[$this->dbTraitRowCacheKey($d[$idCol])] = $d;
      }

      if ($cache->setMultiple($toCache, 0)) {
        $num += count($data);
        $start += $limit;
      }
      else {
        return null;
      }
    }

    return $num;
  }

  public function dbTraitCacheGetSet(string $id, array $fields = []): ?array
  {
    static::dbTraitGlobalCacheInit();
    $cn = $this->dbTraitRowCacheKey($id);
    $cache = self::$dbTraitCache;
    $data = $cache->getSet(
      fn () => $this->dbTraitCacheRetrieveRecord($id),
      $cn
    );
    if (!$data) {
      return null;
    }
    if (count($fields)) {
      $arr = [];
      foreach ($fields as $alias => $field) {
        if (array_key_exists($field, $data)) {
          $arr[is_int($alias) ? $field : $alias] = $data[$field];
        }
      }

      return $arr;
    }

    return $data;
  }

 
  public static function dbTraitCacheInitTrigger(Db $db): void {
    if (!defined("BBN_DBACTIONS_CACHE_INIT")) {
      define("BBN_DBACTIONS_CACHE_INIT", true);
      $cache = Cache::getEngine();
      $sep = Cache::getSeparator();
      $arr = self::dbConfigGetTableClasses($db);
      $db->setTrigger(function ($cfg) use ($cache, $db, $arr, $sep) {
        if (!empty($cfg["write"]) && $cfg["moment"] === "after") {
          $table = $db->tsn(array_values($cfg["tables"])[0]);
          if (isset($arr[$table])) {
            if (!empty($arr[$table]['cache'])) {
              if ($cfg["kind"] === "INSERT") {
              } else {
                $idx1 = X::search($cfg['values_desc'], ['primary' => true]);
                if ($idx1 !== null) {
                  $id = $cfg['values'][$idx1];
                  $cache->delete("table{$sep}{$table}{$sep}{$id}");
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
                    $cache->delete("table{$sep}{$dep['table']}{$sep}{$id}");
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
