<?php

namespace bbn\Accounting;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Dbconfig;
use bbn\Db;


class Item extends DbCls
{
  use Dbconfig;
  use Common;

  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_accounting_taxes',
    'tables' => [
      'taxes' => 'bbn_accounting_taxes'
    ],
    'arch' => [
      'taxes' => [
        "id" => "id",
        "id_country" => "id_country",
        "name" => "name",
        "type" => "type",
        "rate" => "rate",
        "enabled" => "enabled"
      ]
    ],
  ];

  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
  }
}
