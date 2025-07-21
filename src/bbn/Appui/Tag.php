<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 29/11/2014
 * Time: 02:45
 */

namespace bbn\Appui;

use bbn\Db;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\Cache;
use bbn\Models\Cls\Db as DbCls;

class Tag extends DbCls
{
  use DbActions;
  use Cache;
  

  /**
   * @var array Db configuration
   */
  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_tags',
    'tables' => [
      'tags' => 'bbn_tags'
    ],
    'arch' => [
      'tags' => [
        'id' => 'id',
        'tag' => 'tag',
        'id_type' => 'id_type',
        'lang' => 'lang',
        'url' => 'url',
        'color' => 'color',
        'description' => 'description'
      ]
    ]
  ];

  protected $all;

  protected $lang;

  protected $options;

  public function __construct(Db $db, string|null $lang = null)
  {
    $this->lang = $lang ?: 'en';
    parent::__construct($db);
    $this->initClassCfg();
    $this->cacheInit();
    $this->options = Option::getInstance();
  }


  public function getAll(?string $lang = null, bool $force = false)
  {
    if (!$this->all || $force) {
      $table = $this->class_cfg['table'];
      $cf = $this->class_cfg['arch']['tags'];

      $filter = [$cf['lang'] => $lang ?: $this->lang];

      $this->all = $this->db->getColumnValues(
        $table,
        $cf['tag'],
        $filter,
        ['tag' => 'ASC']
      );
    }

    return $this->all;
  }


  public function getByType(string $id_type)
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    return $this->db->getColumnValues(
      $table,
      $cf['tag'],
      [$cf['id_type'] => $id_type],
      ['tag' => 'ASC']
    );
  }

  public function retrieveType($code) : ?string
  {
    return $this->options->fromCode($code, 'cats', 'tag', 'appui');
  }


  public function get(string $tag, ?string $id_type = null, ?string $lang = null): ?array
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    $filter = [$cf['lang'] => $lang ?: $this->lang, $cf['id_type'] => $id_type, $cf['tag'] => $tag];
    return $this->db->rselect($table, [], $filter);
  }


  public function getById(string $id, bool $full = false)
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    if ($full) {
      return $this->db->rselect($table, [], [$cf['id'] => $id]);
    }

    return $this->db->selectOne($table, 'tag', [$cf['id'] => $id]);
  }


  /**
   * Adds a new tag.
   *
   * @param string $tag
   * @param string|null $id_parent
   * @return string
   */
  public function add(string $tag, ?string $id_type = null, ?string $lang = null, string $description = ''): ?string
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    if ($this->db->insertIgnore(
      $table,
      [
        $cf['tag'] => $tag,
        $cf['lang'] => $lang ?: $this->lang,
        $cf['id_type'] => $id_type,
        $cf['description'] => $description
      ]
    )) {
      return $this->db->lastId();
    }

    return null;
  }


  public function remove(string $id): int
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    return $this->db->delete($table, [$cf['id'] => $id]);
  }


  public function setLang($id, $lang): int
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    return $this->db->update($table, [$cf['lang'] => $lang], [$cf['id'] => $id]);
  }


  public function setTag($id, $tag): int
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    return $this->db->update($table, [$cf['tag'] => $tag], [$cf['id'] => $id]);
  }


  public function setDescription($id, $description): int
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    return $this->db->update($table, [$cf['description'] => $description], [$cf['id'] => $id]);
  }


}
