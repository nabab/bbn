<?php

namespace bbn\Models\Cls;

use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbOps;
use bbn\Models\Tts\Cache;
use bbn\Entities\Models\Entities;
use bbn\Entities\Entity;

class NoteLink extends DbCls
{
  use DbOps;
  use Cache;

  protected static $default_class_cfg;

  protected $id_entity;

  public function __construct(
    Db $db,
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall(),
  ) {
    $this->initClassCfg();
    parent::__construct($db);
    if ($entity) {
      $this->id_entity = $entity->getId();
      $this->dbTraitSetFilterCfg([
        $this->fields["id_entity"] => $this->id_entity,
      ]);
    }
  }

  public function getEntities()
  {
    return $this->entities;
  }

  public function getId()
  {
    return $this->id_entity;
  }
}
