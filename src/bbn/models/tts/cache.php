<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 19:51
 */

namespace bbn\models\tts;

use bbn;

trait cache
{
  private $_cache_prefix;

  protected $cache_engine;

  protected function cache_init(){
    if ( \is_null($this->cache_engine) ){
      $this->cache_engine = bbn\cache::get_engine();
      $this->_cache_prefix = bbn\str::encode_filename(str_replace('\\', '/', \get_class($this)), true).'/';
    }
  }

  protected function _cache_name($uid, $method = ''){
    return $this->_cache_prefix.'/'.$uid.(empty($method) ? '' : '/'.(string)$method);
  }

  protected function cache_delete_all(){
    $this->cache_engine->delete_all($this->_cache_prefix);
    return $this;
  }

  protected function cache_delete($uid){
    $this->cache_engine->delete_all($this->_cache_name($uid));
    return $this;
  }

  protected function cache_get($uid, $method = ''){
    return $this->cache_engine->get($this->_cache_name($uid, $method));
  }

  protected function cache_set($uid, $method = '', $data = null, $ttl = 0){
    $this->cache_engine->set($this->_cache_name($uid, $method), $data, $ttl);
    return $this;
  }

  protected function cache_has($uid, $method = ''){
    return $this->cache_get($uid, $method) ? true : false;
  }

}
