<?php

namespace bbn;

use bbn\Entities\AbstractEntityTable;
use bbn\Entities\Entity;
use bbn\Entities\Link;
use bbn\Entities\People;
use bbn\Entities\Address;
use bbn\Entities\Consultation;
use bbn\Entities\Document;
use bbn\Entities\Options as EntityOptions;
use bbn\Entities\Document\Request;
use bbn\Appui\Masks;
use bbn\Appui\Option;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;

abstract class Entities extends DbCls
{
  use DbActions {
    delete as private DbActionsDelete;
    update as private DbActionsUpdate;
    exists as private DbActionsExists;
  }

  protected function treatWhere(string|array $where): string|array
  {
    $cfg = $this->getClassCfg();
    if (!empty($cfg['arch'][$this->class_table_index]['easy_id']) && Str::isNumber($where)) {
      $where = [$cfg['arch'][$this->class_table_index]['easy_id'] => $where];
    }

    return $where;
  }


  public function delete(string|array $where)
  {
    return $this->DbActionsDelete($this->treatWhere($where));
  }


  public function update(string|array $where, array $data)
  {
    return $this->DbActionsUpdate($this->treatWhere($where), $data);
  }


  public function exists(string|array $where)
  {
    return $this->DbActionsExists($this->treatWhere($where));
  }

  protected static $default_class_cfg = [
    'classes' => [
      'link' => false,
      'people' => false,
      'address' => false,
      'entity' => false,
      'consultation' => false,
      'mail' => false,
      'document' => false,
      'document_request' => false,
      'note' => false,
      'entity_options' => false,
    ],
    'table' => 'bbn_entities',
    'tables' => [
      'entities' => 'bbn_entities',
      'people' => 'bbn_people',
      'address' => 'bbn_addresses',
      'links' => 'bbn_entities_links',
    ],
    'arch' => [
      'entities' => [
        'id' => [
          'name' => 'id',
          'type' => 'primary',
        ],
        'easy_id' => [
          'name' => 'easy_id',
          'type' => 'primary',
          'maxlength' => 5
        ],
        'name' => [
          'name' => 'name',
          'type' => 'string',
          'maxlength' => 100
        ],
        'id_parent' => [
          'name' => 'name',
          'type' => 'string',
          'maxlength' => 100,
          'alias' => 'parent'
        ],
        "cached" =>  [
          "name" => "cached",
          "nullable" => true,
          "type" => "datetime",
        ],
        "change" => [
          "name" => "change",
          "nullable" => true,
          "type" => "datetime",
        ],
        "full" => [
          "name" => "full",
          "nullable" => true,
          "type" => "int",
        ],
      ]
    ],
  ];

  private static $links = [];

  private static $classes = [];


  private $linkCls;


  public function __construct(
    Db $db,
    array $cfg = null,
    protected Option|null $options = null,
    protected Mail|null $mail = null,
    private People|null $people = null,
    private Address|null $address = null,
    private Consultation|null $consultation = null,
    private Document|null $document = null,
    private Request|null $request = null,
    private EntityOptions|null $entityOptions = null,
    private Masks|null $masks = null,
  )
  {
    parent::__construct($db);
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
    $cls = $this->class_cfg['classes'];
    if ($cls['link']) {
      $this->linkCls = $cls['mail'];
    }
    
  }

  public function get($id): Entity
  {
    $cls = $this->class_cfg['classes'];
    return new $cls['entity']($this->db, $id, $this);
  }

  public function masks(): ?Masks
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->masks) {
      $this->masks = new Masks($this->db);
    }

    return $this->masks;
  }

  public function people(): ?People
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->people && $cls['people']) {
      $this->people = new $cls['people']($this->db);
    }

    return $this->people;
  }


  public function address(): ?Address
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->address && $cls['address']) {
      $this->address = new $cls['address']($this->db);
    }

    return $this->address;
  }
  
  public function options(): ?Option
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->options && $cls['option']) {
      $this->options = new $cls['option']($this->db);
    }

    return $this->options;
  }


  public function consultation(): ?Consultation
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->consultation && $cls['consultation']) {
      $this->consultation = new $cls['consultation']($this->db);
    }  
    
    return $this->consultation;
  }  

  public function mail(): ?Option
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->mail && $cls['mail']) {
      $this->mail = new $cls['mail']($this->db);
    }

    return $this->mail;
  }


  public function document(Entity|null $entity = null): ?Document
  {
    $cls = $this->class_cfg['classes'];
    if (!empty($cls['document'])) {
      if ($entity) {
        return new $cls['document']($this->db, $this, $entity);
      }

      if (!$this->document) {
        $this->document = new $cls['document']($this->db);
      }

      return $this->document;
    }
    
    return null;
  }


  public function request(Entity|null $entity = null): ?Request
  {
    $cls = $this->class_cfg['classes'];
    if (!empty($cls['request'])) {
      if ($entity) {
        return new $cls['request']($this->db, $this, $entity);
      }

      if (!$this->request) {
        $this->request = new $cls['request']($this->db);
      }
      
      return $this->request;
    }
    
    return null;
  }


  public function entityOptions(Entity|null $entity = null): ?EntityOptions
  {
    $cls = $this->class_cfg['classes'];
    if (!empty($cls['entity_options'])) {
      if ($entity) {
        return new $cls['entity_options']($this->db, $this, $entity);
      }

      if (!$this->entityOptions) {
        $this->entityOptions = new $cls['entity_options']($this->db);
      }
      
      return $this->entityOptions;
    }
    
    return null;
  }


  public function getBasicInfo($id): ?array
  {
    if ($this->exists($id)) {
      $cfg = $this->getClassCfg();
      $arc = $cfg['arch']['entities'];
      $fields = [$arc['id'], $arc['name']];
      if (!empty($arc['easy_id'])) {
        $fields[] = $arc['easy_id'];
      }

      return $this->db->rselect(
        $this->class_cfg['table'],
        $fields,
        [$arc['id'] => $id]
      );
    }

    return null;
  }


  protected function getLink(string $linkCls, Entity|null $entity = null): ?Link
  {
    $id = $this->options()->fromCode($linkCls::$codes);
    if (!$entity) {
      if (!isset(self::$links[$id])) {
        $link = new $linkCls($this->db, $this);
        self::setLink($id, $link);
      }

      return self::$links[$id];
    }

    return new $linkCls($this->db, $this, $entity);
  }


  protected function getClass(string $clsName, string $index, Entity|null $entity = null): AbstractEntityTable
  {
    if (!$entity) {
      if (!isset(self::$classes[$index])) {
        $cls = new $clsName($this->db, $this);
        self::setClass($index, $cls);
      }

      return self::$classes[$index];
    }

    return new $clsName($this->db, $this, $entity);
  }


  private static function setLink(string $id, Link $link): void
  {
    self::$links[$id] = $link;
  }


  private static function setClass(string $index, AbstractEntityTable $cls): void
  {
    self::$classes[$index] = $cls;
  }

}