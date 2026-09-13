<?php

namespace bbn\Entities\Models;

use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\Cache;
use Exception;
use bbn\Db;
use bbn\X;
use bbn\Appui\Option;
use bbn\Entities\Entity;
use bbn\Entities\Identity;
use bbn\Entities\Address;

use function count;

trait EntityTrait
{
  use Cache;


  protected static $default_class_cfg;

  protected $id_entity;


  public function getEntities() {
    return $this->entities;
  }

  public function getId()
  {
    return $this->id_entity;
  }


  public function getEasyId(): ?int
  {
    if ($this->entity) {
      return $this->entity()->getEasyId();
    }

    return null;
  }


  public function check(): bool
  {
    if ($this->id_entity) {
      return $this->entities->exists($this->id_entity);
    }

    return $this->entities->check();
  }
  

  public function identity(): ?Identity
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
    return count(X::filter($this->getRecords(), $filter));
  }

  public function getAll(array $filter = [], string|array $order= [], int $limit = 0, int $start = 0, $fields = []): array
  {
    $ids = $this->dbTraitSelectValues($this->fields["id"], $filter, $order, $limit, $start);
    return array_map(fn($a) => $this->rselect($a), $ids);
  }

  public function getOne($filter = [], string|array $order= [], int $start = 0, $fields = []): ?array
  {
    return $this->dbTraitRselect(...func_get_args());
  }

  public function getRecords(?string $idx = null)
  {
    return $this->entity->getRecords($idx ?: static::$default_class_cfg['table']);
  }
}
