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
	define("BBN_DEFAULT_MODE", 'content');
}

class mvcv2 implements \bbn\mvc\api{

	use mvc\common;

	private
    /**
     * The current controller
     * @var null|\bbn\mvc\controller
     */
    $controller,
		/**
		/**
		 * The list of views which have been loaded. We keep their content in an array to not have to include the file again. This is useful for loops.
		 * @var array
		 */
		$loaded_views = [],
    /**
     * @var \bbn\db\connection Database object
     */
    $db,
    /**
     * @var \bbn\mvc\environment Environment object
     */
    $env,
    /**
     * @var \bbn\mvc\router Database object
     */
    $router;

	public
		/**
		 * An external object that can be filled after the object creation and can be used as a global with the function add_inc
		 * @var stdClass
		 */
		$inc,
    // Same
    $o,
		/**
		 * The output object
		 * @var null|object
		 */
		$obj;

	// These strings are forbidden to use in URL
	public static
    $reserved = ['index', '_private', '_common', '_htaccess'],
    /**
     * List of possible outputs with their according file extension possibilities
     * @var array
     */
    $outputs = [
      'container' => 'html',
      'content' => 'json'/*,
      'image' => 'jpg,jpeg,gif,png,svg',
      'json' => 'json',
      'text'=>'txt',
      'xml' => 'xml',
      'js' => 'js',
      'css' => 'css',
      'less' => 'less'*/
    ];

	/**
	 * This should be called only once from within the app
	 *
	 * @param object | string $db The database object if there is
	 * @param array $routes An array of routes usually defined in /_appui/current/config/routes.php</em>
	 */
	public function __construct($db = null, $routes = []){
		// Correspond to the path after the URL to the application's public root (set to '/' for a domain's root)
		if ( defined('BBN_CUR_PATH') ){
      $this->env = new \bbn\mvc\environment();
			if ( is_object($db) && ( $class = get_class($db) ) && ( $class === 'PDO' || strpos($class, 'bbn\\db\\') !== false ) ){
				$this->db = $db;
			}
			else{
				$this->db = false;
			}
			$this->inc = new \stdClass();
      $this->o = $this->inc;
      $this->router = new \bbn\mvc\router($this, $routes);
		}
	}

  /**
   * Checks whether a corresponding file has been found or not.
   *
   * @return bool
   */
  public function check()
  {
    return is_object($this->router);
  }

  public function route(){
    if ( $this->check() ){
      $this->path = $this->router->route();
      return $this->path;
    }
  }

  public function add_routes(array $routes){
    $this->routes = \bbn\tools::merge_arrays($this->routes, $routes);
    return $this;
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

  public function get_db(){
    if ( $this->check() ){
      return $this->db;
    }
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
    var_dump("path", $this->path, $this->check());
    if ( $this->check() && $this->path ) {
      $ctrl = new \bbn\mvc\controller($this);
      return $ctrl->process();
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
