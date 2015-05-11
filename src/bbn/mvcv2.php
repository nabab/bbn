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
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution for routes
 * @todo Look into the check function and divide it
 */

if ( !defined("BBN_DEFAULT_MODE") ){
	define("BBN_DEFAULT_MODE", "json");
}

class mvcv2 implements \bbn\mvc\api{

	use mvc\common;

	private
    /**
     * Is set to null while not routed, then 1 if routing was successful, and false otherwise.
     * @var null|boolean
     */
    $is_routed,
    /**
     * The current controller
     * @var null|\bbn\mvc\controller
     */
    $controller,
		/**
		 * The list of used controllers with their corresponding request, so we don't have to look for them again.
		 * @var array
		 */
		$known_controllers = [],
		/**
		 * The list of views which have been loaded. We keep their content in an array to not have to include the file again. This is useful for loops.
		 * @var array
		 */
		$loaded_views = [],
		/**
		 * The list of views which have been loaded. We keep their content in an array to not have to include the file again. This is useful for loops.
		 * @var array
		 */
		$params = [],
		/**
		 * The path sent to the main controller.
		 * @var null|string
		 */
		$path,
		/**
		 * @var \bbn\db\connection Database object
		 */
		$db,
		/**
		 * The mode of the output (doc, html, json, txt, xml...)
		 * @var null|string
		 */
		$mode,
		/**
		 * The request sent to the server to get the actual controller.
		 * @var null|string
		 */
		$url,
		/**
		 * @var array $_POST
		 */
		$post = [],
		/**
		 * @var array $_GET
		 */
		$get = [],
		/**
		 * @var array $_FILES
		 */
		$files = [],
		/**
		 * List of possible outputs with their according file extension possibilities
		 * @var array
		 */
    $outputs = [
      'dom'=>'html',
      'html'=>'html',
      'image'=>'jpg,jpeg,gif,png,svg',
      'json'=>'json','text'=>'txt',
      'xml'=>'xml',
      'js'=>'js',
      'css'=>'css',
      'less'=>'less'
    ],
		/**
		 * Determines if it is sent through the command line
		 * @var boolean
		 */
		$cli;

	public
		/**
		 * An external object that can be filled after the object creation and can be used as a global with the function add_inc
		 * @var stdClass
		 */
		$inc,
		/**
		 * The output object
		 * @var null|object
		 */
		$obj;

	// These strings are forbidden to use in URL
	private static $reserved = ['index', '_private', '_common', '_htaccess'];

	/**
	 * This should be called only once from within the app
	 *
	 * @param object | string $db The database object if there is
	 * @param array $routes An array of routes usually defined in /_appui/current/config/routes.php</em>
	 */
	public function __construct($db = null, $routes = []){
		// Correspond to the path after the URL to the application's public root (set to '/' for a domain's root)
		if ( defined('BBN_CUR_PATH') ){
			if ( is_object($db) && ( $class = get_class($db) ) && ( $class === 'PDO' || strpos($class, 'bbn\\db\\') !== false ) ){
				$this->db = $db;
			}
			else{
				$this->db = false;
			}
			$this->inc = new \stdClass();
			$this->routes = $routes;
			$this->cli = (php_sapi_name() === 'cli');
			// When using CLI a first parameter can be used as route,
			// a second JSON encoded can be used as $this->post
			if ( $this->cli ){
				$this->mode = 'cli';
				global $argv;
				if ( isset($argv[1]) ){
					$this->set_params($argv[1]);
					if ( isset($argv[2]) && json_decode($argv[2]) ){
						$this->post = array_map(function($a){
							return \bbn\str\text::correct_types($a);
						}, json_decode($argv[2], 1));
					}
				}
			}
			// Non CLI request
			else{
				// Data are "normalized" i.e. types are changed through str\text::correct_types
				// If data is post as in the appui SPA framework, mode is assumed to be BBN_DEFAULT_MODE, json by default
				if ( count($_POST) > 0 ){
					$this->post = array_map(function($a){
						return \bbn\str\text::correct_types($a);
					}, $_POST);
					$this->mode = BBN_DEFAULT_MODE;
				}
				// If no post, assuming to be in an HTML document
				else{
					$this->mode = 'doc';
				}
				if ( count($_GET) > 0 ){
					$this->get = array_map(function($a){
						return \bbn\str\text::correct_types($a);
					}, $_GET);
				}
				// Rebuilding the $_FILES array into $this->files in a more logical structure
				if ( count($_FILES) > 0 ){
					$this->mode = 'file';
					foreach ( $_FILES as $n => $f ){
						if ( is_array($f['name']) ){
							$this->files[$n] = [];
							foreach ( $f['name'] as $i => $v ){
								array_push($this->files[$n], [
									'name' => $v,
									'tmp_name' => $f['tmp_name'][$i],
									'type' => $f['type'][$i],
									'error' => $f['error'][$i],
									'size' => $f['size'][$i],
								]);
							}
						}
						else{
							$this->files[$n] = $f;
						}
					}
				}
				if ( isset($_SERVER['REQUEST_URI']) &&
					( BBN_CUR_PATH === '' || strpos($_SERVER['REQUEST_URI'],BBN_CUR_PATH) !== false ) ){
					$url = explode("?", urldecode($_SERVER['REQUEST_URI']))[0];
					$this->set_params(substr($url, strlen(BBN_CUR_PATH)));
				}
			}
			$this->url = implode('/',$this->params);
			$path = $this->url;
			$this->route($path);
		}
	}

	public function get_url(){
		return $this->url;
	}

	public function get_params(){
		return $this->params;
	}

	public function get_post(){
		return $this->post;
	}

	public function get_get(){
		return $this->get;
	}

	public function get_files(){
		return $this->files;
	}

	public function get_mode(){
		return $this->mode;
	}

	/**
	 * Change the output mode (content-type)
	 *
	 * @param $mode
	 * @return string $this->mode
	 */
	public function set_mode($mode){
		if ( isset($this->outputs[$mode]) && ($this->mode !== 'cli') ) {
			$this->mode = $mode;
		}
		return $this->mode;
	}

  public function get_db(){
    if ( $this->check() ){
      return $this->db;
    }
  }

  /**
	 * This will fetch the route to the controller for a given path. Chainable
	 *
	 * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
	 * @return void
	 */
	private function route($path='')
	{
		if ( !$this->is_routed && self::check_path($path) ){
			$this->is_routed = 1;
			$fpath = $path;

			// We go through each path, starting by the longest until it's empty
			while ( strlen($fpath) > 0 ){
				if ( isset($this->known_controllers[$fpath]) ){

				}
				else if ( isset($this->routes[$fpath]) ){
					$s1 = strlen($path);
					$s2 = strlen($fpath);
					$add = $s1 !== $s2 ? substr($path, $s2) : '';
					$this->path = (is_array($this->routes[$fpath]) ? $this->routes[$fpath][0] :
							$this->routes[$fpath]).$add;
				}
				else{
					$fpath = strpos($fpath,'/') === false ? '' : substr($this->path,0,strrpos($fpath,'/'));
				}
			}
			if ( !isset($this->path) ) {
				$this->path = $path;
			}

			$this->controller = new \bbn\mvc\controller($this, $this->path);
			if ( !$this->controller->exists() ){
				if ( isset($this->routes['default']) ) {
					$this->controller = new \bbn\mvc\controller($this, $this->routes['default'] . '/' . $this->path);
				}
				else {
					$this->controller = new \bbn\mvc\controller($this, '404');
					if ( !$this->controller->exists() ){
						header('HTTP/1.0 404 Not Found');
						exit();
					}
				}
			}
		}
		return $this;
	}

	private function set_params($path)
	{
		$this->params = [];
		$tmp = explode('/', $path);
		$num_params = count($tmp);
		foreach ( $tmp as $t ){
			if ( !empty($t) ){
				if ( in_array($t, self::$reserved) ){
					die("The controller you are asking for contains one of the following reserved strings: ".
						implode(", ", self::$reserved));
				}
				array_push($this->params, $t);
			}
		}
	}

	/**
	 * Returns true if called from CLI/Cron, false otherwise
	 *
	 * @return boolean
	 */
	public function is_cli()
	{
		return $this->cli;
	}

	/**
	 * This will reroute a controller to another one seemlessly. Chainable
	 *
	 * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
	 * @return void
	 */
	public function reroute($path='', $check = 1)
	{
		$this->is_routed = false;
		$this->controller = false;
		$this->is_controlled = null;
		$this->route($path);
		if ( $check ){
			$this->check();
		}
		return $this;
	}

	/**
	 * This will get a view.
	 *
	 * @param string $path
	 * @param string $mode
	 * @return string|false
	 */
	public function get_view($path='', $data=null, $mode='html')
	{
		$path = $path.'/'.basename($path).'.'.$mode;
		if ( isset($this->loaded_views[$path]) ){
			$this->loaded_views[$path];
		}
		else {
			$view = new \bbn\mvc\view($path);
			$this->loaded_views[$path] = $view;
		}
		if ( $view->check() ) {
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
	public function get_model($path, array $data=null)
	{
		$model = new mvc\model($path, $this->db, $this->inc);
		return $model->get($data);
	}

	/**
	 * Adds a property to the MVC object inc if it has not been declared.
	 *
	 * @return bool
	 */
	public function add_inc($name, $obj)
	{
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
	public function process()
	{
    if ( $this->check() && $this->controller ) {
      return $this->controller->process();
    }
	}

	/**
	 * Outputs the result.
	 *
	 * @return void
	 */
	public function output()
	{
		$this->obj = $this->controller->get_result();
		if ( !$this->obj ){
			$this->obj = new \stdClass();
		}
		if ( $this->check() && $this->obj ){

			if ( $this->cli ){
				die(isset($this->obj->output) ? $this->obj->output : "no output");
			}
			if ( isset($this->obj->prescript) ){
				if ( empty($this->obj->prescript) ){
					unset($this->obj->prescript);
				}
				else{
					$this->obj->prescript = \JShrink\Minifier::minify($this->obj->prescript);
				}
			}
			if ( isset($this->obj->script) ){
				if ( empty($this->obj->script) ){
					unset($this->obj->script);
				}
				else{
					$this->obj->script = \JShrink\Minifier::minify($this->obj->script);
				}
			}
			if ( isset($this->obj->postscript) ){
				if ( empty($this->obj->postscript) ){
					unset($this->obj->postscript);
				}
				else{
					$this->obj->postscript = \JShrink\Minifier::minify($this->obj->postscript);
				}
			}
			if ( count((array)$this->obj) === 0 ){
				header('HTTP/1.0 404 Not Found');
				exit();
			}
			switch ( $this->mode ){
				case 'json':
				case 'js':
				case 'css':
				case 'doc':
				case 'html':
					if ( !ob_start("ob_gzhandler" ) ){
						ob_start();
					}
					else{
						header('Content-Encoding: gzip');
					}
					break;
				default:
					ob_start();
			}
			if ( empty($this->obj->output) && !empty($this->obj->file) ){
				if ( is_file($this->obj->file) ){
					$this->obj->file = new \bbn\file\file($this->obj->file);
					$this->mode = '';
				}
				else if ( is_object($this->obj->file) ){
					$this->mode = '';
				}
			}
			if ( (empty($this->obj->output) && empty($this->obj->file) && ($this->mode !== 'json')) ||
				(($this->mode === 'json') && empty($this->obj)) ){
				$this->mode = '';
			}
			switch ( $this->mode ){

				case 'json':
					if ( isset($this->obj->output) ){
						$this->obj->html = $this->obj->output;
						unset($this->obj->output);
					}
					header('Content-type: application/json; charset=utf-8');
					echo json_encode($this->obj);
					break;

				case 'js':
					header('Content-type: application/javascript; charset=utf-8');
					echo $this->obj->output;
					break;

				case 'css':
					header('Content-type: text/css; charset=utf-8');
					echo $this->obj->output;
					break;

				case 'less':
					header('Content-type: text/x-less; charset=utf-8');
					echo $this->obj->output;
					break;

				case 'text':
					header('Content-type: text/plain; charset=utf-8');
					echo $this->obj->output;
					break;

				case 'xml':
					header('Content-type: text/xml; charset=utf-8');
					echo $this->obj->output;
					break;

				case 'image':
					if ( isset($this->obj->img) ){
						$this->obj->img->display();
					}
					else{
						$this->log("Impossible to display the following image: ".$this->obj->img->name);
						header('HTTP/1.0 404 Not Found');

					}
					break;

				case 'file':
					if ( isset($this->obj->file) && is_object($this->obj->file) && method_exists($this->obj->file, 'download') ){
						$this->obj->file->download();
					}
					else{
						$this->log("Impossible to display the following controller", $this);
						header('HTTP/1.0 404 Not Found');
						exit();
					}
					break;

				default:
					header('Content-type: text/html; charset=utf-8');
					//die(var_dump("mode:".$this->mode));
					echo $this->obj->output;

			}
		}
	}
}
