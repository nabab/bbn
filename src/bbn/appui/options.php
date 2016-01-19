<?php
/**
 * @package bbn\appui
 */
namespace bbn\appui;
/**
 * An all-in-one options management system
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Oct 28, 2015, 10:23:55 +0000
 * @category  Appui tools
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.1
 * @todo Implement Cache
 */


class options
{
  protected static
    /** @var array */
    $_defaults = [
    'errors' => [
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

  protected function get_rows($where, $start = false, $limit = false){
    $db =& $this->db;
    $tab = $db->tsn($this->cfg['table']);
    $cols = [];
    if ( \bbn\str\text::is_integer($start, $limit) && !empty($where) ){
      if ( !isset($where[$this->cfg['cols']['active']]) ){
        $where[$this->cfg['cols']['active']] = 1;
      }
      if ( $wst = $db->get_where($where, $tab) ){
        foreach ( $this->cfg['cols'] AS $k => $col ){
          if ( $k !== 'active' ){
            array_push($cols, $db->cfn($col, $tab, 1));
          }
        }
        array_push($cols, "COUNT(".$db->escape($tab.'2').'.'.$db->escape($this->cfg['cols']['id']).") AS num_children ");
        $q = "SELECT ".implode(", ", $cols)."
          FROM ".$db->tsn($tab, 1)."
            LEFT JOIN ".$db->tsn($tab, 1)." AS ".$db->escape($tab.'2')."
              ON ".$db->cfn($this->cfg['cols']['id_parent'], $tab.'2', 1)." = ".$db->cfn($this->cfg['cols']['id'], $tab, 1)."
              AND ".$db->cfn($this->cfg['cols']['active'], $tab.'2', 1)." = 1
          $wst
          AND ".$this->db->cfn($this->cfg['cols']['active'], $tab, 1)." = 1
          GROUP BY " . $this->db->cfn($this->cfg['cols']['id'], $tab, 1)."
          ORDER BY text
          ".( $limit && \bbn\str\text::is_integer($start) ? "LIMIT $start, $limit" : '');
        $args = array_values($where);
        if ( class_exists('\\bbn\\appui\\history') && \bbn\appui\history::is_enabled() ){
          array_push($args, 1);
        }
        return $this->db->get_rows($q, $args);
      }
    }
    return false;
  }

  protected function get_row($where){
    if ( $res = $this->get_rows($where, 0, 1) ){
      return $res[0];
    }
    return false;
  }

  public function __construct(\bbn\db\connection $db, array $cfg=[]){
    $this->db = $db;
    $this->cfg = \bbn\tools::merge_arrays(self::$_defaults, $cfg);
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
      if ( \bbn\tools::is_assoc($cfg) ) {
        foreach ($cfg as $k => $v) {
          $val[$k] = $v;
        }
        unset($val[$this->cfg['cols']['value']]);
      }
      else{
        $val[$this->cfg['cols']['value']] = $cfg;
      }
    }
    return $val;
  }

  public function set_default($cat = 0, $id_parent = false){
    $cat = $id_parent ? $this->from_code($cat, $id_parent) : $this->from_code($cat);
    $this->default = $cat;
  }

  public function from_code(){
    $args = func_get_args();
    if ( count($args) && is_array($args[0]) ){
      $args = $args[0];
    }
    $rargs = array_reverse($args, false);
    $id_parent = $this->default;
    while ( count($rargs) ){
      $cur = current($rargs);
      if ( \bbn\str\text::is_integer($cur) ){
        $id_parent = $cur;
      }
      else if ( is_string($cur) ){
        $id_parent = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
          $this->cfg['cols']['id_parent'] => $id_parent,
          $this->cfg['cols']['code'] => $cur
        ]);
      }
      else{
        return false;
      }
      if ( !\bbn\str\text::is_integer($id_parent) ){
        \bbn\tools::log($cur." ||| ".$id_parent, "no_options");
        return false;
      }
      array_shift($rargs);
    }
    return \bbn\str\text::is_integer($id_parent) ? $id_parent : false;
  }

  public function get_prop($id, $prop, $false = true){
    if ( \bbn\str\text::is_integer($id) && ($o = $this->option($id)) ){
      if ( isset($o[$prop]) ){
        return $o[$prop];
      }
    }
    return $false ? false : null;
  }

  public function fix_order($id, $deep = false){
    if (
      ($id = $this->from_code(func_get_args())) &&
      $this->get_prop($id, 'orderable') &&
      ($opts = $this->full_options($id))
    ) {
      $i = 1;
      foreach ( $opts as $o ){
        if ( !isset($o['order']) || ($o['order'] != $i) ){
          $this->set_prop($o['id'], ['order' => $i]);
        }
        $i++;
      }
    }
    return $this;
  }

  /**
   * Retourne le contenu complet d'une option
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return array La liste des catégories
   */
  public function option($id){
    if ( $id = $this->from_code(func_get_args()) ) {
      $tab = $this->db->tsn($this->cfg['table']);
      $opt = $this->get_row([
        $this->db->cfn($this->cfg['cols']['id'], $tab) => $id
      ]);
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
    if ( $id = $this->from_code(func_get_args()) ) {
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
  public function options($cat = 0){
    $args = func_get_args();
    if ( ($cat = $this->from_code(empty($args) ? $cat : $args)) !== false ) {
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
  public function count($cat = 0){
    $cat = $this->from_code(func_get_args());
    if ( \bbn\str\text::is_integer($cat) ) {
      return $this->db->count($this->cfg['table'], [$this->cfg['cols']['id_parent'] => $cat]);
    }
    return false;
  }

  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function full_options($cat = 0, $id_parent = false, $where = [], $order = [], $start = 0, $limit = 2000){
    $opts = $this->native_options($cat, $id_parent, $where = [], $order = [], $start, $limit);
    if ( is_array($opts) ){
      foreach ($opts as $i => $o) {
        $this->get_value($o['id'], $opts[$i]);
      }
      $order = 1;
      $res = [];
      foreach ($opts as $i => $o) {
        // If only one does not have the order property defined we don't sort
        $res[$o['id']] = $o;
        if ( !isset($o['order']) ){
          $order = false;
        }
      }
      if ( $order ) {
        \bbn\tools::sort_by($res, 'order');
      }
      return $res;
    }
    return false;
  }

  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function native_options($cat = 0, $id_parent = false, $where = [], $order = [], $start = 0, $limit = false){
    $cat = $this->from_code($cat, $id_parent ? $id_parent : $this->default);
    if ( \bbn\str\text::is_integer($cat, $start, $limit) ) {
      if ( !is_array($where) ){
        $where = [];
      }
      $where[$this->cfg['cols']['id_parent']] = $cat;
      return $this->get_rows($where, $order = [], $start, $limit);
    }
    return false;
  }

  public function tree($cat, $id_parent = false, $length = 128){
    $length--;
    if ( $length >= 0 ){
      $cat = $this->from_code($cat, $id_parent ? $id_parent : $this->default);
      if ( \bbn\str\text::is_integer($cat) && ($text = $this->text($cat)) ) {
        $res = [
          'id' => $cat,
          'text' => $text
        ];
        if ($opts = $this->db->get_column_values($this->cfg['table'], $this->cfg['cols']['id'], [
          $this->cfg['cols']['id_parent'] => $cat
        ])
        ) {
          $res['items'] = [];
          foreach ($opts as $o) {
            if ($t = $this->tree($o, $length)) {
              array_push($res['items'], $t);
            }
          }
        }
        else if ( $this->text->code($cat) === 'bbn_options' ){
          $res['items'] = $this->options();
        }
        return $res;
      }
    }
    return false;
  }

  public function full_tree($cat, $id_parent = false, $length=128){
    $length--;
    if ( $length >= 0 ) {
      $cat = $this->from_code($cat, $id_parent ? $id_parent : $this->default);
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
            if ( defined('BBN_OPTIONS_URL') && $res['items'] ){
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

  public function add($cfg){
    if ( !isset($cfg[$this->cfg['cols']['id_parent']]) ){
      $cfg[$this->cfg['cols']['id_parent']] = $this->default;
    }
    if ( isset($cfg[$this->cfg['cols']['id_parent']], $cfg[$this->cfg['cols']['text']]) ){
      if ( isset($cfg[$this->cfg['cols']['value']]) &&
        \bbn\str\text::is_json($cfg[$this->cfg['cols']['value']])
      ){
        $cfg[$this->cfg['cols']['value']] = json_decode($cfg[$this->cfg['cols']['value']], 1);
      }
      if ( empty($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = [];
      }
      if ( isset($cfg['num_children']) ){
        unset($cfg['num_children']);
      }
      if ( isset($cfg['items']) ){
        unset($cfg['items']);
      }
      foreach ( $cfg as $k => $c ){
        if ( !in_array($k, $this->cfg['cols']) ){
          $cfg[$this->cfg['cols']['value']][$k] = \bbn\str\text::is_json($c) ? json_decode($c, 1) : $c;
          unset($cfg[$k]);
        }
      }
      if ( is_array($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = json_encode($cfg[$this->cfg['cols']['value']]);
      }
      if ( $this->db->insert($this->cfg['table'], [
        $this->cfg['cols']['id_parent'] => $cfg[$this->cfg['cols']['id_parent']],
        $this->cfg['cols']['text'] => $cfg[$this->cfg['cols']['text']],
        $this->cfg['cols']['code'] => !empty($cfg[$this->cfg['cols']['code']]) ? $cfg[$this->cfg['cols']['code']] : null,
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

  public function orderable($id, $is_orderable = true, $destruct = false){
    if ( !empty($id) && !empty($cfg) && ($o = $this->option($id)) ){
      if ( $is_orderable && empty($o['orderable']) ){

      }
      else if ( !$is_orderable && !empty($o['orderable']) ){

      }
    }
    return $this;
  }

  public function set_prop($id, $cfg){
    if ( !empty($id) && !empty($cfg) && ($o = $this->option($id)) ){
      foreach ( $cfg as $k => $v ) {
        $o[$k] = $v;
      }
      return $this->set($id, $o);
    }
    return false;
  }

  public function unset_prop($id, $cfg){
    if ( !empty($id) && !empty($cfg) && ($o = $this->option($id)) ){
      if ( is_string($cfg) ){
        $cfg = [$cfg];
      }
      foreach ( $cfg as $k ) {
        unset($o[$k]);
      }
      return $this->set($id, $o);
    }
    return false;
  }

  public function set($id, $cfg){
    if ( !empty($id) && !empty($cfg) && is_int($id) ){
      if ( isset($cfg[$this->cfg['cols']['value']]) &&
        \bbn\str\text::is_json($cfg[$this->cfg['cols']['value']])
      ){
        $cfg[$this->cfg['cols']['value']] = json_decode($cfg[$this->cfg['cols']['value']], 1);
      }
      if ( empty($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = [];
      }
      if ( isset($cfg['num_children']) ){
        unset($cfg['num_children']);
      }
      if ( isset($cfg['items']) ){
        unset($cfg['items']);
      }
      foreach ( $cfg as $k => $c ){
        if ( !in_array($k, $this->cfg['cols']) ){
          $cfg[$this->cfg['cols']['value']][$k] = \bbn\str\text::is_json($c) ? json_decode($c, 1) : $c;
          unset($cfg[$k]);
        }
      }
      if ( is_array($cfg[$this->cfg['cols']['value']]) ){
        $cfg[$this->cfg['cols']['value']] = json_encode($cfg[$this->cfg['cols']['value']]);
      }
      return $this->db->update($this->cfg['table'], [
        $this->cfg['cols']['text'] => $cfg[$this->cfg['cols']['text']],
        $this->cfg['cols']['code'] => !empty($cfg[$this->cfg['cols']['code']]) ? $cfg[$this->cfg['cols']['code']] : null,
        $this->cfg['cols']['value'] => isset($cfg[$this->cfg['cols']['value']]) ? $cfg[$this->cfg['cols']['value']] : ''
      ], [
        $this->cfg['cols']['id'] => $id
      ]);
    }
    return false;
  }

  public function remove($id){
    if ( $id = $this->from_code(func_get_args()) ) {
      return $this->db->delete($this->cfg['table'], [
        $this->cfg['cols']['id'] => $id
      ]);
    }
    return false;
  }

  public function get_ids_by_code($code){
    if ( $id = $this->from_code(func_get_args()) ) {
      return $this->db->get_column_values($this->cfg['table'], 'id', [
        $this->cfg['cols']['id_parent'] => $id
      ]);
    }
    return false;
  }

  public function get_id_parent($id){
    if ( $id = $this->from_code(func_get_args()) ){
      return $this->db->get_val(
        $this->cfg['table'],
        $this->cfg['cols']['id_parent'],
        ['id' => $id]);
    }
    return false;
  }

  public function parent($id){
    if ( $id_parent = $this->get_id_parent($id) ){
      return $this->option($id_parent);
    }
    return false;
  }

  public function order($id, $pos){
    if (
      ($pos > 0) &&
      ($parent = $this->parent($id)) &&
      !empty($parent['orderable'])
    ){
      $options = $this->full_options($parent['id']);
      // The order really changes
      if ( $options[$id]['order'] !== $pos ){
        $options = array_values($options);
        $idx = \bbn\tools::find($options, ['id' => $id]);
        if ( $idx !== false ){
          $this->set_prop($options[$idx]['id'], ['order' => $pos]);
          if ( $idx < ($pos - 1) ){
            while ( $idx < ($pos - 1) ){
              $idx++;
              $this->set_prop($options[$idx]['id'], ['order' => $idx]);
            }
          }
          else{
            if ( $idx > ($pos - 1) ){
              while ( $idx > ($pos - 1) ){
                $this->set_prop($options[$idx-1]['id'], ['order' => $idx+1]);
                $idx--;
              }
            }
            else{
              $this->fix_order($parent['id']);
            }
          }
        }
      }
    }
    return $this;
  }

  public function is_parent($id, $id_parent){
    // Preventing infinite loop
    $done = [$id];
    if ( \bbn\str\text::is_integer($id, $id_parent) ){
      while ( $id = $this->get_id_parent($id) ){
        if ( $id === $id_parent ){
          return true;
        }
        if ( in_array($id, $done) ){
          break;
        }
        array_push($done, $id);
      }
    }
    return false;
  }

  public function apply($f, $id = 0, $deep = false){
    $id = $this->from_code($id);
    $opts = $this->full_options($id);
    $changes = 0;
    foreach ( $opts as $i => $o ){
      $o = $f($o);
      if ( $deep && $o['num_children'] ){
        $this->apply($f, $opts[$i]['id'], 1);
      }
      if ( $o && ($opts[$i] !== $o) ){
        $changes += (int)$this->set($o['id'], $o);
      }
    }
  }

  public function has_id(){
    $args = func_get_args();
    if ( $this->from_code($args) ){
      return true;
    }
    if ( is_string($args[0]) && (count($args) > 1) ){
      $code = $args[0];
      array_shift($args);
      if ( $this->from_code($args) ){
        return true;
      }
    }
    return false;
  }

  public function get_id(){
    $args = func_get_args();
    if ( !($id = $this->from_code($args)) ){
      if ( is_string($args[0]) && (count($args) > 1) ){
        $code = $args[0];
        array_shift($args);
        if ( !($id = $this->from_code($args)) ){
          $this->add([
            'code' => $code,
            'text' => $code,
            'id_parent' => $id
          ]);
          $id = $this->from_code(func_get_args());
        }
      }
    }
    return $id;
  }

  public function soptions($cat){
    $r = [];
    if ( $cats = $this->options($cat) ){
      foreach ( $cats as $id => $txt ){
        $o = $this->options($id);
        if ( is_array($o) ){
          $r = \bbn\tools::merge_arrays($r, $o);
        }
        else{
          die("BAD ID: $id");
        }
      }
    }
    return $r;
  }

  public function full_soptions($cat){
    $r = [];
    if ( $cats = $this->options($cat) ){
      foreach ( $cats as $id => $txt ){
        $o = $this->full_options($id);
        if ( is_array($o) ){
          $r = \bbn\tools::merge_arrays($r, $o);
        }
        else{
          die("BAD ID: $id");
        }
      }
    }
    return $r;
  }

  public function native_soptions($cat){
    $r = [];
    if ( $cats = $this->options($cat) ){
      foreach ( $cats as $id => $txt ){
        $r = \bbn\tools::merge_arrays($r, $this->native_options($id));
      }
    }
    return $r;
  }
}
