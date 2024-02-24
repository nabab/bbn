<?php

namespace bbn\Entities;

use Exception;
use bbn\Db;
use bbn\X;
use bbn\Entities;
use bbn\Entities\AbstractEntityTable;
use bbn\Entities\LinkTrait;
use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\DbActions;

class Link extends AbstractEntityTable
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
        'id_people' => 'id_people',
        'id_address' => 'id_address',
        'id_option' => 'id_option',
        'cfg' => 'cfg',
      ],
    ]
  ];

  protected static array $linkCfg = [
    "single" => false,
    "required" => false,
    "people" => false,
    "address" => false,
    "cfg" => false
  ];


  protected $people = null;
  protected $address = null;
  private $id_parent = null;
  
  private $code;
  private $text;
  
  protected $where = [];

  protected static array $codes = [];

  /**
   * @param array $link
   * @param array|null $people
   * @param array|null $address
   * @param array|null $option
   */
  public function __construct(
    Db $db, 
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db, $entities, $entity);
    if (!empty($this::$linkCfg['cfg'])) {
      $this->class_cfg['cfg'] = $this::$linkCfg['cfg'];
    }

    $o = $this->options();
    $codes = self::getCodes($this);
    if (!empty($codes) && ($this->type = $o->fromCode($codes))) {
      $this->cfg =& $this::$linkCfg;
      $option = $o->option($this->type);
      $this->code = $option['code'];
      $this->text = $option['text'];
      $this->rootFilterCfg = [
        $this->fields['link_type'] => $this->type
      ];

      if (!empty($this->cfg['people'])) {
        $this->people = $this->entities->people();
      }

      if (!empty($this->cfg['address'])) {
        $this->address = $this->entities->address();
      }

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
    return $this::$linkCfg;
  }

  public function getList()
  {
    return $this->selectValues($this->fields['id'], []);
  }

  public function getAll($start = 0, $limit = 0): array
  {
    return $this->rselectAll([], [], $start, $limit);
  }

  public function get($id = null): ?array
  {
    if ($this->cfg['single']) {
      $res = $this->rselectAll([]);
      return $res ? $res[0] : null;
    }

    if (!$id) {
      throw new Exception(X::_("This link is not single, you ust enter an ID for get"));
    }

    return $this->rselect([$this->fields['id'] => $id]);
  }

  public static function getCodes(Link $link): array
  {
    return $link::$codes;
  }
  
}

