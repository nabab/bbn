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

  /**
   * The file as being requested
   * @var null|string
   */
  private $_file;
  /**
   * The controller instance requesting the model
   * @var null|string
   */
  private $_ctrl;
  /**
   * The path as being requested
   * @var null|string
   */
  private $_path;
  /**
   * Included files
   * @var null|string
   */
  private $_checkers;

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
  public function __construct(bbn\db $db=null, array $info, controller $ctrl, bbn\mvc $mvc)
  {
		if ( isset($info['path']) && $this->check_path($info['path']) ){
      if ($db) {
        parent::__construct($db);
      }
      $this->cache_init();
      $this->_ctrl = $ctrl;
      $this->_mvc = $mvc;
			$this->inc = &$mvc->inc;
      if ( is_file($info['file']) ){
        $this->_path = $info['path'];
        $this->_file = $info['file'];
        $this->_checkers = $info['checkers'] ?? [];
      }
		}
		else{
			$this->error("The model $info[path] doesn't exist");
		}
  }
  
  public function check_action(array $vars = null, bool $check_empty = false): bool
  {
    if (isset($this->data['res'], $this->data['res'])) {
      if (is_array($vars)) {
        return bbn\x::has_props($this->data, $vars, $check_empty);
      }
      return true;
    }
    return false;
  }

  public function is_controlled_by(string $path, string $type = 'public'): bool
  {
    if ($this->_ctrl && ($this->_ctrl->get_path() === $path)) {
      if ($type === 'cli') {
        return $this->mvc->is_cli();
      }
      if ($type === $this->_ctrl->mode) {
        return true;
      }
    }
    return false;
  }

  public function get_controller_path()
  {
    return $this->_ctrl ? $this->_ctrl->get_path() : false;
  }

  public function has_var(string $var, bool $check_empty = false): bool
  {
    return bbn\x::has_prop($this->data, $var, $check_empty);
  }

  public function has_vars(array $vars, bool $check_empty = false): bool
  {
    return bbn\x::has_props($this->data, $vars, $check_empty);
  }

  public function register_plugin_classes($plugin_path): self
  {
    $this->_ctrl->register_plugin_classes($plugin_path);
    return $this;
  }


  public function get(array $data=null){
    if ( \is_null($data) ){
      $data = [];
    }
    $this->data = $data;
    if ( $this->_plugin ){
      $this->apply_locale($this->_plugin);
    }
    if ( $this->_checkers ){
      foreach ( $this->_checkers as $chk ){
        $d = bbn\mvc::include_model($chk, $this);
        if (is_array($d)) {
          $this->add_data($d);
        }
      }
    }
    return bbn\mvc::include_model($this->_file, $this);
  }

  public function get_content(){
    return $this->_ctrl->get_content(...\func_get_args());
  }

  public function get_model(){
    return $this->_ctrl->get_model(...\func_get_args());
  }

  public function get_cached_model(){
    return $this->_ctrl->get_cached_model(...\func_get_args());
  }

  public function get_plugin_model($path, $data = [], string $plugin = null, $ttl = 0){
    return $this->_ctrl->get_plugin_model(...\func_get_args());
  }

  public function has_plugin(){
    return $this->_ctrl->has_plugin(...\func_get_args());
  }

  public function is_plugin(){
    return $this->_ctrl->is_plugin(...\func_get_args());
  }

  public function plugin_path(){
    return $this->_ctrl->plugin_path(...\func_get_args());
  }

  public function plugin_url(){
    return $this->_ctrl->plugin_url(...\func_get_args());
  }

  /**
   * Adds a property to the MVC object inc if it has not been declared.
   *
   * @return self
   */
  public function add_inc($name, $obj)
  {
    $this->_mvc->add_inc($name, $obj);
    return $this;
  }

  /**
	 * Checks if data exists or if a specific index exists in the data
	 *
	 * @return bool
	 */
	public function has_data($idx = null, $check_empty = false)
	{
    if ( !\is_array($this->data) ){
      return false;
    }
    if ( \is_null($idx) ){
      return !empty($this->data);
    }
    return \bbn\x::has_props($this->data, (array)$idx, $check_empty);
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
    if ( $this->_path ){
      $cn = 'models/'.$this->_path;
      if ( $spec ){
        $cn .= '/'.$spec;
      }
      if ( $data ){
        if (is_array($data)) {
          ksort($data);
        }
        $cn .= '/'.md5(serialize($data));
      }
      return $cn;
    }
  }

  public function set_cache(array $data = null, $spec='', $ttl = 10){
    if ( $this->_path ){
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
      $model =& $this;
      return $this->cache_set_get(function() use($model, $data){
        return $model->get($data);

      }, $cn, '', $ttl);
    }
    return false;
  }

  public function apply_locale($plugin){
    return $this->_mvc->apply_locale($plugin);
  }

}