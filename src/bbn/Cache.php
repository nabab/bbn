<?php
namespace bbn;

use Exception;
use Memcached;
use Traversable;
use Psr\SimpleCache\CacheInterface;
use bbn\Str;
use bbn\X;
use bbn\Models\Cls\Basic;
use function defined;
use function in_array;
use function is_array;
use function mb_ereg_replace;
use function is_object;
use function is_string;
use function count;
use function strlen;

/**
 * Universal caching class: called once per request, it holds the cache system.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Jan 23, 2016, 23:23:55 +0000
 * @category  Cache
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 */

class Cache extends Basic implements CacheInterface
{
  private string $host;
  private int $port;

  protected $locks = [];

  protected static bool $is_init = false;

  protected static string $type;

  protected static int $max_wait = 50;

  protected static int $default_ttl = 0;

  protected static int $max_ttl;

  protected static string $sep = '/';

  protected static $engine;

  protected string $path;

  protected mixed $obj;

  protected $prefix;


  /**
   * @param string $key
   * @param string $path
   * @return string
   */
  public static function _file(string $key, string $path): string
  {
    return self::_dir($key, $path).'/'.self::_sanitize(X::basename($key)).'.bbn.cache';
  }

  private static function setMaxTtl(): void
  {
    if (!isset(self::$max_ttl)) {
      self::$max_ttl = defined('BBN_MAX_TTL') ? constant('BBN_MAX_TTL') : 90 * 24 * 3600;
    }
  }

  private static function setSeparator(string $sep): void
  {
    self::$sep = $sep;
  }


  /**
   * Makes a unique hash out of whatever value which will be used to check if the value has changed.
   *
   * @param $value
   * @return string The hash
   */
  public static function makeHash($value): string
  {
    if (is_object($value) || is_array($value)) {
      $value = serialize($value);
    }

    return md5($value);
  }


  /**
   * Returns the type of cache engine running in the class.
   *
   * @return string The cache engine
   */
  public static function getType(): ?string
  {
    return self::$type;
  }


  /**
   * Returns the type of cache engine running in the class.
   *
   * @return string The cache engine
   */
  public static function getSeparator(): ?string
  {
    return self::$sep;
  }

  public function getObj() {
    return $this->obj;
  }


  /**
   * Returns a length in seconds based on the given parameter, allowing strings such as xl or s to be given as ttl arguments.
   *
   * @param string|int $ttl
   * @return int The corresponding length in seconds.
   */
  public static function ttl($ttl): int
  {
    if (is_null($ttl)) {
      return self::$default_ttl;
    }

    if (Str::isInteger($ttl)) {
      return (int)$ttl;
    }

    if (is_string($ttl)) {
      switch ($ttl) {
        case 'xxs':
          return 30;
        case 'xs':
          return 60;
        case 's':
          return 300;
        case 'm':
          return 3600;
        case 'l':
          return 3600 * 24;
        case 'xl':
          return 3600 * 24 * 7;
        case 'xxl':
          return 3600 * 24 * 30;
      }
    }

    throw new Exception(X::_("Wrong ttl parameter"));
  }


  /**
   * Returns the cache object (and creates one of the given type if it doesn't exist).
   *
   * @param string $engine
   * @return self
   */
  public static function getCache(?string $engine = null): static
  {
    self::_init($engine);
    return self::$engine;
  }


  /**
   * Alias of get_cache.
   *
   * @param null|string $engine
   * @return self
   */
  public static function getEngine(?string $engine = null): static
  {
    return self::getCache($engine);
  }


    /**
     * Constructor - this is a singleton: it can't be called more then once.
     *
     * @param null|string $engine The type of engine to use
     *
     * @throws Exception
     */
  public function __construct(?string $engine = null)
  {
    self::setMaxTtl();
    /** @todo APC doesn't work */
    $engine = defined('BBN_CACHE_ENGINE') ? constant('BBN_CACHE_ENGINE') : 'files';
    if (self::$is_init) {
      throw new Exception(
        X::_("Only one cache object can be called. Use static function Cache::getEngine()")
      );
    }

    if ((($engine === 'apc')) && function_exists('apcu_clear_cache')) {
      self::_set_type('apc');
    }
    elseif ((($engine === 'redis')) && class_exists("\\Redis")) {
      self::setSeparator(':');
      $this->obj = new \Redis();
      $this->host = defined('BBN_CACHE_HOST') ? constant('BBN_CACHE_HOST') : '127.0.0.1';
      $this->port = defined('BBN_CACHE_PORT') ? constant('BBN_CACHE_PORT') : ((int)(getenv('REDIS_PORT') ?: 6379));
      if ($this->obj->connect($this->host, $this->port, 2.5)) {
        $dbIndex = (int)(getenv('REDIS_DB') ?: 0);
        $this->obj->select($dbIndex);
        $this->prefix = getenv('REDIS_PREFIX') ?: constant('BBN_APP_PREFIX') . self::$sep;
        if ($this->prefix) {
          $this->obj->setOption(\Redis::OPT_PREFIX, $this->prefix);
        }
        self::_set_type('redis');
      }
    }
    elseif ((($engine === 'memcache')) && class_exists("Memcached")) {
      $this->obj = new \Memcached();
      $this->host = defined('BBN_CACHE_HOST') ? constant('BBN_CACHE_HOST') : '127.0.0.1';
      $this->port = defined('BBN_CACHE_PORT') ? constant('BBN_CACHE_PORT') : ((int)(getenv('MEMCACHED_PORT') ?: 11211));
      if ($this->obj->addServer($this->host, $this->port)) {
        self::_set_type('memcache');
      }
    }
    elseif ($this->path = Mvc::getCachePath()) {
      self::_set_type('files');
      $this->obj = new \bbn\File\System();
    }
  }


  /**
   * Checks whether a valid cache exists for the given item.
   *
   * @param string     $key The name of the item
   * @param null|int|string $ttl  The time-to-live value
   * @return bool
   */
  public function hasRaw($key): bool
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return (bool)call_user_func('\\apcu_exists', $key);

        case 'redis':
          return $this->obj->exists($key);

        case 'memcache':
          return (bool)$this->obj->get($key);

        case 'files':
          $file = self::_file($key, $this->path);
          return is_file($file);
      }
    }

    return false;
  }


  public function has($key): bool
  {
    $t = $this->info($key);
    if ($t) {
      $newKey = "{$key}".self::$sep."{$t['version']}";
      if ($this->hasRaw($newKey)) {
        return true;
      }
      else {
        $this->delete($key);
      }
    }

    return false;
  }


  /**
   * Deletes all the cache from the given path or globally if none is given.
   *
   * @param string|null $st The path of the items to delete
   *
   * @return bool|int
   */
  public function deleteAll(?string $st = null): int
  {
    if (!self::$type) {
      return 0;
    }

    $st = trim((string)$st, self::$sep);
    return $this->deleteBranch($st);
  }


  protected function deleteBranch(string $path = ''): int
  {
    $sep = $this->getSeparator();
    $count = 0;

    foreach ($this->getKeys($path) as $child) {
      $fullKey = $path === '' ? $child : $path . $sep . $child;
      $count += $this->deleteBranch($fullKey);
    }

    if ($path !== '') {
      if ($this->delete($path)) {
        $count++;
      }
    }

    $this->deleteKeysIndex($path);

    return $count;
  }
  /*
  public function deleteAll(?string $st = null): int
  {
    if (self::$type === 'files') {
      if ($st === null) {
          $st = '';
      }

      $dir = self::_dir($st, $this->path, false);
      if ($this->obj->isDir($dir)) {
        return $this->obj->delete($dir, $dir === $this->path ? false : true);
      }
      else {
        try {
          $res = $this->obj->delete($dir.'.bbn.cache');
        }
        catch (Exception $e) {
          $res = 0;
        }

        return $res;
      }
    }
    elseif (self::$type) {
      $items = $this->items($st);
      $res   = 0;
      switch (self::$type){
        case 'apc':
          foreach ($items as $item){
            $res += (int)call_user_func('\\apcu_delete', $item);
          }
          break;
        case 'redis':
          $res = count($items) ? $this->obj->unlink(...$items) : 0;
          break;
        case 'memcache':
          if (!$st) {
            $this->obj->flush();
          }

          $res = count($items) ? $this->obj->deleteMulti($items) : 0;
          break;
      }

      return $res;
    }

    return 0;
  }
  */


  /**
   * Deletes all the cache globally.
   *
   * @return self
   */
  public function clear(): bool
  {
    return (bool)$this->deleteAll();
  }


  public function isSame(string $key, mixed $data): bool
  {
    $hash = self::makeHash($data);
    return $hash === $this->hash($key);
  }


  /**
   * Checks whether or not the given item is more recent than the given timestamp.
   *
   * @param string   $key The name of the item
   * @param null|int $time The timestamp to which the item's timestamp will be compared
   * @return bool
   */
  public function isAfter(string $key, int $time): bool
  {
    if ($r = $this->getRaw($key)) {
      if (is_array($r) && isset($r['_bbn_cache'])) {
        return $r['timestamp'] > $time;
      }
    }

    return true;
  }


  /**
   * Returns the cache object (array) as stored.
   *
   * @param string $key The name of the item
   * @param int    $ttl  The cache length
   * @return null|array
   */
  private function getRaw(string $key): mixed
  {
    $t = null;
    switch (self::$type) {
      case 'apc':
        if (!function_exists('\\apcu_exists')) {
          throw new Exception(X::_("The APC extension doesn't seem to be installed"));
        }

        if (call_user_func('\\apcu_exists', $key)) {
          try {
            $t = call_user_func('\\apcu_fetch', $key);
          }
          catch (Exception $e) {
            return null;
          }
        }
        break;
      case 'redis':
        try {
          $tmp = $this->obj->get($key);
        }
        catch (Exception $e) {
          return null;
        }
        if ($tmp) {
          $t = unserialize($tmp);
        }

        break;
      case 'memcache':
        try {
          $tmp = $this->obj->get($key);
          $rc = $this->obj->getResultCode();
        }
        catch (Exception $e) {
          $this->log(X::_("Error while fetching cache for key %s: %s", $key, $e->getMessage()));
          return null;
        }

        if ($rc === \Memcached::RES_SUCCESS) {
          $t = unserialize($tmp);
        }
        break;
      case 'files':
        $file = self::_file($key, $this->path);
        if ($this->obj->isFile($file)) {
          $tmp = $this->obj->getContents($file);
          if ($tmp !== false) {
            $t = unserialize($tmp);
          }
        }
        break;
    }

    return $t;
  }


  /**
   * Returns the cache value, false otherwise.
   *
   * @param string $key The name of the item
   * @param int    $ttl  The cache length
   * @return mixed
   */
  public function get(string $key, $nullValue = null): mixed
  {
    $info = $this->info($key);
    if (!$info || empty($info['version']) || !isset($info['expire'])) {
      return $nullValue;
    }

    if ($info['expire'] <= microtime(true)) {
      $this->delete($key);
      return $nullValue;
    }

    $payloadKey = "{$key}" . self::$sep . "{$info['version']}";
    if (!$this->hasRaw($payloadKey)) {
      $this->delete($key);
      return $nullValue;
    }
    
    return $this->getRaw($payloadKey) ?? $nullValue;
  }


  /**
   * Removes the given item from the cache.
   *
   * @param string $key The name of the item
   * @return bool
   */
  public function delete($key): bool
  {
    if (!$this->setLock($key)) {
      return false;
    }

    try {
      $sep = self::$sep;
      $info = $this->info($key);
      if (!$info || empty($info['version'])) {
        // Best effort cleanup of dangling info key
        $this->deleteRaw("{$key}{$sep}__info");
        return false;
      }

      $payloadKey = "{$key}{$sep}{$info['version']}";
      $infoKey = "{$key}{$sep}__info";
      $resPayload = $this->deleteRaw($payloadKey);
      $resInfo = $this->deleteRaw($infoKey);
      $this->removeKey($key);
      return (bool)($resPayload || $resInfo);
    }
    catch (Exception $e) {
      $this->log(X::_("Error while setting cache for key %s: %s", $key, $e->getMessage()));
      return false;
    }
    finally {
      $this->releaseLock($key);
    }
  }


  /**
   * Removes the given item from the cache.
   *
   * @param string $key The name of the item
   * @return bool
   */
  public function deleteRaw($key): bool
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return call_user_func('\\apcu_delete', $key);
        case 'redis':
          $res = $this->obj->unlink($key);
          return $res;
        case 'memcache':
          return $this->obj->delete($key);
        case 'files':
          $file = self::_file($key, $this->path);
          if ($this->obj->isFile($file)) {
            return (bool)$this->obj->delete($file);
          }
          return false;
      }
    }

    return false;
  }


  public function setRaw($key, $val, $ttl): bool
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          if (!function_exists('\\apcu_store')) {
            throw new Exception(X::_("The APC extension doesn't seem to be installed"));
          }

          return call_user_func('\\apcu_store', $key, $val, $ttl ?: self::$max_ttl);
        case 'redis':
          return $this->obj->set($key, serialize($val), ['ex' => $ttl ?: self::$max_ttl]);
        case 'memcache':
          return $this->obj->set(
            $key, serialize($val), $ttl ?: self::$max_ttl
          );
        case 'files':
          $file = self::_file($key, $this->path);
          if ($this->obj->createPath(X::dirname($file))) {
            if ($this->obj->putContents($file, serialize($val))) {
              return true;
            }
          }
      }
    }

    return false;
  }

  /**
   * Stores the given value in the cache for as long as says the TTL.
   *
   * @param string $key The name of the item
   * @param mixed  $val  The value to be stored in the cache
   * @param int    $ttl  The length in seconds during which the value will be considered as valid
   * @param int    $num  The number of retries
   * @return bool Returns true in case of success false otherwise
   */
  public function set($key, $val, $ttl = null, $num = 0): bool
  {
    if ($this->setLock($key)) {
      try {
        return (bool)$this->commit($key, $val, $ttl);
      }
      catch (Exception $e) {
        $this->log(X::_("Error while setting cache for key %s: %s", $key, $e->getMessage()));
        return false;
      }
      finally {
        $this->releaseLock($key);
      }
    }

    if ($num < self::$max_wait) {
      usleep(10000);
      return $this->set($key, $val, $ttl, $num + 1);
    }

    return false;
  }

  /**
   * Returns the cache for the given item, but if expired or absent creates it before by running the provided function.
   *
   * @param callable $fn   The function which returns the value for the cache
   * @param string   $key The name of the item
   * @param int      $ttl  The cache length
   *
   * @return mixed
   * @throws Exception
   */
  public function getSet(callable $fn, string $key, int $ttl = 0, $timeout = 2): mixed
  {
    $end = microtime(true) + $timeout;
    while (microtime(true) < $end) {
      $existing = $this->get($key, null);
      if (($existing !== null) || $this->has($key)) {
        return $existing;
      }

      if ($this->setLock($key, $timeout)) {
        try {
          // Double-check after lock acquisition
          $existing = $this->get($key, null);
          if (($existing !== null) || $this->has($key)) {
            return $existing;
          }

          $data = $fn();
          $this->commit($key, $data, $ttl);
          return $data;
        }
        catch (Exception $e) {
          $this->log(X::_("Error while building cache for key %s: %s", $key, $e->getMessage()));
          return null;
        }
        finally {
          $this->releaseLock($key);
        }
      }

      usleep(10000);
    }

    $this->log(X::_("Max attempts reached while trying to get cache for key %s", $key));
    return null;
  }


  public function info(string $key): ?array
  {
    $sep = self::$sep;
    $infoKey = "{$key}{$sep}__info";
    return $this->getRaw($infoKey) ?? null;
  }

  /**
   * Returns the hash of the given item.
   *
   * @param string $key The name of the item
   * @return null|string
   */
  public function hash($key): ?string
  {
    if ($r = $this->info($key)) {
      return $r['hash'] ?? null;
    }

    return null;
  }

  /**
   * Returns the timestamp of the given item.
   *
   * @param string $key The name of the item
   * @return null|int
   */
  public function timestamp($key): ?int
  {
    if ($r = $this->info($key)) {
      return $r['timestamp'] ?? null;
    }

    return null;
  }

  public function expire($key): ?float
  {
    if ($r = $this->info($key)) {
      return $r['expire'] ?? null;
    }

    return null;
  }

  public function latest($key): ?string
  {
    if ($r = $this->info($key)) {
      return $r['version'] ?? null;
    }

    return null;
  }

  /**
   * Checks if the value of the item corresponds to the given hash.
   *
   * @param string $key The name of the item
   * @param string $hash A MD5 hash to compare with
   * @return bool Returns true if the hashes are different, false otherwise
   */
  public function isChanged(string $key, $value): bool
  {
    return self::makeHash($value) !== $this->hash($key);
  }


  /**
   * Returns the cache value, false otherwise.
   *
   * @param string $key The name of the item
   * @param int    $ttl  The cache length
   * @return mixed
   */
  public function getFull(string $key): ?array
  {
    return $this->getRaw($key);
  }


  /**
   * @return array|null
   */
  public function stat()
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return call_user_func('\\apcu_cache_info');
        case 'memcache':
          return $this->obj->getStats();
        case 'files':
          return $this->obj->getFiles($this->path);
      }
    }

    return null;
  }


  /**
   * @param string $dir
   * @return array
   */
  public function items(?string $dir = null): array 
  {
    if (self::$type) {
      $emptyDir = empty($dir);
      switch (self::$type){
        case 'apc':
          $all  = call_user_func('\\apcu_cache_info');
          $list = [];
          foreach ($all['cache_list'] as $a){
            array_push($list, $a['info']);
          }

          return $list;

        case 'redis':
          $list = [];
          $it = null;
          $prefixLength = strlen($this->prefix);
          do {
            // Scan for some keys
            $arr_keys = $dir ? $this->obj->scan($it, $this->prefix . $dir . '*') : $this->obj->scan($it);

            // Redis may return empty results, so protect against that
            if ($arr_keys !== FALSE) {
              foreach($arr_keys as $str_key) {
                $list[] = mb_substr($str_key, $prefixLength);
              }
            }
          } while ($it > 0);

          sort($list);
          return $list;

        case 'memcache':
          $list = [];
          $arr  = $this->getAllKeys();
          foreach ($arr as $key){
            if ($emptyDir || (mb_strpos($key, $dir) === 0)) {
              $list[] = $key;
            }
          }

          sort($list);
          return $list;

        case 'files':
          $cache =& $this;
          $list  = array_filter(
            array_map(
              function ($a) use ($dir) {
                return ( $dir ? "$dir/" : '' ).X::basename($a, '.bbn.cache');
              }, $this->obj->getFiles($this->path.($dir ? "/$dir" : ''))
            ),
            function ($a) use ($cache) {
              // Only gives valid cache
              return $cache->has($a);
            }
          );
          $dirs  = $this->obj->getDirs($this->path.($dir ? "/$dir" : ''));
          if (count($dirs)) {
            foreach ($dirs as $d){
              $res = $this->items($dir ? $dir.self::$sep.X::basename($d) : X::basename($d));
              foreach ($res as $r){
                array_push($list, $r);
              }
            }
          }

          return $list;
      }
    }

    return [];
  }


  public function getMultiple($keys, $default = null): Traversable|array
  {
    if (!is_iterable($keys)) {
      throw new Exception("Keys must be iterable");
    }

    $res = [];
    foreach ($keys as $k) {
      $res[$k] = $this->has($k) ? $this->get($k) : $default;
    }

    return $res;
  }


  public function setMultiple($values, $ttl = null): bool
  {
    foreach ($values as $k => $v) {
      if (!$this->set($k, $v, $ttl)) {
        return false;
      }
    }

    return true;
  }


  public function deleteMultiple($keys): bool
  {
    if (!is_iterable($keys)) {
      throw new Exception("Keys must be iterable");
    }

    foreach ($keys as $k) {
      if (!$this->delete($k)) {
        return false;
      }
    }

    return true;
  }


  public function browse(string $path = ''): array
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          $all  = call_user_func('\\apcu_cache_info');
          $list = [];
          foreach ($all['cache_list'] as $a){
            array_push($list, $a['info']);
          }

          return $list;
        case 'redis':
          $keys = $this->items($path);
          $list = [];
          $done = [];
          foreach ($keys as $i => $k){
            $bits = X::split($k, self::$sep);
            $idx = 0;
            if (empty($path)) {
              $name = $bits[0];
            }
            elseif (mb_strpos($k, $path) === 0) {
              $idx = count(X::split(trim($path, self::$sep), self::$sep));
              $name = $bits[$idx];
            }
            else {
              continue;
            }

            if ($name) {
              $fullName = trim(trim($path, self::$sep) . self::$sep . $name, self::$sep);
              $num = 0;
              if ($isFolder = $k !== $fullName) {
                $num++;
                $name .= self::$sep;
                $fullName .= self::$sep;
              }
              if (in_array($name, $done)) {
                continue;
              }

              $done[] = $name;
              if ($isFolder) {
                $subdone = [];
                $num += $isFolder ? count(array_filter(
                  $keys,
                  function($a, $j) use ($i, $fullName, $idx, &$subdone) {
                    if (($i !== $j) && (strpos($a, $fullName) === 0)) {
                      $bits = X::split($a, self::$sep);
                      $subname = $bits[$idx+1];
                      if (!in_array($subname, $subdone)) {
                        $subdone[] = $subname;
                        return true;
                      }
                    }

                    return false;
                  },
                  ARRAY_FILTER_USE_BOTH 
                )) : 0;
              }

              //X::dump($name, $k, $path, $num, $bits);
              $list[] = [
                'text' => $name,
                'key' => $k,
                'nodePath' => $fullName,
                'items'=> [],
                'num' => $num,
                'path' => array_slice(X::split($fullName, self::$sep), 0, -1),
                'folder' => $isFolder
              ];
            }
            //X::ddump(count($list));
          }

          return $list;
        case 'memcache':
          $keys = $this->getAllKeys();
          $list = [];
          $done = [];
          foreach ($keys as $i => $k){
            $bits = X::split($k, self::$sep);
            $idx = 0;
            if (empty($path)) {
              $name = $bits[0];
            }
            elseif (mb_strpos($k, $path) === 0) {
              $idx = count(X::split(trim($path, self::$sep), self::$sep));
              $name = $bits[$idx];
            }
            else {
              continue;
            }

            if ($name) {
              $fullName = trim(trim($path, self::$sep) . self::$sep . $name, self::$sep);
              $num = 0;
              if ($isFolder = $k !== $fullName) {
                $num++;
                $name .= self::$sep;
                $fullName .= self::$sep;
              }
              if (in_array($name, $done)) {
                continue;
              }

              $done[] = $name;
              if ($isFolder) {
                $subdone = [];
                $num += $isFolder ? count(array_filter(
                  $keys,
                  function($a, $j) use ($i, $fullName, $idx, &$subdone) {
                    if (($i !== $j) && (strpos($a, $fullName) === 0)) {
                      $bits = X::split($a, self::$sep);
                      $subname = $bits[$idx+1];
                      if (!in_array($subname, $subdone)) {
                        $subdone[] = $subname;
                        return true;
                      }
                    }

                    return false;
                  },
                  ARRAY_FILTER_USE_BOTH 
                )) : 0;
              }

              //X::dump($name, $k, $path, $num, $bits);
              $list[] = [
                'text' => $name,
                'key' => $k,
                'nodePath' => $fullName,
                'items'=> [],
                'num' => $num,
                'path' => X::split(dirname($fullName), self::$sep),
                'folder' => $isFolder
              ];
            }
            //X::ddump(count($list));
          }

          return $list;
        case 'files':
          $this->obj->cd($this->path);
          $content = $this->obj->getFiles($path ? "/$path" : '', true);
          $all = [];
          if (!empty($content)) {
            foreach ($content as $nodePath) {
              $arr = X::split($nodePath, '/');
              $element = array_last($arr);
              $ele =  [
                'text' => $element,
                //'path' => [],
                'nodePath' => $nodePath,
                'items'=> [],
                'num' => $this->obj->isDir($nodePath) ? count($this->obj->getFiles($nodePath, true)) : 0,
                'folder' => $this->obj->isDir($nodePath)
              ];
        
        
              if ($this->obj->isDir($nodePath)) {
                $paths = $element !== $nodePath ? X::split($nodePath, '/') : [];
                $ele['path'] = count($paths) ? array_splice($paths, 0, count($paths) - 1) : $paths;
              }

              array_push($all, $ele);
            }
          }

          $this->obj->back();
          return $this->obj->getFiles($this->path.($path ? "/$path" : ''));
      }
    }

    return [];
  }

  protected function commit($key, $val, $ttl): ?string
  {
    $this->addKey($key);
    $ttl  = self::ttl($ttl);
    $realTtl = $ttl ?: self::$max_ttl;
    $sep = self::$sep;
    $infoKey = "{$key}{$sep}__info";
    $current = $this->getRaw($infoKey);
    $hash = self::makeHash($val);
    $nowSec = time();
    $now = microtime(true);
    $next = "{$nowSec}|1";
    $oldVersion = null;
    $num = 0;
    if ($current) {
      $num = (int)($current['num'] ?? 0);
      if (($current['hash'] ?? null) === $hash && !empty($current['version'])) {
        // Same value, keep same version but refresh actual payload TTL too
        $next = $current['version'];
      }
      else {
        $oldVersion = $current['version'] ?? null;
        if ($oldVersion) {
          [$oldTime, $oldNum] = X::split($oldVersion, '|');
          $versionNum = ((int)$oldNum) + (($oldTime == $nowSec) ? 1 : 0);
          $next = "{$nowSec}|{$versionNum}";
        }
      }

      $num = $current['num'];
    }

    $payloadKey = "{$key}{$sep}{$next}";
    $info = [
      'num' => $num + 1,
      'hash' => $hash,
      'timestamp' => $now,
      'expire' => $now + $realTtl,
      'ttl' => $ttl,
      'version' => $next
    ];

    // Always write payload so TTL stays in sync with metadata
    if (!$this->setRaw($payloadKey, $val, $ttl)) {
      return null;
    }

    if (!$this->setRaw($infoKey, $info, 0)) {
      return null;
    }

    if ($oldVersion && ($oldVersion !== $next)) {
      $this->deleteRaw("{$key}{$sep}{$oldVersion}");
    }

    return $next;
  }

  protected function getLockKey(string $key): string
  {
    $sep = self::$sep;
    return "lock{$sep}{$key}";
  }

  public function hasLock($key): bool
  {
    return $this->hasRaw($this->getLockKey($key));
  }

  protected function setLock($key, $length = 2): bool
  {
    $lockKey = $this->getLockKey($key);
    switch (self::$type) {
      case 'apc':
        if (!function_exists('\\apcu_add')) {
          throw new Exception(X::_("The APC extension doesn't seem to be installed"));
        }

        return call_user_func('\\apcu_add', $lockKey, '1', $length);

      case 'redis':
        $token = bin2hex(random_bytes(16));

        $ok = $this->obj->set($lockKey, $token, ['nx', 'ex' => $length]);
        if ($ok) {
          $this->locks[$lockKey] = $token;
          return true;
        }

        return false;

      case 'memcache':
        return $this->obj->add($lockKey, '1', $length);

      case 'files':
        // Best effort only, not truly atomic across processes
        if ($this->hasRaw($lockKey)) {
          return false;
        }

        return $this->setRaw($lockKey, '1', $length);
    }

    return false;
  }

  protected function releaseLock($key): bool
  {
    $lockKey = $this->getLockKey($key);
    switch (self::$type) {
      case 'redis':
        if (isset($this->locks[$lockKey])) {
          $token = $this->locks[$lockKey];
          $script = '
            if redis.call("get", KEYS[1]) == ARGV[1] then
              return redis.call("del", KEYS[1])
            else
              return 0
            end
          ';
          $result = $this->obj->eval($script, [$lockKey, $token], 1);
          unset($this->locks[$lockKey]);
          return $result === 1;
        }
        return false;
      default:
        return $this->deleteRaw($lockKey);
    }

    return $this->deleteRaw($lockKey);
  }

  protected function getKeys(string $path = ''): array
  {
    $sep = $this->getSeparator();
    $indexKey = $path === '' ? '__keys' : $path . $sep . '__keys';
    return $this->getRaw($indexKey) ?: [];
  }

  protected function deleteKeysIndex(string $path = ''): bool
  {
    $sep = $this->getSeparator();
    $indexKey = $path === '' ? '__keys' : $path . $sep . '__keys';
    return $this->deleteRaw($indexKey);
  }

  protected function addKey(string $key): void
  {
    $sep = $this->getSeparator();
    $bits = X::split($key, $sep);

    if (!count($bits)) {
      return;
    }

    $rootChild = $bits[0];
    $rootIndexes = $this->getKeys('');
    if (!in_array($rootChild, $rootIndexes, true)) {
      $rootIndexes[] = $rootChild;
      $this->setRaw('__keys', $rootIndexes, 0);
    }

    while (count($bits) > 1) {
      $child = array_pop($bits);
      $cur = X::join($bits, $sep);
      $indexes = $this->getKeys($cur);

      if (!in_array($child, $indexes, true)) {
        $indexes[] = $child;
        $indexKey = $cur . $sep . '__keys';
        $this->setRaw($indexKey, $indexes, 0);
      }
    }
  }

  protected function removeKey(string $key): void
  {
    $sep = $this->getSeparator();
    $bits = X::split($key, $sep);

    if (!count($bits)) {
      return;
    }

    $fullBits = $bits;
    while (count($bits) > 1) {
      $child = array_pop($bits);
      $cur = X::join($bits, $sep);
      $indexes = $this->getKeys($cur);

      $pos = array_search($child, $indexes, true);
      if ($pos !== false) {
        array_splice($indexes, $pos, 1);
        $indexKey = $cur . $sep . '__keys';
        if (count($indexes)) {
          $this->setRaw($indexKey, $indexes, 0);
          break;
        }
        else {
          $this->deleteRaw($indexKey);
        }
      }
      else {
        break;
      }
    }

    $rootChild = $fullBits[0];
    $rootIndexes = $this->getKeys('');
    $pos = array_search($rootChild, $rootIndexes, true);
    if ($pos !== false) {
      array_splice($rootIndexes, $pos, 1);
      if (count($rootIndexes)) {
        $this->setRaw('__keys', $rootIndexes, 0);
      }
      else {
        $this->deleteRaw('__keys');
      }
    }
  }


  protected function deleteByIndex(string $path = ''): int
  {
    $sep = $this->getSeparator();
    $count = 0;
    $children = $this->getKeys($path);

    foreach ($children as $child) {
      $fullKey = $path === '' ? $child : $path . $sep . $child;
      $subChildren = $this->getKeys($fullKey);

      if (count($subChildren)) {
        $count += $this->deleteByIndex($fullKey);
        $this->deleteKeysIndex($fullKey);
      }

      if ($this->delete($fullKey)) {
        $count++;
      }
    }

    if ($path === '') {
      $this->deleteKeysIndex('');
    }

    return $count;
  }


  private function getAllKeys() {
    $sock = fsockopen($this->host, $this->port, $errno, $errstr);
    if ($sock === false) {
        throw new Exception("Error connection to server {$this->host} on port {$this->port}: ({$errno}) {$errstr}");
    }

    if (fwrite($sock, "stats items\n") === false) {
        throw new Exception("Error writing to socket");
    }

    $slabCounts = [];
    while (($line = fgets($sock)) !== false) {
        $line = trim($line);
        if ($line === 'END') {
            break;
        }

        // STAT items:8:number 3
        if (preg_match('!^STAT items:(\d+):number (\d+)$!', $line, $matches)) {
            $slabCounts[$matches[1]] = (int)$matches[2];
        }
    }

    foreach ($slabCounts as $slabNr => $slabCount) {
        if (fwrite($sock, "lru_crawler metadump {$slabNr}\n") === false) {
            throw new Exception('Error writing to socket');
        }

        $count = 0;
        while (($line = fgets($sock)) !== false) {
            $line = trim($line);
            if ($line === 'END') {
                break;
            }

            // key=foobar exp=1596440293 la=1596439293 cas=8492 fetch=no cls=24 size=14908
            if (preg_match('!^key=(\S+)!', $line, $matches)) {
                $name = urldecode($matches[1]);
                if ($this->has($name)) {
                  $allKeys[] = $name;
                  $count++;
                }
            }
        }

//        if ($count !== $slabCount) {
//            throw new Exception("Surprise, got {$count} keys instead of {$slabCount} keys");
//        }
    }

    if (fclose($sock) === false) {
        throw new Exception('Error closing socket');
    }
    
    return $allKeys;
  }

  /**
   * @param ?string $engine
   * @return int
   */
  private static function _init(?string $engine = null): int
  {
    if (!self::$is_init) {
      self::$engine  = new Cache($engine);
      self::$is_init = 1;
    }

    return 1;
  }


  /**
   * @param string $type
   */
  private static function _set_type(string $type): void
  {
    self::$type = $type;
  }


  private static function _sanitize($st)
  {
    $st = mb_ereg_replace("([^\w\s\d\-_~,;\/\[\]\(\).])", '', $st);
    $st = mb_ereg_replace("([\.]{2,})", '', $st);
    return $st;
  }


  /**
   * @param string $dir
   * @param string $path
   * @param bool   $parent
   * @return string
   */
  private static function _dir(string $dir, string $path, $parent = true): string
  {
    if ($parent) {
      $dir = X::dirname($dir);
    }

    if (empty($dir)) {
      return $path;
    }
    elseif (Str::sub($dir, -1) === '/') {
      $dir = Str::sub($dir, 0, -1);
    }

    return self::_sanitize(
      str_replace(
        '../',
        '',
        str_replace(
          '\\',
          '/',
          str_replace('//', '/', $path.$dir)
        )
      )
    );
  }


}
