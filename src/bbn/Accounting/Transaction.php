<?php

namespace bbn\Accounting;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Dbconfig;
use bbn\Db;


class Transaction extends DbCls
{
  use Dbconfig;
  use Common;

  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_accounting_transactions',
    'tables' => [
      'transactions' => 'bbn_accounting_transactions'
    ],
    'arch' => [
      'transactions' => [
        "id" => "id",
        "id_entity" => "id_entity",
        "id_account" => "id_account",
        "id_parent" => "id_parent",
        "paid" => "paid",
        "type" => "type",
        "amount" => "amount",
        "method" => "method",
        "id_currency" => "id_currency",
        "currency_rate" => "currency_rate",
        "reference" => "reference",
        "description" => "description",
        "reconciled" => "reconciled"
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
