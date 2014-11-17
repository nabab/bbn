<?php

/* 
 * Copyright (C) 2014 BBN
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bbn;

class environment{
  
	private
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
	 * The mode of the output (dom, html, json, txt, xml...)
	 * @var null|string
	 */
		$mode,
	/**
	 * Determines if it is sent through the command line
	 * @var boolean
	 */
		$cli,
	/**
	 * Parameters which will be sent to the m/v/c
	 * @var array
	 */
		$cfg = [
      'm' => [],
      'v' => [],
      'c' => []
    ];
  
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
		$data = [],
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
		$known_controllers = [],
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
	 * An array of each path bit in the url
	 * @var array
	 */
		$params = [],
	 /**
		* An array of each argument in the url path (params minus the ones leading to the controller)
		* @var array
		*/
		$arguments = [],
	 /**
	 * List of possible outputs with their according file extension possibilities
	 * @var array
	 */
		$outputs = ['dom'=>'html','html'=>'html','image'=>'jpg,jpeg,gif,png,svg','json'=>'json','text'=>'txt','xml'=>'xml','js'=>'js','css'=>'css,less,sass'],

	/**
	 * List of possible and existing universal controller. 
	 * First every item is set to one, then if a universal controller is needed, self::universal_controller() will look for it and sets the according array element to the file name if it's found and to false otherwise.
	 * @var array
	 */
		$ucontrollers = [
      'dom' => 1,
      'html' => 1,
      'image' => 1,
      'json' => 1,
      'text' => 1,
      'xml' => 1,
      'css' => 1,
      'js' => 1
    ];
  
  private static $on = false;

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
   * Sets the $on static var to true allowing the constructor to only be called once
   */
  private static function call(){
    self::$on = 1;
  }

	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct(\bbn\db\connection $db)
	{
    $this->control();
    
    
    if ( count($_POST) > 0 ){
      $this->post = array_map(function($a){
        return \bbn\str\text::correct_types($a);
      }, $_POST);
    }
    
    if ( count($_GET) > 0 ){
      $this->get = array_map(function($a){
        return \bbn\str\text::correct_types($a);
      }, $_GET);
    }
    
    if ( count($_FILES) > 0 ){
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
    
    if ( isset($_SERVER['REQUEST_URI']) && (
            BBN_CUR_PATH === '' ||
            strpos($_SERVER['REQUEST_URI'],BBN_CUR_PATH) !== false
            ) ){
      $url = explode("?", urldecode($_SERVER['REQUEST_URI']))[0];
      $tmp = explode('/', substr($url, strlen(BBN_CUR_PATH)));
      $num_params = count($tmp);
      foreach ( $tmp as $t ){
        if ( !empty($t) ){
          array_push($this->params, $t);
        }
      }
    }
    
    if ( is_object($db) && ( $class = get_class($db) ) && ( $class === 'PDO' || strpos($class, 'bbn\\db\\') !== false ) ){
      $this->db = $db;
    }
    else{
      $this->db = false;
    }
    
    /** @property \stdClass $inc An object which properties will store another object accessible throughout controllers and models */
    $this->inc = new \stdClass();
    $this->routes = $parent;
    $this->cli = (php_sapi_name() === 'cli');

    // When using CLI a first parameter can be used as route,
    // a second JSON encoded can be used as $this->post
    if ( $this->cli ){
      global $argv;
      if ( isset($argv[1]) ){
        $tmp = explode('/', $argv[1]);
        $num_params = count($tmp);
        foreach ( $tmp as $t ){
          if ( !empty($t) ){
            array_push($this->params,$t);
          }
        }
        if ( isset($argv[2]) && json_decode($argv[2]) ){
          $this->post = array_map(function($a){
            return \bbn\str\text::correct_types($a);
          }, json_decode($argv[2], 1));
        }
      }
    }
    
    // If an available mode starts the URL params, it will be picked up
    if ( count($this->params) > 0 && isset($this->outputs[$this->params[0]]) ){
      $this->original_mode = $this->params[0];
      array_shift($this->params);
    }
    // Otherwise in the case there's a "appui" POST we'll throw back JSON
    else if ( isset($this->post['appui']) && isset($this->outputs[$this->post['appui']]) ){
      $this->original_mode = $this->post['appui'];
      unset($this->post['appui']);
    }
    else if ( count($this->post) > 0 ){
      if ( isset($this->post['appui']) ){
        unset($this->post['appui']);
      }
      $this->original_mode = 'json';
    }
    // Otherwise we'll return a whole DOM (HTML page)
    else{
      $this->original_mode = 'dom';
    }
    if ( $this->cli ){
      $this->original_mode = 'cli';
    }
    $this->url = implode('/',$this->params);
    $this->mode = $this->original_mode;
    $path = $this->url;

    $this->inc =& $parent->inc;
    $this->routes =& $parent->routes;
    $this->cli =& $parent->cli;
    $this->db =& $parent->db;
    $this->post =& $parent->post;
    $this->get =& $parent->get;
    $this->files =& $parent->files;
    $this->params =& $parent->params;
    $this->url =& $parent->url;
    $this->original_controller =& $parent->original_controller;
    $this->original_mode =& $parent->original_mode;
    $this->known_controllers =& $parent->known_controllers;
    $this->loaded_views =& $parent->loaded_views;
    $this->ucontrollers =& $parent->ucontrollers;
    $this->arguments = [];
    if ( $this->has_data($data) ){
      $this->data = $data;
    }
    $path = $db;
    while ( strpos($path, '/') === 0 ){
      $path = substr($path, 1);
    }
    while ( substr($path, -1) === '/' ){
      $path = substr($path, 0, -1);
    }
    $params = explode('/', $path);
    if ( $this->cli ){
      $this->mode = 'cron';
    }
    else if ( isset($params[0]) && isset($this->outputs[$params[0]]) ){
      $this->mode = array_shift($params);
      $path = implode('/', $params);
    }
    else if ( $this->original_mode === 'dom' ){
      $this->mode = 'html';
    }
    else {
      $this->mode = $this->original_mode;
    }
		if ( isset($path) ){
			$this->route($path);
		}
	}
  
	/**
	 * This checks that the environment has not already been called, that the constant are defined, and the bbn library included
	 */
  private function control()
  {
    if ( self::$on ){
      die("The environment class can be only called once");
    }
		if ( !defined('BBN_CUR_PATH') ){
      die("No configuration set up. All the BBN_ variables should be defined");
    }
		if ( !class_exists("\\bbn\\tools") ){
      die("The bbn core libraries are not included");
    }
    self::call();
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
			if ( !is_string($a) ||
              (strpos($a,'./') !== false) ||
              (strpos($a,'/') === 0) ){
				die("The path $a is not an acceptable value");
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
	private function set_controller($c)
	{
    if ( $this->controller && $this->mode ){
      if ( !isset($this->known_controllers[$this->mode.'/'.$c]) ){
        $this->known_controllers[$this->mode.'/'.$c] = [
          'path' => $this->controller,
          'args' => $this->arguments
        ];
      }
      if ( is_null($this->original_controller) ){
        $this->original_controller = $this->mode.'/'.$c;
      }
    }
	}

	/**
	 * This returns the current controller's file's name.
	 *
	 * @return string 
	 */
	public function say_controller()
	{
    return $this->controller ? $this->controller : false;
  }
  
	/**
	 * This returns the current controller's file's name.
	 *
	 * @return string 
	 */
	public function say_dir()
	{
    return $this->controller ? dirname($this->controller) : false;
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
    if ( empty($model) && $this->has_data() ){
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

    $tmpl = \LightnCandy::compile($view, [
      'flags' => \LightnCandy::FLAG_MUSTACHELOOKUP |
        \LightnCandy::FLAG_HANDLEBARS |
        \LightnCandy::FLAG_ERROR_LOG
    ]);
    $rndr = \LightnCandy::prepare($tmpl, BBN_DATA_PATH.'tmp');
    return $rndr($model);
	}

	/**
	 * Returns true if called from CLI/Cron, false otherwise
	 *
	 * @return boolean 
	 */
	private function is_cli()
  {
    return $this->cli;
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
				$this->controller = $this->known_controllers[$this->mode.'/'.$p]['path'];
        $this->arguments = $this->known_controllers[$this->mode.'/'.$p]['args']; 
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
				$this->set_controller($p);
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
      
      // We go through each path, starting by the longest until it's empty
			while ( strlen($fpath) > 0 ){
				if ( $this->get_controller($fpath) ){
					if ( strlen($fpath) < strlen($this->path) ){
            $this->arguments = [];
            $args = explode('/', substr($this->path, strlen($fpath)));
            foreach ( $args as $a ){
              if ( \bbn\str\text::is_number($a) ){
                $a = (int)$a;
              }
              array_push($this->arguments, $a);
            }
						// Trimming the array
						while ( empty($this->arguments[0]) ){
							array_shift($this->arguments);
						}
						$t = end($this->arguments);
						while ( empty($t) ){
							array_pop($this->arguments);
							$t = end($this->arguments);
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

	/**
	 * This will include a file from within the controller's path. Chainable
	 *
	 * @param string $file_name If .php is ommited it will be added
	 * @return void 
	 */
	public function incl($file_name)
	{
    if ( $this->is_routed ){
      $d = $this->say_dir().'/';
      if ( substr($file_name, -4) !== '.php' ){
        $file_name .= '.php';
      }
      if ( (strpos($file_name, '..') === false) && file_exists($d.$file_name) ){
        include($d.$file_name);
      }
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
			if ( $this->has_data() && isset($this->obj->output) ){
				$this->obj->output = $this->render($this->obj->output, $this->data);
			}
		}
		return $this;
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
	public function has_data($data=null)
	{
    if ( is_null($data) ){
      $data = $this->data;
    }
		return ( is_array($data) && (count($data) > 0) ) ? 1 : false;
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
            $this->log("Impossible to display the following image: ".$this->obj->img->name);
						header('HTTP/1.0 404 Not Found');
            
					}
					break;
          
				default:
          //die(\bbn\tools::dump($this->obj->file, method_exists($this->obj->file, 'download')));
					if ( isset($this->obj->file) && is_object($this->obj->file) && method_exists($this->obj->file, 'download') ){
            $this->obj->file->download();
          }
          else{
            $this->log("Impossible to display the following controller", $this);
						header('HTTP/1.0 404 Not Found');
						exit();
					}
			}
		}
	}
}