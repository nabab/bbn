<?php

namespace bbn\Models\Cls;

use Db as DbCls;
use bbn\Models\Tts\DbConfig;

class NoteLink extends DbCls
{
  use DbConfig;

  protected static $default_class_cfg;

  protected $id_entity;

  public function __construct(
    Db $db, 
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db);
    $this->_init_class_cfg(static::$default_class_cfg);
    $this->cacheInit();
    if ($entity) {
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

}
