<?php

namespace bbn;

use bbn\Mvc\Common;
use bbn\Mvc\Api;
use bbn\Models\Tts\Singleton;
use bbn\Str;
use bbn\Db;
use bbn\Mvc\Router;
use bbn\Mvc\Controller;
use bbn\Mvc\Model;
use bbn\Mvc\Environment;
use bbn\Mvc\Output;
use bbn\Mvc\View;
use bbn\Util\Timer;
use stdClass;
use Exception;
use function is_null;
use function is_object;
use function is_array;
use function in_array;
use function count;
use function func_get_args;
use function get_class;
use function defined;
use function gettype;

/**
 * MVC controller / dispatcher.
 *
 * This class receives the request, resolves the route, loads the
 * appropriate controller / model / view and finally renders the
 * output.  It is a singleton that implements {@see Api}.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since  Apr 4, 2011, 23:23:55 +0000
 * @category MVC
 * @license  http://www.opensource.org/licenses/mit-license.php MIT
 * @version  0.9
 *
 * @property-read string               $root               Base URL root of the application.
 * @property-read array                $plugins           Registered plugins.
 * @property-read array                $loaded             Loaded views / models / controllers.
 * @property-read Controller|null          $controller        Current controller instance.
 * @property-read Db|null       $db                Database connection (when used inside a controller).
 * @property-read Environment   $env               Environment helper.
 * @property-read Router        $router            Router helper.
 * @property-read array                $post              Parsed POST data.
 * @property-read array                $data              Generic data array.
 * @property-read stdClass             $inc                Miscellaneous objects passed from outside.
 * @property-read object|null          $obj                Rendered output object.
 * @property-read Timer                  $timer              Timer used to measure request duration.
 * @property-read float                  $startTime         Start of request (micro‑seconds).
 *
 * @method static void initPath()                     Initialise all static path constants.
 * @method static string getAppName()                 Get the application name.
 * @method static ?string getAppPrefix()            Get the application prefix (if any).
 * @method static string getAppPath(bool $raw = false) Get the full application path.
 * @method static string getCurPath()               Get the current URL path.
 * @method static string getPublicPath()          Get the public web‑root path.
 * @method static string getLibPath()             Get the vendor library path.
 * @method static string getDataPath(?string $plugin = null) Get the data directory.
 * @method static string getTmpPath(?string $plugin = null) Get the temp directory.
 * @method static string getLogPath(?string $plugin = null) Get the log directory.
 * @method static string getCachePath(?string $plugin = null) Get the cache directory.
 * @method static string getContentPath(?string $plugin = null) Get the content directory.
 * @method static ?string getPluginUrl(string $plugin_name) Get the URL part of a plugin.
 * @method static ?string getPluginPath(string $plugin_name) Get the path of a plugin.
 * @method static ?string getUserTmpPath(?string $id_user = null, ?string $plugin = null) Get a user‑specific temp path.
 * @method static ?string getUserDataPath(?string $id_user = null, ?string $plugin = null) Get a user‑specific data path.
 * @method static mixed includeModel(string $bbn_inc_file, mixed $model, bool $bbn_is_super = false) Include a model file and return its content.
 * @method Timer getTimer()                         Get the request timer.
 * @method bool setConstant(string $name, mixed $value) Set a constant‑like value.
 * @method mixed getConstant(string $name)          Get a constant‑like value.
 * @method array getAllConstants()                  Get all constant‑like values.
 * @method mixed getCookie()                        Get the application cookie.
 * @method array getStaticRoutes()                  Get the list of static routes.
 * @method float getStartTime()                     Get the request start timestamp.
 * @method float getDuration()                      Get elapsed request time.
 * @method int addStaticRoute(...$routes)           Add one or more static routes.
 * @method bool isStaticRoute(?string $url = null)  Check if a URL matches a static route.
 * @method int addAuthorizedRoute(...$routes)       Add one or more authorized routes.
 * @method int addForbiddenRoute(...$routes)        Add one or more forbidden routes.
 * @method bool isAuthorizedRoute(string $url)      Check if a URL is authorized.
 * @method void setRoot(string $root)               Set the base URL root.
 * @method string getRoot()                         Get the base URL root.
 * @method void setLocale(string $locale)           Set the application locale.
 * @method ?string getLocale()                      Get the current locale.
 * @method string fetchDir(string $dir, string $mode) Get a directory listing.
 * @method string fetchCustomDir(string $dir, string $mode, ?string $plugin) Get a custom directory listing.
 * @method string fetchSubpluginDir(string $path, string $mode, string $plugin_from, string $plugin_for) Get a sub‑plugin directory listing.
 * @method static string fetchDir(string $dir, string $mode) Get a directory listing (static wrapper).
 * @method static void addPhpView(string $bbn_inc_file, string $bbn_inc_content, array $bbn_inc_data = []) Render a PHP view and return its output.
 * @method void addToViews(string $path, string $mode, View $view) Register a view in the loaded‑views cache.
 * @method bool check()                             Check if a route has been resolved.
 * @method array|mixed|null getInfo()               Get the resolved route information.
 * @method string getUrl()                          Get the current request URL.
 * @method string getRequest()                      Get the raw request string.
 * @method ?array getParams()                       Get request parameters.
 * @method array getPost()                          Get processed POST data.
 * @method array getGet()                           Get GET parameters.
 * @method array getFiles()                         Get uploaded files.
 * @method ?string getMode()                        Get the current request mode.
 * @method void setMode(string $mode)               Set the current request mode.
 * @method bool isCli()                             Detect CLI execution.
 * @method Mvc reroute(string $path = '', mixed $post = false, mixed $arguments = false) Reroute the request (chainable).
 * @method bool hasView(string $path = '', string $mode = 'html') Check if a view exists.
 * @method void addToViews(string $path, string $mode, View $view) Register a view.
 * @method string getView(string $path, string $mode = 'html', ?array $data = null) Render a view and return its string.
 * @method bool viewExists(string $path, string $mode = 'html') Check if a view exists.
 * @method bool modelExists(string $path)           Check if a model exists.
 * @method bool controllerExists(string $path, bool $private = false) Check if a controller exists.
 * @method string getExternalView(string $full_path, string $mode = 'html', ?array $data = null) Render a view from a different root.
 * @method ?array getPluginFromComponent(string $name) Get the plugin name from a component.
 * @method ?array routeComponent(string $name)      Route a component to its definition.
 * @method ?string customPluginView(string $path, string $mode, array $data, string $plugin) Get a view from a custom plugin.
 * @method bool hasCustomPluginModel(string $path, string $plugin) Check if a custom plugin model exists.
 * @method ?array customPluginModel(string $path, array $data, Controller $ctrl, string $plugin, ?int $ttl = null) Get a model from a custom plugin.
 * @method bool hasSubpluginModel(string $path, string $plugin, string $subplugin) Check if a sub‑plugin model exists.
 * @method ?array subpluginModel(string $path, array $data, Controller $ctrl, string $plugin, string $subplugin, ?int $ttl = null) Get a model from a sub‑plugin.
 * @method bool deleteSubpluginModelCache(string $path, array $data, string $plugin, string $subplugin) Delete a cached model from a sub‑plugin.
 * @method bool deleteCustomPluginModelCache(string $path, array $data, string $plugin) Delete a cached model from a custom plugin.
 * @method bool deleteModelCache(string $path, array $data) Delete a cached model.
 * @method bool deletePluginModelCache(string $path, array $data, string $plugin) Delete a cached model from a plugin.
 * @method string subpluginView(string $path, string $mode, array $data, string $plugin, string $subplugin) Render a view from a sub‑plugin.
 * @method bool hasPluginView(string $path, string $mode, string $plugin) Check if a plugin view exists.
 * @method ?string getPluginView(string $path, string $mode, array $data, string $plugin) Get a view from a plugin.
 * @method mixed getModel(string $path, array $data, Controller $ctrl) Get a model instance.
 * @method array getModelGroup(string $path, array $data, Controller $ctrl) Get a group of models.
 * @method array getCustomModelGroup(string $path, string $plugin, array $data, Controller $ctrl) Get a group of custom plugin models.
 * @method ?array getSubpluginModelGroup(string $path, string $plugin_from, string $plugin_for, array $data, Controller $ctrl) Get a group of sub‑plugin models.
 * @method ?array getPluginModel(string $path, array $data, Controller $ctrl, string $plugin, ?int $ttl = null) Alias for {@see customPluginModel()}.
 * @method ?array getSubpluginModel(string $path, array $data, Controller $ctrl, string $plugin, string $subplugin, ?int $ttl = null) Alias for {@see subpluginModel()}.
 * @method ?array getCachedModel(string $path, array $data, Controller $ctrl, int $ttl = 0) Get a model from cache (or cache it).
 * @method void setCachedModel(string $path, array $data, Controller $ctrl, int $ttl = 0) Cache a model.
 * @method void deleteCachedModel(string $path, array $data, Controller $ctrl) Delete a cached model.
 * @method void addInc(string $name, object $obj)   Add a property to the `$inc` object.
 * @method void process()                           Process the current MVC stack and render the output.
 * @method bool hasContent()                        Check if the current controller has content to output.
 * @method void transform(callable $fn)            Transform the output object via a callback.
 * @method void output()                            Send the final output to the browser / CLI.
 * @method ?Db getDb()                     Get the database connection (if used inside a controller).
 * @method int setPrepath(string $path)             Set the request pre‑path.
 * @method string getPrepath()                      Get the current pre‑path.
 * @method ?array getRoutes(string $type = 'root') Get defined routes of a given type.
 *
 * @property-read array $loaded_views               Internal cache of loaded views (html / css / js).
 * @property-read bool $is_debug                   Debug mode flag.
 * @property-read string $app_name                 Application name (static).
 * @property-read string $app_prefix             Application prefix (static).
 * @property-read string $app_path               Application path (static).
 * @property-read string $cur_path               Current URL path (static).
 * @property-read string $public_path            Public web‑root path (static).
 * @property-read string $lib_path               Vendor library path (static).
 * @property-read string $data_path              Data directory (static).
 * @property-read string $tmp_path               Temp directory (static).
 * @property-read bool $db_in_controller         Flag indicating whether the DB object is accessible from controllers.
 * @property-read array $constants               User‑defined constants.
 * @property-read array $post                    Processed POST data (lazy‑loaded).
 * @property-read array $loaded                  Aggregated loaded resources (views, models, controllers).
 * @property-read array $static_routes           List of static routes.
 * @property-read array $authorized_routes       List of authorized routes.
 * @property-read array $forbidden_routes        List of forbidden routes.
 * @property-read string $root                   Base URL root (instance).
 * @property-read array $plugins                 Registered plugins (instance).
 * @property-read array $loaded                  Aggregated loaded resources (instance).
 * @property-read array $static_routes           Static routes list (instance).
 * @property-read array $authorized_routes       Authorized routes list (instance).
 * @property-read array $forbidden_routes        Forbidden routes list (instance).
 * @property-read string $root                   Base URL root (instance).
 */
final class Mvc implements Api
{
  /* -----------------------------------------------------------------------
     *  TRAITS & STATIC PROPERTIES
     * ----------------------------------------------------------------------- */
  use Singleton;
  use Common;

  /**
   * @var array The list of loaded views indexed by mode (`html`, `css`, `js`).
   */
  private static $_loaded_views = [
    'html' => [],
    'css'  => [],
    'js'   => []
  ];

  /**
   * @var bool Debug flag – toggled via {@see debug()}.
   */
  private static $_is_debug = false;

  /**
   * @var string Application name (defined via BBN_APP_NAME).
   */
  private static $_app_name;

  /**
   * @var string Application prefix (e.g. a module name).
   */
  private static $_app_prefix;

  /**
   * @var string Full application path (static).
   */
  private static $_app_path;

  /**
   * @var string Current URL path (static).
   */
  private static $_cur_path;

  /**
   * @var string Path to the public web‑root (static).
   */
  private static $_public_path;

  /**
   * @var string Path to the vendor libraries (static).
   */
  private static $_lib_path;

  /**
   * @var string Path to the data directory (static).
   */
  private static $_data_path;

  /**
   * @var string Path to the temporary directory (static).
   */
  private static $_tmp_path;

  /**
   * Flag indicating that a DB object may be used inside a controller.
   */
  protected static $db_in_controller = false;

  protected static $globals = [];
  /**
   * @var bool Internal flag – request has been routed.
   */
  private $is_routed = false;

  /**
   * @var string Default controller name (fallback).
   */
  private $default;

  /**
   * @var bool Internal flag – controller is controlled by the router.
   */
  private $is_controlled = false;

  /**
   * @var Timer Timer used to measure request duration.
   */
  private Timer $timer;

  /**
   * @var float Request start timestamp (micro‑seconds).
   */
  private float $startTime;

  /**
   * @var null|Controller Current controller instance.
   */
  protected $controller;

  /**
   * @var Db|null Database connection (when a controller asks for it).
   */
  protected $db;

  /**
   * @var Environment Environment helper.
   */
  protected $env;

  /**
   * @var Router Router helper.
   */
  protected $router;

  /**
   * @var array Configuration data for the current request.
   */
  protected $info;

  /**
   * @var string Base URL root (instance).
   */
  protected $root = '';

  /**
   * @var array Registered plugins (instance).
   */
  protected $plugins;

  /**
   * @var array Aggregated loaded resources (instance).
   */
  protected $loaded = [
    'views' => [
      'html' => [],
      'css'  => [],
      'js'   => []
    ],
    'models' => [],
    'ctrls'  => []
  ];

  /**
   * @var array List of static routes (e.g. “/static/*”).
   */
  protected $static_routes = [];

  /**
   * @var array Routes that are allowed for the current user.
   */
  protected $authorized_routes = [];

  /**
   * @var array Routes that are forbidden for the current user.
   */
  protected $forbidden_routes = [];

  /**
   * @var array Raw POST data (lazy‑loaded).
   */
  protected $post;

  /**
   * @var array User‑defined constants (key => value).
   */
  protected $constants = [];

  /**
   * @var stdClass Object that can be filled after object creation
   *               and can be used as a global namespace (`add_inc`).
   */
  public $inc;

  /**
   * @var array Generic data array (e.g. passed to views).
   */
  public $data = [];

  // Same as $data but kept for backward compatibility.
  public $o;

  /**
   * @var object|null Output object (used for rendering).
   */
  public $obj;

  /**
   * Flag used to know whether the view has been processed.
   */
  public $checkerDone = false;

  /**
   * Strings that are forbidden to appear in URLs (security).
   */
  public static $reserved = ['_private', '_common', '_htaccess'];

    /* -----------------------------------------------------------------------
     *  CONSTRUCTION / INITIALISATION
     * ----------------------------------------------------------------------- */
  /**
   * Initialise all static path constants (if they are not already set) and
   * prepare the internal timer.
   */
  public static function initPath(): void
  {
    if (!self::$_app_name) {
      self::$_app_name       = defined('BBN_APP_NAME')       ? constant('BBN_APP_NAME')       : 'app';
      self::$_app_path       = defined('BBN_APP_PATH')       ? constant('BBN_APP_PATH')       : '';
      self::$_app_prefix     = defined('BBN_APP_PREFIX')     ? constant('BBN_APP_PREFIX')     : '';
      self::$_cur_path       = defined('BBN_CUR_PATH')       ? constant('BBN_CUR_PATH')       : '';
      self::$_public_path    = defined('BBN_PUBLIC')         ? constant('BBN_PUBLIC')         : '';
      self::$_lib_path       = defined('BBN_LIB_PATH')       ? constant('BBN_LIB_PATH')       : '';
      self::$_data_path      = defined('BBN_DATA_PATH')      ? constant('BBN_DATA_PATH')      : '';
      self::$_tmp_path       = defined('BBN_TMP_PATH')      ? constant('BBN_TMP_PATH')      : '';
    }
  }

  /**
   * Return the application name.
   *
   * @return string
   */
  public static function getAppName(): string
  {
    self::initPath();
    return self::$_app_name;
  }

  /**
   * Return the application prefix (if any).
   *
   * @return ?string
   */
  public static function getAppPrefix(): ?string
  {
    self::initPath();
    return self::$_app_prefix;
  }

  /**
   * Return the full application path.
   *
   * @param bool $raw If true, the trailing “src/” is omitted.
   * @return string
   */
  public static function getAppPath(bool $raw = false): string
  {
    self::initPath();
    return self::$_app_path . ($raw ? '' : 'src/');
  }

  /**
   * Return the current URL path.
   *
   * @return string
   */
  public static function getCurPath(): string
  {
    self::initPath();
    return self::$_cur_path;
  }

  /**
   * Return the public web‑root path.
   *
   * @return string
   */
  public static function getPublicPath(): string
  {
    self::initPath();
    return self::$_public_path;
  }

  /**
   * Return the vendor library path.
   *
   * @return string
   */
  public static function getLibPath(): string
  {
    self::initPath();
    return self::$_lib_path;
  }

  /**
   * Return the data directory (optionally for a plugin).
   *
   * @param string|null $plugin Plugin name.
   * @return string
   */
  public static function getDataPath(?string $plugin = null): string
  {
    self::initPath();
    return self::$_data_path . ($plugin ? 'plugins/' . $plugin . '/' : '');
  }

  /**
   * Return the temporary directory (optionally for a plugin).
   *
   * @param string|null $plugin Plugin name.
   * @return string
   */
  public static function getTmpPath(?string $plugin = null): string
  {
    self::initPath();
    return self::$_tmp_path . ($plugin ? 'plugins/' . $plugin . '/' : '');
  }

  /**
   * Return the log directory.
   *
   * @param string|null $plugin Plugin name.
   * @return string
   */
  public static function getLogPath(?string $plugin = null): string
  {
    self::initPath();
    return self::$_app_name ? self::getDataPath() . 'logs/' . ($plugin ? $plugin . '/' : '') : '';
  }

  /**
   * Return the cache directory.
   *
   * @param string|null $plugin Plugin name.
   * @return string
   */
  public static function getCachePath(?string $plugin = null): string
  {
    self::initPath();
    return self::getTmpPath() . 'cache/' . ($plugin ? $plugin . '/' : '');
  }

  /**
   * Return the content directory.
   *
   * @param string|null $plugin Plugin name.
   * @return string
   */
  public static function getContentPath(?string $plugin = null): string
  {
    self::initPath();
    return self::$_app_name ? self::getDataPath() . ($plugin ? 'plugins/' . $plugin . '/' : 'content/') : '';
  }

  /**
   * Return the URL part of a given plugin.
   *
   * @param string $plugin_name Plugin identifier.
   * @return null|string|false  URL fragment or false.
   */
  public static function getPluginUrl(string $plugin_name)
  {
    if ($mvc = self::getInstance()) {
      return $mvc->pluginUrl($plugin_name);
    }

    return null;
  }

  /**
   * Return the filesystem path of a given plugin.
   *
   * @param string $plugin_name Plugin identifier.
   * @return ?string
   */
  public static function getPluginPath(string $plugin_name): ?string
  {
    if ($mvc = self::getInstance()) {
      return $mvc->pluginPath($plugin_name);
    }

    return null;
  }

  /**
   * Return a temporary user directory.
   *
   * @param string|null $id_user  User ID.
   * @param string|null $plugin   Plugin name.
   * @return ?string
   */
  public static function getUserTmpPath(?string $id_user = null, ?string $plugin = null): ?string
  {
    if (!$id_user) {
      $usr = User::getInstance();
      if ($usr) {
        $id_user = $usr->getId();
      }
    }

    if ($id_user) {
      return self::getTmpPath() . 'users/' . $id_user . '/tmp/' . ($plugin ? $plugin . '/' : '');
    }

    return null;
  }

  /**
   * Return a user data directory.
   *
   * @param string|null $id_user  User ID.
   * @param string|null $plugin   Plugin name.
   * @return ?string
   */
  public static function getUserDataPath(?string $id_user = null, ?string $plugin = null): ?string
  {
    if (!self::$_app_name) {
      return null;
    }

    if (!$id_user) {
      $usr = User::getInstance();
      if ($usr) {
        $id_user = $usr->getId();
      }
    }

    if ($id_user) {
      return self::getDataPath() . 'users/' . $id_user . '/data/' . ($plugin ? $plugin . '/' : '');
    }

    return null;
  }

  /**
   * Include a model file and return its content (used internally).
   *
   * @param string $bbn_inc_file   File to include.
   * @param mixed  $model          Expected model name.
   * @param bool   $bbn_is_super   Whether to treat the model as “super”.
   * @return mixed|false           Parsed content or false on failure.
   */
  public static function includeModel(string $bbn_inc_file, $model, bool $bbn_is_super = false)
  {
    if (!is_file($bbn_inc_file)) {
      return false;
    }

    ob_start();
    $d = (function () use ($bbn_inc_file, $model, $bbn_is_super) {
      return include $bbn_inc_file;
    })();
    if (ob_get_level()) {
      ob_end_clean();
    }

    // Support for serialized objects
    if (is_string($d) && ($obj = @unserialize($d)) && is_object($obj)) {
      return $d;
    }

    if (is_object($d)) {
      $d = X::toArray($d);
    }

    return is_array($d) ? $d : false;
  }

    /* -----------------------------------------------------------------------
     *  PUBLIC GETTERS / HELPERS
     * ----------------------------------------------------------------------- */
  /**
   * Get the internal timer.
   *
   * @return Timer
   */
  public function getTimer(): Timer
  {
    return $this->timer;
  }

  /**
   * Store a constant‑like value.
   *
   * @param string $name  Constant name.
   * @param mixed  $value Value to store.
   * @return bool         True on success, false if the key already exists.
   */
  public function setConstant(string $name, $value): bool
  {
    if (!X::hasProp($this->constants, $name)) {
      $this->constants[$name] = $value;
      return true;
    }

    return false;
  }

  /**
   * Retrieve a stored constant‑like value.
   *
   * @param string $name Constant name.
   * @return mixed|null   Value or null if not found.
   */
  public function getConstant(string $name)
  {
    return X::hasProp($this->constants, $name) ? $this->constants[$name] : null;
  }

  /**
   * Store a constant‑like value.
   *
   * @param string $name  Constant name.
   * @param mixed  $value Value to store.
   * @return bool         True on success, false if the key already exists.
   */
  public static function setGlobal(string $name, $value): void
  {
    self::$globals[$name] = $value;
  }

  /**
   * Retrieve a stored constant‑like value.
   *
   * @param string $name Constant name.
   * @return mixed|null   Value or null if not found.
   */
  public static function getGlobal(string $name)
  {
    return X::hasProp(self::$globals, $name) ? self::$globals[$name] : null;
  }

  /**
   * Get all stored constants.
   *
   * @return array
   */
  public function getAllConstants(): array
  {
    return $this->constants;
  }

  /**
   * Retrieve the cookie that stores the session data.
   *
   * @return mixed|false  Decoded cookie value or false.
   */
  public function getCookie()
  {
    return empty($_COOKIE[constant('BBN_APP_NAME')]) ? false : json_decode($_COOKIE[constant('BBN_APP_NAME')], true)['value'];
  }

  /**
   * Get the list of static routes.
   *
   * @return array
   */
  public function getStaticRoutes(): array
  {
    return $this->static_routes;
  }

  /**
   * Get the request start timestamp.
   *
   * @return float
   */
  public function getStartTime(): float
  {
    return $this->startTime;
  }

  /**
   * Get the elapsed request duration (seconds).
   *
   * @return float
   */
  public function getDuration(): float
  {
    return microtime(true) - $this->startTime;
  }

  /**
   * Add one or more static routes.
   *
   * @param mixed $routes   One or more route strings.
   * @return int           Number of newly added routes.
   */
  public function addStaticRoute(...$routes): int
  {
    $res = 0;
    $aliases = array_flip($this->getRoutes('alias') ?: []);
    $todo = [];

    foreach ($routes as $a) {
      $todo[] = $a;
      if (isset($aliases[$a])) {
        $todo[] = $aliases[$a];
      } else {
        foreach ($aliases as $alias => $real) {
          if (Str::pos($a, $alias . '/') === 0) {
            $todo[] = $real . Str::sub($a, Str::len($alias));
            break;
          }
        }
      }
    }

    foreach ($todo as $a) {
      if (!\in_array($a, $this->static_routes, true)) {
        $this->static_routes[] = $a;
        $res++;
      }
    }

    return $res;
  }

  /**
   * Check whether a URL matches a static route.
   *
   * @param ?string $url  URL to test (defaults to the current request URL).
   * @return bool
   */
  public function isStaticRoute(?string $url = null): bool
  {
    if ($url === null) {
      $url = $this->getRequest();
    }

    if (\in_array($url, $this->static_routes, true)) {
      return true;
    }

    $auth_applicable = '';

    foreach ($this->static_routes as $ar) {
      if (
        (Str::sub($ar, -1) === '*')
        && (Str::pos($url, Str::sub($ar, 0, -1)) === 0)
      ) {
        if (Str::len($ar) > Str::len($auth_applicable)) {
          $auth_applicable = Str::sub($ar, 0, -1);
        }
      }
    }

    if ($auth_applicable) {
      foreach ($this->forbidden_routes as $forbidden) {
        if (
          (Str::sub($forbidden, -1) === '*')
          && (Str::pos($url, Str::sub($forbidden, 0, -1)) === 0)
          && (Str::len($auth_applicable) < Str::len($forbidden))
        ) {
          return false;
        } elseif ($url === $forbidden) {
          return false;
        }
      }

      return true;
    }

    return false;
  }

  /**
   * Add one or more authorized routes.
   *
   * @param mixed $routes   Route strings.
   * @return int           Number of newly added routes.
   */
  public function addAuthorizedRoute(): int
  {
    $res = 0;
    foreach (func_get_args() as $a) {
      if (!\in_array($a, $this->authorized_routes, true)) {
        $this->authorized_routes[] = $a;
        $res++;
      }
    }

    return $res;
  }

  /**
   * Add one or more forbidden routes.
   *
   * @param mixed $routes   Route strings.
   * @return int           Number of newly added routes.
   */
  public function addForbiddenRoute(): int
  {
    $res = 0;
    foreach (func_get_args() as $a) {
      if (!\in_array($a, $this->forbidden_routes, true)) {
        $this->forbidden_routes[] = $a;
        $res++;
      }
    }

    return $res;
  }

  /**
   * Check whether a URL is authorized for the current user.
   *
   * @param string $url  URL to test.
   * @return bool
   */
  public function isAuthorizedRoute(string $url): bool
  {
    if (\in_array($url, $this->authorized_routes, true)) {
      return true;
    }

    if ($this->isStaticRoute($url)) {
      return true;
    }

    $has_allow_all = false;
    $auth_applicable = '';
    foreach ($this->authorized_routes as $ar) {
      if ($ar === '*') {
        $has_allow_all = true;
        continue;
      }

      if (
        (Str::sub($ar, -1) === '*')
        && (Str::pos($url, Str::sub($ar, 0, -1)) === 0)
      ) {
        if (Str::len($ar) > Str::len($auth_applicable)) {
          $auth_applicable = Str::sub($ar, 0, -1);
        }
      }
    }

    if ($auth_applicable || $has_allow_all) {
      foreach ($this->forbidden_routes as $forbidden) {
        if (
          (Str::sub($forbidden, -1) === '*')
          && (Str::pos($url, Str::sub($forbidden, 0, -1)) === 0)
          && (Str::len($auth_applicable) < Str::len($forbidden))
        ) {
          return false;
        } elseif ($url === $forbidden) {
          return false;
        }
      }

      return true;
    }

    return false;
  }

  /**
   * Set the base URL root of the application.
   *
   * @param string $root  Root path (must end with '/').
   * @return void
   */
  public function setRoot(string $root): void
  {
    /** @todo a proper verification of the path */
    if (Str::pos($root, '/', -1) === false) {
      $root .= '/';
    }

    $this->root = $root;
  }

  /**
   * Get the base URL root of the application.
   *
   * @return string
   */
  public function getRoot(): string
  {
    return $this->root;
  }

  /**
   * Set the application locale.
   *
   * @param string $locale Locale identifier (e.g. “en_US”).
   * @return void
   */
  public function setLocale(string $locale): void
  {
    $this->env->setLocale($locale);
    $this->initLocaleDomain($this->info ? $this->info['plugin_name'] : null);
  }

  /**
   * Get the current application locale.
   *
   * @return ?string
   */
  public function getLocale(): ?string
  {
    return $this->env->getLocale();
  }

  /**
   * Fetch a directory listing.
   *
   * @param string $dir  Directory to list.
   * @param string $mode Mode (`model`, `view`, …).
   * @return mixed
   */
  public function fetchDir(string $dir, string $mode): mixed
  {
    return $this->router->fetchDir($dir, $mode);
  }

  /**
   * Fetch a custom directory listing.
   *
   * @param string $dir      Directory to list.
   * @param string $mode     Mode.
   * @param ?string $plugin  Plugin name.
   * @return mixed
   */
  public function fetchCustomDir(string $dir, string $mode, ?string $plugin): mixed
  {
    return $this->router->fetchCustomDir($dir, $mode, $plugin);
  }

  /**
   * Fetch a sub‑plugin directory listing.
   *
   * @param string $path      Path inside a sub‑plugin.
   * @param string $mode      Mode.
   * @param string $plugin_from  Source plugin.
   * @param string $plugin_for   Target plugin.
   * @return mixed
   */
  public function fetchSubpluginDir(string $path, string $mode, string $plugin_from, string $plugin_for): mixed
  {
    return $this->router->fetchSubpluginDir($path, $mode, $plugin_from, $plugin_for);
  }

  /**
   * Render a PHP view file.
   *
   * @param string $bbn_inc_file   View file to include.
   * @param string $bbn_inc_content Content to eval (usually a template).
   * @param array  $bbn_inc_data   Data to compact into the view scope.
   * @return string                Rendered view output.
   */
  public static function includePhpView(string $bbn_inc_file, string $bbn_inc_content, array $bbn_inc_data = []): string
  {
    $randoms = [];
    $_random = fn($i) => $randoms[$i] ?? ($randoms[$i] = md5(Str::genpwd()));
    ob_start();
    (function () use ($bbn_inc_file, $bbn_inc_content, $bbn_inc_data, $_random): void {
      if ($bbn_inc_content) {
        if (count($bbn_inc_data)) {
          foreach ($bbn_inc_data as $bbn_inc_key => $bbn_inc_val) {
            $$bbn_inc_key = $bbn_inc_val;
          }

          unset($bbn_inc_key, $bbn_inc_val);
        }

        unset($bbn_inc_data);

        /** @throws Exception on eval errors */
        try {
          eval(' ?>' . $bbn_inc_content);
        } catch (Exception $e) {
          error_log("Error for $bbn_inc_file: ". $e->getMessage());
          X::logError($e->getCode(), $e->getMessage(), $bbn_inc_file, $e->getLine());
        }
      }

      echo '';
    })();

    $c = ob_get_contents();
    if (ob_get_level()) {
      ob_end_clean();
    }

    return $c;
  }

  /**
   * Register a loaded view in the internal cache.
   *
   * @param string   $path  Full path to the view file.
   * @param string   $mode  View mode (`html`, `css`, `js`).
   * @param View $view  View instance.
   * @return void
   */
  private static function addView(string $path, string $mode, View $view): void
  {
    if (!isset(self::$_loaded_views[$mode][$path])) {
      self::$_loaded_views[$mode][$path] = $view;
    }

    // The original code returned the cached view – we keep the reference.
    // return self::$_loaded_views[$mode][$path];
  }

  /**
   * Set whether the DB object is accessible from controllers.
   *
   * @param bool $r  True to allow DB access inside controllers.
   */
  public static function setDbInController(bool $r = false): void
  {
    self::$db_in_controller = $r;
  }

  /**
   * Get current debug flag.
   *
   * @return bool
   */
  public static function getDebug(): bool
  {
    return self::$_is_debug;
  }

  /**
   * Enable or disable debug mode.
   *
   * @param int $state  Optional flag (true = enable, false = disable). Default = true.
   */
  public static function debug($state = 1)
  {
    self::$_is_debug = (bool) $state;
  }


    /* -----------------------------------------------------------------------
     *  ROUTE RESOLUTION
     * ----------------------------------------------------------------------- */
  /**
   * Resolve the current request route.
   *
   * @return Mvc   The MVC instance for chaining.
   */
  private function route($url = false): Mvc
  {
    if (is_null($this->info)) {
      $this->info = $this->getRoute($url ?: $this->getUrl() ?: '', $this->getMode() ?: '');
    }

    return $this;
  }

  /**
   * Register a plugin definition (taken from the routes configuration).
   *
   * @param array $plugin  Plugin definition array.
   */
  private function registerPlugin(array $plugin): void
  {
    if (isset($plugin['path'], $plugin['url'], $plugin['name'])) {
      $this->plugins[$plugin['name']] = [
        'name'  => $plugin['name'],
        'url'   => $plugin['url'],
        'path'  => $plugin['path']
      ];
    }
  }

  /**
   * Initialise the locale text‑domain.
   *
   * @param string|null $pluginName  Plugin name (optional).
   */
  private function initLocaleDomain(?string $pluginName = null): void
  {
    if (
      $this->router
      && $this->getLocale()
      && ($textdomain = $this->router->getLocaleDomain($pluginName))
    ) {
      textdomain($textdomain);
    }
  }

  /**
   * Main constructor – receives a DB connection (optional) and a routes
   * definition array (usually from `routes.json`).  It prepares all
   * static paths, the timer, the environment, the router and finally
   * resolves the current route.
   *
   * @param Db|null $db      Optional PDO‑like DB object.
   * @param array            $routes  Route configuration array.
   *
   * @throws Exception  If mandatory constants are missing.
   */
  public function __construct(?Db $db = null, $routes = [])
  {
    if (!defined('BBN_DEFAULT_MODE')) {
      define('BBN_DEFAULT_MODE', 'public');
    }

    if (!defined('BBN_CUR_PATH')) {
      define('BBN_CUR_PATH', '/');
    }

    if (!defined('BBN_APP_NAME')) {
      throw new Exception('BBN_APP_NAME must be defined');
    }

    if (!defined('BBN_APP_PATH')) {
      throw new Exception('BBN_APP_PATH must be defined');
    }

    if (!defined('BBN_DATA_PATH')) {
      throw new Exception('BBN_DATA_PATH must be defined');
    }

    self::singletonInit($this);
    self::initPath();

    $this->timer = new Timer();
    $this->startTime = microtime(true);
    $this->env = new Environment();

    // ---------------------------------------------------------------
    //  Database handling
    // ---------------------------------------------------------------
    $this->db = $db;
    $this->inc = new stdClass();
    if (is_array($routes)) {
      // -----------------------------------------------------------------
      //  Root routes
      // -----------------------------------------------------------------
      if (isset($routes['root'])) {
        foreach ($routes['root'] as $url => &$route) {
          if (isset($route['root']) && defined('BBN_' . strtoupper($route['root']) . '_PATH')) {
            $route['path'] = constant('BBN_' . strtoupper($route['root']) . '_PATH') . $route['path'];
          }

          if (!empty($route['path']) && Str::sub($route['path'], -1) !== '/') {
            $route['path'] .= '/';
          }

          if (isset($route['path'])) {
            $route['url'] = $url;
            $this->registerPlugin($route);
          }
        }
        unset($route);
      }

      // -----------------------------------------------------------------
      //  Allowed / Forbidden routes
      // -----------------------------------------------------------------
      if (isset($routes['allowed'])) {
        $this->authorized_routes = $routes['allowed'];
      }

      if (isset($routes['forbidden'])) {
        $this->forbidden_routes = $routes['forbidden'];
      }

      $this->default = $routes['default'] ?? 'home';
    }

    $this->initLocaleDomain();
    $this->router = new Router($this, $routes);
    $this->route(); // Resolve the first route now
  }

  /**
   * Destructor – clears static state.
   */
  public function destruct(): void
  {
    self::$_app_name = null;
    self::singletonUnset();
  }

  /**
   * Get the default controller name.
   *
   * @return string
   */
  public function getDefault(): string
  {
    return $this->default;
  }

  /**
   * Check if a route has been successfully resolved.
   *
   * @return bool
   */
  public function check(): bool
  {
    return $this->info ? true : false;
  }

  /**
   * Get the resolved route information.
   *
   * @return array|mixed|null
   */
  public function getInfo(): mixed
  {
    return $this->info;
  }

  /**
   * Get the list of registered plugins.
   *
   * @return array
   */
  public function getPlugins(): array
  {
    return $this->plugins;
  }

  /**
   * Check if a given plugin is registered.
   *
   * @param string $plugin Plugin identifier.
   * @return bool
   */
  public function hasPlugin(string $plugin): bool
  {
    return isset($this->plugins[$plugin]);
  }

  /**
   * Check if the given name corresponds to a known plugin.
   *
   * @param string $plugin Plugin identifier.
   * @return bool
   */
  public function isPlugin(string $plugin): bool
  {
    return isset($this->plugins[$plugin]);
  }

  /**
   * Get the filesystem path of a plugin.
   *
   * @param string $plugin Plugin identifier.
   * @param bool $raw      If true, do **not** append the `src/` sub‑folder.
   * @return string|null
   */
  public function pluginPath(string $plugin, bool $raw = false): ?string
  {
    if ($this->hasPlugin($plugin)) {
      return $this->plugins[$plugin]['path'] . ($raw ? '' : 'src/');
    }

    return null;
  }

  /**
   * Get the URL part of a plugin.
   *
   * @param string $plugin Plugin identifier.
   * @return null|string
   */
  public function pluginUrl(string $plugin): ?string
  {
    return $this->hasPlugin($plugin) ? Str::sub($this->plugins[$plugin]['url'], Str::len($this->root)) : false;
  }

  /**
   * Derive the plugin name from a URL/path.
   *
   * @param string $path Path to inspect.
   * @return string|null  Plugin name or null.
   */
  public function pluginName(string $path): ?string
  {
    foreach ($this->plugins as $name => $p) {
      if (Str::pos($path, $p['url']) === 0) {
        return $name;
      }
    }

    return null;
  }

    /* -----------------------------------------------------------------------
     *  ROUTE / CONTROLLER / MODEL / VIEW HELPERS
     * ----------------------------------------------------------------------- */
  /**
   * Retrieve a route definition from the router.
   *
   * @param string $path  Request path.
   * @param string $mode  Mode (`root`, `model`, `view`, …).
   * @return array|mixed|null|null
   */
  public function getRoute(string $path, string $mode): ?array
  {
    return $this->router->route($path, $mode);
  }

  /**
   * Get the resolved view file (if any).
   *
   * @return ?string
   */
  public function getFile(): ?string
  {
    return $this->info['file'] ?? null;
  }

  /**
   * Get the current request URL.
   *
   * @return string
   */
  public function getUrl(): string
  {
    return $this->env->getUrl();
  }

  /**
   * Get the raw request string.
   *
   * @return string
   */
  public function getRequest(): string
  {
    return $this->env->getRequest();
  }

  /**
   * Get request parameters (merged GET & POST, except internal keys).
   *
   * @return array
   */
  public function getParams(): ?array
  {
    return $this->env->getParams();
  }

  /**
   * Get processed POST data (lazy‑loaded).
   *
   * @return array
   */
  public function getPost(): array
  {
    if (!isset($this->post)) {
      $tmp = $this->env->getPost();
      $final = [];

      foreach ($tmp as $k => $v) {
        if (X::indexOf($k, '_bbn_') === 0) {
          $this->setConstant(Str::sub($k, 5), $v);
        } elseif ($k === '_bbn') {
          // No special handling – placeholder for future use.
        } else {
          $final[$k] = $v;
        }
      }

      $this->post = $final;
    }

    return $this->post;
  }

  /**
   * Get GET parameters.
   *
   * @return array
   */
  public function getGet(): array
  {
    return $this->env->getGet();
  }

  /**
   * Get uploaded files.
   *
   * @return array
   */
  public function getFiles(): array
  {
    return $this->env->getFiles();
  }

  /**
   * Get the current request mode (`public`, `private`, `cli`, …).
   *
   * @return ?string
   */
  public function getMode(): ?string
  {
    return $this->env->getMode();
  }

  /**
   * Set the current request mode.
   *
   * @param string $mode  New mode.
   * @return void
   */
  public function setMode(string $mode): void
  {
    $this->env->setMode($mode);
  }

  /**
   * Detect whether the request is executed from the CLI.
   *
   * @return bool
   */
  public function isCli(): bool
  {
    return $this->env->isCli();
  }

  /**
   * Reroute the request to a different path (chainable).
   *
   * @param string $path      New path.
   * @param ?array  $post      POST data (optional).
   * @param ?array  $arguments Arguments array (optional).
   * @return Mvc
   */
  public function reroute(string $path = '', ?array $post = null, ?array $arguments = null): Mvc
  {
    $this->env->simulate($path, $post, $arguments);
    $this->is_routed = false;
    $this->is_controlled = null;
    $this->info = null;
    $this->router->reset();
    $this->route();

    if ($arguments || !isset($this->info['args'])) {
      $this->info['args'] = $arguments;
    }

    if ($this->controller) {
      $this->controller->reset($this->info);
    }

    return $this;
  }

  /**
   * Check whether a view file exists in the current mode.
   *
   * @param string $path  View path.
   * @param string $mode  Mode (`html`, `css`, `js` …).
   * @return bool
   */
  public function hasView(string $path = '', string $mode = 'html'): bool
  {
    return array_key_exists($mode, self::$_loaded_views) && isset(self::$_loaded_views[$mode][$path]);
  }

  /**
   * Register a view in the internal cache.
   *
   * @param string   $path  View path.
   * @param string   $mode  View mode.
   * @param View $view  View instance.
   */
  public function addToViews(string $path, string $mode, View $view): void
  {
    if (!array_key_exists($mode, self::$_loaded_views[$mode])) {
      self::$_loaded_views[$mode] = [];
    }

    self::$_loaded_views[$mode][$path] = $view;
  }

  /**
   * Render a view and return its string representation.
   *
   * @param string $path   View path.
   * @param string $mode   View mode.
   * @param array|null $data  Optional data to compact into the view scope.
   * @return string
   * @throws Exception   If the mode is invalid or the path cannot be parsed.
   */
  public function getView(string $path, string $mode = 'html', ?array $data = null): string
  {
    if (!Router::isMode($mode) || !($path = Router::parse($path))) {
      throw new Exception(X::_('Incorrect mode $path $mode'));
    }

    $view = null;

    if ($this->hasView($path, $mode)) {
      $view = self::$_loaded_views[$mode][$path];
    } elseif ($info = $this->router->route($path, $mode)) {
      $view = new View($info);
      $this->addToViews($path, $mode, $view);
    }

    if (is_object($view) && $view->check()) {
      return $view->get($data);
    }

    return '';
  }

  /**
   * Determine whether a view exists (including dynamic routes).
   *
   * @param string $path  View path.
   * @param string $mode  Mode.
   * @return bool
   */
  public function viewExists(string $path, string $mode = 'html'): bool
  {
    if (!Router::isMode($mode) || !($path = Router::parse($path))) {
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
   * Determine whether a model exists.
   *
   * @param string $path  Model path.
   * @return bool
   */
  public function modelExists(string $path): bool
  {
    return (bool) $this->router->route($path, 'model');
  }

  /**
   * Determine whether a controller exists.
   *
   * @param string $path  Controller path.
   * @param bool $private  Whether to look in the private route table.
   * @return bool
   */
  public function controllerExists(string $path, bool $private = false): bool
  {
    return (bool) $this->router->route($path, $private ? 'private' : 'public', true);
  }

  /**
   * Retrieve a view from a different root directory.
   *
   * @param string $full_path  Full path to the view.
   * @param string $mode       View mode.
   * @param array|null $data   Optional data for the view.
   * @return string|null
   */
  public function getExternalView(string $full_path, string $mode = 'html', ?array $data = null): ?string
  {
    if (!Router::isMode($mode) && ($full_path = Str::parsePath($full_path))) {
      throw new Exception(X::_('Incorrect mode $full_path $mode'));
    }

    if (($this->getMode() === 'dom') && (!defined('BBN_DEFAULT_MODE') || (BBN_DEFAULT_MODE !== 'dom'))) {
      $full_path .= ($full_path === '' ? '' : '/') . 'index';
    }

    $view = null;

    if ($this->hasView($full_path, $mode)) {
      $view = self::$_loaded_views[$mode][$full_path];
    } elseif ($info = $this->router->route($full_path, 'free-' . $mode)) {
      $view = new View($info);
      $this->addToViews($full_path, $mode, $view);
    }

    if (is_object($view) && $view->check()) {
      return $view->get($data);
    }

    return '';
  }

  /**
   * Derive the plugin name from a component name.
   *
   * @param string $name Component identifier.
   * @return ?array  Plugin definition or null.
   */
  public function getPluginFromComponent(string $name): ?array
  {
    return $this->router->getPluginFromComponent($name);
  }

  /**
   * Route a component to its definition.
   *
   * @param string $name Component identifier.
   * @return ?array  Route definition or null.
   */
  public function routeComponent(string $name): ?array
  {
    return $this->router->routeComponent($name);
  }

  /**
   * Get a view from a custom plugin.
   *
   * @param string $path   View path.
   * @param string $mode   View mode.
   * @param array  $data   Data to pass to the view.
   * @param string $plugin Plugin identifier.
   * @return ?string|null  Rendered view or null on failure.
   */
  public function customPluginView(string $path, string $mode, array $data, string $plugin): ?string
  {
    if ($plugin && ($route = $this->router->routeCustomPlugin(Router::parse($path), $mode, $plugin))) {
      $view = new View($route);
      if ($view->check()) {
        return is_array($data) ? $view->get($data) : $view->get();
      }

      return '';
    }

    return null;
  }

  /**
   * Check whether a custom plugin model exists.
   *
   * @param string $path   Model path.
   * @param string $plugin Plugin identifier.
   * @return bool
   */
  public function hasCustomPluginModel(string $path, string $plugin): bool
  {
    return (bool) $this->router->routeCustomPlugin(Router::parse($path), 'model', $plugin);
  }

  /**
   * Retrieve a model from a custom plugin.
   *
   * @param string $path      Model path.
   * @param array  $data      Data to send to the model.
   * @param Controller $ctrl  Controller instance.
   * @param string $plugin    Plugin identifier.
   * @param ?int $ttl       Cache TTL (optional).
   * @return ?array|null   Model data or null on failure.
   */
  public function customPluginModel(string $path, array $data, Controller $ctrl, string $plugin, ?int $ttl = null): ?array
  {
    if (
      $plugin
      && ($route = $this->router->routeCustomPlugin(Router::parse($path), 'model', $plugin))
    ) {
      $model = new Model($this->db, $route, $ctrl, $this);
      if ($ttl) {
        return $model->getFromCache($data, '', $ttl);
      }

      return $model->get($data);
    }

    return null;
    /*
        throw new Exception(
            X::_(
                "Impossible to find the find the model %s in the plugin %s",
                $path,
                $plugin
            )
        );
        */
  }

  /**
   * Check whether a sub‑plugin model exists.
   *
   * @param string $path      Model path.
   * @param string $plugin    Plugin identifier.
   * @param string $subplugin Sub‑plugin identifier.
   * @return bool
   */
  public function hasSubpluginModel(string $path, string $plugin, string $subplugin): bool
  {
    return (bool) $this->router->routeSubplugin(Router::parse($path), 'model', $plugin, $subplugin);
  }

  /**
   * Check whether a sub‑plugin JS view exists.
   *
   * @param string $path      View path.
   * @param string $plugin    Plugin identifier.
   * @param string $subplugin Sub‑plugin identifier.
   * @return bool
   */
  public function hasSubpluginJs(string $path, string $plugin, string $subplugin): bool
  {
    return (bool) $this->router->routeSubplugin(Router::parse($path), 'js', $plugin, $subplugin);
  }

  /**
   * Check whether a sub‑plugin HTML view exists.
   *
   * @param string $path      View path.
   * @param string $plugin    Plugin identifier.
   * @param string $subplugin Sub‑plugin identifier.
   * @return bool
   */
  public function hasSubpluginHtml(string $path, string $plugin, string $subplugin): bool
  {
    return (bool) $this->router->routeSubplugin(Router::parse($path), 'html', $plugin, $subplugin);
  }

  /**
   * Check whether a sub‑plugin CSS view exists.
   *
   * @param string $path      View path.
   * @param string $plugin    Plugin identifier.
   * @param string $subplugin Sub‑plugin identifier.
   * @return bool
   */
  public function hasSubpluginCss(string $path, string $plugin, string $subplugin): bool
  {
    return (bool) $this->router->routeSubplugin(Router::parse($path), 'css', $plugin, $subplugin);
  }

  /**
   * Retrieve a sub‑plugin model.
   *
   * @param string $path      Model path.
   * @param array  $data      Data for the model.
   * @param Controller $ctrl  Controller instance.
   * @param string $plugin    Plugin identifier.
   * @param string $subplugin Sub‑plugin identifier.
   * @param ?int $ttl       Cache TTL.
   * @return ?array
   */
  public function subpluginModel(string $path, array $data, Controller $ctrl, string $plugin, string $subplugin, ?int $ttl = null): ?array
  {
    if (
      $plugin
      && $subplugin
      && ($route = $this->router->routeSubplugin(Router::parse($path), 'model', $plugin, $subplugin))
    ) {
      $model = new Model($this->db, $route, $ctrl, $this);
      $res = $ttl ? $model->getFromCache($data, '', $ttl) : $model->get($data);
      return $res;
    }

    throw new Exception(
      X::_(
        "Impossible to find the model %s from subplugin %s in plugin %s",
        $path,
        $subplugin,
        $plugin
      )
    );
  }

  /**
   * Delete a cached model from a sub‑plugin.
   *
   * @param string $path      Model path.
   * @param array  $data      Data used for the cache key.
   * @param string $plugin    Plugin identifier.
   * @param string $subplugin Sub‑plugin identifier.
   * @return bool
   */
  public function deleteSubpluginModelCache(string $path, array $data, string $plugin, string $subplugin): bool
  {
    if (
      $plugin
      && $subplugin
      && ($route = $this->router->routeSubplugin(Router::parse($path), 'model', $plugin, $subplugin))
    ) {
      $model = new Model($this->db, $route, $this->controller, $this);
      return (bool)$model->deleteCache($data);
    }

    throw new Exception(
      X::_(
        "Impossible to find the model %s from subplugin %s in plugin %s",
        $path,
        $subplugin,
        $plugin
      )
    );
  }

  /**
   * Delete a cached model from a custom plugin.
   *
   * @param string $path      Model path.
   * @param array  $data      Data used for the cache key.
   * @param string $plugin    Plugin identifier.
   * @return bool
   */
  public function deleteCustomPluginModelCache(string $path, array $data, string $plugin): bool
  {
    if (
      $plugin
      && ($route = $this->router->routeCustomPlugin(Router::parse($path), 'model', $plugin))
    ) {
      $model = new Model($this->db, $route, $this->controller, $this);
      return (bool)$model->deleteCache($data);
    }

    throw new Exception(
      X::_(
        "Impossible to find the model %s in plugin %s",
        $path,
        $plugin
      )
    );
  }

  /**
   * Delete a cached model.
   *
   * @param string $path  Model path.
   * @param array  $data  Data used for the cache key.
   * @return bool
   */
  public function deleteModelCache(string $path, array $data): bool
  {
    if (($path = Router::parse($path)) && ($route = $this->router->route($path, 'model'))) {
      $model = new Model($this->db, $route, $this->controller, $this);
      return (bool)$model->deleteCache($data);
    }

    throw new Exception(
      X::_(
        "Impossible to find the model %s",
        $path
      )
    );
  }

  /**
   * Delete a cached model from a plugin.
   *
   * @param string $path  Model path.
   * @param array  $data  Data used for the cache key.
   * @param string $plugin Plugin identifier.
   * @return bool
   */
  public function deletePluginModelCache(string $path, array $data, string $plugin): bool
  {
    if (
      $plugin
      && ($route = $this->router->routeCustomPlugin(Router::parse($path), 'model', $plugin))
    ) {
      $model = new Model($this->db, $route, $this->controller, $this);
      return (bool)$model->deleteCache($data);
    }

    throw new Exception(
      X::_(
        "Impossible to find the model %s in plugin %s",
        $path,
        $plugin
      )
    );
  }

  /**
   * Retrieve a sub‑plugin view.
   *
   * @param string $path      View path.
   * @param string $mode      View mode.
   * @param array  $data      Data for the view.
   * @param string $plugin    Plugin identifier.
   * @param string $subplugin Sub‑plugin identifier.
   * @return string
   */
  public function subpluginView(string $path, string $mode, array $data, string $plugin, string $subplugin): string
  {
    if (
      $plugin
      && $subplugin
      && ($route = $this->router->routeSubplugin(Router::parse($path), $mode, $plugin, $subplugin))
    ) {
      $view = new View($route);
      return $view->get($data);
    }

    throw new Exception(
      X::_(
        "Impossible to find the model %s from subplugin %s in plugin %s",
        $path,
        $subplugin,
        $plugin
      )
    );
  }

  /**
   * Check whether a plugin view exists.
   *
   * @param string $path   View path.
   * @param string $mode   View mode.
   * @param string $plugin Plugin identifier.
   * @return bool
   */
  public function hasPluginView(string $path, string $mode, string $plugin): bool
  {
    return (bool) $this->router->routeCustomPlugin(Router::parse($path), $mode, $plugin);
  }

  /**
   * Retrieve a view from a plugin.
   *
   * @param string $path   View path.
   * @param string $mode   View mode.
   * @param array  $data   Data for the view.
   * @param string $plugin Plugin identifier.
   * @return ?string|null  Rendered view or null on failure.
   */
  public function getPluginView(string $path, string $mode, array $data, string $plugin): ?string
  {
    return $this->customPluginView(Router::parse($path), $mode, $data, $this->pluginName($plugin));
  }

  /**
   * Retrieve a model (any order of arguments is accepted).
   *
   * @param string $path  Model path.
   * @param array  $data  Data to send to the model.
   * @param Controller $ctrl  Controller instance.
   * @return array|null   Model data or null.
   */
  public function getModel($path, array $data, Controller $ctrl)
  {
    if (($path = Router::parse($path)) && ($route = $this->router->route($path, 'model'))) {
      $model = new Model($this->db, $route, $ctrl, $this);
      return $model->get($data);
    }

    return [];
  }

  /**
   * Retrieve a group of models (e.g. all models in a directory).
   *
   * @param string $path  Directory path.
   * @param array  $data  Data to send to each model.
   * @param Controller $ctrl  Controller instance.
   * @return array        List of model data.
   */
  public function getModelGroup(string $path, array $data, Controller $ctrl): array
  {
    $res = [];
    if (($path = Router::parse($path))
      && ($items = $this->fetchDir($path, 'model'))
    ) {
      foreach ($items as $it) {
        $res[] = $this->getModel($it, $data, $ctrl);
      }
    }

    return $res;
  }

  /**
   * Retrieve a group of custom plugin models.
   *
   * @param string $path  Directory path.
   * @param string $plugin Plugin identifier.
   * @param array  $data  Data to send to each model.
   * @param Controller $ctrl  Controller instance.
   * @return array        List of model data.
   */
  public function getCustomModelGroup(string $path, string $plugin, array $data, Controller $ctrl): array
  {
    $res = [];
    if (($path = Router::parse($path))
      && ($items = $this->fetchCustomDir($path, 'model', $plugin))
    ) {
      foreach ($items as $it) {
        $res[$it] = $this->customPluginModel($it, $data, $ctrl, $plugin);
      }
    }

    return $res;
  }

  /**
   * Retrieve a group of sub‑plugin models.
   *
   * @param string $path          Directory path.
   * @param string $plugin_from   Source plugin.
   * @param string $plugin_for    Target plugin.
   * @param array  $data          Data to send to each model.
   * @param Controller $ctrl  Controller instance.
   * @return array                List of model data.
   */
  public function getSubpluginModelGroup(string $path, string $plugin_from, string $plugin_for, array $data, Controller $ctrl): array
  {
    $res = [];
    if (($path = Router::parse($path))
      && ($items = $this->fetchSubpluginDir($path, 'model', $plugin_from, $plugin_for))
    ) {
      foreach ($items as $it) {
        $res[$it] = $this->getSubpluginModel($it, $data, $ctrl, $plugin_from, $plugin_for);
      }
    }

    return $res;
  }

  /**
   * Alias for {@see customPluginModel()}.
   *
   * @param string $path      Model path.
   * @param array  $data      Data to send to the model.
   * @param Controller $ctrl  Controller instance.
   * @param string $plugin    Plugin identifier.
   * @param ?int $ttl       Cache TTL.
   * @return ?array
   */
  public function getPluginModel(string $path, array $data, Controller $ctrl, string $plugin, ?int $ttl = null): ?array
  {
    return $this->customPluginModel(Router::parse($path), $data, $ctrl, $this->pluginName($plugin), $ttl);
  }

  /**
   * Alias for {@see subpluginModel()}.
   *
   * @param string $path      Model path.
   * @param array  $data      Data to send to the model.
   * @param Controller $ctrl  Controller instance.
   * @param string $plugin    Plugin identifier.
   * @param string $subplugin Sub‑plugin identifier.
   * @param ?int $ttl       Cache TTL.
   * @return ?array
   */
  public function getSubpluginModel(string $path, array $data, Controller $ctrl, string $plugin, string $subplugin, ?int $ttl = null): ?array
  {
    return $this->subpluginModel($path, $data, $ctrl, $plugin, $subplugin, $ttl);
  }

  /**
   * Retrieve a model from cache (or cache it if it is not cached yet).
   *
   * @param string $path   Model path.
   * @param array  $data   Data to send to the model.
   * @param Controller $ctrl  Controller instance.
   * @param int $ttl       Cache TTL (0 = no expiry).
   * @return array|null   Cached model data or null.
   */
  public function getCachedModel(string $path, array $data, Controller $ctrl, int $ttl = 0): ?array
  {
    if (is_null($data)) {
      $data = $this->data;
    }

    if ($route = $this->router->route(Router::parse($path), 'model')) {
      $model = new Model($this->db, $route, $ctrl, $this);
      return $model->getFromCache($data, '', $ttl);
    }

    return [];
  }

  /**
   * Cache a model after retrieving it.
   *
   * @param string $path   Model path.
   * @param array  $data   Data to send to the model.
   * @param Controller $ctrl  Controller instance.
   * @param int $ttl       Cache TTL (0 = no expiry).
   */
  public function setCachedModel(string $path, array $data, Controller $ctrl, int $ttl = 0): void
  {
    if (is_null($data)) {
      $data = $this->data;
    }

    if ($route = $this->router->route(Router::parse($path), 'model')) {
      $model = new Model($this->db, $route, $ctrl, $this);
      $modelData = $model->get($data);
      $model->setCache($modelData, $data, '', $ttl);
    }
  }

  /**
   * Delete a cached model.
   *
   * @param string $path   Model path.
   * @param array  $data   Data used for the cache key.
   * @param Controller $ctrl  Controller instance.
   */
  public function deleteCachedModel(string $path, array $data, Controller $ctrl): void
  {
    if (is_null($data)) {
      $data = $this->data;
    }

    if ($route = $this->router->route(Router::parse($path), 'model')) {
      $model = new Model($this->db, $route, $ctrl, $this);
      $model->deleteCache($data, '');
    }
  }

  /**
   * Add a property to the `$inc` object (used to expose external objects).
   *
   * @param string $name   Property name.
   * @param object $obj    Object to store.
   */
  public function addInc(string $name, object $obj): void
  {
    if (isset($this->inc->{$name})) {
      throw new Exception(X::_('Impossible to add twice the same property (%s) to inc', $name));
    }

    $this->inc->{$name} = $obj;
  }

  /**
   * Process the current MVC stack and render the output.
   *
   * This method must be called after a route has been successfully resolved
   * (i.e. after {@see check()} returns `true`).  It creates the controller,
   * invokes its `process()` method and finally sends the output to the
   * browser / CLI.
   *
   * @throws Exception  If the resolved route does not contain an `info` array
   *                     or if the controller cannot be instantiated.
   */
  public function process(): void
  {
    if ($this->check()) {
      $this->obj = new stdClass();

      if (!is_array($this->info)) {
        $this->log("No info in MVC", $this->info);
        throw new Exception(X::_("No info in MVC"));
      }

      if (!$this->controller) {
        $this->controller = new Controller($this, $this->info, $this->data);
      }

      $this->controller->process();
    }
  }

  /**
   * Determine whether the current controller has content ready to be output.
   *
   * @return bool
   */
  public function hasContent(): bool
  {
    if ($this->check() && $this->controller) {
      return $this->controller->hasContent();
    }

    return false;
  }

  /**
   * Transform the output object via a user‑provided callback.
   *
   * @param callable $fn  Function that receives the output object.
   */
  public function transform(callable $fn): void
  {
    if ($this->check() && $this->controller) {
      $this->controller->transform($fn);
    }
  }

  /**
   * Send the final output to the client (browser or CLI).
   *
   * @throws Exception  If the controller could not be processed.
   */
  public function output(): void
  {
    if ($this->check() && $this->controller) {
      if ($this->controller->isStream()) {
        // Legacy path – should never be hit in normal operation.
        die('{"ended": true}');
      }

      $obj = $this->controller->get();

      if ($this->isCli()) {
        if (isset($obj->content)) {
          echo $obj->content;
        }

        return;
      }

      if ((gettype($obj) !== 'object') || (get_class($obj) !== 'stdClass')) {
        throw new Exception(X::_('Unexpected output: %s', gettype($obj)));
      }

      if ($this->obj && X::countProperties($this->obj)) {
        $obj = X::mergeObjects($obj, $this->obj);
      }

      $output = new Output($obj, $this->getMode());
      $output->run();
    } else {
      // 404 fallback
      Output::statusHeader(404);
    }
  }

    /* -----------------------------------------------------------------------
     *  DATABASE / ENVIRONMENT HELPERS
     * ----------------------------------------------------------------------- */
  /**
   * Get the database connection (if it has been enabled for controllers).
   *
   * @return Db|null
   */
  public function getDb(): ?Db
  {
    if (self::$db_in_controller && $this->db) {
      return $this->db;
    }

    return null;
  }

  /**
   * Set a pre‑path for the router.
   *
   * @param string $path  New pre‑path.
   * @return int          1 on success, throws otherwise.
   */
  public function setPrepath(string $path): int
  {
    if ($this->check()) {
      if ($this->router->getPrepath(false) === $path) {
        return 1;
      }

      if ($this->env->setPrepath($path) && $this->router->setPrepath($path)) {
        return 1;
      }
    }

    throw new Exception(
      X::_('The setPrepath method cannot be used in this MVC')
    );
  }

  /**
   * Get the current pre‑path.
   *
   * @return string
   */
  public function getPrepath(): string
  {
    if ($this->check()) {
      return $this->router->getPrepath();
    }

    return '';
  }

  /**
   * Get routes of a given type (`root`, `allowed`, `forbidden`, …).
   *
   * @param string $type  Route type.
   * @return ?array       Route list or null.
   */
  public function getRoutes(string $type = 'root'): ?array
  {
    if ($this->check()) {
      $routes = $this->router->getRoutes();
      return $routes[$type] ?? null;
    }

    return null;
  }


}
