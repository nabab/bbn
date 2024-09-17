<?php
namespace bbn\Entities;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Entities\Models\Entities;
use bbn\Entities\Tables\Link;
use bbn\Entities\Tables\Options as EntityOptions;
use bbn\Appui\Option;
use bbn\Models\Tts\Cache;


class Entity
{
  use Cache;

  protected array $class_cfg;

  protected array $fields;
  protected string $table;

  protected array $props;

  protected array $where;

  /** @var null|bool Adherent verification status. */
  private $checked = null;

  protected array $info = [];

  protected string $id;

  protected ?Link $links;

  protected $easyId = null;

  /**
   * Constructor.
   *
   * @param Db    $db
   * @param array $cfg
   * @param array $params
   */
  public function __construct(protected Db $db, string $id, protected Entities $entities)
  {
    $this->class_cfg = $this->entities->getClassCfg();
    $this->table = $this->class_cfg['table'];
    $this->fields = $this->class_cfg['arch']['entities'];
    $this->props = $this->class_cfg['props']['entities'];
    if ($this->fields['easy_id']) {
      if (Str::isInteger($id)) {
        $this->easyId = $id;
        $this->id = $this->entities->selectOne($this->fields['id'], [$this->fields['easy_id'] => $id]);
      }
      elseif ($this->entities->exists($id)) {
        $this->easyId = $this->entities->selectOne($this->fields['easy_id'], [$this->fields['id'] => $id]);
        $this->id = $id;
      }
    }
    elseif ($this->entities->exists($id)) {
      $this->id = $id;
    }
    else {
      throw new Exception(X::_("The entity does not exist"));
    }


    $this->where = [
      $this->fields['id'] => $this->id
    ];

    $this->cacheInit();
	}

  public function check(): bool
  {
    if ($this->checked === null) {
      $this->checked = (bool)$this->db->count($this->class_cfg['tables']['entities'], $this->where);
    }

    return $this->checked;
  }


  public function getId(): string
  {
    return $this->id;
  }


  public function getField(string $field, bool $force = false): ?string
  {
    if (!in_array($field, $this->fields)) {
      throw new Exception(X::_("The field %s does not exist", $field));
    }

    if ($force || !array_key_exists($field, $this->info)) {
      $this->info[$field] = $this->db->selectOne(
        $this->class_cfg['table'],
        $field,
        $this->where
      );
    }

    return $this->info[$field];
  }


  public function getFields(array $fields, bool $force = false): array
  {
    $res = [];
    foreach ($fields as $f) {
      $res[$f] = $this->getField($f, $force);
    }

    return $res;
  }


  public function getFromTable()
  {
    $fields = $this->fields;
    $table = $this->class_cfg['tables']['entities'];
    $cfg = [
      'tables' => [$table],
      'fields' => array_values($this->getFieldsList()),
      'where' => $this->where
    ];

    $data = $this->db->rselect($cfg);
    return $data;
  }

  public function getBasicInfo(): array
  {
    $arc = $this->class_cfg['arch']['entities'];
    $fields = [$arc['id'], $arc['name']];
    if (!empty($arc['easy_id'])) {
      $fields[] = $arc['easy_id'];
    }

    return $this->db->rselect(
      $this->class_cfg['table'],
      $fields,
      $this->where
    );
  }


  public function getMinimalInfo(?array $fields = null): array
  {
    $arc = $this->class_cfg['arch']['entities'];
    if (!$fields) {
      $fields = [$arc['id'], $arc['name']];
      if (!empty($arc['easy_id'])) {
        $fields[] = $arc['easy_id'];
      }
    }

    return $this->db->rselect(
      $this->class_cfg['table'],
      $fields,
      $this->where
    );
  }


  protected function getFieldsList(): array
  {
    $fields = $this->fields;
    $props = $this->props;
    $db = $this->db;
    $table = $this->class_cfg['tables']['entities'];
    return array_map(function ($a) use (&$db, $table) {
      return $db->cfn($a, $table);
    }, array_filter($fields, function($k) use (&$props) {
      return !isset($props[$k]) || !array_key_exists('showable', $props[$k]) || $props[$k]['showable'];
    }, ARRAY_FILTER_USE_KEY));

  }


  protected function getLink(string $linkCls): ?Link
  {
    return $this->entities->getLink($linkCls, $this);
  }

  public function people(): ?People
  {
    return $this->entities->people();
  }

  public function address(): ?Address
  {
    return $this->entities->address();
  }

  public function links(): Link
  {
    if (!isset($this->links)) {
      $this->links = $this->entities->getGlobalLink($this);
    }

    return $this->links;
  }
  
  public function options(): ?Option
  {
    return $this->entities->options();
  }
  
  public function entityOptions(): ?EntityOptions
  {
    return $this->entities->entityOptions($this);
  }

  public function getParent(): ?Entity
  {
    if ($this->check()
        && !empty($this->fields['id_parent'])
        && ($id_parent = $this->getField($this->fields['id_parent']))
    ) {
      return $this->entities->get($id_parent);
    }

    return null;
  }

  public function getParentInfo(): ?array
  {
    if ($parent = $this->getParent()) {
      return $parent->getMinimalInfo();
    }

    return null;
  }

  public function getSisters(): array
  {
    $res = [];
    if ($this->check()
        && !empty($this->fields['id_parent'])
        && ($id_parent = $this->getField($this->fields['id_parent']))
    ) {
      $tmp = $this->db->getColumnValues(
        $this->table,
        $this->fields['id'],
        [$this->fields['id_parent'] => $id_parent]
      );
      foreach ($tmp as $sis) {
        if ($sis !== $this->id) {
          $ent = $this->entities->get($sis);
          $res[] = $ent->getMinimalInfo();
        }
      }

    }
    
    return $res;
  }

  public function countChildren(): int
  {
    return $this->entities->count([
      $this->fields['id_parent'] => $this->id
    ]);
  }


  public function getChildren(): array
  {
    $tmp = $this->db->getColumnValues(
      $this->table,
      $this->fields['id'],
      [$this->fields['id_parent'] => $this->id]
    );
    $res = [];
    foreach ($tmp as $e) {
      if ($e !== $this->id) {
        $ent = $this->entities->get($e);
        $res[] = $ent->getMinimalInfo();
      }
    }

    return $res;
  }

}
