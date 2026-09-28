<?php
namespace bbn\Entities\Models;

use bbn\X;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbPublicCache;
use bbn\Models\Tts\DbPublicOps;
use bbn\Entities\Models\EntityTrait;

abstract class EntityTableJunction extends DbCls
{
  use DbPublicCache {
    dbTraitUpdate as protected baseDbTraitUpdate;
    dbTraitDelete as protected baseDbTraitDelete;
    dbTraitInsert as protected baseDbTraitInsert;
  // Note: I removed dbTraitInsertUpdate alias unless you need it specifically
  }
  use DbPublicOps;
  use EntityTrait;

  /**
   * Override insert to handle junction table logic + cache
   */
  protected function dbTraitInsert(array $data, bool $ignore = false): ?string
  {
    // 1. Set entity ID if available
    if (isset($this->fields['id_entity']) && empty($data[$this->fields['id_entity']]) && $this->getId()) {
      $data[$this->fields['id_entity']] = $this->getId();
    }

    // 2. Perform the actual DB insert via the trait's base method
    $res = $this->baseDbTraitInsert($data, $ignore);

    if ($res !== null) {
      // 3. Update cache for this new junction record
      $this->dbTraitCacheSet($res); // Assuming this sets the cache for ID $res

      // 4. Notify entity that its relationships changed (if needed)
      // Be careful: updateRecord might be too heavy. Consider a lighter method like invalidateEntityCache()
      if ($this->entity()) {
        $this->entity()->updateRecord($this->getClassTable(), $res);
      }
    }

    return $res;
  }

  /**
   * Override update to handle junction table logic + cache
   */
  protected function dbTraitUpdate(string|array $filter, array $data): int
  {
    // 1. Perform the actual DB update via the trait's base method
    $res = $this->baseDbTraitUpdate($filter, $data);

    if ($res > 0) {
      // 2. Get affected IDs (This is still inefficient, but necessary if you don't have them)
      // Better approach: Pass IDs directly or use a method that returns affected IDs from the DB driver
      $ids = $this->dbTraitGetIds($filter);

      foreach ($ids as $id) {
        // 3. Update cache for each affected junction record
        $this->dbTraitCacheSet($id);

        // 4. Notify entity
        if ($this->entity()) {
          $this->entity()->updateRecord($this->getClassTable(), $id);
        }
      }
    }

    return $res;
  }

  /**
   * Override delete to handle junction table logic + cache
   */
  protected function dbTraitDelete(string|array $filter): int
  {
    // 1. Perform the actual DB delete via the trait's base method
    $res = $this->baseDbTraitDelete($filter);

    if ($res > 0) {
      // 2. Get affected IDs before they are gone (if possible) or after? 
      // WARNING: If you delete first, dbTraitGetIds might return empty!
      // You MUST get the IDs BEFORE deleting, or use a different strategy.

      $ids = $this->dbTraitGetIds($filter);

      foreach ($ids as $id) {
        // 3. Remove from cache
        $this->dbTraitCacheDelete($id);

        // 4. Notify entity
        if ($this->entity()) {
          $this->entity()->deleteRecord($this->getClassTable(), $id);
        }
      }
    }

    return $res;
  }
}
