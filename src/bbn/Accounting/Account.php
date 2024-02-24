<?php

namespace bbn\Accounting;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Db;


class Account extends DbCls
{
  use DbActions;
  use Common;

  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_accounting_accounts',
    'tables' => [
      'accounts' => 'bbn_accounting_accounts'
    ],
    'arch' => [
      'accounts' => [
        "id" => "id",
        "name" =>  "name",
        "account_number" => "account_number",
        "id_entity" => "id_entity",
        "id_bank" => "id_bank",
        "id_currency" => "id_currency"
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
