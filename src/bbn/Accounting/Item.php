<?php

namespace bbn\Accounting;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Db;


class Item extends DbCls
{
  use DbActions;
  use Common;

  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_accounting_items',
    'tables' => [
      'items' => 'bbn_accounting_items'
    ],
    'arch' => [
      'items' => [
        "id" => "id",
        "id_entity" => "id_entity",
        "name" => "name",
        "ref" => "ref",
        "description" => "description",
        "id_category" => "id_category",
        "id_currency" => "id_currency",
        "id_tax" => "id_tax",
        "price" => "price",
        "cost" => "cost",
        "quantity" => "quantity",
        "enabled" => "enabled"
      ]
    ],
  ];

  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->initClassCfg($cfg);
  }
}
