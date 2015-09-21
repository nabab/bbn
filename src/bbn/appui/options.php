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
  protected static
    /** @var array */
    $_defaults = [
    'errors' => [
      0 => 'login failed',
      2 => 'password sent',
      3 => 'no email such as',
      4 => 'too many attempts',
      5 => 'impossible to create the user',
      6 => 'wrong user and/or password',
      7 => 'different passwords',
      8 => 'less than 5 mn between emailing password',
      9 => 'user already exists',
      10 => 'problem during user creation'
    ],
    'table' => 'bbn_options',
    'cols' => [
      'id' => 'id',
      'id_parent' => 'id_parent',
      'title' => 'title',
      'code' => 'code',
      'value' => 'value',
      'active' => 'active'
    ]
  ];

  protected $db;

  public function __construct(\bbn\db\connection $db, array $cfg=[]){
    $this->db = $db;
    $this->cfg = \bbn\tools::merge_arrays(self::$_defaults, $cfg);
  }

  /**
   * Retourne le contenu complet d'une option
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return array La liste des catégories
   */
  public function option($id){
    if ($d = $this->db->rselect($this->cfg['table'], [], [$this->cfg['cols']['id'] => $id])) {
      $d['value'] = json_decode($d[$this->cfg['cols']['value']], 1);
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
    return $this->db->get_val($this->cfg['table'], $this->cfg['cols']['title'], $this->cfg['cols']['id'], $id);
  }

  /**
   * Retourne la liste des options d'une catégorie indexée sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array La liste des options indexée sur leur `id`
   */
  public function options($cat = 0){
    if ( is_string($cat) ){
      $cat = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
        $this->cfg['cols']['id_parent'] => 0,
        $this->cfg['cols']['code'] => $cat
      ]);
    }
    return $this->db->select_all_by_keys($this->cfg['table'],
      [$this->cfg['cols']['id'], $this->cfg['cols']['title']],
      [$this->cfg['cols']['id_parent'] => $cat]
    );
  }

  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function full_options($cat = 0){
    if ( is_string($cat) ){
      $cat = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
        $this->cfg['cols']['id_parent'] => 0,
        $this->cfg['cols']['code'] => $cat
      ]);
    }
    $opts = $this->db->rselect_all($this->cfg['table'], $this->cfg['cols'], [
      $this->cfg['cols']['id_parent'] => $cat
    ]);
    $res = [];
    if ( !empty($opts) ){
      foreach ( $opts as $i => $o ){
        $res[$o['id']] = $o;
        $this->get_value($o['id'], $res[$o['id']]);
      }
    }
    return $res;
  }

  public function set_value($id, $val){
    if ( is_array($val) ){
      $val = json_encode($val);
    }
    return $this->db->update($this->cfg['table'], [
      $this->cfg['cols']['value'] => $val
    ], [
      $this->cfg['cols']['id'] => $id
    ]);
  }

  public function get_value($id, &$val=null){
    if ( is_null($val) ){
      $val = [
        $this->cfg['cols']['value'] => $this->db->select_one(
          $this->cfg['table'],
          $this->cfg['cols']['value'],
          [ $this->cfg['cols']['id'] => $id ]
        )
      ];
    }
    if ( \bbn\str\text::is_json($val[$this->cfg['cols']['value']]) ){
      $cfg = json_decode($val[$this->cfg['cols']['value']], 1);
      foreach ($cfg as $k => $v) {
        $val[$k] = $v;
      }
    }
    return $val;
  }

  public function add($cfg){
    if ( isset($cfg[$this->cfg['cols']['id_parent']], $cfg[$this->cfg['cols']['title']]) ){
      if ( isset($cfg[$this->cfg['cols']['value']]) && is_array($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = json_encode($cfg[$this->cfg['cols']['value']]);
      }
      return $this->db->insert($this->cfg['table'], [
        $this->cfg['cols']['id_parent'] => $cfg[$this->cfg['cols']['id_parent']],
        $this->cfg['cols']['title'] => $cfg[$this->cfg['cols']['title']],
        $this->cfg['cols']['code'] => isset($cfg[$this->cfg['cols']['code']]) ? $cfg[$this->cfg['cols']['code']] : null,
        $this->cfg['cols']['value'] => isset($cfg[$this->cfg['cols']['value']]) ? $cfg[$this->cfg['cols']['value']] : ''
      ]);
    }
    return false;
  }

  public function set($id, $cfg){
    if ( !empty($id) && !empty($cfg) && is_int($id) ){
      return $this->db->update($this->cfg['table'], $cfg, [
        $this->cfg['cols']['id'] => $id
      ]);
    }
    return false;
  }

  public function remove($id){
    if ( !empty($id) && is_int($id) ){
      return $this->db->delete($this->cfg['table'], [
        $this->cfg['cols']['id'] => $id
      ]);
    }
    return false;
  }
}