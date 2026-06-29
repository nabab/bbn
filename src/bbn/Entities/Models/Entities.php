<?php

namespace bbn\Entities\Models;

use Exception;
use BadMethodCallException;
use stdClass;
use bbn\Db;
use bbn\Str;
use bbn\X;
use bbn\Models\Tts\DbPublicCache;
use bbn\Models\Tts\DbPublicOps;
use bbn\Entities\Entity;
use bbn\Entities\Tables\Link;
use bbn\Entities\Identity;
use bbn\Entities\Address;
use bbn\Entities\Models\EntityJunction;
use bbn\Entities\Models\EntityTable;
use bbn\Entities\Models\Internals\EntitiesObjects;
use bbn\Entities\Junctions\Consultation;
use bbn\Entities\Tables\Document;
use bbn\Entities\Tables\Options as EntityOptions;
use bbn\Entities\Tables\DocumentRequest;
use bbn\Mail;
use bbn\Appui\Database;
use bbn\Appui\Masks;
use bbn\Appui\Option;
use bbn\Appui\Uauth;
use bbn\Appui\History;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Cls\Nullall;

use function is_int;

/**
 * Class Entities
 * Abstract base class for entity-related operations.
 * Provides methods for interacting with database entities, including CRUD operations.
 */
abstract class Entities extends DbCls
{
  use DbPublicCache;
  use DbPublicOps;

  /**
   * Default class configuration.
   *
   * @var array
   */
  protected static $default_class_cfg = [
    "classes" => [
      "link" => false,
      "identities" => false,
      "address" => false,
      "entity" => false,
      "consultation" => false,
      "mail" => false,
      "document" => false,
      "document_request" => false,
      "note" => false,
      "entity_options" => false,
      "masks" => false,
      "uauth" => false,
    ],
    "table" => "bbn_entities",
    "tables" => [
      "entities" => "bbn_entities",
      "identities" => "bbn_identities",
      "address" => "bbn_addresses",
      "links" => "bbn_entities_links",
    ],
    "arch" => [
      "entities" => [
        "id" => [
          "name" => "id",
          "type" => "primary",
        ],
        "easy_id" => [
          "name" => "easy_id",
          "type" => "primary",
          "maxlength" => 5,
        ],
        "identity" => [
          "name" => "identity",
          "type" => "string",
          "maxlength" => 32,
        ],
        "id_parent" => [
          "name" => "id_parent",
          "type" => "string",
          "maxlength" => 100,
          "alias" => "parent",
        ],
        "cached" => [
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
      ],
    ],
  ];

  protected $defaultCountry;

  /**
   * Links cache.
   *
   * @var array
   */
  private static $linksCache = [];

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

  private $links;

  private static $entityKeys;
  /**
   * Entities constructor.
   *
   * @param Db $db The database instance.
   * @param Option|null $options Option object.
   * @param Mail|null $mail Mail object.
   * @param Identity|null $identity Identity object.
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
    protected Option|null $options = null,
    protected Mail|null $mail = null,
    private Identity|null $identity = null,
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
    $this->initClassCfg();
    $this->dbTraitCacheInit();
    $cls = $this->class_cfg["classes"];
    if (!empty($cls["link"])) {
      $this->linkCls = $cls["link"];
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
    $path = static::class . "\\";
    $cls = ucfirst($method);
    $entity = $args[0] ?? null;

    if (class_exists($path . "Tables\\" . $cls)) {
      return $this->getClass($path . "Tables\\" . $cls, $method, $entity);
    } elseif (class_exists($path . "Junctions\\" . $cls)) {
      return $this->getClass($path . "Junctions\\" . $cls, $method, $entity);
    } elseif (class_exists($path . $cls)) {
      return $this->getClass($path . $cls, $method, $entity);
    } elseif (class_exists($path . "Links\\" . $cls)) {
      return $this->getLink($path . "Links\\" . $cls, $entity);
    } elseif (class_exists($path . "Documents\\" . $cls)) {
      return $this->getClass($path . "Documents\\" . $cls, $method, $entity);
    }

    throw new BadMethodCallException(X::_("The method %s does not exist", $method));
  }

  public function getDefaultCountry(): ?string
  {
    return $this->defaultCountry;
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
   * Updates records based on the given condition and data.
   *
   * @param string|array $where Condition for update.
   * @param array $data Data to update.
   *
   * @return bool
   */
  public function getNewEasyId(): ?int
  {
    $arc = $this->class_cfg["props"]["entities"];
    if (isset($arc["easy_id"])) {
      $num = random_int(1, pow(10, $arc["easy_id"]["max_length"] ?? 5) - 1);
      $max = 100;
      $i = 0;
      while (
        $this->dbTraitSelectOne($arc["easy_id"]["name"], [
          $arc["easy_id"]["name"] => $num,
        ]) &&
        $i < $max
      ) {
        $num = random_int(1, pow(10, $arc["easy_id"]["max_length"] ?? 5) - 1);
        $i++;
      }

      return $num;
    }

    return null;
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
   * @param string|array $orderOrder for sorting results.
   *
   * @return mixed
   */
  public function selectOne(string $field, $filter = [], string|array $order= [])
  {
    return $this->dbTraitSelectOne($field, $filter, $order);
  }

  /**
   * Selects a row as an object from the table through its condition.
   *
   * @param string|array $filter Condition for selection.
   * @param string|array $orderOrder for sorting results.
   * @param array $fields Fields to select.
   *
   * @return stdClass|null
   */
  public function select(
    $filter = [],
    string|array $order= [],
    array $fields = [],
  ): ?stdClass {
    return $this->dbTraitSelect($filter, $order, $fields);
  }

  /**
   * Selects a row as an array from the table through its condition.
   *
   * @param string|array $filter Condition for selection.
   * @param string|array $orderOrder for sorting results.
   * @param array $fields Fields to select.
   *
   * @return array|null
   */
  public function rselect(
    $filter = [],
    string|array $order= [],
    array $fields = [],
  ): ?array {
    return $this->dbTraitRselect($filter, $order, $fields);
  }

  /**
   * Selects multiple values based on a field and condition.
   *
   * @param string $field The field to select.
   * @param array $filter Condition for selection.
   * @param string|array $orderOrder for sorting results.
   * @param int $limit Maximum number of results.
   * @param int $start Starting point for results.
   *
   * @return array
   */
  public function selectValues(
    string $field,
    array $filter = [],
    string|array $order= [],
    int $limit = 0,
    int $start = 0,
  ): array {
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
   * @param int $limit
   * @param int $start
   * @param array $fields
   *
   * @return array
   */
  public function selectAll(
    array $filter = [],
    string|array $order= [],
    int $limit = 0,
    int $start = 0,
    array $fields = [],
  ): array {
    return $this->dbTraitSelectAll($filter, $order, $limit, $start, $fields);
  }

  /**
   * Returns an array of rows as arrays from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param int $limit
   * @param int $start
   * @param array $fields
   * @return array
   */
  public function rselectAll(
    array $filter = [],
    string|array $order= [],
    int $limit = 0,
    int $start = 0,
    array $fields = [],
  ): array {
    return $this->dbTraitRselectAll($filter, $order, $limit, $start, $fields);
  }

  public function getRelations(string $id, string|null $table = null): ?array
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

  public function getFilterCfg(array $cfg): array
  {
    return $this->dbTraitGetFilterCfg($cfg);
  }

  public function get($id): Entity
  {
    $cls = $this->class_cfg["classes"];
    return new ($cls["entity"])($this->db, $id, $this);
  }

  public function masks(): ?Masks
  {
    $cls = $this->class_cfg["classes"];
    if (!$this->masks) {
      $this->masks = new Masks($this->db);
    }

    return $this->masks;
  }

  public function identity(): ?Identity
  {
    $cls = $this->class_cfg["classes"];
    if (!$this->identity && $cls["identities"]) {
      $this->identity = new ($cls["identities"])($this->db, $this);
    }

    return $this->identity;
  }

  public function uauth(): ?Uauth
  {
    $cls = $this->class_cfg["classes"];
    if (!$this->uauth && $cls["uauth"]) {
      $this->uauth = new ($cls["uauth"])($this->db);
    }

    return $this->uauth;
  }

  public function address(): ?Address
  {
    $cls = $this->class_cfg["classes"];
    if (!$this->address && $cls["address"]) {
      $this->address = new ($cls["address"])($this->db, $this);
    }

    return $this->address;
  }

  public function options(): ?Option
  {
    $cls = $this->class_cfg["classes"];
    if (!$this->options && $cls["option"]) {
      $this->options = new ($cls["option"])($this->db);
    }

    return $this->options;
  }


  public function getLink(string $linkCls, Entity|null $entity = null): ?Link
  {
    $id = $this->options()->fromCode($linkCls::$codes);
    if (!$entity) {
      if (!isset(self::$linksCache[$id])) {
        $link = new $linkCls($this->db, $this);
        self::setLink($id, $link);
      }

      return self::$linksCache[$id];
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


  public static function getEntityKeys(Db $db, Entities $ent): array
  {
    if (!self::$entityKeys) {
      $keys = [];
      $cfg = $ent->getClassCfg();
      foreach ($db->getForeignKeys($cfg['arch']['entities']['id'], $cfg['tables']['entities']) as $tfn => $col) {
        $keys[$db->tsn($tfn)] = $col;
      }
  
      self::$entityKeys = $keys;
    }

    return self::$entityKeys;
  }

  public static function getDbObject(string $table, array $cfg, Db $db, Entities $entities, ?Entity $entity = null)
  {
    $keys = Entities::getEntityKeys($db, $entities);
    if (empty($cfg['class'])) {
      return null;
    }

    try {
      if (isset($keys[$table])) {
        $cls = new $cfg['class']($db, $entities, $entity ?: new Nullall());
      }
      else {
        $cls = new $cfg['class']($db);
      }
    }
    catch (Exception $e) {
      throw new Exception(X::_("The class %s for table %s cannot be instantiated", $cfg['class'], $cfg['table']));
    }

    return $cls;
  }



  protected function factorEntityObject(string $method, string $clsName, ?Entity $entity = null): object
  {
    if (!$entity) {
      if (!isset(self::$classes[$method])) {
        try {
          $cls = new $clsName($this->db, $this);
          self::setClass($method, $cls);
        }
        catch (Exception $e) {
          throw new Exception(X::_("The method %s cannot create the instance of %s", $method, $clsName));
        }
      }
      else {
        $cls = self::$classes[$method];
      }
    }
    else {
      try {
        $cls = new $clsName($this->db, $this, $entity);
      }
      catch (Exception $e) {
        throw new Exception(X::_("The method %s cannot create the instance of %s", $method, $clsName));
      }
    }

    return $cls;
  }


  protected function getClass(
    string $clsName,
    string $index,
    Entity|null $entity = null,
  ): EntityJunction|EntityTable|DbCls {
    if (!$entity) {
      if (!isset(self::$classes[$index])) {
        try {
          $cls = new $clsName($this->db, $this);
          self::setClass($index, $cls);
        }
        catch (Exception $e) {
          throw new Exception(X::_("The class %s for table %s cannot be instantiated", $clsName, $this->class_cfg["table"]));
        }
      }
      else {
        try {
          $cls = self::$classes[$index];
        }
        catch (Exception $e) {
          throw new Exception(X::_("The class %s for table %s cannot be instantiated", $clsName, $this->class_cfg["table"]));
        }
      }

    }
    else {
      try {
        $cls = new $clsName($this->db, $this, $entity);
      }
      catch (Exception $e) {
        throw new Exception(X::_("The class %s for table %s cannot be instantiated", $clsName, $this->class_cfg["table"]));
      }
    }

    return $cls;
  }

  protected function treatWhere(string|array $where): string|array
  {
    $cfg = $this->getClassCfg();
    if (is_int($where)) {
      $where = [$cfg["arch"][$this->class_table_index]["easy_id"] => $where];
    }

    return $where;
  }

  private static function setLink(string $id, Link $link): void
  {
    self::$linksCache[$id] = $link;
  }

  protected static function setClass(
    string $index,
    //EntityJunction|EntityTable $cls,
    $cls,
  ): void {
    self::$classes[$index] = $cls;
  }
}
