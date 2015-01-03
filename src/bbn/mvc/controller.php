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

namespace bbn\mvc;

class controller implements api{

	use common;

	private
		/**
		 * The MVC class from which the controller is called
		 * @var \bbn\mvcv2
		 */
		$mvc,
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
		$arguments = [];
	const
		/**
		 * Path to the controllers.
		 */
		cpath = 'mvc/controllers/';

	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct(\bbn\mvcv2 $mvc, $path='', $data = [])
	{
		// The initial call should only have $db as parameter
		if ( defined('BBN_CUR_PATH') ) {
			$this->mvc =& $mvc;
			$this->path = $path;
			$this->data = is_array($data) ? $data : [];
			// When using CLI a first parameter can be used as route,
			// a second JSON encoded can be used as $this->post
			$this->post = $this->mvc->get_post();
			$this->get = $this->mvc->get_get();
			$this->files = $this->mvc->get_files();
			$this->params = $this->mvc->get_params();

			$this->route();
		}
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
	private function set_controller($p)
	{
		if ( $this->controller ){
			$this->dest = $p;
			$this->dir = dirname($p);
			if ( $this->dir === '.' ){
				$this->dir = '';
			}
			else{
				$this->dir .= '/';
			}
		}
	}

	public function exists(){
		return $this->dest;
	}

	/**
	 * Returns the current controller's file's name.
	 *
	 * @return string
	 */
	public function say_controller()
	{
		return $this->controller ? $this->controller : false;
	}

	/**
	 * Returns the current controller's path.
	 *
	 * @return string
	 */
	public function say_path()
	{
		return $this->controller ? substr($this->controller, strlen(self::cpath), -4) : false;
	}

	/**
	 * Returns the current controller's route, i.e as demanded by the client.
	 *
	 * @return string
	 */
	public function say_route()
	{
		return $this->path;
	}

	/**
	 * Returns the current controller's file's name.
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
		if ( is_string($view) ) {
			return \bbn\tpl::render($view, $model);
		}
		die(\bbn\tools::hdump("Problem with the template", $view));
	}

	/**
	 * Returns true if called from CLI/Cron, false otherwise
	 *
	 * @return boolean
	 */
	public function is_cli()
	{
		return $this->mvc->is_cli();
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
			if ( is_file(self::cpath.$p.'.php') ){
				$this->controller = self::cpath.$p.'.php';
				$parts = explode('/', $p);
				$path = self::cpath;
				// if the current directory of the controller, or any directory above it in the controllers' filesystem, has a file called _ctrl.php, it will be executed and expected to return a non false value in order to authorize the loading of the controller
				foreach ( $parts as $pt ){
					if ( is_file($path.'_ctrl.php') ){
						array_push($this->checkers, $path.'_ctrl.php');
					}
					$path .= $pt.'/';
				}
				$this->set_controller($p);
				return 1;
			}
			return false;
		}
		return 1;
	}



	/**
	 * This will fetch the route to the controller for a given path. Chainable
	 *
	 * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
	 * @return void
	 */
	private function route()
	{
		if ( !$this->is_routed && self::check_path($this->path) )
		{
			$this->is_routed = 1;
			$fpath = $this->path;

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
		}
		return $this;
	}

	/**
	 * This will reroute a controller to another one seemlessly. Chainable
	 *
	 * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
	 * @return void
	 */
	public function reroute($path='', $check = 1)
	{
		return $this->mvc->reroute($path, $check);
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
	 * This will add the given string to the script property, and create it if needed. Chainable
	 *
	 * @param string $script The javascript chain to add
	 * @return void
	 */
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
			if ( $this->has_data() && isset($this->obj->output) ){
				$this->obj->output = $this->render($this->obj->output, $this->data);
			}
		}
		return $this;
	}

	/**
	 * This will get a javascript view encapsulated in an anonymous function for embedding in HTML.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function get_js($path='')
	{
		if ( $r = $this->get_view($path, 'js') ){
			return '
<script>
(function($){
'.$r.'
})(jQuery);
</script>';
		}
		return false;
	}

	/**
	 * This will get a CSS view encapsulated in a scoped style tag.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function get_css($path='')
	{
		if ( $r = $this->get_view($path, 'css') ){
			return '<style scoped>'.\CssMin::minify($r).'</style>';
		}
		return false;
	}

	/**
	 * This will get and compile a LESS view encapsulated in a scoped style tag.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function get_less($path='')
	{
		if ( !isset($this->less) ){
			if ( !class_exists('lessc') ){
				die("No less class, check composer");
			}
			$this->less = new \lessc();
		}
		if ( $r = $this->get_view($path, 'less') ){
			return '<style scoped>'.\CssMin::minify($this->less->compile($r)).'</style>';
		}
		return false;
	}

	/**
	 * This will add a javascript view to $this->obj->script
	 * Chainable
	 *
	 * @param string $path
	 * @param string $mode
	 * @return string|false
	 */
	public function add_js()
	{
		$args = func_get_args();
		foreach ( $args as $a ){
			if ( is_array($a) ){
				$data = $a;
			}
			else if ( is_string($a) ){
				$path = $a;
			}
		}
		if ( $r = $this->get_view(isset($path) ? $path : '', 'js') ){
			$this->add_script($this->render($r, isset($data) ? $data : $this->data));
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
	public function get_view($path, $data=[])
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
					$file1 = $path.'.'.$e;
					$t = explode('/',$path);
					$file2 = $path.'/'.array_pop($t).'.'.$e;
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
	 * This will get a the content of a file located within the data path
	 *
	 * @param string $file_name
	 * @return string|false
	 */
	public function get_content($file_name)
	{
		if ( $this->check_path($file_name) && defined('BBN_DATA_PATH') && is_file(BBN_DATA_PATH.$file_name) ){
			return file_get_contents(BBN_DATA_PATH.$file_name);
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
				$file = $path.'.php';
				if ( isset($this->loaded_phps[$file]) ){
					$bbn_php = $this->loaded_phps[$file];
				}
				else if ( is_file(self::vpath.$file) ){
					$bbn_php = $this->add_php($file);
				}
				if ( isset($bbn_php) ){
					$args = array();
					if ( $this->has_data() ){
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
	public function get_model($path, $data=[])
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
						//$r = include($last_model_file);
						//return is_array($r) ? $r : array();
						return include($last_model_file);
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

	public function get_mode(){
		return $this->mvc->get_mode();
	}

	public function set_mode($mode){
		return $this->mvc->set_mode($mode);
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
		foreach ( $ar as $d ){
			if ( is_array($d) ){
				$this->data = $this->has_data() ? array_merge($this->data,$d) : $d;
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

	public function get_result(){
		return $this->obj;
	}

}
