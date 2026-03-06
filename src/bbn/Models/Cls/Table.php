<?php
namespace bbn\Models\Cls;

use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbOps;
use bbn\Models\Tts\References;

abstract class Table extends DbCls
{
  use References;
  use DbOps;

  /** @var array */
  protected static $default_class_cfg = [
    "table" => null,
    "arch" => [],
  ];

  /** @var array */
  protected $class_cfg;

  public function __construct(Db $db)
  {
    $this->initClassCfg();
    parent::__construct($db);
  }

  public function cfg(): array
  {
    return $this->class_cfg;
  }

  public function exists(string $id): bool
  {
    return $this->dbTraitExists($id);
  }
}
