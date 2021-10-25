<?php

namespace bbn;

use bbn\Util\Enc;

/**
 * The class which deals with App-UI configuration.
 */
class Appui
{

  /** @var array List of the variables transformed in BBN_ constants */
  protected static $vars = [
    "env",
    "is_ssl",
    "admin_email",
    "db_engine",
    "db_host",
    "database",
    "db_user",
    "db_pass",
    "server_name",
    "hostname",
    "public",
    "cur_path",
    "app_path",
    "lib_path",
    "data_path",
    "log_path",
    "user",
    "preferences",
    "permissions",
    "options",
    "history",
    "encryption_key",
    "fingerprint",
    "app_name",
    "app_prefix",
    "site_title",
    "client_name"
  ];

  /** @var bool  */
  private $_checked = null;

  /** @var string */
  private $_settings_file;

  /** @var string */
  private $_environment_file;

  /** @var string */
  private $_routes_file;

  /** @var array */
  private $_settings;

  /** @var array */
  private $_environment;

  /** @var array */
  private $_routes;

  /** @var array */
  private $_current = [];

  /** @var Db */
  private $_currentDb;


  /**
   * Constructor
   */
  public function __construct(array $cfg = null)
  {
    $this->setConfig($cfg);
  }


  /**
   * Undocumented function
   *
   * @param array|null $cfg
   * @return void
   */
  public function setConfig(array $cfg = null)
  {
    $this->_current = [];
    $this->_currentDb = null;
    $has_cfg = (bool)$cfg;
    foreach (self::$vars as $v) {
      if ($has_cfg) {
        $this->_current[$v] = $cfg[$v] ?? null;
      }
      elseif (defined('\\BBN_'.strtoupper($v))) {
        $this->_current[$v] = constant('\\BBN_'.strtoupper($v));
      }
    }
  }


  /**
   * Returns the path to the main application
   *
   * @return string|null
   */
  public function appPath(bool $raw = false): ?string
  {
    if (isset($this->_current['app_path'])) {
      return $this->_current['app_path'].($raw ? '' : 'src/');
    }

    return null;
  }


  /**
   * Returns the path to the libraries
   *
   * @return string|null
   */
  public function libPath(): ?string
  {
    return $this->_current['lib_path'] ?? null;
  }


  /**
   * Returns the path to the data
   *
   * @return string|null
   */
  public function dataPath(): ?string
  {
    return $this->_current['data_path'] ?? null;
  }



  public function getDb(): ?Db
  {
    if (!$this->_currentDb) {
      $this->_currentDb = new Db([
        'engine' => $this->_current['db_host'],
        'host' => $this->_current['db_host'] ?? '',
        'user' => $this->_current['db_user'] ?? '',
        'pass' => $this->_current['db_pass'] ?? '',
        'error_mode' => 'continue'
      ]);
    }

    return $this->currentDb;
  }


  /**
   * Checks just once whether or not all needed constant have been defined
   *
   * @param bool $throwError wil throw an error if set to true instead of returning false
   *
   * @throws \Exception
   * @return bool
   */
  public function check(bool $throwError = false): bool
  {
    if ($throwError || is_null($this->_checked)) {
      $ok = true;
      foreach (self::$vars as $v) {
        if (empty($this->_current[$v])) {
          if ($throwError) {
            throw new \Exception(X::_("The parameter %s is not defined", $v));
          }

          $ok = false;
        }
      }
  
      $this->_checked = $ok;
    }

    return $this->_checked;
  }


  /**
   * Gets the settings of the current project.
   *
   * @return array|null
   */
  public function getSettings(): ?array
  {
    if ($this->check()) {
      if (empty($this->_settings)
          && ($file = $this->getSettingsFile())
          && ($content = file_get_contents($file))
      ) {
        if (substr($file, -4) === '.yml') {
          $this->_settings = yaml_parse($content);
        }
        else {
          $this->_settings = json_decode($content, true);
        }
      }

      return $this->_settings;
    }

    return null;
  }


  /**
   * Gets the settings of the current project.
   *
   * @return array|null
   */
  public function getRoutes(): ?array
  {
    if ($this->check()) {
      if (empty($this->_settings)
          && ($file = $this->getRoutesFile())
          && ($content = file_get_contents($file))
      ) {
        if (substr($file, -4) === '.yml') {
          $this->_routes = yaml_parse($content);
        }
        else {
          $this->_routes = json_decode($content, true);
        }
      }

      return $this->_routes;
    }

    return null;
  }


  /**
   * Gets the index for the given environment.
   *
   * @param string $hostname The hostname of the environment.
   * @param string $servname The URL of the environment.
   *
   * @return string|null
   */
  public function getEnvironmentIndex(string $hostname = null, string $servname = null): ?string
  {
    if ($this->check()) {
      if (empty($hostname) && empty($servname)) {
        if (!defined('BBN_HOSTNAME')) {
          throw new \Exception(X::_("No hostname defined"));
        }

        if (!defined('BBN_SERVER_NAME')) {
          throw new \Exception(X::_("No server name defined"));
        }

        $hostname = BBN_HOSTNAME;
        $servname = BBN_SERVER_NAME;
      }

      return md5($hostname.$servname);
    }

    return null;

  }


  /**
   * Returns the environment variables of an app from the current project.
   *
   * @param string $hostname The hostname of the environment.
   * @param string $servname The URL of the environment.
   *
   * @return array
   */
  public function getEnvironment($hostname = null, string $servname = null): ?array
  {
    if ($this->check()) {
      if ($hostname !== true) {
        $idx = $this->getEnvironmentIndex($hostname, $servname);
      }

      if (empty($this->_environment)
          && ($file = $this->getEnvironmentFile())
          && ($content = file_get_contents($file))
      ) {
        if (substr($file, -4) === '.yml') {
          $envs = yaml_parse($content);
        }
        else {
          $envs = json_decode($content, true);
        }

        $_env =& $this->_environment;
        foreach ($envs as $i => $env) {
          $md5        = $this->getEnvironmentIndex($env['hostname'], $env['server_name']);
          $_env[$md5] = [
            'index' => $i,
            'data' => $env
          ];
        }
      }

      return $hostname === true ? array_values(
        array_map(
          function ($a) {
            return $a['data'];
          },
          $this->_environment
        )
      ) : (
        isset($this->_environment[$idx]) ? $this->_environment[$idx]['data'] : null
      );
    }

    return null;
  }


  /**
   * Get the routes file location.
   *
   * @return string|null
   */
  public function getRoutesFile(): ?string
  {
    if ($this->check()) {
      if (empty($this->_routes_file)) {
        $app_path = $this->appPath();
        if (function_exists('\\yaml_parse') && is_file($app_path.'cfg/settings.yml')) {
          $this->_routes_file = $app_path.'cfg/settings.yml';
        }
        elseif (is_file($app_path.'cfg/settings.json')) {
          $this->_routes_file = $app_path.'cfg/settings.json';
        }
      }

      return $this->_routes_file;
    }

    return null;
  }


  /**
   * Get the settings file location.
   *
   * @return string|null
   */
  public function getSettingsFile(): ?string
  {
    if ($this->check()) {
      if (empty($this->_settings_file)) {
        $app_path = $this->appPath();
        if (function_exists('\\yaml_parse') && is_file($app_path.'cfg/settings.yml')) {
          $this->_settings_file = $app_path.'cfg/settings.yml';
        }
        elseif (is_file($app_path.'cfg/settings.json')) {
          $this->_settings_file = $app_path.'cfg/settings.json';
        }
      }

      return $this->_settings_file;
    }

    return null;
  }


  /**
   * Get the environment file location.
   *
   * @return string|null
   */
  public function getEnvironmentFile(): ?string
  {
    if ($this->check()) {
      if (empty($this->_environment_file)) {
        $app_path = $this->appPath();
        if (function_exists('\\yaml_parse') && is_file($app_path.'cfg/settings.yml')) {
          $this->_environment_file = $app_path.'cfg/environment.yml';
        }
        elseif (is_file($app_path.'cfg/settings.json')) {
          $this->_environment_file = $app_path.'cfg/environment.json';
        }
      }

      return $this->_environment_file;
    }

    return null;
  }


  /**
   * Set environment vars
   *
   * @param array  $update   The new values to be added
   * @param string $hostname The environment's hostname
   * @param string $servname The environment's server name
   * @param bool   $replace  True if the whole value should be replaced by the new one
   *
   * @return bool
   */
  public function setEnvironment(
      array $update,
      string $hostname = null,
      string $servname = null,
      bool $replace = false
  ) : bool
  {
    $idx = $this->getEnvironmentIndex($hostname, $servname);
    if (isset($this->_environment[$idx])) {
      if ($replace) {
        $this->_environment[$idx]['data'] = $update;
      }
      else {
        $this->_environment[$idx]['data'] = array_merge(
          $this->_environment[$idx]['data'],
          $update
        );
      }

      $file = $this->getEnvironmentFile();
      $envs = $this->getEnvironment(true);
      if (substr($file, -4) === '.yml') {
        unlink($file);
        return (bool)\yaml_emit_file($file, $envs);
      }
      else {
        return (bool)\file_put_contents($file, json_encode($envs, JSON_PRETTY_PRINT));
      }
    }

    return false;
  }


  /**
   * Set settings vars
   *
   * @param array $update  The new values to be added
   * @param bool  $replace True if the whole value should be replaced by the new one
   *
   * @return bool
   */
  public function setSettings(array $update, $replace = false) : bool
  {
    if ($this->getSettings()) {
      if ($replace) {
        $this->_settings = $update;
      }
      else {
        $this->_settings = array_merge(
          $this->_settings,
          $update
        );
      }

      $file = $this->getSettingsFile();
      if (substr($file, -4) === '.yml') {
        return (bool)\yaml_emit_file($file, $this->_settings);
      }
      else {
        return (bool)\file_put_contents($file, json_encode($this->_settings, JSON_PRETTY_PRINT));
      }
    }
    else {
      throw new \Exception(X::_("Impossible to get the settings"));
    }

    return false;
  }


  /**
   * Replaces an environment with another, if no hostname and servname is given the default environment will be used.
   *
   * @param array $update
   * @param string|null $hostname
   * @param string|null $servname
   * @return bool
   */
  public function replaceEnvironment(array $update, string $hostname = null, string $servname = null): bool
  {
    return $this->setEnvironment($update, $hostname, $servname, true);
  }


  /**
   * Replaces the settings with another set of options
   *
   * @param array $update
   * @return bool
   */
  public function replaceSettings(array $update): bool
  {
    return $this->setSettings($update, true);
  }


  /**
   * Creates a plugin in the database or check its existence and returns its ID.
   *
   * @param string $name If starts with appui it will be created in appui, otherwise in plugins
   * @param string|null $title
   * @return null|string
   */
  public function addPlugin(string $name, string $title = null): ?string
  {
    $id_plugin = null;
    if (!$title) {
      $title = $name;
    }

    /** @var Appui\Option */
    $o       = Appui\Option::getInstance();
    $isAppui = substr($name, 0, 6) === 'appui-';
    $name    = $isAppui ? substr($name, 6) : $name;
    $params  = $isAppui ? ['appui'] : ['plugins'];
    if ($id_parent = $o->fromCode(...$params)) {
      array_unshift($params, $name);
      if ($id = $o->fromCode(...$params)) {
        $id_plugin = $id;
      }
      else {
        $id_plugin = $o->add(
          [
          'code' => $name,
          'text' => $title,
          'id_parent' => $id_parent
          ]
        );
        if (!$id_plugin) {
          throw new \Exception(X::_("Impossible to add the plugin")." $name");
        }

        $perm_id = $o->add(
          [
          'id_parent' => $id_plugin,
          'code' => 'permissions',
          'text' => 'Permissions'
          ]
        );
        if (!$perm_id) {
          throw new \Exception(X::_("Impossible to add the permission for the plugin")." $name");
        }

        // Other options under permissions
        $o->add(
          [
          'id_parent' => $perm_id,
          'code' => 'options',
          'text' => 'Options'
          ]
        );
        $o->add(
          [
          'id_parent' => $perm_id,
          'code' => 'plugins',
          'text' => 'Plugins'
          ]
        );
      }
    }

    return $id_plugin;
  }


  /**
   * Replaces plugins names by path and *(project)* by the real project name
   *
   * @param string $st
   * @return string
   */
  public function replaceMagicStrings(string $st): string
  {
    // Function for replacing *(project)* by the real project name
    $magic_strings = [
      '/\|\*(project)\*\|/' => BBN_APP_NAME,
    ];
    $routes        = $this->getRoutes();
    $plugins_urls  = $routes['root'];
    $st            = preg_replace_callback(
      '/\|\*(appui-[a-z]+)\*\|/',
      function ($a) use ($plugins_urls) {
        return X::getField($plugins_urls, ['name' => $a[1]], 'url');
      },
      $st
    );

    foreach ($magic_strings as $exp => $val) {
      $string = preg_replace($exp, $val, $st);
    }

    return $string;
  }


  /**
   * Returns all the default data for Db structure, options, menus, permissions from all plugins.
   *
   * @return array
   */
  public function gatherUpdateInfo(): array
  {
    $tables              = [];
    $menus               = [];
    $plugins_options     = [];
    $plugins_permissions = [];
    $routes              = $this->getRoutes();
    $lib_path            = $this->libPath();

    foreach ($routes['root'] as $url => $plugin) {
      if (is_dir($lib_path.'bbn/'.$plugin['name'].'/src/cfg')) {
        $path = $lib_path.'bbn/'.$plugin['name'].'/src/cfg/';
        // Database file
        if (file_exists($path.'database.json')) {
          $db_file = file_get_contents($path.'database.json');
          if ($list = json_decode($db_file, true)) {
            foreach ($list as $t => $it) {
              $tables[$t] = $it;
            }
          }
        }

        /** @todo For the moment all options are in core */
        // Options file
        if (('appui-core' !== $plugin['name'])
            && ('appui-options' !== $plugin['name'])
            && file_exists($path.'nononononono.json')
            //&& file_exists(BBN_LIB_PATH.'bbn/'.$p.'/src/cfg/options.json')
        ) {
          if (($file = file_get_contents($path.'options.json'))
              && ($file = $this->replaceMagicStrings($file))
              && ($list = json_decode($file, true))
          ) {
            if (X::isAssoc($list)) {
                $plugins_options[] = $list;
            }
            else {
              $plugins_options = array_merge($plugins_options, $list);
            }
          }
          else {
            throw new \Exception(X::_("The options file in %s is corrupted", $plugin['name']));
          }
        }

        // Permissions file
        if (file_exists($path.'permissions.json')) {
          if (($file = file_get_contents($path.'permissions.json'))
              && ($file = $this->replaceMagicStrings($file))
              && ($list = json_decode($file, true))
          ) {
            if (X::isAssoc($list)) {
              $plugins_permissions[] = $list;
            }
            else {
              $plugins_permissions = array_merge($plugins_permissions, $list);
            }
          }
          else {
            throw new \Exception(X::_("The permissions file in %s is corrupted", $plugin['name']));
          }
        }

        if (file_exists($path.'menu.json')) {
          $menu_file = file_get_contents($path.'menu.json');
          if ($list = json_decode($menu_file, true)) {
            foreach ($list['items'] as &$it) {
              $it['link'] = $url.'/'.$it['link'];
            }

            unset($it);
            $menus[$plugin['name']] = $list;
          }
          else {
            throw new \Exception(X::_("The database file in %s is corrupted", $plugin['name']));
          }
        }
      }
    }

    // Correcting the menus' sort order
    X::sortBy($menus, 'num');
    foreach ($menus as $i => &$m) {
      $m['num'] = $i + 1;
    }
    unset($m);

    return [
      'tables' => $tables,
      'menus' => $menus,
      'plugins_options' => $plugins_options,
      'plugins_permissions' => $plugins_permissions
    ];
  }


  /**
   * Returns the path of the main RSA public key of the application
   *
   * @param bool $create
   * @return string|null
   */
  public function getPublicKey(bool $create = false): ?string
  {
    if ($this->check()) {
      $path = $this->appPath().'cfg/cert';
      if (!is_file($path.'_rsa.pub') && $create) {
        try {
          Enc::generateCertFiles($path);
        }
        catch (\Exception $e) {
          throw new \Exception(X::_("Failed to create SSL certificate").': '.$e->getMessage());
        }
      }

      if (is_file($path.'_rsa.pub')) {
        return $path.'_rsa.pub';
      }

      return null;
    }
  }


  /**
   * Creates a database with given name based on the given structure.
   *
   * @param Db $db
   * @param string $db_name
   * @param array $tables
   * @return int
   */
  public function createDatabase(Db $db, string $db_name, array $tables): int
  {
    $constraints = [];
    $i  = 0;
    $st = strtolower(Str::genpwd(4));
    foreach ($tables as $table => $structure) {
      foreach ($structure['keys'] as $k => $cfg) {
        if (!empty($cfg['constraint'])) {
          ++$i;
          $tables[$table]['keys'][$k]['constraint'] = "bbn_constraint_{$st}_{$i}";
        }

        if (!empty($cfg['ref_table'])) {
          if (!isset($tables[$cfg['ref_table']])) {
            $tables[$table]['keys'][$k]['ref_db']     = null;
            $tables[$table]['keys'][$k]['ref_table']  = null;
            $tables[$table]['keys'][$k]['ref_column'] = null;
          }
          else {
            $tables[$table]['keys'][$k]['ref_db'] = $db_name;
          }

          if ('bbn_history_uids' === $cfg['ref_table']) {
            if (!isset($constraints[$table])) {
              $constraints[$table] = [];
            }

            if (!isset($constraints[$table][$k])) {
              $constraints[$table][$k] = $tables[$table]['keys'][$k];
            }

            $tables[$table]['keys'][$k]['ref_db']     = null;
            $tables[$table]['keys'][$k]['ref_table']  = null;
            $tables[$table]['keys'][$k]['ref_column'] = null;
          }
        }
      }
    }

    $queries = [
      'table' => [],
      'keys' => [],
      'constraints' => [],
    ];
    // Creating queries for tables, keys and constraint creation
    foreach (array_keys($queries) as $type) {
      $fn = 'getCreate'.ucwords($type);
      foreach ($tables as $table => $structure) {
        if ($tmp = $db->{$fn}($table, $structure)) {
          $queries[$type][$table] = $tmp;
        }
      }
    }
    die(var_dump($queries));

    // creates the Database
    $db->createDatabase($db_name);
    if (!$db->change($db_name)) {
      throw new \Exception(X::_("The database %s doesn't exist", $db_name));
    }

    // Getting the existing tables
    $current_tables = $db->getTables() ?: [];
    $num            = 0;
    foreach ($queries as $type => $arr) {
      foreach ($arr as $table => $q) {
        if (!empty($q)) {
          if (('table' === $type) && !in_array($table, $current_tables, true)) {
            $db->query($q);
            $db_err = $db->getLastError();
            if ($db_err) {
              throw new \Exception($db_err);
            }
            else {
              $num++;
            }
          }
        }
      }
    }

    return $num;
  }


  /**
   * Returns the id_group for the given code, creating the group if needed.
   *
   * @param string $code
   * @return string|null
   */
  public function getUserGroup(string $code): ?string
  {
    $id_group = null;
    if ($this->check()
        && ($db = $this->getDb())
        && !($id_group = $db->selectOne(
          'bbn_users_groups', 'id', [
          'code' => $code,
          ]
        ))
        && $db->insert(
          'bbn_users_groups',
          [
            'group' => 'Administrators',
            'code' => 'admin',
          ]
        )
      ) {
        $id_group = $db->lastId();
      }

    return $id_group;
  }


  /**
   * Returns the admin user's ID and creates it if it doesn't exist.
   *
   * @param [type] $password
   * @return string|null
   */
  public function getAdminUser($password): ?string
  {
    $id_user = null;
    if (($db = $this->getDb())
        && !($id_user = $db->selectOne(
          'bbn_users',
          'id',
          ['login' => $this->current['admin_email']]
        ))
        && $db->insert(
          'bbn_users',
          [
            'username' => $this->current['admin_name'],
            'email' => $this->current['admin_email'],
            'login' => $this->current['admin_email'],
            'id_group' => $this->getUserGroup('admin'),
            'admin' => 1,
            'dev' => 1,
            'theme' => $this->current['theme'] ?? 'default',
          ]
        )
    ) {
      $id_user = $db->lastId();
      $db->insert(
        'bbn_users_passwords',
        [
          'pass' => sha1($password),
          'id_user' => $id_user,
          'added' => date('Y-m-d H:i:s'),
        ]
      );
    }

    return $id_user;
  }


}
