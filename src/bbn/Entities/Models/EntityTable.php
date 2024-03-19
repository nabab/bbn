<?php
namespace bbn\Entities\Models;


use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Entities\Models\EntityTrait;

abstract class EntityTable extends DbCls
{
  use DbActions;
  use EntityTrait;
}
