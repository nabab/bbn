<?php
namespace bbn\mvc;
use bbn;
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
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */

class model extends bbn\models\cls\db{

	use
    common,
    bbn\models\tts\cache;

	private
    /**
     * The MVC class from which the model is called
     * @var mvc
     */
    $mvc,
    /**
     * The file as being requested
     * @var null|string
     */
    $file,
    /**
     * The controller instance requesting the model
     * @var null|string
     */
    $ctrl,
    /**
     * The path as being requested
     * @var null|string
     */
    $path;

  public
    /**
     * The database connection instance
     * @var null|bbn\db
     */
    $db,
    /**
     * The data model
     * @var null|array
     */
    $data,
  /**
   * An external object that can be filled after the object creation and can be used as a global with the function add_inc
   * @var \stdClass
   */
    $inc;

	/**
	 * Models are always recreated and reincluded, even if they have from the same path
   * They are all created from bbn\mvc::get_model
	 *
	 * @param null|bbn\db $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
   * @param array  $info The full path to the model's file
   * @param controller $ctrl The parent controller
   * @param controller $mvc The parent MVC
	 */
	public function __construct(bbn\db $db=null, array $info, controller $ctrl, bbn\mvc $mvc){
		if ( isset($info['path']) && $this->check_path($info['path']) ){
      parent::__construct($db);
      $this->cache_init();
      $this->ctrl = $ctrl;
      $this->mvc = $mvc;
			$this->inc = $this->ctrl->inc;
      if ( is_file($info['file']) ){
        $this->path = $info['path'];
        $this->file = $info['file'];
      }
		}
		else{
			$this->error("The model $info[path] doesn't exist");
		}
	}

  public function register_plugin_classes($plugin_path): self
  {
    $this->ctrl->register_plugin_classes($plugin_path);
    return $this;
  }


  public function get(array $data=null){
    if ( \is_null($data) ){
      $data = [];
    }
    $this->data = $data;
    return bbn\mvc::include_model($this->file, $this);
  }

  public function get_content(){
    return $this->ctrl->get_content(...\func_get_args());
  }

  public function get_model(){
    return $this->ctrl->get_model(...\func_get_args());
  }

  public function get_cached_model(){
    return $this->ctrl->get_cached_model(...\func_get_args());
  }

  public function get_plugin_model($path, $data = [], $ttl = 0){
    return $this->ctrl->get_plugin_model(...\func_get_args());
  }

  public function has_plugin(){
    return $this->ctrl->has_plugin(...\func_get_args());
  }

  public function is_plugin(){
    return $this->ctrl->is_plugin(...\func_get_args());
  }

  public function plugin_path(){
    return $this->ctrl->plugin_path(...\func_get_args());
  }

  public function plugin_url(){
    return $this->ctrl->plugin_url(...\func_get_args());
  }
  /**
	 * Checks if data exists or if a specific index exists in the data
	 *
	 * @return bool
	 */
	public function has_data($idx=null)
	{
    if ( !\is_array($this->data) ){
      return false;
    }
    if ( \is_null($idx) ){
      return !empty($this->data);
    }
    $args = \func_get_args();
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
		$ar = \func_get_args();
		foreach ( $ar as $d ){
			if ( \is_array($d) ){
				$this->data = $this->has_data() ? array_merge($this->data,$d) : $d;
			}
		}
		return $this;
	}

	protected function _cache_name($data, $spec = ''){
    if ( $this->path ){
      $cn = 'models/'.$this->path;
      if ( $spec ){
        $cn .= '/'.$spec;
      }
      if ( $data ){
        $cn .= '/'.md5(serialize($data));
      }
      return $cn;
    }
  }

  public function set_cache(array $data = null, $spec='', $ttl = 10){
    if ( $this->path ){
      $d = $this->get($data);
      $this->cache_set($this->_cache_name($data, $spec), '', $d, $ttl);
    }
  }

  public function delete_cache(array $data = null, $spec=''){
    if ( $cn = $this->_cache_name($data, $spec) ){
      $this->cache_delete($cn, '');
    }
  }

  public function get_from_cache(array $data = null, $spec='', $ttl = 10){
    if ( $cn = $this->_cache_name($data, $spec) ){
      if ( \is_int($ttl) && $this->cache_has($cn) ){
        return $this->cache_get($cn);
      }
      $this->set_cache($data, $spec, $ttl);
      if ( $this->cache_has($cn) ){
        return $this->cache_get($cn);
      }
      return false;
    }
  }
}