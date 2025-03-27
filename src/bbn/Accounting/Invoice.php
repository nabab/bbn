<?php

namespace bbn\Accounting;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Db;


class Invoice extends DbCls
{
  use DbActions;
  use Common;

  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_accounting_invoices',
    'tables' => [
      'invoices' => 'bbn_accounting_invoices'
    ],
    'arch' => [
      'invoices' => [
        "id" => "id",
        "id_entity" => "id_entity",
        "order_number" => "order_number",
        "invoice_number" => "invoice_number",
        "status" => "status",
        "invoiced" => "invoiced",
        "due" => "due",
        "amount" => "amount",
        "id_category" => "id_category",
        "id_currency" => "id_currency",
        "currency_rate" => "currency_rate",
        "id_contact" => "id_contact",
        "comment" => "comment",
        "deleted" => "deleted"
      ]
    ],
  ];

  public function __construct(Db $db, array|null $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->initClassCfg($cfg);
  }
}
