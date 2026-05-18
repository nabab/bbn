<?php
namespace bbn\Entities\Models;

use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbJunction;
use bbn\Models\Cls\Nullall;
use bbn\Entities\Models\EntityTableJunction;
use bbn\Entities\Entity;

abstract class EntityJunction extends EntityTableJunction
{
  use DbJunction;

  public function __construct(
    Db $db, 
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    $this->initClassCfg();
    parent::__construct($db);
    $this->dbJunctionInit();
    if (!is_a($entity, '\\bbn\\Models\\Cls\\Nullall')) {
      $this->id_entity = $entity->getId();
      $this->dbTraitSetFilterCfg([$this->fields['id_entity'] => $this->id_entity]);
    }
  }
}
