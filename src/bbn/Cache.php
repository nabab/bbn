<?php
namespace bbn;

use bbn\Str;
use Exception;
use Memcached;
use Traversable;
use Psr\SimpleCache\CacheInterface;
use function defined;
use function in_array;
use function is_array;
use function mb_ereg_replace;
use function is_object;
use function is_string;
use function count;

/**
 * Universal caching class: called once per request, it holds the cache system.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Jan 23, 2016, 23:23:55 +0000
 * @category  Cache
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 */

class Cache implements CacheInterface
{
  private string $host;
  private int $port;
  protected static $is_init = false;

  protected static $type = null;

  protected static $max_wait = 10;

  protected static $default_ttl = 0;

  protected static $engine;

  protected $path;

  protected $obj;

  protected $fs;

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
  public static function getCache(?string $engine = null): self
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
  public static function getEngine(?string $engine = null): self
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
    elseif ((($engine === 'redis')) && class_exists("Redis")) {
      $this->obj = new \Redis();
      $this->host = defined('BBN_CACHE_HOST') ? constant('BBN_CACHE_HOST') : '172.18.0.2';
      $this->port = defined('BBN_CACHE_PORT') ? constant('BBN_CACHE_PORT') : ((int)(getenv('MEMCACHED_PORT') ?: 11211));
      if ($this->obj->connect($this->host, $this->port, 2.5)) {
        $dbIndex = (int)(getenv('REDIS_DB') ?: 0);
        $this->obj->select($dbIndex);
        $this->prefix = getenv('REDIS_PREFIX') ?: constant('BBN_APP_NAME') . '/';
        if ($this->prefix) {
          $this->obj->setOption(\Redis::OPT_PREFIX, $this->prefix);
        }
        self::_set_type('redis');
      }
    }
    elseif ((($engine === 'memcache')) && class_exists("Memcached")) {
      $this->obj = new \Memcached();
      $this->host = defined('BBN_CACHE_HOST') ? constant('BBN_CACHE_HOST') : '172.18.0.2';
      $this->port = defined('BBN_CACHE_PORT') ? constant('BBN_CACHE_PORT') : ((int)(getenv('MEMCACHED_PORT') ?: 11211));
      if ($this->obj->addServer($this->host, $this->port)) {
        self::_set_type('memcache');
      }
    }
    elseif ($this->path = Mvc::getCachePath()) {
      self::_set_type('files');
      $this->fs = new \bbn\File\System();
    }
  }


  /**
   * Checks whether a valid cache exists for the given item.
   *
   * @param string     $key The name of the item
   * @param null|int|string $ttl  The time-to-live value
   * @return bool
   */
  public function has($key, null|int|string $ttl = null): bool
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return (bool)call_user_func('\\apcu_exists', $key);

        case 'redis':
          $t = $this->getRaw($key);
          if ($t) {
            if ((!$ttl || !isset($t['ttl']) || ($ttl === $t['ttl']))
                && (!$t['expire'] || ($t['expire'] > time()))
            ) {
              return true;
            }

            $this->delete($key);
          }

          return false;

        case 'memcache':
          $t = $this->getRaw($key);
          if ($t) {
            if ((!$ttl || !isset($t['ttl']) || ($ttl === $t['ttl']))
                && (!$t['expire'] || ($t['expire'] > time()))
            ) {
              return true;
            }

            $this->delete($key);
          }

          return false;

        case 'files':
          $file = self::_file($key, $this->path);
          if (($content = $this->fs->getContents($file))
              && ($t = json_decode($content, true))
          ) {
            if ((!$ttl || !isset($t['ttl']) || ($ttl === $t['ttl']))
                && (!$t['expire'] || ($t['expire'] > time()))
            ) {
              return true;
            }

            $this->fs->delete($file);
          }
          return false;
      }
    }

    return false;
  }


  /**
   * Removes the given item from the cache.
   *
   * @param string $key The name of the item
   * @return bool
   */
  public function delete($key): bool
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return call_user_func('\\apcu_delete', $key);
        case 'redis':
          return $this->obj->unlink($key);
        case 'memcache':
          return $this->obj->delete($key);
        case 'files':
          $file = self::_file($key, $this->path);
          if ($this->fs->isFile($file)) {
            return (bool)$this->fs->delete($file);
          }
          return false;
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
    if (self::$type === 'files') {
      if ($st === null) {
          $st = '';
      }

      $dir = self::_dir($st, $this->path, false);
      if ($this->fs->isDir($dir)) {
        return $this->fs->delete($dir, $dir === $this->path ? false : true);
      }
      else {
        try {
          $res = $this->fs->delete($dir.'.bbn.cache');
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


  /**
   * Deletes all the cache globally.
   *
   * @return self
   */
  public function clear(): bool
  {
    return (bool)$this->deleteAll();
  }


  /**
   * Returns the timestamp of the given item.
   *
   * @param string $key The name of the item
   * @return null|int
   */
  public function timestamp(string $key): ?int
  {
    if ($r = $this->getRaw($key)) {
      return $r['timestamp'];
    }

    return null;
  }


  /**
   * Returns the hash of the given item.
   *
   * @param string $key The name of the item
   * @return null|string
   */
  public function hash(string $key): ?string
  {
    if ($r = $this->getRaw($key)) {
      return $r['hash'];
    }

    return null;
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
  public function isNew(string $key, ?int $time = null): bool
  {
    if (!$time) {
      return false;
    }

    if ($r = $this->getRaw($key)) {
      return $r['timestamp'] > $time;
    }

    return true;
  }

  public function setRaw($key, $val, $ttl): bool
  {
    if (self::$type) {
      $ttl  = self::ttl($ttl);
      $hash = self::makeHash($val);
      switch (self::$type){
        case 'apc':
          if (!function_exists('\\apcu_store')) {
            throw new Exception(X::_("The APC extension doesn't seem to be installed"));
          }

          return call_user_func('\\apcu_store', $key, $val, $ttl);
        case 'redis':
          if ($ttl) {
            return $this->obj->set(
              $key, json_encode($val)
            );
          }
          else {
            return $this->obj->set($key, json_encode($val));
          }
        case 'memcache':
          return $this->obj->set(
            $key, json_encode($val), $ttl
          );
        case 'files':
          $file = self::_file($key, $this->path);
          if ($this->fs->createPath(X::dirname($file))) {
            if ($this->fs->putContents($file, json_encode($val, JSON_PRETTY_PRINT))) {
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
   * @return bool Returns true in case of success false otherwise
   */
  public function set($key, $val, $ttl = null, ?float $exec = null): bool
  {
    if (self::$type) {
      $ttl  = self::ttl($ttl);
      $hash = self::makeHash($val);
      $value = [
        'timestamp' => microtime(1),
        'hash' => $hash,
        'expire' => $ttl ? time() + $ttl : 0,
        'ttl' => $ttl,
        'exec' => $exec,
        'value' => $val
      ];

      return $this->setRaw($key, $value, $ttl);
    }

    return false;
  }


  /**
   * Checks if the value of the item corresponds to the given hash.
   *
   * @param string $key The name of the item
   * @param string $hash A MD5 hash to compare with
   * @return bool Returns true if the hashes are different, false otherwise
   */
  public function isChanged(string $key, $hash): bool
  {
    return $hash !== $this->hash($key);
  }


  /**
   * Returns the cache object (array) as stored.
   *
   * @param string $key The name of the item
   * @param int    $ttl  The cache length
   * @return null|array
   */
  private function getRaw(string $key, ?int $ttl = null, bool $force = false, int $attempts = 0): ?array
  {
    switch (self::$type) {
      case 'apc':
        if (!function_exists('\\apcu_exists')) {
          throw new Exception(X::_("The APC extension doesn't seem to be installed"));
        }

        if (call_user_func('\\apcu_exists', $key)) {
          $t = call_user_func('\\apcu_fetch', $key);
        }
        break;
      case 'redis':
        $tmp = $this->obj->get($key);
        if ($tmp) {
          $t = json_decode($tmp, true);
        }

        break;
      case 'memcache':
        $tmp = $this->obj->get($key);
        if ($tmp) {
          $t = json_decode($tmp, true);
        }

        break;
      case 'files':
        $file = self::_file($key, $this->path);
        if ($this->fs->isFile($file)
          && ($t = $this->fs->getContents($file))) {
          $t = json_decode($t, true);
        }
        break;
    }

    if (!empty($t)) {
      if (!empty($t['building'])) {
        if ($attempts < 5) {
          usleep(100000);
          return $this->getRaw($key, $ttl, $force, $attempts + 1);
        }
        else {
          return null;
        }
      }

      if (!empty($t['expire']) && ($t['expire'] < time())) {
        $this->delete($key);
      }
      elseif (!$ttl || !isset($t['ttl']) || ($ttl <= $t['ttl'])) {
        return $t;
      }
    }
    return null;
  }


  /**
   * Returns the cache value, false otherwise.
   *
   * @param string $key The name of the item
   * @param int    $ttl  The cache length
   * @return mixed
   */
  public function get(string $key, mixed $ttl = null): mixed
  {
    if ($r = $this->getRaw($key, $ttl)) {
      return $r['value'];
    }

    return false;
  }


  /**
   * Returns the cache value, false otherwise.
   *
   * @param string $key The name of the item
   * @param int    $ttl  The cache length
   * @return mixed
   */
  public function getFull(string $key, ?int $ttl = null): ?array
  {
    if ($r = $this->getRaw($key, $ttl)) {
      return $r;
    }

    return null;
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
  public function getSet(callable $fn, string $key, ?int $ttl = null)
  {
    $tmp  = $this->getRaw($key, $ttl);
    $data = null;
    // Can't get the data
    $this->setRaw($key, [
      'value' => $tmp ? $tmp['value'] : null,
      'hash' => $tmp ? $tmp['hash'] : null,
      'building' => true,
      'ttl' => 10,
      'expire' => time() + 10
    ], 10);
    if (!$tmp) {
      $this->setRaw($key, ['value' => null, 'building' => true, 'ttl' => 10, 'expire' => time() + 10], 10);
      try {
        $data = $fn();
      }
      catch (Exception $e) {
        $this->delete($key);
        throw $e;
      }

      $this->set($key, $data, $ttl);
    }
    else {
      $data = $tmp['value'];
    }

    return $data;
  }


  /**
   * @return array|bool|false
   */
  public function info()
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return call_user_func('\\apcu_cache_info');
        case 'memcache':
          return $this->obj->getStats('slabs');
        case 'files':
          return $this->fs->getFiles($this->path);
      }
    }
  }


  /**
   * @return array|bool|false
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
          return $this->fs->getFiles($this->path);
      }
    }
  }


  /**
   * @param string $dir
   * @return array
   */
  public function items(?string $dir = null): array 
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
          $list = [];
          $it = null;
          $prefixLength = strlen($this->prefix);
          while ($keys = $this->obj->scan($it, ($this->prefix ?? '' ).($dir ? "$dir*" : '*'))) {
            foreach ($keys as $key) {
              $key = mb_substr($key, $prefixLength);
              if (empty($dir) || (mb_strpos($key, $dir) === 0)) {
                $list[] = $key;
              }
            }
          }

          sort($list);
          return $list;

        case 'memcache':
          $list = [];
          $arr  = $this->getAllKeys();
          foreach ($arr as $key){
            if (empty($dir) || (mb_strpos($key, $dir) === 0)) {
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
              }, $this->fs->getFiles($this->path.($dir ? "/$dir" : ''))
            ),
            function ($a) use ($cache) {
              // Only gives valid cache
              return $cache->has($a);
            }
          );
          $dirs  = $this->fs->getDirs($this->path.($dir ? "/$dir" : ''));
          if (count($dirs)) {
            foreach ($dirs as $d){
              $res = $this->items($dir ? $dir.'/'.X::basename($d) : X::basename($d));
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
            $bits = X::split($k, '/');
            $idx = 0;
            if (empty($path)) {
              $name = $bits[0];
            }
            elseif (mb_strpos($k, $path) === 0) {
              $idx = count(X::split(trim($path, '/'), '/'));
              $name = $bits[$idx];
            }
            else {
              continue;
            }

            if ($name) {
              $fullName = trim(trim($path, '/') . '/' . $name, '/');
              $num = 0;
              if ($isFolder = $k !== $fullName) {
                $num++;
                $name .= '/';
                $fullName .= '/';
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
                      $bits = X::split($a, '/');
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
                'path' => X::split(dirname($fullName), '/'),
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
            $bits = X::split($k, '/');
            $idx = 0;
            if (empty($path)) {
              $name = $bits[0];
            }
            elseif (mb_strpos($k, $path) === 0) {
              $idx = count(X::split(trim($path, '/'), '/'));
              $name = $bits[$idx];
            }
            else {
              continue;
            }

            if ($name) {
              $fullName = trim(trim($path, '/') . '/' . $name, '/');
              $num = 0;
              if ($isFolder = $k !== $fullName) {
                $num++;
                $name .= '/';
                $fullName .= '/';
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
                      $bits = X::split($a, '/');
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
                'path' => X::split(dirname($fullName), '/'),
                'folder' => $isFolder
              ];
            }
            //X::ddump(count($list));
          }

          return $list;
        case 'files':
          $this->fs->cd($this->path);
          $content = $this->fs->getFiles($path ? "/$path" : '', true);
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
                'num' => $this->fs->isDir($nodePath) ? count($this->fs->getFiles($nodePath, true)) : 0,
                'folder' => $this->fs->isDir($nodePath)
              ];
        
        
              if ($this->fs->isDir($nodePath)) {
                $paths = $element !== $nodePath ? X::split($nodePath, '/') : [];
                $ele['path'] = count($paths) ? array_splice($paths, 0, count($paths) - 1) : $paths;
              }

              array_push($all, $ele);
            }
          }

          $this->fs->back();
          return $this->fs->getFiles($this->path.($path ? "/$path" : ''));
      }
    }

    return [];
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
