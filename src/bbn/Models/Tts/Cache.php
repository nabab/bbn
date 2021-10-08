<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 19:51
 */

namespace bbn\Models\Tts;

use bbn;

trait Cache
{
  private $_cache_prefix;

  protected $cache_engine;

  protected function cacheInit(){
    if ( \is_null($this->cache_engine) ){
      $this->cache_engine = bbn\Cache::getEngine();
      $this->_cache_prefix = bbn\Str::encodeFilename(str_replace('\\', '/', \get_class($this)), true).'/';
    }
  }

  protected function _cache_name($uid, $method = ''){
    $uid = (string)$uid;
    $path = \bbn\Str::isUid($uid) ? substr($uid, 0, 3).'/'.substr($uid, 3, 3).'/'.substr($uid, 6) : $uid;
    return $this->_cache_prefix.$path.(empty($method) ? '' : '/'.(string)$method);
  }

  protected function cacheDeleteAll(){
    $this->cache_engine->deleteAll($this->_cache_prefix);
    return $this;
  }

  protected function cacheDelete($uid, $method = ''){
    $this->cache_engine->deleteAll($this->_cache_name($uid, $method));
    return $this;
  }

  protected function cacheGet($uid, $method = ''){
    return $this->cache_engine->get($this->_cache_name($uid, $method));
  }

  protected function cacheSet($uid, $method = '', $data = null, $ttl = 0){
    $this->cache_engine->set($this->_cache_name($uid, $method), $data, $ttl);
    return $this;
  }

  protected function cacheGetSet(callable $fn, $uid, $method = '', $ttl = 0){
    return $this->cache_engine->getSet($fn, $this->_cache_name($uid, $method), $ttl);
  }

  protected function cacheHas($uid, $method = ''){
    return $this->cacheGet($uid, $method) ? true : false;
  }

  protected function serializeFunction(callable $function)
  {
    return $this->cache_engine->serializeFunction($function);
  }
}