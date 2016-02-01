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
        'cfg' => 'cfg',
        'active' => 'active'
      ]
    ];

  protected
    /** @var \bbn\db\connection The database connection */
    $db,
    /** @var \bbn\cache The cache object */
    $cacher,
    /** @var int The default root ID of the options in the table */
    $default = 0;

  private function _cache_name($method, $uid){
    return 'bbn-options-'.$method.'-'.$uid;
  }

  private function _cache_delete($id, $parents = true, $deep = false){
    $this->cacher->delete($this->_cache_name('option', $id), $id);
    $this->cacher->delete($this->_cache_name('native_option', $id), $id);
    $this->cacher->delete($this->_cache_name('options', $id), $id);
    $this->cacher->delete($this->_cache_name('full_options', $id), $id);
    $this->cacher->delete($this->_cache_name('native_options', $id), $id);
    $this->cacher->delete($this->_cache_name('native_soptions', $id), $id);
    $this->cacher->delete($this->_cache_name('soptions', $id), $id);
    $this->cacher->delete($this->_cache_name('native_tree', $id), $id);
    $this->cacher->delete($this->_cache_name('full_tree', $id), $id);
    $this->cacher->delete($this->_cache_name('tree', $id), $id);
    if ( $parents ){
      $parents = $this->parents($id);
      foreach ( $parents as $i => $p ){
        if ( $i === 0 ){
          $this->cacher->delete($this->_cache_name('options', $p), $p);
          $this->cacher->delete($this->_cache_name('full_options', $p), $p);
          $this->cacher->delete($this->_cache_name('native_options', $p), $p);
          $this->cacher->delete($this->_cache_name('native_soptions', $p), $p);
          $this->cacher->delete($this->_cache_name('soptions', $p), $p);
        }
        $this->cacher->delete($this->_cache_name('native_tree', $p), $p);
        $this->cacher->delete($this->_cache_name('full_tree', $p), $p);
        $this->cacher->delete($this->_cache_name('tree', $p), $p);
      }
    }
    if ( $deep ){
      $items = $this->items($id);
      foreach ( $items as $item ){
        $this->_cache_delete($item, false, 1);
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
    $tab = $db->tsn($this->cfg['table']);
    $cols = [];
    if ( !empty($where) ){
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
          ORDER BY text";
        $args = array_values($where);
        if ( class_exists('\\bbn\\appui\\history') && \bbn\appui\history::is_enabled() ){
          array_push($args, 1);
        }
        return $this->db->get_rows($q, $args);
      }
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
   * @param \bbn\db\connection $db
   * @param array $cfg
   */
  public function __construct(\bbn\db\connection $db, array $cfg=[]){
    $this->db = $db;
    $this->cfg = \bbn\tools::merge_arrays(self::$_defaults, $cfg);
    $this->cacher = \bbn\cache::get_engine();
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
    if ( \bbn\str\text::is_integer($args[0]) ){
      return $args[0];
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

  /**
   * @param int $id option's ID
   * @param string $prop Name of the property to fetch
   * @param bool $false Sets if function returns false (default) or null in case of not found
   * @return mixed
   */
  public function get_prop($id, $prop, $false = true){
    if ( \bbn\str\text::is_integer($id) ){
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
    return $this->db->get_column_values($this->cfg['table'], $this->cfg['cols']['id'], [
      $this->cfg['cols']['id_parent'] => $id
    ]);
  }

  public function native_option($id){
    if ( $id = $this->from_code(func_get_args()) ){
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
    if ( $id = $this->from_code(func_get_args()) ) {
      if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
        return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
      }
      if ( $opt = $this->native_option($id) ) {
        $this->get_value($opt);
        $this->get_cfg($opt);
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
   * @param string|int $id La catégorie, sous la forme de son `id`, ou de son nom
   * @return array La liste des options indexée sur leur `id`
   */
  public function options($id = 0){
    $args = func_get_args();
    if ( ($id = $this->from_code(empty($args) ? $id : $args)) !== false ) {
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
    $id = $this->from_code(empty($args) ? $id : $args);
    if ( \bbn\str\text::is_integer($id) ) {
      return $this->db->count($this->cfg['table'], [$this->cfg['cols']['id_parent'] => $id]);
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
    $opts = $this->native_options($id, $id_parent, $where = [], $order = [], $start, $limit);
    if ( is_array($opts) ){
      $res = [];
      foreach ($opts as $i => $o) {
        $this->get_value($opts[$i]);
        $this->get_cfg($opts[$i]);
        $res[$o['id']] = $opts[$i];
      }
      if ( $this->get_param($id, 'orderable') ) {
        \bbn\tools::sort_by($res, ['cfg', 'order']);
      }
      $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
      return $res;
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
    $id = $this->from_code($id, $id_parent ? $id_parent : $this->default);
    if ( \bbn\str\text::is_integer($id, $start) ) {
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

  public function tree($id, $id_parent = false, $length = 128){
    $length--;
    if ( $length >= 0 ){
      $id = $this->from_code($id, $id_parent ? $id_parent : $this->default);
      if ( \bbn\str\text::is_integer($id) && ($text = $this->text($id)) ) {
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
            if ($t = $this->tree($o, $length)) {
              array_push($res['items'], $t);
            }
          }
        }
        else if ( $this->text->code($id) === 'bbn_options' ){
          $res['items'] = $this->options();
        }
        $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
        return $res;
      }
    }
    return false;
  }

  public function full_tree($id, $id_parent = false, $length=128){
    $length--;
    if ( $length >= 0 ) {
      $id = $this->from_code($id, $id_parent ? $id_parent : $this->default);
      if (\bbn\str\text::is_integer($id) && ($text = $this->text($id))) {
        if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
          return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
        }
        $c = $this->cfg['cols'];
        $res = $this->db->rselect($this->cfg['table'], array_values($c), [
          $c['id'] => $id
        ]);
        if ($res) {
          $this->get_value($res);
          $this->get_cfg($res);
          $res['items'] = [];
          if ($opts = $this->db->get_column_values(
            $this->cfg['table'],
            $c['id'],
            [$c['id_parent'] => $id],
            [$c['text'] => 'ASC'])
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
          else if ( ($res['code'] === 'bbn_options') &&
            ($res['items'] = $this->full_options())
          ){
            if ( defined('BBN_OPTIONS_URL') ){
              array_walk($res['items'], function(&$a){
                $a['link'] = BBN_OPTIONS_URL.$a['id'];
              });
            }
          }
          else{
            unset($res['items']);
          }
          $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
          return $res;
        }
      }
    }
    return false;
  }

  public function native_tree($id, $id_parent = false, $length=128){
    $length--;
    if ( $length >= 0 ) {
      $id = $this->from_code($id, $id_parent ? $id_parent : $this->default);
      if ( \bbn\str\text::is_integer($id) ) {
        if ( $this->cacher->has($this->_cache_name(__FUNCTION__, $id)) ){
          return $this->cacher->get($this->_cache_name(__FUNCTION__, $id));
        }
        $c = $this->cfg['cols'];
        if ( $res = $this->native_option($id) ) {
          $its = $this->items($id);
          if ( count($its) ){
            $res['items'] = [];
            foreach ( $its as $it ){
              array_push($res['items'], $this->native_tree($it, $id, $length));
            }
          }
          $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
          return $res;
        }
      }
      return false;
    }
    die("Exhausted length of $length in tree function");
  }

  private function _prepare(&$it, $with_items=false){
    $c = $this->cfg['cols'];
    if ( !isset($it[$c['id_parent']]) ){
      $it[$c['id_parent']] = $this->default;
    }
    if ( isset($it[$c['id_parent']], $it[$c['text']]) ){
      if ( isset($it[$c['value']]) &&
        \bbn\str\text::is_json($it[$c['value']])
      ){
        $it[$c['value']] = json_decode($it[$c['value']], 1);
      }
      if ( empty($it[$c['value']]) ){
        $it[$c['value']] = [];
      }
      if ( isset($it['num_children']) ){
        unset($it['num_children']);
      }
      if ( !$with_items && isset($it['items']) ){
        unset($it['items']);
      }
      foreach ( $it as $k => $v ){
        if ( !in_array($k, $c) ){
          $it[$c['value']][$k] = \bbn\str\text::is_json($v) ? json_decode($v, 1) : $v;
          unset($it[$k]);
        }
      }
      if ( is_array($it[$c['value']]) ){
        $it[$c['value']] = json_encode($it[$c['value']]);
      }
      if ( is_array($it[$c['cfg']]) ){
        $it[$c['cfg']] = json_encode($it[$c['cfg']]);
      }
      if ( empty($it[$c['value']]) || ($it[$c['value']] === '[]') ){
        $it[$c['value']] = '{}';
      }
      if ( empty($it[$c['cfg']]) || ($it[$c['cfg']] === '[]') ){
        $it[$c['cfg']] = '{}';
      }
      return true;
    }
    return false;
  }

  public function add($it){
    if ( $this->_prepare($it, 1) ){
      $c = $this->cfg['cols'];
      if ( $this->db->insert($this->cfg['table'], [
        $c['id_parent'] => $it[$c['id_parent']],
        $c['text'] => $it[$c['text']],
        $c['code'] => !empty($it[$c['code']]) ? $it[$c['code']] : null,
        $c['value'] => isset($it[$c['value']]) ? $it[$c['value']] : '',
        $c['cfg'] => isset($it[$c['cfg']]) ? $it[$c['cfg']] : '',
        $c['active'] => 1
      ]) ){
        $this->_cache_delete($it[$c['id_parent']]);
        $id = $this->db->last_id();
        $res = 1;
        if ( !empty($it['items']) && is_array($it['items']) ){
          foreach ( $it['items'] as $it ){
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
    if ( $this->_prepare($cfg) ){
      $c = $this->cfg['cols'];
      \bbn\tools::dump($cfg);
      if ( $res = $this->db->update($this->cfg['table'], [
        $c['text'] => $cfg[$c['text']],
        $c['code'] => !empty($cfg[$c['code']]) ? $cfg[$c['code']] : null,
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

  public function remove($id){
    if ( $id = $this->from_code(func_get_args()) ) {
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
    if ( !empty($id) && !empty($params) && ($o = $this->option($id)) ){
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
    if ( \bbn\str\text::is_integer($id) ){
      $opt = $this->option($id);
    }
    else if ( is_array($id) ){
      $opt =& $id;
    }
    if ( empty($opt) || !isset($opt['id']) ){
      return false;
    }
    if ( \bbn\str\text::is_json($opt[$this->cfg['cols']['cfg']]) ){
      $opt['cfg'] = json_decode($opt[$this->cfg['cols']['cfg']], 1);
    }
    return empty($opt['cfg']) ? [] : $opt['cfg'];
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
    if ( \bbn\str\text::is_integer($id) ){
      $opt = $this->option($id);
    }
    else if ( is_array($id) ){
      $opt =& $id;
    }
    if ( empty($opt) || !isset($opt['id']) ){
      return false;
    }
    if ( \bbn\str\text::is_json($opt[$this->cfg['cols']['cfg']]) ){
      $opt['cfg'] = json_decode($opt[$this->cfg['cols']['cfg']], 1);
    }
    return isset($opt['cfg'], $opt['cfg'][$param]) ? $opt['cfg'][$param] : ($false ? false : null);
  }

  public function unset_param($id, $param){
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
    if ( \bbn\str\text::is_integer($id) ){
      $opt = $this->option($id);
    }
    else if ( is_array($id) ){
      $opt =& $id;
    }
    if ( empty($opt) || !isset($opt['id']) ){
      return false;
    }
    if ( isset($opt[$this->cfg['cols']['value']]) && \bbn\str\text::is_json($opt[$this->cfg['cols']['value']]) ){
      $val = json_decode($opt[$this->cfg['cols']['value']], 1);
      if ( \bbn\tools::is_assoc($val) ) {
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
    if ( \bbn\str\text::is_integer($id = $this->from_code(func_get_args())) ){
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
    if ( !empty($id) && !empty($prop) && ($o = $this->option($id)) ){
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
    if ( $id = $this->from_code(func_get_args()) ){
      if ( $id_parent = $this->get_id_parent($id) ){
        return $this->option($id_parent);
      }
    }
    return false;
  }

  public function parents($id){
    if ( $id = $this->from_code(func_get_args()) ){
      $root = [false, 0, $this->default];
      $res = [];
      while ( !in_array(($id_parent = $this->get_id_parent($id)), $root) ){
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

  public function soptions(){
    $r = [];
    if ( $id = $this->options($this->from_code(func_get_args())) ){
      foreach ( $id as $i => $txt ){
        $o = $this->options($i);
        if ( is_array($o) ){
          $r = \bbn\tools::merge_arrays($r, $o);
        }
      }
    }
    return $r;
  }

  public function full_soptions(){
    $r = [];
    if ( $ids = $this->options($this->from_code(func_get_args())) ){
      foreach ( $ids as $id => $txt ){
        $o = $this->full_options($id);
        if ( is_array($o) ){
          $r = \bbn\tools::merge_arrays($r, $o);
        }
      }
    }
    return $r;
  }

  public function native_soptions(){
    $r = [];
    if ( $ids = $this->options($this->from_code(func_get_args())) ){
      foreach ( $ids as $id => $txt ){
        $r = \bbn\tools::merge_arrays($r, $this->native_options($id));
      }
    }
    return $r;
  }

  public function export($id, $deep = false){
    if ( ($ret = $deep ? $this->native_tree($id) : $this->native_option($id)) ){
      return var_export($ret, 1);
    }
    return false;
  }

  public function import(array $option, $id_parent = false){
    $option['id_parent'] = $id_parent ? $id_parent : $this->default;
    $res = 0;
    $items = empty($option['items']) ? false : $option['items'];
    unset($option['id']);
    unset($option['items']);
    $res += (int)$this->add($option);
    if ( $items ){
      $id = $this->db->last_id();
      foreach ( $items as $it ){
        $res += (int)$this->import($it, $id);
      }
    }
    return $res;
  }
}
