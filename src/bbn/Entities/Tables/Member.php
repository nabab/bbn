<?php

namespace bbn\Entities\Tables;

use bbn\Db;
use bbn\X;
use bbn\Entities\Models\Entities;
use bbn\Entities\Models\EntityJunction;
use bbn\Models\Cls\Nullall;
use bbn\Entities\Entity;

class Member extends EntityJunction
{
  protected static $default_class_cfg = [
    'table' => 'bbn_members',
    'tables' => [
      'members' => 'bbn_members'
    ],
    'arch' => [
      'members' => [
        'id' => 'id',
        'id_group' => 'id_group',
        'id_identity' => 'id_identity',
        'email' => 'email',
        'login' => 'login',
        'username' => 'username',
        'cfg' => 'cfg',
        'active' => 'active',
        'phone' => 'phone',
        'function' => 'fonction',
        'admin' => 'admin',
        'dev' => 'dev',
        'theme' => 'theme'
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
    if ($this->identity()->exists($id)) {

    }

    return null;
  }

}
