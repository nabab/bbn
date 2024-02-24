<?php
namespace bbn\Entities;

use bbn\X;
use bbn\Db;
use bbn\Entities;
use bbn\Appui\Option;
use bbn\Models\Tts\Cache;


class Entity
{
  use Cache;

  protected array $class_cfg;

  protected $fields;

  protected $props;

  protected $where;

  /** @var null|bool Adherent verification status. */
  private $checked = null;

  protected $info = ['consulte' => false];

  /**
   * Constructor.
   *
   * @param Db    $db
   * @param array $cfg
   * @param array $params
   */
  public function __construct(protected Db $db, protected string $id, protected Entities $entities)
  {
    $this->class_cfg = $this->entities->getClassCfg();
    $this->fields = $this->class_cfg['arch']['entities'];
    $this->props = $this->class_cfg['props']['entities'];
    $this->where = [
      $this->fields['easy_id'] ? $this->fields['easy_id'] : $this->fields['id'] => $id
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


  public function getFromTable()
  {
    $fields = $this->fields;
    $table = $this->class_cfg['tables']['entities'];
    $cfg = [
      'tables' => [$table],
      'fields' => array_values($this->getFieldsList()),
      'where' => $this->where
    ];

    if (isset($fields['id_parent'], $this->class_cfg['entities']['props']['id_parent'])) {
      $cfg['fields'][$this->class_cfg['entities']['props']['id_parent']['alias'] ?? 'parent'] = 'bbn_parents.' . $fields['name'];
      $cfg['join'] = [[
        'table' => $this->class_cfg['tables']['entities'],
        'alias' => 'bbn_parents_entities',
        'on' => [
          'conditions' => [[
            'field' => 'bbn_parents_entities.' . $fields['id'],
            'exp' => $table . '.' . $fields['id_parent']
          ]]
        ]
      ]];
    }

    $data = $this->db->rselect($cfg);
    return $data;
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


  protected function getLink(string $id): ?Link
  {
    return $this->entities->getLink(Link::class, $id);
  }

  protected function people(): ?People
  {
    return $this->entities->people();
  }

  protected function address(): ?Address
  {
    return $this->entities->address();
  }
  
  public function options(): ?Option
  {
    return $this->entities->options();
  }

}
