<?php
namespace bbn\Entities;


use bbn\Models\Cls\Nullall;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\Cache;
use Exception;
use bbn\Db;
use bbn\X;
use bbn\Appui\Option;
use bbn\Entities;

abstract class AbstractEntityTable extends DbCls
{
  use DbActions;
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
    $this->_init_class_cfg($this::$default_class_cfg);
    $this->cacheInit();
    if ($entity) {
      $this->id_entity = $entity->getId();
      $this->DbActionsSetFilterCfg([$this->fields['id_entity'] => $this->id_entity]);
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
    $this->DbActionsResetFilterCfg();
  }


  public function check(): bool
  {
    if ($this->id_entity) {
      return $this->entities->check($this->id_entity);
    }

    return $this->entities->check();
  }
  

  public function people(): ?People
  {
    return $this->entities->people();
  }

  public function address(): ?Address
  {
    return $this->entities->address();
  }

  public function options(): ?Option
  {
    return $this->entities->options();
  }

  public function entity(): ?Entity
  {
    return $this->entity;
  }
}
