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
      $sep = CacheCls::getSeparator();
      $this->_cache_prefix = str_replace('\\', $sep, \get_class($this)).$sep;
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
    $this->cacheInit();
    $uid  = (string)$uid;
    $sep = CacheCls::getSeparator();
    $path = Str::isUid($uid) ? Str::sub($uid, 0, 3).$sep . Str::sub($uid, 3, 3).$sep . Str::sub($uid, 6) : $uid;
    return $this->_cache_prefix.$path.(empty($method) ? '' : $sep.(string)$method).(empty($locale) ? '' : "-$locale");
  }

  protected function cacheGetKeys(string $path = ''): array
  {
    $sep = CacheCls::getSeparator();
    return $this->cache_engine->get($path.$sep.'__keys') ?: [];
  }

  protected function cacheSetKey(string $key): void
  {
    $sep = CacheCls::getSeparator();
    $bits = X::split($key, $sep);
    $key = array_pop($bits);
    $cur = '';
    foreach ($bits as $i => $bit) {
      $cur .= $bit.$sep;
      $indexes = $this->cache_engine->get($cur.'__keys') ?: [];
      if (!in_array($bits[$i+1] ?? $key, $indexes)) {
        $indexes[] = $bits[$i+1] ?? $key;
        $this->cache_engine->set($cur.'__keys', $indexes);
      }
    }
  }

  protected function cacheUnsetKey(string $key): void
  {
    $sep = CacheCls::getSeparator();
    $bits = X::split($key, $sep);
    $cur = '';
    $key = array_pop($bits);
    while (count($bits)) {
      $cur = X::join($bits, $sep).$sep;
      $indexes = $this->cache_engine->get($cur.'__keys') ?: [];
      if (in_array($key, $indexes)) {
        array_splice($indexes, array_search($key, $indexes), 1);
        if (!count($indexes)) {
          $this->cache_engine->delete($cur.'__keys');
        }
        else {
          $this->cache_engine->set($cur.'__keys', $indexes);
          break;
        }
      }

      $key = array_pop($bits);
    }
  }


  /**
   * Deletes all the cache related to the current class
   *
   * @return self
   */
  protected function cacheDeleteAll(): static
  {
    $this->cacheInit();
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
  protected function cacheDelete(string $uid, string $method = ''): static
  {
    $this->cacheInit();
    $root = $this->_cache_name($uid);
    $sep = CacheCls::getSeparator();
    if (!$method) {
      if ($keys = $this->cacheGetKeys($root)) {
        foreach ($keys as $k) {
          $this->cache_engine->delete($root.$sep.$k);
          $this->cacheUnsetKey($root.$sep.$k);
        }
      }
    }
    else {
      $cn = $this->_cache_name($uid, $method);
      $this->cacheUnsetKey($cn);
      $this->cache_engine->delete($cn);
    }
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
  protected function cacheDeleteLocale(string $uid, string $locale, string $method = ''): static
  {
    $this->cacheInit();
    $root = $this->_cache_name($uid);
    $sep = CacheCls::getSeparator();
    if (!$method) {
      if ($keys = $this->cacheGetKeys($root)) {
        foreach ($keys as $k) {
          $this->cache_engine->delete($root.$sep.$k);
          $this->cacheUnsetKey($root.$sep.$k);
        }
      }
    }
    else {
      $cn = $this->_cache_name($uid, $method, $locale);
      $this->cache_engine->delete($cn);
    }
    return $this;
  }


  /**
   * Gets the hash from a cache key
   *
   * @param string $uid
   * @param string $method
   * @return mixed
   */
  protected function cacheHash(string $uid, string $method = ''): mixed
  {
    $this->cacheInit();
    return $this->cache_engine->hash($this->_cache_name($uid, $method));
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
    $this->cacheInit();
    return $this->cache_engine->get($this->_cache_name($uid, $method));
  }

  /**
   * Gets the cached data
   *
   * @param string $uid
   * @param string $method
   * @return mixed
   */
  protected function cacheGetRaw(string $uid, string $method = ''): mixed
  {
    $this->cacheInit();
    return $this->cache_engine->getFull($this->_cache_name($uid, $method));
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
    $this->cacheInit();
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
    $this->cacheInit();
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
  protected function cacheSet(string $uid, string $method = '', $data = null, int $ttl = 0): static
  {
    $this->cacheInit();
    $cn = $this->_cache_name($uid, $method);
    $this->cacheSetKey($cn);
    $this->cache_engine->set($cn, $data, $ttl);
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
  protected function cacheSetLocale(string $uid, string $locale, string $method = '', $data = null, int $ttl = 0): static
  {
    $this->cacheInit();
    $cn = $this->_cache_name($uid, $method, $locale);
    $this->cacheSetKey($cn);
    $this->cache_engine->set($cn, $data, $ttl);
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
    $this->cacheInit();
    $cn = $this->_cache_name($uid, $method);
    $this->cacheSetKey($cn);
    return $this->cache_engine->getSet($fn, $cn, $ttl);
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
    $this->cacheInit();
    $cn = $this->_cache_name($uid, $method, $locale);
    $this->cacheSetKey($cn);
    return $this->cache_engine->getSet($fn, $cn, $ttl);
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
    $this->cacheInit();
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
    $this->cacheInit();
    return $this->cacheGetLocale($uid, $locale, $method) ? true : false;
  }
}