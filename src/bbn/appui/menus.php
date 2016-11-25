<?php
namespace bbn\appui;
use bbn;

class menus extends bbn\models\cls\basic{

  use
    bbn\models\tts\cache,
    bbn\models\tts\optional;

  private static
    /** @var int The ID of the option's for the root path / */
    $id_public_root;

  protected static
    /** @var string path where the permissions and real path are */
    $public_root = 'permissions|page';

  protected
    /** @var bbn\appui\options The options object */
    $options;

  public function __construct(bbn\appui\options $o, $r){
    $this->options = $o;
    $this->cache_init();
    self::optional_init($this);
  }

  /**
   * @param $root
   */
  private static function _set_public_root($root){
    self::$id_public_root = $root;
  }

  private function _get_public_root(){
    if ( is_null(self::$id_public_root) &&
      ($id = $this->options->from_path(self::$public_root, '|', BBN_APPUI))
    ){
      self::_set_public_root($id);
    }
    return self::$id_public_root;
  }

  private function _set_relative_public_root($rel_path){
    self::$public_root = self::$public_root . '|' . $rel_path;
  }

  private function _adapt($ar, bbn\user\preferences $pref, $prepath = false){
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

  private function _filter($ar, bbn\user\preferences $pref){
    $usr = bbn\user::get_instance();
    if ( is_object($usr) && $usr->is_admin() ){
      return $ar;
    }
    return array_filter($ar, function($a)use($pref){
      if ( !empty($a['public']) ){
        return true;
      }
      if ( isset($a['id_permission']) && $pref->has($a['id_permission'], $pref->get_user(), $pref->get_group()) ){
        return true;
      }
      if ( !isset($a['id_permission']) && !empty($a['items']) ){
        return true;
      }
      return false;
    });
  }

  private function _arrange(array $menu, $prepath = false){
    if ( isset($menu['text']) ){
      $res = [
        'id' => $menu['id'],
        'text' => $menu['text'],
        'public' => isset($menu['alias']) && !empty($menu['alias']['public']) ? 1 : 0,
        'icon' => isset($menu['icon']) ? $menu['icon'] : 'cog'
      ];
      if ( !empty($menu['id_alias']) ){
        $res['id_permission'] = $menu['id_alias'];
        $res['link'] = $this->options->to_path($menu['id_alias'], '', $this->_get_public_root());
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

  /**
   * @param $path
   * @return bool|false|int
   */
  public function from_path($path){
    if ( !bbn\str::is_integer($path) ){
      $path = $this->options->from_code($path, self::$option_root_id);
    }
    return bbn\str::is_integer($path) ? $path : false;
  }

  /**
   * Returns the path corresponding to an ID
   * @param $id
   * @return int|boolean
   */
  public function to_path($id){
    if ( bbn\str::is_integer($id) ){
      return $this->options->to_path($id, '', $this->_get_public_root());
    }
    return false;
  }

  public function get($id, $prefix = ''){
    $id = $this->from_path($id);
  }

  public function add_shortcut($id, bbn\user\preferences $pref){
    if ( $id_menu = $this->from_path('shortcuts') ){
      return $pref->set_link($id, $id_menu);
    }
  }

  public function remove_shortcut($id, bbn\user\preferences $pref){
    if ( $id_menu = $this->from_path('shortcuts') ){
      return $pref->unset_link($id);
    }
  }

  public function tree($id, $prepath = false){
    if ( $id = $this->from_path($id) ){
      if ( $this->cache_has($id, __FUNCTION__) ){
        return $this->cache_get($id, __FUNCTION__);
      }
      $tree = $this->options->full_tree($id);
      $res = $this->_arrange($tree, $prepath);
      $this->cache_set($id, __FUNCTION__, $res['items']);
      return $res['items'];
    }
  }

  public function custom_tree($id, bbn\user\preferences $pref, $prepath = false){
    if ( $tree = $this->tree($id, $prepath) ){
      return $this->_adapt($tree, $pref, $prepath);
    }
  }
  
  public function shortcuts(bbn\user\preferences $pref){
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