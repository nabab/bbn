<?php

namespace bbn\Entities\Tables;

use Exception;
use bbn\Db;
use bbn\X;
use bbn\Entities\Models\Entities;
use bbn\Entities\Models\EntityTable;
use bbn\Entities\Entity;
use bbn\Entities\LinkTrait;
use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\DbActions;

class Link extends EntityTable
{
  use DbActions;

  private $type;
  private $cfg;
  protected static $default_class_cfg = [
    'table' => 'bbn_entities_links',
    'tables' => [
      'links' => 'bbn_entities_links'
    ],
    'arch' => [
      'links' => [
        'id' => 'id',
        'link_type' => 'link_type',
        'id_entity' => 'id_entity',
        'id_identity' => 'id_identity',
        'id_address' => 'id_address',
        'id_option' => 'id_option',
        'cfg' => 'cfg',
      ],
    ]
  ];

  protected static array $linkCfg = [
    "single" => false,
    "required" => false,
    "identity" => false,
    "address" => false,
    "cfg" => false
  ];


  private $id_parent = null;
  private $code;
  private $text;

  protected $where = [];

  protected static array $codes = [];

  /**
   * @param Db $db
   * @param Entities $entities
   * @param Entity|Nullall $entity
   */
  public function __construct(
    Db $db,
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db, $entities, $entity);
    if (!empty(static::$linkCfg['cfg'])) {
      $this->class_cfg['cfg'] = static::$linkCfg['cfg'];
    }

    $o = $this->options();
    $codes = self::getCodes($this);
    $this->cfg =& static::$linkCfg;
    if (!empty($codes) && ($this->type = $o->fromCode($codes))) {
      $option = $o->option($this->type);
      $this->code = $option['code'];
      $this->text = $option['text'];
      $this->rootFilterCfg = [
        $this->fields['link_type'] => $this->type
      ];

      if (!empty($this->cfg['option'])) {
        if (!empty($this->cfg['option']['id_parent'])) {
          $this->id_parent = $this->options()->fromCode(...$this->cfg['option']['id_parent']);
          if (!$this->id_parent) {
            throw new Exception(X::_("The parent for %s is not defined", $this->text)); 
          }
        }
      }
    }
  }


  public function check() : bool
  {
    return (bool)$this->type;
  }


  public function getType()
  {
    return $this->type;
  }


  public function getText()
  {
    return $this->text;
  }


  public function getCode()
  {
    return $this->code;
  }


  public function getCfg()
  {
    return static::$linkCfg;
  }

  public function getList(array $filter = [])
  {
    return $this->dbTraitSelectValues($this->fields['id'], $filter);
  }

  public function getFullList(array $filter = [])
  {
    return $this->dbTraitRselectAll($filter);
  }

  public function get($id = null): ?array
  {
    if ($this->cfg['single']) {
      $res = $this->dbTraitRselectAll([]);
      return $res ? $res[0] : null;
    }

    if (!$id) {
      throw new Exception(X::_("This link is not single, you must enter an ID for get"));
    }

    return $this->dbTraitRselect([$this->fields['id'] => $id]);
  }

  public function getByIdentity(string $id): ?array
  {
    return $this->{$this->cfg['single'] ? 'dbTraitRselect' : 'dbTraitRselectAll'}([
      $this->fields['id_entity'] => $this->entity->getId(),
      $this->fields['id_identity'] => $id
    ]);
  }

  public function getByAddress(string $id): ?array
  {
    return $this->{$this->cfg['single'] ? 'dbTraitRselect' : 'dbTraitRselectAll'}([
      $this->fields['id_entity'] => $this->entity->getId(),
      $this->fields['id_address'] => $id
    ]);
  }

  public function update(array $data, string|array $where): int
  {
    return $this->dbTraitUpdate($where, $data);
  }

  public function delete(string $id): int
  {
    return $this->dbTraitDelete($id);
  }

  public function insert(array $data): ?string
  {
    foreach (self::$linkCfg as $k => $v) {
      if ($v['required'] && !isset($data[$k])) {
        throw new Exception(X::_("The field %s is required", $k));
      }
    }

    $data[$this->fields['link_type']] = $this->type;
    if ($this->entity) {
      $data[$this->fields['id_entity']] = $this->entity->getId();
    }

    if (empty($data[$this->fields['id_entity']])) {
      throw new Exception(X::_("The entity is not defined"));
    }

    return $this->dbTraitInsert($data);
  }

  public static function getCodes(Link $link): array
  {
    return $link::$codes;
  }
}

