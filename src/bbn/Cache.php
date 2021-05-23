<?php
namespace bbn;

/**
 * Universal caching class: called once per request, it holds the cache system.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Jan 23, 2016, 23:23:55 +0000
 * @category  Cache
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 */

class Cache
{

  protected static $is_init = false;

  protected static $type = null;

  protected static $max_wait = 10;

  protected static $engine;

  protected $path;

  protected $obj;

  protected $fs;


  /**
   * @param null $engine
   * @return int
   */
  private static function _init(string $engine = null): int
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
      $dir = dirname($dir);
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
   * @param string $item
   * @param string $path
   * @return string
   */
  private static function _file(string $item, string $path): string
  {
    return self::_dir($item, $path).'/'.self::_sanitize(basename($item)).'.bbn.cache';
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

    throw new \Exception(
      X::_("Wrong ttl parameter")
    );
  }


  /**
   * Returns the cache object (and creates one of the given type if it doesn't exist).
   *
   * @param string $engine
   * @return self
   */
  public static function getCache(string $engine = null): self
  {
    self::_init($engine);
    return self::$engine;
  }


  /**
   * Alias of get_cache.
   *
   * @param string $engine
   * @return self
   */
  public static function getEngine(string $engine = null): self
  {
    return self::getCache($engine);
  }


    /**
     * Constructor - this is a singleton: it can't be called more then once.
     *
     * @param string $engine The type of engine to use
     *
     * @throws Exception
     */
  public function __construct(string $engine = null)
  {
    /** @todo APC doesn't work */
    $engine = 'files';
    if (self::$is_init) {
      throw new \Exception(
        X::_("Only one cache object can be called. Use static function Cache::getEngine()")
      );
    }

    if ((!$engine || ($engine === 'apc')) && function_exists('apc_clear_cache')) {
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
   * @param string     $item The name of the item
   * @param string|int $ttl  The time-to-live value
   * @return bool
   */
  public function has(string $item, $ttl = 0): bool
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return apc_exists($item);
        case 'memcache':
          return $this->obj->get($item) !== $item;
        case 'files':
          $file = self::_file($item, $this->path);
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
  }


  /**
   * Removes the given item from the cache.
   *
   * @param string $item The name of the item
   * @return bool
   */
  public function delete(string $item): bool
  {
    if (self::$type) {
      switch (self::$type){
        case 'apc':
          return apc_delete($item);
        case 'memcache':
          return $this->obj->delete($item);
        case 'files':
          $file = self::_file($item, $this->path);
          if (is_file($file)) {
            return !!$this->fs->delete($file);
          }
          return false;
      }
    }
  }


    /**
     * Deletes all the cache from the given path or globally if none is given.
     *
     * @param string|null $st The path of the items to delete
     *
     * @return bool|int
     */
  public function deleteAll(string $st = null): bool
  {
    if (self::$type === 'files') {
      if ($st === null) {
          $st = '';
      }

      $dir = self::_dir($st, $this->path, false);
      if ($this->fs->isDir($dir)) {
        return !!$this->fs->delete($dir, $dir === $this->path ? false : true);
      }
      else {
        return !!$this->fs->delete($dir.'.bbn.cache');
      }
    }
    elseif (self::$type) {
      $items = $this->items($st);
      $res   = 0;
      foreach ($items as $item){
        if (!$st || strpos($item, $st) === 0) {
          switch (self::$type){
            case 'apc':
              $res += (int)apc_delete($item);
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
  public function clear(): self
  {
    $this->deleteAll();
    return $this;
  }


  /**
   * Returns the timestamp of the given item.
   *
   * @param string $item The name of the item
   * @return null|int
   */
  public function timestamp(string $item): ?int
  {
    if ($r = $this->getRaw($item)) {
      return $r['timestamp'];
    }

    return null;
  }


  /**
   * Returns the hash of the given item.
   *
   * @param string $item The name of the item
   * @return null|string
   */
  public function hash(string $item): ?string
  {
    if ($r = $this->getRaw($item)) {
      return $r['hash'];
    }

    return null;
  }


  /**
   * Checks whether or not the given item is more recent than the given timestamp.
   *
   * @param string   $item The name of the item
   * @param null|int $time The timestamp to which the item's timestamp will be compared
   * @return bool
   */
  public function isNew(string $item, int $time = null): bool
  {
    if (!$time) {
      return false;
    }

    if ($r = $this->getRaw($item)) {
      return $r['timestamp'] > $time;
    }

    return true;
  }


  /**
   * Stores the given value in the cache for as long as says the TTL.
   *
   * @param string $item The name of the item
   * @param mixed  $val  The value to be stored in the cache
   * @param int    $ttl  The length in seconds during which the value will be considered as valid
   * @return bool Returns true in case of success false otherwise
   */
  public function set(string $item, $val, int $ttl = 10, float $exec = null): bool
  {
    if (self::$type) {
      $ttl  = self::ttl($ttl);
      $hash = self::makeHash($val);
      switch (self::$type){
        case 'apc':
          return \apc_store(
            $item, [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'ttl' => $ttl,
            'value' => $val
            ], $ttl
          );
        case 'memcache':
          return $this->obj->set(
            $item, [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'ttl' => $ttl,
            'value' => $val
            ], false, $ttl
          );
        case 'files':
          $file = self::_file($item, $this->path);
          if ($this->fs->createPath(dirname($file))) {
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
   * @param string $item The name of the item
   * @param string $hash A MD5 hash to compare with
   * @return bool Returns true if the hashes are different, false otherwise
   */
  public function isChanged(string $item, $hash): bool
  {
    return $hash !== $this->hash($item);
  }


  /**
   * Returns the cache object (array) as stored.
   *
   * @param string $item The name of the item
   * @param int    $ttl  The cache length
   * @return null|array
   */
  private function getRaw(string $item, int $ttl = 0, bool $force = false): ?array
  {
    switch (self::$type) {
      case 'apc':
        /*
        if (\apc_exists($item)) {
          return \apc_fetch($item);
        }
        break;
        */
      case 'memcache':
        /*
        $tmp = $this->obj->get($item);
        if ($tmp !== $item) {
          return $tmp;
        }
        break;
        */
      case 'files':
        $file = self::_file($item, $this->path);
        if (!$this->fs->isFile($file)) {
          $tmp_file = dirname($file).'/_'.basename($file);
          if ($this->fs->isFile($tmp_file)) {
            $num = 0;
            while (!$this->fs->isFile($file) && ($num < self::$max_wait)) {
              X::log([$item, $file, $tmp_file, date('Y-m-d H:i:s')], 'wait_for_cache');
              sleep(1);
              $num++;
            }
          }
        }

        if (($t = $this->fs->getContents($file))
            && ($t = json_decode($t, true))
        ) {
          if ($t
              && (!$ttl || !isset($t['ttl']) || ($ttl <= $t['ttl']))
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
   * @param string $item The name of the item
   * @param int    $ttl  The cache length
   * @return mixed
   */
  public function get(string $item, int $ttl = 0)
  {
    if ($r = $this->getRaw($item, $ttl)) {
      return $r['value'];
    }

    return false;
  }


    /**
     * Returns the cache for the given item, but if expired or absent creates it before by running the provided function.
     *
     * @param callable $fn   The function which returns the value for the cache
     * @param string   $item The name of the item
     * @param int      $ttl  The cache length
     *
     * @return mixed
     * @throws \Exception
     */
  public function getSet(callable $fn, string $item, int $ttl = 0)
  {
    switch (self::$type) {
      case 'apc':
        break;
      case 'memcache':
        break;
      case 'files':
        // Getting the data
        $tmp  = $this->getRaw($item, $ttl);
        $data = null;
        // Can't get the data
        if (!$tmp) {
          $file = self::_file($item, $this->path);
          // Temporary file will be created to tell other processes the cache is being created
          $tmp_file = dirname($file).'/_'.basename($file);
          // Will become true if the cache should be created
          $do = false;
          // If the temporary file doesn't exist we create one
          if (!$this->fs->isFile($tmp_file)) {
            $this->fs->createPath(dirname($tmp_file));
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
            catch (\Exception $e) {
              unlink($tmp_file);
              throw $e;
            }

            $exec = $timer->stop();
            $this->set($item, $data, $ttl, $exec);
            $this->fs->delete($tmp_file);
          }
          // Otherwise another process is certainly creating the cache, so wait for it
          else {
            return $this->get($item);
          }

          // Creating the cache
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
          return apc_cache_info();
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
          return apc_cache_info();
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
          $all  = apc_cache_info();
          $list = [];
          foreach ($all['cache_list'] as $a){
            array_push($list, $a['info']);
          }
          return $list;
        case 'memcache':
          $list     = [];
          $allSlabs = $this->obj->getExtendedStats('slabs');
          foreach ($allSlabs as $server => $slabs){
            foreach ($slabs as $slabId => $slabMeta){
              $cdump = $this->obj->getExtendedStats('cachedump',(int)$slabId);
              foreach ($cdump AS $keys => $arrVal){
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
                return ( $dir ? $dir.'/' : '' ).basename($a, '.bbn.cache');
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
              $res = $this->items($dir ? $dir.'/'.basename($d) : basename($d));
              foreach ($res as $r){
                array_push($list, $r);
              }
            }
          }
          return $list;
      }
    }
  }


}
