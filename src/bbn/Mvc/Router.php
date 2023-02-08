<?php

/**
 * Manages the translations berween the URLs requested and the app filesystem.
 *
 * @category  MVC
 * @package MVC
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright 2015 BBN Solutions
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @link https://bbn.io/php/doc/bbn/mvc/router
 * @since May 12, 2015, 12:55:56 +0000
 */

namespace bbn\Mvc;

use bbn;
use bbn\X;

/**
 * @category MVC
 * @package MVC
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link https://bbn.io/php/doc/bbn/mvc/router
 */
class Router
{
  use Common;
  use bbn\Models\Tts\Retriever;

  /**
   * The list of types of controllers.
   *
   * @var array
   */
  private static $_controllers = ['cli', 'dom', 'content', 'public', 'private'];

  /**
   * The list of filetypes for each non controller element.
   *
   * @var array
   */
  private static $_filetypes = [
    'model' => ['php'],
    'html' => ['html', 'php'],
    'js' => ['js'],
    'css' => ['css', 'less', 'scss'],
  ];

  /**
   * The list of types.
   *
   * @var array
   */
  private static $_modes = [
    'image',
    'file',
    'cli',
    'private',
    'dom',
    'public',
    'model',
    'html',
    'js',
    'css',
  ];

  /**
   * @var array list of used routes with each original request to avoid looking for them again
   */
  private static $_known = [
    'cli' => [],
    'dom' => [],
    'public' => [],
    'private' => [],
    'model' => [],
    'html' => [],
    'js' => [],
    'css' => [],
    'component' => [],
  ];

  /**
   * @var array list of bound textdomains for gettext
   */
  private $_textdomains = [];

  /**
   * @var string The current mode as defined in self::$_modes
   */
  private $_mode;

  /**
   * @var string The path to prepend to the given path
   *
   * @todo deprecated
   */
  private $_prepath;

  /**
   * @var string The path to the app root (where is ./mvc)
   */
  private $_root;

  /**
   * @var bool|string
   */
  private $alt_root = false;

  /**
   * @var array The list of known external controllers routes
   */
  private $_routes = [];


  /**
   * Checks whether The given string is a valid mode.
   *
   * @param string $mode The mode as defined in self::$_modes
   *
   * @return bool
   */
  public static function isMode(string $mode): bool
  {
    return (bool)\in_array($mode, self::$_modes, true);
  }


  /**
   * Removes trailing slashes.
   *
   * @param string $path
   *
   * @return string
   */
  public static function parse(string $path): string
  {
    while (strpos($path, '//') !== false){
      $path = str_replace('//', '/', $path);
    }

    // case like my/dir/.
    if ((strlen($path) > 0) && (X::basename($path) === '.')) {
      $path = substr($path, 0, -1);
    }

    return $path ?: '.';
  }


  /**
   * Gets the name that the checker file should have for the given known route.
   *
   * @param array $cfg
   * @return string|null
   */
  public static function getCheckerFile(array $cfg): ?string
  {
    if (!empty($cfg['mode'])) {
      if (\in_array($cfg['mode'], self::$_controllers, true)) {
        return '_ctrl.php';
      }
      if ($cfg['mode'] === 'model') {
        return '_model.php';
      }
      if (!empty($cfg['ext']) && ($cfg['ext'] === 'less')) {
        return '_mixins.less';
      }
    }

    return null;
  }


  /**
   * Router constructor.
   *
   * @param bbn\Mvc $mvc
   * @param array $routes
   */
  public function __construct(bbn\Mvc $mvc, array $routes = [])
  {
    self::retrieverInit($this);
    $this->_mvc    = $mvc;
    $this->_routes = $routes;
    $this->_root   = $this->_mvc->appPath();
    $this->_registerLocaleDomain();
  }


  /**
   * Resets the full path in the mvc/mode of an external app (plugin).
   *
   * @return self
   */
  public function reset(): self
  {
    $this->alt_root = false;
    return $this;
  }


  /**
   * @param $path
   * @return bool
   */
  public function setPrepath($path): bool
  {
    if (!$this->checkPath($path)) {
      throw new Exception(X::_("The prepath $path is not valid"));
    }

    $this->_prepath = $path;
    if (substr($this->_prepath, -1) !== '/') {
      $this->_prepath = $this->_prepath . '/';
    }

    if ($this->_mode) {
      $this->route($this->_mvc->getUrl(), $this->_mode);
    }

    return true;
  }

  /**
   * @param int $with_slash
   * @return string
   */
  public function getPrepath($with_slash = 1): string
  {
    if (!empty($this->_prepath)) {
      return $with_slash ? $this->_prepath : substr($this->_prepath, 0, -1);
    }

    return '';
  }


  /**
   * @param string|null $plugin
   * @return string|null
   */
  public function getLocaleDomain(string $plugin = null): ?string
  {
    return $this->_textdomains[$plugin ?: 'main'] ?? null;
  }


  /**
   * Retrieves the plugin's name from the component's name if any.
   *
   * @param string $name
   * @return array|null
   */
  public function getPluginFromComponent(string $name): ?array
  {
    foreach ($this->getPlugins() as $n => $p) {
      if (X::indexOf($name, $n . '-') === 0) {
        return $p;
      }
    }

    return null;
  }


  /**
   * @param string $name
   * @return array|null
   */
  public function routeComponent(string $name): ?array
  {
    if ($p = $this->getPluginFromComponent($name)) {
      $root       = $p['path'] . 'src/';
      $prefix     = $p['name'] . '-';
      $plugin     = $p['name'];
      $plugin_url = $p['url'];
    }
    else {
      $root       = $this->appPath();
      $prefix     = (defined('BBN_APP_PREFIX') ? BBN_APP_PREFIX : BBN_APP_NAME) . '-';
      if (X::indexOf($name, $prefix) !== 0) {
        $prefix = substr($name, 0, strpos($name, '-') + 1);
      }
      $plugin     = null;
      $plugin_url = null;
    }

    if (!empty($root) && (X::indexOf($name, $prefix) === 0)) {
      $local_name = substr($name, strlen($prefix));
      $parts      = explode('-', $local_name);
      $root      .= 'components/';
      $path       = implode('/', $parts);
      $dir        = $root . $path;
      $this->_registerLocaleDomain($plugin);

      if (is_dir($dir)) {
        $res   = [
          'js' => [],
          'html' => [],
          'css' => [],
        ];
        $fpath = $root . $path . '/' . end($parts);
        foreach ($res as $mode => $c) {
          foreach (self::$_filetypes[$mode] as $f) {
            if (is_file($fpath . '.' . $f)) {
              $res[$mode] = $this->_set_known(
                [
                'file' => $fpath . '.' . $f,
                'path' => str_replace('-', '/', $local_name),
                'plugin' => $plugin_url,
                'plugin_name' => $plugin,
                'component' => true,
                'component_name' => $name,
                'ext' => $f,
                'mode' => $mode,
                'i18n' => $mode === 'js' ? $this->_find_translation($plugin ?? null) : null,
                ], true
              );
              break;
            }
          }
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * @param string $path
   * @param string $mode
   * @param string $plugin
   * @return array|null
   */
  public function routeCustomPlugin(string $path, string $mode, string $plugin): ?array
  {
    if ($root = $this->_get_custom_root($mode, $plugin)) {
      foreach (self::$_filetypes[$mode] as $t) {
        if (is_file($root . $path . '.' . $t)) {
          $file = $root . $path . '.' . $t;
          break;
        }
      }

      if (!empty($file)) {
        return $this->_set_known(
          [
          'file' => $file,
          'path' => $path,
          'ext' => $t,
          'plugin' => $plugin,
          'mode' => $mode,
          'i18n' => $t === 'js' ? $this->_find_translation($plugin ?? null) : null,
          ], true
        );
      }
    }

    return null;
  }


  /**
   * @param string $path
   * @param string $mode
   * @param string $plugin
   * @param string $subplugin
   * @return array|null
   */
  public function routeSubplugin(string $path, string $mode, string $pluginName, string $subplugin): ?array
  {
    if ($root = $this->_get_subplugin_root($mode, $pluginName, $subplugin)) {
      foreach (self::$_filetypes[$mode] as $t) {
        if (is_file($root . $path . '.' . $t)) {
          $file = $root . $path . '.' . $t;
          break;
        }
      }

      if (!empty($file)) {
        return $this->_set_known(
          [
          'file' => $file,
          'path' => $path,
          'ext' => $t,
          'plugin' => $this->pluginPath($pluginName),
          'plugin_name' => $pluginName,
          'mode' => $mode,
          'i18n' => $t === 'js' ? $this->_find_translation($plugin ?? null) : null,
          ], false
        );
      }
    }

    return null;
  }


  /**
   * @param string $path
   * @param string $mode
   *
   * @return array|mixed|null
   */
  public function route(string $path, string $mode): ?array
  {
    if (self::isMode($mode)) {
      // If there is a prepath defined we prepend it to the path
      if ($this->_prepath && (strpos($path, '/') !== 0) && (strpos($path, $this->_prepath) !== 0)) {
        $path = $this->_prepath . $path;
      }

      // We only try to retrieve a file path through a whole URL for controllers
      if (\in_array($mode, self::$_controllers, true)) {
        $this->_mode = $mode;
        return $this->_find_controller($path, $mode);
      }

      return $this->_find_mv($path, $mode);
    }

    return null;
  }


  /**
   * @param $path
   * @param $mode
   * @return array|null
   */
  public function fetchDir($path, $mode): ?array
  {
    // Only for views and models
    if (!self::isMode($mode) && !\in_array($mode, self::$_controllers)) {
      throw new \Exception(X::_("The mode %s is invalid", $mode));
    }

    // If there is a prepath defined we prepend it to the path
    if ($this->_prepath
        && (strpos($path, '/') !== 0)
        && (strpos($path, $this->_prepath) !== 0)
    ) {
      $path = $this->_prepath . $path;
    }

    /** @var string $root Where the files will be searched for by default */
    $root   = $this->_get_root($mode);
    $plugin = $this->_find_plugin($path);
    if ($plugin && ($alt_path = $plugin['url'])) {
      $alt_root = $this->_get_alt_root($mode, $alt_path);
    }
    elseif ($alt_root = $this->_get_alt_root($mode)) {
      $alt_path = $this->alt_root;
    }

    $dir = false;
    $dir1 = self::parse($root . $path);
    if (is_dir($dir1) && (strpos($dir1, $root) === 0)) {
      $dir = $dir1;
    }
    elseif (!empty($alt_path) && !empty($alt_root) && ($dir2 = self::parse($alt_root . substr($path, \strlen($alt_path) + 1))) && (strpos($dir2, $alt_root) === 0)
        && is_dir($dir2)
    ) {
      $dir = $dir2;
    }

    if (!$dir) {
      throw new \Exception(X::_("Impossible to find the directory for %s", $path));
    }

    $res   = [];
    $files = bbn\File\Dir::getFiles($dir);
    $prepath = $path && ($path !== '.') ? $path.'/' : '';
    if (!is_array($files)) {
      throw new \Exception(X::_("Impossible to find the directory for %s", $dir));
    }

    foreach ($files as $f) {
      if (\in_array(bbn\Str::fileExt($f), self::$_filetypes[$mode], true)) {
        $res[] = $prepath.bbn\Str::fileExt($f, true)[0];
      }
    }

    return $res;
  }


  /**
   * @param $path
   * @param $mode
   * @return array|null
   */
  public function fetchCustomDir(string $path, string $mode, string $plugin): array
  {
    // Only for views and models
    if (!self::isMode($mode) && !\in_array($mode, self::$_controllers)) {
      throw new \Exception(X::_("The mode %s is invalid", $mode));
    }

    // If there is a prepath defined we prepend it to the path
    if ($this->_prepath
        && (strpos($path, '/') !== 0)
        && (strpos($path, $this->_prepath) !== 0)
    ) {
      $path = $this->_prepath . $path;
    }

    /** @var string $root Where the files will be searched for by default */
    $root   = $this->_get_custom_root($mode, $plugin);

    $dir = false;
    $dir1 = self::parse($root . $path);
    if (is_dir($dir1) && (strpos($dir1, $root) === 0)) {
      $dir = $dir1;
    }

    if (!$dir) {
      throw new \Exception(X::_("Impossible to find the directory for %s", $path));
    }


    $res     = [];
    $files   = bbn\File\Dir::getFiles($dir);
    $prepath = $path && ($path !== '.') ? $path.'/' : '';
    if (!is_array($files)) {
      throw new \Exception(X::_("The directory %s doesn't exist", $dir));
    }

    foreach ($files as $f) {
      if (\in_array(bbn\Str::fileExt($f), self::$_filetypes[$mode], true)) {
        $res[] = $prepath.bbn\Str::fileExt($f, true)[0];
      }
    }

    return $res;
  }


  /**
   * @param $path
   * @param $mode
   * @return array|null
   */
  public function fetchSubpluginDir(string $path, string $mode, string $plugin_from, string $plugin_for): array
  {
    // Only for views and models
    if (!self::isMode($mode) && !\in_array($mode, self::$_controllers)) {
      throw new \Exception(X::_("The mode %s is invalid", $mode));
    }

    // If there is a prepath defined we prepend it to the path
    if ($this->_prepath
        && (strpos($path, '/') !== 0)
        && (strpos($path, $this->_prepath) !== 0)
    ) {
      $path = $this->_prepath . $path;
    }

    /** @var string $root Where the files will be searched for by default */
    $root   = $this->_get_subplugin_root($mode, $plugin_from, $plugin_for);

    $dir = false;
    $dir1 = self::parse($root . $path);
    if (is_dir($dir1) && (strpos($dir1, $root) === 0)) {
      $dir = $dir1;
    }

    if (!$dir) {
      throw new \Exception(X::_("Impossible to find the directory for %s", $path));
    }


    $res     = [];
    $files   = bbn\File\Dir::getFiles($dir);
    $prepath = $path && ($path !== '.') ? $path.'/' : '';
    if (!is_array($files)) {
      throw new \Exception(X::_("The directory %s doesn't exist", $dir));
    }

    foreach ($files as $f) {
      if (\in_array(bbn\Str::fileExt($f), self::$_filetypes[$mode], true)) {
        $res[] = $prepath.bbn\Str::fileExt($f, true)[0];
      }
    }

    return $res;
  }


  /**
   * @return array
   */
  public function getRoutes(): array
  {
    return $this->_routes;
  }


  /**
   * Get the full path in the mvc/mode of the main app.
   *
   * @param string $mode The mode as defined in self::$_modes
   *
   * @return string
   */
  private function _get_root(string $mode): ?string
  {
    if (self::isMode($mode)) {
      return $this->_root . $this->_get_mode_path($mode);
    }

    return null;
  }


  /**
   * Returns the mode path.
   *
   * @param string $mode
   * @return string
   * @throws Exception
   */
  private function _get_mode_path(string $mode)
  {
    if ($mode === 'dom') {
      return 'mvc/public/';
    }

    if ($mode === 'cli') {
      return 'cli/';
    }

    if (in_array($mode, self::$_modes)) {
      return 'mvc/'.$mode.'/';
    }

    throw new \Exception(X::_("The mode $mode doesn't exist in router!"));
  }


  /**
   * Get the full path in the mvc/mode of an external app (plugin).
   *
   * @param string $mode The mode as defined in self::$_modes
   * @param string|null $path The path of the plugin
   * @return string|null
   */
  private function _get_alt_root(string $mode, string $path = null): ?string
  {
    if (($path || $this->alt_root)
        && self::isMode($mode)
        && isset($this->_routes['root'][$path ?: $this->alt_root])
    ) {
      $res = bbn\Str::parsePath($this->_routes['root'][$path ?: $this->alt_root]['path']) .
        '/src/' . $this->_get_mode_path($mode);
      return $res;
    }

    return null;
  }


  /**
   * Checks whether a path is part of the routes['alias'] array.
   *
   * @param mixed $path
   *
   * @return string|null
   */
  private function _is_alias(string $path): ?string
  {
    if (!empty($this->_routes['alias'])) {
      $path = self::parse($path);
      if (isset($this->_routes['alias'][$path])) {
        return $path;
      }

      foreach (array_keys($this->_routes['alias']) as $p) {
        if (strpos($path, $p . '/') === 0) {
          return $p;
        }
      }
    }

    return null;
  }


  /**
   * Returns the alias of the given path if it is part of the routes['alias'] array.
   *
   * @param mixed $path
   *
   * @return string|null
   */
  private function _get_alias(string $path): ?string
  {
    $path = self::parse($path);
    if (isset($this->_routes['alias'][$path])) {
      return \is_array($this->_routes['alias'][$path]) ? $this->_routes['alias'][$path][0] : $this->_routes['alias'][$path];
    }

    return null;
  }


  /**
   * Checks whether a path is known for its corresponding mode.
   *
   * @param string $path
   * @param string $mode
   *
   * @return bool
   */
  private function _is_known(string $path, string $mode): bool
  {
    return self::isMode($mode) && isset(self::$_known[$mode][$path]);
  }


  /**
   * Retrieves the route from a given path in a given mode.
   *
   * @param string $path
   * @param string $mode
   *
   * @return array|null
   */
  private function _get_known(string $path, string $mode): ?array
  {
    if ($this->_is_known($path, $mode)) {
      // If it's a controller based on an alias the original known array has to be retrieved
      if (\in_array($mode, self::$_controllers, true)
          && \is_string(self::$_known[$mode][$path])
          && isset(self::$_known[$mode][self::$_known[$mode][$path]])
      ) {
        $path = self::$_known[$mode][$path];
      }

      return self::$_known[$mode][$path];
    }

    return null;
  }


  /**
   * Sets and stores a given route, adding the corresponding checkers.
   *
   * @param array $o
   * @param bool $save
   */
  private function _set_known(array $o, bool $save = true): ?array
  {
    // mode, path and file indexes are mandatory
    if (!isset($o['mode'], $o['path'], $o['file']) || !self::isMode($o['mode']) || !\is_string($o['path']) || !\is_string($o['file'])) {
      return null;
    }

    $mode = $o['mode'];
    $path = self::parse($o['path']);
    // The root in the main application where to search in is defined according to the mode
    $root = $this->_get_root($mode);
    if (!empty($o['plugin'])) {
      $this->_registerLocaleDomain($o['plugin']);
      $plugin_root = $this->_get_alt_root($mode, $o['plugin']);
      $plugin_path = substr($path, strlen($o['plugin']) + 1);
    }

    // About to define self::$_known[$mode][$path] so first check it has not already been defined
    if (!isset(self::$_known[$mode][$path])) {
      self::$_known[$mode][$path] = $o;
      $s                          = &self::$_known[$mode][$path];
      // Defining the checker files' name according to the mode (controllers, Models and CSS)
      $checker_file = self::getCheckerFile($o);
      if (!empty($checker_file)) {
        // Looking for checker files in each parent directory
        $s['checkers'] = [];
        $tmp           = $path;
        // There should be a new property fullPath
        if ((X::basename($o['file']) === 'index.php') && (X::basename($o['path']) !== 'index')) {
          $tmp .= '/index';
        }
        // Going backwards in the tree, so adding reversely to the array (prepending)
        while (\strlen($tmp) > 0) {
          $tmp     = self::parse(X::dirname($tmp));
          $checker = ($tmp === '.' ? '' : $tmp . '/') . $checker_file;
          if (!empty($o['plugin'])) {
            $plugin_path = self::parse(X::dirname($plugin_path));
            $alt_ctrl    = $plugin_root . ($plugin_path === '.' ? '' : $plugin_path . '/') . $checker_file;
            if (is_file($alt_ctrl) && !\in_array($alt_ctrl, $s['checkers'], true)) {
              array_unshift($s['checkers'], $alt_ctrl);
            }
          }

          if (is_file($root . $checker) && !\in_array($root . $checker, $s['checkers'], true)) {
            array_unshift($s['checkers'], $root . $checker);
          }

          if ($tmp === '.') {
            $tmp = '';
          }
        }

        // Particular case where it's CLI: we want the first _ctrl to be executed
        if (($mode === 'cli') && is_file($this->_get_root('public').$checker_file)) {
          array_unshift($s['checkers'], $this->_get_root('public').$checker_file);
        }
      }
    }

    if (!$save) {
      // If not saving the index is unset and the function will be relaunched in case the same request is done again
      $o = self::$_known[$mode][$path];
      unset(self::$_known[$mode][$path]);

      return $o;
    }

    return self::$_known[$mode][$path];
  }


  /**
   * Return the actual controller file corresponding to a given path.
   *
   * @param string $path
   * @param string $mode
   *
   * @return mixed
   */
  private function _find_controller($path, $mode): ?array
  {
    // Removing trailing slashes
    $path = self::parse($path);
    // If the result is already known we just return it
    if ($this->_is_known($path, $mode)) {
      return $this->_get_known($path, $mode);
    }

    /** @var string $root The directory corresponding to mode where the files will be searched for */
    $root = $this->_get_root($mode);
    /** @var bool|string $file Once found, full path and filename */
    $file = false;
    /** @var string $tmp Will contain the different states of the path along searching for the file */
    $tmp = $path;
    /** @var array $args Each element of the URL outside the file path */
    $args = [];
    // Decomposing the path into parts
    $parts = X::split($path, '/');
    // Checking first if the specific route exists (through $routes['alias'])
    if ($alias_name = $this->_is_alias($tmp)) {
      // Adding args accordingly
      while (X::join($parts, '/') !== $alias_name) {
        array_unshift($args, array_pop($parts));
        if (!count($parts)) {
          break;
        }
      }

      if ($alias = $this->_get_alias($alias_name)) {
        $tmp = $alias;
      }
    }

    /** @var array|null $plugin Plugin info if it's inside one */
    $plugin = $this->_find_plugin($tmp);
    /** @var string $root The alternative directory corresponding to mode where the files will be searched for */
    $plugin_root = $plugin ? $this->_get_alt_root($mode, $plugin['url']) : null;
    /** The path parsed from this alternative root */
    $plugin_path = $plugin ? substr($tmp, strlen($plugin['url']) + 1) : null;
    /** @var string $real_path The real application path (ie from root to the controller) */
    $real_path = null;
    // We go through the path, removing a bit each time until we find the corresponding file
    while (\strlen($tmp) > 0) {
      // navigation (we are in dom and dom is default or we are not in dom, i.e. public)
      if ((($mode === 'dom') && (BBN_DEFAULT_MODE === 'dom')) || ($mode !== 'dom')) {
        // Then looks for a corresponding file in the regular MVC
        if (file_exists($root . $tmp . '.php')) {
          $real_path = $tmp;
          $file      = $root . $tmp . '.php';
          $plugin    = false;
        }
        // Then looks for a home.php file in the corresponding directory
        elseif (is_dir($root . $tmp) && is_file($root . $tmp . '/home.php')) {
          $real_path = $tmp . '/home';
          $file      = $root . $tmp . '/home.php';
          $plugin    = false;
        }
        // If an alternative root exists (plugin), we look into it for the same
        elseif ($plugin) {
          // Corresponding file
          if (file_exists($plugin_root . $plugin_path . '.php')) {
            $real_path = $tmp;
            $file      = $plugin_root . $plugin_path . '.php';
            $root      = $plugin_root;
          }
          // home.php in corresponding dir
          elseif (is_dir($plugin_root . $plugin_path) && is_file($plugin_root . ($plugin_path ? $plugin_path . '/' : '') . 'home.php')) {
            $real_path = $tmp . '/home';
            $file      = $plugin_root . $plugin_path . '/home.php';
            $root      = $plugin_root;
          }
        }
      }

      // Full DOM requested
      if (!$file && ($mode === 'dom')) {
        // Root index file (if $tmp is at the root level)
        if (($tmp === '.') && !$plugin) {
          // If file exists

          if (file_exists($root . 'index.php')) {
            $real_path = '.';
            $file      = $root . 'index.php';
          }
          // Otherwise $file will remain undefined
          else {
            /* @todo throw an alert as there is no default index */
            $this->log(X::_('Impossible to find a route'));

            return null;
          }
        }
        // There is an index file in a subfolder
        elseif (file_exists($root . ($tmp === '.' ? '' : $tmp . '/') . 'index.php')) {
          $real_path = $tmp;
          $file      = $root . ($tmp === '.' ? '' : $tmp . '/') . 'index.php';
          $plugin    = false;
        }
        // An alternative root exists, we look into it
        elseif ($plugin) {
          // Corresponding file
          $dir = $plugin_root . ($plugin_path ? $plugin_path . '/' : '');
          if (is_dir($dir) && file_exists($dir . 'index.php')) {
            $real_path = $tmp;
            $file      = $dir . 'index.php';
            $root      = $plugin_root;
          }

          // home.php in corresponding dir
        }
      }

      if ($file) {
        break;
      }

      array_unshift($args, X::basename($tmp));
      $tmp = strpos($tmp, '/') === false ? '' : substr($tmp, 0, strrpos($tmp, '/'));
      if ($plugin) {
        $plugin_path = strpos($plugin_path, '/') === false ? '' : X::dirname($plugin_path);
      }

      if (empty($tmp) && ($mode === 'dom')) {
        $tmp = '.';
      } elseif ($tmp === '.') {
        $tmp = '';
      }
    }

    if (!$file && !$plugin && !empty($this->_routes['force']) && ($this->_routes['force'] !== $path)) {
      return $this->_find_controller(self::parse($this->_routes['force']), $mode);
    }

    /**
     * @todo Should there be a 404? If so, a real one or a default file? For which modes?
     */
    // Not found, sending the default controllers
    /*
                if ( !$file && is_file($root.'404.php') ){
                  $real_path = '404';
                  $file = $root.'404.php';
                }
                */

    if ($file) {
      return $this->_set_known(
        [
        'file' => $file,
        'path' => $real_path,
        'root' => X::dirname($root, 2) . '/',
        'request' => $path,
        'mode' => $mode,
        'plugin' => $plugin ? $plugin['url'] : false,
        'plugin_name' => $plugin ? $plugin['name'] : false,
        'args' => $args,
        ]
      );
    }

    return null;
    // Aaaargh!
    //die(X::dump("No default file defined for mode $mode $tmp (and no 404 file either)"));
  }


  /**
   * Returns Plugin info from the given path if exists.
   *
   * @param $path
   * @return array|null
   */
  private function _find_plugin($path): ?array
  {
    if ($plugins = $this->getPlugins()) {
      foreach ($plugins as $p) {
        if ((strpos($path, $p['url'] . '/') === 0) || ($p['url'] === $path)) {
          return $p;
        }
      }
    }

    return null;
  }


  /**
   * @param string|null $plugin
   * @return string|null
   */
  private function _find_translation(string $plugin = null): ?string
  {
    if ($locale = $this->getLocale()) {
      $locale = strtolower(substr($locale, 0, 2));
      $fpath = $plugin ? $this->pluginPath($plugin) : $this->_mvc->appPath();
      if (file_exists($fpath."locale/$locale/$locale.json")) {
        return $fpath."locale/$locale/$locale.json";
      }
    }

    return null;
  }


  /**
   * Alias for _get_root() method
   *
   * @param $mode
   * @return string|null
   */
  private function _get_classic_root($mode): ?string
  {
    return $this->_get_root($mode);
  }


  /**
   * @param $mode
   * @param $plugin
   * @return string|null
   */
  private function _get_plugin_root($mode, $plugin): ?string
  {
    if (self::isMode($mode)) {
      return $this->pluginPath($plugin) . $this->_get_mode_path($mode);
    }

    return null;
  }


  /**
   * @param $mode
   * @param $plugin
   * @param $subplugin
   * @return string|null
   */
  private function _get_subplugin_root($mode, $plugin, $subplugin): ?string
  {
    if (isset(self::$_filetypes[$mode])) {
      return $this->pluginPath($plugin) . 'plugins/' . $subplugin . '/' . $mode . '/';
    }

    return null;
  }


  /**
   * @param $mode
   * @param $plugin
   * @return string|null
   */
  private function _get_custom_root($mode, $plugin): ?string
  {
    if (isset(self::$_filetypes[$mode])) {
      return $this->_root . 'plugins/' . $plugin . '/' . $mode . '/';
    }

    return null;
  }


  /**
   * @param string $path
   * @param string $mode
   * @return array|null
   */
  private function _find_mv(string $path, string $mode): ?array
  {
    // Mode exists
    if (self::isMode($mode)) {
      if ($this->_is_known($path, $mode)) {
        return $this->_get_known($path, $mode);
      }

      $plugin      = $this->_find_plugin($path);
      $plugin_url  = $plugin ? $plugin['url'] : false;
      $plugin_name = $plugin ? $plugin['name'] : false;
      $root        = $this->_get_classic_root($mode);
      $file        = false;
      $alt_root    = false;
      if ($plugin_url) {
        $p        = $this->_routes['root'][$plugin_url];
        $plugin   = $p['name'];
        $alt_path = substr($path, strlen($plugin_url) + 1);
        $alt_root = $this->_get_plugin_root($mode, $plugin);
      }

      foreach (self::$_filetypes[$mode] as $t) {
        if (is_file($root . $path . '.' . $t)) {
          $file = $root . $path . '.' . $t;
          break;
        }
        elseif ($alt_root) {
          if (is_file($alt_root . $alt_path . '.' . $t)) {
            $file = $alt_root . $alt_path . '.' . $t;
            break;
          }
        }
      }

      if ($file) {
        return $this->_set_known(
          [
          'file' => $file,
          'path' => $path,
          'plugin' => $plugin_url,
          'plugin_name' => $plugin_name,
          'ext' => $t,
          'mode' => $mode,
          'i18n' => $t === 'js' ? $this->_find_translation($plugin ?? null) : null,
          ], true
        );
      }
    }

    return null;
  }


  /**
   * Setting up the textdomain (locale) for the given plugin.
   *
   * @param string $plugin
   *
   * @return string|null
   */
  private function _registerLocaleDomain(string $plugin = null): ?string
  {
    if (empty($plugin)) {
      if (is_dir($this->appPath().'locale')) {
        $lang_path = $this->appPath().'locale';
        $name      = 'main';
      }
    }
    elseif (isset($this->_routes['root'][$plugin]['name'])
        && is_dir($this->_routes['root'][$plugin]['path'] . 'src/locale')
    ) {
      $lang_path = $this->_routes['root'][$plugin]['path'] . 'src/locale';
      $name      = $this->_routes['root'][$plugin]['name'];
    }
    if (isset($lang_path, $name)) {
      if (!X::hasProp($this->_textdomains, $name)) {
        $idx_file  = $lang_path.'/index.txt';
        if (!is_file($idx_file)) {
          if (is_dir(X::dirname($idx_file))) {
            $idx = '';
          }
          else {
            return null;
          }
        }
        else {
          $idx = file_get_contents($idx_file);
        }


        $textdomain = $name.$idx;

        bindtextdomain($textdomain, $lang_path);
        bind_textdomain_codeset($textdomain, 'UTF-8');
        $this->_textdomains[$name] = $textdomain;
      }

      //$lang_path = X::dirname($this->_routes['root'][$plugin]['path']).'/src/locale';
      return $this->_textdomains[$name];
    }

    return null;
  }


}
