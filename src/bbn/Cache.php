<?php
namespace bbn;

use Closure;
use Exception;
use Traversable;
use function Opis\Closure\serialize as serializeFn;
use function Opis\Closure\unserialize as unserializeFn;
use Psr\SimpleCache\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

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
  protected static $is_init = false;

  protected static $type = null;

  protected static $max_wait = 10;

  protected static $default_ttl = 60;

  protected static $engine;

  protected $path;

  protected $obj;

  protected $fs;


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
    $st = \mb_ereg_replace("([^\w\s\d\-_~,;\/\[\]\(\).])", '', $st);
    $st = \mb_ereg_replace("([\.]{2,})", '', $st);
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
    elseif (substr($dir, -1) === '/') {
      $dir = substr($dir, 0, -1);
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
    if (\is_object($value) || \is_array($value)) {
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

    if (\is_string($ttl)) {
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

    X::log($ttl);
    throw new Exception(X::_("Wrong ttl parameter"));
  }


  /**
   * Returns the cache object (and creates one of the given type if it doesn't exist).
   *
   * @param string $engine
   * @return self
   */
  public static function getCache(string|null $engine = null): self
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
    $engine = 'files';
    if (self::$is_init) {
      throw new Exception(
        X::_("Only one cache object can be called. Use static function Cache::getEngine()")
      );
    }

    if ((!$engine || ($engine === 'apc')) && function_exists('apcu_clear_cache')) {
      self::_set_type('apc');
    }
    elseif ((!$engine || ($engine === 'memcache')) && class_exists("Memcache")) {
      $this->obj = new \Memcache();
      if ($this->obj->connect("127.0.0.1", 11211)) {
        self::_set_type('memcache');
      }
    }
    elseif ($this->path = Mvc::getCachePath()) {
      self::_set_type('files');
      $this->fs = new File\System();
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
          return (bool)apcu_exists($key);
        case 'memcache':
          return $this->obj->get($key) !== $key;
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
          return apcu_delete($key);
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
  public function deleteAll(string|null $st = null): bool
  {
    if (self::$type === 'files') {
      if ($st === null) {
          $st = '';
      }

      $dir = self::_dir($st, $this->path, false);
      if ($this->fs->isDir($dir)) {
        return (bool)$this->fs->delete($dir, $dir === $this->path ? false : true);
      }
      else {
        try {
          $res = $this->fs->delete($dir.'.bbn.cache');
        }
        catch (Exception $e) {
          $res = false;
        }

        return (bool)$res;
      }
    }
    elseif (self::$type) {
      $items = $this->items($st);
      $res   = 0;
      foreach ($items as $item){
        if (!$st || strpos($item, $st) === 0) {
          switch (self::$type){
            case 'apc':
              $res += (int)apcu_delete($item);
              break;
            case 'memcache':
              $res += (int)$this->obj->delete($item);
              break;
          }
        }
      }

      return $res;
    }

    return false;
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
      switch (self::$type){
        case 'apc':
          if (!function_exists('\\apcu_store')) {
            throw new Exception(X::_("The APC extension doesn't seem to be installed"));
          }

          return \apcu_store(

            $key, [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'ttl' => $ttl,
            'value' => $val
            ], $ttl
          );
        case 'memcache':
          return $this->obj->set(
            $key, [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'ttl' => $ttl,
            'value' => $val
            ], false, $ttl
          );
        case 'files':
          $file = self::_file($key, $this->path);
          if ($this->fs->createPath(X::dirname($file))) {
            $value = [
              'timestamp' => microtime(1),
              'hash' => $hash,
              'expire' => $ttl ? time() + $ttl : 0,
              'ttl' => $ttl,
              'exec' => $exec,
              'value' => $val
            ];
            if ($this->fs->putContents($file, json_encode($value, JSON_PRETTY_PRINT))) {
              return true;
            }
          }
      }
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
  private function getRaw(string $key, ?int $ttl = null, bool $force = false): ?array
  {
    switch (self::$type) {
      case 'apc':
        if (!function_exists('\\apcu_exists')) {
          throw new Exception(X::_("The APC extension doesn't seem to be installed"));
        }

        if (\apcu_exists($key)) {
          return \apcu_fetch($key);
        }
        break;
      case 'memcache':
        $tmp = $this->obj->get($key);
        if ($tmp !== $key) {
          return $tmp;
        }
        break;
      case 'files':
        $file = self::_file($key, $this->path);
        if ($this->fs->isFile($file)
          && ($t = $this->fs->getContents($file))
          && ($t = json_decode($t, true))
        ) {
          if ((!$ttl || !isset($t['ttl']) || ($ttl <= $t['ttl']))
              && (!$t['expire'] || ($t['expire'] > time()))
          ) {
            return $t;
          }
        }
        break;
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
    switch (self::$type) {
      case 'apc':
        break;
      case 'memcache':
        break;
      case 'files':
        // Getting the data
        $tmp  = $this->getRaw($key, $ttl);
        $data = null;
        // Can't get the data
        if (!$tmp) {
          $file = self::_file($key, $this->path);
          // Temporary file will be created to tell other processes the cache is being created
          $tmp_file = X::dirname($file).'/_'.X::basename($file);
          // Will become true if the cache should be created
          $do = false;
          // If the temporary file doesn't exist we create one
          if (!$this->fs->isFile($tmp_file)) {
            $this->fs->createPath(X::dirname($tmp_file));
            $this->fs->putContents($tmp_file, ' ');
            // If the original file exists we delete it
            if ($this->fs->isFile($file)) {
              $this->fs->delete($file);
            }

            $timer = new Util\Timer();
            $timer->start();
            try {
              $data = $fn();
            }
            catch (Exception $e) {
              unlink($tmp_file);
              throw $e;
            }

            $exec = $timer->stop();
            $this->set($key, $data, $ttl, $exec);
            $this->fs->delete($tmp_file);
          }
          // Otherwise another process is certainly creating the cache, so wait for it
          else {
            $timeout = time() + self::$max_wait;
            while (time() < $timeout) {
              if (($r = $this->get($key)) !== false) {
                return $r;
              }

              usleep(500);
            }

            return $this->get($key);
          }
        }
        else {
          $data = $tmp['value'];
        }
        return $data;
    }
  }


  /**
   * @return array|bool|false
   */
  public function info()
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return apcu_cache_info();
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
          return apcu_cache_info();
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
  public function items(string $dir = '')
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          $all  = apcu_cache_info();
          $list = [];
          foreach ($all['cache_list'] as $a){
            array_push($list, $a['info']);
          }
          return $list;
        case 'memcache':
          $list     = [];
          $allSlabs = $this->obj->getExtendedStats('slabs');
          foreach ($allSlabs as $slabs){
            foreach ($slabs as $slabId => $slabMeta){
              $cdump = $this->obj->getExtendedStats('cachedump',(int)$slabId);
              foreach ($cdump AS $arrVal){
                foreach ($arrVal AS $k => $v){
                  if ($k !== 'CLIENT_ERROR') {
                    echo array_push($list, $k);
                  }
                }
              }
            }
          }
          return $list;
        case 'files':
          $cache =& $this;
          $list  = array_filter(
            array_map(
              function ($a) use ($dir) {
                return ( $dir ? $dir.'/' : '' ).X::basename($a, '.bbn.cache');
              }, $this->fs->getFiles($this->path.($dir ? '/'.$dir : ''))
            ),
            function ($a) use ($cache) {
              // Only gives valid cache
              return $cache->has($a);
            }
          );
          $dirs  = $this->fs->getDirs($this->path.($dir ? '/'.$dir : ''));
          if (\count($dirs)) {
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

}
