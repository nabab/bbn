<?php

namespace bbn\Entities;

use bbn\X;
use bbn\Db;
use bbn\User;
use bbn\Entities\AbstractEntityTable;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbConfig;

abstract class DocumentRequest extends AbstractEntityTable
{
  use DbConfig;

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_documents_requests',
    'tables' => [
      'documents_requests' => 'bbn_entities_documents_requests'
    ],
    'arch' => [
      'documents_requests' => [
        "id" => "id",
        "id_entity" => "id_entity",
        "sent" => "sent",
        "message" => "message",
        "last_call" => "last_call",
        "num_calls" => "num_calls",
      ]
    ]
  ];
}

