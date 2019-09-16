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

use bbn\mvc\router;

if ( !\defined("BBN_DEFAULT_MODE") ){
	define("BBN_DEFAULT_MODE", 'public');
}

// Correspond to the path after the URL to the application's public root (set to '/' for a domain's root)
if ( !\defined("BBN_CUR_PATH") ){
	define('BBN_CUR_PATH', '/');
}

if ( !\defined("BBN_APP_NAME") ){
	die("BBN_APP_NAME must be defined");
}

if ( !\defined("BBN_APP_PATH") ){
	die("BBN_APP_PATH must be defined");
}

if ( !\defined("BBN_DATA_PATH") ){
	die("BBN_DATA_PATH must be defined");
}

class mvc implements mvc\api{

	use
    models\tts\singleton,
    mvc\common;

  private static
    /**
     * The list of views which have been loaded. We keep their content in an array to not have to include the file again. This is useful for loops.
     * @var array
     */
    $_loaded_views = [
      'html' => [],
      'css' => [],
      'js' => []
    ],
    $_is_debug = false,
    $_app_name,
    $_app_path,
    $_cur_path,
    $_data_path;

	private
    /**
     * The current controller
     * @var null|mvc\controller
     */
    $controller,
    /**
     * @var db Database object
     */
    $db,
    /**
     * @var mvc\environment Environment object
     */
    $env,
    /**
     * @var mvc\router Database object
     */
    $router,
    /**
     * @var array The file(s)'s configuration to transmit to the m/v/c
     */
    $info,
    /**
     * @var string The root of the application in the URL (base href)
     */
    $root,
    /**
     * @var array The plugins registered through the routes
     */
    $plugins,
    /**
     * @var array The plugins registered through the routes
     */
    $loaded = [
      'views' => [
        'html' => [],
        'css' => [],
        'js' => []
      ],
      'models' => [],
      'ctrls' => []
    ];

	public
    /**
     * An external object that can be filled after the object creation and can be used as a global with the function add_inc
     * @var stdClass
     */
    $inc,
    /**
     * An external object that can be filled after the object creation and can be used as a global with the function add_inc
     * @var stdClass
     */
    $data = [],
    // Same
    $o,
		/**
		 * The output object
		 * @var null|object
		 */
		$obj;

  private static
    $db_in_controller = false;

	// These strings are forbidden to use in URL
	public static
    $reserved = ['_private', '_common', '_htaccess'];

  public static function init_path(){
    if ( !self::$_app_name ){
      self::$_app_name = BBN_APP_NAME;
      self::$_app_path = BBN_APP_PATH;
      self::$_cur_path = BBN_CUR_PATH;
      self::$_data_path = BBN_DATA_PATH;
    }
  }

  public static function get_app_name(){
    return self::$_app_name;
  }

  public static function get_app_path($raw = false){
    return self::$_app_path.($raw ? '' : 'src/');
  }

  public static function get_cur_path(){
    return self::$_cur_path;
  }

  public static function get_data_path(string $plugin = null): string
  {
    return BBN_DATA_PATH.($plugin ? 'plugins/'.$plugin.'/' : '');
  }

  public static function get_tmp_path(string $plugin = null): string
  {
    return self::$_app_name ? self::get_data_path().'tmp/'.($plugin ? $plugin.'/' : '') : '';
  }

  public static function get_log_path(string $plugin = null): string
  {
    return self::$_app_name ? self::get_data_path().'logs/'.($plugin ? $plugin.'/' : '') : '';
  }

  public static function get_cache_path(string $plugin = null): string
  {
    return BBN_DATA_PATH.'cache/'.($plugin ? $plugin.'/' : '');
  }

  public static function get_content_path(string $plugin = null): string
  {
    return self::$_app_name ? self::get_data_path().'content/'.($plugin ? 'plugins/'.$plugin.'/' : '') : '';
  }

  public static function get_user_tmp_path(string $id_user = null, string $plugin = null):? string
  {
    if (!self::$_app_name){
      return null;
    }
    if ( !$id_user ){
      $usr = \bbn\user::get_instance();
      if ( $usr ){
        $id_user = $usr->get_id();
      }
    }
    if ( $id_user ){
      return self::get_data_path().'users/'.$id_user.'/tmp/'.($plugin ? $plugin.'/' : '');;
    }
    return null;
  }

  public static function get_user_data_path(string $id_user = null, string $plugin = null):? string
  {
    if (!self::$_app_name){
      return null;
    }
    if ( !$id_user ){
      $usr = \bbn\user::get_instance();
      if ( $usr ){
        $id_user = $usr->get_id();
      }
    }
    if ( $id_user ){
      return self::get_data_path().'users/'.$id_user.'/data/'.($plugin ? $plugin.'/' : '');;
    }
    return null;
  }

  public static function include_model($bbn_inc_file, $model){
    if ( is_file($bbn_inc_file) ){
      ob_start();
      $d = include($bbn_inc_file);
      ob_end_clean();
      if ( \is_object($d) ){
        $d = x::to_array($d);
      }
      if ( !\is_array($d) ){
        return false;
      }
      return $d;
    }
    return false;
  }

  public function get_cookie(){
    return empty($_COOKIE[BBN_APP_NAME]) ? false : json_decode($_COOKIE[BBN_APP_NAME], true)['value'];
  }

  public function set_root($root){
    /** @todo a proper verification of the path */
    if ( strpos($root, -1) !== '/' ){
      $root .= '/';
    }
    if ( 1 ){
      $this->root = $root;
    }
  }

  public function get_root(){
    return $this->root;
  }

  public function fetch_dir($dir, $mode){
    return $this->router->fetch_dir($dir, $mode);
  }

  public function apply_locale($plugin){
    return $this->router->apply_locale($plugin);
  }

  public static function include_php_view($bbn_inc_file, $bbn_inc_content, array $bbn_inc_data = [])
  {
    $randoms = [];
    $_random = function($i) use (&$randoms){
      if ( !isset($randoms[$i]) ){
        $randoms[$i] = md5(\bbn\str::genpwd());
      }
      return $randoms[$i];
    };
    $fn = function() use($bbn_inc_file, $bbn_inc_content, $bbn_inc_data, $_random){
      if ( $bbn_inc_content ){
        ob_start();
        if ( \count($bbn_inc_data) ){
          foreach ( $bbn_inc_data as $bbn_inc_key => $bbn_inc_val ){
            $$bbn_inc_key = $bbn_inc_val;
          }
          unset($bbn_inc_key, $bbn_inc_val);
        }
        unset($bbn_inc_data);
        try{
          eval('?>'.$bbn_inc_content);
        }
        catch ( \Exception $e){
          x::log_error($e->getCode(), $e->getMessage(), $bbn_inc_file, 1);
        }
        $c = ob_get_contents();
        ob_end_clean();
        return $c;
      }
      return '';
    };
    return $fn();
  }

  /**
   * @param string $bbn_inc_file
   * @param mvc\controller $ctrl
   * @return string
   */
  public static function include_controller(string $bbn_inc_file, mvc\controller $ctrl, $bbn_is_super = false){
    if ( $ctrl->is_cli() === 'direct' ){
      return require($bbn_inc_file);
    }
    ob_start();
    $r = require($bbn_inc_file);
    if ( $output = ob_get_contents() ){
      ob_end_clean();
    }
    if ( $bbn_is_super ){
      return $r ? true : false;
    }
    return $output;
  }

  /**
   * This function gets the content of a view file and adds it to the loaded_views array.
   *
   * @param string $p The full path to the view file
   * @return string The content of the view
   */
  private static function add_view($path, $mode, mvc\view $view)
  {
    if ( !isset(self::$_loaded_views[$mode][$path]) ){
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
  public static function set_db_in_controller($r=false)
  {
    self::$db_in_controller = $r ? true : false;
  }

  /**
   * @return bool
   */
  public static function get_debug(){
    return self::$_is_debug;
  }

  public static function debug($state = 1){
    self::$_is_debug = $state;
  }

  private function route($url = false){
    if ( \is_null($this->info) ){
      $this->info = $this->get_route($this->get_url(), $this->get_mode());
    }
    return $this;
  }

  private function register_plugin(array $plugin){
    if ( isset($plugin['path'], $plugin['url'], $plugin['name']) ){
      $this->plugins[$plugin['name']] = [
        'url' => $plugin['url'],
        'path' => $plugin['path']
      ];
    }
  }

  private function init_locale(){
    if ( defined('BBN_LOCALE') && is_dir(self::get_app_path().'locale') ){
      putenv('LANG='.BBN_LOCALE);
      //setlocale(LC_ALL, '');
      setlocale(LC_MESSAGES,BBN_LOCALE);
      //setlocale(LC_CTYPE, BBN_LOCALE);
      //$domains = glob($root.'/'.$locale.'/LC_MESSAGES/messages-*.mo');
      //$current = basename($domains[0],'.mo');
      //$timestamp = preg_replace('{messages-}i','',$current);
      $name = defined('BBN_APP_NAME') ? BBN_APP_NAME : 'bbn-app';
      bindtextdomain($name, self::get_app_path().'locale');
      bind_textdomain_codeset($name, 'UTF-8');
      textdomain($name);
    }
    return $this;
  }

  /**
	 * This should be called only once from within the app
	 *
	 * @param object | string $db The database object if there is
	 * @param array $routes An array of routes usually defined in /_appui/current/config/routes.php</em>
	 */
	public function __construct($db = null, $routes = []){
    self::singleton_init($this);
    self::init_path();
    $this->env = new mvc\environment();
		if ( \is_object($db) && ( $class = \get_class($db) ) && ( $class === 'PDO' || strpos($class, '\db') !== false ) ){
			$this->db = $db;
		}
		else{
			$this->db = null;
		}
		$this->inc = new \stdClass();
    $this->o = $this->inc;
    if ( \is_array($routes) && isset($routes['root']) ){
      foreach ( $routes['root'] as $url => &$route ){
        if ( !empty($route['path']) && (substr($route['path'], -1) !== '/') ){
          $route['path'] .= '/';
        }
        if ( isset($route['path']) ){
          $route['url'] = $url;
          $this->register_plugin($route);
        }
      }
    }
    $this->init_locale();
    $this->router = new mvc\router($this, $routes);
    $this->route();
	}

  /**
   * Checks whether a corresponding file has been found or not.
   *
   * @return bool
   */
  public function check(){
    return $this->info ? true : false;
  }

  public function get_plugins(){
    return $this->plugins;
  }

  public function has_plugin($plugin){
    return isset($this->plugins[$plugin]);
  }

  public function is_plugin($plugin){
    /** @todo This function! */
    return isset($this->plugins[$plugin]);
  }

  public function plugin_path($plugin, $raw = false){
    if ($this->has_plugin($plugin)) {
        return $this->plugins[$plugin]['path'].($raw ? '' : 'src/');
    }
  }

  public function plugin_url($plugin){
    return $this->has_plugin($plugin) ? substr($this->plugins[$plugin]['url'], \strlen($this->root)) : false;
  }

  public function plugin_name($path){
    foreach ( $this->plugins as $name => $p ){
      if ( $p['url'] === $path ){
        return $name;
      }
    }
    return false;
  }

  /*public function add_routes(array $routes){
    $this->routes = x::merge_arrays($this->routes, $routes);
    return $this;
  }*/

  public function get_route($path, $mode, $root = null){
    return $this->router->route($path, $mode, $root);
  }

  public function get_file(){
    return $this->info['file'];
  }

  public function get_url(){
    return $this->env->get_url();
  }

  public function get_params(){
		return $this->env->get_params();
	}

	public function get_post(){
		return $this->env->get_post();
	}

	public function get_get(){
		return $this->env->get_get();
	}

	public function get_files(){
		return $this->env->get_files();
	}

  public function get_mode(){
    return $this->env->get_mode();
  }

  public function set_mode($mode){
    return $this->env->set_mode($mode);
  }

  public function is_cli(){
    return $this->env->is_cli();
  }

  /**
	 * This will reroute a controller to another one seemlessly. Chainable
	 *
	 * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
	 * @return void
	 */
	public function reroute($path='', $post = false, $arguments = false){
    $this->env->simulate($path, $post, $arguments);
		$this->is_routed = false;
		$this->is_controlled = null;
    $this->info = null;
    $this->router->reset();
		$this->route();
    $this->info['args'] = $arguments;
    $this->controller->reset($this->info);
		return $this;
	}

  /**
   * @param string $path
   * @param string $mode
   * @return bool
   */
  public function has_view(string $path = '', string $mode = 'html'): bool
  {
    return array_key_exists($mode, self::$_loaded_views[$mode]) && isset(self::$_loaded_views[$mode][$path]);
  }

  /**
   * @param string $path
   * @param string $mode
   * @param mvc\view $view
   * @return void
   */
  public function add_to_views(string $path, string $mode, mvc\view $view): void
  {
    if ( !array_key_exists($mode, self::$_loaded_views[$mode]) ){
      self::$_loaded_views[$mode] = [];
    }
    self::$_loaded_views[$mode][$path] = $view;
  }

  /**
   * This will get a view.
   *
   * @param string $path
   * @param string $mode
   * @param array $data
   * @return string|false
   */
  public function get_view(string $path, string $mode = 'html', array $data=null){
    if ( !router::is_mode($mode) ){
      die("Incorrect mode $path $mode");
    }
    $view = null;
    if ( $this->has_view($path, $mode) ){
      $view = self::$_loaded_views[$mode][$path];
    }
    else if ( $info = $this->router->route($path, $mode) ){
      $view = new mvc\view($info);
      $this->add_to_views($path, $mode, $view);
    }
    if ( \is_object($view) && $view->check() ){
      return \is_array($data) ? $view->get($data) : $view->get();
    }
    return '';
  }

  /**
   * This will get a view from a different root.
   *
   * @param string $full_path
   * @param string $mode
   * @param array $data
   * @return string|false
   */
  public function get_external_view(string $full_path, string $mode = 'html', array $data=null){
    if ( !router::is_mode($mode) ){
      die("Incorrect mode $full_path $mode");
    }
    if ( ($this->get_mode() === 'dom') && (!defined('BBN_DEFAULT_MODE') || (BBN_DEFAULT_MODE !== 'dom')) ){
      $full_path .= ($full_path === '' ? '' : '/').'index';
    }
    $view = null;
    if ( $this->has_view($full_path, $mode) ){
      $view = self::$_loaded_views[$mode][$full_path];
    }
    else if ( $info = $this->router->route(basename($full_path), 'free-'.$mode, \dirname($full_path)) ){
      $view = new mvc\view($info);
      $this->add_to_views($full_path, $mode, $view);
    }
    if ( \is_object($view) && $view->check() ){
      return \is_array($data) ? $view->get($data) : $view->get();
    }
    return '';
  }



  /**
   * This will get a view.
   *
   * @param string $path
   * @param string $mode
   * @param array $data
   * @param string $plugin
   * @return string|false
   */
  public function get_plugin_view(string $path, string $mode, array $data, string $plugin){
    if ( !router::is_mode($mode) ){
      die("Incorrect mode $path $mode");
    }
    $route = false;
    if ( $name = $this->plugin_name($plugin) ){
      $bits = x::split($path, DIRECTORY_SEPARATOR);
      // The plugin model can be in another plugin
      if ( (count($bits) > 1) && ($root = $this->plugin_path($bits[0])) ){
        $route = $this->router->route(substr($path, strlen($bits[0])+1), $mode, $root.'plugins/'.$name.'/'.$mode.'/');
      }
    }
    if ( !$route ){
      $route = $this->router->route($path, $mode, self::get_app_path().'plugins/'.$name.'/'.$mode.'/');
    }
    if ( $route ){
      $view = new mvc\view($route);
      if ( $view->check() ){
        return \is_array($data) ? $view->get($data) : $view->get();
      }
      return '';
    }
  }

  /**
   * This will get the model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function get_model($path, array $data, mvc\controller $ctrl){
    if ( $route = $this->router->route($path, 'model') ){
      $model = new mvc\model($this->db, $route, $ctrl, $this);
      return $model->get($data);
    }
    return [];
  }

  public function get_plugin_model(string $path, array $data, mvc\controller $ctrl, string $plugin, int $ttl = 0){
    $route = false;
    if ( $name = $this->plugin_name($plugin) ){
      $bits = x::split($path, DIRECTORY_SEPARATOR);
      // The plugin model can be in another plugin
      if ( (count($bits) > 1) && ($root = $this->plugin_path($bits[0])) ){
        $route = $this->router->route($path, 'model', $root.'plugins/'.$name.'/model/');
      }
    }
    if ( !$route ){
      $route = $this->router->route($path, 'model', self::get_app_path().'plugins/'.$name.'/model/');
    }
    if ( $route ){
      $model = new mvc\model($this->db, $route, $ctrl, $this);
      if ( $ttl ){
        return $model->get_from_cache($data, '', $ttl);
      }
      return $model->get($data);
    }
  }
  /**
   * This will get the model as it is in cache if any and otherwise will save it in cache then return it
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function get_cached_model($path, array $data, mvc\controller $ctrl, $ttl = 10){
    if ( \is_null($data) ){
      $data = $this->data;
    }
    if ( $route = $this->router->route($path, 'model') ){
      $model = new mvc\model($this->db, $route, $ctrl, $this);
      return $model->get_from_cache($data, '', $ttl);
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
  public function set_cached_model($path, array $data, mvc\controller $ctrl, $ttl = 10){
    if ( \is_null($data) ){
      $data = $this->data;
    }
    if ( $route = $this->router->route($path, 'model') ){
      $model = new mvc\model($this->db, $route, $ctrl, $this);
      return $model->set_cache($data, '', $ttl);
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
  public function delete_cached_model($path, array $data, mvc\controller $ctrl){
    if ( \is_null($data) ){
      $data = $this->data;
    }
    if ( $route = $this->router->route($path, 'model') ){
      $model = new mvc\model($this->db, $route, $ctrl, $this);
      return $model->delete_cache($data, '');
    }
    return [];
  }

  /**
	 * Adds a property to the MVC object inc if it has not been declared.
	 *
	 * @return bool
	 */
	public function add_inc($name, $obj){
		if ( !isset($this->inc->{$name}) ){
			$this->inc->{$name} = $obj;
		}
	}

	/**
	 * Returns the rendered result from the current mvc if successufully processed
	 * process() (or check()) must have been called before.
	 *
	 * @return string|false
	 */
	public function process(){
    if ( $this->check() ){
      $this->obj = new \stdClass();
      if ( !\is_array($this->info)){
        $this->log("No info in MVC", $this->info);
        die("No info in MVC");
      }
      if ( !$this->controller ){
        $this->controller = new mvc\controller($this, $this->info, $this->data, $this->obj);
      }
      $this->controller->process();
    }
	}

	public function has_content(){
    if ( $this->check() && $this->controller ){
      return $this->controller->has_content();
    }
    return false;
  }

  public function transform(callable $fn){
    if ( $this->check() && $this->controller ){
      $this->controller->transform($fn);
    }
  }

  public function output(){
    if ( $this->check() && $this->controller ){
      $obj = $this->controller->get();
      if ($this->is_cli()){
        die(isset($obj->content) ? $obj->content : "no output");
      }
      if ( \is_array($obj) ){
        $obj = x::to_object($obj);
      }
			if ( (\gettype($obj) !== 'object') || (\get_class($obj) !== 'stdClass') ){
				die(x::dump("Unexpected output: ".\gettype($obj)));
			}
			if ( x::count_properties($this->obj) ){
			  $obj = x::merge_objects($obj, $this->obj);
      }
      $output = new mvc\output($obj, $this->get_mode());
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
  public function get_db(): ?db
  {
    if ( self::$db_in_controller && $this->db ){
      return $this->db;
    }
    return null;
  }

  public function set_prepath($path){
    if ( $this->check() ){
      if ( $this->router->get_prepath(false) === $path ){
        return 1;
      }
      if ( $this->env->set_prepath($path) && $this->router->set_prepath($path) ){
        $this->params = $this->get_params();
        return 1;
      }
    }
    die("The set_prepath method cannot be used in this MVC");
  }

  public function get_prepath(){
    if ( $this->check() ){
      return $this->router->get_prepath();
    }
  }

  public function get_routes($type = 'root'){
    if ( $this->check() ){
      $routes = $this->router->get_routes();
      return isset($routes[$type]) ? $routes[$type] : false;
    }
  }


}
