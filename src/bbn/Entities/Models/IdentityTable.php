<?php
namespace bbn\Entities\Models;

use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbPublicOps;
use bbn\Models\Tts\DbPublicCache;
use bbn\Entities\Identity;

abstract class IdentityTable extends DbCls
{
  use DbPublicCache;
  use DbPublicOps;

  public function __construct(Db $db, protected Identity $identity)
  {
    parent::__construct($db);
    $this->initClassCfg();
    $this->dbTraitCacheInit();
  }
}
