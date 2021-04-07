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
 *
 * A permission is an optioon under the permission option ("permissions", "appui") or one oof its aliases.
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
   * permissions constructor.
   */
  public function __construct(array $routes = null)
  {
    if (!($this->opt = bbn\Appui\Option::getInstance())) {
      throw new \Exception(X::_('Impossible to construct permissions: you need to instantiate options before'));
    }

    if (!($this->user = bbn\User::getInstance())) {
      throw new \Exception(X::_('Impossible to construct permissions: you need to instantiate user before'));
    }

    if (!($this->pref = Preferences::getInstance())) {
      throw new \Exception(X::_('Impossible to construct permissions: you need to instantiate preferences before'));
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
      throw new \Exception(X::_("Impossible to find the permission code"));
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
  public function options(string $id_option = null, string $type = 'access'): ?array
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
  public function fullOptions(string $id_option = null, string $type = 'access'): ?array
  {
    if (($id_option = $this->_get_id_option($id_option, $type))
        && ($os = $this->opt->fullOptions(\func_get_args()))
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
   * @param null|string $id_option
   * @param string      $type
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
   * @param null|string $id_option
   * @param string      $type
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
   * @param string $id_option
   * @param string $type
   * @param bool   $force
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
   * @param mixed  $id_option
   * @param string $type
   * @param bool   $force
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
   * @param mixed  $id_option
   * @param string $type
   * @param bool   $force
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
   * Checks if an option corresponds to the given path.
   *
   * @param string $path
   * @param string $type
   * @return null|string
   */
  public function is(string $path, string $type = 'access'): ?string
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
   * @param null|string $id_option
   * @param string      $type
   * @return null|int
   */
  public function remove($id_option, string $type = 'access'): ?int
  {
    if ($id_option = $this->_get_id_option($id_option, $type)) {
      return $this->pref->delete($id_option);
    }

    return null;
  }


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


  public function optionToPermission(string $id_option, bool $create = false): ?string
  {
    if (bbn\Str::isUid($id_option)) {
      $aliases = $this->opt->getAliasItems($id_option);
      $root    = self::getOptionId('options');
      $id_perm = null;
      $all     = [];
      foreach ($aliases as $a) {
        $parents = $this->opt->parents($a);
        $all[]   = $parents;
        if (in_array($root, $parents)) {
          $id_perm = $a;
          break;
        }
      }

      if (!$id_perm) {
        foreach ($aliases as $i => $a) {
          foreach ($all[$i] as $b) {
            if ($this->opt->alias($b) === $root) {
              $id_perm = $a;
              break;
            }
          }
        }
      }

      if (!$id_perm && $create) {
        $this->createFromId($id_option);
        return $this->optionToPermission($id_option);
      }

      return $id_perm ?: null;
    }
  }


  /**
   * Checks if the category represented by the given option ID is readable by the current user
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
   * Checks if the category represented by the given option ID is writable by the current user
   *
   * @param string|null $id_option
   * @return bool|null
   */
  public function writeOption(string $id_option, bool $force = false): ?bool
  {
    return $this->readOption($id_option, $force);
  }


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
      array_map(function($a) {
        $a['code'] = 'appui-'.$a['code'];
        return $a;
      }, $this->opt->fullOptions($appui)),
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


  public function accessExists(string $id_perm): bool
  {
    $real    = false;
    $parents = array_reverse($this->opt->parents($id_perm));
    $access  = $this->opt->fromCode('access', 'permissions', 'appui');
    if (in_array($access, $parents, true)) {
      $path_to_file = $this->opt->toPath($id_perm, '', $access);
      if (substr($path_to_file, -1) === '/') {
        return is_dir(bbn\Mvc::getAppPath().'mvc/public/'.substr($path_to_file, 0, -1));
      }

      return file_exists(bbn\Mvc::getAppPath().'mvc/public/'.$path_to_file.'.php');
    }
    else {
      $plugin_name = $this->opt->code($parents[2]);
      if ($this->opt->code($parents[1]) === 'appui') {
        $plugin_name = 'appui-'.$plugin_name;
      }

      $path_to_file = $this->opt->toPath($id_perm, '', $parents[4]);
      if (substr($path_to_file, -1) === '/') {
        return is_dir(bbn\Mvc::getPluginPath($plugin_name).'mvc/public/'.substr($path_to_file, 0, -1));
      }

      return file_exists(bbn\Mvc::getPluginPath($plugin_name).'mvc/public/'.$path_to_file.'.php');
    }

    return false;
  }


  public function updateAll(array $routes)
  {
    $this->opt->deleteCache();

    $res = ['total' => false];

    /** @var string The ID option for permissions < appui */
    if ($id_permission = $this->getOptionRoot()) {
      /** @var string The option's ID for appui */
      $appui = $this->opt->fromCode('appui');

      /** @var string The option's ID for plugins */
      $plugins = $this->opt->fromCode('plugins');

      /** @var string The option's ID of the permissions on options $id_option */
      $id_option = $this->getOptionId('options');

      /** @var string The option's ID of the permissions on pages (controllers) $id_page */
      $id_page = $this->getOptionId('access');

      /** @var string The option's ID of the permissions on pages (controllers) $id_page */
      $id_plugins = $this->getOptionId('plugins');

      /** @var function Filter php files */
      $fn = function ($a) {
        return !empty($a['num'])
          || ((substr($a['name'], -4) === '.php')
              && (basename($a['name']) !== '_ctrl.php'));
      };

      // The app base access
      if ($id_page) {

        /** @todo Add the possibility to do it for another project? */
        $fs = new bbn\File\System();

        $res['total'] = 0;
        // All files in public from the main app
        if ($all = $fs->getTree(bbn\Mvc::getAppPath().'mvc/public', '', false, $fn)) {
          $all = $this->_treat($all);
          usort($all, [$this, '_sort']);
          array_walk($all, [$this, '_walk']);
          foreach ($all as $i => &$it){
            $it['cfg']     = json_encode(['order' => $i + 1]);
            $res['total'] += $this->_add($it, $id_page);
          }

          unset($it);
        }

        if (!empty($routes)) {
          foreach ($routes as $url => $route) {
            $root = false;
            if (strpos($route['name'], 'appui-') === 0) {
              $root = $this->opt->fromCode('access', 'permissions', substr($route['name'], 6), 'appui');
            }
            elseif (!($root = $this->opt->fromCode($route['name'], $id_plugins))) {
              $root = $this->opt->add(
                [
                  'text' => $route['name'],
                  'code' => $route['name'],
                  'id_parent' => $id_plugins
                ]
              );
            }

            if (!$root) {
              $err = X::_(
                "Impossible to find the plugin %s",
                substr($route['name'], 6)
              );
              X::log($err, 'updatePermissions');
              continue;
              throw new \Exception($err);
            }

            if ($all = $fs->getTree(
              $route['path'].'src/mvc/public',
              '',
              false,
              $fn
            )
            ) {
              $all = $this->_treat($all);
              usort($all, [$this, '_sort']);
              array_walk($all, [$this, '_walk']);
              foreach ($all as $i => &$it){
                $it['cfg']     = json_encode(['order' => $i + 1]);
                $res['total'] += $this->_add($it, $root);
              }

              unset($it);
            }
          }
        }
      }

      $cf = $this->opt->getClassCfg();
      // $id_option must be set to generate the option's permissions
      $tmp = $this->db->getColumnValues([
        'table' => $cf['table'],
        'fields' => [$cf['arch']['options']['id']],
        'join' => [
          [
            'table' => $cf['table'],
            'alias' => 'parent_option',
            'type' => 'left',
            'on' => [
              [
                'field' => 'parent_option.'.$cf['arch']['options']['id'],
                'exp' => $cf['table'].'.'.$cf['arch']['options']['id_parent']
              ], [
                'field' => 'parent_option.'.$cf['arch']['options']['cfg'],
                'operator' => 'doesnotcontain',
                'value' => '"permissions":'
              ]
            ]
          ]
        ],
        'where' => [
          [
            'field' => $cf['table'].'.'.$cf['arch']['options']['cfg'],
            'operator' => 'contains',
            'value' => '"permissions":'
          ], [
            'field' => $cf['table'].'.'.$cf['arch']['options']['id'],
            'operator' => '!=',
            'value' => $this->opt->getRoot()
          ], [
            'field' => $cf['table'].'.'.$cf['arch']['options']['id'],
            'operator' => '!=',
            'value' => $this->opt->fromCode('appui')
          ], [
            'field' => $cf['table'].'.'.$cf['arch']['options']['id'],
            'operator' => '!=',
            'value' => $this->opt->fromCode('plugins')
          ], [
            'field' => 'parent_option.'.$cf['arch']['options']['id'],
            'operator' => 'isnull'
          ]
        ]
      ]);

      if ($id_option && $tmp) {
        $permissions = [];
        foreach ($tmp as $id) {
          /** @var array The option's config */
          $cfg = $this->opt->getCfg($id) ?: [];
          if (!empty($cfg['permissions'])) {
            $permissions[$id] = $cfg['permissions'];
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
          $parents = array_reverse($this->opt->parents($id));
          // Looking for the root of the options' permissions
          if ((count($parents) > 2) && \in_array($parents[1], [$appui, $plugins])) {
            $root = $this->opt->fromCode('options', 'permissions', $parents[2]);
          }
          else {
            $root = $id_option;
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
              if ($tmp = $this->opt->fullTreeRef($id)) {
                $it = $tmp;
              }
              break;
            case 'children':
            case 1:
            case '1':
              if ($tmp = $this->opt->fullOptionsRef($id)) {
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
            $res['total'] += $this->create($all[0]);
          }
        }
      }

      $this->opt->deleteCache();
    }

    return $res;

  }


  public function createFromId(string $id): ?int
  {
    // The option
    if ($opt = $this->opt->option($id)) {
      // All the parents from the closest
      $parents = $this->opt->parents($id);
      // The farest parent having permissions set
      $id_parent = false;
      foreach ($parents as $i => $p) {
        $cfg = $this->opt->getCfg($p);
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
      }
      // If no parent with permissions this option must have it
      if (!$id_parent) {
        $cfg = $this->opt->getCfg($id);
        if (!$cfg['permissions']) {
          return null;
        }

        /** @var string The option's ID for appui */
        $appui = $this->opt->fromCode('appui');

        /** @var string The option's ID for plugins */
        $plugins = $this->opt->fromCode('plugins');

        /** @var string The option's ID of the permissions on options $id_option */
        $id_option = $this->getOptionId('options');
        // we need to locate the root of the permission
        $rparents = array_reverse($parents);
        // Looking for the root of the options' permissions
        if ((count($rparents) > 2) && \in_array($rparents[1], [$appui, $plugins])) {
          $id_perm = $this->opt->fromCode('options', 'permissions', $rparents[2]);
        }
        else {
          $id_perm = $id_option;
        }
        
      }
      // Otherwise the parent permission needs to exist
      elseif (!($id_perm = $this->optionToPermission($id_parent))) {
        return null;
      }

      return $this->opt->add([
        'id_parent' => $id_perm,
        'id_alias' => $id
      ]) ? 1 : 0;
    }

    return null;
  }


  public function create(array $item): ?int
  {
    X::log($item, "permissions");
    if (X::hasProps($item, ['id_parent', 'id_alias'], true)) {
      $cf       = $this->opt->getClassCfg();
      $res      = 0;
      $subitems = false;
      $id       = $this->db->selectOne(
        $cf['table'],
        $cf['arch']['options']['id'],
        [
          $cf['arch']['options']['id_parent'] => $item['id_parent'],
          $cf['arch']['options']['id_alias'] => $item['id_alias']
        ]
      );
      if (!empty($item['items'])) {
        $subitems = $item['items'];
        unset($item['items']);
      }

      if (!$id) {
        $id = $this->opt->add($item);
        if ($id) {
          $res++;
        }
      }

      if ($id && $subitems) {
        //die(var_dump($subitems, $item));
        foreach ($subitems as $it) {
          $it['id_parent'] = $id;
          $res             += (int)$this->create($it);
        }
      }

      return $res;
    }

    return null;

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
