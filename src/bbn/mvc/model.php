<?php
namespace bbn\mvc;
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

class model{

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
		$url;

	const
		/**
		 * Path to the models.
		 */
		root = 'mvc/models/';

	/**
	 * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
	 *
	 * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct($path, \bbn\db\connection $db=null, \stdClass $inc=null)
	{
		if ( $this->check_path() && file_exists(self::root.$path.'.php') ){
			$this->inc = $inc;
			$this->db = $db;
			$this->path = $path;
		}
		else{
			$this->error("The model $path doesn't exist");
		}
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

	public function get(array $data=null){
		$db = isset($db) ? $db : $this->db;
		$file = self::root.$this->path.'.php';
		if ( is_null($data) ){
			$data = [];
		}
		$this->data = $data;
		return call_user_func(
			function() use ($db, $file, $data)
			{
				//$r = include($last_model_file);
				//return is_array($r) ? $r : array();
				return include($file);
			}
		);
	}

	/**
	 * This will get the model. There is no order for the arguments.
	 *
	 * @params string path to the model
	 * @params array data to send to the model
	 * @return array|false A data model
	 */
	private function get_model($path, $data=[])
	{
		if ( $this->check() ) {
			$model = new \bbn\mvc\model($this->db, $path);
			return $model->get($data);
		}
	}

	/**
	 * Checks if data exists or if a specific index exists in the data
	 *
	 * @return bool
	 */
	public function has_data($idx=null)
	{
		if ( is_null($idx) ){
			return ( is_array($this->data) && !empty($this->data) );
		}
		return ( is_array($this->data) && isset($this->data[$idx]) );
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