<?php

namespace bbn\Entities\Tables;

use bbn\Entities\Models\EntityTable;

class MemberMessage extends EntityTable
{
  protected static $default_class_cfg = [
    'table' => 'bbn_members_messages',
    'tables' => [
      'members_messages' => 'bbn_members_messages'
    ],
    'arch' => [
      'members_messages' => [
        "id" => "id",
        "id_identity" => "id_identity",
        "id_entity" => "id_entity",
        "moment" => "moment",
        "type" => "type",
        "subtype" => "subtype",
        "email" => "email",
        "text" => "text",
        "treated" => "treated",
      ]
    ]
  ];

}
