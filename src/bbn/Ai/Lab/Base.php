<?php
namespace bbn\Ai\Lab;

use bbn\X;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;

abstract class Base extends DbCls
{
  use DbActions;
  protected static $default_class_cfg = [];

  public function __construct(Db $db)
  {
    parent::__construct($db);
    $this->initClassCfg(self::$default_class_cfg);
  }
}