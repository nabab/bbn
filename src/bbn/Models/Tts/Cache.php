<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 19:51
 */

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Cache as CacheCls;

trait Cache
{
  private $_cache_prefix;

  protected ?CacheCls $cache_engine = null;

  /**
   * Initializes the cache object, must be called in __construct
   *
   * @return void
   */
  protected function cacheInit(): void
  {
    if ( \is_null($this->cache_engine) ){
      $this->cache_engine = CacheCls::getEngine();
      $this->_cache_prefix = Str::encodeFilename(str_replace('\\', '/', \get_class($this)), true).'/';
    }
  }


  /**
   * Throws an exception if the cache has not been initialized
   *
   * @return void
   */
  protected function cacheCheck(): void
  {
    if (!$this->cache_engine) {
      throw new Exception(X::_("The cache object has not been initialized"));
    }
  }


  /**
   * Generates a name for the cache based on the class name and the method called - or a gievn string
   *
   * @param [type] $uid
   * @param string $method
   * @param string $locale
   * @return string
   */
  protected function _cache_name($uid, $method = '', string $locale = ''): string
  {
    $uid  = (string)$uid;
    $path = Str::isUid($uid) ? Str::sub($uid, 0, 3).'/' . Str::sub($uid, 3, 3).'/' . Str::sub($uid, 6) : $uid;
    return $this->_cache_prefix.$path.(empty($method) ? '' : '/'.(string)$method).(empty($locale) ? '' : "-$locale");
  }


  /**
   * Deletes all the cache related to the current class
   *
   * @return self
   */
  protected function cacheDeleteAll(): self
  {
    $this->cacheCheck();
    $this->cache_engine->deleteAll($this->_cache_prefix);
    return $this;
  }


  /**
   * Deletes the given cache
   *
   * @param string $uid
   * @param string $method
   * @return self
   */
  protected function cacheDelete(string $uid, string $method = ''): self
  {
    $this->cacheCheck();
    $this->cache_engine->deleteAll($this->_cache_name($uid, $method));
    return $this;
  }


  /**
   * Deletes the given cache for a specific locale
   *
   * @param string $uid
   * @param string $locale
   * @param string $method
   * @return self
   */
  protected function cacheDeleteLocale(string $uid, string $locale, string $method = ''): self
  {
    $this->cacheCheck();
    $this->cache_engine->deleteAll($this->_cache_name($uid, $method, $locale));
    return $this;
  }


  /**
   * Gets the cached data
   *
   * @param string $uid
   * @param string $method
   * @return mixed
   */
  protected function cacheGet(string $uid, string $method = ''): mixed
  {
    $this->cacheCheck();
    return $this->cache_engine->get($this->_cache_name($uid, $method));
  }

  /**
   * Gets the cached data
   *
   * @param string $uid
   * @param string $method
   * @return ?array
   */
  protected function cacheGetFull(string $uid, string $method = ''): ?array
  {
    $this->cacheCheck();
    return $this->cache_engine->getFull($this->_cache_name($uid, $method));
  }


  /**
   * Gets the cached data for a specific locale
   *
   * @param string $uid
   * @param string $locale
   * @param string $method
   * @return mixed
   */
  protected function cacheGetLocale(string $uid, string $locale, string $method = ''): mixed
  {
    $this->cacheCheck();
    return $this->cache_engine->get($this->_cache_name($uid, $method, $locale));
  }


  /**
   * Sets the cache
   *
   * @param string $uid
   * @param string $method
   * @param array|null $data
   * @param integer $ttl
   * @return self
   */
  protected function cacheSet(string $uid, string $method = '', $data = null, int $ttl = 0): self
  {
    $this->cacheCheck();
    $this->cache_engine->set($this->_cache_name($uid, $method), $data, $ttl);
    return $this;
  }


  /**
   * Sets the cache for a specific locale
   *
   * @param string $uid
   * @param string $locale
   * @param string $method
   * @param array|null $data
   * @param integer $ttl
   * @return self
   */
  protected function cacheSetLocale(string $uid, string $locale, string $method = '', $data = null, int $ttl = 0): self
  {
    $this->cacheCheck();
    $this->cache_engine->set($this->_cache_name($uid, $method, $locale), $data, $ttl);
    return $this;
  }


  /**
   * Gets the cache or creates it if needs to
   *
   * @param callable $fn
   * @param string $uid
   * @param string $method
   * @param integer $ttl
   * @return mixed
   */
  protected function cacheGetSet(callable $fn, string $uid, $method = '', int $ttl = 0): mixed
  {
    $this->cacheCheck();
    return $this->cache_engine->getSet($fn, $this->_cache_name($uid, $method), $ttl);
  }


  /**
   * Gets the cache for a specific locale or creates it if needs to
   *
   * @param callable $fn
   * @param string $uid
   * @param string $locale
   * @param string $method
   * @param integer $ttl
   * @return mixed
   */
  protected function cacheGetSetLocale(callable $fn, string $uid, string $locale, $method = '', int $ttl = 0): mixed
  {
    $this->cacheCheck();
    return $this->cache_engine->getSet($fn, $this->_cache_name($uid, $method, $locale), $ttl);
  }


  /**
   * Checks whether the cache exists and is valid
   *
   * @param string $uid
   * @param string $method
   * @return boolean
   */
  protected function cacheHas(string $uid, string $method = ''): bool
  {
    $this->cacheCheck();
    return $this->cacheGet($uid, $method) ? true : false;
  }


  /**
   * Checks whether the cache exists and is valid
   *
   * @param string $uid
   * @param string $method
   * @return boolean
   */
  protected function cacheHasLocale(string $uid, string $locale, string $method = ''): bool
  {
    $this->cacheCheck();
    return $this->cacheGetLocale($uid, $locale, $method) ? true : false;
  }
}