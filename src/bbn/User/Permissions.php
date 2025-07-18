<?php
/**
 * @package user
 */
namespace bbn\User;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\User;
use bbn\Db;
use bbn\Mvc;
use bbn\User\Preferences;
use bbn\Appui\Option;
use bbn\File\System;
use bbn\Models\Cls\Basic;
use bbn\Models\Tts\Retriever;
use bbn\Models\Tts\Current;
/**
 * A permission system linked to options, User classes and preferences.
 *
 * A permission is an option under the permission option ("permissions", "appui") or one of its aliases.
 * They are ONLY permissions.
 *
 * No(bool)! From the moment a user or a group has a preference on an item, it is considered to have a permission.
 * No(bool)! Deleting a permission deletes the preference
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

class Permissions extends Basic
{
  use Retriever;
  use Current;

  /** @var Option */
  protected $opt;

  /** @var Preferences */
  protected $pref;

  /** @var User */
  protected $user;

  /** @var Db */
  protected $db;

  /** @var array */
  protected $plugins = [];

  /** @var array */
  protected $allowedRoutes = [];

  /** @var array */
  protected $forbiddenRoutes = [];


  /**
   * Permissions constructor.
   *
   * @param array $routes An array of routes to the plugins
   */
  public function __construct(array|null $routes = null)
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

    /** @todo Add the default routes from Mvc::getInstance */
    if (empty($routes)) {
      $mvc    = Mvc::getInstance();
      $routes = $mvc->getRoutes();
    }

    if ($routes) {
      if (!empty($routes['root'])) {
        foreach ($routes['root'] as $url => $plugin) {
          $plugin['url']   = $url;
          $this->plugins[] = $plugin;
        }
      }

      if (!empty($routes['allowed']) && is_array($routes['allowed'])) {
        $this->allowedRoutes = $routes['allowed'];
      }

      if (!empty($routes['forbidden']) && is_array($routes['forbidden'])) {
        $this->forbiddenRoutes = $routes['forbidden'];
      }
    }

    self::retrieverInit($this);
    $this->db = Db::getInstance();
  }


  public function isAuthorizedRoute($url): bool
  {
    if (in_array($url, $this->allowedRoutes, true)) {
      return true;
    }

    foreach ($this->allowedRoutes as $ar) {
      if (substr($ar, -1) === '*') {
        if ((strlen($ar) === 1) || (strpos($url, substr($ar, 0, -1)) === 0)) {
          if (in_array($url, $this->forbiddenRoutes, true)) {
            return false;
          }

          foreach ($this->forbiddenRoutes as $ar2) {
            if (substr($ar2, -1) === '*') {
              if (strpos($url, substr($ar2, 0, -1)) === 0) {
                return false;
              }
            }
          }

          return true;
        }
      }
    }

    return false;
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
  public function fromPathInfo(string $path): ?array
  {
    $bits = X::split(trim($path, ' /'), '/');
    $remain = [];
    while ($path) {
      if ($id = $this->fromPath($path)) {
        return [
          'id'    => $id,
          'path'  => $path,
          'param' => X::join($remain, '/')
        ];
      }
      array_unshift($remain, array_pop($bits));
      $path = X::join($bits, '/');
    }

    return null;
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
    $parent = null;
    $root   = false;
    $old_path = $path;
    if (($type === 'access') && $this->plugins && !empty($path)) {
      foreach ($this->plugins as $plugin) {
        if (strpos($path, $plugin['url'].'/') === 0) {
          if (strpos($plugin['name'], 'appui-') === 0) {
            $root = $this->opt->fromCode(
              $type,
              'permissions',
              substr($plugin['name'], 6),
              'appui',
              'plugins'
            );
            $path = substr($path, strlen($plugin['url']) + 1);
          }
          elseif ($plugin['name']) {
            $root = $this->opt->fromCode(
              $type,
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
      $root = $this->opt->fromCode($type, 'permissions');
    }

    if (!$root) {
      throw new Exception(X::_("Impossible to find the permission code for %s as %s", $path, $type));
    }

    $parts  = explode('/', trim($path, '/'));
    $parent = $root;

    $path = '';
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
            $prev_parent,
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
    if ($parents = $this->opt->parents($id_option)) {
      $idPlugin = $this->opt->getTemplateId('plugin');
      $prefix = '';
      foreach ($parents as $i => $p) {
        if ($this->opt->getIdAlias($p) === $idPlugin) {
          $codes = $this->opt->getCodePath($id_option);
          if (!is_array($codes)) {
            throw new Exception("No array for path in $id_option");
          }

          $path = array_slice($codes, 0, $i - 1);
          if ($p !== $this->opt->getDefault()) {
            $isOk = true;
            while ($isOk) {
              $prefix = $this->opt->code($p) . ($prefix ? '-' . $prefix : '');
              $i++;
              $p = $parents[$i] ?? null;
              $isOk = $p && ($code = $this->opt->code($p)) && ($code !== 'plugins');
            }

            $plugin = X::getRow($this->plugins, ['name' => $prefix]);
            $prefix = $plugin['url'] . '/';
          }

          return $prefix.X::join(array_reverse($path), '');
        }
      }
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
  public function options(string|null $id_option = null, string $type = 'access'): ?array
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
  public function fullOptions(string|null $id_option = null, string $type = 'access'): ?array
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
  public function getAll(string|null $id_option = null, string $type = 'access'): ?array
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
  public function get(string|null $id_option = null, string $type = 'access', bool $force = false): ?array
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
  public function has(string|null $id_option = null, string $type = 'access', bool $force = false): bool
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
  public function getApplicableCfg(string $id_option): ?array
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

    //X::ddump($root);
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
  public function readOption(string|null $id_option = null, bool $force = false): ?bool
  {
    if ($this->user->isAdmin()) {
      return true;
    }

    if ($id_perm = $this->optionToPermission($id_option)) {
      return $this->pref->has($id_perm, $force) ?: $this->user->isAdmin();
    }

    return false;
  }


  /**
   * Checks if the given option is writable by the current user.
   *
   * @param string|null $id_option
   * @return bool|null
   */
  public function writeOption(string $id_option, bool $force = false): ?bool
  {
    if ($this->user->isAdmin()) {
      return true;
    }

    if ($id_perm = $this->optionToPermission($id_option)) {
      $p = $this->pref->get($id_perm);
      if (is_array($p) && isset($p['write']) && $p['write']) {
        return true;
      }
    }

    return false;
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
    $root    = $this->opt->fromCode('permissions');
    $access  = $this->opt->fromCode('access', $root);
    $options = $this->opt->fromCode('options', $root);
    $sources = [];
    $all     = $this->opt->getPlugins(null, true);
    foreach ($all as $o) {
      if ($id_perm = $this->opt->fromCode('access', $o['rootPermissions'])) {
        $id_option = $this->opt->fromCode('options', $o['rootPermissions']);
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

    X::sortBy($sources, 'text');
    array_unshift($sources, [
      'text' => _("Main application"),
      'rootAccess' => $access,
      'rootOptions' => $options,
      'code' => ''
    ]);

    return $sources;
  }


  /**
   * Returns the closest Plugin > Permissions > Options root for the given option.
   *
   * @param string $name
   *
   * @return string|null
   */
  public function optionPermissionRoot(string $id): ?string
  {
    if ($idSubplugin = $this->opt->getParentSubplugin($id)) {
      return $this->opt->fromCode('permissions', $idSubplugin);
    }
    if ($idPlugin = $this->opt->getParentPlugin($id)) {
      return $this->opt->fromCode('options', 'permissions', $idPlugin);
    }

    return $this->opt->fromCode('options', 'permissions');
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
    $idPlugin = $this->opt->getTemplateId('plugin');
    $parents = $this->opt->parents($id_perm);
    $pluginName = '';
    foreach ($parents as $i => $p) {
      if ($this->opt->getIdAlias($p) === $idPlugin) {
        $access = $parents[$i-2];
        if ($idPlugin === $this->opt->getDefault()) {
          $isOk = true;
          $current = $p;
          while ($isOk) {
            $pluginName = $this->opt->code($p) . ($pluginName ? '-' : '') . $pluginName;
            $i++;
            $current = $parents[$i];
            $isOk = $this->opt->parent($current)['code'] === 'plugins';
          }
        }
        break;
      }
    }

    if (!empty($access)) {
      $path_to_file = $this->opt->toPath($id_perm, '', $access);
      if ($pluginName) {
        if (substr($path_to_file, -1) === '/') {
          return is_dir(Mvc::getPluginPath($pluginName).'mvc/public/'.substr($path_to_file, 0, -1));
        }

        return file_exists(Mvc::getPluginPath($pluginName).'mvc/public/'.$path_to_file.'.php');
      }
      else {
            if (substr($path_to_file, -1) === '/') {
        return is_dir(Mvc::getAppPath().'mvc/public/'.substr($path_to_file, 0, -1));
      }

      return file_exists(Mvc::getAppPath().'mvc/public/'.$path_to_file.'.php');
}
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
      array_push($args, substr($name, 6), 'appui', 'plugins');
    }
    else {
      array_push($args, $name, 'plugins');
    }

    return $this->opt->fromCode(...$args);
  }


  /**
   * Updates all access permission for the given path in the given root.
   *
   * @param string $path    The path to look for files in (mustn't include mvc/public)
   * @param string $root    The ID of the root access option
   * @param string $url     The path part of the URL root of the given absolute path
   * @param array  $res The part of the absolute path corresponding to the url
   *
   * @return array
   */
  public function accessUpdatePath(
      string $path,
      string $root,
      string $url = '',
      array $res = []
  ): array
  {
    if (!empty($url) && (substr($url, -1) !== '/')) {
      $url .= '/';
    }

    $num = 0;
    $fs  = new System();
    $ff  = function ($a) use ($url, $path) {
      if (empty($url)) {
        $a['path'] = substr($a['name'], strlen(Mvc::getAppPath() . 'mvc/public/'));
      }
      else {
        $a['path'] = $url.substr($a['name'], strlen($path.'mvc/public/'));
      }

      if (substr($a['path'], -4) === '.php') {
        $a['path'] = substr($a['path'], 0, -4);
      }

      return $this->fFilter($a);
    };

    $res = [];
    if ($all = $fs->getTree($path.'mvc/public', '', false, $ff)) {
      $all = self::fTreat($all, false);
      usort($all, ['\\bbn\User\\Permissions', 'fSort']);
      array_walk($all, ['\\bbn\\User\\Permissions', 'fWalk']);

      foreach ($all as $i => $it) {
        $it['cfg'] = json_encode(['order' => $i + 1]);
        $this->_add($it, $root, $url, $res);
      }
    }

    return $res;
  }


  /**
   * Updates all access permission for the main app.
   *
   * @return array|null
   */
  public function accessUpdateApp(): ?array
  {
    if ($id_page = $this->opt->fromCode('access', 'permissions')) {
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

  public function getOptionRoot()
  {
    return $this->opt->fromCode('access', 'permissions');
  }

  /**
   * update All
   *
   * @param array $routes
   *
   * @return void
   */
  public function updateAll(array $routes)
  {
    $this->opt->deleteCache();

    $res = ['total' => 0];

    /** @var string The option's ID of the permissions on pages (controllers) $id_page */
    $id_page = $this->opt->fromCode('access', 'permissions');

    /** @var string The option's ID of the permissions on pages (controllers) $id_page */

    // The app base access
    if ($id_page) {

      /** @todo Add the possibility to do it for another project? */
      $idPluginsTemplate = $this->opt->getPluginsTemplateId();
      $idPluginTemplate = $this->opt->getPluginTemplateId();
      $aliases = $this->opt->getAliasFullOptions($idPluginTemplate);
      $aliasesByName = [];
      // Each plugin, including the main app
      foreach ($aliases as $a) {
        $pluginGroup = $this->opt->closest($a['id'], $idPluginsTemplate);
        if ($pluginGroup) {
          $name = $this->opt->toPath($a['id'], '-', $pluginGroup);
        }
        elseif ($a['code'] === constant('BBN_APP_NAME')) {
          $name = $a['code'];
        }

        if ($name) {
          $aliasesByName[$name] = $a;
        }

      }

      if (!empty($routes)) {
        foreach ($routes as $url => $route) {
          if (!isset($aliasesByName[$route['name']])) {
            $err = X::_("Impossible to find the plugin %s", $route['name']);
            X::log($err, 'errorUpdatePermissions');
            throw new Exception($err);
          }

          $root = $this->opt->fromCode('access', 'permissions', $aliasesByName[$route['name']]['id']);
          if (!$root) {
            $err = X::_("Impossible to find the plugin %s", $route['name']);
            X::log($err, 'errorUpdatePermissions');
            throw new Exception($err);
          }

          $res['data'] = $this->accessUpdatePath($route['path'], $root, $url);
        }
      }

      //$res['total'] += $this->optionsUpdateAll();

      $this->opt->deleteCache();
    }

    return $res;

  }


  public function createFromId(string $id): ?string
  {
    $opt = $this->opt->option($id);
    if (!$opt) {
      X::log($this->opt->option($id));
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
    // appui or plugins or neither (main app)
    $root_original = array_shift($parents);

    // Looking for the root of the options' permissions
    if (($num > 2) && \in_array($root_original, [$appui, $plugins])) {

      // Plugin inside a plugin
      if (($num > 4) && ($this->opt->code($parents[1]) === 'plugins')) {

        /** @var string  */
        $id_plugin = array_shift($parents);
        // dropping 'plugins'
        array_shift($parents);

        $root_parent = $this->opt->fromCode('plugins', 'permissions', $id_plugin);
        if (!$root_parent) {
          throw new Exception("Impossible to find a parent for plugin's permission ".$id_plugin);
        }

        $id_subplugin = array_shift($parents);
        $subplugin  = $this->opt->code($id_subplugin);
        if ($root_original === $appui) {

        }
        $alias = $this->opt->fromCode(
          'options',
          'permissions',
          substr($subplugin, $root_original === $appui ? 6: 0),
          $root_original
        );

        $id_root = $this->opt->fromCode($subplugin, $root_parent);

        if (!$id_root) {
          $id_root = $this->opt->add([
            'id_parent' => $root_parent,
            'code' => $subplugin,
            'text' => $subplugin,
            'id_alias' => $alias
          ]);
        }

      }
      else {
        $id_root = $this->opt->fromCode('options', 'permissions', array_shift($parents));
      }
    }
    else {
      $id_root = $this->opt->fromCode('options', 'permissions');
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
      if (!$cfg['permissions'] ) {
        $parent_option = $this->opt->option($p);
        if (!empty($parent_option['alias'])) {
          $scfg = $this->opt->getCfg($parent_option['id_alias']);
          if ($scfg['permissions']) {
            $id_parent = $p;
          }
        }
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

    $id_parent = $id_parent_perm ?: $id_root;
    foreach (array_reverse($perm_parents) as $a) {
      $id_parent = $this->opt->add(
        [
          'id_parent' => $id_parent,
          'id_alias'  => $a
        ]
      );
    }

    return $this->opt->add(
      [
        'id_parent' => $id_parent,
        'id_alias'  => $id
      ]
    );
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


  public function fFilter(array $a): bool
  {
    if (!empty($a['num'])
      || ((substr($a['name'], -4) === '.php')
          && (X::basename($a['name']) !== '_super.php'))
    ) {
      if (!$this->isAuthorizedRoute($a['path'])) {
        return true;
      }
    }

    return false;
  }


  public static function fTreat(array $tree, $parent = false)
  {
    $res = [];
    foreach ($tree as $i => $t){
      $code      = $t['type'] === 'dir' ? X::basename($t['name']).'/' : X::basename($t['name'], '.php');
      $text      = $t['type'] === 'dir' ? X::basename($t['name']) : X::basename($t['name'], '.php');
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
  private function _add($o, $id_parent, string $url = '', array &$res = []): int
  {
    $total = 0;
    $items = isset($o['items']) ? $o['items'] : false;
    unset($o['items']);
    $path = $url . $o['code'];
    $o['id_parent'] = $id_parent;
    if (!($id = $this->opt->fromCode($o['code'], $id_parent))) {
      if ($id = $this->opt->add($o)) {
        $res[$id] = $path;
      }
    }

    /* No(bool)!
    else if ( isset($o['cfg']) ){
      $this->opt->set($id, $o);
    }
    */
    if (\is_array($items)) {
      foreach ($items as $it){
        if (substr($path, -1) !== '/') {
          $path .= '/';
        }

        $total += $this->_add($it, $id, $path, $res);
      }
    }

    return $total;
  }


  /**
   * @param string|null $id_option
   * @param string      $type
   * @return null|string
   */
  private function _get_id_option(string|null $id_option = null, $type = 'access'): ?string
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
