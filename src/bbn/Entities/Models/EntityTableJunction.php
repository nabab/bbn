<?php
namespace bbn\Entities\Models;

use bbn\X;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbPublicOps;
use bbn\Entities\Models\EntityTrait;

abstract class EntityTableJunction extends DbCls
{
  use DbPublicOps;
  use EntityTrait;

  public function insert(array $data): ?string
  {
    if ($res = $this->dbTraitInsert($data)) {
      $data = $this->dbTraitCacheGetSet($res);
      $this->entity()->updateRecord($this->getClassTable(), $res, $data);
    }

    return $res;
  }

  public function insertIgnore(array $data): ?string
  {
    if ($res = $this->dbTraitInsert($data, true)) {
      $data = $this->dbTraitCacheGetSet($res);
      $this->entity()->updateRecord($this->getClassTable(), $res, $data);
    }

    return $res;
  }

  public function update(string|array $filter, array $data): int
  {
    if ($res = $this->dbTraitUpdate($filter, $data)) {
      $ids = $this->dbTraitGetIds($filter);
      foreach ($ids as $id) {
        $data = $this->dbTraitCacheGetSet($id);
        $this->entity()->updateRecord($this->getClassTable(), $id, $data);
      }      
    }

    return $res;
  }

  public function delete(string|array $filter): int
  {
    if ($res = $this->dbTraitDelete($filter)) {
      $ids = $this->dbTraitGetIds($filter);
      foreach ($ids as $id) {
        $this->entity()->deleteRecord($this->getClassTable(), $id, []);
      }
    }

    return $res;
  }

}
