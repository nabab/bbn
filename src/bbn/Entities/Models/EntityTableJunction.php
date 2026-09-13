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
    dbTraitUpdate as dbTraitEntityCacheUpdate;
    dbTraitDelete as dbTraitEntityCacheDelete;
    dbTraitInsert as dbTraitEntityCacheInsert;
    dbTraitInsertUpdate as dbTraitEntityCacheInsertUpdate;
  }
  use DbPublicOps;
  use EntityTrait;

  protected function dbTraitInsert(array $data, bool $ignore = false): ?string
  {
    if ($res = $this->dbTraitEntityCacheInsert($data, $ignore)) {
      $data = $this->dbTraitCacheGetSet($res);
      $this->entity()->updateRecord($this->getClassTable(), $res);
    }

    return $res;
  }

  protected function dbTraitUpdate(string|array $filter, array $data): int
  {
    if ($res = $this->dbTraitEntityCacheUpdate($filter, $data)) {
      $ids = $this->dbTraitGetIds($filter);
      foreach ($ids as $id) {
        $data = $this->dbTraitCacheSet($id);
        $this->entity()->updateRecord($this->getClassTable(), $id);
      }      
    }

    return $res;
  }

  protected function dbTraitDelete(string|array $filter): int
  {
    if ($res = $this->dbTraitEntityCacheDelete($filter)) {
      $ids = $this->dbTraitGetIds($filter);
      foreach ($ids as $id) {
        $this->dbTraitCacheDelete($id);
        $this->entity()->deleteRecord($this->getClassTable(), $id);
      }
    }

    return $res;
  }

}
