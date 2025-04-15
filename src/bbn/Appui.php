<?php

namespace bbn;

use Exception;
use stdClass;

use bbn\File\System;
use bbn\Util\Enc;
use bbn\Db;
use bbn\User;
use bbn\User\Preferences;
use bbn\User\Permissions;
use bbn\Appui\Menu;
use bbn\Appui\Option;
use bbn\Appui\Passwords;
use bbn\Appui\Api;
use bbn\Appui\Dashboard;
use bbn\Appui\Database;
use bbn\Models\Itf\Installer;
use Generator;

/**
 * The class which deals with App-UI configuration.
 */
class Appui
{

  private const LOGO = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAADTAAAA0wB/Z14fgAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAKXSURBVFiF5ZcxT+pQFIC/FgUWTTSuJLA41sGFhQSMkKjAr1CCJsbJTU0b2N35HWjiRFzVSHRwMBEqMJG2JjoRE+rgow+wLQUxJu+dsb33fF/PPbe9FW5ubkx+McTfhP8fAoZhcHl5+TsChmGwu7vL4eEh5+fntmNmfhKez+ep1WoAyLIMwObm5sC4H6nAMByg2+0iy/KXSkxdwA7uJuEoMD8/z8zMeCvkBh+WODs7Axx6YGFhgUgkQqfT4fHxkff396nA+yUURQFsKtCDC4JAMBhkeXmZ2dlZ14SappHL5TzB+yWur68HBfrhvRglYRgGe3t7qKrqGQ6QSqU4Pj7+K2AHHyUxTtmH4YVCAZ/P9yngBneSmAYcQPQCH5Z4fX2dGF4sFi04gKDrurm4uOg5SavVIh6P8/T0NBFcFAf7XlRVlZeXF09JGo0GsVjMgvv9fk/z0um0LRxANE2Ter2OYRiuSZrNJmtra1a37+zscHV1xdLSkuu8TCbDycmJLRz+vAdM02RUJfL5vPXkuVyOUqnEysoKFxcXOC1hJpP53GoOcACh/0gmCALhcNg2oa7rJBIJotEopVJp4N7d3R3r6+tommZdy2azHB0ducK/CIySeHt7Y25uzjbR7e0tqVQKXdc9w20FnCQ6nQ6BQMA1WbVa5fT0lP39fU9wcPga9nqi15iKorC6ukq73XZNFgqFODg48AwHhwpYNwWBarXK9vY2AJIkUalUbJdH0zSen589g3vhqmqaJpIkWceo+/t7YrHYl0pMCh8pACCKIrIsWxIPDw8kEglL4jtwTwL9EhsbG5ZEMpmkXq9/C+5ZoCehKApbW1sAhMPhgX0/abg2oV10u13K5TLpdHqsbp+awLTj3/83HBUfhqFVwjxEB4YAAAAASUVORK5CYII=";

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

  /** @var bool  */
  private $_is_installer = false;

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
  private $_dbFilesContent;

  /** @var array */
  private $_optionFilesContent;

  /** @var array */
  private $_permissionFilesContent;

  /** @var array */
  private $_menuFilesContent;

  /** @var array */
  private $_dashboardFilesContent;

  /** @var array */
  private $_structureFilesContent;

  /** @var array */
  private $_current = [];

  /** @var Db */
  private $_currentDb;

  /** @var Option */
  private $_currentOption;

  /** @var User */
  private $_currentUser;

  /** @var Preferences */
  private $_currentPref;

  /** @var Permissions */
  private $_currentPerm;

  /** @var System */
  private $_currentFs;

  /** @var Passwords */
  private $_currentPass;

  /** @var Menu */
  private $_currentMenu;

  /** @var array */
  private $_info;

  public static function getLogo()
  {
    return self::LOGO;
  }


  /**
   * Constructor
   *
   * @param null|array       $cfg An initial configuration
   * @param null|System $fs  A File System connection for the given config.
   */
  public function __construct(array|null $cfg = null, ?System $fs = null)
  {
    $this->setConfig($cfg, $fs);
  }


  /**
   * Returns all the names of the constant according to this class configuration
   *
   * @return array
   */
  public static function getConstantNames(): array
  {
    $res = [];
    foreach (self::$vars as $v) {
      $res[] = 'BBN_' . strtoupper($v);
    }

    return $res;
  }


  /**
   * Check that all the constants are defined to this class configuration
   *
   * @return bool
   */
  public static function checkConstantNamesExist(): bool
  {
    foreach (self::getConstantNames() as $v) {
      if (!defined($v)) {
        return false;
      }
    }

    return true;
  }


  /**
   * Unsets all the object created for the current environment if any
   *
   * @return void
   */
  public function unsetConfig()
  {
    $this->_current                = [];
    $this->_currentPerm            = null;
    $this->_currentPref            = null;
    $this->_currentPass            = null;
    $this->_currentMenu            = null;
    $this->_currentOption          = null;
    $this->_currentUser            = null;
    $this->_currentDb              = null;
    $this->_currentFs              = null;
    $this->_dbFilesContent         = null;
    $this->_optionFilesContent     = null;
    $this->_permissionFilesContent = null;
    $this->_menuFilesContent       = null;
    $this->_info                   = null;
  }


  public function getConfig()
  {
    return $this->_current;
  }

  /**
   * Sets the whole current config.
   *
   * @param array|null       $cfg An application configuration
   * @param System|null $fs  A filesystem object accessing the config path
   * @return void
   */
  public function setConfig(?array $cfg = null, ?System $fs = null)
  {
    $this->unsetConfig();
    $this->_currentFs = $fs ?? new System();
    $has_cfg          = (bool)$cfg;
    foreach (self::$vars as $v) {
      if ($has_cfg) {
        $this->_current[$v] = $cfg[$v] ?? null;
      }
      elseif (defined('\\BBN_'.strtoupper($v))) {
        $this->_current[$v] = constant('\\BBN_'.strtoupper($v));
      }
    }

    $c =& $this->_current;

    $url = 'http';
    if ($c['is_ssl']) {
      $url .= 's';
    }

    $url .= '://' . $c['server_name'];
    if (isset($c['port']) && !in_array($c['port'], [80, 443])) {
      $url .= ':'.$c['port'];
    }

    if (!empty($c['cur_path'])) {
      $url .= $c['cur_path'];
    }

    if (substr($url, -1) !== '/') {
      $url .= '/';
    }

    $this->_current['url'] = $url;
  }


  /**
   * Returns the path to the main application
   *
   * @param bool $raw If false, src/ is appended to the app_path var.
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


  /**
   * Returns a Db object according to the current config, creates it if needed
   *
   * @todo Attach history if configured
   * @return Db|null
   */
  public function getDb(): ?Db
  {
    if (!$this->_currentDb) {
      if (!$this->_is_installer && X::isDefined('BBN_DB_HOST', 'BBN_DB_ENGINE', 'BBN_DB_USER')
          && ($this->_current['db_host'] == constant('BBN_DB_HOST'))
          && ($this->_current['db_engine'] == constant('BBN_DB_ENGINE'))
          && ($this->_current['db_user'] == constant('BBN_DB_USER'))
      ) {
        $db = Db::getInstance();
      }
      else {
        $db = new Db(
          [
            'engine' => $this->_current['db_engine'],
            'host' => $this->_current['db_host'] ?? '',
            'user' => $this->_current['db_user'] ?? '',
            'pass' => $this->_current['db_pass'] ?? '',
            'error_mode' => 'continue'
          ]
        );
      }

      if ($db->check()) {
        if (!empty($this->_current['database'])) {
          $db->change($this->_current['database']);
        }

        $this->_currentDb = $db;
      }
    }

    return $this->_currentDb;
  }


  /**
   * Returns a Db object according to the current config, creates it if needed
   *
   * @return Option | null
   */
  public function getOption(): ?Option
  {
    if (!$this->_currentOption) {
      $this->_currentOption = new Option($this->getDb());
    }

    return $this->_currentOption;
  }


  /**
   * Returns a special User class which connects directly as admin.
   *
   * @return User
   */
  public function getUser(): User
  {
    if ($this->_currentUser) {
      return $this->_currentUser;
    }

    $this->_currentUser = new class($this->getDb(), $this->_current) extends User {

      private $_cfg;


      /**
       * Constructor which logs in directly the admin after regular construction.
       *
       * @param Db    $db  The database connection corresponding to the current configuration
       * @param array $cfg A configuration array with admin_email and admin_password
       */
      public function __construct(Db $db, array $cfg)
      {
        parent::__construct($db, $cfg);
        $this->_cfg = $cfg;
        $this->logAdminIn();
      }


      /**
       * Logs in the admin user.
       *
       * @return void
       */
      public function logAdminIn(): void
      {
        $id_user = $this->db->selectOne('bbn_users', 'id', ['email' => $this->_cfg['admin_email']]);
        if ($id_user) {
          $this->logIn($id_user);
          return;
        }

        throw new Exception("Impossible to fiond the admin user");
      }


    };

    return $this->_currentUser;
  }


  /**
   * Returns a Db object according for the current config, creates it if needed
   *
   * @return Preferences
   */
  public function getPreferences(): ?Preferences
  {
    if (!$this->_currentPref) {
      $this->_currentPref = new Preferences($this->getDb());
    }

    return $this->_currentPref;
  }


  /**
   * Returns a Db object according for the current config, creates it if needed
   *
   * @return Permissions
   */
  public function getPermissions(): ?Permissions
  {
    if (!$this->_currentPerm) {
      $routes             = $this->getRoutes();
      $user               = $this->getUser();
      $preferences        = $this->getPreferences();
      $this->_currentPerm = new Permissions($routes);
    }

    return $this->_currentPerm;
  }


  /**
   * Returns a Password object according for the current config, creates it if needed
   *
   * @return Passwords
   */
  public function getPassword(): Passwords
  {
    if (!$this->_currentPass) {
      $this->_currentPass = new Passwords($this->getDb());
    }

    return $this->_currentPass;
  }


  /**
   * Returns a menu instance accordin to the current configuration
   *
   * @return Menu
   */
  public function getMenu(): Menu
  {
    if (!$this->_currentMenu) {
      $this->_currentMenu = new Menu();
    }

    return $this->_currentMenu;

  }


  /**
   * Checks just once whether or not all needed constant have been defined
   *
   * @param bool $throwError wil throw an error if set to true instead of returning false
   *
   * @throws Exception
   * @return bool
   */
  public function check(bool $throwError = false): bool
  {
    if ($throwError || is_null($this->_checked)) {
      $ok = true;
      foreach (self::$vars as $v) {
        if (!array_key_exists($v, $this->_current)) {
          if ($throwError) {
            throw new Exception(X::_("The parameter %s is not defined", $v));
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
          && ($content = $this->_currentFs->getContents($file))
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
      if (empty($this->_routes)
          && ($file = $this->getRoutesFile())
          && ($content = $this->_currentFs->getContents($file))
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
  public function getEnvironmentIndex(string|null $hostname = null, string|null $servname = null): ?string
  {
    if ($this->check()) {
      if (empty($hostname) && empty($servname)) {
        if (!defined('BBN_HOSTNAME')) {
          throw new Exception(X::_("No hostname defined"));
        }

        if (!defined('BBN_SERVER_NAME')) {
          throw new Exception(X::_("No server name defined"));
        }

        $hostname = constant('BBN_HOSTNAME');
        $servname = constant('BBN_SERVER_NAME');
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
  public function getEnvironment($hostname = null, string|null $servname = null): ?array
  {
    if ($this->check()) {
      if ($hostname !== true) {
        $idx = $this->getEnvironmentIndex($hostname, $servname);
      }

      if (empty($this->_environment)
          && ($file = $this->getEnvironmentFile())
          && ($content = $this->_currentFs->getContents($file))
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
        if (function_exists('\\yaml_parse')
            && $this->_currentFs->isFile($app_path.'cfg/routes.yml')
        ) {
          $this->_routes_file = $app_path.'cfg/routes.yml';
        }
        elseif ($this->_currentFs->isFile($app_path.'cfg/routes.json')) {
          $this->_routes_file = $app_path.'cfg/routes.json';
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
        if (function_exists('\\yaml_parse')
            && $this->_currentFs->isFile($app_path.'cfg/settings.yml')
        ) {
          $this->_settings_file = $app_path.'cfg/settings.yml';
        }
        elseif ($this->_currentFs->isFile($app_path.'cfg/settings.json')) {
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
        if (function_exists('\\yaml_parse')
            && $this->_currentFs->isFile($app_path.'cfg/environment.yml')
        ) {
          $this->_environment_file = $app_path.'cfg/environment.yml';
        }
        elseif ($this->_currentFs->isFile($app_path.'cfg/environment.json')) {
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
      string|null $hostname = null,
      string|null $servname = null,
      bool $replace = false
  ): bool
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
        $content = \yaml_emit($envs);
      }
      else {
        $content = json_encode($envs, JSON_PRETTY_PRINT);
      }

      return (bool)$this->_currentFs->putContents($file, $content);
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
        $content = \yaml_emit($this->_settings);
      }
      else {
        $content = json_encode($this->_settings, JSON_PRETTY_PRINT);
      }

      return (bool)$this->_currentFs->putContents($file, $content);
    }
    else {
      throw new Exception(X::_("Impossible to get the settings"));
    }

    return false;
  }


  /**
   * Replaces an environment with another
   *
   * @param array       $update   The new environment config
   * @param string|null $hostname A hostname, if none given default env value wwill be used
   * @param string|null $servname A server name, if none given default env value wwill be used
   * @return bool
   */
  public function replaceEnvironment(
      array $update,
      string|null $hostname = null,
      string|null $servname = null
  ): bool
  {
    return $this->setEnvironment($update, $hostname, $servname, true);
  }


  /**
   * Replaces the settings with another set of options
   *
   * @param array $update The new settings
   * @return bool
   */
  public function replaceSettings(array $update): bool
  {
    return $this->setSettings($update, true);
  }


  /**
   * Creates a plugin in the database or check its existence and returns its ID.
   *
   * @param string      $name  Created in appui if starts with `appui-`, otherwise in plugins
   * @param string|null $title The title of the plugin
   * @return null|string
   */
  public function addPlugin(string $name, string|null $title = null): ?string
  {
    $id_plugin = null;
    if (!$title) {
      $title = $name;
    }

    /** @var Option */
    $o = $this->getOption();
    if (!is_dir($this->libPath() . "/$name")) {
      mkdir($this->appPath() . "plugins/$name");
    }

    if (substr($name, 0, 6) === 'appui-') {
      $name   = substr($name, 6);
      $params = ['appui'];
    }
    else {
      $name   = $name;
      $params = ['plugins'];
    }

    if ($id_parent = $o->fromCode(...$params)) {
      array_unshift($params, $name);
      if ($id = $o->fromCode(...$params)) {
        $id_plugin = $id;
      }
      else {
        $pluginTemplate = $o->fromCode('plugin', 'list', 'templates', 'options', 'appui');
        $id_plugin = $o->add(
          [
            'code' => $name,
            'text' => $title,
            'id_parent' => $id_parent,
            'id_alias' => $pluginTemplate
          ]
        );

        if (!$id_plugin) {
          throw new Exception(X::_("Impossible to add the plugin")." $name");
        }
      }
    }

    return $id_plugin;
  }


  /**
   * Replaces plugins names by path and *(project)* by the real project name
   *
   * @param string $st The string nwhere to replace the values
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
   * Returns the constraints on the history_uids table
   *
   * @return array
   */
  public function getHistoryConstraints(): array
  {
    $tables      = $this->getDbFilesContent();
    $constraints = [];
    $i           = 0;
    $st          = strtolower(Str::genpwd(4));
    foreach ($tables as $table => $structure) {
      foreach ($structure['keys'] as $k => $cfg) {
        if (!empty($cfg['ref_column'])) {
          ++$i;
          $tables[$table]['keys'][$k]['constraint'] = "bbn_constraint_{$st}_{$i}";
        }

        if (!empty($cfg['ref_table']) && ('bbn_history_uids' === $cfg['ref_table'])) {
          if (!isset($constraints[$table])) {
            $constraints[$table] = [];
          }

          if (!isset($constraints[$table][$k])) {
            $constraints[$table][$k] = $tables[$table]['keys'][$k];
          }
        }
      }
    }

    return $constraints;
  }


  /**
   * Returns an array of tables with their structures from the database.json files in all plugins.
   *
   * @return array
   */
  public function getDbFilesContent(): array
  {
    if (!$this->_dbFilesContent) {
      $routes   = $this->getRoutes();
      $tables   = [];
      foreach ($routes['root'] as $url => $plugin) {
        $fn = $plugin['root'] . 'Path';
        if (method_exists($this, $fn)) {
          $path = $this->$fn() . $plugin['path'] . '/src/cfg/';
          if ($this->_currentFs->exists($path.'database.json')) {
            if ($list = $this->_currentFs->decodeContents($path.'database.json', 'json', true)) {
              foreach ($list as $t => $it) {
                $tables[$t] = $it;
              }
            }
            else {
              throw new Exception(X::_("Unreadable database file in plugin %s", $plugin['name']));
            }
          }
        }
        else {
          throw new Exception(X::_("Impossible to recognize the root in plugin %s", $plugin['name']));
        }
      }

      $this->_dbFilesContent = $tables;
    }

    return $this->_dbFilesContent;
  }


  /**
   * Returns an array of tables with their structures from the database.json files in all plugins.
   *
   * @return array
   */
  public function getStructureFilesContent(): array
  {
    if (!$this->_structureFilesContent) {
      $routes   = $this->getRoutes();
      $tables   = [];
      foreach ($routes['root'] as $url => $plugin) {
        $fn = $plugin['root'] . 'Path';
        if (method_exists($this, $fn)) {
          $path = $this->$fn() . $plugin['path'] . '/src/cfg/';
          if ($this->_currentFs->exists($path.'database.json')) {
            if ($list = $this->_currentFs->decodeContents($path.'database.json', 'json', true)) {
              foreach ($list as $t => $it) {
                $tables[$t] = $it;
              }
            }
            else {
              throw new Exception(X::_("Unreadable database file in plugin %s", $plugin['name']));
            }
          }
        }
        else {
          throw new Exception(X::_("Impossible to recognize the root in plugin %s", $plugin['name']));
        }
      }

      $this->_structureFilesContent = $tables;
    }

    return $this->_structureFilesContent;
  }


  /**
   * Returns an array of tables with their structures from the database.json files in all plugins.
   *
   * @todo The options files need to be dispatched again through the plugins
   * @return array
   */
  public function getOptionFilesContent(): array
  {
    if (!$this->_optionFilesContent) {
      $routes   = $this->getRoutes();
      $options  = [];
      foreach ($routes['root'] as $url => $plugin) {
        $fn = $plugin['root'] . 'Path';
        if (method_exists($this, $fn)) {
          $path = $this->$fn() . $plugin['path'] . '/src/cfg/';
          if (('appui-core' !== $plugin['name'])
              && ('appui-options' !== $plugin['name'])
              && $this->_currentFs->exists($path.'options.json')
              /* && file_exists(BBN_LIB_PATH.'bbn/'.$p.'/src/cfg/options.json') */
          ) {
            if ($list = $this->_currentFs->decodeContents($path.'options.json', 'json', true)) {
              if (X::isAssoc($list)) {
                $options[] = $list;
              }
              else {
                $options = array_merge($options, $list);
              }
            }
            else {
              throw new Exception(X::_("The options file in %s is corrupted", $plugin['name']));
            }
          }
        }
        else {
          throw new Exception(X::_("Impossible to recognize the root in plugin %s", $plugin['name']));
        }
      }

      $this->_optionFilesContent = $options;
    }

    return $this->_optionFilesContent;
  }


  /**
   * Returns an array of tables with their structures from the database.json files in all plugins.
   *
   * @return array
   */
  public function getPermissionFilesContent(): array
  {
    if (!$this->_permissionFilesContent) {
      $routes   = $this->getRoutes();
      $perms    = [];
      foreach ($routes['root'] as $url => $plugin) {
        $fn = $plugin['root'] . 'Path';
        if (method_exists($this, $fn)) {
          $path = $this->$fn() . $plugin['path'] . '/src/cfg/';
          if ($this->_currentFs->exists($path.'permissions.json')) {
            if ($list = $this->_currentFs->decodeContents($path.'permissions.json', 'json', true)) {
              if (X::isAssoc($list)) {
                $perms[] = $list;
              }
              else {
                $perms = array_merge($perms, $list);
              }
            }
            else {
              throw new Exception(X::_("The permission file in %s is corrupted", $plugin['name']));
            }
          }
        }
        else {
          throw new Exception(X::_("Impossible to recognize the root in plugin %s", $plugin['name']));
        }
      }

      $this->_permissionFilesContent = $perms;
    }

    return $this->_permissionFilesContent;
  }


  /**
   * Returns an array of menus with their structures from the menu.json files in all plugins.
   *
   * @return array
   */
  public function getMenuFilesContent(): array
  {
    if (!$this->_menuFilesContent) {
      $routes   = $this->getRoutes();
      $menus    = [];
      foreach ($routes['root'] as $url => $plugin) {
        $fn = $plugin['root'] . 'Path';
        if (method_exists($this, $fn)) {
          $path = $this->$fn() . $plugin['path'] . '/src/cfg/';
          if ($this->_currentFs->exists($path . 'menu.json')) {
            if ($list = $this->_currentFs->decodeContents($path.'menu.json', 'json', true)) {
              if (!empty($list['items'])) {
                foreach ($list['items'] as &$it) {
                  $it['link'] = $url.'/'.$it['link'];
                }
                unset($it);
                $menus[$plugin['name']] = $list;
              }
              else {
                X::log("Problem in {$path} menu.json");
              }
            }
            else {
              throw new Exception(X::_("The menu file in %s is corrupted", $plugin['name']));
            }
          }
        }
        else {
          throw new Exception(X::_("Impossible to recognize the root in plugin %s", $plugin['name']));
        }
      }

      // Correcting the menus' sort order
      X::sortBy($menus, 'num');
      foreach ($menus as $i => &$m) {
        $m['num'] = $i + 1;
      }

      unset($m);
      $this->_menuFilesContent = $menus;
    }

    return $this->_menuFilesContent;
  }


  /**
   * Returns an array of dashboards with their structures from the dashboards.json files in all plugins.
   *
   * @return array
   */
  public function getDashboardFilesContent(): array
  {
    if (!$this->_dashboardFilesContent) {
      $routes     = $this->getRoutes();
      $dashboards = [];
      foreach ($routes['root'] as $url => $plugin) {
        $fn = $plugin['root'] . 'Path';
        if (method_exists($this, $fn)) {
          $path = $this->$fn() . $plugin['path'] . '/src/cfg/';
          if ($this->_currentFs->exists($path . 'dashboards.json')) {
            if ($list = $this->_currentFs->decodeContents($path.'dashboards.json', 'json', true)) {
              foreach ($list as $i => $item) {
                if (empty($item['items'])
                  || empty($item['code'])
                ) {
                  continue;
                }
                foreach ($item['items'] as $k => $it) {
                  if (\is_array($it['id_option'])) {
                    $list[$i]['items'][$k]['id_option'] = $this->getOption()->fromCode(...$it['id_option']);
                  }
                  if (!Str::isUid($list[$i]['items'][$k]['id_option'])) {
                    unset($list[$i]['items'][$k]);
                  }
                }
                if (!isset($dashboards[$item['code']])) {
                  $dashboards[$item['code']] = [
                    'text' => $item['text'],
                    'code' => $item['code'],
                    'items' => []
                  ];
                }
                $dashboards[$item['code']]['items'] = X::mergeArrays($dashboards[$item['code']]['items'], $list[$i]['items']);
              }

            }
            else {
              throw new Exception(X::_("The dashboards file in %s is corrupted", $plugin['name']));
            }
          }
        }
        else {
          throw new Exception(X::_("Impossible to recognize the root in plugin %s", $plugin['name']));
        }
      }

      $this->_dashboardFilesContent = $dashboards;
    }

    return $this->_dashboardFilesContent;
  }


  /**
   * Returns an array of tables with their whole structure, keys included
   *
   * @return array
   */
  public function getDatabaseStructure(): array
  {
    $tables = $this->getDbFilesContent();
    $i      = 0;
    $st     = strtolower(Str::genpwd(4));
    foreach ($tables as $table => $structure) {
      foreach ($structure['keys'] as $k => $cfg) {
        if (!empty($cfg['ref_column'])) {
          ++$i;
          $tables[$table]['keys'][$k]['constraint'] = "bbn_constraint_{$st}_{$i}";
        }

        if (!empty($cfg['ref_table'])) {
          if (!isset($tables[$cfg['ref_table']]) || ('bbn_history_uids' === $cfg['ref_table'])) {
            $tables[$table]['keys'][$k]['ref_db']     = null;
            $tables[$table]['keys'][$k]['ref_table']  = null;
            $tables[$table]['keys'][$k]['ref_column'] = null;
          }
          else {
            $tables[$table]['keys'][$k]['ref_db'] = $this->_current['database'];
          }
        }
      }
    }

    return $tables;
  }


  /**
   * Returns an array of queries for creating the database
   *
   * @return array
   */
  public function getDatabaseCreationQueries(): array
  {
    $db      = $this->getDb();
    $tables  = $this->getDatabaseStructure();
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

    return $queries;
  }


  /**
   * Returns the path of the main RSA public key of the application
   *
   * @param bool $create If true will create it if it doesn't exist
   * @return string|null
   */
  public function getPublicKey(bool $create = false): ?string
  {
    if ($this->check()) {
      $path = $this->appPath().'cfg/cert';
      if (!$this->_currentFs->isFile($path.'_rsa.pub') && $create) {
        try {
          Enc::generateCertFiles($path);
        }
        catch (Exception $e) {
          throw new Exception(X::_("Failed to create SSL certificate").': '.$e->getMessage());
        }
      }

      if ($this->_currentFs->isFile($path.'_rsa.pub')) {
        return $path.'_rsa.pub';
      }
    }

    return null;
  }


  /**
   * Creates a database with given name based on the given structure.
   *
   * @return int
   */
  public function createDatabase(): int
  {
    $db = $this->getDb();
    // creates the Database
    $db->createDatabase($this->_current['database']);
    if (!$db->change($this->_current['database'])) {
      throw new Exception(X::_("The database %s doesn't exist", $this->_current['database']));
    }

    // Getting the existing tables
    $current_tables = $db->getTables() ?: [];
    $queries        = $this->getDatabaseCreationQueries();
    $num            = 0;
    foreach ($queries as $type => $arr) {
      foreach ($arr as $table => $q) {
        if (!empty($q) && (('table' !== $type) || !in_array($table, $current_tables, true))) {
          $current_tables[] = $table;
          $db->query($q);
          $db_err = $db->getLastError();
          if ($db_err) {
            throw new Exception($db_err);
          }
          elseif ('table' === $type) {
            $num++;
          }
        }
      }
    }

    return $num;
  }


  /**
   * Returns the id_group for the given code, creating the group if needed.
   *
   * @param string $code The group's code
   * @param string $name The group's name
   * @param string $type The group's type
   * @return string|null
   */
  public function getUserGroup(string $code, string $name, $type = 'real'): ?string
  {
    $id_group = null;
    if ($this->check()
        && ($db = $this->getDb())
        && !($id_group = $db->selectOne(
          'bbn_users_groups', 'id', [
          'code' => $code,
          'type' => $type
          ]
        ))
        && $db->insert(
          'bbn_users_groups',
          [
            'group' => $name,
            'code' => $code,
            'type' => $type
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
   * @param string $name     The admin's name
   * @param string $password The password if needed to create it
   * @return string|null
   */
  public function getAdminUser(string $name, string $password): ?string
  {
    $id_user = null;
    if (($db = $this->getDb())
        && !($id_user = $db->selectOne(
          'bbn_users',
          'id',
          ['login' => $this->_current['admin_email']]
        ))
        && $db->insert(
          'bbn_users',
          [
            'username' => $name,
            'email' => $this->_current['admin_email'],
            'login' => $this->_current['admin_email'],
            'id_group' => $this->getUserGroup('admin', 'Administrators'),
            'admin' => 1,
            'dev' => 1,
            'theme' => $this->_current['theme'] ?? 'default',
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


  /**
   * Returns the options' root's ID and creates it if it doesn't exist.
   *
   * @throws Exception
   * @return string|null
   */
  public function getOptionRoot(): string
  {
    $db = $this->getDb();
    $id = $db->selectOne(
      'bbn_options',
      'id',
      [
        'code' => 'root',
        'id_parent' => null
      ]
    );

    if (!$id && $db->insert(
        'bbn_options',
        [
          'id_parent' => null,
          'code' => 'root',
          'text' => 'root'
        ]
      )
    ) {
      $id = $db->lastId();
      $this->getOption()->deleteCache();
    }

    if (!$id) {
      throw new Exception("Impossible to create the root option");
    }

    return $id;
  }


  /**
   * Returns the ID of the main client, and creates if not exist.
   *
   * @param string $name The name of the client
   * @return string|null
   */
  public function getClient(string $name): string
  {
    $id_client = null;
    if (($db = $this->getDb())
        && !($id_client = $db->selectOne('bbn_clients', 'id', ['name' => $name]))
        && $db->insert('bbn_clients', ['name' => $name])
    ) {
      $id_client = $db->lastId();
    }

    if (!$id_client) {
      throw new Exception("Impossible to create the client");
    }

    return $id_client;
  }


  /**
   * Returns the ID of the main app project, and creates if not exist.
   *
   * @todo Check what is the right id_project: from table options or projects?
   * @return string|null
   */
  public function getProject(): string
  {
    $opt = $this->getOption();
    $id_project_list = $opt->fromCode('list', 'project', 'appui');
    if (!($id_project = $opt->fromCode($this->_current['app_name'], $id_project_list))) {
      $id_project = $opt->add(
        [
          'id_parent' => $id_project_list,
          'id_alias' => ['project', 'templates', 'project', 'appui'],
          'text' => $this->_current['app_name'],
          'code' => $this->_current['app_name'],
        ]
      );
      $opt->applyTemplate($id_project);
      $opt->deleteCache();
      $idPath = $opt->fromCode('app', 'path', $id_project);
      X::log($idPath, 'idPath');
      $idAliasPath = $opt->fromCode("bbn-project", "types", "ide", "appui");
      $opt->add([
        'id_parent' => $idPath,
        'id_alias' => $idAliasPath,
        'text' =>  "main",
        'code' =>  "main",
        'path' =>  "/",
        'bcolor' =>  "#0c1b71",
        'fcolor' =>  "#fdfdfd",
        'default' =>  true,
        'language' =>  "en"
      ], false, true);
    }

    $id_client = $this->getClient($this->_current['client_name']);

    $db = $this->getDb();
    // Create project
    $id_project = $db->selectOne(
      'bbn_projects',
      'id',
      [
        'id_client' => $id_client,
        'name' => $this->_current['app_name']
      ]
    );
    if (!$id_project
        && $db->insert(
          'bbn_projects',
          [
            'id_client' => $id_client,
            'db' => constant('BBN_DATABASE'),
            'name' => constant('BBN_APP_NAME'),
            'lang' => 'en',
          ]
        )
    ) {
      $id_project = $db->lastId();
    }

    if (!$id_project) {
      throw new Exception("Impossible to create the project");
    }

    return $id_project;
  }


  /**
   * Returns the ID and creates if needed the app entry in the options
   *
   * @return string|null
   */
  public function getAppId(): ?string
  {
    $id_app = null;
    if (($db = $this->getDb())
        && ($opt = $this->getOption())
    ) {
      $id_env = $opt->fromCode('env', $this->_current['app_name'], 'list', 'project', 'appui');
      if (!$id_env) {
        $this->getProject();
        $id_env = $opt->fromCode('env', $this->_current['app_name'], 'list', 'project', 'appui');
      }

      if (!$id_env) {
        throw new Exception("Impossible to retrieve the environment ID");
      }

      if ($id_env
          && !($id_app = $db->selectOne(
            'bbn_options',
            'id',
            [
              'id_parent' => $id_env,
              'text' => $this->_current['app_path'],
              'code' => $this->_current['server_name'].
                  ($this->_current['cur_path'] === '/' ? '' : $this->_current['cur_path'])
            ]
          ))
      ) {
        $id_app = $opt->add(
          [
            'id_parent' => $id_env,
            'text' => $this->_current['app_path'],
            'code' => $this->_current['server_name'].
                ($this->_current['cur_path'] === '/' ? '' : $this->_current['cur_path']),
            'env' => $this->_current['env']
          ]
        );
      }
    }

    if (!$id_app) {
      throw new Exception("Impossible to create the application row");
    }

    return $id_app;
  }


  /**
   * Gets and creates if needs be the main internal user's ID
   *
   * @return string
   */
  public function getInternalUser(): string
  {
    $id_appui_user = null;
    $db            = $this->getDb();
    // Create "APPUI" user
    $id_internal_group = $this->getUserGroup('internal', 'Internal users', 'internal');
    if (!($id_appui_user = $db->selectOne(
      'bbn_users',
      'id',
      [
        'username' => 'APPUI',
        'email' => null,
        'login' => null,
        'id_group' => $id_internal_group,
      ]
    )) && $db->insert(
      'bbn_users',
      [
        'username' => 'APPUI',
        'email' => null,
        'login' => null,
        'id_group' => $id_internal_group,
        'admin' => 1,
        'dev' => 1,
        'theme' => 'default',
      ]
    )
    ) {
      $id_appui_user = $db->lastId();
    }

    if (!$id_appui_user) {
      throw new Exception("Impossible to create the client");
    }

    return $id_appui_user;
  }


  /**
   * Import the options from the default bbn file in the current environment.
   *
   * @return iterable
   */
  public function importOptions()
  {
    if ($opt = $this->getOption()) {
      $root   = $this->getOptionRoot();
      if ($tmp = $this->getRoutes()) {
        $routes = array_values($tmp['root']);
      }

      $idx = X::find($routes, ['name' => 'appui-core']);
      $step = 100;
      $next = $step;
      $num = 0;

      if ($routes[$idx]) {
        $opt->deleteCache(null);
        if (!$opt->getMagicTemplateId()) {
          $templatesFile = $this->libPath() . $routes[$idx]['path'] . '/src/cfg/templates.json';
          $tmp = $this->_currentFs->decodeContents($templatesFile, 'json', true);
          foreach ($opt->import($tmp, $root) as $res) {
            $num += $res;
          }
        }

        if (!($idApp = $opt->fromCode($this->_current['app_name'], $root))) {
          $idApp = $opt->add([
            'id_parent' => $root,
            'code' => $this->_current['app_name'],
            'text' => $this->_current['site_title'],
            'id_alias' => $opt->getPluginTemplateId()
          ], true);
          $num += $opt->applyTemplate($idApp);
          $opt->deleteCache(null);
        }

        $opt->setDefault($idApp);

        $id_appui = $opt->fromCode('appui');
        $appui_options = [];
        $idPluginTpl = $opt->getPluginTemplateId();
        $todo = [];
        foreach (array_values($routes) as $i => $r) {
          $idFile = $this->libPath() . $r['path'] . '/src/cfg/plugin.json';
          $optionsFile = $this->libPath() . $r['path'] . '/src/cfg/options.json';
          $pluginsFile = $this->libPath() . $r['path'] . '/src/cfg/plugins.json';
          $templatesFile = $this->libPath() . $r['path'] . '/src/cfg/templates.json';
          if ($this->_currentFs->exists($idFile)) {
            $tmp = $this->_currentFs->decodeContents($idFile, 'json', true);
            if (!$tmp) {
              throw new Exception(X::_("Illegal JSON in %s", $idFile));
            }

            $tmp['id_alias'] = $idPluginTpl;
            $tmp['id_parent'] = $id_appui;
            if ($id_plugin = $opt->add($tmp)) {
              $num++;
            }

            $num += $opt->applyTemplate($id_plugin);
            if ($this->_currentFs->exists($optionsFile)) {
              $tmp = $this->_currentFs->decodeContents($optionsFile, 'json', true);
              if (!$tmp) {
                throw new Exception(X::_("Illegal JSON in %s", $optionsFile));
              }

              $id_options = $opt->fromCode('options', $id_plugin);
              if (!$id_options) {
                throw new Exception(X::_("Impossible to find the options parent"));
              }
              $todo[] = [$tmp, $id_options];
              foreach($opt->import($tmp, $id_options, true) as $res) {
                $num += $res;
                if ($num >= $next) {
                  $next += $step;
                  yield $num;
                }
              }
            }

            if ($this->_currentFs->exists($pluginsFile)) {
              $tmp = $this->_currentFs->decodeContents($pluginsFile, 'json', true);
              if (!$tmp) {
                throw new Exception(X::_("Illegal JSON in %s", $pluginsFile));
              }
              if (X::isAssoc($tmp)) {
                $tmp = [$tmp];
              }

              $id_plugins = $opt->fromCode('plugins', $id_plugin);
              if (!$id_plugins) {
                throw new Exception(X::_("Impossible to find the options parent"));
              }
              $todo[] = [$tmp, $id_plugins];
              foreach($opt->import($tmp, $id_plugins, true) as $res) {
                $num += $res;
                if ($num >= $next) {
                  $next += $step;
                  yield $num;
                }
              }
            }

            if (($r['name'] !== 'appui-core') && $this->_currentFs->exists($templatesFile)) {
              $tmp = $this->_currentFs->decodeContents($templatesFile, 'json', true);
              if (!$tmp) {
                throw new Exception(X::_("Illegal JSON in %s", $templatesFile));
              }
              if (X::isAssoc($tmp)) {
                $tmp = [$tmp];
              }
  
              $id_templates = $opt->fromCode('templates', $id_plugin);
              if (!$id_templates) {
                throw new Exception(X::_("Impossible to find the templates plugin %s options", $r['name']));
              }
              $todo[] = [$tmp, $id_templates];
              foreach($opt->import($tmp, $id_templates, true) as $res) {
                $num += $res;
                if ($num >= $next) {
                  $next += $step;
                  yield $num;
                }
              }
            }
          }

          if ($num >= $next) {
            $next += $step;
            yield $num;
          }
        }

        foreach ($todo as $td) {
          foreach($opt->import($td[0], $td[1]) as $res) {
            $num += $res;
            if ($num >= $next) {
              $next += $step;
              yield $num;
            }
          }
        }

        /*
        foreach ($opt->import($appui_options, $root) as $success) {
          if ($success) {
            $res += $success;
            if ($res >= $next) {
              $next += $step;
              yield $res;
            }
          }
        }
          */

        $opt->deleteCache(null);
      }
    }
  }


  /**
   * Update the plugins in the options table for the current environment
   *
   * @return int
   */
  public function updatePlugins(): int
  {
    $res = 0;
    if ($opt = $this->getOption()) {
      $res = (int)$opt->updatePlugins();
      $opt->deleteCache(null);
    }

    return $res;
  }


  /**
   * Update all the templates in the options table for the current environment
   *
   * @return int
   */
  public function updateTemplates(): int
  {
    $res = 0;
    if ($opt = $this->getOption()) {
      $res = (int)$opt->updateAllTemplates();
      $opt->deleteCache(null);
    }

    return $res;
  }


  /**
   * Updates all the permissions in the current environment.
   *
   * @return int
   */
  public function updatePermissions(): int
  {
    $res = 0;
    if ($perm = $this->getPermissions()) {
      $perm_routes = [];
      $routes      = $this->getRoutes();
      foreach ($routes['root'] as $u => $r) {
        $perm_routes[$u] = [
          'url' => $u,
          'path' => constant('BBN_LIB_PATH') . $r['path'].'/src/',
          'root' => 'lib',
          'name' => $r['name']
        ];
      }

      X::log($perm_routes);
      $perms = $perm->updateAll($perm_routes);
      $res   = $perms['total'] ?? 0;
      $this->getOption()->deleteCache(null);
    }

    return $res;
  }


  /**
   * Registers the application to the central server.
   *
   * @return bool
   */
  public function register(): bool
  {
    $user       = $this->getUser();
    $db         = $this->getDb();
    $api        = new Api($user, $db);
    $pass       = $this->getPassword();
    $rsa        = $this->getPublicKey();
    $id_project = $this->getProject();
    $id_app     = $this->getAppId();
    try {
      $reg = $api->registerProject(
        [
          'key' => file_get_contents($rsa),
          'id_project' => $id_project,
          'id_app' => $id_app,
          'site_title' => $this->_current['site_title'],
          'user' => $this->_current['admin_email'],
          'id_user' => $user->getId(),
          'app_name' => $this->_current['app_name'],
          'url' => $this->_current['url'],
          'hostname' => $this->_current['hostname']
        ]
      );
    }
    catch (Exception $e) {
      throw new Exception(X::_("The application didn't register!").PHP_EOL.$e->getMessage());
    }

    if (!empty($reg) && !empty($reg['id_app'])) {
      $this->setEnvironment(['id_app' => $reg['id_app']]);
      $this->setSettings(['project' => $reg['id_project']]);
      $pass->store($reg['key'], $id_app);
      return true;
    }

    return false;
  }


  /**
   * Update the menus in the database
   *
   * @return int The number of changes made
   */
  public function updateMenus(): int
  {
    $db         = $this->getDb();
    $opt_class  = $this->getOption();
    $menu_class = $this->getMenu();
    $pref_class = $this->getPreferences();
    $perm_class = $this->getPermissions();
    $numChanges = 0;
    if (!($id_main_menu = $menu_class->getByCode('main'))
      && ($id_main_menu = $menu_class->add(
        [
          'text' => 'Main menu',
          'code' => 'main',
          'num' => 1,
        ]
      ))
    ) {
      $numChanges++;
      $pref_class->makePublic($id_main_menu);
      // Set default menu
      if ($pref_class->add(
        $opt_class->fromCode('default', 'menu', 'appui'),
        [
          'text' => 'Default menu',
          'id_alias' => $id_main_menu,
        ]
      )) {
        $pref_class->makePublic($id_main_menu);
        $numChanges++;
      }
    }

    $menus = $this->getMenuFilesContent();
    if (!$menus) {
      X::log("No menu!");
    }

    // Check if Plugins menu exists
    if ($idPluginMenu = $menu_class->getByCode('plugins')) {
      $items = $menu_class->get($idPluginMenu);
      foreach ($menus as $m) {
        $menu = X::getRow($items, ['text' => $m['text']]);
        $idMenu = !empty($menu['id']) ? $menu['id'] :null;
        if (empty($menu)) {
          if (!$idMenu = $menu_class->add($idPluginMenu, $m)) {
            throw new Exception(X::_("Impossible to add the menu element %s!", $m['text']));
          }
          $numChanges++;
        }
        else if (($m['icon'] !== $menu['icon'])
          || ($m['num'] !== $menu['num'])
        ) {
          if (!$menu_class->set($idMenu, [
            'icon' => $m['icon'],
            'num' => $m['num']
          ])) {
            throw new Exception(X::_("Impossible to update the menu element %s!", $idMenu));
          }
          $numChanges++;
        }
        if (!empty($idMenu)) {
          if (!empty($m['items'])) {
            $subItems = $menu_class->get($idPluginMenu, $idMenu);
            foreach ($m['items'] as $mit) {
              $item = X::getRow($subItems, ['text' => $mit['text']]);
              $idItem = !empty($item['id']) ? $item['id'] : null;
              if (empty($item)) {
                $mit = array_merge(
                  $mit,
                  [
                    'id_parent' => $idMenu,
                    'id_option' => $perm_class->fromPath($mit['link']),
                  ]
                );
                unset($mit['link']);
                if (!$menu_class->add($idPluginMenu, $mit)) {
                  throw new Exception(X::_("Impossible to add the menu element %s!", $mit['text']));
                }
                $numChanges++;
              }
              else {
                $idOption = $perm_class->fromPath($mit['link']);
                if (\array_key_exists('icon', $mit)
                  && \array_key_exists('icon', $item)
                  && ($mit['icon'] !== $item['icon'])
                ) {
                  if (!$menu_class->set($idItem, [
                    'icon' => $mit['icon']
                  ])) {
                    throw new Exception(X::_("Impossible to update the menu element %s!", $idItem));
                  }
                  $numChanges++;
                }
                if (\array_key_exists('num', $mit)
                  && \array_key_exists('num', $item)
                  && ($mit['num'] !== $item['num'])
                ) {
                  if (!$menu_class->set($idItem, [
                    'num' => $mit['num']
                  ])) {
                    throw new Exception(X::_("Impossible to update the menu element %s!", $idItem));
                  }
                  $numChanges++;
                }
                if ($idOption !== $item['id_option']) {
                  if (!$menu_class->set($idItem, [
                    'id_option' => $idOption
                  ])) {
                    throw new Exception(X::_("Impossible to update the menu element %s!", $idItem));
                  }
                  $numChanges++;
                }
              }
            }
          }
        }
      }
    }
    // Add Plugins menu
    elseif ($id_plugin_menu = $menu_class->add(
      [
        'text' => 'Plugins',
        'code' => 'plugins',
        'num' => 2,
      ]
    )) {
      $numChanges++;
      $pref_class->makePublic($id_plugin_menu);
      foreach ($menus as $m) {
        if ($id_parent_menu = $menu_class->add($id_plugin_menu, $m)) {
          foreach ($m['items'] as $mit) {
            $mit = array_merge(
              $mit,
              [
                'id_parent' => $id_parent_menu,
                'id_option' => $perm_class->fromPath($mit['link']),
              ]
            );
            unset($mit['link']);
            $menu_class->add($id_plugin_menu, $mit);
            $numChanges++;
          }
        }
        else {
          throw new Exception(X::_("Impossible to add the menu element %s!", $m['text']));
        }
      }

      $id_group = $this->getUserGroup('admin', 'Administrators');
      $db->update(
        'bbn_users_options',
        [
          'id_user' => null,
          'id_group' => $id_group,
        ],
        [
          'id' => $id_plugin_menu,
        ]
      );
    }

    return $numChanges;

  }


  /**
   * Updates the dashboard on the database
   *
   * @return int The number of rows inserted
   */
  public function updateDashboard(): int
  {
    $db          = $this->getDb();
    $optClass   = $this->getOption();
    $prefClass  = $this->getPreferences();
    $prefCfg = $prefClass->getClassCfg();
    $dashClass   = new Dashboard();
    $adminGroup = $this->getUserGroup('admin', 'Administrators');
    $devGroup   = $this->getUserGroup('dev', 'Developers');
    $numChanges = 0;
    $defaultCreated = false;
    if (!($idDashboard = $optClass->fromCode('dashboard', 'appui'))) {
      throw new Exception(X::_('Dashboard option not found'));
    }
    if (!($idList = $optClass->fromCode('list', $idDashboard))) {
      throw new Exception(X::_('Dashboard list option not found'));
    }
    if (!($defDash = $optClass->fromCode('default', $idDashboard))) {
      throw new Exception(X::_('Default dashboard option not found'));
    }

    if (!($idDefaultDashboard = $dashClass->getId('default'))
      && ($idDefaultDashboard = $dashClass->insert([
        $prefCfg['arch']['user_options']['text'] => 'Default dashboard',
        'code' => 'default',
        $prefCfg['arch']['user_options']['public'] => 1
      ]))
    ) {
      $defaultCreated = true;
      $numChanges++;
    }

    if (empty($idDefaultDashboard)) {
      throw new Exception(X::_('Default dashboard not found'));
    }

    if (!($idPluginsDashboard = $dashClass->getId('plugins'))
      && ($idPluginsDashboard = $dashClass->insert([
        $prefCfg['arch']['user_options']['text'] => 'Plugins dashboard',
        'code' => 'plugins',
        $prefCfg['arch']['user_options']['public'] => 1
      ]))
    ) {
      $numChanges++;
      if (!$db->selectOne($prefCfg['table'], $prefCfg['arch']['user_options']['id_alias'], [
        $prefCfg['arch']['user_options']['id_option'] => $defDash,
        $prefCfg['arch']['user_options']['id_group'] => $adminGroup,
      ])) {
        $db->insert(
          $prefCfg['table'], [
          $prefCfg['arch']['user_options']['id_option'] => $defDash,
          $prefCfg['arch']['user_options']['id_group'] => $adminGroup,
          $prefCfg['arch']['user_options']['id_alias'] => $idPluginsDashboard,
          ]
        );
      }
      if (!$db->selectOne($prefCfg['table'], $prefCfg['arch']['user_options']['id_alias'], [
        $prefCfg['arch']['user_options']['id_option'] => $defDash,
        $prefCfg['arch']['user_options']['id_group'] => $devGroup,
      ])) {
        $db->insert(
          $prefCfg['table'], [
          $prefCfg['arch']['user_options']['id_option'] => $defDash,
          $prefCfg['arch']['user_options']['id_group'] => $devGroup,
          $prefCfg['arch']['user_options']['id_alias'] => $idPluginsDashboard,
          ]
        );
      }
    }

    if (empty($idPluginsDashboard)) {
      throw new Exception(X::_('Plugins dashboard not found'));
    }

    if ($dashboards = $this->getDashboardFilesContent()) {
      foreach ($dashboards as $dash) {
        if (empty($dash['code'])) {
          throw new Exception(X::_('Dashboard code not found %s', \json_encode($dash)));
        }
        if (!($idDash = $dashClass->getId($dash['code']))) {
          if (!($idDash = $dashClass->insert([
            $prefCfg['arch']['user_options']['text'] => $dash['text'],
            'code' => $dash['code'],
            $prefCfg['arch']['user_options']['public'] => 1
          ]))) {
            throw new Exception(X::_('Dashboard not created %s', \json_encode($dash)));
          }
          $dashClass->setCurrent($idDash);
          $numChanges++;
        }
        else {
          $dashClass->setCurrent($idDash);
          if (($d = $dashClass->get($idDash))
            && ($dash['text'] !== $d[$prefCfg['arch']['user_options']['text']])
          ) {
            $d[$prefCfg['arch']['user_options']['text']] = $dash['text'];
            if (!$dashClass->update($d)) {
              throw new Exception(X::_('Dashboard %s not updated', $idDash));
            }
            $numChanges++;
          }
        }
        if (empty($idDash)) {
          throw new Exception(X::_('Dashboard %s not found', $dash['code']));
        }
        if (!empty($dash['items'])) {
          $dashWidgets = $dashClass->getWidgets();
          foreach ($dash['items'] as $item) {
            $dashClass->setCurrent($idDash);
            $widgetOpt = $optClass->option($item['id_option']);
            if (\is_null(X::find($dashWidgets, [
              $prefCfg['arch']['user_options_bits']['id_option'] => $item['id_option']
            ]))) {
              $dashClass->addWidget($item['id_option']);
              $numChanges++;
              if (($dash['code'] === 'plugins')
                && $defaultCreated
                && !empty($widgetOpt['public'])
              ) {
                $dashClass->setCurrent($idDefaultDashboard);
                $dashClass->addWidget($item['id_option']);
                $numChanges++;
              }
            }
          }
        }
      }
    }
    return $numChanges;
  }


  /**
   * Updates the history tables and add the history constraints.
   *
   * @return iterable
   */
  public function updateHistory() 
  {
    $tot_insert     = 0;
    $inserted       = 0;
    $step           = 100;
    $db             = $this->getDb();
    $opt_class      = $this->getOption();
    $pass           = $this->getPassword();
    $id_appui_user  = $this->getInternalUser();
    $dbc            = new Database($db);
    $id_connections = $opt_class->fromCode(
      'connections',
      $this->_current['db_engine'],
      'database',
      'appui'
    );
    if ($id_connections
        && ($id_connection = $opt_class->add(
          [
            'id_parent' => $id_connections,
            'text' => $this->_current['db_host'],
            'code' => $this->_current['db_user'].'@'.$this->_current['db_host'],
          ]
        ))
        && $pass->store($this->_current['db_pass'], $id_connection)
        && ($id_option_database = $dbc->importDb($this->_current['database'], $id_connection, true))
    ) {
      $id_option_table  = $dbc->tableId('bbn_options', $id_option_database);
      $id_option_column = $dbc->columnId('id', 'bbn_options', $id_option_database);
      $tst              = microtime(true);
      // Insert all options into bbn_history_uids and bbn_history tables
      $history_rows     = $db->getColumnValues('bbn_options', 'id');
      $num_history_rows = count($history_rows);
      foreach ($history_rows as $o) {
        if ($db->insert(
          'bbn_history_uids',
          [
            'bbn_uid' => $o,
            'bbn_table' => $id_option_table,
            'bbn_active' => 1,
          ]
        )
            && $db->insert(
              'bbn_history',
              [
                'opr' => 'INSERT',
                'uid' => $o,
                'col' => $id_option_column,
                'tst' => $tst,
                'usr' => $id_appui_user,
              ]
            )
        ) {
          $inserted++;
          if (!($inserted % $step)) {
            yield $inserted;
          }
        }
      }

      // Create constraints
      if ($constraints = $this->getHistoryConstraints()) {
        foreach ($constraints as $ctable => $ckeys) {
          $db->query($db->getCreateConstraints($ctable, ['keys' => $ckeys]));
        }
      }
    }

    return $inserted;
  }


  /**
   * Installs an app-ui instance after the installation of composer and directories structure.
   *
   * @param Installer $installer An installer object coming from the previously executed script.
   * @param array|null $cfg       The configuration comuing from the post.
   * @return bool
   */
  public function install($installer, array $cfg, int $step = 100): bool
  {
    if (!method_exists($installer, 'report')) {
      throw new Exception(X::_("The installer is invalid"));
    }

    $this->_is_installer = true;

    $installer->report(' ');
    $installer->report('Starting the initialization file');
    $installer->report(' ');

    // Initial settings
    /*
    date_default_timezone_set('UTC');
    ignore_user_abort(true);
    ini_set('output_buffering', 'Off');
    if (function_exists('apache_setenv')) {
      apache_setenv('no-gzip', '1');
      apache_setenv('dont-vary', '1');
    }
    */

    // Cache, deleting all before starting
    $installer->report('Deleting cache if any');
    $cache    = Cache::getEngine();
    $cache->deleteAll('');

    $routes   = $this->getRoutes();
    $settings = $this->getSettings();

    // Making a simple plugins array with only the names
    $plugins = array_map(
      function ($r) {
        return $r['name'];
      },
      array_values($routes['root'])
    );
    $installer->report(count($plugins).' plugins found');

    $plugins_urls = [];
    foreach ($routes['root'] as $url => $route) {
      $plugins_urls[$route['name']] = $url;
    }

    // We need a key, if we don't find it we create one..
    $installer->report('Generating the certificate');
    try {
      $this->getPublicKey(true);
    }
    catch (Exception $e) {
      $installer->report('Failed to create SSL certificate: '.$e->getMessage(), false, true);
    }

    // All the update info
    $installer->report('Gathering all the update information');

    // Database creation phase
    $installer->report('Creating the database');
    try {
      $this->createDatabase();
    }
    catch (Exception $e) {
      $installer->report($e->getMessage(), false, true);
    }

    $installer->report('Database created successfully');

    // Records generation
    if ($installer->has_appui()) {
      $installer->report('Records generation', true);

      if ($id_appui_user = $this->getInternalUser()) {
        $installer->report('Internal user and group created successfully');
      }
      else {
        $installer->report('Error during internal user or group creation', false, true);
      }

      $settings = $this->getSettings();

      if (!empty($settings)) {
        $settings['external_user_id'] = $id_appui_user;
        if (!defined('BBN_EXTERNAL_USER_ID')) {
          define('BBN_EXTERNAL_USER_ID', $id_appui_user);
        }

        $this->setSettings(['external_user_id' => $id_appui_user]);
      }
      else {
        $installer->report('Impossible to retrieve the settings file!', false, true);
      }

      if ($this->getUserGroup('admin', 'Administrators')
          && $this->getUserGroup('dev', 'Developers')
      ) {
        $installer->report('Admin and dev group created/retrieved successfully');
      }
      else {
        $installer->report('Error during admin group creation', false, true);
      }

      if ($this->getAdminUser($cfg['admin_name'], $cfg['admin_password'])) {
        $installer->report('Admin user created successfully');
      }
      else {
        $installer->report('Error during admin user creation', false, true);
      }

      foreach ($this->importOptions() as $res) {
        $installer->report("{$res} options imported");
      }

      /*
      if ($res = $this->updatePlugins()) {
        $installer->report("{$res} options for plugins imported");
      }
      else {
        $installer->report('No new option imported');
      }

      if ($res = $this->updateTemplates()) {
        $installer->report("{$res} options from templates");
      }
      else {
        $installer->report("No new options from templates");
      }
      */

      $cache->deleteAll('');

      $installer->report("Permissions creation...");
      if ($res = $this->updatePermissions()) {
        $installer->report("{$res} Permissions created");
      }
      else {
        $installer->report('No new permissions created');
      }

      if ($this->getProject()) {
        $installer->report("Main project OK");
      }
      else {
        $installer->report('Impossible retrieving the main project', false, true);
      }

      if ($id_app = $this->getAppId()) {
        $installer->report("App ID OK");
      }
      else {
        $installer->report('Impossible retrieving the app ID', false, true);
      }

      // Add Main menu
      $installer->report('Creating menus...');
      $this->updateMenus();

      $installer->report('Menus created');

      if (in_array('appui-dashboard', $plugins)) {

        $this->updateDashboard();
        $installer->report('Dashboard created');
      }

      // If history is active
      if (!empty($settings['history'])) {
        $installer->report(X::_("History update starting, it might take a while..."));
        foreach ($this->updateHistory() as $res) {
          $installer->report(X::_("%s entries imported", $res));
        }

        $installer->report(X::_("History update successful"));
      }

      $cache->deleteAll('');
      $installer->report('Default DB records created.', true);

      // Contacting the server to give the id_project, id_client, id_user and the public key
      // Using JWT and JWE
      if ($this->register()) {
        $installer->report("Registering the app with ID {$id_app}...");
      }
      else {
        $installer->report('Impossible retrieving the app ID', false, true);
      }

    }

    return true;
  }


}
