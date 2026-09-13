<?php

namespace bbn\Entities\Tables;

use Exception;
use bbn\Db;
use bbn\X;
use bbn\Entities\Models\Entities;
use bbn\Entities\Models\EntityTable;
use bbn\Entities\Entity;
use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\Tagger;

class Link extends EntityTable
{
  use Tagger;

  private string $type;
  private array $cfg;
  protected static $default_class_cfg = [
    "table" => "bbn_entities_links",
    "tables" => [
      "links" => "bbn_entities_links",
      "tags" => "bbn_entities_links_tags",
    ],
    "arch" => [
      "links" => [
        "id" => "id",
        "link_type" => "link_type",
        "id_entity" => "id_entity",
        "id_identity" => "id_identity",
        "id_address" => "id_address",
        "id_option" => "id_option",
        "cfg" => "cfg",
      ],
      "tags" => [
        "id" => "id",
        "id_tag" => "id_tag",
        "id_link" => "id_link",
      ],
    ],
    "relations" => [
      "identity" => "id_identity",
      "address" => "id_address",
      "option" => "id_option",
    ],
  ];

  protected static array $linkCfg = [
    "single" => false,
    "tags" => false,
    "required" => false,
    "identity" => false,
    "address" => false,
    "cfg" => false,
  ];

  private $id_parent = null;
  private $code;
  private $text;

  protected $where = [];

  protected $hasTags = false;

  protected static array $codes = [];

  /**
   * @param Db $db
   * @param Entities $entities
   * @param Entity|Nullall $entity
   */
  public function __construct(
    Db $db,
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall(),
  ) {
    parent::__construct($db, $entities, $entity);
    $o = $this->options();
    $codes = self::getCodes($this);
    $this->cfg = &static::$linkCfg ?: self::$linkCfg;
    if (!empty($this->cfg["cfg"])) {
      $this->class_cfg["cfg"] = $this->cfg["cfg"];
    }

    if (!empty($this->cfg["tags"])) {
      $this->hasTags = true;
      $taggerFields = [
        "id_tag" => $this->class_cfg["arch"]["tags"]["id_tag"],
        "id_element" => $this->class_cfg["arch"]["tags"]["id_link"],
      ];
      $hasEntity = !empty($this->entity) && is_a($this->entity, Entity::class);
      if ($hasEntity) {
        $taggerFields["id_entity"] = $this->class_cfg["arch"]["tags"]["id_entity"];
      }

      $this->taggerInit(
        $this->class_cfg["tables"]["tags"],
        $taggerFields,
        $hasEntity ? $this->entity->getId() : null
      );
      if (is_string($this->cfg["tags"])) {
        $this->taggerType = $this->taggerObject->retrieveType(
          $this->cfg["tags"],
        );
      }
    }

    if (!empty($codes) && ($this->type = $o->fromCode($codes))) {
      $option = $o->option($this->type);
      $this->code = $option["code"];
      $this->text = $option["text"];
      $this->rootFilterCfg = [
        $this->fields["link_type"] => $this->type,
      ];

      if (!empty($this->cfg["option"])) {
        if (!empty($this->cfg["option"]["id_parent"])) {
          $this->id_parent = $this->options()->fromCode(
            ...$this->cfg["option"]["id_parent"],
          );
          if (!$this->id_parent) {
            throw new Exception(
              X::_("The parent for %s is not defined", $this->text),
            );
          }
        }
      }
    }
  }

  public function check(): bool
  {
    return (bool) $this->type;
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
    return $this->dbTraitSelectValues($this->fields["id"], $filter);
  }

  public function getFullList(array $filter = [])
  {
    if (is_a($this->entity(), 'bbn\Models\Cls\Nullall')) {
      $res = $this->dbTraitRselectAll($filter);
    }
    else {
      $res = $this->normalize($this->getRecords());
      if ($res && $filter) {
        $res = X::filter($res, $filter);
      }
    }

    return $res;
  }

  public function get($id = null): ?array
  {
    if ($id) {
      $res = X::getRow($this->getRecords(), [$this->fields["id"] => $id]);
    } elseif (!empty($this->cfg["single"])) {
      $res = X::getRow($this->getRecords(), [$this->fields["link_type"] => $this->type]);
    } else {
      throw new Exception(
        X::_("This link is not single, you must enter an ID for get"),
      );
    }

    if ($res) {
      return $this->normalize([$res])[0];
    }

    return null;
  }

  public function getAll(
    array $filter = [],
    string|array $order= [],
    int $limit = 0,
    int $start = 0,
    $fields = [],
  ): array {
    $res = parent::getAll($filter, $order, $limit, $start, $fields);
    return $this->normalize($res);
  }

  public function getByIdentity(string $id): ?array
  {
    $res = X::getRow($this->getRecords(), [
      $this->fields["link_type"] => $this->type,
      $this->fields["id_identity"] => $id,
    ]);
    if ($res) {
      $res = $this->normalize([$res])[0];
    }

    return $res;
  }

  public function getByAddress(string $id): ?array
  {
    $res = X::getRow($this->getRecords(), [
      $this->fields["link_type"] => $this->type,
      $this->fields["id_address"] => $id,
    ]);
    if ($res) {
      $res = $this->normalize([$res])[0];
    }

    return $res;
  }

  public function insert(array $data): ?string
  {
    $ccfg = $this->getClassCfg();
    $rel = $ccfg["relations"];
    foreach ($this->cfg as $k => $v) {
      if (!empty($v["required"]) && !isset($data[$this->fields[$rel[$k]]])) {
        throw new Exception(
          X::_("The field %s is required", $this->fields[$rel[$k]]),
        );
      }
    }

    $data[$this->fields["link_type"]] = $this->type;
    if ($this->entity) {
      $data[$this->fields["id_entity"]] = $this->entity->getId();
    }

    if (empty($data[$this->fields["id_entity"]])) {
      throw new Exception(X::_("The entity is not defined"));
    }

    if (!empty($this->cfg["single"]) && ($ex = $this->get())) {
      if (
        (!empty($this->cfg["identity"]) &&
          $data[$this->fields[$rel["identity"]]] !==
            $ex[$this->fields[$rel["identity"]]]) ||
        (!empty($this->cfg["address"]) &&
          $data[$this->fields[$rel["address"]]] !==
            $ex[$this->fields[$rel["address"]]]) ||
        (!empty($this->cfg["option"]) &&
          $data[$this->fields[$rel["option"]]] !==
            $ex[$this->fields[$rel["option"]]])
      ) {
        if ($this->dbTraitDelete($ex[$this->fields["id"]])) {
          return $this->dbTraitInsert($data);
        }

        return null;
      }

      return $this->dbTraitUpdate($ex[$this->fields["id"]], $data)
        ? $ex[$this->fields["id"]]
        : null;
    }

    return $this->dbTraitInsert($data);
  }

  public static function getCodes(Link $link): array
  {
    return $link::$codes;
  }

  protected function normalize(array $data): array
  {
    if ($this->hasTags
      || (!empty($this->class_cfg["cfg"])
        && !empty($this->fields["cfg"])
      )
    ) {
      foreach ($data as &$d) {
        if ($this->hasTags) {
          $d["tags"] = $this->getTags($d[$this->fields["id"]]);
        }

        if (!empty($this->class_cfg["cfg"])
          && !empty($this->fields["cfg"])
        ) {
          foreach ($this->class_cfg["cfg"] as $v) {
            if (isset($v["field"])
              && !array_key_exists($v["field"], $d)
            ) {
              $d[$v["field"]] = !empty($d[$this->fields["cfg"]])
                ? ($d[$this->fields["cfg"]][$v["field"]] ?? null)
                : null;
            }
          }

          if (array_key_exists($this->fields["cfg"], $d)) {
            unset($d[$this->fields["cfg"]]);
          }
        }
      }

      unset($d);
    }

    return $data;
  }
}
