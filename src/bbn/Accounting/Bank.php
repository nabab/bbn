<?php

namespace bbn\Accounting;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Db;


class Bank extends DbCls
{
  use DbActions;
  use Common;

  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_accounting_banks',
    'tables' => [
      'banks' => 'bbn_accounting_banks'
    ],
    'arch' => [
      'banks' => [
        "id" => "id",
        "name" => "name",
        "id_address" => "id_address"
      ]
    ]
  ];

  public function __construct(Db $db, array|null $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->initClassCfg($cfg);
  }
}
