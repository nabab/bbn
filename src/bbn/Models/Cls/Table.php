<?php
namespace bbn\Models\Cls;

use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\References;

abstract class Table extends DbCls
{
  use References;
  use DbActions;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => null,
    'arch' => []
  ];

  /** @var array */
  protected $class_cfg;

  public function __construct(Db $db, array $cfg = [])
  {
    parent::__construct($db);
    $this->initClassCfg($cfg ?: static::$default_class_cfg);
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
