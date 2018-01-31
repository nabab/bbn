<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 29/11/2014
 * Time: 02:45
 */

namespace bbn\appui;

use bbn;


class masks extends bbn\models\cls\db {

  use bbn\models\tts\optional;

  protected
    $notes,
    $o;

  public function __construct(bbn\db $db){
    parent::__construct($db);
    self::optional_init();
    $this->notes = new notes($this->db);
    $this->o = bbn\appui\options::get_instance();
  }

  public function count($id_type = null){
    if ( $id_type ){
      return $this->db->count('bbn_notes_masks', ['id_type' => $id_type]);
    }

    return $this->db->count('bbn_notes_masks');
  }

  /**
   * Gets the content of a mask based on the provided ID
   * @param string $id
   * @return array|null
   */
  public function get(string $id): ?array
  {
    $mask = $this->db->rselect('bbn_notes_masks', [], ['id_note' => $id]);
    if ( $data = $this->notes->get($mask['id_note']) ){
      $data['default'] = $mask['def'];
      $data['id_type'] = $mask['id_type'];
      $data['type'] = $this->o->text($mask['id_type']);
      return $data;
    }
    return null;
  }

  public function get_all($id_type = null){
    $all = $id_type ? $this->db->get_column_values('bbn_notes_masks', 'id_note', [
      'id_type' => $id_type
    ]) : $this->db->get_column_values('bbn_notes_masks', 'id_note');
    $r = [];
    foreach ( $all as $a ){
      $r[] = $this->get($a);
    }
    return $r;
  }

  public function get_text_value($id_type){
    $all = $this->get_all($id_type);
    $admin = new bbn\user\manager(\bbn\user::get_instance());
    if ( \is_array($all) ){
      $res = [];
      foreach ( $all as $a ){
        $res[] = [
          'text' => $a['title'].
            ($a['default'] ? ' ('._('default').')' : '').
            ' - v'.$a['version'].' '.
            \bbn\date::format($a['creation']).' '._('by').' '.
            $admin->get_name($a['id_user']),
          'value' => $a['id_note']
        ];
      }
      return $res;
    }
    return null;
  }

  public function get_default($id_type){
    if ( $id_note = $this->db->select_one('bbn_notes_masks', 'id_note', [
      'id_type' => $id_type,
      'def' => 1
    ]) ){
      return $this->get($id_note);
    }
    return null;
  }

  public function set_default($id_note){
    $current = $this->get($id_note);
    if ( $current ){
      while ( $old = $this->get_default($current['id_type']) ){
        if ( $old['id_note'] === $id_note ){
          return null;
        }
        $this->db->update('bbn_notes_masks', [
          'def' => 0
        ], [
          'id_note' => $old['id_note']
        ]);
      }
      return $this->db->update('bbn_notes_masks', [
        'def' => 1
      ], [
        'id_note' => $id_note
      ]);
    }
  }

  public function insert($id_type, $title, $content): ?string
  {
    if (
      $this->o->exists($id_type) &&
      ($id_note = $this->notes->insert($title, $content))
    ){
      $data = [
        'id_note' => $id_note,
        'id_type' => $id_type
      ];
      if ( !$this->count($id_type) ){
        $data['def'] = 1;
      }
      if ( $this->db->insert('bbn_notes_masks', $data) ){
        return $id_note;
      }
    }
    return null;
  }

  public function update(array $cfg){
    if ( !empty($cfg['id']) && !empty($cfg['nom']) && !empty($cfg['categorie']) && !empty($cfg['titre']) && !empty($cfg['texte']) ){
      $id = $cfg['id'];
      unset($cfg['id']);
      $this->db->update('bbn_notes_masks', $cfg, ['id' => $id]);
    }
  }

  public function delete($id_note){
    if ( $this->db->delete('bbn_notes_masks', ['id_note' => $id_note]) ){
      return $this->notes->remove($id_note);
    }
  }

  public function render($note, $data){
    if ( bbn\str::is_uid($note) ){
      $note = $this->get($note);
    }
    if ( !empty($note['content']) ){
      return \bbn\tpl::render($note['content'], $data);
    }
    return null;
  }

  public function get_categories(){

  }










  public function get_st($id){
    $this->_init_option();
    if ( $a = $this->get($id) ){
      $a['categorie'] = $this->o->title($a['categorie']);
      return $a;
    }
    return false;
  }

  public function get_all_st($incl_deleted = false){
    $all = $this->db->get_column_values('bbn_notes_masks', 'id');
    $r = [];
    foreach ( $all as $a ){
      array_push($r, $this->get_st($a));
    }
    return $r;
  }

  public function get_by_cat($cat){
    $id = $this->db->select_one('bbn_notes_masks', 'id_note', ['id_type' => $cat, 'def' => 1]);
    return $this->get($id);
  }

  public function get_by_cat_st($cat){
    $id = $this->db->select_one('bbn_notes_masks', 'id', ['categorie' => $cat, 'defaut' => 1]);
    return $this->get_st($id);
  }

}