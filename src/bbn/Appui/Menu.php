<?php
namespace bbn\Appui;

use bbn;
use bbn\X;
use bbn\Str;

class Menu extends bbn\Models\Cls\Basic
{

  use bbn\Models\Tts\Cache;
  use bbn\Models\Tts\Optional;

  /** @var int The ID of the option's for the root path / */
  private static $id_public_root;

  /** @var string path where the permissions and real path are */
  protected static $public_root = 'permissions|access';

  /** @var bbn\Appui\Option The options object */
  protected $options;

  /** @var bbn\User\Preferences The preferences object */
  protected $pref;

  /** @var bbn\User\Permissions The permissions object */
  protected $perm;
    


  public function __construct()
  {
    $this->options = bbn\Appui\Option::getInstance();
    $this->pref    = bbn\User\Preferences::getInstance();
    $this->perm    = bbn\User\Permissions::getInstance();
    $this->cacheInit();
    self::optionalInit();
  }


  /**
   *
   *
   * @param string $path
   * @return bool|false|int
   */
  public function fromPath(string $path)
  {
    $id = null;
    if (!Str::isUid($path)) {
      //$path = $this->options->fromCode($path, self::$option_root_id);
      if (!($id = self::getOptionId($path))) {
        //X::hddump(self::getOptionId($path), self::getOptionId($path, 'options'), self::getOptionRoot());
        $id = $this->perm->fromPath($path);
      }
    }

    return Str::isUid($id) ? $id : null;
  }


  /**
   * Returns the path corresponding to an ID
   *
   * @param string $id
   * @return int|boolean
   */
  public function toPath(string $id)
  {
    $path = null;
    if (Str::isUid($id)) {
      $path = $this->perm->toPath($id);
    }

    return \is_string($path) ? $path : null;
  }


  /**
   *
   *
   * @param string $path
   * @return bool|false|int
   */
  /*
  public function fromPath(string $path): ?string
  {
    if (!Str::isUid($path)) {
      //$path = $this->options->fromCode($path, self::$option_root_id);
      $path = $this->perm->fromPath($path);
    }

    return Str::isUid($path) ? $path : null;
  }


  /**
   * Returns the path corresponding to an ID
   *
   * @param string $id
   * @return int|boolean
   */
  /*
  public function toPath(string $id)
  {
    if (Str::isUid($id)) {
      return $this->perm->toPath($id);
    }

    return false;
  }
  */


  public function tree($id, $prepath = false)
  {
    if (Str::isUid($id)) {
      if ($this->cacheHas($id, __FUNCTION__)) {
        return $this->cacheGet($id, __FUNCTION__);
      }

      $tree = $this->pref->getTree($id);
      $res  = $this->_arrange($tree, $prepath);
      $this->cacheSet($id, __FUNCTION__, $res['items'] ?? []);
      return $res['items'] ?? [];
    }
  }


  public function customTree($id, $prepath = false)
  {
    if ($tree = $this->tree($id, $prepath)) {
      return $this->_adapt($tree, $this->pref, $prepath);
    }
  }


  /**
   * Adds an user'shortcut from a menu
   *
   * @param string $id The menu item's ID to link
   * @return string|null
   */
  public function addShortcut(string $id): ?string
  {
    if (($bit = $this->pref->getBit($id, false))
        && ($id_option = $this->fromPath('shortcuts'))
        && ($c = $this->pref->getClassCfg())
    ) {
      if ($id_menu = $this->pref->getByOption($id_option)) {
        $id_menu = $id_menu[$c['arch']['user_options']['id']];
      }
      else {
        $id_menu = $this->pref->add($id_option, [$c['arch']['user_options']['text'] => X::_('Shortcuts')]);
      }

      if (!empty($id_menu)
          && ($arch = $c['arch']['user_options_bits'])
      ) {
        if (($bits = $this->pref->getBits($id_menu, false, false))
            && ( X::find($bits, [$arch['id_option'] => $bit[$arch['id_option']]]) !== null)
        ) {
          return null;
        }

        return $this->pref->addBit(
          $id_menu, [
          $arch['id_option'] => $bit[$arch['id_option']],
          $arch['text'] => $bit[$arch['text']],
          $arch['cfg'] => $bit[$arch['cfg']],
          $arch['num'] => $this->pref->nextBitNum($id_menu) ?: 1
          ]
        );
      }
    }

    return null;
  }


  /**
   * Adds an user'shortcut from a router
   *
   * @param string $url An URL to append to the permission's URL
   * @param string $text The text to show in the shortcut
   * @param string $icon The icon to show in the shortcut
   * @return string|null
   */
  public function addShortcutByUrl(string $url, string $text, string $icon): ?string
  {
    if ($info = $this->perm->fromPathInfo($url)) {
      $id_option = $this->fromPath('shortcuts');
      $c = $this->pref->getClassCfg();
      $arch = $c['arch']['user_options_bits'];
      if ($id_menu = $this->pref->getByOption($id_option)) {
        $id_menu = $id_menu['id'];
      }
      else {
        $id_menu = $this->pref->add($id_option, [$c['arch']['user_options']['text'] => X::_('Shortcuts')]);
      }

      if (!empty($id_menu)) {
        $shortcuts = $this->pref->getBits($id_menu, false, false);
        if (X::getRow($shortcuts, [
          'id_option' => $info['id'],
          'url' => $info['param']
        ])) {
          return null;
        }

        return $this->pref->addBit($id_menu, [
          $arch['id_option'] => $info['id'],
          $arch['text'] => $text,
          'url' => $info['param'],
          'icon' => $icon,
          $arch['num'] => $this->pref->nextBitNum($id_menu) ?: 1
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
  public function removeShortcut($id): ?int
  {
    if (Str::isUid($id)) {
      return $this->pref->deleteBit($id);
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
    if (($id_option = $this->fromPath('shortcuts'))
        && ($menu = $this->pref->getByOption($id_option))
    ) {
      $links = $this->pref->getBits($menu['id']);
      $res   = [];
      foreach ($links as $link){
        if (empty($link['id_option'])) {
          $this->pref->deleteBit($link['id']);
        }
        elseif ($url = $this->toPath($link['id_option'])) {
          $res[] = [
            'id' => $link['id'],
            'id_option' => $link['id_option'],
            'url' => $url . (empty($link['url']) ? '' : '/' . $link['url']),
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
  public function getIdMenu(string $id): ?string
  {
    return $this->pref->getIdByBit($id);
  }


  /**
   * Removes menu and deletes parent cache
   * @param string $id
   * @return int|boolean
   */
  public function remove(string $id)
  {
    if (Str::isUid($id)) {
      if ($id_menu = $this->getIdMenu($id)) {
        if ($this->pref->deleteBit($id)) {
          $this->deleteCache($id_menu);
          return true;
        }
      }
      elseif ($this->pref->delete($id)) {
        $this->options->deleteCache($this->fromPath('menu'));
        return true;
      }
    }

    return false;
  }


  /**
   * Add menu and delete the chache.
   *
   * @param string|array $id_menu
   * @param array        $cfg
   * @return null|string
   * @internal param $id
   */
  public function add($id_menu, array|null $cfg = null): ?string
  {
    $id_opt = $this->fromPath('menus');
    if (\is_array($id_menu)) {
      $cfg = $id_menu;
    }

    if (!empty($cfg)) {
      if (Str::isUid($id_menu)) {
        $this->deleteCache($id_menu);
        $id = $this->pref->addBit($id_menu, $cfg);
      }
      else {
        $id = $this->pref->addToGroup($id_opt, $cfg);
      }

      $this->options->deleteCache($id_opt);
      return $id;
    }

    return null;
  }


  /**
   * Updates a menu item and deletes the menu cache
   *
   * @param string $id
   * @param array  $cfg
   * @return bool
   */
  public function set(string $id, array $cfg): bool
  {
    if (Str::isUid($id)
        && ($id_menu = $this->getIdMenu($id))
        && $this->pref->updateBit($id, $cfg)
    ) {
      $this->deleteCache($id_menu);
      return true;
    }

    return false;
  }


  /**
   * Sets the menu's text and deletes its chache
   *
   * @param string $id   The menu's ID
   * @param array  $text The new text tp set
   * @return bool
   */
  public function setText(string $id, string $text): bool
  {
    if (Str::isUid($id) && $this->pref->setText($id, $text)) {
      $this->deleteCache($id);
      return true;
    }

    return false;
  }


  /**
   * Clears the menu cache
   */
  public function deleteCache($id_menu)
  {
    $this->options->deleteCache($this->fromPath('menu'), true);
    return $this->cacheDelete($id_menu);
  }


  /**
   * Gets the user's default menu
   *
   * @return string
   */
  public function getDefault(): ?string
  {
    if (($id_opt = $this->fromPath('default'))
        && ($all = $this->pref->getAll($id_opt))
    ) {
      $id = null;
      if ($by_id_user = \array_filter(
        $all, function ($a) {
          return !empty($a['id_user']) && !empty($a['id_alias']);
        }
      )
      ) {
        $id = $by_id_user[0]['id_alias'];
      }
      elseif ($by_id_group = \array_filter(
        $all, function ($a) {
          return !empty($a['id_group']) && !empty($a['id_alias']);
        }
      )
      ) {
        $id = $by_id_group[0]['id_alias'];
      }
      elseif ($by_public = \array_filter(
        $all, function ($a) {
          return !empty($a['public']) && !empty($a['id_alias']);
        }
      )
      ) {
        $id = $by_public[0]['id_alias'];
      }

      return $id;
    }

    return null;
  }


  /**
   * Gets the user's menus list (text-value form)
   *
   * @param string $k_text  The key used for the text. Default: 'text'
   * @param string $k_value The key used for the value. Default 'value'
   * @return array
   */
  public function getMenus($k_text = 'text', $k_value = 'value'): array
  {
    $c    = $this->pref->getClassCfg();
    $pref =& $this->pref;
    if (!($id_menus = self::getOptionId('menus'))) {
      throw new \Exception("Impossible to find the option for menus");
    }

    if (!($menus = $this->pref->getAll($id_menus))) {
      return [];
      throw new \Exception("Impossible to get the  menus items");
    }

    return array_map(
      function ($e) use ($c, $k_text, $k_value, $pref) {
        return [
          $k_text => $e[$c['arch']['user_options']['text']],
          $k_value => $e[$c['arch']['user_options']['id']],
          $c['arch']['user_options']['public'] => $e[$c['arch']['user_options']['public']],
          $c['arch']['user_options']['id_user'] => $e[$c['arch']['user_options']['id_user']],
          $c['arch']['user_options']['id_group'] => $e[$c['arch']['user_options']['id_group']],
          'hasItems' => (bool)count($pref->getBits($e[$c['arch']['user_options']['id']]))
          ];
      },
      $menus
    );
  }


  public function get(string $id_menu, $submenu = null): ?array
  {
    $res = $this->pref->getBits($id_menu, $submenu);
    if (\is_array($res) && !empty($res)) {
      $idPermTplId = $this->options->getPermissionsTemplateId();
      $idAccessTplId = $this->options->fromCode('access', $idPermTplId);
      $root = $this->options->getRoot();
      foreach ($res as $k => &$d) {
        $d['numChildren'] = count($this->pref->getBits($id_menu, $d['id']));
        $path = $tmp = [];
        if (!is_null($d['id_option'])) {
          $id_option = $d['id_option'];
          while ($id_option && ($id_option !== $root)) {
            array_unshift($tmp, $id_option);
            if ($o = $this->options->parent($id_option)) {
              if ($o['id_alias'] === $idAccessTplId) {
                $path = $tmp;
                break;
              }
              else {
                $id_option = $o['id'];
              }
            }
            else {
              X::log("Impossible to find the option $id_option", 'menuErrors');
              break;
            }
          }
        }

        $d['path'] = $path;
        if (!empty($d['path'][0])) {
          //array_shift($d['path']);
        }
  
        if (!$d['numChildren']
            && isset($d['id_option'])
            && ($tmp = $this->perm->toPath($d['id_option']))
        ) {
          $d['link'] = $tmp;
        }
      }
  
      unset($d);
      return $res;
    }

    return null;
  }


  /**
   * Clones a menu
   *
   * @param string $id   The menu's ID to clone
   * @param string $name The new menu's name
   * @return null|string The new ID
   */
  public function clone(string $id, string $name): ?string
  {
    if (Str::isUid($id) && ($id_menu = $this->add(['text' => $name]))) {
      if (($bits = $this->pref->getFullBits($id)) && !$this->_clone($id_menu, $bits)) {
        return null;
      }

      return $id_menu;
    }

    return null;
  }


  /**
   * Copies a menu into another one.
   *
   * @param string $id         The menu's ID to copy
   * @param string $id_menu_to The target menu's ID
   * @param array  $cfg
   * @return null|string The new ID
   */


  public function copy(string $id_menu, string $id_menu_to, array $cfg): ?string
  {
    if (Str::isUid($id_menu)
        && Str::isUid($id_menu_to)
        && ($bits = $this->pref->getFullBits($id_menu))
        && ($id = $this->add($id_menu_to, $cfg))
        && $this->_clone($id_menu_to, $bits, $id)
    ) {
      return $id;
    }

    return null;
  }


  /**
   * Clones a section/link to an other menu.
   *
   * @param string $id_bit     The bit's ID to clone
   * @param string $id_menu_to The menu's ID to clone
   * @param string $cfgvaule   of bit
   * @return null|string The new ID
   */


  public function copyTo(string $id_bit, string $id_menu_to, array $cfg): ?string
  {
    if (Str::isUid($id_bit)
        && Str::isUid($id_menu_to)
        && ($bit = $this->pref->getBit($id_bit))
        && ($id_menu = $this->getIdMenu($id_bit))
    ) {
      $bit  = array_merge(
        $bit, $cfg, [
        'id_parent' => null,
        'num' => $this->pref->getMaxBitNum($id_menu_to, null, true)
        ]
      );
      $bits = $this->pref->getFullBits($id_menu, $id_bit, true);
      if ($id = $this->add($id_menu_to, $bit)) {
        if (!empty($bits) && !$this->_clone($id_menu_to, $bits, $id)) {
          return null;
        }

        return $id;
      }
    }

    return null;
  }


  public function fixOrder(string $id, $id_parent = null, $deep = false): ?int
  {
    if (Str::isUid($id)
        && (empty($id_parent) || Str::isUid($id_parent))
    ) {
      $fixed = $this->pref->fixBitsOrder($id, $id_parent, $deep);
      if ($fixed) {
        $this->deleteCache($id);
      }

      return (int)$fixed;
    }

    return null;
  }


  /**
   * Orders a section/link.
   *
   * @param string $id  The section/link's ID
   * @param int    $pos The new position.
   * @return bool
   */
  public function order(string $id, int $pos): bool
  {
    if (Str::isUid($id)
        && $this->pref->orderBit($id, $pos)
    ) {
      $this->deleteCache($this->getIdMenu($id));
      return true;
    }

    return false;
  }


  /**
   * Moves a section/link inside to another one.
   *
   * @param string      $id        The section/link's ID.
   * @param string|null $id_parent The parent's ID.
   * @return bool
   */
  public function move(string $id, string|null $id_parent = null): bool
  {
    if ($this->pref->moveBit($id, $id_parent)) {
      $this->deleteCache($this->getIdMenu($id));
      return true;
    }

    return false;
  }


  public function getOptionsMenus()
  {
    //$items = $this->options->fullOptions('menus', self::$option_root_id);
    $items = self::getOption('menu');
    $res   = [];
    foreach ($items as $it){
      $res[] = [
        'text' => $it['text'],
        'value' => $it['id']
      ];
    }

    return $res;
  }


  /**
   * Gets the ID of a menu by its code and relative user's access
   * @param string $code The code of the menu
   * @param bool $pub The value of the 'public' field
   * @param string|null $idUser The value of the 'id_user' field
   * @param string|null $idGroup The value of the 'id_group' field
   * @return string|null
   */
  public function getByCode(string $code, bool $pub = true, $idUser = null, $idGroup = null): ?string
  {
    $prefCfg = $this->pref->getClassCfg();
    $pFields = $prefCfg['arch']['user_options'];
    $where = [
      'conditions' => [[
        'field' => $pFields['id_option'],
        'value' => $this->fromPath('menus')
      ], [
        'field' => 'JSON_UNQUOTE(JSON_EXTRACT(' . $pFields['cfg'] . ', "$.code"))',
        'value' => $code
      ], [
        'field' => $pFields['public'],
        'value' => (int)$pub
      ]]
    ];
    if (!empty($idUser)) {
      $where['conditions'][] = [
        'field' => $pFields['id_user'],
        'value' => $idUser
      ];
    }
    if (!empty($idGroup)) {
      $where['conditions'][] = [
        'field' => $pFields['id_group'],
        'value' => $idGroup
      ];
    }
    return $this->pref->getDb()->selectOne([
      'table' => $prefCfg['table'],
      'fields' => [$pFields['id']],
      'where' => $where
    ]);
  }


  /**
   * @param $root
   */
  private static function _set_public_root($root)
  {
    self::$id_public_root = $root;
  }


  private function _get_public_root()
  {
    if (\is_null(self::$id_public_root)
        && ($id = $this->options->fromPath(self::$public_root, '|', $this->options->fromCode('appui')))
    ) {
      self::_set_public_root($id);
    }

    return self::$id_public_root;
  }


  private function _set_relative_public_root($rel_path)
  {
    self::$public_root = self::$public_root . '|' . $rel_path;
  }


  private function _adapt($ar, $prepath = false)
  {
    $tmp = $this->_filter($ar, $this->pref);
    foreach ($tmp as $i => $it){
      if (!empty($it['items'])) {
        $tmp[$i]['items'] = $this->_adapt($it['items'], $prepath);
      }
    }

    $res = [];
    foreach ($tmp as $i => $it){
      if (!empty($it['items']) || !empty($it['id_permission'])) {
        $res[] = $it;
      }
    }

    return $res;
  }


  private function _filter($ar)
  {
    $usr = bbn\User::getInstance();
    if (\is_object($usr) && $usr->isDev()) {
      return $ar;
    }

    $pref = $this->pref;
    return array_filter(
      $ar,
      function ($a) use ($pref) {
        if (!empty($a['public'])) {
          return true;
        }

        if (isset($a['id_permission']) && $pref->has($a['id_permission'], $pref->getUser(), $pref->getGroup())) {
          return true;
        }

        if (!isset($a['id_permission']) && !empty($a['items'])) {
          return true;
        }

        return false;
      }
    );
  }


  private function _arrange(array $menu, $prepath = false)
  {
    if (isset($menu['text'], $menu['id'])) {
      $res = [
        'id' => $menu['id'],
        'text' => $menu['text'],
        'icon' => $menu['icon'] ?? 'nf nf-fa-cog'
      ];
      if (!empty($menu['id_option'])) {
        $opt                  = $this->options->option($menu['id_option']);
        $res['public']        = !empty($opt['public']) ? 1 : 0;
        $res['id_permission'] = $menu['id_option'];
        $res['link']          = $this->perm->toPath($menu['id_option']);
        if ($prepath && (strpos($res['link'], $prepath) === 0)) {
          $res['link'] = substr($res['link'], strlen($prepath));
        }

        if (!empty($menu['argument'])) {
          $res['link'] .= (substr($menu['argument'], 0, 1) === '/' ? '' : '/').$menu['argument'];
        }
      }

      if (!empty($menu['items'])) {
        $res['items'] = [];
        foreach ($menu['items'] as $m){
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
  private function _clone(string $id_menu, array $bits, string|null $id_parent = null)
  {
    $c = $this->pref->getClassCfg();
    if (Str::isUid($id_menu)) {
      foreach ($bits as $bit){
        unset($bit[$c['arch']['user_options_bits']['id']]);
        $bit[$c['arch']['user_options_bits']['id_user_option']] = $id_menu;
        $bit[$c['arch']['user_options_bits']['id_parent']]      = $id_parent;
        if (!($id = $this->add($id_menu, $bit))
            || (!empty($bit['items']) && !$this->_clone($id_menu, $bit['items'], $id))
        ) {
          return false;
        }
      }

      return true;
    }

    return false;
  }


}
