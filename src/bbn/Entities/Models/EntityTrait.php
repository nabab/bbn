<?php

namespace bbn\Entities\Models;

use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\Cache;
use Exception;
use bbn\Db;
use bbn\X;
use bbn\Appui\Option;
use bbn\Entities\Models\Entities;
use bbn\Entities\Entity;
use bbn\Entities\Identities;
use bbn\Entities\Address;

trait EntityTrait
{
  use Cache;

  protected static $default_class_cfg;

  protected $id_entity;

  public function __construct(
    Db $db, 
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db);
    $this->initClassCfg(static::$default_class_cfg);
    $this->cacheInit();
    if (!is_a($entity, 'bbn\\Models\\Cls\\Nullall')) {
      $this->id_entity = $entity->getId();
      $this->dbTraitSetFilterCfg([$this->fields['id_entity'] => $this->id_entity]);
    }
  }

  public function getEntities() {
    return $this->entities;
  }

  public function getId()
  {
    return $this->id_entity;
  }

  public function unsetEntity()
  {
    $this->id_entity = null;
    $this->dbTraitResetFilterCfg();
  }


  public function check(): bool
  {
    if ($this->id_entity) {
      return $this->entities->exists($this->id_entity);
    }

    return $this->entities->check();
  }
  

  public function identity(): ?Identities
  {
    return $this->entities->identity();
  }

  public function address(): ?Address
  {
    return $this->entities->address();
  }

  public function options(): ?Option
  {
    return $this->entities->options();
  }

  public function entity(): Entity|Nullall
  {
    return $this->entity;
  }


  public function count(array $filter = []): int
  {
    return $this->dbTraitCount($filter);
  }

  public function getAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitRselectAll(...func_get_args());
  }

  public function getOne($filter = [], array $order = [], int $start = 0, $fields = []): ?array
  {
    return $this->dbTraitRselect(...func_get_args());
  }
}