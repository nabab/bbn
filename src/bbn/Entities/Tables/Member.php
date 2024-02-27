<?php

namespace bbn\Entities\Tables;

use bbn\Db;
use bbn\X;
use bbn\Entities\Models\Entities;
use bbn\Entities\Models\EntityTable;

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

}
