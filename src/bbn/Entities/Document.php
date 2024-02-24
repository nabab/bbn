<?php

namespace bbn\Entities;

use bbn\X;
use bbn\Db;
use bbn\User;
use bbn\Entities\AbstractEntityTable;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbConfig;

abstract class Document extends AbstractEntityTable
{
  use DbConfig;

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_documents',
    'tables' => [
      'documents' => 'bbn_entities_documents'
    ],
    'arch' => [
      'documents' => [
        "id" => "id",
        "id_entity" => "id_entity",
        "name" => "name",
        "doc_type" => "doc_type",
        "files" => "files",
        "date_added" => "date_added",
        "comment" => "comment",
        "treatment" => "treatment",
        "date_doc" => "date_doc",
        "date_ref" => "date_ref",
        "id_option" => "id_option",
        "labels" => "labels",
      ]
    ]
  ];
}

