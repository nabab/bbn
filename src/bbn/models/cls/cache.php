<?php
/**
 * @package bbn
 */
namespace bbn\models\cls;
use bbn;
/**
 * Object Class with Db and cache
 *
 *
 * This class implements basic functions and vars
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Generic classes
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 * Todo: create a new delegation generic function for the double underscores functions
 */
abstract class cache extends bbn\models\cls\basic
{
	protected
    /**
     * @var bbn\db
     */
    $db,
		/** @var string */
		$_cache_prefix,
		/** @var $cacher cache */
		$cacher;

  public function __construct(bbn\db $db){
    $this->db = $db;
		$this->cacher = bbn\cache::get_engine();
		$this->_cache_prefix = str_replace('\\', '/', \get_class($this)).'/';
	}

	protected function _cache_name($uid, $method = ''){
    if ( is_array($uid) ){
      $uid = md5(serialize($uid));
    }
    else if ( is_object($uid) ){
      $uid = md5(json_encode($uid));
    }
		return $this->_cache_prefix.(string)$uid.
			(empty($method) ? '' : '-'.(string)$method);
	}

	public function cache_delete_all(){
		$this->cacher->delete_all($this->_cache_prefix);
		return $this;
	}

	public function cache_delete($uid){
		$this->cacher->delete_all($this->_cache_name($uid));
		return $this;
	}

	public function cache_get($uid, $method = '', $ttl = 0){
		return $this->cacher->get($this->_cache_name($uid, $method), $ttl);
	}

	public function cache_set($uid, $method = '', $data = null, $ttl = 0){
		$this->cacher->set($this->_cache_name($uid, $method), $data, $ttl);
		return $this;
	}

	public function cache_get_set(callable $fn, $uid, $method = '', $ttl = 0){
		$cn = $this->_cache_name($uid, $method);
		return $this->cacher->get_set($fn, $cn, $ttl);
	}

	public function cache_has($uid, $method = '', $ttl = 0){

    return $this->cache_get($uid, $method, $ttl) ? true : false;
  }
}