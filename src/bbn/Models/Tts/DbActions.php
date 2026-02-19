<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use bbn\X;
use bbn\Str;
use bbn\Cache;
use stdClass;
use Exception;
use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Database actions trait for "regular" (non-junction) tables.
 *
 * Provides CRUD helpers built on top of DbTrait, with optional row-level caching:
 * - Row cache: table/<table_name>/<id> -> full row array
 * - IDs cache: table/<table_name>/ids/<signature> -> array of ids for a filter/order pair
 *
 * Notes:
 * - The cache is designed to reduce repeated SELECTs, especially in long-lived workers.
 * - Query results are cached as lists of ids, then rows are cached by id.
 */
trait DbActions
{
  use DbTrait;

  /** @var Cache The cache engine used by this trait (shared across instances). */
  protected static Cache $dbTraitCache;

  /**
   * Ensures the cache engine is initialized.
   *
   * @return void
   */
  private static function dbTraitCacheInit(): void
  {
    if (!isset(self::$dbTraitCache)) {
      self::$dbTraitCache = Cache::getEngine();
    }
  }

  /**
   * Returns the cache key for a row.
   *
   * @param string $id The row's id.
   * @return string
   */
  private function dbTraitRowCacheKey(string $id): string
  {
    return 'table/' . $this->class_table . '/' . $id;
  }

  /**
   * Returns the cache key for an "ids list" query.
   *
   * @param array $filter Filter config.
   * @param array $order Order config.
   * @return string
   */
  private function dbTraitIdsCacheKey(array $filter, array $order): string
  {
    $signature = md5(json_encode([$filter, $order]));
    return 'table/' . $this->class_table . '/ids/' . $signature;
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
  private function dbTraitCacheGet(string $id, array $fields = []): ?array
  {
    self::dbTraitCacheInit();
    $res = null;

    if ($full = self::$dbTraitCache->getFull($this->dbTraitRowCacheKey($id))) {
      $res = $full['value'] ?? null;

      if ($res && $fields) {
        $arr = [];
        foreach ($fields as $alias => $field) {
          if (array_key_exists($field, $res)) {
            $arr[is_int($alias) ? $field : $alias] = $res[$field];
          }
        }

        return $arr;
      }
    }

    return $res;
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
  private function dbTraitCacheSet(string $id, array $fields = []): ?array
  {
    self::dbTraitCacheInit();
    $cfg = $this->getClassCfg();
    $f = $cfg['arch'][$this->class_table_index];

    if ($data = $this->dbTraitSingleSelection([$f['id'] => $id], [], 'array', [])) {
      self::$dbTraitCache->set($this->dbTraitRowCacheKey($id), $data);

      if ($fields) {
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

    return null;
  }

  /**
   * Deletes a row from cache.
   *
   * @param string $id The row's id.
   * @return void
   */
  private function dbTraitCacheDelete(string $id): void
  {
    self::dbTraitCacheInit();
    self::$dbTraitCache->delete($this->dbTraitRowCacheKey($id));
  }

  /**
   * Returns the matching ids for the given filter/order.
   *
   * Fast paths:
   * - If $filter is a string, it's assumed to be the id.
   * - If $filter is an array containing only the id column, returns that id.
   *
   * When class caching is enabled, the ids list is cached using a signature derived
   * from $filter and $order.
   *
   * @param string|array $filter Filter configuration or a single id.
   * @param array        $order Order configuration.
   * @return array The list of ids matching the query.
   */
  protected function dbTraitGetIds(string|array $filter = [], array $order = []): array
  {
    if (is_string($filter)) {
      return [$filter];
    }

    $cfg = $this->getClassCfg();
    $f = $cfg['arch'][$this->class_table_index];

    // If the filter is exactly "id = X", return it directly without a DB call.
    if (isset($filter[$f['id']]) && (count($filter) === 1)) {
      return [$filter[$f['id']]];
    }

    // Cached ids list by query signature.
    if ($cfg['cache'] ?? false) {
      self::dbTraitCacheInit();
      $cacheKey = $this->dbTraitIdsCacheKey($filter, $order);

      /** @var array|null $res */
      if ($res = self::$dbTraitCache->get($cacheKey)) {
        return $res;
      }
    }

    /** @var array $res */
    $res = $this->db->getColumnValues($this->class_table, $f['id'], $filter, $order);

    if ($cfg['cache'] ?? false) {
      self::$dbTraitCache->set($cacheKey, $res);
    }

    return $res;
  }

  /**
   * Checks whether at least one row exists for the given filter.
   *
   * If $filter is a string, it's treated as the row id.
   * When caching is enabled and a string id is provided, the cache is used as a shortcut.
   *
   * @param string|array $filter Row id or filter configuration.
   * @return bool True if at least one row exists, false otherwise.
   */
  protected function dbTraitExists(string|array $filter): bool
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $f = $this->class_cfg['arch'][$this->class_table_index];
    if (is_string($filter)) {
      if ($this->class_cfg['cache'] ?? false) {

        if ($this->dbTraitCacheGet($filter)) {
          return true;
        }
      }

      $cfg = [$f['id'] => $filter];
    }
    elseif (is_array($filter)) {
      $cfg = $filter;
    }

    return !empty($cfg) && (bool)$this->db->count(
      $this->class_table,
      $this->dbTraitGetFilterCfg($cfg)
    );
  }

  /**
   * Inserts a new row into the table.
   *
   * Handles JSON cfg column if configured in the table arch.
   * When caching is enabled, clears ids-list cache and populates row cache.
   *
   * @param array $data   The row data.
   * @param bool  $ignore Whether to ignore insert errors (insertIgnore).
   * @return string|null The inserted row id, or null on failure.
   */
  protected function dbTraitInsert(array $data, bool $ignore = false): ?string
  {
    if ($data = $this->dbTraitPrepare($data)) {
      $ccfg = $this->getClassCfg();

      // Encode "cfg" column as JSON if configured.
      if (!empty($ccfg['arch'][$this->class_table_index]['cfg'])) {
        $col = $ccfg['arch'][$this->class_table_index]['cfg'];
        if (isset($data[$col])) {
          $data[$col] = json_encode($data[$col]);
        }
      }

      if ($this->db->{$ignore ? 'insertIgnore' : 'insert'}($this->class_table, $data)) {
        $id = $this->db->lastId();

        if ($this->class_cfg['cache'] ?? false) {
          self::dbTraitCacheInit();
          self::$dbTraitCache->deleteAll('table/' . $this->class_table . '/ids/');
          $this->dbTraitCacheSet($id);
        }

        return $id;
      }
    }

    return null;
  }

  /**
   * Deletes row(s) from the table.
   *
   * Accepts a string id or a filter array.
   * When caching is enabled:
   * - deletes row cache entries for affected ids
   * - clears the ids-list cache for this table
   *
   * Optionally supports cascade deletion on related tables.
   *
   * @param string|array $filter  Row id or filter configuration.
   * @param bool         $cascade Whether to cascade delete relations.
   * @return int Number of deleted rows.
   */
  protected function dbTraitDelete(string|array $filter, bool $cascade = false): int
  {
    if ($this->dbTraitExists($filter)) {
      $cfg = $this->getClassCfg();
      $f = $cfg['arch'][$this->class_table_index];

      if (!is_array($filter) && !empty($f['id'])) {
        $filter = [$f['id'] => $filter];
      }

      $ids = ($this->class_cfg['cache'] ?? false) ? $this->dbTraitGetIds($filter) : [];

      if ($res = $this->db->delete($this->class_table, $this->dbTraitGetFilterCfg($filter))) {
        if ($this->class_cfg['cache'] ?? false) {
          foreach ($ids as $id) {
            $this->dbTraitCacheDelete($id);
          }
        }

        if ($cascade) {
          foreach ($this->dbTraitGetTableRelations() as $rel) {
            $this->db->delete(
              $rel['table'],
              [$rel['col'] => is_array($filter) ? $filter[$f['id']] : $filter]
            );
          }
        }

        if ($this->class_cfg['cache'] ?? false) {
          self::dbTraitCacheInit();
          self::$dbTraitCache->deleteAll('table/' . $this->class_table . '/ids/');
        }

        return $res;
      }
    }

    return 0;
  }

  /**
   * Updates row(s) in the table.
   *
   * Accepts a string id or a filter array.
   * Handles JSON cfg partial updates if configured in the table arch.
   * When caching is enabled:
   * - refreshes row cache entries for affected ids
   * - clears the ids-list cache for this table
   *
   * @param string|array $filter Row id or filter configuration.
   * @param array        $data   The data to update.
   * @return int Number of updated rows.
   */
  protected function dbTraitUpdate(string|array $filter, array $data): int
  {
    $ccfg = $this->getClassCfg();
    $f = $ccfg['arch'][$this->class_table_index];

    if (!is_array($filter)) {
      $filter = [$f['id'] => $filter];
    }

    if (!$this->dbTraitExists($filter)) {
      throw new Exception(X::_("Impossible to find the given row"));
    }

    $ids = ($this->class_cfg['cache'] ?? false) ? $this->dbTraitGetIds($filter) : [];

    if ($data = $this->dbTraitPrepare($data)) {
      // JSON cfg partial update support
      if (!empty($f['cfg'])) {
        $col = $f['cfg'];
        if (!empty($data[$col])) {
          if (is_string($data[$col])) {
            $data[$col] = json_decode($data[$col], true);
          }

          $jsonUpdate = 'JSON_SET(IFNULL(' . $this->db->csn($col, true) . ' ,"{}")';
          foreach ($data[$col] as $k => $v) {
            $jsonUpdate .= ', "$.' . $k . '", ' . (
              is_iterable($v)
                ? "JSON_EXTRACT('".Str::escapeSquotes(json_encode($v))."', '$')"
                : ('"'.Str::escapeDquotes($v).'"')
            );
          }

          $jsonUpdate .= ")";
          $data[$col] = [null, $jsonUpdate];
        }
      }

      $res = $this->db->update($this->class_table, $data, $this->dbTraitGetFilterCfg($filter));

      if ($res && ($this->class_cfg['cache'] ?? false)) {
        foreach ($ids as $id) {
          $this->dbTraitCacheSet($id);
        }

        self::dbTraitCacheInit();
        self::$dbTraitCache->deleteAll('table/' . $this->class_table . '/ids/');
      }

      return $res;
    }

    return 0;
  }

  /**
   * Inserts or updates a row based on unique keys.
   *
   * Checks the table unique keys; if all columns of a key are provided,
   * tries to find an existing row id and updates it; otherwise inserts.
   *
   * @param array $data The row data.
   * @return string|null The row id (existing or newly inserted), or null on failure.
   */
  protected function dbTraitInsertUpdate(array $data): ?string
  {
    $cfg = $this->getClassCfg();
    $keys = $this->db->getUniqueKeys($this->class_table);
    $update = false;

    if (!empty($keys)) {
      foreach ($keys as $columns) {
        $checked = array_filter(
          $columns,
          fn($col) => !array_key_exists($col, $data) || is_null($data[$col])
        );

        if (empty($checked)) {
          $update = $this->db->selectOne(
            $this->class_table,
            $cfg['arch'][$this->class_table_index]['id'],
            array_intersect_key($data, array_flip($columns))
          );
          break;
        }
      }
    }

    if ($update) {
      $this->dbTraitUpdate($update, $data);
      return $update;
    }

    return $this->dbTraitInsert($data);
  }

  /**
   * Selects a single value from the first matching row.
   *
   * When caching is enabled, resolves ids then reads from row cache.
   *
   * @param string       $field  The field to return.
   * @param string|array $filter Row id or filter configuration.
   * @param array        $order  Order configuration.
   * @return mixed The field value, or null if not found.
   */
  protected function dbTraitSelectOne(string $field, string|array $filter = [], array $order = [])
  {
    if ($ids = ($this->class_cfg['cache'] ?? false) ? $this->dbTraitGetIds($filter, $order) : false) {
      if ($tmp = $this->dbTraitCacheGet($ids[0], [$field])) {
        return $tmp[$field] ?? null;
      }

      $tmp = $this->dbTraitCacheSet($ids[0], [$field]);
      return $tmp[$field] ?? null;
    }

    if ($res = $this->dbTraitSingleSelection($filter, $order, 'array', [$field])) {
      return $res[$field] ?? null;
    }

    return null;
  }

  /**
   * Selects a single row and returns it as an object.
   *
   * When caching is enabled, resolves ids then reads from row cache.
   *
   * @param string|array $filter Row id or filter configuration.
   * @param array        $order  Order configuration.
   * @param array        $fields Optional list of fields to return (same format as cache projection).
   * @return stdClass|null The row as an object, or null if not found.
   */
  protected function dbTraitSelect(string|array $filter = [], array $order = [], array $fields = []): ?stdClass
  {
    if ($ids = ($this->class_cfg['cache'] ?? false) ? $this->dbTraitGetIds($filter, $order) : false) {
      if ($tmp = $this->dbTraitCacheGet($ids[0], $fields)) {
        return (object)$tmp;
      }

      $tmp = $this->dbTraitCacheSet($ids[0], $fields);
      return $tmp ? (object)$tmp : null;
    }

    return $this->dbTraitSingleSelection($filter, $order, 'object', $fields);
  }

  /**
   * Selects a single row and returns it as an array.
   *
   * When caching is enabled, resolves ids then reads from row cache.
   *
   * @param string|array $filter Row id or filter configuration.
   * @param array        $order  Order configuration.
   * @param array        $fields Optional list of fields to return (same format as cache projection).
   * @return array|null The row as an array, or null if not found.
   */
  protected function dbTraitRselect(string|array $filter = [], array $order = [], array $fields = []): ?array
  {
    if ($ids = ($this->class_cfg['cache'] ?? false) ? $this->dbTraitGetIds($filter, $order) : false) {
      if ($tmp = $this->dbTraitCacheGet($ids[0], $fields)) {
        return $tmp;
      }

      return $this->dbTraitCacheSet($ids[0], $fields);
    }

    return $this->dbTraitSingleSelection($filter, $order, 'array', $fields);
  }

  /**
   * Returns an array of values for a single field, matching the given conditions.
   *
   * @param string $field The field to return.
   * @param array  $filter Filter configuration.
   * @param array  $order Order configuration.
   * @param int    $limit Max number of rows.
   * @param int    $start Offset.
   * @return array
   */
  protected function dbTraitSelectValues(
    string $field,
    array $filter = [],
    array $order = [],
    int $limit = 0,
    int $start = 0
  ): array
  {
    return $this->dbTraitSelection($filter, $order, $limit, $start, 'value', [$field]);
  }

  /**
   * Returns the number of rows matching the given conditions.
   *
   * @param array $filter Filter configuration.
   * @return int
   */
  protected function dbTraitCount(array $filter = []): int
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $req = $this->dbTraitGetRequestCfg($filter, [], 1, 0, [$this->fields['id']]);
    return $this->db->count($req);
  }

  /**
   * Returns an array of rows as objects matching the given conditions.
   *
   * When caching is enabled:
   * - resolves ids (possibly cached)
   * - for each id, returns cached row or loads it and caches it
   *
   * @param array $filter Filter configuration.
   * @param array $order Order configuration.
   * @param int   $limit Max number of rows.
   * @param int   $start Offset.
   * @param array $fields Optional list of fields to return (same format as cache projection).
   * @return array
   */
  protected function dbTraitSelectAll(
    array $filter = [],
    array $order = [],
    int $limit = 0,
    int $start = 0,
    $fields = []
  ): array
  {
    if ($ids = ($this->class_cfg['cache'] ?? false) ? $this->dbTraitGetIds($filter, $order) : false) {
      $res = [];

      foreach ($ids as $id) {
        if ($tmp = $this->dbTraitCacheGet($id, $fields)) {
          $res[] = (object)$tmp;
        }
        else {
          $tmp = $this->dbTraitCacheSet($id, $fields);
          if ($tmp) {
            $res[] = (object)$tmp;
          }
        }
      }

      return $res;
    }

    return $this->dbTraitSelection($filter, $order, $limit, $start, 'object', $fields);
  }

  /**
   * Returns an array of rows as arrays matching the given conditions.
   *
   * When caching is enabled:
   * - resolves ids (possibly cached)
   * - for each id, returns cached row or loads it and caches it
   *
   * @param array $filter Filter configuration.
   * @param array $order Order configuration.
   * @param int   $limit Max number of rows.
   * @param int   $start Offset.
   * @param array $fields Optional list of fields to return (same format as cache projection).
   * @return array
   */
  protected function dbTraitRselectAll(
    array $filter = [],
    array $order = [],
    int $limit = 0,
    int $start = 0,
    $fields = []
  ): array
  {
    if ($ids = ($this->class_cfg['cache'] ?? false) ? $this->dbTraitGetIds($filter, $order) : false) {
      $res = [];

      foreach ($ids as $id) {
        if ($tmp = $this->dbTraitCacheGet($id, $fields)) {
          $res[] = $tmp;
        }
        else {
          $tmp = $this->dbTraitCacheSet($id, $fields);
          if ($tmp) {
            $res[] = $tmp;
          }
        }
      }

      return $res;
    }

    return $this->dbTraitSelection($filter, $order, $limit, $start, 'array', $fields);
  }

  /**
   * Returns relations for a given row id.
   *
   * @param string      $id    The row id.
   * @param string|null $table Optional related table name to restrict results.
   * @return array|null
   */
  protected function dbTraitGetRelations(string $id, string|null $table = null): ?array
  {
    if ($this->dbTraitExists($id)) {
      $db =& $this->db;
      $res = [];

      foreach ($this->dbTraitGetTableRelations($table) as $rel) {
        if ($all = $db->getColumnValues($rel['table'], $rel['primary'], [$rel['col'] => $id])) {
          $res[$rel['table']] = [
            'col' => $rel['col'],
            'primary' => $rel['primary'],
            'values' => $all
          ];
        }
      }

      return $res;
    }

    return null;
  }

  /**
   * Builds a filter configuration for a simple search string.
   *
   * If $cols is empty, it will scan the table model and select:
   * - text/char columns
   * - int columns if the filter looks numeric
   *
   * @param string|int $filter Search term.
   * @param array      $cols   Columns to search on.
   * @param bool       $strict If true, uses '='; otherwise uses 'contains'.
   * @return array The filter configuration.
   */
  protected function dbTraitGetSearchFilter(string|int $filter, array $cols = [], bool $strict = false): array
  {
    $cfg = $this->getClassCfg();
    $isNumber = Str::isNumber($filter);

    $finalFilter = [
      'logic' => 'OR',
      'conditions' => []
    ];

    if (empty($cols)) {
      $tableCols = $this->db->modelize($cfg['table'])['fields'];
      foreach ($tableCols as $col => $colCfg) {
        if ((Str::pos($colCfg['type'], 'text') !== false) || (Str::pos($colCfg['type'], 'char') !== false)) {
          $cols[] = $col;
        }
        elseif ($isNumber && (Str::pos($colCfg['type'], 'int') !== false)) {
          $cols[] = $col;
        }
      }
    }

    foreach ($cols as $col) {
      $finalFilter['conditions'][] = [
        'field' => $this->db->cfn($col, $cfg['table']),
        'operator' => $strict ? '=' : 'contains',
        'value' => $filter
      ];
    }

    return $finalFilter;
  }

  /**
   * Executes a search on the table.
   *
   * @param array|string $filter Search string or a full filter configuration.
   * @param array        $cols   Columns to search on (used only if $filter is a string).
   * @param array        $fields Fields to return.
   * @param array        $order  Order configuration.
   * @param bool         $strict If true, uses '='; otherwise uses 'contains'.
   * @param int          $limit  Max rows.
   * @param int          $start  Offset.
   * @return array Array of rows as arrays.
   */
  protected function dbTraitSearch(
    array|string $filter,
    array $cols = [],
    array $fields = [],
    array $order = [],
    bool $strict = false,
    int $limit = 0,
    int $start = 0
  ): array
  {
    if (is_array($filter)) {
      $finalFilter = $filter;
      if (empty($fields) && !empty($cols)) {
        $fields = $cols;
      }
    }
    else {
      $finalFilter = $this->dbTraitGetSearchFilter($filter, $cols, $strict);
    }

    return $this->dbTraitRselectAll($finalFilter, $order, $limit, $start, $fields);
  }

  /**
   * Gets a single row and returns it, using dbTraitSelection().
   *
   * @param string|array $filter Row id or filter configuration.
   * @param array        $order  Order configuration.
   * @param string       $mode   'array'|'object' (passed through to dbTraitSelection()).
   * @param array        $fields Fields to return.
   * @return mixed The first matching row, or null.
   */
  private function dbTraitSingleSelection(
    string|array $filter,
    array $order,
    string $mode = 'array',
    array $fields = []
  ): mixed
  {
    $f = $this->class_cfg['arch'][$this->class_table_index];

    if (is_string($filter)) {
      $cfg = [$f['id'] => $filter];
    }
    elseif (is_array($filter)) {
      $cfg = $filter;
    }

    if (isset($cfg) && ($res = $this->dbTraitSelection($cfg, $order, 1, 0, $mode, $fields))) {
      return $res[0];
    }

    return null;
  }
}
