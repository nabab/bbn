<?php
namespace bbn;

/**
 * Model View Controller Class.
 *
 * Called once per request, holds the environment's variables and routes each request to its according controller, then acts as a link between the controller and models and views it uses.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.9
 * @todo Add feature to auto-detect a different corresponding index and redirect to it through Appui
 * @todo Add $this->dom to public controllers (?)
 */

use bbn\Mvc\Router;

if (!\defined("BBN_DEFAULT_MODE")) {
  define("BBN_DEFAULT_MODE", 'public');
}

// Correspond to the path after the URL to the application's public root (set to '/' for a domain's root)
if (!\defined("BBN_CUR_PATH")) {
  define('BBN_CUR_PATH', '/');
}

if (!\defined("BBN_APP_NAME")) {
  throw new \Exception("BBN_APP_NAME must be defined");
}

if (!\defined("BBN_APP_PATH")) {
  throw new \Exception("BBN_APP_PATH must be defined");
}

if (!\defined("BBN_DATA_PATH")) {
  throw new \Exception("BBN_DATA_PATH must be defined");
}

class Mvc implements Mvc\Api
{
  use Models\Tts\Singleton;
  use Mvc\Common;

  /**
   * @var array The list of views which have been loaded
   */
  private static $_loaded_views = [
    'html' => [],
    'css' => [],
    'js' => []
  ];

  /**
   * @var bool
   */
  private static $_is_debug = false;

  /**
   * @var string The application name
   */
  private static $_app_name;

  /**
   * @var string The application prefix
   */
  private static $_app_prefix;

  /**
   * @var string The application path
   */
  private static $_app_path;

  /**
   * @var string The path in the URL
   */
  private static $_cur_path;

  /**
   * @var string The libraries path (vendor)
   */
  private static $_lib_path;

  /**
   * @var string The data path
   */
  private static $_data_path;

  protected static $db_in_controller = false;

  /**
   * The current controller
   * @var null|Mvc\Controller
   */
  protected $controller;

  /**
   * @var db Database object
   */
  protected $db;

  /**
   * @var Mvc\Environment Environment object
   */
  protected $env;

  /**
   * @var Mvc\Router Database object
   */
  protected $router;

  /**
   * @var array The file(s)'s configuration to transmit to the m/v/c
   */
  protected $info;

  /**
   * @var string The root of the application in the URL (base href)
   */
  protected $root;

  /**
   * @var array The plugins registered through the routes
   */
  protected $plugins;

  /**
   * @var array The plugins registered through the routes
   */
  protected $loaded = [
    'views' => [
      'html' => [],
      'css' => [],
      'js' => []
    ],
    'models' => [],
    'ctrls' => []
  ];

  protected $authorized_routes = [];

  protected $forbidden_routes = [];

  /**
   * @var stdClass An external object that can be filled after the object creation and can be used as a global with the function add_inc
   */
  public $inc;

  /**
   * @var array
   */
  public $data = [];

  // Same
  public $o;

  /**
   * The output object
   * @var null|object
   */
  public $obj;

    // These strings are forbidden to use in URL
  public static $reserved = ['_private', '_common', '_htaccess'];


  /**
   * Sets all the different paths' properties.
   *
   * @return void
   */
  public static function initPath()
  {
    if (!self::$_app_name) {
      self::$_app_name   = BBN_APP_NAME;
      self::$_app_path   = BBN_APP_PATH;
      self::$_app_prefix = BBN_APP_PREFIX;
      self::$_cur_path   = BBN_CUR_PATH;
      self::$_lib_path   = BBN_LIB_PATH;
      self::$_data_path  = BBN_DATA_PATH;
    }
  }


  /**
   * Returns the current app's name.
   *
   * @return string
   */
  public static function getAppName(): string
  {
    return self::$_app_name;
  }


  /**
   * Returns the current app's prefix if any.
   *
   * @return string|null
   */
  public static function getAppPrefix(): ?string
  {
    return self::$_app_prefix;
  }


  /**
   * Returns the current app's full path (with src/ at the end if raw if false).
   *
   * @param boolean $raw
   *
   * @return string
   */
  public static function getAppPath($raw = false): string
  {
    return self::$_app_path.($raw ? '' : 'src/');
  }


  /**
   * Returns the web public path of the app.
   *
   * @return string
   */
  public static function getCurPath(): string
  {
    return self::$_cur_path;
  }


  /**
   * Returns the full path of the libraries (vendor folder).
   *
   * @return string
   */
  public static function getLibPath(): string
  {
    return self::$_lib_path;
  }


  /**
   * Returns the full path of the data; if plugin is provided gives the path for the plugin's data.
   *
   * @param string $plugin
   *
   * @return string
   */
  public static function getDataPath(string $plugin = null): string
  {
    return BBN_DATA_PATH.($plugin ? 'plugins/'.$plugin.'/' : '');
  }


  /**
   * Returns the full path of the temp data; if plugin is provided gives the path for the plugin's temp data.
   *
   * @param string $plugin
   *
   * @return string
   */
  public static function getTmpPath(string $plugin = null): string
  {
    return self::$_app_name ? self::getDataPath().'tmp/'.($plugin ? $plugin.'/' : '') : '';
  }


  /**
   * Returns the full path of the logs.
   *
   * @todo Not sure it makes sense to have the plugin as for now all logs are in the same directory.
   *
   * @param string $plugin
   *
   * @return string
   */
  public static function getLogPath(string $plugin = null): string
  {
    return self::$_app_name ? self::getDataPath().'logs/'.($plugin ? $plugin.'/' : '') : '';
  }


  /**
   * Returns ths full path of the cache
   *
   * @todo Not sure it makes sense to have the plugin as for now all logs are in the same directory.
   *
   * @param string $plugin
   *
   * @return string
   */
  public static function getCachePath(string $plugin = null): string
  {
    return BBN_DATA_PATH.'cache/'.($plugin ? $plugin.'/' : '');
  }


  /**
   * Returns the full path of the content data; if plugin is provided gives the path for the plugin's content data.
   *
   * @param string $plugin
   *
   * @return string
   */
  public static function getContentPath(string $plugin = null): string
  {
    return self::$_app_name ? self::getDataPath().($plugin ? 'plugins/'.$plugin.'/' : 'content/') : '';
  }


  /**
   * Returns the URL part of the given plugin.
   *
   * @param string $plugin_name the plugin
   *
   * @return null|string|false
   */
  public static function getPluginUrl(string $plugin_name)
  {
    if ($mvc = self::getInstance()) {
      return $mvc->pluginUrl($plugin_name);
    }

    return null;
  }


  /**
   * Returns the URL part of the given plugin.
   *
   * @param string $plugin_name the plugin
   *
   * @return null|string
   */
  public static function getPluginPath(string $plugin_name): ?string
  {
    if ($mvc = self::getInstance()) {
      return $mvc->pluginPath($plugin_name);
    }

    return null;
  }


  /**
   * Returns
   *
   * @param string $id_user
   * @param string $plugin
   *
   * @return string|null
   */
  public static function getUserTmpPath(string $id_user = null, string $plugin = null):? string
  {
    if (!self::$_app_name) {
      return null;
    }

    if (!$id_user) {
      $usr = \bbn\User::getInstance();
      if ($usr) {
        $id_user = $usr->getId();
      }
    }

    if ($id_user) {
      return self::getDataPath().'users/'.$id_user.'/tmp/'.($plugin ? $plugin.'/' : '');
    }

    return null;
  }


  public static function getUserDataPath(string $id_user = null, string $plugin = null):? string
  {
    if (!self::$_app_name) {
      return null;
    }

    if (!$id_user) {
      $usr = \bbn\User::getInstance();
      if ($usr) {
        $id_user = $usr->getId();
      }
    }

    if ($id_user) {
      return self::getDataPath().'users/'.$id_user.'/data/'.($plugin ? $plugin.'/' : '');
      ;
    }

    return null;
  }


  public static function includeModel($bbn_inc_file, $model)
  {
    if (is_file($bbn_inc_file)) {
      ob_start();
      $d = include $bbn_inc_file;
      ob_end_clean();
      if (\is_object($d)) {
        $d = X::toArray($d);
      }

      if (!\is_array($d)) {
        return false;
      }

      return $d;
    }

    return false;
  }


  public function getCookie()
  {
    return empty($_COOKIE[BBN_APP_NAME]) ? false : json_decode($_COOKIE[BBN_APP_NAME], true)['value'];
  }


  public function addAuthorizedRoute(): int
  {
    $res = 0;
    foreach (\func_get_args() as $a) {
      if (!in_array($a, $this->authorized_routes, true)) {
        $this->authorized_routes[] = $a;
        $res++;
      }
    }

    return $res;
  }


  public function addForbiddenRoute(): int
  {
    $res = 0;
    foreach (\func_get_args() as $a) {
      if (!in_array($a, $this->forbidden_routes, true)) {
        $this->forbidden_routes[] = $a;
        $res++;
      }
    }

    return $res;
  }


  public function isAuthorizedRoute($url): bool
  {
    if (in_array($url, $this->authorized_routes, true)) {
      return true;
    }

    foreach ($this->authorized_routes as $ar) {
      if (substr($ar, -1) === '*') {
        if ((strlen($ar) === 1) || (strpos($url, substr($ar, 0, -1)) === 0)) {
          if (in_array($url, $this->forbidden_routes, true)) {
            return false;
          }

          foreach ($this->forbidden_routes as $ar2) {
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


  public function setRoot($root)
  {
    /** @todo a proper verification of the path */
    if (strpos($root, '/',-1) === false) {
      $root .= '/';
    }

    $this->root = $root;
  }


  public function getRoot()
  {
    return $this->root;
  }


  public function setLocale(string $locale)
  {
    $this->env->setLocale($locale);
    $this->initLocaleDomain();
  }


  public function getLocale(): ?string
  {
    return $this->env->getLocale();
  }


  public function fetchDir($dir, $mode)
  {
    return $this->router->fetchDir($dir, $mode);
  }


  public static function includePhpView($bbn_inc_file, $bbn_inc_content, array $bbn_inc_data = [])
  {
    $randoms = [];
    $_random = function ($i) use (&$randoms) {
      if (!isset($randoms[$i])) {
        $randoms[$i] = md5(Str::genpwd());
      }

      return $randoms[$i];
    };
    $fn      = function () use ($bbn_inc_file, $bbn_inc_content, $bbn_inc_data, $_random) {
      if ($bbn_inc_content) {
        ob_start();
        if (\count($bbn_inc_data)) {
          foreach ($bbn_inc_data as $bbn_inc_key => $bbn_inc_val){
            $$bbn_inc_key = $bbn_inc_val;
          }

          unset($bbn_inc_key, $bbn_inc_val);
        }

        unset($bbn_inc_data);
        /*
        try {
          eval('?>'.$bbn_inc_content);
        }
        catch (\Exception $e){
          //error_log($e->getMessage());
          X::logError($e->getCode(), , $bbn_inc_file, 1);
        }
        */
        eval('use bbn\X as xx; use bbn\Str as st; ?>'.$bbn_inc_content);

        $c = ob_get_contents();
        ob_end_clean();
        return $c;
      }

      return '';
    };
    return $fn();
  }


  /**
   * This function gets the content of a view file and adds it to the loaded_views array.
   *
   * @param string $p The full path to the view file
   * @return string The content of the view
   */
  private static function addView($path, $mode, Mvc\View $view)
  {
    if (!isset(self::$_loaded_views[$mode][$path])) {
      self::$_loaded_views[$mode][$path] = $view;
    }

    return self::$_loaded_views[$mode][$path];
  }


  /**
   * This function gets the content of a view file and adds it to the loaded_views array.
   *
   * @param string $p The full path to the view file
   * @return string The content of the view
   */
  public static function setDbInController($r=false)
  {
    self::$db_in_controller = $r ? true : false;
  }


  /**
   * @return bool
   */
  public static function getDebug()
  {
    return self::$_is_debug;
  }


  public static function debug($state = 1)
  {
    self::$_is_debug = (bool)$state;
  }


  private function route($url = false)
  {
    if (\is_null($this->info)) {
      $this->info = $this->getRoute($this->getUrl(), $this->getMode());
    }

    return $this;
  }


  private function registerPlugin(array $plugin)
  {
    if (isset($plugin['path'], $plugin['url'], $plugin['name'])) {
      $this->plugins[$plugin['name']] = [
        'name' => $plugin['name'],
        'url' => $plugin['url'],
        'path' => $plugin['path']
      ];
    }
  }


  private function initLocaleDomain()
  {
    if ($this->router
      && $this->getLocale()
      && ($textdomain = $this->router->getLocaleDomain())
    ) {
      textdomain($textdomain);
    }

    return $this;
  }


  /**
     * This should be called only once from within the app
     *
     * @param object | string $db     The database object if there is
     * @param array           $routes An array of routes usually defined in /_appui/current/cfg/routes.json</em>
     */
  public function __construct($db = null, $routes = [])
  {
    self::singletonInit($this);
    self::initPath();
    $this->env = new Mvc\Environment();
    if (\is_object($db) && ( $class = \get_class($db) ) && ( $class === 'PDO' || strpos($class, '\Db') !== false )) {
        $this->db = $db;
    }
    else{
        $this->db = null;
    }

    $this->inc = new \stdClass();
    if (\is_array($routes) && isset($routes['root'])) {
      foreach ($routes['root'] as $url => &$route){
        if (isset($route['root']) && defined('BBN_'.strtoupper($route['root']).'_PATH')) {
          $route['path'] = constant('BBN_'.strtoupper($route['root']).'_PATH').$route['path'];
        }

        if (!empty($route['path']) && (substr($route['path'], -1) !== '/')) {
          $route['path'] .= '/';
        }

        if (isset($route['path'])) {
          $route['url'] = $url;
          $this->registerPlugin($route);
        }
      }
    }

    $this->initLocaleDomain();
    $this->router = new Mvc\Router($this, $routes);
    $this->route();
  }


  /**
   * Checks whether a corresponding file has been found or not.
   *
   * @return bool
   */
  public function check()
  {
    return $this->info ? true : false;
  }


  public function getPlugins()
  {
    return $this->plugins;
  }


  public function hasPlugin($plugin)
  {
    return isset($this->plugins[$plugin]);
  }


  public function isPlugin($plugin)
  {
    /** @todo This function! */
    return isset($this->plugins[$plugin]);
  }


  public function pluginPath($plugin, $raw = false)
  {
    if ($this->hasPlugin($plugin)) {
        return $this->plugins[$plugin]['path'].($raw ? '' : 'src/');
    }
  }


  public function pluginUrl($plugin)
  {
    return $this->hasPlugin($plugin) ? substr($this->plugins[$plugin]['url'], \strlen($this->root)) : false;
  }


  public function pluginName($path)
  {
    foreach ($this->plugins as $name => $p){
      if (strpos($path, $p['url']) === 0) {
        return $name;
      }
    }

    return false;
  }


  /*public function add_routes(array $routes){
    $this->routes = X::mergeArrays($this->routes, $routes);
    return $this;
  }*/

    /**
     * @param      $path
     * @param      $mode
     * @param null $root
     *
     * @return array|mixed|null
     */
    public function getRoute($path, $mode, $root = null)
  {
    return $this->router->route($path, $mode, $root);
  }


  public function getFile(): ?string
  {
    return $this->info['file'];
  }


  public function getUrl(): ?string
  {
    return $this->env->getUrl();
  }


  public function getRequest(): ?string
  {
    return $this->env->getRequest();
  }


  public function getParams(): ?array
  {
        return $this->env->getParams();
  }


  public function getPost(): array
  {
        return $this->env->getPost();
  }


  public function getGet(): array
  {
        return $this->env->getGet();
  }


  public function getFiles(): array
  {
        return $this->env->getFiles();
  }


  public function getMode(): ?string
  {
    return $this->env->getMode();
  }


  public function setMode($mode)
  {
    return $this->env->setMode($mode);
  }


  public function isCli(): bool
  {
    return $this->env->isCli();
  }


  /**
     * This will reroute a controller to another one seemlessly. Chainable
     *
     * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
     * @return $this
     */
  public function reroute($path='', $post = false, $arguments = false)
  {
    $this->env->simulate($path, $post, $arguments);
    $this->is_routed     = false;
    $this->is_controlled = null;
    $this->info          = null;
    $this->router->reset();
    $this->route();
    if ($arguments || !isset($this->info['args'])) {
      $this->info['args'] = $arguments;
    }

    if (!$this->controller) {
      throw new \Exception(X::_('Controller is not set'));
    }

    $this->controller->reset($this->info);

    return $this;
  }


  /**
   * @param string $path
   * @param string $mode
   * @return bool
   */
  public function hasView(string $path = '', string $mode = 'html'): bool
  {
    return array_key_exists($mode, self::$_loaded_views) && isset(self::$_loaded_views[$mode][$path]);
  }


  /**
   * @param string   $path
   * @param string   $mode
   * @param Mvc\View $view
   * @return void
   */
  public function addToViews(string $path, string $mode, Mvc\View $view): void
  {
    if (!array_key_exists($mode, self::$_loaded_views[$mode])) {
      self::$_loaded_views[$mode] = [];
    }

    self::$_loaded_views[$mode][$path] = $view;
  }


  /**
   * This will get a view.
   *
   * @param string $path
   * @param string $mode
   * @param array|null $data
   * @return string
   * @throws \Exception
   */
  public function getView(string $path, string $mode = 'html', ?array $data=null)
  {
    if (!router::isMode($mode) || !($path = Router::parse($path))) {
      throw new \Exception(
        X::_("Incorrect mode $path $mode")
      );
    }

    $view = null;
    if ($this->hasView($path, $mode)) {
      $view = self::$_loaded_views[$mode][$path];
    }
    elseif ($info = $this->router->route($path, $mode)) {
      $view = new Mvc\View($info);
      $this->addToViews($path, $mode, $view);
    }

    if (\is_object($view) && $view->check()) {
      return $view->get($data);
    }

    return '';
  }


  /**
   * Checks whether the given view exists or not.
   *
   * @param string $path
   * @param string $mode
   * @return boolean
   */
  public function viewExists(string $path, string $mode = 'html'): bool
  {
    if (!router::isMode($mode) || !($path = Router::parse($path))) {
      return false;
    }

    if ($this->hasView($path, $mode)) {
      return true;
    }

    if ($this->router->route($path, $mode)) {
      return true;
    }

    return false;
  }


  /**
   * Checks whether the given model exists or not.
   *
   * @param string $path
   * @return boolean
   */
  public function modelExists(string $path): bool
  {
    if ($this->router->route($path, 'model')) {
      return true;
    }

    return false;
  }


  /**
   * This will get a view from a different root.
   *
   * @param string $full_path
   * @param string $mode
   * @param array|null $data
   * @return string|false
   */
  public function getExternalView(string $full_path, string $mode = 'html', ?array $data=null)
  {
    if (!router::isMode($mode) && ($full_path = Str::parsePath($full_path))) {
      throw new \Exception(
        X::_("Incorrect mode $full_path $mode")
      );
    }

    if (($this->getMode() === 'dom') && (!defined('BBN_DEFAULT_MODE') || (BBN_DEFAULT_MODE !== 'dom'))) {
      $full_path .= ($full_path === '' ? '' : '/').'index';
    }

    $view = null;
    if ($this->hasView($full_path, $mode)) {
      $view = self::$_loaded_views[$mode][$full_path];
    }
    elseif ($info = $this->router->route(basename($full_path), 'free-'.$mode, \dirname($full_path))) {
      $view = new Mvc\View($info);
      $this->addToViews($full_path, $mode, $view);
    }

    if (\is_object($view) && $view->check()) {
      return $view->get($data);
    }

    return '';
  }


  /**
   * Undocumented function
   *
   * @param string $name
   *
   * @return array|null
   */
  public function getPluginFromComponent(string $name): ?array
  {
    return $this->router->getPluginFromComponent($name);
  }


  /**
   * Undocumented function
   *
   * @param string $name
   *
   * @return array|null
   */
  public function routeComponent(string $name): ?array
  {
    return $this->router->routeComponent($name);
  }


  /**
   * Undocumented function
   *
   * @param string $path
   * @param string $mode
   * @param array  $data
   * @param string $plugin
   *
   * @return string|null
   */
  public function customPluginView(string $path, string $mode, array $data, string $plugin): ?string
  {
    if ($plugin && ($route = $this->router->routeCustomPlugin(router::parse($path), $mode, $plugin))) {
      $view = new Mvc\View($route);
      if ($view->check()) {
        return \is_array($data) ? $view->get($data) : $view->get();
      }

      return '';
    }

    return null;
  }


  /**
   * Undocumented function
   *
   * @param string         $path
   * @param array          $data
   * @param Mvc\Controller $ctrl
   * @param string         $plugin
   * @param int            $ttl
   *
   * @return array|null
   */
  public function customPluginModel(string $path, array $data, Mvc\Controller $ctrl, string $plugin, int $ttl = null): ?array
  {
    if ($plugin && ($route = $this->router->routeCustomPlugin(router::parse($path), 'model', $plugin))) {
      $model = new Mvc\Model($this->db, $route, $ctrl, $this);
      if ($ttl) {
        return $model->getFromCache($data, '', $ttl);
      }

      return $model->get($data);
    }

    return null;
  }


  /**
   * Returns true if the subplugin model exists.
   *
   * @param string $path      The path in the subplugin
   * @param string $plugin    The plugin
   * @param string $subplugin The subplugin
   *
   * @return bool
   */
  public function hasSubpluginModel(string $path, string $plugin, string $subplugin): bool
  {
    return !!$this->router->routeSubplugin(router::parse($path), 'model', $plugin, $subplugin);
  }


  /**
   * Get a subplugin model (a plugin inside the plugin directory of another plugin).
   *
   * @param string         $path      The path inside the subplugin directory
   * @param array          $data      The data for the model
   * @param Mvc\Controller $ctrl      The controller
   * @param string         $plugin    The plugin name
   * @param string         $subplugin The subplugin name
   * @param int            $ttl       The cache TTL
   *
   * @return array|null
   */
  public function subpluginModel(string $path, array $data, Mvc\Controller $ctrl, string $plugin, string $subplugin, int $ttl = null): ?array
  {
    if ($plugin
        && $subplugin
        && ($route = $this->router->routeSubplugin(router::parse($path), 'model', $plugin, $subplugin))
    ) {
      $model = new Mvc\Model($this->db, $route, $ctrl, $this);
      $res   = $ttl ? $model->getFromCache($data, '', $ttl) : $model->get($data);
      return $res;
    }

    return null;
  }


  /**
   * This will get a view.
   *
   * @param string $path   The path of the view in the plugin
   * @param string $mode   The mode of the view
   * @param array  $data   Data for the view
   * @param string $plugin The plugin URL
   *
   * @return string|false
   */
  public function getPluginView(string $path, string $mode, array $data, string $plugin)
  {
    return $this->customPluginView(router::parse($path), $mode, $data, $this->pluginName($plugin));
  }


  /**
   * This will get the model; there is no order for the arguments.
   *
   * @param string $path Path to the model
   * @param array  $data Data to send to the model
   *
   * @return array|false A data model
   */
  public function getModel($path, array $data, Mvc\Controller $ctrl)
  {
    if (($path = Router::parse($path)) && ($route = $this->router->route($path, 'model'))) {
      $model = new Mvc\Model($this->db, $route, $ctrl, $this);
      return $model->get($data);
    }

    return [];
  }


  public function getPluginModel(string $path, array $data, Mvc\Controller $ctrl, string $plugin, int $ttl = null)
  {
    return $this->customPluginModel(router::parse($path), $data, $ctrl, $this->pluginName($plugin), $ttl);
  }


  public function getSubpluginModel(string $path, array $data, Mvc\Controller $ctrl, string $plugin, string $subplugin, int $ttl = null)
  {
    return $this->subpluginModel($path, $data, $ctrl, $plugin, $subplugin, $ttl);
  }


  /**
   * This will get the model as it is in cache if any and otherwise will save it in cache then return it
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function getCachedModel(string $path, array $data, Mvc\Controller $ctrl, int $ttl = 10)
  {
    if (\is_null($data)) {
      $data = $this->data;
    }

    if ($route = $this->router->route(router::parse($path), 'model')) {
      $model = new Mvc\Model($this->db, $route, $ctrl, $this);
      return $model->getFromCache($data, '', $ttl);
    }

    return [];
  }


  /**
   * This will set the model in cache
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function setCachedModel($path, array $data, Mvc\Controller $ctrl, $ttl = 10)
  {
    if (\is_null($data)) {
      $data = $this->data;
    }

    if ($route = $this->router->route(router::parse($path), 'model')) {
      $model = new Mvc\Model($this->db, $route, $ctrl, $this);
      return $model->model_set_cache($data, '', $ttl);
    }

    return [];
  }


  /**
   * This will unset the model in cache
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function deleteCachedModel($path, array $data, Mvc\Controller $ctrl)
  {
    if (\is_null($data)) {
      $data = $this->data;
    }

    if ($route = $this->router->route(router::parse($path), 'model')) {
      $model = new Mvc\Model($this->db, $route, $ctrl, $this);
      return $model->deleteCache($data, '');
    }

    return [];
  }


  /**
     * Adds a property to the MVC object inc if it has not been declared.
     *
     * @return bool
     */
  public function addInc($name, $obj)
  {
    if (!isset($this->inc->{$name})) {
        $this->inc->{$name} = $obj;
    }
  }


    /**
     * Returns the rendered result from the current mvc if successufully processed
     * process() (or check()) must have been called before.
     *x
     * @return string|false
     */
  public function process()
  {
    if ($this->check()) {
      $this->obj = new \stdClass();
      if (!\is_array($this->info)) {
        $this->log("No info in MVC", $this->info);
        die("No info in MVC");
      }

      if (!$this->controller) {
        $this->controller = new Mvc\Controller($this, $this->info, $this->data);
      }

      //die(var_dump($this->info));
      $this->controller->process();
    }
  }


  public function hasContent()
  {
    if ($this->check() && $this->controller) {
      return $this->controller->hasContent();
    }

    return false;
  }


  public function transform(callable $fn)
  {
    if ($this->check() && $this->controller) {
      $this->controller->transform($fn);
    }
  }


  public function output()
  {
    if ($this->check() && $this->controller) {
      $obj = $this->controller->get();
      if ($this->isCli()) {
        if (isset($obj->content)) {
          echo $obj->content;
        }

        exit();
      }

      if (\is_array($obj)) {
        $obj = X::toObject($obj);
      }

      if ((\gettype($obj) !== 'object') || (\get_class($obj) !== 'stdClass')) {
        die(X::dump("Unexpected output: ".\gettype($obj)));
      }

      if (X::countProperties($this->obj)) {
        $obj = X::mergeObjects($obj, $this->obj);
      }

      $output = new Mvc\Output($obj, $this->getMode());
      $output->run();
    }
    else{
      header('HTTP/1.0 404 Not Found');
      exit();
    }
  }


  /**
   * @return bool
   */
  public function getDb(): ?db
  {
    if (self::$db_in_controller && $this->db) {
      return $this->db;
    }

    return null;
  }


  public function setPrepath($path)
  {
    if ($this->check()) {
      if ($this->router->getPrepath(false) === $path) {
        return 1;
      }

      if ($this->env->setPrepath($path) && $this->router->setPrepath($path)) {
        $this->params = $this->getParams();
        return 1;
      }
    }

    die("The set_prepath method cannot be used in this MVC");
  }


  public function getPrepath()
  {
    if ($this->check()) {
      return $this->router->getPrepath();
    }
  }


  public function getRoutes($type = 'root')
  {
    if ($this->check()) {
      $routes = $this->router->getRoutes();
      return isset($routes[$type]) ? $routes[$type] : false;
    }
  }


}
