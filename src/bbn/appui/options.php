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
 * @category  Appui x
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.1
 * @todo Implement Cache
 */


class options
{
  private static $_cache_prefix = 'bbn-options-';

  protected static
    /** @var array */
    $_defaults = [
      'errors' => [
      ],
      'table' => 'bbn_options',
      'cols' => [
        'id' => 'id',
        'id_parent' => 'id_parent',
        'id_alias' => 'id_alias',
        'text' => 'text',
        'code' => 'code',
        'value' => 'value',
        'cfg' => 'cfg',
        'active' => 'active'
      ]
    ];

  protected
    /** @var \bbn\db The database connection */
    $db,
    /** @var \bbn\cache The cache object */
    $cacher,
    /** @var int The default root ID of the options in the table */
    $default = 0;

  private function _has_history(){
    return class_exists('\\bbn\\appui\\history') && \bbn\appui\history::is_enabled();
  }

  private function _cache_name($method, $uid){
    return self::$_cache_prefix.(string)$uid.'-'.$method;
  }

  private function _cache_delete($id, $parents = true, $deep = false){
    $this->cacher->delete_all($this->_cache_name('', $id));
    if ( $parents ){
      $ps = $this->parents($id);
      foreach ( $ps as $i => $p ){
        $this->cacher->delete_all($this->_cache_name('', $p));
      }
    }
    if ( $deep ){
      $items = $this->tree_ids($id);
      foreach ( $items as $item ){
        $this->_cache_delete($item, false);
      }
    }
    return $this;
  }

  /**
   * Performs the actual query with a where parameter.
   * Always returns the whole result without limit
   * @param $where
   * @return array|bool|false
   */
  protected function get_rows($where){
    $db =& $this->db;
    $tab = $this->cfg['table'];
    $cols = [];
    if ( empty($where) ){
      $where = [$this->cfg['cols']['active'] => 1];
    }
    if ( $hist = $this->_has_history() ){
      \bbn\appui\history::disable();
    }
    $wst = $db->get_where($where, $tab);
    if ( $hist ){
      \bbn\appui\history::enable();
    }
    if ( $wst ){
      foreach ( $this->cfg['cols'] AS $k => $col ){
        if ( $k !== 'active' ){
          array_push($cols, $db->cfn($col, $tab, 1));
        }
      }
      array_push($cols, "COUNT(".$db->escape($tab.'2').'.'.$db->escape($this->cfg['cols']['id']).") AS num_children ");
      $q = "SELECT ".implode(", ", $cols)."
        FROM ".$db->escape($tab)."
          LEFT JOIN ".$db->escape($tab)." AS ".$db->escape($tab.'2')."
            ON ".$db->cfn($this->cfg['cols']['id_parent'], $tab.'2', 1)." = ".$db->cfn($this->cfg['cols']['id'], $tab, 1)."
            AND ".$db->cfn($this->cfg['cols']['active'], $tab.'2', 1)." = 1
        $wst
        GROUP BY " . $this->db->cfn($this->cfg['cols']['id'], $tab, 1)."
        ORDER BY ".$db->cfn($this->cfg['cols']['text'], $tab, 1);
      $args = array_values($where);
      return $this->db->get_rows($q, $args);
    }
    return false;
  }

  /**
   * Gets the first row from a result
   * @param $where
   * @return bool
   */
  protected function get_row($where){
    if ( $res = $this->get_rows($where) ){
      return $res[0];
    }
    return false;
  }

  /**
   * options constructor.
   * @param \bbn\db $db
   * @param array $cfg
   */
  public function __construct(\bbn\db $db, array $cfg=[]){
    $this->db = $db;
    $this->cfg = \bbn\x::merge_arrays(self::$_defaults, $cfg);
    $this->cacher = \bbn\cache::get_engine();
  }

  public function get_default(){
    return $this->default;
  }

  /**
   * Gets an option ID from its code, or a succession of codes/ID from the most specific till the most general - or even from the whole option array
   *
   * @param mixed $cat
   * @param mixed $id_parent
   * @return int
   */
  public function from_code(){
    $args = func_get_args();
    while ( isset($args[0]) && is_array($args[0]) ){
      $args = $args[0];
    }
    if ( isset($args['id']) ){
      return $args['id'];
    }
    if ( \bbn\str::is_integer($args[0]) ){
      return $args[0];
    }
    $rargs = array_reverse($args, false);
    $id_parent = $this->default;
    while ( count($rargs) ){
      $cur = current($rargs);
      if ( \bbn\str::is_integer($cur) ){
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
      if ( !\bbn\str::is_integer($id_parent) ){
        \bbn\x::log($cur." ||| ".$id_parent, "no_options");
        return false;
      }
      array_shift($rargs);
    }
    return \bbn\str::is_integer($id_parent) ? $id_parent : false;
  }

  /**
   * @param int $id option's ID
   * @param string $prop Name of the property to fetch
   * @param bool $false Sets if function returns false (default) or null in case of not found
   * @return mixed
   */
  public function get_prop($id, $prop, $false = true){
    if ( \bbn\str::is_integer($id) ){
      $opt = $this->option($id);
    }
    else if ( is_array($id) ){
      $opt =& $id;
    }
    if ( empty($opt) || !isset($opt['id']) ){
      return false;
    }
    if ( isset($opt[$prop]) ){
      return $opt[$prop];
    }
    return $false ? false : null;
  }

  public function fix_order($id, $deep = false){
    if (
      $this->get_param($id, 'orderable') &&
      ($opts = $this->full_options($id))
    ) {
      $i = 1;
      foreach ( $opts as $o ){
        if ( !isset($o['cfg'], $o['cfg']['order']) || ($o['cfg']['order'] != $i) ){
          $this->set_param($o['id'], ['order' => $i]);
        }
        $i++;
      }
    }
    return $this;
  }

  public function items($id){
    $cfg = $this->get_cfg($id);
    if ( empty($cfg['orderable']) ){
      return $this->db->get_column_values(
        $this->cfg['table'],
        $this->cfg['cols']['id'], [
          $this->cfg['cols']['id_parent'] => $id,
        ], [
          $this->cfg['cols']['text'] => 'ASC'
        ]
      );
    }
    $rows = array_map(function($a){
      if ( !($a['cfg'] = json_decode($a['cfg'], 1)) ){
        $a['cfg'] = [];
      }
      return $a;
    }, $this->db->rselect_all($this->cfg['table'], [
      $this->cfg['cols']['id'],
      $this->cfg['cols']['cfg']
    ], [
      $this->cfg['cols']['id_parent'] => $id
    ]));
    usort($rows, function($a, $b){
      return (isset($a['cfg']['order']) ? $a['cfg']['order'] : 1000000) > (isset($b['cfg']['order']) ? $b['cfg']['order'] : 1000000);
    });
    return array_map(function($a){
      return $a['id'];
    }, $rows);
  }

  public function native_option($id){
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ) {
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }
      $tab = $this->db->tsn($this->cfg['table']);
      $opt = $this->get_row([
        $this->db->cfn($this->cfg['cols']['id'], $tab) => $id
      ]);
      if ( $opt ){
        $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $opt);
        return $opt;
      }
    }
    return false;
  }

  /**
   * Retourne le contenu complet d'une option
   *
   * @param int $id La valeur du champ `id` de l'option dans la base de données
   * @return array La liste des catégories
   */
  public function option($id){
    if ( is_array($id) && isset($id['id']) ){
      $opt = $id;
      $id = $id['id'];
    }
    else{
      $id = $this->from_code(func_get_args());
    }
    if ( \bbn\str::is_integer($id) ) {
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }
      if ( isset($opt) || ($opt = $this->native_option($id)) ){
        $this->get_value($opt);
        $this->get_cfg($opt);
        if ( \bbn\str::is_integer($opt['id_alias']) ){
          if ( $opt['id_alias'] === $id ){
            die("Impossible to have the same ID as ALIAS, check out ID $id");
          }
          $opt['alias'] = $this->option($opt['id_alias']);
        }
        $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $opt);
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
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ) {
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
    if ( \bbn\str::is_integer($id) ) {
      return $this->db->get_val($this->cfg['table'], $this->cfg['cols']['code'], $this->cfg['cols']['id'], $id);
    }
    return false;
  }

  /**
   * Retourne la liste des options d'une catégorie indexée sur leur `id`
   *
   * @param string|int $id La catégorie, sous la forme de son `id`, ou de son nom
   * @return array La liste des options indexée sur leur `id`
   */
  public function options($id = 0){
    $args = func_get_args();
    if ( ($id = $this->from_code($args ?: $id)) !== false ) {
      if ( isset($args[0]['id']) ){
        return $args[0]['id'];
      }
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }
      $opt = $this->db->select_all_by_keys($this->cfg['table'],
        [$this->cfg['cols']['id'], $this->cfg['cols']['text']],
        [$this->cfg['cols']['id_parent'] => $id],
        [$this->cfg['cols']['text'] => 'ASC']
      );
      $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $opt);
      return $opt;
    }
    return false;
  }

  /**
   * Retourne la liste des options d'une catégorie indexée sur leur `id`
   *
   * @param string|int $id La catégorie, sous la forme de son `id`, ou de son nom
   * @return array La liste des options indexée sur leur `id`
   */
  public function count($id = 0){
    $args = func_get_args();
    $id = $this->from_code($args ?: $id);
    if ( \bbn\str::is_integer($id) ) {
      return $this->db->count($this->cfg['table'], [$this->cfg['cols']['id_parent'] => $id]);
    }
    return false;
  }

  public function get_path_array($id, $root){
    if ( $code = $this->code($id) ){
      $parts = [];
      while ( $id && ($id !== $root) ){
        array_unshift($parts, $code);
        $id = $this->get_id_parent($id);
        $code = $this->code($id);
      }
      return $parts;
    }
    return false;
  }

  public function get_path($id, $root, $sep = '|'){
    if ( $parts = $this->get_path_array($id, $root) ){
      return implode($sep, $parts);
    }
  }

  public function options_by_alias($id_alias, $full = false){
    if ( \bbn\str::is_integer($id_alias) ){
      $where = [$this->cfg['cols']['id_alias'] => $id_alias];
      $list = $this->get_rows($where);
      if ( is_array($list) ){
        $res = [];
        foreach ($list as $i ){
          if ( $full ){
            array_push($res, $this->option($i));
          }
          else{
            unset($i['value'], $i['cfg']);
            array_push($res, $i);
          }

        }
        return $res;
      }
    }
    return false;
  }

  /**
   * Returns all the infos about the options with a given parent in an array indexed on their IDs
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function full_options($id = 0, $id_parent = false, $where = [], $order = [], $start = 0, $limit = 2000){
    if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
      return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
    }
    if ( \bbn\str::is_integer($id = $this->from_code($id, $id_parent)) ){
      $list = $this->items($id);
      if ( is_array($list) ){
        $res = [];
        foreach ($list as $i) {
          $res[$i] = $this->option($i);
        }
        $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
        return $res;
      }
    }
    return false;
  }

  /**
   * Returns all the infos about the options with a given parent in an array indexed on their IDs
   *
   * @param string|int $id Category, under the form of its ID or code
   * @return array
   */
  public function native_options($id = 0, $id_parent = false, $where = [], $order = [], $start = 0, $limit = false){
    $id = $this->from_code($id, $id_parent ?: $this->default);
    if ( \bbn\str::is_integer($id, $start) ) {
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }
      if ( !is_array($where) ){
        $where = [];
      }
      $where[$this->cfg['cols']['id_parent']] = $id;
      $opts = $this->get_rows($where, $start, $limit);
      $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $opts);
      return $opts;
    }
    return false;
  }

  public function tree_ids($id, &$res = []){
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ) {
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }
      if ( $opts = $this->items($id) ){
        foreach ($opts as $o) {
          array_push($res, $o);
          $this->tree_ids($o, $res);
        }
      }
      return $res;
    }
    return false;
  }

  public function tree($id, $id_parent = false){
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) && ($text = $this->text($id)) ) {
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }
      $res = [
        'id' => $id,
        'text' => $text
      ];
      if ( $opts = $this->items($id) ){
        $res['items'] = [];
        foreach ($opts as $o) {
          if ($t = $this->tree($o)) {
            array_push($res['items'], $t);
          }
        }
      }
      /*else if ( $this->code($id) === 'bbn_options' ){
        $res['items'] = $this->options();
      }*/
      $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
      return $res;
    }
    return false;
  }

  public function full_tree($id){
    $id = $this->from_code(func_get_args());
    if (\bbn\str::is_integer($id) && ($text = $this->text($id))) {
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }

      if ( $res = $this->option($id) ){
        $res['items'] = [];
        if ($opts = $this->items($id) ){
          foreach ($opts as $o) {
            if ($t = $this->full_tree($o)) {
              array_push($res['items'], $t);
            }
          }
        }
        /*else if ( ($res['code'] === 'bbn_options') &&
          ($res['items'] = $this->full_options())
        ){
          if ( defined('BBN_OPTIONS_URL') ){
            array_walk($res['items'], function(&$a){
              $a['link'] = BBN_OPTIONS_URL.$a['id'];
            });
          }
        }*/
        else{
          unset($res['items']);
        }
        $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
        return $res;
      }
    }
    return false;
  }

  public function native_tree($id){
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ) {
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }
      $c = $this->cfg['cols'];
      if ( $res = $this->native_option($id) ) {
        $its = $this->items($id);
        if ( count($its) ){
          $res['items'] = [];
          foreach ( $its as $it ){
            array_push($res['items'], $this->native_tree($it, $id));
          }
        }
        $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
        return $res;
      }
    }
    return false;
  }

  /**
   * @param $it
   * @return bool
   */
  private function _prepare(&$it){
    // The table's columns
    $c = $this->cfg['cols'];
    // If id_parent is undefined it uses the default
    if ( !isset($it[$c['id_parent']]) ){
      $it[$c['id_parent']] = $this->default;
    }
    // Text is required and parent exists
    if ( isset($it[$c['id_parent']]) &&
      !empty($it[$c['text']]) &&
      ($parent = $this->option($it[$c['id_parent']]))
    ){
      // If code is empty it MUST be null
      if ( empty($it[$c['code']]) ){
        $it[$c['code']] = null;
      }
      // In this case we look for an inherited parent
      if ( empty($parent['cfg']['inheritance']) ){
        $parents = $this->parents($it[$c['id_parent']]);
        foreach ( $parents as $i => $p ){
          $tmp = $this->option($p);
          if ( !empty($parent['cfg']['inheritance']) && (($i === 0) || ($parent['cfg']['inheritance'] === 'cascade')) ){
            $parent = $tmp;
            break;
          }
        }
      }
      if ( isset($it[$c['value']]) &&
        \bbn\str::is_json($it[$c['value']])
      ){
        $it[$c['value']] = json_decode($it[$c['value']], 1);
      }
      if ( empty($it[$c['value']]) ){
        $it[$c['value']] = [];
      }
      if ( isset($it['alias']) ){
        unset($it['alias']);
      }
      if ( isset($it['num_children']) ){
        unset($it['num_children']);
      }
      if ( isset($it['items']) ){
        unset($it['items']);
      }
      foreach ( $it as $k => $v ){
        if ( !in_array($k, $c) ){
          $it[$c['value']][$k] = \bbn\str::is_json($v) ? json_decode($v, 1) : $v;
          unset($it[$k]);
        }
      }
      if ( is_array($it[$c['value']]) ){
        $it[$c['value']] = json_encode($it[$c['value']]);
      }
      if ( !isset($it[$c['cfg']]) ){
        $it[$c['cfg']] = [];
      }
      else if ( \bbn\str::is_json($it[$c['cfg']]) ){
        $it[$c['cfg']] = json_decode($it[$c['cfg']], 1);
      }
      else if ( !is_array($it[$c['cfg']]) ){
        $it[$c['cfg']] = $this->get_cfg($it[$c[id]]);
      }
      if ( !is_array($it[$c['cfg']]) ){
        $it[$c['cfg']] = [];
      }
      if ( !empty($parent[$c['cfg']]['orderable']) && empty($it[$c['cfg']]['order']) ){
        $it[$c['cfg']]['order'] = $parent['num_children'] + 1;
      }
      $it[$c['cfg']] = json_encode($it[$c['cfg']]);
      if ( !isset($it[$c['id_alias']]) || !\bbn\str::is_integer($it[$c['id_alias']]) ){
        $it[$c['id_alias']] = null;
      }
      if ( empty($it[$c['value']]) || ($it[$c['value']] === '[]') ){
        $it[$c['value']] = null;
      }
      if ( empty($it[$c['cfg']]) || ($it[$c['cfg']] === '[]') ){
        $it[$c['cfg']] = null;
      }
      return true;
    }
    return false;
  }

  public function add($it, $force = false){
    $res = false;
    $items = !empty($it['items']) && is_array($it['items']) ? $it['items'] : false;
    if ( $this->_prepare($it) ){
      $c = $this->cfg['cols'];
      $id = false;
      if ( $force &&
        !is_null($c['code']) &&
        \bbn\str::is_integer($id = $this->db->select_one($this->cfg['table'], $c['id'], [
          $c['id_parent'] => $it[$c['id_parent']],
          $c['text'] => $it[$c['text']],
          $c['code'] => $it[$c['code']]
        ]))
      ){
        $res = $this->db->update($this->cfg['table'], [
          $c['text'] => $it[$c['text']],
          $c['id_alias'] => $it[$c['id_alias']],
          $c['value'] => $it[$c['value']],
          $c['cfg'] => $it[$c['cfg']],
          $c['active'] => 1
        ], [$c['id'] => $id]);
      }
      else if ( $res = $this->db->insert($this->cfg['table'], [
          $c['id_parent'] => $it[$c['id_parent']],
          $c['text'] => $it[$c['text']],
          $c['code'] => $it[$c['code']],
          $c['id_alias'] => $it[$c['id_alias']],
          $c['value'] => $it[$c['value']],
          $c['cfg'] => $it[$c['cfg']],
          $c['active'] => 1
        ])
      ){
        $id = $this->db->last_id();
      }
      if ( $res ){
        $this->_cache_delete($id);
      }
      if ( \bbn\str::is_integer($id) && $items ){
        foreach ( $items as $it ){
          $it['id_parent'] = $id;
          $res += (int)$this->add($it, $force);
        }
      }
    }
    return $res;
  }

  public function from_path($path, $sep = '|'){
    $parts = explode($sep, $path);
    $parent = null;
    foreach ( $parts as $p ){
      if ( !empty($p) ){
        if ( is_null($parent) ){
          $parent = $this->default;
        }
        $parent = $this->from_code($p, $parent);
      }
    }
    return $parent ?: false;
  }

  public function set($id, $cfg){
    if ( $this->_prepare($cfg) ){
      $c = $this->cfg['cols'];
      if ( $res = $this->db->update($this->cfg['table'], [
        $c['text'] => $cfg[$c['text']],
        $c['code'] => !empty($cfg[$c['code']]) ? $cfg[$c['code']] : null,
        $c['id_alias'] => !empty($cfg[$c['id_alias']]) ? $cfg[$c['id_alias']] : null,
        $c['cfg'] => $cfg[$c['cfg']],
        $c['value'] => $cfg[$c['value']]
      ], [
        $c['id'] => $id
      ]) ){
        $this->_cache_delete($id);
        return $res;
      }
      return 0;
    }
    return false;
  }

  public function update($id, $cfg){
    $originals = $cfg;
    if ( $this->_prepare($cfg) ){
      $c = $this->cfg['cols'];
      $change = [];
      foreach ( $originals as $k => $v ){
        if ( ($k !== 'id') && isset($cfg[$k]) ){
          $change[$k] = $cfg[$k];
        }
      }
      if ( $res = $this->db->update($change, [
        $c['id'] => $id
      ]) ){
        $this->_cache_delete($id);
        return $res;
      }
      return 0;
    }
    return false;
  }

  public function remove($id){
    if ( $id = $this->from_code(func_get_args()) ) {
      $this->_cache_delete($id);
      return $this->db->delete($this->cfg['table'], [
        $this->cfg['cols']['id'] => $id
      ]);
    }
    return false;
  }

  /**
   * Sets the cfg field in the table for a given option, either through an array/object or a string
   * @param int $id
   * @param mixed $val
   * @return int
   */
  public function set_cfg($id, $cfg){
    if ( is_array($cfg) || is_object($cfg) ){
      $cfg = json_encode($cfg);
    }
    if ( $res = $this->db->update($this->cfg['table'], [
      $this->cfg['cols']['cfg'] => $cfg
    ], [
      $this->cfg['cols']['id'] => $id
    ]) ){
      $this->_cache_delete($id);
      return $res;
    }
    return 0;
  }

  /**
   * Sets some cfg parameters in the table for a given option, through an array/object
   * @param int $id
   * @param mixed $val
   * @return int
   */
  public function set_param($id, $params){
    if ( \bbn\str::is_integer($id) &&
      !empty($params) &&
      ($o = $this->option($id))
    ){
      $args = func_get_args();
      if ( is_string($params) && isset($args[2]) ){
        $params = [$params => $args[2]];
      }
      if ( !is_array($params) ){
        die("the parameter sent must be an array in set_param");
      }
      $cfg = $this->get_cfg($id);
      foreach ( $params as $k => $v ){
        $cfg[$k] = $v;
      }
      return $this->set_cfg($id, $cfg);
    }
    return false;
  }

  /**
   * Returns a formatted content of the cfg column: an array if it is json, the raw value otherwise.
   * The function can be called with the value in it, in this case it will just format it without fetching it in the database.
   *
   * @param int $id
   * @param null $val
   * @return array
   */
  public function get_cfg(&$id){
    if ( is_array($id) ){
      $opt =& $id;
    }
    else{
      $id = $this->from_code(func_get_args());
      if ( \bbn\str::is_integer($id) ){
        $opt = $this->option($id);
      }
    }
    if ( empty($opt) || !isset($opt['id']) ){
      return false;
    }
    if ( \bbn\str::is_json($opt[$this->cfg['cols']['cfg']]) ){
      $opt['cfg'] = json_decode($opt[$this->cfg['cols']['cfg']], 1);
    }
    $parents = $this->parents($opt['id']);
    foreach ( $parents as $i => $p ){
      $parent = $this->option($p);
      if ( !empty($parent['cfg']['inheritance']) ){
        if ( (($i === 0) && ($parent['cfg']['inheritance'] === 'children')) || ($parent['cfg']['inheritance'] === 'cascade') ){
          if ( isset($opt['cfg']['order']) ){
            $parent['cfg']['order'] = $opt['cfg']['order'];
          }
          $opt['cfg'] = $parent['cfg'];
          break;
        }
      }
    }
    if ( empty($opt['cfg']) ){
      $opt['cfg'] = [];
    }
    return $opt['cfg'];
  }

  public function fusion($src, $dest){
    $o_src = $this->option($src);
    $o_dest = $this->option($dest);
    $num = 0;
    if ( $o_dest && $o_src ){
      $o_final = \bbn\x::merge_arrays($o_src, $o_dest);
      $tables = $this->db->get_foreign_keys($this->cfg['cols']['id'], $this->cfg['table']);
      foreach ( $tables as $table => $cols ){
        foreach ( $cols as $c ){
          $num += (int)$this->db->update($table, [$c => $dest], [$c => $src]);
        }
      }
      $opt = $this->options($src);
      foreach ( $opt as $id => $text ){
        $num += (int)$this->move($id, $dest);
      }
      $num += (int)$this->set($dest, $o_final);
      $num += (int)$this->remove($src);
      $parent = $this->option($o_src['id_parent']);
      $this->_cache_delete($o_src['id_parent']);
      if ( !empty($parent['cfg']['orderable']) ){
        $this->fix_order($o_src['id_parent']);
      }
    }
    return $num;
  }

  public function move($id, $id_parent){
    $o = $this->option($id);
    $target = $this->option($id_parent);
    $res = false;
    if ( $o && $target ){
      if ( $target['cfg']['orderable'] ){
        $i = empty($target['num_children']) ? 0 : $target['num_children'];
        $this->set_param($id, ['order' => $i + 1]);
      }
      $res = $this->db->update($this->cfg['table'], [
        $this->cfg['cols']['id_parent'] => $id_parent
      ], [
        'id' => $id
      ]);
      $this->_cache_delete($id_parent);
      $this->_cache_delete($id);
      $this->_cache_delete($o['id_parent']);
    }
    return $res;
  }

  public function duplicate($id, $target, $deep = true, $force = false){
    $res = false;
    $target = $this->from_code($target);
    if ( \bbn\str::is_integer($target) ){
      if ( $opt = $this->export($id, $deep, 1) ){
        $res = $this->import($opt, $target, $force);
        $this->_cache_delete($target);
      }
    }
    return $res;
  }

  /**
   * Returns a formatted content of the cfg column: an array if it is json, the raw value otherwise.
   * The function can be called with the value in it, in this case it will just format it without fetching it in the database.
   *
   * @param int $id
   * @param null $val
   * @return array
   */
  public function get_param(&$id, $param, $false = true){
    if ( \bbn\str::is_integer($id) ){
      $opt = $this->option($id);
    }
    else if ( is_array($id) ){
      $opt =& $id;
    }
    if ( empty($opt) || !isset($opt['id']) ){
      return false;
    }
    if ( \bbn\str::is_json($opt[$this->cfg['cols']['cfg']]) ){
      $opt['cfg'] = json_decode($opt[$this->cfg['cols']['cfg']], 1);
    }
    return isset($opt['cfg'], $opt['cfg'][$param]) ? $opt['cfg'][$param] : ($false ? false : null);
  }

  public function set_alias($id, $alias){
    return $this->db->update_ignore($this->cfg['table'], [
      $this->cfg['cols']['id_alias'] => $alias ?: null
    ], [
      $this->cfg['cols']['id'] => $id
    ]);
  }

  public function unset_param($id, $cfg){
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

  public function unset_cfg($id){
    $res = false;
    if ( !empty($id) && ($o = $this->option($id)) && empty($o['num_children']) ){
      $res = $this->db->update($this->cfg['table'], [
        $this->cfg['cols']['cfg'] => null
      ], [
        $this->cfg['cols']['id'] => $id
      ]);
      if ( $res ){
        $this->_cache_delete($id);
      }
    }
    return $res;
  }

  public function unset_value($id){
    $res = false;
    if ( !empty($id) ){
      $res = $this->db->update($this->cfg['table'], [
        $this->cfg['cols']['value'] => null
      ], [
        $this->cfg['cols']['id'] => $id
      ]);
      if ( $res ){
        $this->_cache_delete($id);
      }
    }
    return $res;
  }

  /**
   * Sets the value field in the table for a given option, either through an array/object or a string
   * @param int $id
   * @param mixed $val
   * @return int
   */
  public function set_value($id, $val){
    if ( is_array($val) || is_object($val) ){
      $val = json_encode($val);
    }
    if ( empty($val) ){
      return $this->unset_value($id);
    }
    if ( $res = $this->db->update($this->cfg['table'], [
      $this->cfg['cols']['value'] => $val
    ], [
      $this->cfg['cols']['id'] => $id
    ]) ){
      $this->_cache_delete($id);
      return $res;
    }
    return 0;
  }

  /**
   * Returns a formatted content of the value column: an array if it is json, the raw value otherwise.
   * The function can be called with the value in it, in this case it will just format it without fetching it in the database.
   *
   * @param int $id
   * @param null $val
   * @return mixed
   */
  public function get_value(&$id){
    if ( \bbn\str::is_integer($id) ){
      $opt = $this->option($id);
    }
    else if ( is_array($id) ){
      $opt =& $id;
    }
    if ( empty($opt) || !isset($opt['id']) ){
      return false;
    }
    if ( isset($opt[$this->cfg['cols']['value']]) && \bbn\str::is_json($opt[$this->cfg['cols']['value']]) ){
      $val = json_decode($opt[$this->cfg['cols']['value']], 1);
      if ( \bbn\x::is_assoc($val) ) {
        foreach ($val as $k => $v) {
          if ( !isset($opt[$k]) ){
            $opt[$k] = $v;
          }
        }
        unset($opt[$this->cfg['cols']['value']]);
      }
      else{
        $opt[$this->cfg['cols']['value']] = $val;
      }
    }
    return $opt;
  }

  /**
   * Makes an option act as if it was the root option
   *
   * @param mixed $cat
   * @param mixed $id_parent
   * @return \bbn\appui\options
   */
  public function set_default(){
    if ( \bbn\str::is_integer($id = $this->from_code(func_get_args())) ){
      $this->default = $id;
    }
    return $this;
  }

  public function orderable($id, $is_orderable = true, $destruct = false){
    if ( !empty($id) &&
      !empty($cfg) &&
      ($was_orderable = $this->get_param($id, 'orderable', false))
    ){
      if ( $is_orderable && empty($was_orderable) ){
        return $this->set_param($id, 'orderable', 1);
      }
      else if ( !$is_orderable ){
        if ( $destruct && isset($was_orderable) ){
          return $this->unset_param($id, 'orderable');
        }
        else if ( !empty($was_orderable) ){
          return $this->set_param($id, 'orderable', 0);
        }
      }
    }
    return $this;
  }

  public function set_prop($id, $prop){
    if ( !empty($id) && !empty($prop) && ($o = $this->option($id)) ){
      $args = func_get_args();
      if ( is_string($prop) && isset($args[2]) ){
        $prop = [$prop => $args[2]];
      }
      foreach ( $prop as $k => $v ) {
        $o[$k] = $v;
      }
      return $this->set($id, $o);
    }
    return false;
  }

  public function unset_prop($id, $prop){
    if ( \bbn\str::is_integer($id) && !empty($prop) && ($o = $this->option($id)) ){
      if ( is_string($prop) ){
        $prop = [$prop];
      }
      foreach ( $prop as $k ) {
        unset($o[$k]);
      }
      return $this->set($id, $o);
    }
    return false;
  }

  public function get_ids_by_code($code){
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ) {
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
    if ( $id = $this->from_code(func_get_args()) ){
      if ( $id_parent = $this->get_id_parent($id) ){
        return $this->option($id_parent);
      }
    }
    return false;
  }

  public function parents($id){
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ) {
      $res = [];
      while ( ($id_parent = $this->get_id_parent($id)) !== false ){
        array_push($res, $id_parent);
        $id = $id_parent;
      }
      return $res;
    }
    return false;
  }

  public function order($id, $pos){
    if (
      ($pos > 0) &&
      ($parent = $this->parent($id)) &&
      !empty($parent['cfg']['orderable'])
    ){
      $options = $this->full_options($parent['id']);
      // The order really changes
      $idx = \bbn\x::find($options, ['id' => $id]);
      if ( $options[$id]['cfg']['order'] !== $pos ){
        $options = array_values($options);
        $idx = \bbn\x::find($options, ['id' => $id]);
        if ( $idx !== false ){
          $this->set_param($options[$idx]['id'], ['order' => $pos]);
          if ( $idx < ($pos - 1) ){
            while ( $idx < ($pos - 1) ){
              $idx++;
              if ( $options[$idx]['id'] !== $id ){
                $this->set_param($options[$idx]['id'], ['order' => $idx]);
              }
            }
          }
          else{
            if ( $idx > ($pos - 1) ){
              while ( $idx > ($pos - 1) ){
                if ( $options[$idx-1]['id'] !== $id ){
                  $this->set_param($options[$idx - 1]['id'], ['order' => $idx + 1]);
                }
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
    if ( \bbn\str::is_integer($id, $id_parent) ){
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

  public function map($f, $id, $deep = false){
    $opts = is_array($id) ? $id : ( $deep ? $this->full_tree($id) : $this->full_options($id) );
    if ( is_array($opts) ){
      foreach ( $opts as $i => $o ){
        $opts[$i] = $f($o);
        if ( $deep && !empty($o['num_children']) ){
          $this->map($f, $opts[$i], 1);
        }
      }
    }
    return $opts;
  }

  public function apply($f, $id, $deep = false){
    $originals = is_array($id) ? $id : ( $deep ? $this->full_tree($id) : $this->full_options($id) );
    $opts = $this->map($f, $originals, $deep);
    if ( is_array($opts) ){
      $changes = 0;
      foreach ( $opts as $i => $o ){
        if ( $originals[$i] !== $o ){
          $changes += (int)$this->set($o['id'], $o);
        }
        if ( $deep && $o['num_children'] ){
          $this->apply($f, $o, 1);
        }
      }
      return $changes;
    }
    return false;
  }

  public function has_id(){
    $args = func_get_args();
    if ( $this->from_code($args) ){
      return true;
    }
    if ( is_string($args[0]) && (count($args) > 1) ){
      array_shift($args);
      if ( $this->from_code($args) ){
        return true;
      }
    }
    return false;
  }

  public function get_id_or_create(){
    $args = func_get_args();
    // If the ID doesn't exist yet
    if ( !($id = $this->from_code($args)) ){
      // check there is a first argument with code and other(s)
      if ( is_string($args[0]) && (count($args) > 1) ){
        // Use the code for creation
        $code = array_shift($args);
        // If the rest of the arguments correspond to an option it will create a new one with this ID as parent
        if ( $id = $this->from_code($args) ){
          $this->add([
            'code' => $code,
            'text' => $code,
            'id_parent' => $id
          ]);
          // After adding it we should be able to retrieve the ID
          $id = $this->from_code(func_get_args());
        }
      }
    }
    return $id;
  }

  public function soptions(){
    $r = [];
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ) {
      foreach ( $id as $i => $txt ){
        $o = $this->options($i);
        if ( is_array($o) ){
          $r = \bbn\x::merge_arrays($r, $o);
        }
      }
    }
    return $r;
  }

  public function full_soptions(){
    $r = [];
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ){
      if ( $ids = $this->options($this->from_code($id)) ){
        foreach ( $ids as $id => $txt ){
          $o = $this->full_options($id);
          if ( is_array($o) ){
            $r = \bbn\x::merge_arrays($r, $o);
          }
        }
      }
    }
    return $r;
  }

  public function native_soptions(){
    $r = [];
    $id = $this->from_code(func_get_args());
    if ( \bbn\str::is_integer($id) ){
      if ( $ids = $this->options($this->from_code($id)) ){
        foreach ( $ids as $id => $txt ){
          $r = \bbn\x::merge_arrays($r, $this->native_options($id));
        }
      }
    }
    return $r;
  }

  public function export($id, $deep = false, $return = false){
    if ( ($ret = $deep ? $this->native_tree($id) : $this->native_option($id)) ){
      return $return ? $ret : var_export($ret, 1);
    }
    return false;
  }

  public function import(array $option, $id_parent = false, $force = false){
    $option['id_parent'] = $id_parent ?: $this->default;
    $res = 0;
    $items = empty($option['items']) ? false : $option['items'];
    unset($option['id']);
    unset($option['items']);
    $res += (int)$this->add($option, $force);
    if ( $items ){
      $id = $this->db->last_id();
      foreach ( $items as $it ){
        $res += (int)$this->import($it, $id, $force);
      }
    }
    return $res;
  }

  /**
   * Retourne la liste des options d'une catégorie indexée sur leur `id` sous la forme d'un tableau text/value
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array La liste des options dans un tableau text/value
   */
  public function text_value_options($cat = null, $text = 'text', $id = 'value'){
    $res = [];
    if ( $opts = $this->options($cat) ){
      foreach ( $opts as $k => $o ){
        array_push($res, [
          $text => $o,
          $id => $k
        ]);
      }
    }
    return $res;
  }
}
