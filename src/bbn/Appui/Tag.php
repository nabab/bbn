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
        'lang' => 'lang',
        'description' => 'description'
      ],
      'relations' => [
        'id_tag_orig' => 'tag',
        'id_tag_dest' => 'lang',
        'relationship' => 'relationship'
      ]
    ]
  ];

  protected $all;

  protected $lang;

  public function __construct(Db $db, string $lang = null)
  {
    $this->lang = $lang ?: 'en';
    parent::__construct($db);
    $this->initClassCfg();
    $this->cacheInit();
  }


  public function getAll(bool $force = false)
  {
    if (!$this->all || $force) {
      $table = $this->class_cfg['table'];
      $cf = $this->class_cfg['arch']['tags'];
      $this->all = $this->db->getColumnValues(
        $table,
        $cf['tag'],
        [],
        ['tag' => 'ASC']
      );
    }

    return $this->all;
  }


  public function get(string $tag, string $lang = null): ?array
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    return $this->db->rselect($table, [], [$cf['tag'] => $tag, $cf['lang'] => $lang ?: $this->lang]);
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
  public function add(string $tag, string $lang = null, string $description = ''): ?string
  {
    $table = $this->class_cfg['table'];
    $cf = $this->class_cfg['arch']['tags'];
    if ($this->db->insertIgnore(
      $table,
      [
        $cf['tag'] => $tag,
        $cf['lang'] => $lang ?: $this->lang,
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
