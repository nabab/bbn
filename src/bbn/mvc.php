<?php
namespace bbn;
/**
 * Model View Controller Class
 *
 *
 * This class will route a request to the according model and/or view through its controller.
 * A model and a view can be automatically associated if located in the same directory branch with the same name than the controller in their respective locations
 * A view can be directly imported in the controller through this very class
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */

class mvc 
{
  
	private
	/**
	 * Is set to null while not routed, then 1 if routing was sucessful, and false otherwise.
	 * @var null|boolean
	 */
		$is_routed,
	/**
	 * Is set to null while not controled, then 1 if controller was found, and false otherwise.
	 * @var null|boolean
	 */
		$is_controlled,
	/**
	 * The name of the controller.
	 * @var null|string
	 */
	 	$dest,
	/**
	 * The directory of the controller.
	 * @var null|string
	 */
		$dir,
	/**
	 * The path to the controller.
	 * @var null|string
	 */
		$path,
	/**
	 * The checkers files (with full path)
   * If any they will be checked before the controller
	 * @var null|string
	 */
		$checkers = [],
	/**
	 * The controller file (with full path)
	 * @var null|string
	 */
		$controller,
	/**
	 * The mode of the output (dom, html, json, txt, xml...)
	 * @var null|string
	 */
		$mode;
	public
	/**
	 * An external object that can be filled after the object creation and can be used as a global with the function add_inc
	 * @var stdClass
	 */
		$inc,
	/**
	 * The data model
	 * @var null|array
	 */
		$data,
	/**
	 * The output object
	 * @var null|object
	 */
		$obj,
	/**
	 * The file extension of the view
	 * @var null|string
	 */
	 	$ext,
	/**
	 * The request sent to the server to get the actual controller.
	 * @var null|string
	 */
		$url,
	/**
	 * The first controller to be called at the top of the script.
	 * @var null|string
	 */
		$original_controller,
	/**
	 * The list of used controllers with their corresponding request, so we don't have to look for them again.
	 * @var array
	 */
		$known_controllers = array(),
	/**
	 * The list of views which have been loaded. We keep their content in an array to not have to include the file again. This is useful for loops.
	 * @var array
	 */
		$loaded_views = array(),
	/**
	 * Mustage templating engine.
	 * @var object
	 */
	 	$mustache,
	/**
	 * Database object
	 * @var object
	 */
		$db,
	/**
	 * $_POST array
	 * @var array
	 */
		$post,
	/**
	 * $_GET array
	 * @var array
	 */
		$get,
	/**
	 * An array of each path bit in the url
	 * @var array
	 */
		$params,
	 /**
		* An array of each argument in the url path (params minus the ones leading to the controller)
		* @var array
		*/
		$arguments,
	 /**
	 * List of possible outputs with their according file extension possibilities
	 * @var array
	 */
		$outputs = array('dom'=>'html','html'=>'html','image'=>'jpg,jpeg,gif,png,svg','json'=>'json','text'=>'txt','xml'=>'xml','js'=>'js','css'=>'css,less,sass'),

	/**
	 * List of possible and existing universal controller. 
	 * First every item is set to one, then if a universal controller is needed, self::universal_controller() will look for it and sets the according array element to the file name if it's found and to false otherwise.
	 * @var array
	 */
		$ucontrollers = array(
		'dom' => 1,
		'html' => 1,
		'image' => 1,
		'json' => 1,
		'text' => 1,
		'xml' => 1,
		'css' => 1,
		'js' => 1
	);
	const
	/**
	 * Path to the controllers.
	 */
		cpath = 'mvc/controllers/',
	/**
	 * Path to the models.
	 */
		mpath = 'mvc/models/',
	/**
	 * Path to the views.
	 */
		vpath = 'mvc/views/';

	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct($db, $parent='', $data = array())
	{
		// The initial call should only have $db as parameter
		if ( defined('BBN_CUR_PATH') && is_array($parent) ){
			if ( is_object($db) && ( $class = get_class($db) ) && ( $class === 'PDO' || strpos($class, 'bbn\\db\\') !== false ) ){
				$this->db = $db;
			}
			else{
				$this->db = false;
			}
			$this->inc = new \stdClass();
			$this->routes = $parent;
			$this->mustache = false;
			$this->post = [];
			$this->get = [];
			$this->params = [];
			$this->arguments = [];
			if ( count($_POST) > 0 ){
				$this->post = $_POST;
			}
			if ( count($_GET) > 0 ){
				$this->get = $_GET;
			}
			if ( isset($_SERVER['REQUEST_URI']) && 
			( BBN_CUR_PATH === '' || strpos($_SERVER['REQUEST_URI'],BBN_CUR_PATH) !== false ) ){
        $url = explode("?", $_SERVER['REQUEST_URI'])[0];
				$tmp = explode('/', substr($url, strlen(BBN_CUR_PATH)));
				$num_params = count($tmp);
				foreach ( $tmp as $t ){
					if ( !empty($t) ){
						array_push($this->params,$t);
					}
				}
			}
			// If an available mode starts the URL params, it will be picked up
			if ( count($this->params) > 0 && isset($this->outputs[$this->params[0]]) ){
				$this->original_mode = $this->params[0];
				array_shift($this->params);
			}
			// Otherwise in the case there's a POST we'll throw back JSON
			else if ( count($this->post) > 0 ){
				$this->original_mode = 'json';
			}
			// Otherwise we'll return a whole DOM (HTML page)
			else{
				$this->original_mode = 'dom';
			}
			$this->url = implode('/',$this->params);
			$this->mode = $this->original_mode;
			$path = $this->url;
		}
		// Another call should have the initial controler and the path to reach as parameters
		else if ( is_string($db) && is_object($parent) && isset($parent->url, $parent->original_controller) ){
			$this->inc =& $parent->inc;
			$this->routes =& $parent->routes;
			$this->mustache =& $parent->mustache;
			$this->db =& $parent->db;
			$this->post =& $parent->post;
			$this->get =& $parent->get;
			$this->params =& $parent->params;
			$this->url =& $parent->url;
			$this->original_controller =& $parent->original_controller;
			$this->original_mode =& $parent->original_mode;
			$this->known_controllers =& $parent->known_controllers;
			$this->loaded_views =& $parent->loaded_views;
			$this->ucontrollers =& $parent->ucontrollers;
			$this->arguments = [];
			if ( count($data) > 0 ){
				$this->data = $data;
			}
			$path = $db;
			while ( strpos($path,'/') === 0 ){
				$path = substr($path,1);
			}
			while ( substr($path,-1) === '/' ){
				$path = substr($path,0,-1);
			}
			$params = explode('/',$path);
			if ( isset($params[0]) && isset($this->outputs[$params[0]]) ){
				$this->mode = array_shift($params);
				$path = implode('/',$params);
			}
			else if ( $this->original_mode === 'dom' ){
        $this->mode = 'html';
      }
      else {
				$this->mode = $this->original_mode;
			}
		}
		if ( isset($path) ){
			$this->route($path);
		}
	}

	/**
	 * This checks whether an argument used for getting controller, view or model - which are files - doesn't contain malicious content.
	 *
	 * @param string $p The request path <em>(e.g books/466565 or html/home)</em>
	 * @return bool
	 */
	private function check_path()
	{
		$ar = func_get_args();
		foreach ( $ar as $a ){
			if ( strpos($a,'./') !== false || strpos($a,'../') !== false || strpos($a,'/') === 0 ){
				die("The path $p is not an acceptable value");
			}
		}
		return 1;
	}

	/**
	 * This function gets the content of a view file and adds it to the loaded_views array.
	 *
	 * @param string $p The full path to the view file
	 * @return string The content of the view
	 */
	private function add_view($p)
	{
		if ( !isset($this->loaded_views[$p]) && is_file(self::vpath.$p) ){
			$this->loaded_views[$p] = file_get_contents(self::vpath.$p);
		}
		if ( !isset($this->loaded_views[$p]) ){
			die("The view $p doesn't exist");
		}
		return $this->loaded_views[$p];
	}

	/**
	 * This function gets the source of a PHP template file and adds it to the loaded_phps array.
	 *
	 * @param string $p The full path to the PHP file without the extension
	 * @return string The source of the template
	 */
	private function add_php($p)
	{
		if ( !isset($this->loaded_phps[$p]) && is_file(self::vpath.$p) ){
			$this->loaded_phps[$p] = file_get_contents(self::vpath.$p);
		}
		if ( !isset($this->loaded_phps[$p]) ){
			die("The template $p doesn't exist");
		}
		return $this->loaded_phps[$p];
	}

	/**
	 * This fetches the universal controller for the according mode if it exists.
	 *
	 * @param string $c The mode (dom, html, json, txt, xml...)
	 * @return string controller full name 
	 */
	private function universal_controller($c)
	{
		if ( !isset($this->ucontrollers[$c]) ){
			return false;
		}
		if ( $this->ucontrollers[$c] === 1 ){
			$this->ucontrollers[$c] = is_file(self::cpath.$c.'.php') ? self::cpath.$c.'.php' : false;
		}
		return $this->ucontrollers[$c];
	}

	/**
	 * Adds the newly found controller to the known controllers array, and sets the original controller if it has not been set yet
	 *
	 * @param string $c The name of the request or how set by the controller 
	 * @param file $f The actual controller file ($this->controller)
	 * @return void 
	 */
	private function set_controller($c, $f)
	{
		if ( !isset($this->known_controllers[$this->mode.'/'.$c]) ){
			$this->known_controllers[$this->mode.'/'.$c] = $f;
		}
		if ( is_null($this->original_controller) && !empty($c) ){
			$this->original_controller = $this->mode.'/'.$c;
		}
	}

	/**
	 * This directly renders content with arbitrary values using the existing Mustache engine.
	 *
	 * @param string $view The view to be rendered
	 * @param array $model The data model to fill the view with
	 * @return void 
	 */
	public function render($view, $model='')
	{
    if ( !$this->mustache ){
      $this->mustache = new \Mustache_Engine();
    }
    if ( empty($model) && isset($this->data) ){
      $model = $this->data;
    }
    if ( !is_array($model) ){
      $model = [];
    }
    /*
     * Why the F*** this??
    array_walk($model, function(&$a){
      $a = ( is_array($a) || is_object($a) ) ? json_encode($a) : $a;
    });
     */
    
    return $this->mustache->render($view, $model);
	}

	/**
	 * This looks for a given controller in the file system if it has not been already done and returns it if it finds it, false otherwise.
	 *
	 * @param string $p
	 * @return void 
	 */
	private function get_controller($p)
	{
		if ( !$this->controller ){
			if ( !is_string($p) ){
				return false;
			}
			if ( isset($this->known_controllers[$this->mode.'/'.$p]) ){
				$this->dest = $p;
        $this->dir = dirname($p);
        if ( $this->dir === '.' ){
          $this->dir = '';
        }
        else{
          $this->dir .= '/';
        }
				$this->controller = $this->known_controllers[$this->mode.'/'.$p];
			}
			else{
				if ( isset($this->routes[$this->mode][$p]) ){
          if ( is_array($this->routes[$this->mode][$p]) &&
                      is_file(self::cpath.$this->mode.'/'.$this->routes[$this->mode][$p][0].'.php') ){
            $this->controller = self::cpath.$this->mode.'/'.array_shift($this->routes[$this->mode][$p]).'.php';
            $this->arguments = $this->routes[$this->mode][$p];
          }
          else if ( is_file(self::cpath.$this->mode.'/'.$this->routes[$this->mode][$p].'.php') ){
  					$this->controller = self::cpath.$this->mode.'/'.$this->routes[$this->mode][$p].'.php';
          }
				}
				else if ( is_file(self::cpath.$this->mode.'/'.$p.'.php') ){
					$this->controller = self::cpath.$this->mode.'/'.$p.'.php';
          $parts = explode('/', $p);
          $num = count($parts);
          $path = self::cpath.$this->mode.'/';
          if ( $num > 1 ){
            for ( $i = 0; $i < ( $num - 1 ); $i++ ){
              if ( is_file($path.$parts[$i].'.php') ){
                array_push($this->checkers, $path.$parts[$i].'.php');
              }
              $path .= $parts[$i].'/';
            }
          }
				}
        // Is it necessary??
				else if ( is_dir(self::cpath.$p) && is_file(self::cpath.$p.'/'.$this->mode.'.php') ){
					$this->controller = self::cpath.$p.'/'.$this->mode.'.php';
				}
				else{
					return false;
				}
				$this->dest = $p;
        $this->dir = dirname($p);
        if ( $this->dir === '.' ){
          $this->dir = '';
        }
        else{
          $this->dir .= '/';
        }
				$this->set_controller($p,$this->controller);
			}
		}
		return 1;
	}

	/**
	 * This will fetch the route to the controller for a given path. Chainable
	 *
	 * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
	 * @return void 
	 */
	private function route($path='')
	{
		if ( !$this->is_routed && self::check_path($path) )
		{
			$this->is_routed = 1;
			$this->path = $path;
			$fpath = $path;
			while ( strlen($fpath) > 0 )
			{
				if ( $this->get_controller($fpath) ){
					if ( strlen($fpath) < strlen($this->path) ){
						$this->arguments = explode('/',substr($this->path,strlen($fpath)));
						// Trimming the array
						while ( empty($this->arguments[0]) ){
							array_shift($this->arguments);
						}
						$t = end($this->arguments);
						while ( empty($t) ){
							$t = end($this->arguments);
							array_pop($this->arguments);
						}
					}
					break;
				}
				else{
					$fpath = strpos($fpath,'/') === false ? '' : substr($this->path,0,strrpos($fpath,'/'));
				}
			}
			if ( !$this->controller ){
				$this->get_controller('default');
			}
		}
		return $this;
	}

	/**
	 * This will reroute a controller to another one seemlessly. Chainable
	 *
	 * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
	 * @return void 
	 */
	public function reroute($path='')
	{
		$this->is_routed = false;
		$this->controller = false;
		$this->is_controlled = null;
		$this->route($path);
		$this->check();
		return $this;
	}

	public function add_script($script)
	{
    if ( is_object($this->obj) ){
      if ( !isset($this->obj->script) ){
        $this->obj->script = '';
      }
      $this->obj->script .= $script;
    }
		return $this;
	}

	/**
	 * This will enclose the controller's inclusion
	 * It can be publicly launched through check()
	 *
	 * @return void 
	 */
	private function control()
	{
		if ( $this->controller && is_null($this->is_controlled) ){
			ob_start();
			require($this->controller);
			$output = ob_get_contents();
			ob_end_clean();
			if ( is_object($this->obj) && !isset($this->obj->output) && !empty($output) ){
				$this->obj->output = $output;
			}
			$this->is_controlled = 1;
		}
		return $this;
	}
  
	/**
	 * This will launch the controller in a new function.
	 * It is publicly launched through check().
	 *
	 * @return void 
	 */
	private function process()
	{
		if ( $this->controller && is_null($this->is_controlled) ){
			$this->obj = new \stdClass();
			$this->control();
			if ( $this->data && is_array($this->data) && isset($this->obj->output) ){
				$this->obj->output = $this->render($this->obj->output,$this->data);
			}
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
	public function get_view($path='', $mode='')
	{
		if ( $this->mode && !is_null($this->dest) && $this->check_path($path, $this->mode) ){
			if ( empty($mode) ){
				$mode = $this->mode;
			}
			if ( empty($path) ){
				$path = $this->dest;
			}
			if ( isset($this->outputs[$mode]) ){
				$ext = explode(',',$this->outputs[$mode]);
				/* First we look into the loaded_views if it isn't there already */
				foreach ( $ext as $e ){
					$file1 = $mode.'/'.$path.'.'.$e;
					$t = explode('/',$path);
					$file2 = $mode.'/'.$path.'/'.array_pop($t).'.'.$e;
					if ( isset($this->loaded_views[$file1]) ){
						return $this->loaded_views[$file1];
					}
					else if ( isset($this->loaded_views[$file2]) ){
						return $this->loaded_views[$file2];
					}
					else if ( is_file(self::vpath.$file1) ){
						return $this->add_view($file1);
					}
					else if ( is_file(self::vpath.$file2) ){
						return $this->add_view($file2);
					}
				}
			}
		}
		return false;
	}

	/**
	 * This will get a PHP template view
	 *
	 * @param string $path
	 * @param string $mode
	 * @return string|false 
	 */
	private function get_php($path='', $mode='')
	{
		if ( $this->mode && !is_null($this->dest) && $this->check_path($path, $this->mode) ){
			if ( empty($mode) ){
				$mode = $this->mode;
			}
			if ( empty($path) ){
				$path = $this->dest;
			}
			if ( isset($this->outputs[$mode]) ){
				$file = $mode.'/'.$path.'.php';
				if ( isset($this->loaded_phps[$file]) ){
					$bbn_php = $this->loaded_phps[$file];
				}
				else if ( is_file(self::vpath.$file) ){
					$bbn_php = $this->add_php($file);
				}
				if ( isset($bbn_php) ){
					$args = array();
					if ( isset($this->data) && count($this->data) > 0 ){
						foreach ( (array)$this->data as $key => $val ){
							$$key = $val;
							array_push($args, '$'.$key);
						}
					}
					return eval('return call_user_func(function() use ('.implode(',', $args).'){ ?>'.$bbn_php.' <?php });');
				}
			}
		}
		return false;
	}

	/**
	 * This will get the model. There is no order for the arguments.
	 *
	 * @params string path to the model
	 * @params array data to send to the model
	 * @return array|false A data model 
	 */
	private function get_model()
	{
		if ( $this->dest ){
			$args = func_get_args();
			foreach ( $args as $a ){
				if ( is_array($a) ){
					$d = $a;
				}
				else if ( is_string($a) && $this->check_path($a) ){
					$path = $a;
				}
				else if ( is_object($a) && ( $class = get_class($a) ) && ( $class === 'PDO' || $class === 'bbn\db\connection' ) ){
					$db = $a;
				}
			}
			if ( !isset($path) ){
				$path = $this->dest;
			}
			if ( strpos($path,'..') === false && is_file(self::mpath.$path.'.php') ){
				$db = isset($db) ? $db : $this->db;
				$last_model_file = self::mpath.$path.'.php';
				$data = isset($d) ? $d : $this->data;
				return call_user_func(
					function() use ($db, $last_model_file, $data)
					{
						$r = include($last_model_file);
						return $r ? $r : array();
					}
				);
			}
		}
		return false;
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
	 * Processes the controller and checks whether it has been routed or not.
	 *
	 * @return bool 
	 */
	public function check()
	{
    foreach ( $this->checkers as $chk ){
      // If a checker file returns false, the controller is not processed
      if ( !include_once($chk) ){
        return false;
      }
    }
		$this->process();
		return $this->is_routed;
	}

	/**
	 * Returns the output object.
	 *
	 * @return object|false 
	 */
	public function get()
	{
		if ( $this->check() ){
			return $this->obj;
		}
		return false;
	}

	/**
	 * Checks if data exists
	 *
	 * @return bool
	 */
	public function has_data()
	{
		return ( isset($this->data) && is_array($this->data) ) ? 1 : false;
	}

	/**
	 * Returns the rendered result from the current mvc if successufully processed
	 * process() (or check()) must have been called before.
	 *
	 * @return string|false
	 */
	public function get_rendered()
	{
		if ( isset($this->obj->output) ){
			return $this->obj->output;
		}
		return false;
	}

	/**
	 * Returns the rendered result from the current mvc if successufully processed
	 * process() (or check()) must have been called before.
		*
	 * @return string|false
	 */
	public function get_script()
	{
		if ( isset($this->obj->script) ){
			return $this->obj->script;
		}
		return '';
	}

	/**
	 * Sets the data. Chainable. Should be useless as $this->data is public. Chainable.
	 *
	 * @param array $data
	 * @return void 
	 */
	public function set_data(array $data)
	{
		$this->data = $data;
		return $this;
	}

	/**
	 * Merges the existing data if there is with this one. Chainable.
	 *
	 * @return void 
	 */
	public function add_data(array $data)
	{
		$ar = func_get_args();
		foreach ( $ar as $d )
		{
			if ( is_array($d) )
			{
				if ( !is_array($this->data) ){
					$this->data = $d;
				}
				else{
					$this->data = array_merge($this->data,$d);
				}
			}
		}
		return $this;
	}

	/**
	 * Merges the existing data if there is with this one. Chainable.
	 *
	 * @return void 
	 */
	public function add($d, $data=array())
	{
		$o = new mvc($d, $this, $data);
		if ( $o->check() ){
			return $o;
		}
		return false;
	}

	/**
	 * Outputs the result.
	 *
	 * @return void 
	 */
	public function output()
	{
		if ( !$this->obj ){
			$this->obj = new \stdClass();
		}
		if ( $this->check() && $this->obj ){
			
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
        case 'dom':
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
			switch ( $this->mode ){
				
				case 'json':
					if ( isset($this->obj->output) ){
						$this->obj->html = $this->obj->output;
						unset($this->obj->output);
					}
					header('Content-type: application/json; charset=utf-8');
					echo json_encode($this->obj);
					break;
          
				case 'dom':
				case 'html':
					header('Content-type: text/html; charset=utf-8');
					echo $this->obj->output;
					break;
          
				case 'js':
					header('Content-type: application/javascript; charset=utf-8');
					echo $this->obj->output;
					break;
        
				case 'css':
					header('Content-type: text/css; charset=utf-8');
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
						header('HTTP/1.0 404 Not Found');
					}
					break;
          
				default:
					if ( isset($this->obj->file) ){
						if ( !is_file($this->obj->file) ){
							unset($this->obj->file);
						}
					}
					if ( !isset($this->obj->file) ){
						header('HTTP/1.0 404 Not Found');
						exit();
					}
					header("X-Sendfile: ".$this->obj->file);
					header("Content-type: application/octet-stream");
					header('Content-Disposition: attachment; filename="' . basename($this->obj->file) . '"');
			}
		}
	}
}
?>