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

class cache{

  private static $is_init = false;
  private static $type = null;
  private static $max_wait = 10;
  private static $engine;

  private $path;
  private $obj;

  /**
   * @param null $engine
   * @return int
   */
  private static function _init($engine = null): int
  {
    if (!self::$is_init) {
      self::$engine = new cache($engine);
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

  private static function _sanitize($st){
    $st = mb_ereg_replace("([^\w\s\d\-_~,;\/\[\]\(\).])", '', $st);
    $st = mb_ereg_replace("([\.]{2,})", '', $st);
    return $st;
  }

  /**
   * @param string $dir
   * @param string $path
   * @param bool $parent
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
    return self::_dir($item, $path).self::_sanitize(basename($item)).'.bbn.cache';
  }

  /**
   * Makes a unique hash out of whatever value which will be used to check if the value has changed.
   * 
   * @param $value
   * @return string The hash
   */
  public static function make_hash($value): string
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
  public static function get_type(): ?string
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
    if (str::is_integer($ttl)) {
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
          return 3600*24;
        case 'xl':
          return 3600*24*7;
        case 'xxl':
          return 3600*24*30;
      }
    }
    return 0;
  }

  /**
   * Returns the cache object (and creates one of the given type if it doesn't exist).
   * 
   * @param string $engine
   * @return self
   */
  public static function get_cache(string $engine = null): self
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
  public static function get_engine(string $engine = null): self
  {
    return self::get_cache($engine);
  }

  /**
   * Constructor - this is a singleton: it can't be called more then once.
   * 
   * @param string $engine The type of engine to use
   */
  public function __construct(string $engine = null)
  {
    /** @todo APC doesn't work */
    $engine = 'files';
    if (self::$is_init) {
      die("Only one cache object can be called. Use static function cache::get_engine()");
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
    elseif ($this->path = mvc::get_cache_path()) {
      self::_set_type('files');
    }
  }

  /**
   * Checks whether a valid cache exists for the given item.
   * 
   * @param string $item The name of the item
   * @param string|int $ttl The time-to-live value
   * @return bool
   */
  public function has(string $item, $ttl = 0): bool
  {
    
    if ( self::$type ){
      switch ( self::$type ){
        case 'apc':
          return apc_exists($item);
        case 'memcache':
          return $this->obj->get($item) !== $item;
        case 'files':
          $file = self::_file($item, $this->path);
          if ( is_file($file) ){
            $t = json_decode(file_get_contents($file), true);
            if ( 
              (!$ttl || !isset($t['ttl']) || ($ttl === $t['ttl']))
              && (!$t['expire'] || ($t['expire'] > time()))
            ) {
              return true;
            }
            unlink($file);
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
      switch ( self::$type ){
        case 'apc':
          return apc_delete($item);
        case 'memcache':
          return $this->obj->delete($item);
        case 'files':
          $file = self::_file($item, $this->path);
          if ( is_file($file) ){
            return !!unlink($file);
          }
          return false;
      }
    }
  }

  /**
   * Deletes all the cache from the given path or globally if none is given.
   * 
   * @param string $st The path of the items to delete
   * @return bool|int
   */
  public function delete_all(string $st = null): bool
  {
    if ( self::$type === 'files' ){
      $dir = self::_dir($st, $this->path, false);
      if ( is_dir($dir) ){
        return !!file\dir::delete($dir, $dir === $this->path ? false : true);
      }
      else if ( is_file($dir.'.bbn.cache') ){
        return !!unlink($dir.'.bbn.cache');
      }
    }
    else if ( self::$type ){
      $items = $this->items($st);
      $res = 0;
      foreach ( $items as $item ){
        if ( !$st || strpos($item, $st) === 0 ){
          switch ( self::$type ){
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
    $this->delete_all();
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
    if ( $r = $this->get_raw($item) ){
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
    if ( $r = $this->get_raw($item) ){
      return $r['hash'];
    }
    return null;
  }

  /**
   * Checks whether or not the given item is more recent than the given timestamp.
   * 
   * @param string $item The name of the item
   * @param null|int $time The timestamp to which the item's timestamp will be compared
   * @return bool
   */
  public function is_new(string $item, int $time = null): bool
  {
    if ( !$time ){
      return false;
    }
    if ( $r = $this->get_raw($item) ){
      return $r['timestamp'] > $time;
    }
    return true;
  }

  /**
   * Stores the given value in the cache for as long as says the TTL.
   * 
   * @param string $item The name of the item
   * @param $val The value to be stored in the cache
   * @param int $ttl The length in seconds during which the value will be considered as valid
   * @return bool Returns true in case of success false otherwise
   */
  public function set(string $item, $val, $ttl = 10): bool
  {
    if ( self::$type ){
      $ttl = self::ttl($ttl);
      $hash = self::make_hash($val);
      switch ( self::$type ){
        case 'apc':
          return \apc_store($item, [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'ttl' => $ttl,
            'value' => $val
          ], $ttl);
        case 'memcache':
          return $this->obj->set($item, [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'ttl' => $ttl,
            'value' => $val
          ], false, $ttl);
        case 'files':
          $file = self::_file($item, $this->path);
          if ( $dir = self::_dir($item, $this->path) ){
            file\dir::create_path($dir);
          }
          $value = [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'expire' => $ttl ? time() + $ttl : 0,
            'ttl' => $ttl,
            'value' => $val
          ];
          return file_put_contents($file, json_encode($value, JSON_PRETTY_PRINT)) ? true : false;
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
  public function is_changed(string $item, $hash): bool
  {
    return $hash !== $this->hash($item);
  }

  /**
   * Set the cache file in a block state so other processes don't try to create it.
   *
   * @param string $item
   * @return bool
   */
  public function block(string $item): bool
  {
    if (self::$type === 'files') {
      $file = self::_file($item, $this->path);
      if (file_exists($file) && ($t = file_get_contents($file))) {
        $t = json_decode($t, true);
        if (!empty($t['block'])) {
          return false;
        }
      }
      if ($dir = self::_dir($item, $this->path)) {
        file\dir::create_path($dir);
      }
      if (file_put_contents(
        $file,
        json_encode(
          [
            'block' => 1,
            'date' => date('Y-m-d H:i:s')
          ],
          JSON_PRETTY_PRINT
        )
      )
      ) {
        return true;
      }
      return false;
    }
    return true;
  }

  /**
   * Unset the block staate from the cache file.
   *
   * @param string $item
   * @return bool
   */
  public function unblock(string $item): bool
  {
    if (self::$type === 'files') {
      $file = self::_file($item, $this->path);
      if (file_exists($file) && ($t = file_get_contents($file))) {
        $t = json_decode($t, true);
        if (!empty($t['block'])) {
          @unlink($file);
          return true;
        }
        return false;
      }
    }
    return true;
  }

  /**
   * Returns the cache object (array) as stored.
   * 
   * @param string $item The name of the item
   * @param int $ttl The cache length
   * @return null|array
   */
  private function get_raw(string $item, int $ttl = 0): ?array
  {
    switch (self::$type) {
      case 'apc':
        if (\apc_exists($item)) {
          return \apc_fetch($item);
        }
        break;
      case 'memcache':
        $tmp = $this->obj->get($item);
        if ($tmp !== $item) {
          return $tmp;
        }
        break;
      case 'files':
        $file = self::_file($item, $this->path);
        if (file_exists($file) 
            && ($t = file_get_contents($file))
            && ($t = json_decode($t, true))
        ) {
          $num = 0;
          while (is_array($t) && !empty($t['block']) && ($num < self::$max_wait)) {
            \bbn\x::log([$item, date('Y-m-d H:i:s')], 'wait_for_cache');
            $num++;
            if ($t = file_get_contents($file)) {
              $t = json_decode($t, true);
            }
          }
          if ($t
              && empty($t['block'])
              && (!$ttl || !isset($t['ttl']) || ($ttl === $t['ttl']))
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
   * @param int $ttl The cache length
   * @return mixed
   */
  public function get(string $item, int $ttl = 0)
  {
    if ( $r = $this->get_raw($item, $ttl) ){
      return $r['value'];
    }
    return false;
  }

  /**
   * Returns the cache for the given item, but if expired or absent creates it before by running the provided function.
   * 
   * @param string $item The name of the item
   * @param function $fn The function which returns the value for the cache
   * @param int $ttl The cache length
   * @return mixed
   */
  public function set_get(callable $fn, string $item, int $ttl = 0)
  {
    $tmp = $this->get_raw($item, $ttl);
    if (!$tmp) {
      if ($this->block($item)) {
        $data = $fn();
        if ($this->unblock($item)) {
          $this->set($item, $data, $ttl);
        }
      }
    }
    else {
      $data = $tmp['value'];
    }
    return $data;
  }

  /**
   * @return array|bool|false
   */
  public function info(){
    if ( self::$type ){
      switch ( self::$type ){
        case 'apc':
          return apc_cache_info();
        case 'memcache':
          return $this->obj->getStats('slabs');
        case 'files':
          return file\dir::get_files($this->path);
      }
    }
  }

  /**
   * @return array|bool|false
   */
  public function stat(){
    if ( self::$type ){
      switch ( self::$type ){
        case 'apc':
          return apc_cache_info();
        case 'memcache':
          return $this->obj->getStats();
        case 'files':
          return file\dir::get_files($this->path);
      }
    }
  }

  /**
   * @param string $dir
   * @return array
   */
  public function items(string $dir = ''){
    if ( self::$type ){
      switch ( self::$type ){
        case 'apc':
          $all = apc_cache_info();
          $list = [];
          foreach ( $all['cache_list'] as $a ){
            array_push($list, $a['info']);
          }
          return $list;
        case 'memcache':
          $list = [];
          $allSlabs = $this->obj->getExtendedStats('slabs');
          foreach ( $allSlabs as $server => $slabs ){
            foreach ( $slabs as $slabId => $slabMeta ){
              $cdump = $this->obj->getExtendedStats('cachedump',(int)$slabId);
              foreach ( $cdump AS $keys => $arrVal ){
                foreach ( $arrVal AS $k => $v ){
                  if ( $k !== 'CLIENT_ERROR' ){
                    echo array_push($list, $k);
                  }
                }
              }
            }
          }
          return $list;
        case 'files':
          $cache =& $this;
          $list = array_filter(array_map(function($a) use ($dir){
            return ( $dir ? $dir.'/' : '' ).basename($a, '.bbn.cache');
          }, file\dir::get_files($this->path.($dir ? '/'.$dir : ''))),
            function($a) use ($cache){
            // Only gives valid cache
              return $cache->has($a);
          });
          $dirs = file\dir::get_dirs($this->path.($dir ? '/'.$dir : ''));
          if ( \count($dirs) ){
            foreach ( $dirs as $d ){
              $res = $this->items($dir ? $dir.'/'.basename($d) : basename($d));
              foreach ( $res as $r ){
                array_push($list, $r);
              }
            }
          }
          return $list;
      }
    }
  }

}