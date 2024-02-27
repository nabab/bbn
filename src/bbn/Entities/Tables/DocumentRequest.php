<?php

namespace bbn\Entities\Tables;

use bbn\X;
use bbn\Db;
use bbn\User;
use bbn\Entities\Models\Entities;
use bbn\Entities\Entity;
use bbn\Entities\Models\EntityTable;
use bbn\Models\Tts\DbConfig;

abstract class DocumentRequest extends EntityTable
{
  use DbConfig;

  protected static $default_class_cfg = [
    'table' => 'bbn_documents_requests',
    'tables' => [
      'documents_requests' => 'bbn_documents_requests'
    ],
    'arch' => [
      'documents_requests' => [
        "id" => "id",
        "id_entity" => "id_entity",
        "date_envoi" => "date_envoi",
        "message" => "message",
        "dernier_rappel" => "dernier_rappel",
        "num_rappels" => "num_rappels"
      ]
    ]
  ];

}

