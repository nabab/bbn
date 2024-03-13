<?php

namespace bbn\Entities\Tables;

use bbn\X;
use bbn\Db;
use bbn\User;
use bbn\Entities\Models\EntityTable;

abstract class Document extends EntityTable
{
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
        "content" => "content",
        "date_doc" => "date_doc",
        "date_ref" => "date_ref",
        "id_option" => "id_option",
        "labels" => "labels",
      ]
    ]
  ];
}

