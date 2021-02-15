<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 29/11/2014
 * Time: 02:45
 */

namespace bbn\Appui;

use bbn;
use bbn\X;

class Masks extends bbn\Models\Cls\Db
{

  use bbn\Models\Tts\Optional;

  protected $notes;

  protected $o;

  public function __construct(bbn\Db $db)
  {
    parent::__construct($db);
    self::optionalInit();
    $this->notes = new Note($this->db);
    $this->o = bbn\Appui\Option::getInstance();
  }

  public function count($id_type = null)
  {
    if ($id_type) {
      return $this->db->count('bbn_notes_masks', ['id_type' => $id_type]);
    }

    return $this->db->count('bbn_notes_masks');
  }

  /**
   * Gets the content of a mask based on the provided ID
   * @param string $id
   * @return array|null
   */
  public function get(string $id, bool $simple = false): ?array
  {
    if (($mask = $this->db->rselect('bbn_notes_masks', [], ['id_note' => $id]))
        && ($data = $this->notes->get($mask['id_note'], null, $simple))
    ) {
      $data['default'] = $mask['def'];
      $data['id_type'] = $mask['id_type'];
      $data['type'] = $this->o->text($mask['id_type']);
      $data['name'] = $mask['name'];
      return $data;
    }
    return null;
  }

  public function getAll($id_type = null, $simple = true)
  {
    if (!empty($id_type) && !bbn\Str::isUid($id_type)) {
      $id_type = self::getOptionId($id_type);
    }
    $r = [];
    $all = $this->db->getColumnValues(
      'bbn_notes_masks', 'id_note', $id_type ? [
      'id_type' => $id_type
      ] : []
    );
    foreach ($all as $a){
      if ($tmp = $this->get($a, $simple)) {
        $r[] = $tmp;
      }
    }
    return $r;
  }

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
            $tmp['fulltext'] = $a['title'].
              ($a['default'] ? ' ('.dgettext(X::tDom(), 'default').')' : '').
              ' - v'.$a['version'].' '.
              \bbn\Date::format($a['creation']).' '.dgettext(X::tDom(), 'by').' '.
              $admin->getName($a['id_user']);
          }
          $res[] = $tmp;
        }
        return $res;
      }
    }
    return null;
  }

  public function getDefault($id_type)
  {
    if (!bbn\Str::isUid($id_type)) {
      $id_type = self::getOptionId($id_type);
    }
    if ($id_note = $this->db->selectOne(
      'bbn_notes_masks', 'id_note', [
      'id_type' => $id_type,
      'def' => 1
      ]
    ) 
    ) {
      return $this->get($id_note);
    }
    return null;
  }

  public function setDefault($id_note)
  {
    $current = $this->get($id_note);
    if ($current) {
      if ($old = $this->getDefault($current['id_type'])) {
        if ($old['id_note'] === $id_note) {
          return 1;
        }
        $this->db->update(
          'bbn_notes_masks', [
          'def' => 0
          ], [
          'id_note' => $old['id_note']
          ]
        );
      }
      return $this->db->update(
        'bbn_notes_masks', [
        'def' => 1
        ], [
        'id_note' => $id_note
        ]
      );
    }
  }

  public function insert($name, $id_type, $title, $content): ?string
  {
    if (!bbn\Str::isUid($id_type)) {
      $id_type = self::getOptionId($id_type);
    }
    if ($this->o->exists($id_type) 
        && ($id_note = $this->notes->insert($title, $content, $this->o->fromCode('masks', 'types', 'note', 'appui')))
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

  public function update(array $cfg)
  {
    if (!empty($cfg['id_note'])  
        && !empty($cfg['title']) 
        && !empty($cfg['content']) 
        && !empty($cfg['name'])
    ) {
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

  public function delete($id_note)
  {
    if ($this->db->delete('bbn_notes_masks', ['id_note' => $id_note])) {
      return $this->notes->remove($id_note);
    }
  }

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

  public function getCategories()
  {
    return self::getOptions();
  }



  /*






  public function get_st($id){
    $this->_init_option();
    if ( $a = $this->get($id) ){
      $a['categorie'] = $this->o->title($a['categorie']);
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
