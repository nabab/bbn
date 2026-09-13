<?php
namespace bbn\Ai\Lab;

use bbn\X;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbOps;

abstract class Base extends DbCls
{
  use DbOps;
  protected static $default_class_cfg = [];

  public function __construct(Db $db)
  {
    $this->initClassCfg();
    parent::__construct($db);
  }
}
