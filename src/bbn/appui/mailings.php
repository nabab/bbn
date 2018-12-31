<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 15/06/2018
 * Time: 12:02
 */

namespace bbn\appui;
use bbn;

class mailings
{
  private
    $db,
    $mvc,
    $notes;

  private function _note(): notes
  {
    if ( !$this->notes ){
      $this->notes = new notes($this->db);
    }
    return $this->notes;
  }

  public function __construct(bbn\db $db, $mvc)
  {
    if ( method_exists($mvc, 'get_model') && $db->check() ){
      $this->db = $db;
      $this->mvc = $mvc;
    }
  }

  public function check(): bool
  {
    return $this->db && $this->db->check();
  }

  public function is_sending(): bool
  {
    return $this->db->count("bbn_emailings", ['statut' => 'en cours']) > 0;
  }

  public function is_suspended($id): bool
  {
    return !!$this->db->select_one('bbn_emailings', 'id', ['id' => $id, 'statut' => 'suspendu']);
  }

  public function get_recipients($id_recipients): ?array
  {
    if ( $this->check() && ($opt = options::get_instance()) && ($cfg = $opt->option($id_recipients)) ){
      if ( isset($cfg['model'], $cfg['grid']) && ($m = $this->mvc->get_model($cfg['model'], $cfg['grid'])) ){
        $m['column'] = $cfg['column'];
        return $m;
      }
    }
    return [];
  }

  public function get_next_mailing(): ?array
  {
    if ( $this->check() ){
      $notes = $this->_note();
      if (
        ($row = $this->db->rselect('bbn_emailings', [], [
          ['envoi', '<', date('Y-m-d H:i:s')],
          ['envoi', '>', 0],
          ['statut', 'LIKE', 'pret']
        ])) &&
        ($note = $notes->get($row['id_note']))
      ){
        return bbn\x::merge_arrays($row, $note);
      }
    }
    return null;
  }

  public function change_state($id_mailing, $new_state): ?bool
  {
    if ( $this->check() ){
      return (bool)$this->db->update("bbn_emailings", ['statut' => $new_state], ['id' => $id_mailing]);
    }
    return false;
  }

  public function get_medias($id, $version){
    $med = [];
    if ( $files = $this->_note()->get_medias($id, $version) ){
      foreach ( $files as $f ){
        $t = $f['title'] ?: $f['name'];
        if ( is_file(BBN_DATA_PATH.$f['file']) && !\array_key_exists($t, $med) ){
          $med[$t] = BBN_DATA_PATH.$f['file'];
        }
      }
    }
    return $med;
  }

}