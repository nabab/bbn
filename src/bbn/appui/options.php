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
      'text' => 'text',
      'code' => 'code',
      'value' => 'value',
      'active' => 'active'
    ]
  ];

  protected
    $db,
    $default = 0;

  public function __construct(\bbn\db\connection $db, array $cfg=[]){
    $this->db = $db;
    $this->cfg = \bbn\tools::merge_arrays(self::$_defaults, $cfg);
  }

  public function set_default($cat = 0){
    $this->default = $cat;
  }

  public function from_code($cat){
    if ( is_string($cat) && ($r = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
      $this->cfg['cols']['id_parent'] => $this->default,
      $this->cfg['cols']['code'] => $cat
    ])) ){
      return $r;
    }
    return \bbn\str\text::is_integer($cat) ? $cat : $this->default;
  }
  /**
   * Retourne le contenu complet d'une option
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return array La liste des catégories
   */
  public function option($id){
    $id = $this->from_code($id);
    if ( \bbn\str\text::is_integer($id) ) {
      $tab = $this->db->tsn($this->cfg['table']);
      $opt = $this->db->get_row($this->get_query()."
        AND " . $this->db->cfn($this->cfg['cols']['id'], $tab, 1) . " = ?
        GROUP BY " . $this->db->cfn($this->cfg['cols']['id'], $tab, 1),
        $id);
      if ($opt) {
        $this->get_value($id, $opt);
        return $opt;
      }
    }
    return false;
  }

  /**
   * Returns an option's title
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return string La valeur du champ titre correspondant
   */
  public function text($id){
    $id = $this->from_code($id);
    if ( \bbn\str\text::is_integer($id) ) {
      return $this->db->get_val($this->cfg['table'], $this->cfg['cols']['text'], $this->cfg['cols']['id'], $id);
    }
    return false;
  }

  /**
   * Returns an option's code
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return string La valeur du champ titre correspondant
   */
  public function code($id){
    $id = $this->from_code($id);
    if ( \bbn\str\text::is_integer($id) ) {
      return $this->db->get_val($this->cfg['table'], $this->cfg['cols']['code'], $this->cfg['cols']['id'], $id);
    }
    return false;
  }

  /**
   * Retourne la liste des options d'une catégorie indexée sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array La liste des options indexée sur leur `id`
   */
  public function options($cat = null){
    $cat = $this->from_code($cat);
    if ( \bbn\str\text::is_integer($cat) ) {
      return $this->db->select_all_by_keys($this->cfg['table'],
        [$this->cfg['cols']['id'], $this->cfg['cols']['text']],
        [$this->cfg['cols']['id_parent'] => $cat],
        [$this->cfg['cols']['text'] => 'ASC']
      );
    }
    return false;
  }

  /**
   * Retourne la liste des options d'une catégorie indexée sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array La liste des options indexée sur leur `id`
   */
  public function count($cat = null){
    $cat = $this->from_code($cat);
    if ( \bbn\str\text::is_integer($cat) ) {
      return $this->db->count($this->cfg['table'], [$this->cfg['cols']['id_parent'] => $cat]);
    }
    return false;
  }

  protected function get_query(){
    $tab = $this->db->tsn($this->cfg['table']);
    $cols = [];
    foreach ( $this->cfg['cols'] AS $k => $col ){
      if ( $k !== 'active' ){
        array_push($cols, $this->db->cfn($col, $tab, 1));
      }
    }
    array_push($cols, "COUNT(".$this->db->escape($tab.'2').'.'.$this->db->escape($this->cfg['cols']['id']).") AS num_children ");
    return "SELECT ".implode(", ", $cols)."
      FROM ".$this->db->tsn($tab, 1)."
        LEFT JOIN ".$this->db->tsn($tab, 1)." AS ".$this->db->escape($tab.'2')."
          ON ".$this->db->cfn($this->cfg['cols']['id_parent'], $tab.'2', 1)." = ".$this->db->cfn($this->cfg['cols']['id'], $tab, 1)."
          AND ".$this->db->cfn($this->cfg['cols']['active'], $tab.'2', 1)." = 1
      WHERE ".$this->db->cfn($this->cfg['cols']['active'], $tab, 1)." = 1";
  }

  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function full_options($cat = null, $start = 0, $limit = 2000){
    $cat = $this->from_code($cat);
    if ( \bbn\str\text::is_integer($cat, $start, $limit) ) {
      $db =& $this->db;
      $tab = $db->tsn($this->cfg['table']);
      $opts = $db->get_rows($this->get_query()."
        AND ".$db->cfn($this->cfg['cols']['id_parent'], $tab, 1)." = ?
        GROUP BY ".$db->cfn($this->cfg['cols']['id'], $tab, 1)."
        ORDER BY ".$db->cfn($this->cfg['cols']['text'], $tab, 1)."
        LIMIT $start, $limit",
        $cat);
      $res = [];
      // Tells if we sort by order property or leave it by text
      $order = 1;
      if (!empty($opts)) {
        foreach ($opts as $i => $o) {
          $res[$o['id']] = $o;
          $this->get_value($o['id'], $res[$o['id']]);
          // If only one does not have the order property defined we don't sort
          if ( !isset($res[$o['id']]['order']) ){
            $order = false;
          }
        }
        if ( $order ) {
          \bbn\tools::sort_by($res, 'order');
        }
      }
      return $res;
    }
    return false;
  }

  public function tree($cat, $length=128){
    $length--;
    if ( $length >= 0 ){
      $cat = $this->from_code($cat);
      if ( \bbn\str\text::is_integer($cat) && ($text = $this->text($cat)) ) {
        $res = [
          'id' => $cat,
          'text' => $text
        ];
        $res['item'] = [];
        if ($opts = $this->db->get_column_values($this->cfg['table'], $this->cfg['cols']['id'], [
          $this->cfg['cols']['id_parent'] => $cat
        ])
        ) {
          foreach ($opts as $o) {
            if ($t = $this->tree($o, $length)) {
              array_push($res['items'], $t);
            }
          }
        }
        else if ( $this->text->code($cat) === 'bbn_options' ){
          $res['items'] = $this->options();
        }
        else{
          unset($res['items']);
        }
        return $res;
      }
    }
    return false;
  }

  public function full_tree($cat, $length=128){
    $length--;
    if ( $length >= 0 ) {
      $cat = $this->from_code($cat);
      if (\bbn\str\text::is_integer($cat) && ($text = $this->text($cat))) {
        $res = $this->db->rselect($this->cfg['table'], array_values($this->cfg['cols']), [
          $this->cfg['cols']['id'] => $cat
        ]);
        if ($res) {
          $this->get_value($cat, $res);
          $res['items'] = [];
          if ($opts = $this->db->get_column_values(
            $this->cfg['table'],
            $this->cfg['cols']['id'],
            [$this->cfg['cols']['id_parent'] => $cat],
            [$this->cfg['cols']['text'] => 'ASC'])
          ) {
            foreach ($opts as $o) {
              if ($t = $this->full_tree($o, $length)) {
                array_push($res['items'], $t);
              }
            }
            if (isset($t['order'])) {
              \bbn\tools::sort_by($res['items'], 'order');
            }
          }
          else if ( $res['code'] === 'bbn_options' ){
            $res['items'] = $this->full_options();
            if ( defined('BBN_OPTIONS_URL') ){
              array_walk($res['items'], function(&$a){
                $a['link'] = BBN_OPTIONS_URL.$a['id'];
              });
            }
          }
          else{
            unset($res['items']);
          }
          return $res;
        }
      }
    }
    return false;
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
      unset($val[$this->cfg['cols']['value']]);
    }
    return $val;
  }

  public function add($cfg){
    if ( !isset($cfg[$this->cfg['cols']['id_parent']]) ){
      $cfg[$this->cfg['cols']['id_parent']] = 0;
    }
    if ( isset($cfg[$this->cfg['cols']['id_parent']], $cfg[$this->cfg['cols']['text']]) ){
      if ( empty($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = [];
        foreach ( $cfg as $k => $c ){
          if ( ($k !== 'items') && !in_array($k, $this->cfg['cols']) ){
            $cfg[$this->cfg['cols']['value']][$k] = \bbn\str\text::is_json($c) ? json_decode($c, 1) : $c;
          }
        }
      }
      if ( !empty($cfg[$this->cfg['cols']['value']]) && is_array($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = json_encode($cfg[$this->cfg['cols']['value']]);
      }
      if ( $this->db->insert($this->cfg['table'], [
        $this->cfg['cols']['id_parent'] => $cfg[$this->cfg['cols']['id_parent']],
        $this->cfg['cols']['text'] => $cfg[$this->cfg['cols']['text']],
        $this->cfg['cols']['code'] => isset($cfg[$this->cfg['cols']['code']]) ? $cfg[$this->cfg['cols']['code']] : null,
        $this->cfg['cols']['value'] => isset($cfg[$this->cfg['cols']['value']]) ? $cfg[$this->cfg['cols']['value']] : '',
        $this->cfg['cols']['active'] => 1
      ]) ){
        $id = $this->db->last_id();
        $res = 1;
        if ( !empty($cfg['items']) && is_array($cfg['items']) ){
          foreach ( $cfg['items'] as $it ){
            $it['id_parent'] = $id;
            $res += (int)$this->add($it);
          }
        }
        return $res;
      }
    }
    return false;
  }

  public function set($id, $cfg){
    if ( !empty($id) && !empty($cfg) && is_int($id) ){
      if ( empty($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = [];
        foreach ( $cfg as $k => $c ){
          if ( ($k !== 'items') && ($k !== 'num_children') && !in_array($k, $this->cfg['cols']) ){
            $cfg[$this->cfg['cols']['value']][$k] = \bbn\str\text::is_json($c) ? json_decode($c, 1) : $c;
          }
        }
      }
      if ( !empty($cfg[$this->cfg['cols']['value']]) && is_array($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = json_encode($cfg[$this->cfg['cols']['value']]);
      }
      if ( $this->db->update($this->cfg['table'], [
        $this->cfg['cols']['text'] => $cfg[$this->cfg['cols']['text']],
        $this->cfg['cols']['code'] => !empty($cfg[$this->cfg['cols']['code']]) ? $cfg[$this->cfg['cols']['code']] : null,
        $this->cfg['cols']['value'] => isset($cfg[$this->cfg['cols']['value']]) ? $cfg[$this->cfg['cols']['value']] : ''
      ], [
        $this->cfg['cols']['id'] => $id
      ]) ){
        return 1;
      }
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