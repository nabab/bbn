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

  protected function get_rows($where, $start = 0, $limit = 2000){
    $tab = $this->db->tsn($this->cfg['table']);
    $db =& $this->db;
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
          LIMIT $start, $limit";
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
      foreach ($cfg as $k => $v) {
        $val[$k] = $v;
      }
      unset($val[$this->cfg['cols']['value']]);
    }
    return $val;
  }

  public function set_default($cat = 0, $id_parent = false){
    $cat = $this->from_code($cat, $id_parent);
    $this->default = $cat;
  }

  public function from_code($cat, $id_parent = false){
    $id_parent = $id_parent === false ? $this->default : $this->from_code($id_parent);
    if ( is_string($cat) && ($r = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
      $this->cfg['cols']['id_parent'] => $id_parent,
      $this->cfg['cols']['code'] => $cat
    ])) ){
      return $r;
    }
    return \bbn\str\text::is_integer($cat) ? $cat : false;
  }

  /**
   * Retourne le contenu complet d'une option
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return array La liste des catégories
   */
  public function option($id, $cat = false){
    $id = $this->from_code($id, $cat);
    if ( \bbn\str\text::is_integer($id) ) {
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
  public function text($id, $cat = false){
    $id = $this->from_code($id, $cat);
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
  public function options($cat = null, $id_parent = false){
    $cat = $this->from_code($cat, $id_parent);
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
  public function count($cat = null, $id_parent = false){
    $cat = $this->from_code($cat, $id_parent);
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
  public function full_options($cat = null, $id_parent = false, $start = 0, $limit = 2000){
    if ( $opts = $this->native_options($cat, $id_parent, $start, $limit) ){
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
  public function native_options($cat = null, $id_parent = false, $start = 0, $limit = 2000){
    $cat = $this->from_code(is_null($cat) ? $this->default : $cat, $id_parent);
    if ( \bbn\str\text::is_integer($cat, $start, $limit) ) {
      return $this->get_rows([$this->cfg['cols']['id_parent'] => $cat], $start, $limit);
    }
    return false;
  }

  public function tree($cat, $id_parent = false, $length = 128){
    $length--;
    if ( $length >= 0 ){
      $cat = $this->from_code($cat, $id_parent);
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

  public function full_tree($cat, $id_parent = false, $length=128){
    $length--;
    if ( $length >= 0 ) {
      $cat = $this->from_code($cat, $id_parent);
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
    $id = $this->from_code($id);
    if ( is_int($id) ){
      return $this->db->delete($this->cfg['table'], [
        $this->cfg['cols']['id'] => $id
      ]);
    }
    return false;
  }

  public function get_ids_by_code($code){
    if ( $code ) {
      return $this->db->get_column_values($this->cfg['table'], 'id', [
        $this->cfg['cols']['code'] => $code
      ]);
    }
    return false;
  }

  public function get_id_parent($id){
    if ( $id = $this->from_code($id) ){
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
    if ( $parent = $this->parent($id) ){
      if ( !empty($parent['orderable']) ){
        $options = $this->full_options($parent['id']);
        // The order really changes
        if ( $options[$id]['order'] !== $pos ){
          $idx = \bbn\tools::find($options, ['id' => $id]);
          if ( $idx !== false ){

          }




          $i = 1;
          $new_order = [];
          foreach ( $options as $k => $o ){
            if ( $i === $pos ){

            }
            else if ( $k === $id ){

            }
            if ( !isset($o['order']) || $o['order'] !== $i ){

            }
          }
        }
        var_dump($options);
      }
    }
    return false;
  }

  public function is_parent($id, $id_parent){
    $id = $this->from_code($id);
    $id_parent = $this->from_code($id_parent);
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

  public function get_id($code, $id_parent){
    if ( !($id = $this->from_code($code, $id_parent)) ){
      $this->add([
        'code' => $code,
        'text' => $code,
        'id_parent' => $id_parent
      ]);
      $id = $this->from_code($code, $id_parent);
    }
    return $id;
  }
}
