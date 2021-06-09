<?php

namespace bbn\Mvc;

use bbn;
use bbn\X;

class Controller implements Api
{

  use Common;

  /**
   * The MVC class from which the controller is called
   * @var bbn\Mvc
   */
  private $_mvc;

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

  public
    /**
     * The db connection if accepted by the mvc class.
     */
    $db,
    /**
     * @var string The mode of the controller (dom, cli...), which will determine its route
     */
    $mode,
    /**
     * The data model
     * @var array
     */
    $data = [],
    /**
     * All the parts of the path requested
     * @var array
     */
    $params = [],
    /**
     * All the parts of the path requested which are not part of the controller path
     * @var array
     */
    $arguments = [],
    /**
     * The data sent through POST
     * @var array
     */
    $post = [],
    /**
     * The data sent through GET
     * @var array
     */
    $get = [],
    /**
     * A numeric indexed array of the files sent through POST (different from native)
     * @var array
     */
    $files = [],
    /**
     * The output object
     * @var null|object
     */
    $obj,
    /**
     * An external object that can be filled after the object creation and can be used as a global with the function add_inc
     * @var stdClass
     */
    $inc;


  /**
   * This will call the initial build a new instance.
   * It should be called only once from within the script.
   * All subsequent calls to controllers should be done through $this->add($path).
   *
   * @param bbn\Mvc       $mvc
   * @param array         $files
   * @param array|boolean $data
   */
  public function __construct(bbn\Mvc $mvc, array $files, $data = false)
  {
    $this->_mvc = $mvc;
    $this->reset($files, $data);
  }


  public function reset(array $info, $data = false)
  {
    if (isset($info['mode'], $info['path'], $info['file'], $info['request'], $info['root'])) {
      $this->_path     = $info['path'];
      $this->_plugin   = $info['plugin'];
      $this->_request  = $info['request'];
      $this->_file     = $info['file'];
      $this->_root     = $info['root'];
      $this->arguments = $info['args'];
      $this->_checkers = $info['checkers'];
      $this->mode      = $info['mode'];
      $this->data      = \is_array($data) ? $data : [];
      // When using CLI a first parameter can be used as route,
      // a second JSON encoded can be used as $this->post
      /** @var bbn\Db db */
      $this->db     = $this->_mvc->getDb();
      $this->inc    = &$this->_mvc->inc;
      $this->post   = $this->_mvc->getPost();
      $this->get    = $this->_mvc->getGet();
      $this->files  = $this->_mvc->getFiles();
      $this->params = $this->_mvc->getParams();
      $this->url    = $this->getUrl();
      $this->obj    = new \stdClass();
    }
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
    return $this->_request;
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
    if (($pp = $this->getPrepath()) && (strpos($this->_path, $pp) === 0)) {
      return substr($this->_path, \strlen($pp));
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
    if (($pp = $this->getPrepath()) && (strpos($this->_request, $pp) === 0)) {
      return substr($this->_request, \strlen($pp));
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
      $p = dirname($this->_path);
      if ($p === '.') {
        return '';
      }

      if (($prepath = $this->getPrepath())
          && (strpos($p, $prepath) === 0)
      ) {
        return substr($p, \strlen($prepath));
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
  public function render(string $view, array $model = null): string
  {
    if (empty($model) && !empty($this->data)) {
      $model = $this->data;
    }

    return \is_array($model) ? bbn\Tpl::render($view, $model) : $view;
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
  public function reroute($path='', $post = false, $arguments = false)
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
      $d = dirname($this->_file).'/';
      if (substr($file_name, -4) !== '.php') {
        $file_name .= '.php';
      }

      if ((strpos($file_name, '..') === false) && file_exists($d.$file_name)) {
        $bbn_path = $d.$file_name;
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
      function ($class_name) use ($plugin_path) {
        if ((strpos($class_name,'/') === false)
            && (strpos($class_name,'.') === false)
        ) {
          $cls  = explode('\\', $class_name);
          $path = implode('/', $cls);
          if (file_exists($plugin_path.'lib/'.$path.'.php')) {
            include_once $plugin_path.'lib/'.$path.'.php';
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

      ob_start();
      if (defined('BBN_ROOT_CHECKER')) {
        if (!defined('BBN_ROOT_CHECKER_OK')) {
          define('BBN_ROOT_CHECKER_OK', true);
          array_unshift($this->_checkers, BBN_ROOT_CHECKER);
        }
      }

      foreach ($this->_checkers as $appui_checker_file){
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

      ob_end_clean();
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
      if ($this->_plugin) {
        $router = Router::getInstance();
        if ($textDomain = $router->getLocaleDomain($this->_plugin)) {
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
  public function getJs($path='', array $data=null, $encapsulated = true)
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
      return '<script>'.
        ( $encapsulated ? '(function(){'.PHP_EOL : '' ).
        ( empty($data) ? '' : 'let data = '.X::jsObject($data).';' ).
        $r.
        //( $encapsulated ? '})(jQuery);' : '' ).
        ($encapsulated ? PHP_EOL.'})();' : '').
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
  public function getJsGroup($files='', array $data=null, $encapsulated = true)
  {
    if ($js = $this->getViewGroup($files, $data, 'js')) {
      return '<script>'.
      ( $encapsulated ? '(function($){'.PHP_EOL : '' ).
      ( empty($data) ? '' : 'let data = '.X::jsObject($data).';' ).
      $js.
      //( $encapsulated ? '})(jQuery);' : '' ).
      ( $encapsulated ? PHP_EOL.'})();' : '' ).
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
  public function getViewGroup($files='', array $data=null, $mode = 'html')
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
      foreach ($files as $f){
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
  public function getCss($path='')
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
  public function getLess($path='')
  {
    return $this->getView($path, 'css', false);
  }


  /**
   * This will get a CSS view encapsulated in a scoped style tag and add it to the output object.
   *
   * @param string $path
   * @return self
   */
  public function addCss($path='')
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
  public function addLess($path='')
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
    foreach ($args as $i => $a){
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
    foreach ($arr as $k => $a){
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
  public function setColor(string $bg = null, string $txt = null)
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
        $v   = new View($tmp['js']);
        $res = [
          'name' => $name,
          'script' => $v->get($data)
        ];
        if (!empty($tmp['css'])) {
          $v          = new View($tmp['css']);
          $res['css'] = $v->get();
        }

        if (!empty($tmp['html'])) {
          $v              = new View($tmp['html']);
          $res['content'] = $v->get($data);
        }

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
    foreach ($args as $a){
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
      if (($this->getMode() === 'dom')
          && (!defined('BBN_DEFAULT_MODE') || (BBN_DEFAULT_MODE !== 'dom'))
      ) {
        $r['path'] .= '/index';
      }
    }
    elseif (strpos($r['path'], './') === 0) {
      $r['path'] = $this->getCurrentDir().substr($r['path'], 1);
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
   * @throws \Exception
   */
  public function getExternalView(string $full_path, string $mode = 'html', ?array $data=null)
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
  public function customPluginView(string $path, string $mode = 'html', array $data = [], string $plugin = null): ?string
  {
    if (!$plugin) {
      $plugin = $this->getPlugin();
    }

    if ($plugin) {
      return $this->_mvc->customPluginView($path, $mode, $data, $plugin);
    }

    return null;
  }


  public function getComponentView(string $name, string $type = 'html', array $data = [])
  {

  }


  public function getPluginView(string $path, string $type = 'html', array $data = [])
  {
    return $this->_mvc->getPluginView($path, $type, $data, $this->getPlugin());
  }


  public function getPluginViews(string $path, array $data = [], array $data2 = null)
  {
    return [
      'html' => $this->_mvc->getPluginView($path, 'html', $data, $this->getPlugin()),
      'css' => $this->_mvc->getPluginView($path, 'css', [], $this->getPlugin()),
      'js' => $this->_mvc->getPluginView($path, 'js', $data2 ?: $data, $this->getPlugin()),
    ];
  }


  public function getPluginModel($path, $data = [], string $plugin = null, int $ttl = 0)
  {
    return $this->_mvc->getPluginModel($path, $data, $this, $plugin ?: $this->getPlugin(), $ttl);
  }


  public function getSubpluginModel($path, $data = [], string $plugin = null, string $subplugin, int $ttl = 0)
  {
    return $this->_mvc->getSubpluginModel($path, $data, $this, $plugin ?: $this->getPlugin(), $subplugin, $ttl);
  }


  public function hasSubpluginModel(string $path, string $plugin, string $subplugin)
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
  private function retrieveVar($var)
  {
    if (\is_string($var) && (strpos($var, '$') === 0) && isset($this->data[substr($var, 1)])) {
      return $this->data[substr($var, 1)];
    }

    return false;
  }


  public function action()
  {
    $this->obj = $this->addData(['res' => ['success' => false]])->addData($this->post)->getObjectModel();
  }


  public function cachedAction($ttl = 60)
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
      string $title = null,
      $data = null,
      int $ttl = null,
      string $path = ''
  ): self
  {
    if (empty($path)) {
      $basename = basename($this->_file, '.php');
      if (X::indexOf(['index', 'home'], $basename) > -1) {
        $bits = X::split($this->_path, '/');
        if ((count($bits) === 1) && ($bits[0] === '.')) {
          $path = $basename;
        }
        elseif (end($bits) !== $basename) {
          $bits[] = $basename;
          $path = X::join($bits, '/');
        }
      }
    }
    if ($this->getRoute($path ?: $this->_path, 'model')) {
      $model = $ttl === null ? $this->getModel($path, X::mergeArrays($this->post, $this->data)) : $this->getCachedModel($path, X::mergeArrays($this->post, $this->data), $ttl);
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

    $this->obj->css = $this->getLess($path, false);
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
    else{
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
    if ($this->checkPath($file_name)
        && \defined('BBN_DATA_PATH')
        && is_file(BBN_DATA_PATH.$file_name)
    ) {
      return file_get_contents(BBN_DATA_PATH.$file_name);
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


  public function setPrepath($path)
  {
    if ($this->exists() && $this->_mvc->setPrepath($path)) {
      $this->params = $this->_mvc->getParams();
      return $this;
    }

    die("Prepath $path is not valid");
  }


  /**
   * This will get the model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function getModel()
  {
    $args = \func_get_args();
    $die  = false;
    foreach ($args as $a){
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
    elseif (strpos($path, './') === 0) {
      $path = $this->getCurrentDir().substr($path, 1);
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
        die("$path is an invalid model");
      }

      return [];
    }

    return $m;
  }


  /**
   * This will get the model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function getCachedModel()
  {
    $args = \func_get_args();
    $die  = false;
    $ttl  = 0;
    $data = [];
    foreach ($args as $a){
      if (\is_string($a) && \strlen($a)) {
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
    elseif (strpos($path, './') === 0) {
      $path = $this->getCurrentDir().substr($path, 1);
    }

    if (!isset($data)) {
      $data = $this->data;
    }

    $m = $this->_mvc->getCachedModel($path, $data, $this, $ttl);
    if (!\is_array($m) && $die) {
      die("$path is an invalid model");
    }

    return $m;
  }


  /**
   * This will delete the cached model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   */
  public function deleteCachedModel()
  {
    $args = \func_get_args();

    foreach ($args as $a){
      if (\is_string($a) && \strlen($a)) {
        $path = $a;
      }
      elseif (\is_array($a)) {
        $data = $a;
      }
    }

    if (!isset($path)) {
      $path = $this->_path;
    }
    elseif (strpos($path, './') === 0) {
      $path = $this->getCurrentDir().substr($path, 1);
    }

    if (!isset($data)) {
      $data = $this->data;
    }

    return $this->_mvc->deleteCachedModel($path, $data, $this);
  }


  /**
   * This will get the model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return $this
   */
  public function setCachedModel()
  {
    $args = \func_get_args();
    $die  = 1;
    foreach ($args as $a){
      if (\is_string($a) && \strlen($a)) {
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
    elseif (strpos($path, './') === 0) {
      $path = $this->getCurrentDir().substr($path, 1);
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
      return (new \stdClass());
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
   * Checks if there is ny HTML content in the object
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
   * Returns the rendered result from the current mvc if successufully processed
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


  public function setMode($mode)
  {
    if ($this->_mvc->setMode($mode)) {
      $this->mode = $mode;
      //die(var_dump($mode));
    }

    return $this;
  }


  /**
   * Returns the rendered result from the current mvc if successufully processed
   * process() (or check()) must have been called before.
   *
   * @return string|false
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
    foreach ($ar as $d){
      if (\is_array($d)) {
        $this->data = empty($this->data) ? $d : array_merge($this->data, $d);
      }
    }

    return $this;
  }


  /**
   * Merges the existing data if there is with this one. Chainable.
   *
   * @return void
   */
  public function add($path, $data=[], $internal = false)
  {
    if (substr($path, 0, 2) === './') {
      $path = $this->getCurrentDir().substr($path, 1);
    }

    if ($route = $this->_mvc->getRoute($path, $internal ? 'private' : 'public')) {
      $o = new Controller($this->_mvc, $route, $data);
      $o->process();
      return $o;
    }

    return false;
  }


  /**
   * Merges the existing data if there is with this one. Chainable.
   *
   * @return void
   */
  public function addToObj(string $path, $data=[], $internal = false): self
  {
    if (substr($path, 0, 2) === './') {
      $path = $this->getCurrentDir().substr($path, 1);
    }

    if ($route = $this->_mvc->getRoute($path, $internal ? 'private' : 'public')) {
      $o = new Controller($this->_mvc, $route, $data);
      $o->process();
      $this->obj = X::mergeObjects($this->obj, $o->obj);
    }
    else {
      throw new \Error(X::_("Impossible to route the following request").': '.$path);
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
     * @param string         $bbn_inc_file
     * @param Controller $ctrl
     * @return string|bool|void
     */
    public static function includeController(string $bbn_inc_file, Controller $ctrl, $bbn_is_super = false)
    {
      if ($ctrl->isCli()) {
          return include $bbn_inc_file;
      }

      ob_start();
      $r      = include $bbn_inc_file;
      $output = ob_get_contents();
      ob_end_clean();

      if ($bbn_is_super) {
          return $r ? true : false;
      }

      return $output;
    }
}
