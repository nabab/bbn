<?php
/**
 * Masks class, handling masks for notes.
 *
 * @namespace bbn\Appui
 * @author BBN
 * @since 29/11/2014
 */
namespace bbn\Appui;

use bbn;
use bbn\X;

class Masks extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\Optional;

  /**
   * Notes object.
   *
   * @var Note
   */
  protected $notes;

  /**
   * Constructor.
   *
   * @param bbn\Db $db Database object.
   */
  public function __construct(bbn\Db $db)
  {
    parent::__construct($db);
    $this->initOptional();
    $this->notes = new Note($this->db);
  }

  /**
   * Count masks.
   *
   * @param int|null $id_type Type ID.
   * @return int Count of masks.
   */
  public function count($id_type = null)
  {
    if ($id_type) {
      return $this->db->count('bbn_notes_masks', ['id_type' => $id_type]);
    }

    return $this->db->count('bbn_notes_masks');
  }

  /**
   * Get a mask by ID.
   *
   * @param string $id Mask ID.
   * @param bool $simple Return simple data.
   * @return array|null Mask data.
   */
  public function get(string $id, bool $simple = false): ?array
  {
    if (($mask = $this->db->rselect('bbn_notes_masks', [], ['id_note' => $id]))
        && ($data = $this->notes->get($mask['id_note'], null, $simple))
    ) {
      $data['default'] = $mask['def'];
      $data['id_type'] = $mask['id_type'];
      $data['type'] = $this->options->text($mask['id_type']);
      $data['name'] = $mask['name'];
      return $data;
    }
    return null;
  }

  /**
   * Get all masks.
   *
   * @param int|null $id_type Type ID.
   * @param bool $simple Return simple data.
   * @return array Masks data.
   */
  public function getAll($id_type = null, $simple = true)
  {
    if (!empty($id_type) && !bbn\Str::isUid($id_type)) {
      $id_type = self::getOptionId($id_type);
    }
    $r = [];
    $all = $this->db->getColumnValues('bbn_notes_masks', 'id_note', $id_type ? ['id_type' => $id_type] : []);
    foreach ($all as $a) {
      if ($tmp = $this->get($a, $simple)) {
        $r[] = $tmp;
      }
    }
    return $r;
  }

  /**
   * Get text value for a type.
   *
   * @param int $id_type Type ID.
   * @param bool $fulltext Return full text.
   * @return array|null Text value.
   */
  public function getTextValue($id_type, $fulltext = false)
  {
    if (!bbn\Str::isUid($id_type)) {
      $id_type = self::getOptionId($id_type);
    }
    if (bbn\Str::isUid($id_type)) {
      $all = $this->getAll($id_type);
      $admin = new bbn\User\Manager(\bbn\User::getInstance());
      if (\is_array($all)) {
        $res = [];
        foreach ($all as $a) {
          $tmp = [
            'text' => $a['name'],
            'value' => $a['id_note']
          ];
          if ($fulltext) {
            $tmp['fulltext'] = $a['title'] .
              ($a['default'] ? ' (' . X::_('default') . ')' : '') .
              ' - v' . $a['version'] . ' ' .
              \bbn\Date::format($a['creation']) . ' ' . X::_('by') . ' ' .
              $admin->getName($a['id_user']);
          }
          $res[] = $tmp;
        }
        return $res;
      }
    }
    return null;
  }

  /**
   * Get default mask for a type.
   *
   * @param int $id_type Type ID.
   * @return array|null Default mask data.
   */
  public function getDefault($id_type)
  {
    if (!bbn\Str::isUid($id_type)) {
      $id_type = self::getOptionId($id_type);
    }
    if ($id_note = $this->db->selectOne('bbn_notes_masks', 'id_note', [
      'id_type' => $id_type,
      'def' => 1
    ])) {
      return $this->get($id_note);
    }
    return null;
  }

  /**
   * Set default mask for a type.
   *
   * @param int $id_note Mask ID.
   * @return int|bool Result of update operation.
   */
  public function setDefault($id_note)
  {
    $current = $this->get($id_note);
    if ($current) {
      if ($old = $this->getDefault($current['id_type'])) {
        if ($old['id_note'] === $id_note) {
          return 1;
        }
        $this->db->update('bbn_notes_masks', ['def' => 0], ['id_note' => $old['id_note']]);
      }
      return $this->db->update('bbn_notes_masks', ['def' => 1], ['id_note' => $id_note]);
    }
  }

  /**
   * Insert a new mask.
   *
   * @param string $name Mask name.
   * @param int $id_type Type ID.
   * @param string $title Mask title.
   * @param string $content Mask content.
   * @return string|null ID of the inserted mask.
   */
  public function insert($name, $id_type, $title, $content): ?string
  {
    if (!bbn\Str::isUid($id_type)) {
      $id_type = self::getOptionId($id_type);
    }
    if ($this->options->exists($id_type)
        && ($id_note = $this->notes->insert(
          $title,
          $content,
          $this->options->fromCode('masks', 'types', 'note', 'appui')
        ))
    ) {
      $data = [
        'id_note' => $id_note,
        'id_type' => $id_type,
        'name' => $name
      ];
      if (!$this->count($id_type)) {
        $data['def'] = 1;
      }
      if ($this->db->insert('bbn_notes_masks', $data)) {
        return $id_note;
      }
    }
    return null;
  }

  /**
   * Update a mask.
   *
   * @param array $cfg Mask data.
   * @return bool Result of update operation.
   */
  public function update(array $cfg)
  {
    if (!empty($cfg['id_note']) && !empty($cfg['title']) && !empty($cfg['content']) && !empty($cfg['name'])) {
      $data = [
        'name' => $cfg['name'],
        'def' => !empty($cfg['def']) || !empty($cfg['default']) ? 1 : 0
      ];
      if (!empty($cfg['id_type'])) {
        $data['id_type'] = $cfg['id_type'];
      }
      $update_mask = $this->db->update('bbn_notes_masks', $data, ['id_note' => $cfg['id_note']]);
      $update_notes = $this->notes->update($cfg['id_note'], $cfg['title'], $cfg['content']);
      if ($update_mask || $update_notes) {
        return true;
      }
    }
    return false;
  }

  /**
   * Delete a mask.
   *
   * @param int $id_note Mask ID.
   * @return bool Result of delete operation.
   */
  public function delete($id_note)
  {
    if ($this->db->delete('bbn_notes_masks', ['id_note' => $id_note])) {
      return $this->notes->remove($id_note);
    }
  }

  /**
   * Render a mask.
   *
   * @param string $note Mask ID or data.
   * @param array $data Data to render with.
   * @return string|null Rendered mask content.
   */
  public function render($note, $data)
  {
    if (bbn\Str::isUid($note)) {
      $note = $this->get($note);
    }
    if (!empty($note['content'])) {
      return \bbn\Tpl::render($note['content'], $data);
    }
    return null;
  }

  /**
   * Get categories.
   *
   * @return array Categories.
   */
  public function getCategories()
  {
    return self::getOptions();
  }

 /*






  public function get_st($id){
    $this->_init_option();
    if ( $a = $this->get($id) ){
      $a['categorie'] = $this->options->title($a['categorie']);
      return $a;
    }
    return false;
  }

  public function get_all_st($incl_deleted = false){
    $all = $this->db->getColumnValues('bbn_notes_masks', 'id');
    $r = [];
    foreach ( $all as $a ){
      array_push($r, $this->get_st($a));
    }
    return $r;
  }

  public function get_by_cat($cat){
    $id = $this->db->selectOne('bbn_notes_masks', 'id_note', ['id_type' => $cat, 'def' => 1]);
    return $this->get($id);
  }

  public function get_by_cat_st($cat){
    $id = $this->db->selectOne('bbn_notes_masks', 'id', ['categorie' => $cat, 'defaut' => 1]);
    return $this->get_st($id);
  }
  */

}