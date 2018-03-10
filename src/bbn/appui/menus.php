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
    $options,
    /** @var bbn\user\preferences The preferences object */
    $pref;

  public function __construct(){
    $this->options = bbn\appui\options::get_instance();
    $this->pref = bbn\user\preferences::get_instance();
    $this->cache_init();
    self::optional_init();
  }

  /**
   * @param $root
   */
  private static function _set_public_root($root){
    self::$id_public_root = $root;
  }

  private function _get_public_root(){
    if ( \is_null(self::$id_public_root) &&
      ($id = $this->options->from_path(self::$public_root, '|', BBN_APPUI))
    ){
      self::_set_public_root($id);
    }
    return self::$id_public_root;
  }

  private function _set_relative_public_root($rel_path){
    self::$public_root = self::$public_root . '|' . $rel_path;
  }

  private function _adapt($ar, $prepath = false){
    $tmp = $this->_filter($ar, $this->pref);
    foreach ( $tmp as $i => $it ){
      if ( !empty($it['items']) ){
        $tmp[$i]['items'] = $this->_adapt($it['items'], $prepath);
      }
    }
    $res = [];
    foreach ( $tmp as $i => $it ){
      if ( !empty($it['items']) || !empty($it['id_permission']) ){
        $res[] = $it;
      }
    }
    return $res;
  }

  private function _filter($ar){
    $usr = bbn\user::get_instance();
    if ( \is_object($usr) && $usr->is_dev() ){
      return $ar;
    }
    $pref = $this->pref;
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
          $res['link'] = substr($res['link'], \strlen($prepath));
        }
      }
      if ( !empty($menu['items']) ){
        $res['items'] = [];
        foreach ( $menu['items'] as $m ){
          $res['items'][] = $this->_arrange($m, $prepath);
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
    if ( !bbn\str::is_uid($path) ){
      $path = $this->options->from_code($path, self::$option_root_id);
    }
    return bbn\str::is_uid($path) ? $path : false;
  }

  /**
   * Returns the path corresponding to an ID
   * @param $id
   * @return int|boolean
   */
  public function to_path($id){
    if ( bbn\str::is_uid($id) ){
      return $this->options->to_path($id, '', $this->_get_public_root());
    }
    return false;
  }

  public function get($id, $prefix = ''){
    $id = $this->from_path($id);
  }

  public function add_shortcut($id){
    if ( $id_menu = $this->from_path('shortcuts') ){
      return $this->pref->add_link($id, $id_menu);
    }
  }

  public function remove_shortcut($id){
    if ( $id_menu = $this->from_path('shortcuts') ){
      $pref = $this->pref->get($id);
      // Case where the shortcut is coming directly from the menu
      if ( $id_menu === $pref['id_option'] ){
        return $this->pref->delete($id);
      }
      // Otherwise it comes from the permissions
      else{
        return $this->pref->set_link($id);
      }
    }
  }

  public function tree($code, $prepath = false){
    if ( $id = $this->options->from_code($code, 'menus', self::$option_root_id) ){
      if ( $this->cache_has($id, __FUNCTION__) ){
        return $this->cache_get($id, __FUNCTION__);
      }
      $tree = $this->options->full_tree($id);
      $res = $this->_arrange($tree, $prepath);
      $this->cache_set($id, __FUNCTION__, $res['items']);
      return $res['items'];
    }
  }

  public function custom_tree($id, $prepath = false){
    if ( $tree = $this->tree($id, $prepath) ){
      return $this->_adapt($tree, $this->pref, $prepath);
    }
  }

  public function shortcuts(){
    if ( $id_menu = $this->from_path('shortcuts') ){
      $links = $this->pref->get_links($id_menu);
      $res = [];
      foreach ( $links as $link ){
        if ( ($o = $this->options->option($link['id_option'])) &&
          ($url = $this->to_path($o['id_alias']))
        ){
          $res[] = [
            'id' => $link['id'],
            'id_option' => $link['id_option'],
            'url' => $url,
            'text' => $o['text'],
            'icon' => $o['icon']
          ];
        }
      }
      return $res;
    }
  }

  public function get_id_menu($id): ?string
  {
    $parents = $this->options->parents($id);
    $root = $this->get_option_root();
    foreach ( $parents as $i => $val ){
      if ( $val === $root ){
        return $parents[$i - 2];
      }
    }
    return null;
  }

  /**
   * Delete  chache for menu
   * @param $id
   * @return int|boolean
   */
  public function remove(string $id){
    if( $id_menu = $this->get_id_menu($id) ){
      $this->options->remove($id);
      $this->delete_cache($id_menu);
      return true;
    }
    return false;
  }

  /**
   * Add menu and delete  chache for menu
   * @param string $id_parent
   * @param array $cfg
   * @return null|string
   * @internal param $id
   */
  public function add(string $id_parent, array $cfg): ?string
  {
    if( $id_menu = $this->get_id_menu($id_parent) ){
      $cfg['id_parent'] = $id_parent;
      if ( $res = $this->options->add($cfg) ){
        $this->delete_cache($id_menu);
      }
      return $res;
    }
    return null;
  }

  /**
   * Set menu and delete chache for menu
   * @param string $id
   * @param array $cfg
   * @return bool|int
   */
  public function set(string $id, array $cfg){
    if ( $id_menu = $this->get_id_menu($id) ){
      if ( $res = $this->options->set($id, $cfg) ){
        $this->delete_cache($id_menu);
      }
      return $res;
    }
  }
  /**
   * Set text and delete  chache for menu
   * @param string $id
   * @param array $cfg
   * @return bool|int
   */
  public function set_text(string $id, string $cfg)
  {
    if( $id_menu = $this->get_id_menu($id) ){
      if ( $res = $this->options->set_text($id, $cfg) ){
        $this->delete_cache($id_menu);
      }
      return $res;
    }
  }

  public function get_options_menus(){
    $items = $this->options->full_options('menus', self::$option_root_id);
    $res = [];
    foreach ( $items as $it ){
      $res[] = [
        'text' => $it['text'],
        'value' => $it['id']
      ];
    }
    return $res;
  }

  public function delete_cache($id_menu){
    //die(\bbn\x::dump($this->options->text($id_menu)));
    $this->options->delete_cache($id_menu, true);
    return $this->cache_delete($id_menu);
  }


}
