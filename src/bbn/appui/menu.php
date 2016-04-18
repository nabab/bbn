<?php
namespace bbn\appui;

class menu {

  private static
    $_cache_prefix = 'bbn-menu-',
    $id_root,
    $id_public_root;

  protected static
    /** @var string path where the menus are */
    $root = 'bbn_menus',
    /** @var string path where the permissions and real path are */
    $public_root = 'bbn_permissions|page';

  protected
    /** @var \bbn\appui\options The options object */
    $options,
    /** @var \bbn\cache The cache object */
    $cacher;

  public function __construct(\bbn\appui\options $o){
    $this->options = $o;
    $this->cacher = \bbn\cache::get_engine();
  }

  private static function _set_root($root){
    self::$id_root = $root;
  }

  private function _get_root(){
    if ( is_null(self::$id_root) &&
      ($id = $this->options->from_path(self::$root))
    ){
      self::_set_root($id);
    }
    return self::$id_root;
  }

  private static function _set_public_root($root){
    self::$id_public_root = $root;
  }

  private function _get_public_root(){
    if ( is_null(self::$id_public_root) &&
      ($id = $this->options->from_path(self::$public_root))
    ){
      self::_set_public_root($id);
    }
    return self::$id_public_root;
  }

  private function _cache_name($method, $uid){
    return self::$_cache_prefix.$method.'-'.(string)$uid;
  }

  private function _adapt($ar, \bbn\user\preferences $pref){
    $tmp = $this->_filter($ar, $pref);
    foreach ( $tmp as $i => $it ){
      if ( !empty($it['items']) ){
        $tmp[$i]['items'] = $this->_adapt($it['items'], $pref);
      }
    }
    $res = [];
    foreach ( $tmp as $i => $it ){
      if ( !empty($it['items']) || !empty($it['id_permission']) ){
        array_push($res, $it);
      }
    }
    return $res;
  }

  private function _filter($ar, \bbn\user\preferences $pref){
    $usr = \bbn\user\connection::get_user();
    if ( is_object($usr) && $usr->is_admin() ){
      return $ar;
    }
    return array_filter($ar, function($a)use($pref){
      if ( isset($a['id_permission']) ){
        if ( !$pref->has($a['id_permission']) ){
          return false;
        }
      }
      else if ( empty($a['items']) ){
        return false;
      }
      return true;
    });
  }

  private function _arrange(array $menu){
    if ( isset($menu['text']) ){
      $res = [
        'text' => $menu['text'],
        'icon' => isset($menu['icon']) ? $menu['icon'] : 'cog'
      ];
      if ( !empty($menu['alias']) ){
        $res['id_permission'] = $menu['id_alias'];
        $res['link'] = $this->options->get_path($menu['id_alias'], $this->_get_public_root(), '');
      }
      if ( !empty($menu['items']) ){
        $res['items'] = [];
        foreach ( $menu['items'] as $m ){
          array_push($res['items'], $this->_arrange($m));
        }
      }
      return $res;
    }
    return false;
  }

  public function to_id($id){
    if ( !\bbn\str::is_integer($id) ){
      $id = $this->options->from_path(self::$root.'|'.$id);
    }
    return \bbn\str::is_integer($id) ? $id : false;
  }

  public function get($id, $prefix = ''){
    $id = $this->to_id($id);
  }

  public function tree($id){
    if ( $id = $this->to_id($id) ){
      $cn = $this->_cache_name(__FUNCTION__, $id);
      if ( $this->cacher->has($cn) ){
        return $this->cacher->get($cn);
      }
      $items = $this->options->full_tree($id);
      $res = $this->_arrange($items);
      $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
      return $res;
    }
  }

  public function custom_tree($id, \bbn\user\preferences $pref){
    if ( ($tree = $this->tree($id)) && isset($tree['items']) ){
      return $this->_adapt($tree['items'], $pref);
    }
  }

}