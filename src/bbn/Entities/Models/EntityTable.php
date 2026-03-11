<?php
namespace bbn\Entities\Models;

use bbn\X;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\DbPublicOps;
use bbn\Models\Tts\DbCache;
use bbn\Entities\Models\Entities;
use bbn\Entities\Models\EntityTrait;
use bbn\Entities\Entity;

abstract class EntityTable extends DbCls
{
  use DbCache;
  use DbPublicOps;
  use EntityTrait;

  public function __construct(
    Db $db,
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall(),
  ) {
    $this->initClassCfg();
    parent::__construct($db);
    if (!is_a($entity, Nullall::class)) {
      $this->id_entity = $entity->getId();
      $this->dbTraitSetFilterCfg([
        $this->fields["id_entity"] => $this->id_entity,
      ]);
    }

    $this->dbTraitCacheInit();
  }

  public function getIdEntity(): ?string
  {
    return $this->id_entity ?? null;
  }

  public function count(array $filter = []): int
  {
    return count($this->getAll($filter));
  }

  public function getAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    $res = $this->getRecords();
    $filter = $this->dbTraitGetFilterCfg($filter);
    if (!empty($filter)) {
      $res = X::filter($res, $filter);
    }
    if (!empty($order)) {
      $res = X::sortBy($res, $order);
    }
    if ($start) {
      $res = array_slice($res, $start);
    }
    if ($limit) {
      $res = array_slice($res, 0, $limit);
    }
    return $res;
  }

  public function getOne($filter = [], array $order = [], int $start = 0, $fields = []): ?array
  {
    $res = $this->getAll($filter, $order, 1, $start, $fields);
    return $res[0] ?? null;
  }
}
