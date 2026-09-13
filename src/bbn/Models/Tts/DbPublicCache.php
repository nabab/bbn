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
use bbn\X;

/**
 * Provides Cache helpers built on top of DbActions, with row-level caching:
 * - Row cache: table/<table_name>/<id> -> full row array
 *
 * Notes:
 * - The cache is designed to reduce repeated SELECTs, especially in long-lived workers.
 * - Query results are cached as lists of ids, then rows are cached by id.

 */
trait DbPublicCache
{
  use DbCache;

  /**
   * Returns the cache key for a row.
   *
   * @param string $id The row's id.
   * @return string
   */
  public function dbCacheKey(string $id): string
  {
    return $this->dbTraitRowCacheKey($id);
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
  public function dbCacheGet(
    string $id,
    array $fields = [],
    bool $autoExclude = false,
  ): ?array {
    return $this->dbTraitCacheGet($id, $fields, $autoExclude);
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
  public function dbCacheSet(string $id, array $fields = []): ?array
  {
    return $this->dbTraitCacheSet($id, $fields);
  }

  /**
   * Deletes a row from cache.
   *
   * @param string $id The row's id.
   * @return void
   */
  public function dbCacheDelete(string $id): void
  {
    $this->dbTraitCacheDelete($id);
  }


  public function dbCacheHash(string $id): ?string
  {
    return $this->dbTraitCacheHash($id);
  }

  public function dbCacheInfo(string $id): ?array
  {
    return $this->dbTraitCacheInfo($id);
  }

  public function dbCacheImport(int $limit = 10000): ?int
  {
    return $this->dbTraitCacheImport($limit);
  }

  public function dbCacheGetSet(string $id, array $fields = []): ?array
  {
    return $this->dbTraitCacheGetSet($id, $fields);
  }
}
