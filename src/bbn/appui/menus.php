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
      ($id = $this->options->from_path(self::$public_root, '|', $this->options->from_code('appui')))
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
    if ( isset($menu['text'], $menu['id']) ){
      $res = [
        'id' => $menu['id'],
        'text' => $menu['text'],
        'icon' => $menu['icon'] ?? 'nf nf-fa-cog'
      ];
      if ( !empty($menu['id_option']) ){
        $opt = $this->options->option($menu['id_option']);
        $res['public'] = !empty($opt['public']) ? 1 : 0;
        $res['id_permission'] = $menu['id_option'];
        $res['link'] = $this->options->to_path($menu['id_option'], '', $this->_get_public_root());
        if ( $prepath && (strpos($res['link'], $prepath) === 0) ){
          $res['link'] = substr($res['link'], \strlen($prepath));
        }
        if ( !empty($menu['argument']) ){
          $res['link'] .= (substr($menu['argument'], 0, 1) === '/' ? '' : '/').$menu['argument'];
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
   *
   */
  private function _clone(string $id_menu, array $bits, string $id_parent = null){
    $c = $this->pref->get_class_cfg();
    if ( \bbn\str::is_uid($id_menu) ){
      foreach ( $bits as $bit ){
        unset($bit[$c['arch']['user_options_bits']['id']]);
        $bit[$c['arch']['user_options_bits']['id_user_option']] = $id_menu;
        $bit[$c['arch']['user_options_bits']['id_parent']] = $id_parent;
        if (
          !($id = $this->add($id_menu, $bit)) ||
          (!empty($bit['items']) && !$this->_clone($id_menu, $bit['items'], $id))
        ){
          return false;
        }
      }
      return true;
    }
    return false;
  }

  /**
   *
   *
   * @param string $path
   * @return bool|false|int
   */
  public function from_path(string $path){
    if ( !bbn\str::is_uid($path) ){
      //$path = $this->options->from_code($path, self::$option_root_id);
      $path = self::get_appui_option_id($path);
    }
    return bbn\str::is_uid($path) ? $path : false;
  }

  /**
   * Returns the path corresponding to an ID
   *
   * @param string $id
   * @return int|boolean
   */
  public function to_path(string $id){
    if ( bbn\str::is_uid($id) ){
      return $this->options->to_path($id, '', $this->_get_public_root());
    }
    return false;
  }

  public function tree($id, $prepath = false){
    if ( \bbn\str::is_uid($id) ){
      if ( $this->cache_has($id, __FUNCTION__) ){
        return $this->cache_get($id, __FUNCTION__);
      }
      $tree = $this->pref->get_tree($id);
      $res = $this->_arrange($tree, $prepath);
      $this->cache_set($id, __FUNCTION__, $res['items'] ?? []);
      return $res['items'] ?? [];
    }
  }

  public function custom_tree($id, $prepath = false){
    if ( $tree = $this->tree($id, $prepath) ){
      return $this->_adapt($tree, $this->pref, $prepath);
    }
  }

  /**
   * Adds an user'shortcut from a menu
   *
   * @param string $id The menu item's ID to link
   * @return string|null
   */
  public function add_shortcut(string $id): ?string
  {
    if (
      ($bit = $this->pref->get_bit($id, false)) &&
      ($id_option = $this->from_path('shortcuts')) &&
      ($c = $this->pref->get_class_cfg())
    ){
      if ( $id_menu = $this->pref->get_by_option($id_option) ){
        $id_menu = $id_menu[$c['arch']['user_options']['id']];
      }
      else {
        $id_menu = $this->pref->add($id_option, [$c['arch']['user_options']['text'] => _('Shortcuts')]);
      }
      if (
        !empty($id_menu) &&
        ($arch = $c['arch']['user_options_bits'])
      ){
        if (
          ($bits = $this->pref->get_bits($id_menu, false, false)) &&
          ( \bbn\x::find($bits, [$arch['id_option'] => $bit[$arch['id_option']]]) !== false)
        ){
          return null;
        }
        return $this->pref->add_bit($id_menu, [
          $arch['id_option'] => $bit[$arch['id_option']],
          $arch['text'] => $bit[$arch['text']],
          $arch['cfg'] => $bit[$arch['cfg']],
          $arch['num'] => $this->pref->next_bit_num($id_menu) ?: 1
        ]);
      }
    }
    return null;
  }

  /**
   * Removes an user'shortcut
   *
   * @param string $id The shortcut's ID
   * @return null|int
   */
  public function remove_shortcut($id): ?int
  {
    if ( \bbn\str::is_uid($id) ){
      return $this->pref->delete_bit($id);
    }
    return null;
  }

  /**
   * Gets the user' shortcuts list
   *
   * @return null|array
   */
  public function shortcuts(): ?array
  {
    if (
      ($id_option = $this->from_path('shortcuts')) &&
      ($menu = $this->pref->get_by_option($id_option))
    ){
      $links = $this->pref->get_bits($menu['id']);
      $res = [];
      foreach ( $links as $link ){
        if ( ($url = $this->to_path($link['id_option'])) ){
          $res[] = [
            'id' => $link['id'],
            'id_option' => $link['id_option'],
            'url' => $url,
            'text' => $link['text'],
            'icon' => $link['icon'],
            'num' => $link['num']
          ];
        }
      }
      return $res;
    }
    return null;
  }

  /**
   * Returns the menu's ID form an its item
   *
   * @param string $id The ID of the menu's item
   * @return null|string
   */
  public function get_id_menu(string $id): ?string
  {
    return $this->pref->get_id_by_bit($id);
  }

  /**
   * Removes menu and deletes parent cache
   * @param $id
   * @return int|boolean
   */
  public function remove(string $id){
    if ( \bbn\str::is_uid($id) ){
      if ( $id_menu = $this->get_id_menu($id) ){
        if ( $this->pref->delete_bit($id) ){
          $this->delete_cache($id_menu);
          return true;
        }
      }
      else if ( $this->pref->delete($id) ){
        $this->options->delete_cache($this->from_path('menus'));
        return true;
      }
    }
    return false;
  }

  /**
   * Add menu and delete the chache.
   *
   * @param string|array $id_parent
   * @param array $cfg
   * @return null|string
   * @internal param $id
   */
  public function add($id_menu, array $cfg = null): ?string
  {
    $ids = [];
    if ( \is_array($id_menu) ){
      $cfg = $id_menu;
      $id_opt = $this->from_path('menus');      
    }
    if ( !empty($cfg) ){
      if ( \bbn\str::is_uid($id_menu) ){
        $id = $this->pref->add_bit($id_menu, $cfg);
      }
      else {
        $id = $this->pref->add($id_opt, $cfg);
      }
    }
    if ( !empty($id) ){
      if ( \bbn\str::is_uid($id_menu) ){
        $this->delete_cache($id_menu);
      }
      $this->options->delete_cache($id_opt);
      return $id;
    }
    return null;
  }

  /**
   * Updates a menu item and deletes the menu cache
   *
   * @param string $id
   * @param array $cfg
   * @return bool
   */
  public function set(string $id, array $cfg): bool
  {
    if (
      \bbn\str::is_uid($id) &&
      ($id_menu = $this->get_id_menu($id)) &&
      $this->pref->update_bit($id, $cfg)
    ){
      $this->delete_cache($id_menu);
      return true;
    }
    return false;
  }

  /**
   * Sets the menu's text and deletes its chache
   *
   * @param string $id The menu's ID
   * @param array $text The new text tp set
   * @return bool
   */
  public function set_text(string $id, string $text): bool
  {
    if ( \bbn\str::is_uid($id) && $this->pref->set_text($id, $text) ){
      $this->delete_cache($id);
      return true;
    }
    return false;
  }

  /**
   * Clears the menu cache
   */
  public function delete_cache($id_menu){
    $this->options->delete_cache($this->from_path('menus'), true);
    return $this->cache_delete($id_menu);
  }

  /**
   * Gets the user's default menu
   *
   * @return string
   */
  public function get_default(): ?string
  {
    if (
      ($id_opt = $this->from_path('default')) &&
      ($all = $this->pref->get_all($id_opt))
    ){
      $id = false;
      foreach ( $all as $a ){
        if ( !empty($a['id_user']) ){
          $id = $a['id_alias'];
          break;
        }
        else if ( !empty($a['id_group']) ){
          $id = $a['id_alias'];
          break;
        }
        else if ( !empty($a['public']) ){
          $id = $a['id_alias'];
          break;
        }
      }
      return $id;
    }
	return null;
  }

  /**
   * Gets the user's menus list (text-value form)
   *
   * @param string $k_text The key used for the text. Default: 'text'
   * @param string $k_value The key used for the value. Default 'value'
   * @return array
   */
  public function get_menus($k_text = 'text', $k_value = 'value'): ?array
  {
    $c = $this->pref->get_class_cfg();
    return array_map(function($e) use($c, $k_text, $k_value){
      return [
        $k_text => $e[$c['arch']['user_options']['text']],
        $k_value => $e[$c['arch']['user_options']['id']],
        $c['arch']['user_options']['public'] => $e[$c['arch']['user_options']['public']],
        $c['arch']['user_options']['id_user'] => $e[$c['arch']['user_options']['id_user']],
        $c['arch']['user_options']['id_group'] => $e[$c['arch']['user_options']['id_group']]
      ];
    }, $this->pref->get_all(self::get_appui_option_id('menus')));
  }



  /**
   * Clones a menu
   *
   * @param string $id The menu's ID to clone
   * @param string $name The new menu's name
   * @return null|string The new ID
   */
  public function clone(string $id, string $name): ?string
  {
    if ( \bbn\str::is_uid($id) && ($id_menu = $this->add(['text' => $name])) ){
      if ( ($bits = $this->pref->get_full_bits($id)) && !$this->_clone($id_menu, $bits) ){
        return null;
      }
      return $id_menu;
    }
    return null;
  }


  /**
   * Copies a menu into another one.
   *
   * @param string $id The menu's ID to copy
   * @param string $id_menu_to The target menu's ID
   * @param array $cfg
   * @return null|string The new ID
   */

  public function copy(string $id_menu, string $id_menu_to, array $cfg): ?string
  {
    if (
      \bbn\str::is_uid($id_menu) &&
      \bbn\str::is_uid($id_menu_to) &&
      ($bits = $this->pref->get_full_bits($id_menu)) &&
      ($id = $this->add($id_menu_to, $cfg)) &&
      $this->_clone($id_menu_to, $bits, $id)
    ){
      return $id;
    }
    return null;
  }

  /**
   * Clones a section/link to an other menu.
   *
   * @param string $id_bit The bit's ID to clone
   * @param string $id_menu_to The menu's ID to clone
   * @param string $cfgvaule of bit
   * @return null|string The new ID
   */

  public function copy_to(string $id_bit, string $id_menu_to, array $cfg): ?string
  {
    if (
      \bbn\str::is_uid($id_bit) &&
      \bbn\str::is_uid($id_menu_to) &&
      ($bit = $this->pref->get_bit($id_bit)) &&
      ($id_menu = $this->get_id_menu($id_bit))
    ){
      $bit = array_merge($bit, $cfg, [
        'id_parent' => null,
        'num' => $this->pref->get_max_bit_num($id_menu_to, null, true)
      ]);
      $bits = $this->pref->get_full_bits($id_menu, $id_bit, true);
      if ( $id = $this->add($id_menu_to, $bit) ){
        if ( !empty($bits) && !$this->_clone($id_menu_to, $bits, $id) ){
          return null;
        }
        return $id;
      }
    }
    return null;
  }

  /**
   * Orders a section/link.
   *
   * @param string $id The section/link's ID
   * @param int $pos The new position.
   * @return bool
   */
  public function order(string $id, int $pos): bool
  {
    if ( $res = $this->pref->order_bit($id, $pos) ){
      $this->delete_cache($this->get_id_menu($id));
      return true;
    }
    return false;
  }

  /**
   * Moves a section/link inside to another one.
   *
   * @param string $id The section/link's ID.
   * @param string|null $id_parent The parent's ID.
   * @return bool
   */
  public function move(string $id, string $id_parent = null): bool
  {
    if ( $this->pref->move_bit($id, $id_parent) ){
      $this->delete_cache($this->get_id_menu($id));
      return true;
    }
    return false;
  }




  public function get_options_menus(){
    //$items = $this->options->full_options('menus', self::$option_root_id);
    $items = self::get_appui_option('menus');
    $res = [];
    foreach ( $items as $it ){
      $res[] = [
        'text' => $it['text'],
        'value' => $it['id']
      ];
    }
    return $res;
  }



  public function get($id, $prefix = ''){
    $id = $this->from_path($id);
  }


}
