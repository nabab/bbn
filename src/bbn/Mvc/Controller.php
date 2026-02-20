<?php

namespace bbn\Mvc;

use Exception;
use stdClass;
use bbn\Db;
use bbn\Mvc as MvcCls;
use bbn\X;
use bbn\Str;
use bbn\Tpl;
use bbn\Util\Timer;
use bbn\File\System;

class Controller implements Api
{
  use Common;

  /**
   * When reroute is used $reroutes will be used to check we're not in an infinite reroute loop
   * @var array
   */
  private $_reroutes = [];

  /**
   * Is set to null while not controlled, then 1 if controller was found, and false otherwise.
   * @var null|boolean
   */
  private $_is_controlled;

  /**
   * Is set to false while not rerouted
   * @var null|boolean
   */
  private $_is_rerouted = false;

  /**
   * The internal path to the controller.
   * @var null|string
   */
  private $_path;

  /**
   * The request sent to get to the controller
   * @var null|string
   */
  private $_request;

  /**
   * The directory of the controller.
   * @var null|string
   */
  private $_dir;

  /**
   * The full path to the controller's file.
   * @var null|string
   */
  private $_file;

  /**
   * The full path to the root directory.
   * @var null|string
   */
  private $_root;

  /**
   * The checkers files (with full path)
   * If any they will be checked before the controller
   * @var array
   */
  private $_checkers = [];

  /**
   * The URL path to the plugin.
   * @var null|string
   */
  private $_plugin;

  /**
   * The plugin name.
   * @var null|string
   */
  private $_plugin_name;

  private $_stream = false;

  /**
   * @var Db The db connection if accepted by the mvc class.
   */
  public Db $db;
  /**
   * @var string The mode of the controller (dom, cli...), which will determine its route
   */
  public string $mode;
  /**
   * @var string The URL leading to this controller
   */
  public string $url;
  /**
   * The data model
   * @var array
   */
  public array $data = [];
  /**
   * All the parts of the path requested
   * @var array
   */
  public array $params = [];
  /**
   * All the parts of the path requested which are not part of the controller path
   * @var array
   */
  public array $arguments = [];
  /**
   * The data sent through POST
   * @var array
   */
  public array $post = [];
  /**
   * The data sent through GET
   * @var array
   */
  public array $get = [];
  /**
   * A numeric indexed array of the files sent through POST (different from native)
   * @var array
   */
  public array $files = [];
  /**
   * The output object
   * @var null|object
   */
  public ?stdClass $obj;
  /**
   * An external object that can be filled after the object creation and can be used as a global with the function add_inc
   * @var stdClass
   */
  public ?stdClass $inc;

  public Timer $timer;


  /**
   * This will call the initial build a new instance.
   * It should be called only once from within the script.
   * All subsequent calls to controllers should be done through $this->add($path).
   *
   * @param MvcCls        $mvc
   * @param array         $route
   * @param array|boolean $data
   */
  public function __construct(MvcCls $mvc, array $route, $data = false)
  {
    $this->_mvc = $mvc;
    $this->timer = $mvc->timer;
    $this->reset($route, $data);
  }

  public function setStream($type = ''): self
  {
    $content = ob_get_contents();
    if ($this->_stream || $content) {
      throw new Exception("Impossible to stream a controller that has already been streamed");
    }

    while (ob_get_level()) {
      ob_end_clean();
    }

    $this->_stream = true;
    set_time_limit(0);
    ini_set('output_buffering', 'Off');
    ini_set('zlib.output_compression', false);
    if (function_exists('apache_setenv')) {
      apache_setenv('no-gzip', '1');
      apache_setenv('dont-vary', '1');
    }

    header('X-Accel-Buffering: no');
    header("Content-Encoding: none");
    header("Transfer-Encoding: chunked"); // Or "Content-Length: ... " if chunking is not used
    header('Content-Type: plain/text; charset=UTF-8');
    //header('Content-Type: application/json; charset=UTF-8');

    return $this;
  }

  public function isStream(): bool
  {
    return $this->_stream;
  }


  public function stream($data): void
  {
    if ($this->_stream) {
      if (!$data) {
        return;
      }

      while (ob_get_level()) {
        ob_flush();
      }

      $st = json_encode(
        is_string($data) ?
          ['content' => $data] :
          (is_array($data) ? $data : ['success' => false])
      ) . PHP_EOL;
      $len = Str::len($st);
      if ($len < 8192) {
        $st .= str_repeat(' ', 8192 - $len);
      }

      echo $st;
      flush();
    }
  }


  /**
   * Pings the stream to check if the connection is still alive
   *
   * @return bool
   */
  public function pingStream(): bool
  {
    if (!$this->isStream()) {
      return false;
    }

    $this->stream(['__bbn_stream_ping__' => 1]);
    return (connection_status() === CONNECTION_NORMAL)
      && !connection_aborted();
  }


  /**
   * @param array $info
   * @param false $data
   */
  public function reset(array $info, $data = false)
  {
    if (!isset($info['mode'], $info['path'], $info['file'], $info['request'], $info['root'])) {
      X::log($info, 'error_control_reset');
      throw new Exception("Impossible to reset the controller without the necessary information");
    }

    $this->_path        = $info['path'];
    $this->_plugin      = $info['plugin'];
    $this->_request     = $this->getRequest();
    $this->_file        = $info['file'];
    $this->_root        = $info['root'];
    $this->arguments    = $info['args'];
    $this->_checkers    = $info['checkers'];
    $this->_plugin      = $info['plugin'];
    $this->_plugin_name = $info['plugin_name'];
    $this->mode         = $info['mode'];
    $this->data         = \is_array($data) ? $data : [];
    // When using CLI a first parameter can be used as route,
    // a second JSON encoded can be used as $this->post
    /** @var Db db */
    if ($db = $this->_mvc->getDb()) {
      $this->db = $db;
    }

    $this->inc    = &$this->_mvc->inc;
    $this->post   = $this->_mvc->getPost();
    $this->get    = $this->_mvc->getGet();
    $this->files  = $this->_mvc->getFiles();
    $this->params = $this->_mvc->getParams();
    $this->url    = $this->getUrl();
    $this->obj    = new stdClass();
  }


  /**
   * Add a route to authorized routes list if not already exists.
   *
   * @return int
   */
  public function addAuthorizedRoute(): int
  {
    return $this->_mvc->addAuthorizedRoute(...\func_get_args());
  }


  /**
   * Checks if a route is authorized.
   *
   * @param $url
   * @return bool
   */
  public function isAuthorizedRoute($url): bool
  {
    return $this->_mvc->isAuthorizedRoute($url);
  }


  /**
   * Returns the root of the application in the URL (base href).
   *
   * @return string
   */
  public function getRoot()
  {
    return $this->_mvc->getRoot();
  }


  /**
   * Sets the root of the application in the URL (base href).
   *
   * @param string $root
   * @return $this
   */
  public function setRoot($root)
  {
    $this->_mvc->setRoot($root);
    return $this;
  }


  /**
   * Get the request url.
   *
   * @return string|null
   */
  public function getUrl()
  {
    return $this->_mvc->getUrl();
  }


  /**
   * Returns the internal path to the controller.
   *
   * @return string|null
   */
  public function getPath()
  {
    return $this->_path;
  }


  /**
   * Returns the current controller's route, i.e as demanded by the client.
   *
   * @return string
   */
  public function getRequest()
  {
    return $this->_mvc->getRequest();
  }


  /**
   * Checks if the internal path to the controller exists.
   *
   * @return bool
   */
  public function exists()
  {
    return !empty($this->_path);
  }


  public function getAll()
  {
    return [
      'controller' => $this->getController(),
      'dir' => $this->getCurrentDir(),
      'local_path' => $this->getLocalPath(),
      'local_route' => $this->getLocalRoute(),
      'path' => $this->getPath(),
      'root' => $this->getRoot(),
      'request' => $this->getRequest(),
      'checkers' => $this->_checkers
    ];
  }


  /**
   * Returns the current controller's root directory.
   *
   * @return string
   */
  public function sayRoot()
  {
    return $this->_root;
  }


  /**
   * Returns the current controller's file's name.
   *
   * @return string
   */
  public function getController()
  {
    return $this->_file;
  }


  /**
   * Returns the current controller's path.
   *
   * @return string
   */
  public function getLocalPath()
  {
    if (($pp = $this->getPrepath()) && (Str::pos($this->_path, $pp) === 0)) {
      return Str::sub($this->_path, Str::len($pp));
    }

    return $this->_path;
  }


  /**
   * Returns the current controller's route.
   *
   * @return string
   */
  public function getLocalRoute()
  {
    if (($pp = $this->getPrepath()) && (Str::pos($this->_request, $pp) === 0)) {
      return Str::sub($this->_request, Str::len($pp));
    }

    return $this->_request;
  }


  /**
   * Returns the current controller's directory name.
   *
   * @return string
   */
  public function getCurrentDir(): ?string
  {
    if ($this->_path) {
      $p = X::dirname($this->_path);
      if ($p === '.') {
        return '';
      }

      if (
          ($prepath = $this->getPrepath())
          && (Str::pos($p, $prepath) === 0)
      ) {
        return Str::sub($p, Str::len($prepath));
      }

      return $p;
    }

    return null;
  }


  /**
   * If the controller is inside a plugin it will its name and null otherwise.
   *
   * @return null|string
   */
  public function getPlugin()
  {
    return $this->_plugin;
  }


  /**
   * This directly renders content with arbitrary values using the existing Mustache engine.
   *
   * @param string $view The view to be rendered
   * @param array|null $model The data model to fill the view with
   * @return string
   */
  public function render(string $view, array|null $model = null): string
  {
    if (empty($model) && !empty($this->data)) {
      $model = $this->data;
    }

    return \is_array($model) ? Tpl::render($view, $model) : $view;
  }


  /**
   * Returns true if called from CLI/Cron, false otherwise
   *
   * @return boolean
   */
  public function isCli()
  {
    return $this->_mvc->isCli();
  }


  /**
   * This will reroute a controller to another one seamlessly.
   *
   * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
   * @return void
   */
  public function reroute($path = '', $post = false, $arguments = false)
  {
    if (!\in_array($path, $this->_reroutes) && ($this->_path !== $path)) {
      $this->_reroutes[] = $path;
      $this->_mvc->reroute($path, $post, $arguments);
      $this->_is_rerouted = 1;
    }
  }


  /**
   * This will include a file from within the controller's path. Chainable
   *
   * @param string $file_name If .php is omitted it will be added
   * @return $this
   */
  public function incl($file_name)
  {
    if ($this->exists()) {
      $d = X::dirname($this->_file) . '/';
      if (Str::sub($file_name, -4) !== '.php') {
        $file_name .= '.php';
      }

      if ((Str::pos($file_name, '..') === false) && file_exists($d . $file_name)) {
        $bbn_path = $d . $file_name;
        unset($d, $file_name);
        include $bbn_path;
      }
    }

    return $this;
  }


  /**
   * This will add the given string to the script property, and create it if needed. Chainable
   *
   * @param string $script The javascript chain to add
   * @return $this
   */
  public function addScript($script)
  {
    if (\is_object($this->obj)) {
      if (!isset($this->obj->script)) {
        $this->obj->script = '';
      }

      $this->obj->script .= $script;
    }

    return $this;
  }


  /**
   * Register a plugin class using spl_autoload.
   *
   * @param $plugin_path
   * @return $this
   */
  public function registerPluginClasses($plugin_path): self
  {
    spl_autoload_register(
        function ($class_name) use ($plugin_path): void {
          if (
              (Str::pos($class_name, '/') === false)
              && (Str::pos($class_name, '.') === false)
          ) {
            $cls  = explode('\\', $class_name);
            $path = implode('/', $cls);
            if (file_exists($plugin_path . 'lib/' . $path . '.php')) {
              include_once $plugin_path . 'lib/' . $path . '.php';
            }
          }
        }
    );
    return $this;
  }


  /**
   * This will enclose the controller's inclusion
   * It can be publicly launched through check()
   *
   * @return boolean
   */
  private function control()
  {
    if ($this->_file && !isset($this->_is_controlled)) {
      $ok = 1;
      if ($this->_plugin) {
        $this->registerPluginClasses($this->pluginPath());
      }

      if (!$this->_mvc->isStaticRoute() && defined('BBN_ROOT_CHECKER')) {
        if (!$this->_mvc->checkerDone) {
          $this->_mvc->checkerDone = true;
          array_unshift($this->_checkers, constant('BBN_ROOT_CHECKER'));
        }
      }

      ob_start();
      foreach ($this->_checkers as $appui_checker_file) {
        // If a checker file returns false, the controller is not processed
        // The checker file can define data and inc that can be used in the subsequent controller
        if (self::includeController($appui_checker_file, $this, true) === false) {
          $ok = false;
          break;
        }
      }

      if (($log = ob_get_contents()) && \is_string($log)) {
        $this->obj->content = $log;
      }

      if (ob_get_level()) {
        ob_end_clean();
      }

      // If rerouted during the checkers
      if ($this->_is_rerouted) {
        $this->_is_rerouted = false;
        return $this->control();
      }

      if (!$ok) {
        return false;
      }

      $output = self::includeController($this->_file, $this);
      // If rerouted during the controller
      if ($this->_is_rerouted) {
        $this->_is_rerouted = false;
        return $this->control();
      }

      if (\is_object($this->obj) && !isset($this->obj->content) && !empty($output)) {
        $this->obj->content = $output;
      }

      $this->_is_controlled = 1;
    }

    return $this->_is_controlled ? true : false;
  }


  /**
   * This will launch the controller in a new function.
   * It is publicly launched through check().
   *
   * @return $this
   */
  public function process()
  {
    if (\is_null($this->_is_controlled)) {
      if ($this->_plugin_name) {
        $router = Router::getInstance();
        if ($textDomain = $router->getLocaleDomain($this->_plugin_name)) {
          $oldTextDomain = textdomain(null);
          if ($textDomain !== $oldTextDomain) {
            textdomain($textDomain);
          }
          else {
            unset($oldTextDomain);
          }
        }
      }

      $this->control();
      if (!empty($oldTextDomain)) {
        textdomain($oldTextDomain);
      }
    }

    return $this;
  }


  /**
   * Checks if the controller has been rerouted
   *
   * @return bool
   */
  public function hasBeenRerouted()
  {
    return (bool)$this->_is_rerouted;
  }


  /**
   * This will get a javascript view encapsulated in an anonymous function for embedding in HTML
   * from a path.
   *
   * @param string $path
   * @return string|false
   */
  public function getJs($path = '', array|null $data = null, $encapsulated = true)
  {
    $params = func_get_args();
    // The model can be set as first argument if the path is default
    if (\is_array($path)) {
      // In which case the second argument, if defined, is $encapsulated
      if (array_key_exists(1, $params)) {
        $encapsulated = $data;
      }

      $data = $path;
      $path = '';
    }

    if ($r = $this->getView($path, 'js', $data)) {
      return '<script>' .
        ( $encapsulated ? '(function(){' . PHP_EOL : '' ) .
        ( empty($data) ? '' : 'let data = ' . X::jsObject($data) . ';' ) .
        $r .
        //( $encapsulated ? '})(jQuery);' : '' ).
        ($encapsulated ? PHP_EOL . '})();' : '') .
        '</script>';
    }

    return false;
  }


  /**
   * This will get a javascript view encapsulated in an anonymous function for embedding in HTML
   * from a dir or an array of files.
   *
   * @param array|string $files
   * @param array        $data
   * @param boolean      $encapsulated
   * @return string|false
   */
  public function getJsGroup($files = '', array|null $data = null, $encapsulated = true)
  {
    if ($js = $this->getViewGroup($files, $data, 'js')) {
      return '<script>' .
      ( $encapsulated ? '(function($){' . PHP_EOL : '' ) .
      ( empty($data) ? '' : 'let data = ' . X::jsObject($data) . ';' ) .
      $js .
      //( $encapsulated ? '})(jQuery);' : '' ).
      ( $encapsulated ? PHP_EOL . '})();' : '' ) .
      '</script>';
    }

    return false;
  }


  /**
   * This will get a view for embedding in HTML.
   *
   * @param array|string $files
   * @param array        $data
   * @param string       $mode
   * @return string|false
   */
  public function getViewGroup($files = '', array|null $data = null, $mode = 'html')
  {
    if (!\is_array($files)) {
      if (!($tmp = $this->_mvc->fetchDir($files, $mode))) {
        $this->error("Impossible to get files from directory $files");
        return false;
      }

      $files = $tmp;
    }

    if (\is_array($files) && \count($files)) {
      $st = '';
      foreach ($files as $f) {
        if ($tmp = $this->getView($f, $mode, $data)) {
          $st .= $tmp;
        }
      }

      return $st;
    }

    $this->error('Impossible to get files from get_view_group files argument empty');
  }


  /**
   * This will get a CSS view encapsulated in a scoped style tag.
   *
   * @param string $path
   * @return string|false
   */
  public function getCss($path = '')
  {
    if ($r = $this->getView($path, 'css')) {
      return \CssMin::minify($r);
    }

    return false;
  }


  /**
   * This will get and compile a LESS view encapsulated in a scoped style tag.
   *
   * @param string $path
   * @return string|false
   */
  public function getLess($path = '')
  {
    return $this->getView($path, 'css', false);
  }


  /**
   * This will get a CSS view encapsulated in a scoped style tag and add it to the output object.
   *
   * @param string $path
   * @return self
   */
  public function addCss($path = '')
  {
    if ($css = $this->getCss($path)) {
      if (!isset($this->obj->css)) {
        $this->obj->css = '';
      }

      $this->obj->css .= $css;
    }

    return $this;
  }


  /**
   * This will get and compile a LESS view encapsulated in a scoped style tag and add it to the output object.
   *
   * @param string $path
   * @return self
   */
  public function addLess($path = '')
  {
    if ($css = $this->getLess($path)) {
      if (!isset($this->obj->css)) {
        $this->obj->css = '';
      }

      $this->obj->css .= $css;
    }

    return $this;
  }


  /**
   * This will add a javascript view from a file path to $this->obj->script
   * Chainable
   *
   * @return self
   */
  public function addJs()
  {
    $args     = \func_get_args();
    $has_path = false;
    foreach ($args as $a) {
      if ($new_data = $this->retrieveVar($a)) {
        $this->jsData($new_data);
      }
      elseif (\is_string($a)) {
        $has_path = 1;
      }
      elseif (\is_array($a)) {
        $this->jsData($a);
      }
      elseif ($a === true) {
        $this->jsData($this->data);
      }
    }

    if (!$has_path) {
      array_unshift($args, $this->_path);
    }

    $args[] = 'js';
    if ($r = $this->getView(...$args)) {
      $this->addScript($r);
    }

    return $this;
  }


  /**
   * This will add a javascript view from a directory path or an array of files to $this->obj->script
   * Chainable
   *
   * @param mixed $files
   * @param array $data
   * @return self
   */
  public function addJsGroup($files = '', array $data = [])
  {
    if ($js = $this->getViewGroup($files, $data, 'js')) {
      $this->jsData($data)->addScript($js);
    }

    return $this;
  }


  /**
   * Adds to the output object from an array.
   *
   * @param array $arr
   * @return $this
   */
  public function setObj(array $arr)
  {
    foreach ($arr as $k => $a) {
      $this->obj->{$k} = $a;
    }

    return $this;
  }


  /**
   * Sets the url on the output object.
   *
   * @param string $url
   * @return $this
   */
  public function setUrl(string $url)
  {
    $this->obj->url = $url;
    return $this;
  }


  /**
   * Sets the title on the output object.
   *
   * @param $title
   * @return $this
   */
  public function setTitle($title)
  {
    $this->obj->title = $title;
    return $this;
  }


  /**
   * Sets the icon on the output object.
   *
   * @param string $icon
   * @return $this
   */
  public function setIcon(string $icon)
  {
    $this->obj->icon = $icon;
    return $this;
  }


  /**
   * Sets background and font colors on the output object.
   *
   * @param string|null $bg
   * @param string|null $txt
   * @return $this
   */
  public function setColor(string|null $bg = null, string|null $txt = null)
  {
    if ($bg) {
      $this->obj->bcolor = $bg;
    }

    if ($txt) {
      $this->obj->fcolor = $txt;
    }

    return $this;
  }


  /**
   * Retrieves the plugin's name from the component's name if any
   */
  public function getPluginFromComponent(string $name)
  {
    return $this->_mvc->getPluginFromComponent($name);
  }


  /**
   * Returns a component from the given name if exists and null otherwise.
   *
   * @param string $name
   * @return array|null
   */
  public function routeComponent(string $name)
  {
    return $this->_mvc->routeComponent($name);
  }


  /**
   * Returns a component with it's content from the given name if exists and null otherwise.
   *
   * @param string $name
   * @param array $data
   * @return array|null
   */
  public function getComponent(string $name, array $data = []): ?array
  {
    if ($tmp = $this->routeComponent($name)) {
      if (!empty($tmp['js'])) {
        $locale = constant('BBN_LOCALE') ?? 'en';
        $plugin = $tmp['js']['plugin_name'] ?? null;
        $v   = new View($tmp['js']);
        $fs = new System();
        $path = $fs->createPath($this->dataPath($plugin));
        $file = "$path/component-registry-$locale.json";
        if (!$fs->exists($file)) {
          $fs->putContents($file, '[]');
        }

        $registry = $fs->decodeContents($file, 'json', true);
        $res = [
          'plugin' => $plugin,
          'name' => $name,
          'script' => $v->get($data),
        ];
        if (!empty($tmp['css'])) {
          $v          = new View($tmp['css']);
          $res['css'] = $v->get();
        }

        if (!empty($tmp['html'])) {
          $v = new View($tmp['html']);
          if (!$data) {
            $data = [];
          }

          $data['componentName'] = $name;
          $res['content']        = $v->get($data);
        }

        $hash = md5(json_encode($res));
        $write = false;
        if (!isset($registry[$name])) {
          $registry[$name] = [
            'name' => $name,
            'plugin' => $plugin,
            'hash' => $hash,
            'version' => 1
          ];
          $write = true;
        }
        elseif ($hash !== $registry[$name]['hash']) {
          $registry[$name]['version']++;
          $registry[$name]['hash'] = $hash;
          $write = true;
        }

        if ($write) {
          $fs->putContents($file, json_encode($registry, JSON_PRETTY_PRINT));
        }

        $res['version'] = $registry[$name]['version'];
        return $res;
      }
    }

    return null;
  }


  /**
   * Sets or add to the output object data property from an array.
   *
   * @param array $data
   * @return $this
   */
  public function jsData($data)
  {
    if (is_array($data) && X::isAssoc($data)) {
      if (!isset($this->obj->data)) {
        $this->obj->data = $data;
      }
      elseif (X::isAssoc($this->obj->data)) {
        $this->obj->data = X::mergeArrays($this->obj->data, $data);
      }
    }

    return $this;
  }


  /**
   * Parses arguments from an array.
   *
   * @param array $args
   * @return array
   */
  private function getArguments(array $args)
  {
    $r = [];
    foreach ($args as $a) {
      if ($new_data = $this->retrieveVar($a)) {
        $r['data'] = $new_data;
      }
      elseif (\is_string($a) && !isset($r['path'])) {
        $r['path'] = $a;
      }
      elseif (\is_string($a) && Router::isMode($a) && !isset($r['mode'])) {
        $r['mode'] = $a;
      }
      elseif (\is_array($a) && !isset($r['data'])) {
        $r['data'] = $a;
      }
      elseif (\is_bool($a) && !isset($r['die'])) {
        $r['die'] = $a;
      }
    }

    if (!isset($r['mode']) && isset($r['path']) && Router::isMode($r['path'])) {
      $r['mode'] = $r['path'];
      unset($r['path']);
    }

    if (empty($r['path'])) {
      $r['path'] = $this->_path;
      if (
          ($this->getMode() === 'dom')
          && (!defined('BBN_DEFAULT_MODE') || (BBN_DEFAULT_MODE !== 'dom'))
      ) {
        $r['path'] .= '/index';
      }
    }
    elseif (Str::pos($r['path'], './') === 0) {
      $r['path'] = $this->getCurrentDir() . Str::sub($r['path'], 1);
    }

    if (!isset($r['data'])) {
      $r['data'] = $this->data;
    }

    if (!isset($r['die'])) {
      $r['die'] = true;
    }

    return $r;
  }


  /**
   * This will get a view.
   *
   * @param string $path
   * @param string $mode
   * @return string|false
   */
  public function getView()
  {
    $args = $this->getArguments(\func_get_args());
    /*if ( !isset($args['mode']) ){
      $v = $this->_mvc->getView($args['path'], 'html', $args['data']);
      if ( !$v ){
        $v = $this->_mvc->getView($args['path'], 'php', $args['data']);
      }
    }
    else{
      $v = $this->_mvc->getView($args['path'], $args['mode'], $args['data']);
    }*/
    if (empty($args['mode'])) {
      $args['mode'] = 'html';
    }

    $v = $this->_mvc->getView($args['path'], $args['mode'], $args['data']);
    /*
    if ( !$v && $args['die'] ){
      die("Impossible to find the $args[mode] view $args[path] from $args[file]");
    }
    */
    return $v;
  }


  /**
   * This will get a view from a different root.
   *
   * @param string $full_path
   * @param string $mode
   * @param array|null $data
   *
   * @return false|string
   * @throws Exception
   */
  public function getExternalView(string $full_path, string $mode = 'html', ?array $data = null)
  {
    return $this->_mvc->getExternalView($full_path, $mode, $data);
  }


  /**
   * Retrieves a view of a custom plugin.
   *
   * @param string $path
   * @param string $mode
   * @param array $data
   * @param string|null $plugin
   *
   * @return string|null
   */
  public function customPluginView(string $path, string $mode = 'html', array $data = [], string|null $plugin = null): ?string
  {
    if (!$plugin) {
      $plugin = $this->getPlugin();
    }

    if ($plugin) {
      return $this->_mvc->customPluginView($path, $mode, $data, $plugin);
    }

    return null;
  }


  /**
   * Retrieves a view of a custom plugin.
   *
   * @param string $path
   * @param array $data
   * @param string|null $plugin
   *
   * @return string|null
   */
  public function customPluginModel(string $path, array $data = [], string|null $plugin = null): ?string
  {
    if (!$plugin) {
      $plugin = $this->getPlugin();
    }

    if ($plugin) {
      return $this->_mvc->customPluginModel($path, $data, $this, $plugin);
    }

    return null;
  }

  public function hasCustomPluginModel(string $path, string $plugin): bool
  {
    return $this->_mvc->hasCustomPluginModel($path, $plugin);
  }




  /**
   * This will get a view.
   *
   * @param string $path
   * @param string $type
   * @param array $data
   *
   * @return string|null
   */
  public function getPluginView(string $path, string $type = 'html', array $data = [])
  {
    return $this->_mvc->getPluginView($path, $type, $data, $this->getPlugin());
  }


  /**
   * Gets views for html, css and js.
   *
   * @param string $path
   * @param array $data
   * @param array|null $data2
   *
   * @return array
   */
  public function getPluginViews(string $path, array $data = [], array|null $data2 = null)
  {
    return [
      'html' => $this->_mvc->getPluginView($path, 'html', $data, $this->getPlugin()),
      'css' => $this->_mvc->getPluginView($path, 'css', [], $this->getPlugin()),
      'js' => $this->_mvc->getPluginView($path, 'js', $data2 ?: $data, $this->getPlugin()),
    ];
  }


  /**
   * Retrieves a model of a the plugin.
   *
   * @param $path
   * @param array $data
   * @param string|null $plugin
   * @param int $ttl
   *
   * @return array|null
   */
  public function getPluginModel(string $path, array $data = [], string|null $plugin = null, int $ttl = 0)
  {
    return $this->_mvc->getPluginModel($path, $data, $this, $plugin ?: $this->getPlugin(), $ttl);
  }


  /**
   * Get a sub plugin model (a plugin inside the plugin directory of another plugin).
   *
   * @param $path
   * @param array $data
   * @param string|null $plugin
   * @param string $subplugin
   * @param int $ttl
   *
   * @return array|null
   */
  public function getSubpluginModel(string $path, array $data, string|null $plugin, string $subplugin, int $ttl = 0): ?array
  {
    return $this->_mvc->getSubpluginModel($path, $data, $this, $plugin ?: $this->getPlugin(), $subplugin, $ttl);
  }


  /**
   * Returns true if the subplugin model exists.
   *
   * @param string $path
   * @param string $plugin
   * @param string $subplugin
   *
   * @return bool
   */
  public function hasSubpluginModel(string $path, string $plugin, string $subplugin): bool
  {
    return $this->_mvc->hasSubpluginModel(...\func_get_args());
  }


  /*
  public function get_php(){
    $args = $this->getArguments(\func_get_args());
    $v = $this->_mvc->getView($args['path'], 'php', $args['data']);
    if ( !$v && $args['die'] ){
      die("Impossible to find the PHP view $args[path]");
    }
    return $v;
  }

  public function get_html(){
    $args = $this->getArguments(\func_get_args());
    $v = $this->_mvc->getView($args['path'], 'html', $args['data']);
    if ( !$v && $args['die'] ){
      die("Impossible to find the HTML view $args[path]");
    }
    return $v;
  }
  */

  /**
   * Retrieves data from the data property using the key name from the provided variable name.
   *
   * @param $var
   *
   * @return false|mixed
   */
  private function retrieveVar($var)
  {
    if (\is_string($var) && (Str::pos($var, '$') === 0) && isset($this->data[Str::sub($var, 1)])) {
      return $this->data[Str::sub($var, 1)];
    }

    return false;
  }


  /**
   * Merges post data and result array with the current data
   * and gets the model then sets the output object.
   *
   * @return void
   */
  public function action()
  {
    $res = [
      'res' => [
        'success' => false
      ]
    ];
    $tmp = $this->addData($res)
            ->addData($this->post)
            ->getModel();
    if (!$tmp) {
      $tmp = $res;
    }

    $this->obj = X::toObject($tmp);
  }


  /**
   * Merges post data and result array with the current data
   * and gets the model from cache then sets the output object.
   *
   * @param int $ttl
   */
  public function cachedAction(int $ttl = 60)
  {
    $this->obj = X::toObject(
        $this->addData(['res' => ['success' => false]])->addData($this->post)->getCachedModel('', $this->data, $ttl)
    );
  }


  /**
   * Compile and echoes all the views with the given data
   *
   * @param string     $title The title of the final object
   * @param array|bool $data  The data, if true the path' model will be used
   * @param int        $ttl   The time-to-live value if cache must be used for the model
   * @param string     $path  The path for the views/model; if null the controller path will be used
   *
   * @return self
   */
  public function combo(
      string|null $title = null,
      $data = null,
      int|null $ttl = null,
      string $path = ''
  ): self
  {
    if (empty($path)) {
      $basename = X::basename($this->_file, '.php');
      if (X::indexOf(['index', 'home'], $basename) > -1) {
        $bits = X::split($this->_path, '/');
        if ((count($bits) === 1) && ($bits[0] === '.')) {
          $path = $basename;
        }
        elseif (end($bits) !== $basename) {
          $bits[] = $basename;
          $path   = X::join($bits, '/');
        }
      }
    }
    if ($this->getRoute($path ?: $this->_path, 'model')) {
      $model = $ttl === null
        ? $this->getModel($path, X::mergeArrays($this->post, $this->data))
        : $this->getCachedModel($path, X::mergeArrays($this->post, $this->data), $ttl);

      if ($model && is_array($model)) {
        $this->addData($model);
      }
      else {
        $model = [];
      }
    }
    elseif ($data === true) {
      $model = $this->data;
    }

    $this->obj->css = $this->getLess($path);
    if ($new_title = $this->retrieveVar($title)) {
      $this->setTitle($new_title);
    }
    elseif ($title) {
      $this->setTitle($title);
    }

    if ($tmp = $this->retrieveVar($data)) {
      $data = $tmp;
    }
    elseif (!\is_array($data)) {
      $data = $data === true ? $model : [];
    }

    if ($this->mode === 'dom') {
      $this->data['script'] = $this->getJs($path, $data);
    }
    else {
      $this->addJs($path, $data, false);
    }

    echo $this->getView($path);
    return $this;
  }


  /**
   * This will get a the content of a file located within the data path
   *
   * @param string $file_name
   * @return string|false
   */
  public function getContent($file_name)
  {
    if (
        $this->checkPath($file_name)
        && is_file($this->dataPath() . $file_name)
    ) {
      return file_get_contents($this->dataPath() . $file_name);
    }

    return false;
  }


  /**
   * This will return the path to the directory of the current controller
   *
   * @return string
   */
  public function getDir()
  {
    return $this->_dir;
  }


  /**
   * @return string
   */
  public function getPrepath()
  {
    if ($this->exists()) {
      return $this->_mvc->getPrepath();
    }

    return '';
  }


  /**
   * @param $path
   * @return $this
   * @throws Exception
   */
  public function setPrepath($path)
  {
    if ($this->exists() && $this->_mvc->setPrepath($path)) {
      $this->params = $this->_mvc->getParams();
      return $this;
    }

    throw new Exception(X::_("Prepath $path is not valid"));
  }


  /**
   * This will get the model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|null A data model
   * @throws Exception
   */
  public function getModel()
  {
    $args = \func_get_args();
    $die  = false;
    foreach ($args as $a) {
      if (\is_string($a)) {
        $path = $a;
      }
      elseif (\is_array($a)) {
        $data = $a;
      }
      elseif (\is_bool($a)) {
        $die = $a;
      }
    }

    if (empty($path)) {
      $path = $this->_path;
      if (($this->getMode() === 'dom') && (!defined('BBN_DEFAULT_MODE') || (BBN_DEFAULT_MODE !== 'dom'))) {
        $path .= '/index';
      }
    }
    elseif (Str::pos($path, './') === 0) {
      $path = $this->getCurrentDir() . Str::sub($path, 1);
    }

    if (!isset($data)) {
      $data = $this->data;
    }

    $m = $this->_mvc->getModel($path, $data, $this);
    if (\is_object($m)) {
      $m = X::toArray($m);
    }

    if (!\is_array($m)) {
      if ($die) {
        throw new Exception(X::_("$path is an invalid model"));
      }

      return [];
    }

    return $m;
  }


  public function getModelGroup(string $path, array|null $data = null)
  {
    if (Str::pos($path, './') === 0) {
      $path = $this->getCurrentDir() . Str::sub($path, 1);
    }

    if (!isset($data)) {
      $data = $this->data;
    }

    $m = $this->_mvc->getModelGroup($path, $data, $this);
    if (\is_object($m)) {
      $m = X::toArray($m);
    }
  }

  public function getCustomModelGroup(string $path, string $plugin, array|null $data = null): array
  {
    if (Str::pos($path, './') === 0) {
      $path = $this->getCurrentDir() . Str::sub($path, 1);
    }

    if (!isset($data)) {
      $data = $this->data;
    }

    $res = $this->_mvc->getCustomModelGroup($path, $plugin, $data, $this);
    if (\is_object($res)) {
      $res = X::toArray($res);
    }

    return $res;
  }

  
  public function getSubpluginModelGroup(string $path, string $plugin_from, string $plugin_for, array|null $data = null): array
  {
    if (!isset($data)) {
      $data = $this->data;
    }

    $res = $this->_mvc->getSubpluginModelGroup($path, $plugin_from, $plugin_for, $data, $this);
    if (\is_object($res)) {
      $res = X::toArray($res);
    }

    return $res;
  }

  
  /**
   * This will get the cached model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|null A data model
   */
  public function getCachedModel()
  {
    $args = \func_get_args();
    $die  = false;
    $ttl  = 0;
    foreach ($args as $a) {
      if (\is_string($a) && Str::len($a)) {
        $path = $a;
      }
      elseif (\is_array($a)) {
        $data = $a;
      }
      elseif (\is_int($a)) {
        $ttl = $a;
      }
      elseif (\is_bool($a)) {
        $die = $a;
      }
    }

    if (!isset($path)) {
      $path = $this->_path;
    }
    elseif (Str::pos($path, './') === 0) {
      $path = $this->getCurrentDir() . Str::sub($path, 1);
    }

    if (!isset($data)) {
      $data = $this->data;
    }

    $m = $this->_mvc->getCachedModel($path, $data, $this, $ttl);
    if (\is_object($m)) {
      $m = X::toArray($m);
    }

    if (!\is_array($m)) {
      if ($die) {
        throw new Exception(X::_("$path is an invalid model"));
      }

      return [];
    }

    return $m;
  }


  /**
   * This will delete the cached model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return void
   */
  public function deleteCachedModel()
  {
    $args = \func_get_args();

    foreach ($args as $a) {
      if (\is_string($a) && Str::len($a)) {
        $path = $a;
      }
      elseif (\is_array($a)) {
        $data = $a;
      }
    }

    if (!isset($path)) {
      $path = $this->_path;
    }
    elseif (Str::pos($path, './') === 0) {
      $path = $this->getCurrentDir() . Str::sub($path, 1);
    }

    if (!isset($data)) {
      $data = $this->data;
    }

    $this->_mvc->deleteCachedModel($path, $data, $this);
  }


  /**
   * This will set the cached model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return $this
   */
  public function setCachedModel()
  {
    $args = \func_get_args();

    foreach ($args as $a) {
      if (\is_string($a) && Str::len($a)) {
        $path = $a;
      }
      elseif (\is_array($a)) {
        $data = $a;
      }
      elseif (\is_int($a)) {
        $ttl = $a;
      }
    }

    if (!isset($path)) {
      $path = $this->_path;
    }
    elseif (Str::pos($path, './') === 0) {
      $path = $this->getCurrentDir() . Str::sub($path, 1);
    }

    if (!isset($data)) {
      $data = $this->data;
    }

    if (!isset($ttl)) {
      $ttl = 10;
    }

    $this->_mvc->setCachedModel($path, $data, $this, $ttl);
    return $this;
  }


  /**
   * @return object|null
   * @throws Exception
   */
  public function getObjectModel(): ?object
  {
    $args      = \func_get_args();
    $has_cache = false;
    foreach ($args as $a) {
      if (\is_int($a)) {
        $has_cache = true;
        break;
      }
    }

    if ($has_cache) {
      $m = $this->getCachedModel(...$args);
    }
    else {
      $m = $this->getModel(...$args);
    }

    if (empty($m)) {
      return (new stdClass());
    }

    if (X::isAssoc($m)) {
      $m = X::toObject($m);
    }

    return \is_object($m) ? $m : null;
  }


  /**
   * Adds a property to the MVC object inc if it has not been declared.
   *
   * @return self
   */
  public function addInc($name, $obj)
  {
    $this->_mvc->addInc($name, $obj);
    return $this;
  }


  /**
   * @param int $num
   * @return bool
   */
  public function hasArguments(int $num = 1)
  {
    $i = 0;
    while ($i < $num) {
      if (!array_key_exists($i, $this->arguments)) {
        return false;
      }

      $i++;
    }

    return true;
  }


  /**
   * Returns the output object.
   *
   * @return object|false
   */
  public function get()
  {
    return $this->obj;
  }


  /**
   * Transform the output object using a callback
   *
   * @param callable $fn
   */
  public function transform(callable $fn): void
  {
    $this->obj = $fn($this->obj);
  }


  /**
   * Checks if data exists or if a specific index exists in the data
   *
   * @return bool
   */
  public function hasData($idx = null, $check_empty = false)
  {
    if (!\is_array($this->data)) {
      return false;
    }

    if (\is_null($idx)) {
      return !empty($this->data);
    }

    return X::hasProps($this->data, (array)$idx, $check_empty);
  }


  /**
   * Checks if there is any HTML content in the object
   *
   * @return bool
   */
  public function hasContent()
  {
    if (!\is_object($this->obj)) {
      return false;
    }

    return !empty($this->obj->content);
  }


  /**
   * Returns the rendered result from the current mvc if successfully processed
   * process() (or check()) must have been called before.
   *
   * @return string|false
   */
  public function getRendered()
  {
    if (isset($this->obj->content)) {
      return $this->obj->content;
    }

    return false;
  }


  public function getMode()
  {
    return $this->mode;
  }


  /**
   * @param $mode
   * @return self
   */
  public function setMode($mode)
  {
    if ($this->_mvc->setMode($mode)) {
      $this->mode = $mode;
    }

    return $this;
  }


  /**
   * Returns the rendered script result from the current mvc if successfully processed
   * process() (or check()) must have been called before.
   *
   * @return string
   */
  public function getScript()
  {
    if (isset($this->obj->script)) {
      return $this->obj->script;
    }

    return '';
  }


  /**
   * Sets the data. Chainable. Should be useless as $this->data is public. Chainable.
   *
   * @param array $data
   * @return $this
   */
  public function setData(array $data)
  {
    $this->data = $data;
    return $this;
  }


  /**
   * Merges the existing data if there is with this one. Chainable.
   *
   * @return $this
   */
  public function addData(array $data)
  {
    $ar = \func_get_args();
    foreach ($ar as $d) {
      if (\is_array($d)) {
        $this->data = empty($this->data) ? $d : array_merge($this->data, $d);
      }
    }

    return $this;
  }


  /**
   * Returns a new Controller instance with the given arguments.
   *
   * @return Controller|false
   */
  public function add($path, $data = [], $private = false)
  {
    if (Str::sub($path, 0, 2) === './') {
      $path = $this->getCurrentDir() . Str::sub($path, 1);
    }

    if ($route = $this->_mvc->getRoute($path, $private ? 'private' : 'public')) {
      $o = new Controller($this->_mvc, $route, $data);
      $o->process();
      return $o;
    }

    return false;
  }


  /**
   * Creates a new Controller instance and merges it's object with the existing one.
   *
   * @param string $path
   * @param array $data
   * @param bool $private
   * @return self
   */
  public function addToObj(string $path, $data = [], $private = false): self
  {
    if (Str::sub($path, 0, 2) === './') {
      $path = $this->getCurrentDir() . Str::sub($path, 1);
    }

    if ($route = $this->_mvc->getRoute($path, $private ? 'private' : 'public')) {
      $o = new Controller($this->_mvc, $route, $data);
      $o->process();
      $this->obj = X::mergeObjects($this->obj ?: new stdClass(), $o->obj ?: new stdClass());
    }
    elseif ($private) {
      throw new \Error(X::_("Impossible to route the following private request") . ': ' . $path);
    }
    else {
      throw new \Error(X::_("Impossible to route the following public request") . ': ' . $path);
    }

    return $this;
  }


  public function getResult()
  {
    return $this->obj;
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
    return $this->_mvc->viewExists($path, $mode);
  }


  /**
   * Checks whether the given model exists or not.
   *
   * @param string $path
   * @return boolean
   */
  public function modelExists(string $path): bool
  {
    return $this->_mvc->modelExists($path);
  }

  /**
   * Checks whether the given model exists or not.
   *
   * @param string $path
   * @return boolean
   */
  public function controllerExists(string $path, bool $private = false): bool
  {
    return $this->_mvc->controllerExists($path, $private);
  }

    /**
     * @param string         $bbn_inc_file
     * @param Controller $ctrl
     * @return string|bool|void
     */
  public static function includeController(string $bbn_inc_file, Controller $ctrl, $bbn_is_super = false)
  {
    if ($ctrl->isCli()) {
      return (function() use ($bbn_inc_file, $ctrl, $bbn_is_super) {
        return include $bbn_inc_file;
      })();
    }

    ob_start();
    $r = (function() use ($bbn_inc_file, $ctrl, $bbn_is_super) {
      return include $bbn_inc_file;
    })();
    $output = ob_get_contents();
    if (ob_get_level()) {
      ob_end_clean();
    }

    if ($bbn_is_super) {
      return $r ? true : false;
    }

    return $output;
  }
}
