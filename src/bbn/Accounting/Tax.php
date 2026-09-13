<?php

namespace bbn\Accounting;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbOps;
use bbn\Db;

class Tax extends DbCls
{
  use DbOps;
  use Common;

  protected static $default_class_cfg = [
    "errors" => [],
    "table" => "bbn_accounting_taxes",
    "tables" => [
      "taxes" => "bbn_accounting_taxes",
    ],
    "arch" => [
      "taxes" => [
        "id" => "id",
        "id_country" => "id_country",
        "name" => "name",
        "type" => "type",
        "rate" => "rate",
        "enabled" => "enabled",
      ],
    ],
  ];

  public function __construct(Db $db)
  {
    // Setting up the class configuration
    $this->initClassCfg();
    // The database connection
    parent::__construct($db);
  }
}
