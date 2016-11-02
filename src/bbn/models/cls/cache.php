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
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * Todo: create a new delegation generic function for the double underscores functions
 */
class cache extends db
{
	protected
		/** @var string */
		$_cache_prefix,
		/** @var $cacher cache */
		$cacher;

	public function __construct(bbn\db $db)
	{
		parent::__construct($db);
		$this->cacher = bbn\cache::get_engine();
		$this->_cache_prefix = bbn\str::encode_filename(str_replace('\\', '/', get_class($this))).'/';
	}

	protected function _cache_name($uid, $method = ''){
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

	public function cache_get($uid, $method = ''){
		return $this->cacher->get($this->_cache_name($uid, $method));
	}

	public function cache_set($uid, $method = '', $data = null, $ttl = 0){
		$this->cacher->set($this->_cache_name($uid, $method), $data, $ttl);
		return $this;
	}

	public function cache_has($uid, $method = ''){
		return $this->cache_get($uid, $method) ? true : false;
	}


}