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

  public function __construct(\bbn\appui\options $o, $r){
    $this->options = $o;
    $this->cacher = \bbn\cache::get_engine();
    if ( !empty($r) && is_string($r) ){
      $this->_set_relative_public_root($r);
    }
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

  private function _set_relative_public_root($rel_path){
    self::$public_root = self::$public_root . '|' . $rel_path;
  }

  private function _cache_name($method, $uid){
    return self::$_cache_prefix.$method.'-'.(string)$uid;
  }

  private function _adapt($ar, \bbn\user\preferences $pref, $prepath = false){
    $tmp = $this->_filter($ar, $pref);
    foreach ( $tmp as $i => $it ){
      if ( !empty($it['items']) ){
        $tmp[$i]['items'] = $this->_adapt($it['items'], $pref, $prepath);
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
        if ( !$pref->has($a['id_permission'], $pref->get_user(), $pref->get_group()) ){
          return false;
        }
      }
      else if ( empty($a['items']) ){
        return false;
      }
      return true;
    });
  }

  private function _arrange(array $menu, $prepath = false){
    if ( isset($menu['text']) ){
      $res = [
        'id' => $menu['id'],
        'text' => $menu['text'],
        'icon' => isset($menu['icon']) ? $menu['icon'] : 'cog'
      ];
      if ( !empty($menu['alias']) ){
        $res['id_permission'] = $menu['id_alias'];
        $res['link'] = $this->options->get_path($menu['id_alias'], $this->_get_public_root(), '');
        if ( $prepath && (strpos($res['link'], $prepath) === 0) ){
          $res['link'] = substr($res['link'], strlen($prepath));
        }
      }
      if ( !empty($menu['items']) ){
        $res['items'] = [];
        foreach ( $menu['items'] as $m ){
          array_push($res['items'], $this->_arrange($m, $prepath));
        }
      }
      return $res;
    }
    return false;
  }

  public function from_path($path){
    if ( !\bbn\str::is_integer($path) ){
      $path = $this->options->from_path(self::$root.'|'.$path);
    }
    return \bbn\str::is_integer($path) ? $path : false;
  }

  /**
   * Returns the path corresponding to an ID
   * @param $id
   */
  public function to_path($id){
    if ( \bbn\str::is_integer($id) ){
      return $this->options->to_path($id, '', $this->_get_public_root());
    }
    return false;
  }

  public function get($id, $prefix = ''){
    $id = $this->from_path($id);
  }

  public function add_shortcut($id, \bbn\user\preferences $pref){
    if ( $id_menu = $this->from_path('shortcuts') ){
      return $pref->set_link($id, $id_menu);
    }
  }

  public function remove_shortcut($id, \bbn\user\preferences $pref){
    if ( $id_menu = $this->from_path('shortcuts') ){
      return $pref->unset_link($id);
    }
  }

  public function tree($id, $prepath = false){
    if ( $id = $this->from_path($id) ){
      $cn = $this->_cache_name(__FUNCTION__, $id);
      if ( $this->cacher->has($cn) ){
        return $this->cacher->get($cn);
      }
      $items = $this->options->full_tree($id);
      $res = $this->_arrange($items, $prepath);
      $this->cacher->set($this->_cache_name(__FUNCTION__, $id), $res);
      return $res;
    }
  }

  public function custom_tree($id, \bbn\user\preferences $pref, $prepath = false){
    if ( ($tree = $this->tree($id, $prepath)) && isset($tree['items']) ){
      return $this->_adapt($tree['items'], $pref, $prepath);
    }
  }
  
  public function shortcuts(\bbn\user\preferences $pref){
    if ( $id_menu = $this->from_path('shortcuts') ){
      $ids = $pref->get_links($id_menu);
      $res = [];
      foreach ( $ids as $id ){
        if ( ($o = $this->options->option($id)) &&
          ($url = $this->to_path($o['id_alias']))
        ){
          array_push($res, [
            'id' => $id,
            'url' => $url,
            'text' => $o['text'],
            'icon' => $o['icon']
          ]);
        }
      }
      return $res;
    }
  }

}