<?php

namespace bbn\Entities\Tables;

use bbn\Db;
use bbn\X;
use bbn\Entities\Models\Entities;
use bbn\Entities\Models\EntityTable;
use bbn\Models\Cls\Nullall;
use bbn\Entities\Entity;

class Member extends EntityTable
{
  protected static $default_class_cfg = [
    'table' => 'bbn_members_entities',
    'tables' => [
      'members_entities' => 'bbn_members_entities'
    ],
    'arch' => [
      'members_entities' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'id_member' => 'id_member',
        'id_group' => 'id_group',
        'id_option' => 'id_option',
        'cfg' => 'cfg'
      ]
    ]
  ];


  public function __construct(
    Db $db,
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db, $entities, $entity);
  }


  public function getContact($id): ?array
  {
    if ($this->identities()->exists($id)) {

    }

    return null;
  }

}
