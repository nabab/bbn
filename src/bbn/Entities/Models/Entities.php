<?php

namespace bbn\Entities\Models;

use Exception;
use stdClass;
use bbn\Db;
use bbn\Str;
use bbn\X;
use bbn\Entities\Entity;
use bbn\Entities\Tables\Link;
use bbn\Entities\Identities;
use bbn\Entities\Address;
use bbn\Entities\Models\EntityJunction;
use bbn\Entities\Models\EntityTable;
use bbn\Entities\Junctions\Consultation;
use bbn\Entities\Tables\Document;
use bbn\Entities\Tables\Options as EntityOptions;
use bbn\Entities\Tables\DocumentRequest;
use bbn\Mail;
use bbn\Appui\Database;
use bbn\Appui\Masks;
use bbn\Appui\Option;
use bbn\Appui\Uauth;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;

/**
 * Class Entities
 * Abstract base class for entity-related operations.
 * Provides methods for interacting with database entities, including CRUD operations.
 */
abstract class Entities extends DbCls
{
  use DbActions;

  /**
   * Default class configuration.
   * 
   * @var array
   */
  protected static $default_class_cfg = [
    'classes' => [
      'link' => false,
      'identities' => false,
      'address' => false,
      'entity' => false,
      'consultation' => false,
      'mail' => false,
      'document' => false,
      'document_request' => false,
      'note' => false,
      'entity_options' => false,
      'masks' => false,
      'uauth' => false,
    ],
    'table' => 'bbn_entities',
    'tables' => [
      'entities' => 'bbn_entities',
      'identities' => 'bbn_identities',
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
        'identity' => [
          'name' => 'identity',
          'type' => 'string',
          'maxlength' => 32
        ],
        'id_parent' => [
          'name' => 'id_parent',
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

  /**
   * Links cache.
   *
   * @var array
   */
  private static $links = [];

  /**
   * Class cache.
   *
   * @var array
   */
  private static $classes = [];

  /**
   * Link class instance.
   *
   * @var mixed
   */
  private $linkCls;

  /**
   * Entities constructor.
   *
   * @param Db $db The database instance.
   * @param array|null $cfg Configuration options.
   * @param Option|null $options Option object.
   * @param Mail|null $mail Mail object.
   * @param Identities|null $identities Identities object.
   * @param Address|null $address Address object.
   * @param Consultation|null $consultation Consultation object.
   * @param Document|null $document Document object.
   * @param DocumentRequest|null $request DocumentRequest object.
   * @param EntityOptions|null $entityOptions EntityOptions object.
   * @param Masks|null $masks Masks object.
   * @param Uauth|null $uauth Uauth object.
   */
  public function __construct(
    Db $db,
    array $cfg = null,
    protected Option|null $options = null,
    protected Mail|null $mail = null,
    private Identities|null $identities = null,
    private Address|null $address = null,
    private Consultation|null $consultation = null,
    private Document|null $document = null,
    private DocumentRequest|null $request = null,
    private EntityOptions|null $entityOptions = null,
    private Masks|null $masks = null,
    private Uauth|null $uauth = null,
  ) {
    parent::__construct($db);
    // Setting up the class configuration
    $this->initClassCfg($cfg);
    $cls = $this->class_cfg['classes'];
    if ($cls['link']) {
      $this->linkCls = $cls['mail'];
    }
  }

  /**
   * Magic method to handle dynamic method calls.
   * 
   * @param string $method Method name.
   * @param array $args Arguments for the method.
   * 
   * @return mixed
   * @throws Exception If the method does not exist.
   */
  public function __call($method, $args)
  {
    $path = '\\' . get_class($this) . '\\';
    $cls = ucfirst($method);
    $entity = $args[0] ?? null;

    if (class_exists($path . 'Tables\\' . $cls)) {
      return $this->getClass($path . 'Tables\\' . $cls, $method, $entity);
    } else if (class_exists($path . 'Junctions\\' . $cls)) {
      return $this->getClass($path . 'Junctions\\' . $cls, $method, $entity);
    } else if (class_exists($path . 'Links\\' . $cls)) {
      return $this->getLink($path . 'Links\\' . $cls, $entity);
    } else if (class_exists($path . 'Documents\\' . $cls)) {
      return $this->getClass($path . 'Documents\\' . $cls, $method, $entity);
    }

    throw new Exception(X::_("The method %s does not exist", $method));
  }

  /**
   * Deletes records based on the given condition.
   *
   * @param string|array $where Condition for deletion.
   * 
   * @return bool
   */
  public function delete(string|array $where)
  {
    return $this->dbTraitDelete($this->treatWhere($where));
  }

  /**
   * Updates records based on the given condition and data.
   *
   * @param string|array $where Condition for update.
   * @param array $data Data to update.
   * 
   * @return bool
   */
  public function update(string|array $where, array $data)
  {
    return $this->dbTraitUpdate($this->treatWhere($where), $data);
  }

  /**
   * Checks if a record exists based on the given condition.
   *
   * @param string|array $where Condition for existence check.
   * 
   * @return bool
   */
  public function exists(string|array $where)
  {
    return $this->dbTraitExists($this->treatWhere($where));
  }

  /**
   * Retrieves a single value based on the field and condition.
   *
   * @param string $field The field to select.
   * @param string|array $filter Condition for selection.
   * @param array $order Order for sorting results.
   * 
   * @return mixed
   */
  public function selectOne(string $field, $filter = [], array $order = [])
  {
    return $this->dbTraitSelectOne($field, $filter, $order);
  }

  /**
   * Selects a row as an object from the table through its condition.
   *
   * @param string|array $filter Condition for selection.
   * @param array $order Order for sorting results.
   * @param array $fields Fields to select.
   *
   * @return stdClass|null
   */
  public function select($filter = [], array $order = [], array $fields = []): ?stdClass
  {
    return $this->dbTraitSelect($filter, $order, $fields);
  }

  /**
   * Selects a row as an array from the table through its condition.
   *
   * @param string|array $filter Condition for selection.
   * @param array $order Order for sorting results.
   * @param array $fields Fields to select.
   *
   * @return array|null
   */
  public function rselect($filter = [], array $order = [], array $fields = []): ?array
  {
    return $this->dbTraitRselect($filter, $order, $fields);
  }

  /**
   * Selects multiple values based on a field and condition.
   *
   * @param string $field The field to select.
   * @param array $filter Condition for selection.
   * @param array $order Order for sorting results.
   * @param int $limit Maximum number of results.
   * @param int $start Starting point for results.
   * 
   * @return array
   */
  public function selectValues(string $field, array $filter = [], array $order = [], int $limit = 0, int $start = 0): array
  {
    return $this->dbTraitSelectValues($field, $filter, $order, $limit, $start);
  }

  /**
   * Returns the number of rows from the table for the given conditions.
   *
   * @param array $filter
   *
   * @return int
   */
  public function count(array $filter = []): int
  {
    return $this->dbTraitCount($filter);
  }


  /**
   * Returns an array of rows as objects from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param array $limit
   * @param array $start
   *
   * @return array
   */
  public function selectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitSelectAll($filter, $order, $limit, $start, $fields);
  }


  /**
   * Returns an array of rows as arrays from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param array $limit
   * @param array $start
   *
   * @return array
   */
  public function rselectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitRselectAll($filter, $order, $limit, $start, $fields);
  }

  public function getRelations(string $id, string $table = null): ?array
  {
    return $this->dbTraitGetRelations($id, $table);
  }

  public function getColTitle(string $col): ?string
  {
    $db = new Database($this->db);
    $cid = $db->columnId($col, $this->class_table);
    if (!$cid) {
      return null;
    }

    return $this->options()->text($cid);
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

  public function identities(): ?Identities
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->identities && $cls['identities']) {
      $this->identities = new $cls['identities']($this->db, $this);
    }

    return $this->identities;
  }


  public function uauth(): ?Uauth
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->uauth && $cls['uauth']) {
      $this->uauth = new $cls['uauth']($this->db);
    }

    return $this->uauth;
  }

  public function address(): ?Address
  {
    $cls = $this->class_cfg['classes'];
    if (!$this->address && $cls['address']) {
      $this->address = new $cls['address']($this->db, $this);
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


  public function request(Entity|null $entity = null): ?DocumentRequest
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


  public function getLink(string $linkCls, Entity|null $entity = null): ?Link
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


  public function getGlobalLink(Entity|null $entity = null): ?Link
  {
    if (!$entity) {
      if (!isset($this->links)) {
        $this->links = new Link($this->db, $this);
      }

      return $this->links;
    }

    return new Link($this->db, $this, $entity);
  }


  protected function getClass(string $clsName, string $index, Entity|null $entity = null): EntityJunction|EntityTable
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


  protected function treatWhere(string|array $where): string|array
  {
    $cfg = $this->getClassCfg();
    if (!empty($cfg['arch'][$this->class_table_index]['easy_id']) && Str::isNumber($where)) {
      $where = [$cfg['arch'][$this->class_table_index]['easy_id'] => $where];
    }

    return $where;
  }


  private static function setLink(string $id, Link $link): void
  {
    self::$links[$id] = $link;
  }


  private static function setClass(string $index, EntityJunction|EntityTable $cls): void
  {
    self::$classes[$index] = $cls;
  }

}