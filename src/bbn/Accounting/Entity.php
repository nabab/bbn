<?php

namespace bbn\Accounting;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Db;


class Entity extends DbCls
{
  use DbActions;
  use Common;

  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_accounting_entities',
    'tables' => [
      'entities' => 'bbn_accounting_entities'
    ],
    'arch' => [
      'entities' => [
        "id" => "id",
        "name" => "name",
        "id_address" => "id_address",
        "owner" => "owner",
        "id_country" => "id_country",
        "tax_number" => "tax_number"
      ]
    ]
  ];

  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->initClassCfg($cfg);
  }
}
