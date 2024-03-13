<?php
namespace bbn\Entities\Models;


use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbJunction;
use bbn\Entities\Models\EntityTrait;

abstract class EntityJunction extends DbCls
{
  use DbJunction;
  use EntityTrait;
}
