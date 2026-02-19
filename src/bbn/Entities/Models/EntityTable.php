<?php
namespace bbn\Entities\Models;


use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActionsPublic;
use bbn\Entities\Models\EntityTrait;

abstract class EntityTable extends DbCls
{
  use DbActionsPublic;
  use EntityTrait;
}
