<?php
/**
 * @package user
 */
namespace bbn\User;

use bbn;
use bbn\X;
use bbn\Str;

/**
 * A permission system linked to options, User classes and preferences.
 * From the moment a user or a group has a preference on an item, it is considered to have a permission. Deleting a permission deletes the preference
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Nov 24, 2016, 13:23:12 +0000
 * @category  Authentication
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.1
 * @todo Store the deleted preferences? And restore them if the a permission is re-given
 */

class Permissions extends bbn\Models\Cls\Basic
{
  use bbn\Models\Tts\Retriever;
  use bbn\Models\Tts\Optional;
  use bbn\Models\Tts\Current;

  /** @var bbn\Appui\Option */
  protected $opt;

  /** @var bbn\User\Preferences */
  protected $pref;

  /** @var bbn\User */
  protected $user;

  /** @var bbn\Db */
  protected $db;

  /** @var array */
  protected $plugins = [];


  /**
   * permissions constructor.
   */
  public function __construct(array $routes = null)
  {
    if (!($this->opt = bbn\Appui\Option::getInstance())) {
      die('Impossible to construct permissions: you need to instantiate options before');
    }

    if (!($this->user = bbn\User::getInstance())) {
      die('Impossible to construct permissions: you need to instantiate user before');
    }

    if (!($this->pref = Preferences::getInstance())) {
      die('Impossible to construct permissions: you need to instantiate preferences before');
    }

    if ($routes) {
      foreach ($routes as $url => $plugin) {
        $plugin['url']   = $url;
        $this->plugins[] = $plugin;
      }
    }

    self::retrieverInit($this);
    self::optionalInit();
    $this->db = bbn\Db::getInstance();
  }


  /**
   * Returns the option's ID corresponds to the given path.
   *
   * @param string $path
   * @param string $type
   * @return null|string
   */
  public function fromPath(string $path, $type = 'access'): ?string
  {
    $parent = null;
    $root   = false;
    if (($type === 'access') && $this->plugins && !empty($path)) {
      foreach ($this->plugins as $plugin) {
        if (strpos($path, $plugin['url'].'/') === 0) {
          if (strpos($plugin['name'], 'appui-') === 0) {
            $root = $this->opt->fromCode(
              'access',
              'permissions',
              substr($plugin['name'], 6),
              BBN_APPUI
            );
            $path = substr($path, strlen($plugin['url']));
          }
        }
      }
    }

    if (!$root) {
      $root = $this->opt->fromCode($type, self::$option_root_id);
    }

    if (!$root) {
      throw new \Exception(_("Impossible to find the permission code"));
    }

    $parts  = explode('/', $path);
    $parent = $root;
    foreach ($parts as $i => $p){
      $is_not_last = $i < (\count($parts) - 1);
      if (!empty($p)) {
        $prev_parent = $parent;
        // Adds a slash for each bit of the path except the last one
        $parent = $this->opt->fromCode($p.($is_not_last ? '/' : ''), $prev_parent);
        // If not found looking for a subpermission
        if (!$parent && $is_not_last) {
          $parent = $this->opt->fromCode($p, $prev_parent);
        }
      }
    }

    return $parent ?: null;
  }


  public function toPath(string $id_option): ?string
  {
    $p    = [];
    $bits = $this->opt->getCodePath($id_option);
    if (empty($bits) || (count($bits) < 4)) {
      return null;
    }

    $bits = array_reverse($bits);
    if (array_shift($bits) !== 'appui') {
      return null;
    }

    $root   = array_shift($bits);
    $ok     = false;
    $prefix = '';
    // Main application
    if ($root === 'permissions') {
      if (array_shift($bits) !== 'access') {
        throw new \Exception("The permission should be under access");
      }

      $ok = true;
    }
    // Plugins
    elseif ($plugin = X::getRow($this->plugins, ['name' => 'appui-'.$root])) {
      if ((array_shift($bits) !== 'permissions') || (array_shift($bits) !== 'access')) {
        throw new \Exception("The permission should be under permissions/access of the plugin");
      }

      $prefix = $plugin['url'].'/';
      $ok     = true;
    }

    if ($ok) {
      return $prefix.X::join($bits, '');
    }

    return null;
  }


  /**
   * Returns the result of Appui\Option::options filtered with only the ones authorized to the current user.
   *
   * @param string|null $id_option
   * @param string      $type
   * @return array|null
   */
  public function options(string $id_option = null, String $type = 'access'): ?array
  {
    if (($id_option = $this->_get_id_option($id_option, $type))
        && ($os = $this->opt->options(\func_get_args()))
    ) {
      $res = [];
      foreach ($os as $o){
        if ($this->pref->has($o['id'])) {
          $res[] = $o;
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns the result of Appui\Option::full_options filtered with only the ones authorized to the current user.
   *
   * @param string|null $id_option
   * @param string      $type
   * @return array|null
   */
  public function fullOptions(string $id_option = null, String $type = 'access'): ?array
  {
    if (($id_option = $this->_get_id_option($id_option, $type))
        && ($os = $this->opt->fullOptions(\func_get_args()))
    ) {
      $res = [];
      foreach ($os as $o){
        /* if ( ($ids = $this->pref->retrieveIds($o['id'])) && ($cfg = $this->pref->get($ids[0])) ){
          $res[] = bbn\X::mergeArrays($o, $cfg);
        } */
        if ($this->has($o['id'], $type)) {
          $res[] = bbn\X::mergeArrays($o, $this->pref->getByOption($o['id']) ?: []);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns the full list of permissions existing in the given option
   *
   * @param null|string $id_option
   * @param string      $type
   * @return null|array
   */
  public function getAll(string $id_option = null, String $type = 'access'): ?array
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->options($id_option ?: $this->getCurrent());
    }

    return null;
  }


  /**
   * Returns the full list of permissions existing in the given option with all the current user's preferences
   *
   * @param null|string $id_option
   * @param string      $type
   * @return array|bool|false
   */
  public function getFull($id_option = null, String $type = 'access'): ?array
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->fullOptions($id_option ?: $this->getCurrent());
    }

    return null;
  }


  /**
   * Returns an option combined with its sole/first permission
   *
   * @param string $id_option
   * @param string $type
   * @param bool   $force
   * @return array|bool
   */
  public function get(string $id_option = null, String $type = 'access', bool $force = false): ?array
  {
    /*
    if ( $all = $this->getAll($id_option, $type) ){
      $r = [];
      foreach ( $all as $a ){
        if ( $this->has($a['id'], '', $force) ){
          $r[] = $a;
        }
      }
      return $r;
    }
    */
    if (($id_option = $this->_get_id_option($id_option, $type))
        && $this->has($id_option, $type, $force)
    ) {
      return $this->pref->option($id_option);
    }

    return null;
  }


  /**
   * Checks if a user and/or a group has a permission.
   *
   * @param mixed  $id_option
   * @param string $type
   * @param bool   $force
   * @return bool
   */
  public function has(string $id_option = null, String $type = 'access', bool $force = false): bool
  {
    if (!$force && $this->user && $this->user->isDev()) {
      return true;
    }

    if ($id_option = $this->_get_id_option($id_option, $type)) {
      $option = $this->opt->option($id_option);
      if (!empty($option['public'])) {
        return true;
      }

      return $this->pref->has($id_option, $force);
    }

    return false;
  }


  /**
   * Checks if a user and/or a group has a permission for the given option or for its childern.
   *
   * @param mixed  $id_option
   * @param string $type
   * @param bool   $force
   * @return bool
   */
  public function hasDeep(string $id_option = null, String $type = 'access', bool $force = false): bool
  {
    if (!$force && $this->user && $this->user->isDev()) {
      return true;
    }

    if ($this->has($id_option, $type, $force)) {
      return true;
    }

    if (($id_option = $this->_get_id_option($id_option, $type))
        && ($options = $this->opt->fullOptions($id_option))
    ) {
      foreach ($options as $option){
        if ($this->hasDeep($option['id'], $type, $force)) {
          return true;
        }
      }
    }

    return false;
  }


  /**
   * Checks if an option corresponds to the given path.
   *
   * @param string $path
   * @param string $type
   * @return null|string
   */
  public function is(string $path, String $type = 'access'): ?string
  {
    return $this->fromPath($path, $type);
  }


  /**
   * Adapts a given array of options' to user's permissions
   *
   * @param array $arr
   * @return array
   */
  public function customize(array $arr): array
  {
    $res = [];
    if (isset($arr[0])) {
      foreach ($arr as $a){
        if (isset($a['id']) && $this->has($a['id'])) {
          $res[] = $a;
        }
      }
    }
    elseif (isset($arr['items'])) {
      $res = $arr;
      unset($res['items']);
      foreach ($arr['items'] as $a){
        if (isset($a['id']) && $this->has($a['id'])) {
          if (!isset($res['items'])) {
            $res['items'] = [];
          }

          $res['items'][] = $a;
        }
      }
    }

    return $res;
  }


  /**
   * Grants a new permission to a user or a group
   * @param null|string $id_option
   * @param string      $type
   * @return int
   */
  public function add(string $id_option, String $type = 'access'): ?int
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->setByOption($id_option, []);
    }

    return null;
  }


  /**
   * Deletes a preference for a path or an ID.
   *
   * @param null|string $id_option
   * @param string      $type
   * @return null|int
   */
  public function remove($id_option, String $type = 'access'): ?int
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->delete($id_option);
    }

    return null;
  }


  /**
   * Checks if the category represented by the given option ID is readable by the current user
   *
   * @param string|null $id_option
   * @return bool|null
   */
  public function readOption(string $id_option = null): ?bool
  {
    if (bbn\Str::isUid($id_option)) {
      $root        = self::getOptionId('options');
      $id_to_check = $this->opt->fromCode('opt'.$id_option, $root);
      return $this->has($id_to_check, 'options');
    }

    return null;
  }


  /**
   * Checks if the category represented by the given option ID is writable by the current user
   *
   * @param string|null $id_option
   * @return bool|null
   */
  public function writeOption(string $id_option): ?bool
  {
    if (bbn\Str::isUid($id_option)) {
      $root        = self::getOptionId('opt'.$id_option, 'options');
      $id_to_check = $this->opt->fromCode('write', $root);
      return $this->has($id_to_check, 'options');
    }

    return null;
  }


  public function updateAll($routes)
  {
    $res = ['total' => false];
    /** @var int The option's ID of the permissions' root $id_permission (permissions, appui) */
    if ($id_permission = $this->getOptionRoot()) {
      /** @var int The option's ID of the permissions on options $id_option */
      $id_option = $this->getOptionId('options');
      if (!$id_option) {
        $id_option = $this->opt->add(
          [
          'id_parent' => $id_permission,
          'code' => 'options',
          'text' => _("Options"),
          'cfg' => [
            'icon' => 'nf nf-fa-cogs'
          ]
          ]
        );
      }

      /** @var int The option's ID of the permissions on pages (controllers) $id_page */
      $id_page = $this->getOptionId('access');
      if (!$id_page) {
        $id_page = $this->opt->add(
          [
          'id_parent' => $id_permission,
          'code' => 'access',
          'text' => _("Access"),
          'cfg' => [
            'icon' => 'nf nf-fa-files'
          ]
          ]
        );
      }

      /** @var int The option's ID of the permissions on pages (controllers) $id_page */
      $id_plugins = $this->getOptionId('plugins');
      if (!$id_plugins) {
        $id_plugins = $this->opt->add(
          [
            'id_parent' => $id_permission,
            'code' => 'plugins',
            'text' => _("Plugins"),
            'cfg' => [
              'icon' => 'nf nf-fa-plug'
            ]
          ]
        );
      }

      die(X::dump($id_option, $id_page, $id_plugins));

      $id_access_plugins = $this->opt->fromCode('access', $id_plugins);

      /** @todo Add the possibility to do it for another project? */
      $fs = new bbn\File\System();
      $fn = function ($a) {
        return ($a['ext'] === 'php')
          && (basename($a['name']) !== '_ctrl.php');
        // Before we allowed directories
        /*
        return (($a['type'] === 'dir') || ($a['ext'] === 'php'))
            && (basename($a['name']) !== '_ctrl.php');
        */
      };

      if ($id_page
          && ($root = bbn\Mvc::getAppPath().'mvc/public')
          && ($all = $fs->scan($root, $fn, false, 'te'))
      ) {
        $all = $this->_treat($all);
        /*
        foreach ($routes as $url => $route){
          if ($tree = bbn\File\Dir::getTree(
            $route['path'].'/src/mvc/public', false, function ($a) {
              return (($a['type'] === 'dir') || ($a['ext'] === 'php')) && (basename($a['name']) !== '_ctrl.php');
            }
          )
          ) {
            $treated = $this->_treat($tree);
            $this->_merge($all, $treated, $url);
          }
        }
        */

        usort($all, [$this, '_sort']);
        array_walk($all, [$this, '_walk']);
        $res['total'] = 0;
        //die(X::dump($all));
        foreach ($all as $i => $it){
          $it['cfg']     = json_encode(['order' => $i + 1]);
          $res['total'] += $this->_add($it, $id_page);
        }
      }

      // $id_option must be set to generate the option's permissions
      if ($id_option && ($permissions = $this->opt->findPermissions($this->opt->getRoot(), true))) {
        foreach ($permissions as $p){
          $p['code']      = 'opt'.$p['id'];
          $p['id_alias']  = $p['id'];
          $p['id_parent'] = $id_option;
          $p['type']      = 'option';
          unset($p['id']);
          if (!empty($p['items'])) {
            $p['items'] = $this->_treat_options($p['items']);
          }

          $res['total'] += $this->opt->add($p, true, true);
          if (!empty($p['items'])) {
            unset($p['items']);
          }

          $p['id_parent'] = $this->opt->fromCode($p['code'], $id_option);
          $p['code']      = 'write';
          $p['text']      = 'Écriture';
          $res['total']  += $this->opt->add($p, true, true);
        }
      }

      $this->opt->deleteCache();
    }

    return $res;

  }


  private function _treat_options(array $tree)
  {
    $t = $this;
    return \array_map(
      function ($i) use ($t) {
        $i['id_alias'] = $i['id'];
        $i['code']     = 'opt'.$i['id'];
        $i['type']     = 'option';
        unset($i['id']);
        if (!empty($i['items'])) {
          $i['items'] = $t->_treat_options($i['items']);
        }

        return $i;
      }, $tree
    );
  }


  private function _treat(array $tree, $parent=false)
  {
    $res = [];
    foreach ($tree as $i => $t){
      $t['name'] = Str::changeCase($t['name'], 'lower');
      $code      = $t['type'] === 'dir' ? basename($t['name']).'/' : basename($t['name'], '.php');
      $text      = $t['type'] === 'dir' ? basename($t['name']) : basename($t['name'], '.php');
      $o         = [
        'code' => $code,
        'text' => $text
      ];
      if ($t['type'] === 'file') {
        $o['type'] = 'file';
      }

      if (!empty($t['items'])) {
        $o['items'] = $this->_treat($t['items'], $o['code']);
      }

      array_push($res, $o);
    }

    return $res;
  }


  // Sort names between folders and files
  private function _sort($a, $b)
  {
    if (substr($a['code'], -1) === '/') {
      $a['code'] = '00'.$a['code'];
    }

    if (substr($b['code'], -1) === '/') {
      $b['code'] = '00'.$b['code'];
    }

    $a = str_replace('.', '0', str_replace('_', '1', Str::changeCase($a['code'], 'lower')));
    $b = str_replace('.', '0', str_replace('_', '1', Str::changeCase($b['code'], 'lower')));
    return strcmp($a, $b);
  }


  // Sort items' hierarchy
  private function _walk(&$a)
  {
    if (!empty($a['items'])) {
      usort($a['items'], [$this, '_sort']);
      array_walk($a['items'], [$this, '_walk']);
    }
  }


  // Add options to the options table
  private function _add($o, $id_parent, $total = 0)
  {
    $items = isset($o['items']) ? $o['items'] : false;
    unset($o['items']);
    $o['id_parent'] = $id_parent;
    if (!($id = $this->opt->fromCode($o['code'], $id_parent))) {
      $total += (int)$this->opt->add($o, false, true);
      $id     = $this->db->lastId();
    }

    /* No!!!
    else if ( isset($o['cfg']) ){
      $this->opt->set($id, $o);
    }
    */
    if (\is_array($items)) {
      foreach ($items as $it){
        $total = $this->_add($it, $id, $total);
      }
    }

    return $total;
  }


  private function _merge(&$target, $src, $path)
  {
    $parts = explode('/', $path);
    foreach ($parts as $p){
      if (!empty($p)) {
        foreach ($target as $i => $a){
          if (($a['code'] === $p.'/') && !empty($target[$i]['items'])) {
            $this->_merge($target[$i]['items'], $src, substr($path, \strlen($p) + 1));
            return;
          }
        }

        array_push(
          $target, [
          'code' => $p.'/',
          'text' => $p,
          'items' => $src
          ]
        );
      }
    }
  }


  /**
   * @param string|null $id_option
   * @param string      $type
   * @return null|string
   */
  private function _get_id_option(string $id_option = null, $type = 'access'): ?string
  {
    if ($id_option && !bbn\Str::isUid($id_option)) {
      $id_option = $this->fromPath($id_option, $type);
    }
    elseif (null === $id_option) {
      $id_option = $this->getCurrent();
    }

    if (bbn\Str::isUid($id_option)) {
      return $id_option;
    }

    return null;
  }


}
