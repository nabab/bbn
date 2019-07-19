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

  private static
    $is_init = false,
    $type = false,
    $engine;

  private
    $path,
    $obj;

  /**
   * @param null $engine
   * @return int
   */
  private static function _init($engine = null){
    if ( !self::$is_init ){
      self::$engine = new cache($engine);
      self::$is_init = 1;
    }
    return 1;
  }

  /**
   * @param string $type
   */
  private static function _set_type(string $type){
    self::$type = $type;
  }

  private static function sanitize($st){
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
  private static function _dir(string $dir, string $path, $parent = true){
    if ( $parent ){
      $dir = dirname($dir);
    }
    if ( empty($dir) ){
      return $path;
    }
    else if ( substr($dir, -1) === '/' ){
      $dir = substr($dir, 0, -1);
    }
    return $path.self::sanitize(str_replace('../', '', str_replace('\\', '/', $dir)));
  }

  /**
   * @param string $item
   * @param string $path
   * @return string
   */
  private static function _file(string $item, string $path){
    return self::_dir($item, $path).'/'.self::sanitize(basename($item)).'.bbn.cache';
  }

  /**
   * @param $value
   * @return string
   */
  public static function make_hash($value){
    if ( \is_object($value) || \is_array($value) ){
      $value = serialize($value);
    }
    return md5($value);
  }

  /**
   * @return string
   */
  public static function get_type(){
    return self::$type;
  }

  /**
   * @param $ttl
   * @return int
   */
  public static function ttl($ttl){
    if ( str::is_integer($ttl) ){
      return $ttl;
    }
    if ( \is_string($ttl) ){
      switch ( $ttl ){
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
   * @param null $engine
   * @return mixed
   */
  public static function get_cache($engine = null){
    self::_init($engine);
    return self::$engine;
  }

  /**
   * @param null $engine
   * @return mixed
   */
  public static function get_engine($engine = null){
    return self::get_cache($engine);
  }

  /**
   * cache constructor.
   * @param null $engine
   */
  public function __construct($engine = null){

    if ( self::$is_init ){
      die("Only one cache object can be called. Use static function cache::get_engine()");
    }

    if ( function_exists('apc_clear_cache') && (!$engine || ($engine === 'apc')) ){
      self::_set_type('apc');
    }
    else if ( class_exists("Memcache") && (!$engine || ($engine === 'memcache')) ){
      $this->obj = new \Memcache();
      if ( $this->obj->connect("127.0.0.1", 11211) ){
        self::_set_type('memcache');
      }
    }
    else {
      $this->path = \defined("BBN_DATA_PATH") ? BBN_DATA_PATH : file\dir::clean(sys_get_temp_dir());
      if ( substr($this->path, -1) !== '/' ){
        $this->path .= '/';
      }
      $this->path .= 'bbn_cache/';
      file\dir::create_path($this->path);
      self::_set_type('files');
    }
  }

  /**
   * @param string $it
   * @return bool|string
   */
  public function has(string $it){
    if ( self::$type ){
      switch ( self::$type ){
        case 'apc':
          return apc_exists($it);
        case 'memcache':
          return $this->obj->get($it) !== $it;
        case 'files':
          $file = self::_file($it, $this->path);
          if ( is_file($file) ){
            $t = json_decode(file_get_contents($file), true);
            if ( !$t['expire'] || ($t['expire'] > time()) ){
              return true;
            }
            unlink($file);
          }
          return false;
      }
    }
  }

  /**
   * @param string $it
   * @return bool|int|string
   */
  public function delete(string $it){
    if ( self::$type && \is_string($it) ){
      switch ( self::$type ){
        case 'apc':
          return apc_delete($it);
        case 'memcache':
          return $this->obj->delete($it);
        case 'files':
          $file = self::_file($it, $this->path);
          if ( is_file($file) ){
            return unlink($file);
          }
          return 1;
      }
    }
  }

  /**
   * @param string|null $st
   * @return bool|int
   */
  public function delete_all(string $st = null){
    if ( self::$type === 'files' ){
      $dir = self::_dir($st, $this->path, false);
      if ( is_dir($dir) ){
        return file\dir::delete($dir, $dir === $this->path ? false : true);
      }
      else if ( is_file($dir.'.bbn.cache') ){
        unlink($dir.'.bbn.cache');
      }
    }
    else if ( self::$type ){
      $its = $this->items($st);
      $res = 0;
      foreach ( $its as $it ){
        if ( !$st || strpos($it, $st) === 0 ){
          switch ( self::$type ){
            case 'apc':
              $res += (int)apc_delete($it);
              break;
            case 'memcache':
              $res += (int)$this->obj->delete($it);
              break;
            case 'files':
              $file = self::_file($it, $this->path);
              if ( is_file($file) ){
                $res += (int)unlink($file);
              }
              break;
          }
        }
      }
      return $res;
    }
    return false;
  }

  /**
   * @return $this
   */
  public function clear(){
    $this->delete_all();
    return $this;
  }

  /**
   * @param string $it
   * @return bool
   */
  public function timestamp(string $it){
    if ( $r = $this->get_raw($it) ){
      return $r['timestamp'];
    }
    return false;
  }

  /**
   * @param string $it
   * @return bool|mixed
   */
  public function hash(string $it){
    if ( $r = $this->get_raw($it) ){
      return $r['hash'];
    }
    return false;
  }

  /**
   * @param string $it
   * @param null $time
   * @return bool
   */
  public function is_new(string $it, $time = null){
    if ( !$time ){
      $time = time();
    }
    if ( $r = $this->get_raw($it) ){
      return $r['timestamp'] > $time;
    }
    return true;
  }

  /**
   * @param string $it
   * @param $val
   * @param int $ttl
   * @return array|bool
   */
  public function set(string $it, $val, $ttl = 10){
    if ( self::$type ){
      $ttl = self::ttl($ttl);
      $hash = self::make_hash($val);
      switch ( self::$type ){
        case 'apc':
          return apc_store($it, [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'value' => $val
          ], $ttl);
        case 'memcache':
          return $this->obj->set($it, [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'value' => $val
          ], false, $ttl);
        case 'files':
          $file = self::_file($it, $this->path);
          if ( $dir = self::_dir($it, $this->path) ){
            file\dir::create_path($dir);
          }
          $value = [
            'timestamp' => microtime(1),
            'hash' => $hash,
            'expire' => $ttl ? time() + $ttl : 0,
            'value' => $val
          ];
          return file_put_contents($file, serialize($value)) ? true : false;
      }
    }
  }

  /**
   * @param string $it
   * @param $hash
   * @return bool
   */
  public function is_changed(string $it, $hash){
    if ( $r = $this->get_raw($it) ){
      return $hash !== $r['hash'];
    }
  }

  /**
   * @param string $it
   * @return array|bool|mixed|string
   */
  private function get_raw(string $it){
    if ( $this->has($it) ){
      switch ( self::$type ){
        case 'apc':
          return apc_fetch($it);
        case 'memcache':
          return $this->obj->get($it);
        case 'files':
          $file = self::_file($it, $this->path);
          if ( $t = file_get_contents($file) ){
            return unserialize($t, ['allowed_classes' => true]);
          }
      }
    }
    return false;
  }

  public function get(string $it){
    if ( $r = $this->get_raw($it) ){
      return $r['value'];
    }
    return false;
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