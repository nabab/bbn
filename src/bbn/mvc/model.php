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

class model extends obj{

	use common;

	private
		/**
		 * @var \bbn\db\connection Database object
		 */
		$db,
		/**
		 * The name of the controller (its real path and filename without extension)
		 * @var null|string
		 */
		$dest,
		/**
		 * The directory of the controller (the directory in which is $this->dest).
		 * @var null|string
		 */
		$dir,
		/**
		 * The path as being requested
		 * @var null|string
		 */
		$path;

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
		 * The request sent to the server to get the actual controller.
		 * @var null|string
		 */
		$url,
		/**
		 * The first controller to be called at the top of the script.
		 * @var null|string
		 */
		$original_controller;

	const
		/**
		 * Path to the models.
		 */
		mpath = 'mvc/models/';

	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct($path, $db, $inc, $data = [])
	{
		if ( $this->check_path() && file_exists($path.'.php') ){
			$this->inc = $inc;
		}
	}

	/**
	 * Returns the current controller's path.
	 *
	 * @return string
	 */
	public function say_path()
	{
		return $this->controller ? substr($this->controller, strlen(self::cpath.$this->mode.'/'), -4) : false;
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
	 * Returns true if called from CLI/Cron, false otherwise
	 *
	 * @return boolean
	 */
	public function is_cli()
	{
		return $this->is_cli();
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
}