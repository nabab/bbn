<?php
namespace bbn;

/**
 * Model View Controller Class
 *
 *
 * This class, called once per request, holds the environment's variables
 * and routes each request to its according controller, then acts as a
 * link between the controller and models and views it uses
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.9
 * @todo Add feature to auto-detect a different corresponding index and redirect to it through Appui
 * @todo Add $this->dom to public controllers (?)
 */

use bbn\mvc\router;

if ( !defined("BBN_DEFAULT_MODE") ){
	define("BBN_DEFAULT_MODE", 'public');
}

// Correspond to the path after the URL to the application's public root (set to '/' for a domain's root)
if ( !defined("BBN_CUR_PATH") ){
	die("BBN_CUR_PATH must be defined");
}

if ( !defined("BBN_APP_PATH") ){
	die("BBN_APP_PATH must be defined");
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
    $loaded_views = [
      'html' => [],
      'css' => [],
      'js' => []
    ],
    $is_debug = false;

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
    $reserved = ['index', '_private', '_common', '_htaccess'];

  public static function include_model($bbn_inc_file, $model){
    if ( is_file($bbn_inc_file) ){
      ob_start();
      $d = include($bbn_inc_file);
      ob_end_clean();
      if ( is_object($d) ){
        $d = x::to_array($d);
      }
      if ( !is_array($d) ){
        return false;
      }
      return $d;
    }
    return false;
  }

  public function fetch_dir($dir, $mode){
    return $this->router->fetch_dir($dir, $mode);
  }

  public static function include_php_view($bbn_inc_content, array $bbn_inc_data = [])
  {
    if ( $bbn_inc_content ){
      ob_start();
      if ( count($bbn_inc_data) ){
        foreach ( $bbn_inc_data as $bbn_inc_key => $bbn_inc_val ){
          $$bbn_inc_key = $bbn_inc_val;
        }
        unset($bbn_inc_key, $bbn_inc_val);
      }
      unset($bbn_inc_data);
      eval('?>'.$bbn_inc_content);
      $c = ob_get_contents();
      ob_end_clean();
      return $c;
    }
    return '';
  }

  /**
   * @param string $bbn_inc_file
   * @param mvc\controller $ctrl
   * @return string
   */
  public static function include_controller(string $bbn_inc_file, mvc\controller $ctrl, $bbn_is_super = false){
    ob_start();
    $r = require($bbn_inc_file);
    $output = ob_get_contents();
    ob_end_clean();
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
    if ( !isset(self::$loaded_views[$mode][$path]) ){
      self::$loaded_views[$mode][$path] = $view;
    }
    return self::$loaded_views[$mode][$path];
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

  public static function get_debug(){
    return self::$is_debug;
  }

  public static function debug($state = 1){
    self::$is_debug = $state;
  }

  private function route($url = false){
    if ( is_null($this->info) ){
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

  /**
	 * This should be called only once from within the app
	 *
	 * @param object | string $db The database object if there is
	 * @param array $routes An array of routes usually defined in /_appui/current/config/routes.php</em>
	 */
	public function __construct($db = null, $routes = []){
    self::singleton_init($this);
    $this->env = new mvc\environment();
		if ( is_object($db) && ( $class = get_class($db) ) && ( $class === 'PDO' || strpos($class, '\db') !== false ) ){
			$this->db = $db;
		}
		else{
			$this->db = false;
		}
		$this->inc = new \stdClass();
    $this->o = $this->inc;
    if ( is_array($routes) && isset($routes['root']) ){
      $roots = $routes['root'];
      $routes['root'] = [];
      foreach ( $roots as $r ){
        $this->register_plugin($r);
        $routes['root'][$r['url']] = $r['path'];
      }
    }
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

  public function has_plugin($plugin){
    return isset($this->plugins[$plugin]);
  }

  public function is_plugin($plugin){
    /** @todo This function! */
    return isset($this->plugins[$plugin]);
  }

  public function plugin_path($plugin){
    return $this->has_plugin($plugin) ? $this->plugins[$plugin]['path'] : false;
  }

  public function plugin_url($plugin){
    return $this->has_plugin($plugin) ? $this->plugins[$plugin]['url'] : false;
  }

  /*public function add_routes(array $routes){
    $this->routes = x::merge_arrays($this->routes, $routes);
    return $this;
  }*/

  public function get_route($path, $mode){
    return $this->router->route($path, $mode);
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
		$this->route();
    $this->info['args'] = $arguments;
    $this->controller->reset($this->info);
    $this->log("MVC reroute", $path, $post, $arguments, $this->info);
		return $this;
	}

  /**
	 * This will get a view.
	 *
	 * @param string $path
	 * @param string $mode
	 * @return string|false
	 */
	public function get_view($path='', $mode='html', $data=null)
	{
    if ( !router::is_mode($mode) ){
      die("Incorrect mode $path $mode");
    }
		if ( isset(self::$loaded_views[$mode][$path]) ){
			$view = self::$loaded_views[$mode][$path];
		}
		else if ( $file = $this->router->route($path, $mode) ){
			$view = new mvc\view($file, $mode, $data);
      self::$loaded_views[$mode][$path] = $view;
		}
		if ( isset($view) && $view->check() ){
			return is_array($data) ? $view->get($data) : $view->get();
		}
		return '';
	}

  /**
   * This will get the model. There is no order for the arguments.
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function get_model($path, array $data, mvc\controller $ctrl)
  {
    if ( $route = $this->router->route($path, 'model') ){
      $model = new mvc\model($this->db, $route, $ctrl);
      return $model->get($data);
    }
    return [];
  }

  /**
   * This will get the model as it is in cache if any and otherwise will save it in cache then return it
   *
   * @params string path to the model
   * @params array data to send to the model
   * @return array|false A data model
   */
  public function get_cached_model($path, array $data, mvc\controller $ctrl){
    if ( is_null($data) ){
      $data = $this->data;
    }
    if ( $route = $this->router->route($path, 'model') ){
      $model = new mvc\model($this->db, $route, $ctrl);
      return $model->get_from_cache($data);
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
  public function set_cached_model($path, array $data, mvc\controller $ctrl){
    if ( is_null($data) ){
      $data = $this->data;
    }
    if ( $route = $this->router->route($path, 'model') ){
      $model = new mvc\model($this->db, $route, $ctrl);
      return $model->set_cache($data);
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
    if ( $this->check() ) {
      $this->obj = new \stdClass();
      if ( !is_array($this->info)){
        $this->log("No info in MVC", $this->info);
        die("No info in MVC");
      }
      if ( !$this->controller ){
        $this->controller = new mvc\controller($this, $this->info, $this->data, $this->obj);
      }
      $this->controller->process();
    }
	}

  public function output(){
    if ( $this->check() && $this->controller ) {
      $obj = $this->controller->get();
      if ($this->is_cli()) {
        die(isset($obj->content) ? $obj->content : "no output");
      }
      if ( is_array($obj) ){
        $obj = x::to_object($obj);
      }
			if ( (gettype($obj) !== 'object') || (get_class($obj) !== 'stdClass') ){
				die(x::dump("Unexpected output: ".gettype($obj)));
			}
      $output = new mvc\output($obj, $this->get_mode());
      $output->run();
    }
  }

  public function get_db(){
    if ( self::$db_in_controller && $this->db ){
      return $this->db;
    }
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
