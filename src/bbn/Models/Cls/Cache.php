<?php
/**
 * @package bbn
 */
namespace bbn\Models\Cls;

use bbn\Cache as CacheCls;
use bbn\Db;
use bbn\Models\Cls\Basic;

use function is_array;
/**
 * Object Class with Db and cache
 *
 *
 * This class implements Basic functions and vars
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Generic classes
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 * Todo: create a new delegation generic function for the double underscores functions
 */
abstract class Cache extends Basic
{
  /** @var string */
	protected string $_cache_prefix;

  /** @var CacheCls $cacher */
  protected CacheCls $cacher;

  public function __construct(protected Db $db)
  {
    $this->cacher = CacheCls::getEngine();
    $this->_cache_prefix = str_replace('\\', '/', \get_class($this)).'/';
  }

	protected function _cache_name($uid, $method = '', string $locale = ''){
    if ( is_array($uid) ){
      $uid = md5(serialize($uid));
    }
    else if ( is_object($uid) ){
      $uid = md5(json_encode($uid));
    }
		return $this->_cache_prefix.(string)$uid.
			(empty($method) ? '' : '-'.(string)$method).
			(empty($locale) ? '' : '-'.(string)$locale);
	}

	public function cacheDeleteAll(){
		$this->cacher->deleteAll($this->_cache_prefix);
		return $this;
	}

	public function cacheDelete($uid){
		$this->cacher->deleteAll($this->_cache_name($uid));
		return $this;
	}

	public function cacheGet($uid, $method = '', $ttl = 0){
		return $this->cacher->get($this->_cache_name($uid, $method), $ttl);
	}

	public function cacheGetLocale($uid, string $locale, $method = '', $ttl = 0){
		return $this->cacher->get($this->_cache_name($uid, $method, $locale), $ttl);
	}

	public function cacheSet($uid, $method = '', $data = null, $ttl = 0){
		$this->cacher->set($this->_cache_name($uid, $method), $data, $ttl);
		return $this;
	}

	public function cacheSetLocale($uid, string $locale, $method = '', $data = null, $ttl = 0){
		$this->cacher->set($this->_cache_name($uid, $method, $locale), $data, $ttl);
		return $this;
	}

	public function cacheGetSet(callable $fn, $uid, $method = '', $ttl = 0){
		$cn = $this->_cache_name($uid, $method);
		return $this->cacher->getSet($fn, $cn, $ttl);
	}

	public function cacheGetSetLocale(callable $fn, $uid, string $locale, $method = '', $ttl = 0){
		$cn = $this->_cache_name($uid, $method, $locale);
		return $this->cacher->getSet($fn, $cn, $ttl);
	}

	public function cacheHas($uid, $method = '', $ttl = 0){

    return $this->cacheGet($uid, $method, $ttl) ? true : false;
  }

	public function cacheHasLocale($uid, string $locale, $method = '', $ttl = 0){

    return $this->cacheGetLocale($uid, $locale, $method, $ttl) ? true : false;
  }
}