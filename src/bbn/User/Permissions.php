<?php
/**
 * @package user
 */
namespace bbn\User;

use Exception, bbn, bbn\X, bbn\Str, bbn\User, bbn\Db, bbn\Appui\Option, bbn\Mvc, bbn\File\System;
/**
 * A permission system linked to options, User classes and preferences.
 *
 * A permission is an option under the permission option ("permissions", "appui") or one of its aliases.
 * They are ONLY permissions.
 *
 * No!!! From the moment a user or a group has a preference on an item, it is considered to have a permission.
 * No!!! Deleting a permission deletes the preference
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
   * Permissions constructor.
   *
   * @param array $routes An array of routes to the plugins
   */
  public function __construct(array $routes = null)
  {
    if (!($this->opt = Option::getInstance())) {
      throw new Exception(X::_('Impossible to construct permissions: you need to instantiate options before'));
    }

    if (!($this->user = User::getInstance())) {
      throw new Exception(X::_('Impossible to construct permissions: you need to instantiate user before'));
    }

    if (!($this->pref = Preferences::getInstance())) {
      throw new Exception(X::_('Impossible to construct permissions: you need to instantiate preferences before'));
    }

    if ($routes) {
      foreach ($routes as $url => $plugin) {
        $plugin['url']   = $url;
        $this->plugins[] = $plugin;
      }
    }

    self::retrieverInit($this);
    self::optionalInit();
    $this->db = Db::getInstance();
  }


  /**
   * Returns the option's ID corresponds to the given path.
   *
   * @todo The type shouldn't always be access as it's a path?
   *
   * @param string $path The path
   * @param string $type The type
   * @return null|string
   */
  public function fromPath(string $path, $type = 'access', $create = false): ?string
  {
    $opath  = $path;
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
            $path = substr($path, strlen($plugin['url']) + 1);
          }
          elseif ($plugin['name']) {
            $root = $this->opt->fromCode(
              'access',
              'permissions',
              $plugin['name'],
              'plugins',
            );
            $path = substr($path, strlen($plugin['url']) + 1);
          }

          break;
        }
      }
    }

    if (!$root) {
      $root = $this->opt->fromCode($type, self::$option_root_id);
    }

    if (!$root) {
      throw new Exception(X::_("Impossible to find the permission code for $path"));
    }

    $parts  = explode('/', trim($path, '/'));
    $parent = $root;

    foreach ($parts as $i => $p){
      $is_last = $i === (\count($parts) - 1);
      if (!empty($p)) {
        $prev_parent = $parent;
        // Adds a slash for each bit of the path except the last one
        $parent = $this->opt->fromCode($p.($is_last ? '' : '/'), $prev_parent);
            // If not found looking for a subpermission
        if (!$parent && !$is_last) {
          $parent = $this->opt->fromCode($p, $prev_parent);
        }
        elseif ($is_last && $prev_parent && !$parent && $create) {
          if ($this->_add(
            [
              'code' => $p,
              'text' => $p
            ],
            $prev_parent
          )
          ) {
            $parent = $this->db->lastId();
          }
        }
      }
    }

    return $parent ?: null;
  }


  /**
   * Returns the path corresponding to the given ID
   *
   * @param string $id_option The option's UID
   *
   * @return string|null
   */
  public function toPath(string $id_option): ?string
  {
    $p    = [];
    $bits = $this->opt->getCodePath($id_option);
    // Minimum: appui, plugin, permissions, path
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
        throw new Exception("The permission should be under access");
      }

      $ok = true;
    }
    // Plugins
    elseif ($plugin = X::getRow($this->plugins, ['name' => 'appui-'.$root])) {
      if ((array_shift($bits) !== 'permissions') || (array_shift($bits) !== 'access')) {
        throw new Exception("The permission should be under permissions/access of the plugin");
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
   * Returns the result of Option::Options filtered through current user's permissions.
   *
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @return array|null
   */
  public function options(string $id_option = null, string $type = 'access'): ?array
  {
    if (($id_option = $this->_get_id_option($id_option, $type))
        && ($os = $this->opt->options($id_option))
    ) {
      $res = [];
      foreach ($os as $id => $o){
        if ($this->pref->has($id)) {
          $res[$id] = $o;
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns the result of Option::fullOptions filtered through current user's permissions.
   *
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @return array|null
   */
  public function fullOptions(string $id_option = null, string $type = 'access'): ?array
  {
    if (($id_option = $this->_get_id_option($id_option, $type))
        && ($os = $this->opt->fullOptions($id_option))
    ) {
      $res = [];
      foreach ($os as $o){
        /* if ( ($ids = $this->pref->retrieveIds($o['id'])) && ($cfg = $this->pref->get($ids[0])) ){
          $res[] = X::mergeArrays($o, $cfg);
        } */
        if ($this->has($o['id'], $type)) {
          $res[] = X::mergeArrays($o, $this->pref->getByOption($o['id']) ?: []);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns the full list of permissions existing in the given option
   *
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @return null|array
   */
  public function getAll(string $id_option = null, string $type = 'access'): ?array
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->options($id_option ?: $this->getCurrent());
    }

    return null;
  }


  /**
   * Returns the full list of permissions existing in the given option with all the current user's preferences
   *
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @return array|bool|false
   */
  public function getFull($id_option = null, string $type = 'access'): ?array
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->fullOptions($id_option ?: $this->getCurrent());
    }

    return null;
  }


  /**
   * Returns an option combined with its sole/first permission
   *
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @param bool        $force     Force permission check
   * @return array|bool
   */
  public function get(string $id_option = null, string $type = 'access', bool $force = false): ?array
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
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @param bool        $force     Force permission check
   * @return bool
   */
  public function has(string $id_option = null, string $type = 'access', bool $force = false): bool
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
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @param bool        $force     Force permission check
   * @return bool
   */
  public function hasDeep(string $id_option = null, string $type = 'access', bool $force = false): bool
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
   * Alias of fromPath.
   *
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @return null|string
   */
  public function is(string $path, string $type = 'access'): ?string
  {
    return $this->fromPath($path, $type);
  }


  /**
   * Adapts a given array of options' to user's permissions
   *
   * @todo Check if it's used anywhere.
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
   * Grants a new permission to a user or a group.
   *
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @return int
   */
  public function add(string $id_option, string $type = 'access'): ?int
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->setByOption($id_option, []);
    }

    return null;
  }


  /**
   * Deletes a preference for a path or an ID.
   *
   * @param string|null $id_option The option's UID
   * @param string      $type      The type: access or option
   * @return null|int
   */
  public function remove($id_option, string $type = 'access'): ?int
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->delete($id_option);
    }

    return null;
  }


  /**
   * Returns the permissions inherited properties.
   *
   * @param string|null $id_option The option's UID
   * @return array|null
   */
  public function getParentCfg(string $id_option): ?array
  {
    foreach ($this->opt->parents($id_option) as $i => $p) {
      $cfg = $this->opt->getCfg($p);
      if (!empty($cfg['permissions'])) {
        if ((!$i && ($cfg['permissions'] === 'children')) 
          || in_array($cfg['permissions'], ['all', 'cascade'])
        ) {
          return [
            'cfg' => $cfg['permissions'],
            'from' => $p,
            'from_text' => $this->opt->text($p),
            'cascade' => in_array($cfg['permissions'], ['all', 'cascade'])
          ];
        }

        break;
      }
    }

    return null;
  }


  /**
   * Returns the corresponding permission of a given option.
   *
   * @param string|null $id_option The option's UID
   * @param bool        $create    The permission will be created if it doesn't exist.
   *
   * @return string|null
   */
  public function optionToPermission(string $id_option, bool $create = false): ?string
  {
    /** @var string The result - an option's ID */
    $id_perm = null;

    if (!Str::isUid($id_option)) {
      throw new Exception("The string sent is not a UID: $id_option");
    }

    if (!$this->opt->exists($id_option)) {
      throw new Exception("The options with a UID $id_option doesn't exist");
    }

    /** @var array List of aliases (permissions have aliases to the original options) */
    $aliases = $this->opt->getAliasItems($id_option);

    /** @var string The root (with options code) for this option's permission */
    $root    = $this->optionPermissionRoot($id_option);

    foreach ($aliases as $a) {
      $parents = $this->opt->parents($a);
      if (in_array($root, $parents)) {
        $id_perm = $a;
        break;
      }
    }

    if (!$id_perm && $create) {
      return $this->createFromId($id_option);
    }

    return $id_perm ?: null;
  }


  /**
   * Checks if the given option is readable by the current user.
   *
   * @param string|null $id_option
   * @return bool|null
   */
  public function readOption(string $id_option = null, bool $force = false): ?bool
  {
    if ($this->user->isAdmin()) {
      return true;
    }

    if ($id_perm = $this->optionToPermission($id_option)) {
      return $this->pref->has($id_perm, $force) ?: $this->user->isAdmin();
    }

    return true;
  }


  /**
   * Checks if the given option is writable by the current user.
   *
   * @param string|null $id_option
   * @return bool|null
   */
  public function writeOption(string $id_option, bool $force = false): ?bool
  {
    return $this->readOption($id_option, $force);
  }


  /**
   * Returns an array corresponding to the different roots for permissions in the project.
   *
   * @param bool $only_with_children Only the ones having children will be returned
   *
   * @return array
   */
  public function getSources($only_with_children = true): array
  {
    $appui   = $this->opt->fromCode('appui');
    $root    = $this->opt->fromCode('permissions', $appui);
    $access  = $this->opt->fromCode('access', $root);
    $options = $this->opt->fromCode('options', $root);
    $plugins = $this->opt->fromCode('plugins');
    $sources = [[
      'text' => _("Main application"),
      'rootAccess' => $access,
      'rootOptions' => $options,
      'code' => ''
    ]];
    $all     = array_merge(
      array_map(
        function($a) {
          $a['code'] = 'appui-'.$a['code'];
          return $a;
        },
        $this->opt->fullOptions($appui)
      ),
      $this->opt->fullOptions($plugins)
    );
    foreach ($all as $o) {
      if (!empty($o['plugin'])
          && ($id_perm = $this->opt->fromCode('access', 'permissions', $o['id']))
      ) {
        $id_option = $this->opt->fromCode('options', 'permissions', $o['id']);
        $tmp       = $this->opt->option($id_perm);
        if (!$only_with_children || !empty($tmp['num_children'])) {
          $sources[] = [
            'text' => $o['text'],
            'code' => $o['code'],
            'rootAccess' => $id_perm,
            'rootOptions' => $id_option
          ];
        }
      }
    }

    return $sources;
  }


  /**
   * Checks if the given permission corresponds to real file in mvc/public.
   *
   * @param string $id_perm
   *
   * @return bool
   */
  public function accessExists(string $id_perm): bool
  {
    $real    = false;
    $parents = array_reverse($this->opt->parents($id_perm));
    $access  = $this->opt->fromCode('access', 'permissions', 'appui');
    if (in_array($access, $parents, true)) {
      $path_to_file = $this->opt->toPath($id_perm, '', $access);
      if (substr($path_to_file, -1) === '/') {
        return is_dir(Mvc::getAppPath().'mvc/public/'.substr($path_to_file, 0, -1));
      }

      return file_exists(Mvc::getAppPath().'mvc/public/'.$path_to_file.'.php');
    }
    else {
      $plugin_name = $this->opt->code($parents[2]);
      if ($this->opt->code($parents[1]) === 'appui') {
        $plugin_name = 'appui-'.$plugin_name;
      }

      $path_to_file = $this->opt->toPath($id_perm, '', $parents[4]);
      if (substr($path_to_file, -1) === '/') {
        return is_dir(Mvc::getPluginPath($plugin_name).'mvc/public/'.substr($path_to_file, 0, -1));
      }

      return file_exists(Mvc::getPluginPath($plugin_name).'mvc/public/'.$path_to_file.'.php');
    }

    return false;
  }


  /**
   * Returns
   *
   * @param string $name
   *
   * @return string|null
   */
  public function accessPluginRoot(string $name): ?string
  {
    $args = ['access', 'permissions'];
    if (strpos($name, 'appui-') === 0) {
      array_push($args, substr($name, 6), 'appui');
    }
    else {
      array_push($args, $name, 'plugins');
    }
    X::log($args, 'errorUpdatePermissions');

    return $this->opt->fromCode(...$args);
  }


  /**
   * Returns
   *
   * @param string $name
   *
   * @return string|null
   */
  public function optionPermissionRoot(string $id, $create = false): ?string
  {
    /** @var string The option's ID for appui */
    $appui   = $this->opt->fromCode('appui');

    /** @var string The option's ID for plugins */
    $plugins = $this->opt->fromCode('plugins');

    /** @var array The parents, the first being root */
    $parents = array_reverse($this->opt->parents($id));

    $num     = count($parents);

    // Looking for the root of the options' permissions
    if (($num > 2) && \in_array($parents[1], [$appui, $plugins])) {

      if (($num > 4) && ($this->opt->code($parents[3]) === 'plugins')) {

        /** @var string  */
        $root_parent = $this->opt->fromCode('plugins', 'permissions', $parents[2]);
        if (!$root_parent) {
          throw new Exception("Impossible to find a parent for plugin's permission ".$parents[2]);
        }

        $plugin  = $this->opt->code($parents[4]);
        if ($parents[1] === $appui) {
          $alias = $this->opt->fromCode('options', 'permissions', $plugin, 'appui');
        }
        else {
          $alias = $this->opt->fromCode('options', 'permissions', $plugin, 'plugins');
        }

        $id_root = $this->opt->fromCode($plugin, $root_parent);
        if (!$id_root && $create) {
          $id_root = $this->opt->add([
            'id_parent' => $root_parent,
            'code' => $plugin,
            'text' => $plugin,
            'id_alias' => $alias
          ]);
        }

        return $id_root;
      }

      return $this->opt->fromCode('options', 'permissions', $parents[2]);
    }

    /** @var string The option's ID of the permissions on options $id_option */
    return $this->getOptionId('options');
  }


  /**
   * Updates all access permission for the given path in the given root.
   *
   * @param string $path    The path to look for files in (mustn't include mvc/public)
   * @param string $root    The ID of the root access option
   * @param string $url     The path part of the URL root of the given absolute path
   * @param string $urlPath The part of the absolute path corresponding to the url
   *
   * @return int
   */
  public function accessUpdatePath(
      string $path,
      string $root,
      string $url = ''
  ): int
  {
    if (!empty($url) && (substr($url, -1) !== '/')) {
      $url .= '/';
    }

    $num = 0;
    $fs  = new System();
    $ff  = function ($a) use ($url, $path) {
      if (empty($url)) {
        $a['path'] = substr($a['name'], strlen(Mvc::getAppPath().'mvc/public/'));
      }
      else {
        $a['path'] = $url.substr($a['name'], strlen($path.'mvc/public/'));
      }

      if (substr($a['path'], -4) === '.php') {
        $a['path'] = substr($a['path'], 0, -4);
      }

      return \bbn\User\Permissions::fFilter($a);
    };

    if ($all = $fs->getTree($path.'mvc/public', '', false, $ff)) {
      $all = self::fTreat($all, false);
      usort($all, ['\\bbn\User\\Permissions', 'fSort']);
      array_walk($all, ['\\bbn\\User\\Permissions', 'fWalk']);
      foreach ($all as $i => $it) {
        $it['cfg'] = json_encode(['order' => $i + 1]);
        $num      += $this->_add($it, $root);
      }
    }

    return $num;
  }


  /**
   * Updates all access permission for the main app.
   *
   * @return int|null
   */
  public function accessUpdateApp(): ?int
  {
    if ($id_page = $this->getOptionId('access')) {
      return $this->accessUpdatePath(Mvc::getAppPath(), $id_page);
    }

    return null;
  }


  /**
   * Updates all the permission for the options.
   *
   * @return int|null
   */
  public function optionsUpdateAll()
  {
    $cf = $this->opt->getClassCfg();
    $of =& $cf['arch']['options'];

    $num = 0;

    $tmp = $this->db->getColumnValues(
      [
        'table' => $cf['table'],
        'fields' => [$of['id']],
        'join' => [
          [
            'table' => $cf['table'],
            'alias' => 'parent_option',
            'type' => 'left',
            'on' => [
              [
                'field' => 'parent_option.'.$of['id'],
                'exp' => $cf['table'].'.'.$of['id_parent']
              ], [
                'field' => 'parent_option.'.$of['cfg'],
                'operator' => 'contains',
                'value' => '"permissions":'
              ]
            ]
          ]
        ],
        'where' => [
          [
            'field' =>  $cf['table'].'.'.$of['cfg'],
            'operator' => 'contains',
            'value' => '"permissions":'
          ], [
            'field' => $cf['table'].'.'.$of['id'],
            'operator' => '!=',
            'value' => $this->opt->getRoot()
          ], [
            'field' => $cf['table'].'.'.$of['id'],
            'operator' => '!=',
            'value' => $this->opt->fromCode('appui')
          ], [
            'field' => $cf['table'].'.'.$of['id'],
            'operator' => '!=',
            'value' => $this->opt->fromCode('plugins')
          ], [
            'field' => 'parent_option.'.$of['id'],
            'operator' => 'isnull'
          ]
        ]
      ]
    );

    if ($tmp) {
      $permissions = [];
      foreach ($tmp as $id) {
        /** @var array The option's config */
        $cfg = $this->opt->getCfg($id) ?: [];
        if (!empty($cfg['permissions'])) {
          $permissions[$id] = $cfg['permissions'];
          foreach ($this->opt->getAliasItems($id) as $alias) {
            $permissions[$alias] = $cfg['permissions'];
          }
        }

        /*
        if (isset($cfg['scfg']) && !empty($cfg['scfg']['permissions'])) {
          foreach ($this->opt->items($id) as $ido) {
            $permissions[$ido] = $cfg['scfg']['permissions'];
          }
        }
        */
      }


      foreach ($permissions as $id => $mode) {
        $all = [];
        /** @var array The parents, starting from root */
        if (!($root = $this->optionPermissionRoot($id, true))) {
          continue;
        }

        $it = false;
        switch ($mode) {
          case 'single':
            if ($tmp = $this->opt->option($id)) {
              $it = $tmp;
            }
            break;
          case 'cascade':
          case 'all':
            if ($tmp = $this->opt->fullTree($id)) {
              $it = $tmp;
            }
            break;
          case 'children':
          case 1:
          case '1':
            if ($tmp = $this->opt->fullOptions($id)) {
              $it = $this->opt->option($id);
              $it['items'] = $tmp;
            }
            break;
        }

        if ($it) {
          $all = X::rmap(
            function ($a) {
              $tmp = [
                'text' => '',
                'code' => null,
                'id_alias' => $a['id']
              ];
              if (!empty($a['items'])) {
                $tmp['items'] = $a['items'];
              }

              return $tmp;
            },
            [$it],
            'items'
          );

          $all[0]['id_parent'] = $root;
          $num += $this->createOptionPermission($all[0]);
        }
      }
    }

    return $num;
  }


  /**
   * update All
   *
   * @param array $routes
   *
   * @return void
   */
  public function updateAll(array $routes, $withApp = false)
  {
    $this->opt->deleteCache();

    $res = ['total' => 0];

    /** @var string The ID option for permissions < appui */
    if ($id_permission = $this->getOptionRoot()) {
      /** @var string The option's ID for appui */
      $appui = $this->opt->fromCode('appui');

      /** @var string The option's ID for plugins */
      $plugins = $this->opt->fromCode('plugins');

      /** @var string The option's ID of the permissions on pages (controllers) $id_page */
      $id_page = $this->getOptionId('access');

      /** @var string The option's ID of the permissions on pages (controllers) $id_page */
      $id_plugins = $this->getOptionId('plugins');

      // The app base access
      if ($id_page) {

        /** @todo Add the possibility to do it for another project? */
        $fs = new System();

        if ($withApp) {
          $res['total'] += (int)$this->accessUpdateApp();
        }

        if (!empty($routes)) {
          foreach ($routes as $url => $route) {
            $root = $this->accessPluginRoot($route['name']);

            if (!$root) {
              $err = X::_(
                "Impossible to find the plugin %s",
                substr($route['name'], 6)
              );
              X::log($err, 'errorUpdatePermissions');
              continue;
              throw new Exception($err);
            }

            $res['total'] += $this->accessUpdatePath($route['path'].'src/', $root, $url);
          }
        }
      }

      $res['total'] += $this->optionsUpdateAll();

      $this->opt->deleteCache();
    }

    return $res;

  }


  public function createFromId(string $id): ?string
  {
    $opt = $this->opt->option($id);
    if (!$opt) {
      throw new Exception("The option $id doesn't exist");
    }

    if ($this->optionToPermission($id)) {
      throw new Exception("The permission for option $id already exist");
    }

    /** @var string The option's ID for appui */
    $appui   = $this->opt->fromCode('appui');

    /** @var string The option's ID for plugins */
    $plugins = $this->opt->fromCode('plugins');

    /** @var array The parents, the first being root */
    $parents = array_reverse($this->opt->parents($id));

    /** @var string The root (with options code) for this option's permission */
    $root    = $this->optionPermissionRoot($id);

    $num     = count($parents);

    if ($num < 2) {
      throw new Exception("The permission for option $id already exist");
    }

    // Removing root
    array_shift($parents);
    $root_original = array_shift($parents);

    // Looking for the root of the options' permissions
    if (($num > 2) && \in_array($root_original, [$appui, $plugins])) {

      if (($num > 4) && ($this->opt->code($parents[1]) === 'plugins')) {

        /** @var string  */
        $root_parent = $this->opt->fromCode('plugins', 'permissions', $parents[0]);
        if (!$root_parent) {
          throw new Exception("Impossible to find a parent for plugin's permission ".$parents[0]);
        }

        array_shift($parents);
        array_shift($parents);
        $plugin  = $this->opt->code(array_shift($parents));
        $alias = $this->opt->fromCode('options', 'permissions', $plugin, $root_original);
        $id_root = $this->opt->fromCode($plugin, $root_parent);

        if (!$id_root) {
          $id_root = $this->opt->add([
            'id_parent' => $root_parent,
            'code' => $plugin,
            'text' => $plugin,
            'id_alias' => $alias
          ]);
        }

      }
      else {
        $id_root = $this->opt->fromCode('options', 'permissions', array_shift($parents));
      }
    }
    else {
      $id_root = $this->opt->fromCode('options', 'permissions', $appui);
    }

    if (!$id_root) {
      throw new Exception("No root found for option $id");
    }

    $parents = array_reverse($parents);
    // All the parents from the closest
    $parents = $this->opt->parents($id);
    // The farest parent having permissions set
    $id_parent = false;
    $perm_parents = [];
    $id_parent_perm = null;
    // Looping all the parent IDs from the deepest
    foreach ($parents as $i => $p) {
      $cfg = $this->opt->getCfg($p);
      $id_parent = null;
      // The root is the last with permnission on
      if (!$cfg['permissions']) {
        break;
      }
      else {
        if (!$i && ($cfg['permissions'] === 'children')) {
          $id_parent = $p;
        }
        elseif (in_array($cfg['permissions'], ['all', 'cascade'])) {
          $id_parent = $p;
        }
      }

      if ($id_parent) {
        // Looking for the permission
        $aliases = $this->opt->getAliasItems($id_parent);
        foreach ($aliases as $a) {
          if (in_array($root, $this->opt->parents($a))) {
            $id_parent_perm = $a;
            break;
          }
        }
        
        // We break at first permission found
        if ($id_parent_perm) {
          break;
        }
        else {
          $perm_parents[] = $id_parent;
        }
      }
    }

    X::log(['perm_parents', $perm_parents]);
    $id_parent = $id_parent_perm ?: $id_root;
    foreach (array_reverse($perm_parents) as $a) {
      $id_parent = $this->opt->add([
        'id_parent' => $id_parent,
        'id_alias'  => $a
      ]);
    }

    X::log($this->opt->option($id_parent));

    return $this->opt->add([
      'id_parent' => $id_parent,
      'id_alias'  => $id
    ]);
  }


  public function createOptionPermission(array $item): ?int
  {
    if (X::hasProps($item, ['id_parent', 'id_alias'], true)) {
      $cf       = $this->opt->getClassCfg();
      $co       =& $cf['arch']['options'];
      $res      = 0;
      $children = false;
      $id       = $this->db->selectOne(
        $cf['table'],
        $co['id'],
        [
          $co['id_parent'] => $item['id_parent'],
          $co['id_alias'] => $item['id_alias']
        ]
      );
      if (!empty($item['items'])) {
        $children = $item['items'];
        unset($item['items']);
      }

      if (!$id) {
        $item['text'] = null;
        $id = $this->opt->add($item);
        X::log($this->opt->getPathArray($item['id_alias']), 'insertPerm');
        if ($id) {
          $res++;
        }

      }
      elseif ($this->opt->text($id)) {
        $this->db->update(
          $cf['table'],
          [
            $co['text'] => null,
            $co['cfg'] => null,
            $co['value'] => null
          ],
          [$co['id'] => $id]
        );
      }

      if ($id && $children) {
        //die(var_dump($subitems, $item));
        foreach ($children as $it) {
          $it['id_parent'] = $id;
          $res             += (int)$this->createOptionPermission($it);
        }
      }

      return $res;
    }

    return null;

  }


  public static function fFilter(array $a): bool
  {
    $mvc = Mvc::getInstance();
    if (!empty($a['num'])
      || ((substr($a['name'], -4) === '.php')
          && (basename($a['name']) !== '_ctrl.php'))
    ) {
      if (!$mvc->isAuthorizedRoute($a['path'])) {
        return true;
      }
    }

    return false;
  }


  public static function fTreat(array $tree, $parent = false)
  {
    $res = [];
    foreach ($tree as $i => $t){
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
        $o['items'] = self::fTreat($t['items'], $o['code']);
      }

      array_push($res, $o);
    }

    return $res;
  }


  // Sort names between folders and files
  public static function fSort($a, $b)
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
  public static function fWalk(&$a)
  {
    if (!empty($a['items'])) {
      usort($a['items'],  ['\\bbn\User\\Permissions', 'fSort']);
      array_walk($a['items'], ['\\bbn\User\\Permissions', 'fWalk']);
    }
  }


  public static function fMerge(&$target, $src, $path)
  {
    $parts = explode('/', $path);
    foreach ($parts as $p){
      if (!empty($p)) {
        foreach ($target as $i => $a){
          if (($a['code'] === $p.'/') && !empty($target[$i]['items'])) {
            self::fMerge($target[$i]['items'], $src, substr($path, \strlen($p) + 1));
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


  // Add options to the options table
  private function _add($o, $id_parent, $total = 0)
  {
    $items = isset($o['items']) ? $o['items'] : false;
    unset($o['items']);
    $o['id_parent'] = $id_parent;
    if (!($id = $this->opt->fromCode($o['code'], $id_parent))) {
      $total += (int)$this->opt->add($o, false, true);
      X::log($o, 'insertPerm');
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


  /**
   * @param string|null $id_option
   * @param string      $type
   * @return null|string
   */
  private function _get_id_option(string $id_option = null, $type = 'access'): ?string
  {
    if ($id_option && !Str::isUid($id_option)) {
      $id_option = $this->fromPath($id_option, $type);
    }
    elseif (null === $id_option) {
      $id_option = $this->getCurrent();
    }

    if (Str::isUid($id_option)) {
      return $id_option;
    }

    return null;
  }


}
