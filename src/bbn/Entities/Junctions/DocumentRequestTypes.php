<?php

namespace bbn\Entities\Junctions;

use bbn\Entities\Models\EntityJunction;
use bbn\Models\Tts\DbJunction;

class DocumentRequestTypes extends EntityJunction
{
  use DbJunction;

  protected static $default_class_cfg = [
    'table' => 'bbn_documents_requests_types',
    'tables' => [
      'request_types' => 'bbn_documents_requests_types',
    ],
    'arch' => [
      'request_types' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'id_request' => 'id_request',
        'doc_type' => 'doc_type'
      ]
    ]
  ];

  protected function getRequestDocTypes(string $id_request)
  {
    if ($this->entity->check()) {
      $fields = $this->class_cfg['arch']['types'];
      return $this->db->rselectAll([
        'table' => $this->class_cfg['tables']['request_types'],
        'fields' => [
          $fields['doc_type'] => $this->db->cfn($fields['doc_type'], $this->class_cfg['tables']['request_typestypes'])
        ],
        'where' => [
          $this->db->cfn($fields['id_request'], $this->class_cfg['tables']['request_types']) => $id_request
        ]
      ]);
    }

    return [];
  }

}

