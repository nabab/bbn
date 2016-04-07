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
		 * @var \bbn\db Database object
		 */
		$db,
    /**
     * The data model
     * @var null|array
     */
		$data,
		/**
		 * The path as being requested
		 * @var null|string
		 */
    $file,
		/**
		 * An external object that can be filled after the object creation and can be used as a global with the function add_inc
		 * @var stdClass
		 */
		$inc;

	/**
	 * Models are always recreated and reincluded, even if they have from the same path
   * They are all created from \bbn\mvc::get_model
	 *
   * @param array  $info The full path to the model's file
	 * @param null|\bbn\db $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
	 * @param string | object $parent The parent controller</em>
	 * @return bool
	 */
	public function __construct(array $info, \bbn\db $db=null, controller $ctrl){
		if ( $this->check_path() ){
      $this->ctrl = $ctrl;
			$this->inc = $this->ctrl->inc;
			$this->db = $db;
			$this->file = $info['file'];
		}
		else{
			$this->error("The model $file doesn't exist");
		}
	}

  public function get(array $data=null){
    if ( is_null($data) ){
      $data = [];
    }
    $this->data = $data;
    $d = include($this->file);
    if ( !is_array($d) ){
      return false;
    }
    return $d;
  }

  public function get_content(){
    return call_user_func_array([$this->ctrl, 'get_content'], func_get_args());
  }

  public function get_model(){
    return call_user_func_array([$this->ctrl, 'get_model'], func_get_args());
  }

  /**
	 * Checks if data exists or if a specific index exists in the data
	 *
	 * @return bool
	 */
	public function has_data($idx=null)
	{
    if ( !is_array($this->data) ){
      return false;
    }
    if ( is_null($idx) ){
      return !empty($this->data);
    }
    $args = func_get_args();
    foreach ( $args as $arg ){
      if ( !isset($this->data[$idx]) ){
        return false;
      }
    }
    return true;
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