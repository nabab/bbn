<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 17/09/2015
 * Time: 01:16
 */

namespace bbn\appui;


class options
{
  protected $db;

  public function __construct(\bbn\db\connection $db){
    $this->db = $db;
  }

  /**
   * Retourne le contenu complet d'une option
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return array La liste des catégories
   */
  public function option($id){
    if ($d = $this->db->rselect("bbn_options", [], ['id' => $id])) {
      $d['value'] = json_decode($d['value'], 1);
      return $d;
    }
    return false;
  }

  /**
   * Retourne le titre d'une option
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return string La valeur du champ titre correspondant
   */
  public function title($id){
    return $this->db->get_val("bbn_options", "title", "id", $id);
  }

  /**
   * Retourne la liste des options d'une catégorie indexée sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array La liste des options indexée sur leur `id`
   */
  public function options($cat = null){
    if ( is_string($cat) ){
      $cat = $this->db->get_val("bbn_options", "id", "code", $cat);
    }
    if ( $cat ){
      return $this->db->get_key_val("
        SELECT id, title
        FROM bbn_options
        WHERE id_parent = ?
        AND actif = 1
        ORDER BY title",
        $cat);
    }
    else{
      return $this->db->get_key_val("
        SELECT id, title
        FROM bbn_options
        WHERE id_parent IS NULL
        AND actif = 1
        ORDER BY title");
    }
  }

  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function full_options($cat = null){
    $res = [];
    if ( !empty($cat) && !\bbn\str\text::is_integer($cat) ){
      $cat = $this->db->get_val("bbn_options", "id", "code", $cat);
    }
    if ( $cat ){
      $opts = $this->db->get_rows("
        SELECT *
        FROM bbn_options
        WHERE id_parent = ?
        AND actif = 1
        ORDER BY title",
        $cat);
    }
    else{
      $opts = $this->db->get_rows("
        SELECT *
        FROM bbn_options
        WHERE id_parent IS NULL
        AND actif = 1
        ORDER BY title");
    }
    if ( !empty($opts) ){
      foreach ( $opts as $o ){
        $res[$o['id']] = $o;
        if (!empty($o['value']) && ($cfg = json_decode($o['value'], 1))) {
          foreach ($cfg as $k => $v) {
            $res[$o['id']][$k] = $v;
          }
        }
      }
    }
    return $res;
  }

  public function add($cat, $titre, $val)
  {

  }

  public function set($fn, $cp = null)
  {
    return false;
  }

  public function remove($cat, $titre, $val)
  {
    switch ($cat) {

    }
  }
}