<?php

namespace bbn\Entities;

use bbn\Appui\Option;
use bbn\Db;
use bbn\Models\Tts\Dbconfig;
use bbn\Str;
use bbn\X;

abstract class AbstractLinkType
{
  use Dbconfig;

  /**
   * @var Db
   */
  protected $db;

  /**
   * @var EntityInterface
   */
  protected $entity;

  /**
   * @var Option
   */
  protected $options;

  /**
   * @var string|null
   */
  protected ?string $type = null;


  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_entities_links',
    'tables' => [
      'links' => 'bbn_entities_links',
      'people' => 'bbn_people',
      'addresses' => 'bbn_addresses',
      'options' => 'bbn_options',
      'entities' => 'bbn_entities'
    ],
    'arch' => [
      'links' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'link_type' => 'link_type',
        'id_people' => 'id_people',
        'id_address' => 'id_address',
        'id_option' => 'id_option',
        'cfg' => 'cfg'
      ],
      'entities' => [
        'id' => 'id',
        'name' => 'nom'
      ]
    ],
  ];

  /**
   * @param EntityInterface $entity
   * @param Db $db
   * @param array $cfg
   *
   * @throws \Exception
   */
  public function __construct(EntityInterface $entity, Db $db, array $cfg = [])
  {
    $this->db       = $db;
    $this->entity   = $entity;
    $this->options  = Option::getInstance();
    $this->_init_class_cfg($cfg);
    $this->setType();
  }

  /**
   * @return void
   * @throws \Exception
   */
  private function setType(): void
  {
    if (!$this->type) {
      $cls  = \get_class($this);
      $name = substr($cls, strrpos($cls, '\\') + 1);
      $name = strtolower($name);

      if (!$this->type = $this->options->fromCode($name, 'LIENS')) {
        throw new \Exception(X::_('Unable to find type for') . " $cls" );
      }
    }
  }


  /**
   * Returns an array of link objects or empty array if no result found.
   *
   * ```php
   * X::dump($link->getAll());
   *
   * // (array) [
   *  bbn\Entities\Link {
   *    stdClass Object 'link': {
   *     'id': '0ab0986031be11ecbb0c37346045384d',
   *     'link_type': '0a9ebf0a31be11ecbb0c37346045384d',
   *     'id_adherent': 99,
   *     'id_tiers': '0aa1830231be11ecbb0c37346045384d',
   *     'id_lieu': '0aa8204031be11ecbb0c37346045384d',
   *      'id_option': '0aa9758a31be11ecbb0c37346045384d',
   *     'cfg': null,
   *   }
   *  stdClass Object 'people': {
   *      'id': '0aa1830231be11ecbb0c37346045384d',
   *      'nom': 'a',
   *      'prenom': 'aa',
   *      'civilite': 'M',
   *      'fullname': 'M aa a',
   *      'email': null,
   *      'portable': null,
   *      'cfg': null
   *     },
   *  stdClass Object 'address': {
   *       'id': '0aa8204031be11ecbb0c37346045384d',
   *       'adresse': 'address A',
   *       'cp': 123,
   *       'ville': 'Martigues',
   *       'id_country': null,
   *       'tel': null,
   *       'fax': null,
   *       'email': null
   *      },
   *   stdClass Object 'option': {
   *        'id': '0aa9758a31be11ecbb0c37346045384d',
   *        'id_parent': '0aa8db0c31be11ecbb0c37346045384d',
   *        'id_alias': null,
   *        'num': null,
   *        'code': 'P',
   *        'text': 'foo',
   *        'value': null,
   *        'cfg': null
   *      }
   *    }
   * ]
   * ```
   *
   * @param bool $included_deleted
   * @return array
   */
  public function getAll(bool $included_deleted = false): array
  {
    $r = [];

    if ($links = $this->fetch()) {
      $id_tiers   = [];
      $id_lieu    = [];
      $id_options = [];

      $f = $this->fields;
      foreach ($links as $link) {
        $id_tiers[]   = $this->parseId($link[$f['id_people']]) ?? '';
        $id_lieu[]    = $this->parseId($link[$f['id_address']]) ?? '';
        $id_options[] = $this->parseId($link[$f['id_option']]) ?? '';
      }

      // Here will fetch ALL linked items for all results in just 3 queries
      // Then later will assign every link together
      // To avoid executing too many queries
      $tables = $this->class_cfg['tables'];

      $people    = $this->fetchLinks($tables['people'], $id_tiers);
      $addresses = $this->fetchLinks($tables['addresses'], $id_lieu);
      $options   = $this->fetchLinks($tables['options'], $id_options);

      foreach ($links as $link) {
        $r[] = new Link(
          $link,
          $this->getLinkedItem($people, $link[$f['id_people']]),
          $this->getLinkedItem($addresses, $link[$f['id_address']]),
          $this->getLinkedItem($options, $link[$f['id_option']])
        );
      }
    }

    return $r;
  }

  /**
   * Returns a Link object from the given id or null if not exists.
   *
   * @param $id
   *
   * @return Link|null
   */
  public function get($id): ?Link
  {
    if ($link = $this->fetch('one', [$this->fields['id'] => $id])) {
      $f      = $this->fields;
      $tables = $this->class_cfg['tables'];
      return new Link(
        $link,
        $this->fetchLinks($tables['people'], [$this->parseId($link[$f['id_people']]) ?? ''], 'one'),
        $this->fetchLinks($tables['addresses'], [$this->parseId($link[$f['id_address']]) ?? ''], 'one'),
        $this->fetchLinks($tables['options'], [$this->parseId($link['id_option']) ?? ''], 'one'),
      );
    }

    return null;
  }

  /**
   * @param $id_tiers
   *
   * @return string|null
   */
  public function _id_by_tiers($id_tiers): ?string
  {
    if ($id = $this->fetch('field', [
      $this->fields['id_people'] => $id_tiers
    ], $this->fields['id'])) {
      return $id;
    }

    return null;
  }

  /**
   * @param $id_lieu
   *
   * @return string|null
   */
  public function _id_by_lieu($id_lieu): ?string
  {
    if ($id = $this->fetch('field', [
      $this->fields['id_address'] => $id_lieu
    ], $this->fields['id'])) {
      return $id;
    }

    return null;
  }

  /**
   * @param bool $included_deleted
   * @return int|null
   */
  public function count(bool $included_deleted = false): ?int
  {
    return $this->db->count(
      $this->class_table, [
        $this->fields['id_entity'] => $this->entity->getId(),
        $this->fields['link_type'] => $this->type
      ]
    );
  }

  /**
   * @param array $cfg
   *
   * @return mixed|false
   */
  public function insert(array $cfg)
  {
     $f = $this->fields;
    if (($cfg = $this->input($cfg)) && (isset($cfg[$f['id_people']]) || isset($cfg[$f['id_address']]))) {
      $param = [];
      foreach ($cfg as $k => $v){
        if (!in_array($k, $this->fields)) {
          $param[$k] = $v;
          unset($cfg[$k]);
        }
      }

      if (!empty($param)) {
        $cfg[$f['cfg']] = json_encode($param);
      }

      $cfg[$f['id_entity']] = $this->entity->getId();
      $cfg[$f['link_type']]   = $this->type;

      if (\array_key_exists($f['id'], $cfg)) {
        unset($cfg[$f['id']]);
      }

      if ($this->db->insert($this->class_table, $cfg)) {
        return $this->output($this->db->lastId());
      }

      $this->error("Insert", false);
      return false;
    }

    $this->error("Insert");
    return false;
  }


  /**
   * @param array $cfg
   *
   * @return mixed|false
   */
  public function update(array $cfg)
  {
    $f = $this->fields;
    if (($cfg = $this->input($cfg))
        && isset($cfg[$f['id']])
        && (isset($cfg[$f['id_people']]) || isset($cfg[$f['id_address']]))
    ) {
      $param = [];
      foreach ($cfg as $k => $v){
        if (!in_array($k, $this->fields)) {
          $param[$k] = $v;
          unset($cfg[$k]);
        }
      }

      $cfg[$f['cfg']] = json_encode($param);

      $id = $cfg[$f['id']];
      unset($cfg[$f['id']]);

      if ($this->db->update($this->class_table, $cfg, [
        $f['id'] => $id, $f['id_entity'] => $this->entity->getId()]
      )) {
        return $this->output($id);
      }

      $this->error("Update", false);
      return false;
    }

    $this->error("Update");
    return false;
  }

  /**
   * @param $id
   *
   * @return array|null
   */
  public function get_st($id): ?array
  {
    if ($id instanceof Link) {
      $r    = $id;
      $link = (array)$id->link;
    }
    elseif ($r = $this->get($id)) {
      $link = (array)$r->link;
    }
    else {
      return null;
    }

    foreach ($link as $k => $v) {
      switch ($k) {
        case $this->fields['id_people']:
          $link[$k] = $this->entity->fnom((array)$r->people);
          break;
        case $this->fields['id_address']:
          $link[$k] = $this->entity->fadresse((array)$r->address);
          break;
        case $this->fields['id_entity']:
          $cfg = $this->class_cfg;
          $f   = $cfg['arch']['entities'];
          $link[$k] = $this->db->selectOne($cfg['tables']['entities'], $f['name'], [
            $f['id'] => $v
          ]);
          break;
      }
    }

    return $link;
  }

  /**
   * @param bool $included_deleted
   * @return array
   */
  public function get_all_st(bool $included_deleted = false): array
  {
    $r = [];
    if ($liens = $this->getAll($included_deleted)) {
      foreach ($liens as $lien){
        array_push($r, $this->get_st($lien));
      }
    }

    return $r;
  }

  /**
   * @param bool $included_deleted
   * @return array|null
   */
  public function get_ids(bool $included_deleted = false): ?array
  {
    return $this->db->getColumnValues(
      $this->class_table, $this->fields['id'], [
        $this->fields['id_entity'] => $this->entity->getId(),
        $this->fields['link_type'] => $this->type
      ]
    );
  }

  /**
   * @param $id
   *
   * @return mixed|false
   */
  public function delete($id)
  {
    $param = [
      $this->fields['id_entity'] => $this->entity->getId(),
      $this->fields['id'] => $id,
      $this->fields['link_type'] => $this->type
    ];

    if ($this->db->delete($this->class_table, $param)) {
      return $id;
    }

    $this->error("Delete");
    return false;
  }

  /**
   * @param $message
   * @param $die
   */
  public function error($message, $die = true)
  {
    $this->entity->error("Error while executing $message with ".get_class($this), $die);
  }


  /**
   * @param array $items
   * @param       $id
   *
   * @return array|null
   */
  private function getLinkedItem(array $items, $id): ?array
  {
    foreach ($items as $item) {
      if ($item[$this->fields['id']] === $id) {
        return $item;
      }
    }

    return null;
  }

  /**
   * @param string $table
   * @param array  $values
   * @param string $type
   *
   * @return array
   */
  private function fetchLinks(string $table, array $values, string $type = 'all'): array
  {
    $method = 'rselectAll';

    if ($type === 'one') {
      $method = 'rselect';
    }

    if ($result = $this->db->$method($table, [], [$this->fields['id'] => $values])) {
      return $result;
    }

    return [];
  }

  /**
   * @param string $type
   * @param array  $where
   * @param array  $fields
   *
   * @return mixed
   */
  private function fetch(string $type = 'all', array $where = [], $fields = [])
  {
    $method = 'rselectAll';

    if ($type === 'one') {
      $method = 'rselect';
    }
    elseif ($type === 'field') {
      $method = 'selectOne';
    }

    return $this->db->$method($this->class_table, $fields, array_merge([
      $this->fields['id_entity'] => $this->entity->getId(),
      $this->fields['link_type'] => $this->type
    ], $where));
  }

  /**
   * @param $id
   *
   * @return mixed
   */
  private function parseId($id)
  {
    return Str::isUid($id) ? hex2bin($id) : $id;
  }

  /**
   * @param array $cfg
   *
   * @return array|false
   */
  protected function input(array $cfg)
  {
    return $cfg;
  }

  /**
   * @param $cfg
   *
   * @return mixed
   */
  protected function output($cfg)
  {
    return $cfg;
  }
}