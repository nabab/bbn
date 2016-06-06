<?php
namespace bbn;

/**
 * Universal caching class
 *
 *
 * This class, called once per request, holds the cache system
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Jan 23, 2016, 23:23:55 +0000
 * @category  Cache
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.9
 * @todo Add feature to auto-detect a different corresponding index and redirect to it through Appui
 * @todo Add $this->dom to public controllers (?)
 */

class cache{

  private static
    $is_init = false,
    $type = false,
    $engine;

  private
    $path,
    $obj;

  private static function _init($engine = null){
    if ( !self::$is_init ){
      self::$engine = new cache($engine);
      self::$is_init = 1;
    }
    return 1;
  }

  private static function _set_type($type){
    self::$type = $type;
  }

  private static function _dir($item){
    $dir = dirname($item);
    if ( empty($dir) ){
      return '';
    }
    return str_replace("../", '', str_replace("\\", "/", $dir));
  }

  private static function _file($item, $path){
    return $path.self::_dir($item).'/'.\bbn\str::encode_filename(basename($item)).'.bbn.cache';
  }

  public static function make_hash($value){
    return md5(serialize($value));
  }

  public static function get_type(){
    return self::$type;
  }

  public static function ttl($ttl){
    if ( \bbn\str::is_integer($ttl) ){
      return $ttl;
    }
    if ( is_string($ttl) ){
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

  public static function get_engine($engine = null){
    self::_init($engine);
    return self::$engine;
  }

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
      $this->path = defined("BBN_DATA_PATH") ? BBN_DATA_PATH : \bbn\file\dir::clean(sys_get_temp_dir());
      if ( substr($this->path, -1) !== '/' ){
        $this->path .= '/';
      }
      $this->path .= 'bbn_cache/';
      \bbn\file\dir::create_path($this->path);
      self::_set_type('files');
    }
  }

  public function has($it){
    if ( self::$type && is_string($it) ){
      switch ( self::$type ){
        case 'apc':
          return apc_exists($it);
        case 'memcache':
          return $this->obj->get($it) !== $it;
        case 'files':
          $file = self::_file($it, $this->path);
          if ( is_file($file) ){
            $t = unserialize(file_get_contents($file));
            if ( !$t['expire'] || ($t['expire'] > time()) ){
              return true;
            }
            unlink($file);
          }
          return false;
      }
    }
  }

  public function delete($it){
    if ( self::$type && is_string($it) ){
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

  public function delete_all($st=false){
    if ( self::$type ){
      $its = $this->items();
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
  }

  public function clear(){
    if ( self::$type ){
      switch ( self::$type ){
        case 'apc':
          apc_clear_cache('user');
          apc_clear_cache('system');
          break;
        case 'memcache':
          $this->obj->flush();
          break;
        case 'files':
          $files = \bbn\file\dir::get_files($this->path);
          foreach ( $files as $f ){
            unlink($f);
          }
      }
    }
    return $this;
  }

  public function timestamp($it){
    if ( $r = $this->get_raw($it) ){
      return $r['timestamp'];
    }
    return false;
  }

  public function hash($it){
    if ( $r = $this->get_raw($it) ){
      return $r['hash'];
    }
    return false;
  }

  public function is_new($it, $time){
    if ( $r = $this->get_raw($it) ){
      return $r['timestamp'] > $time;
    }
    return true;
  }

  public function set($it, $val, $ttl = 0){
    if ( self::$type && is_string($it) ){
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
          if ( $dir = self::_dir($it) ){
            \bbn\file\dir::create_path($this->path.'/'.$dir);
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

  public function is_changed($it, $hash){
    if ( $r = $this->get_raw($it) ){
      return $hash !== $r['hash'];
    }
  }

  private function get_raw($it){
    if ( $this->has($it) ){
      switch ( self::$type ){
        case 'apc':
          return apc_fetch($it);
        case 'memcache':
          return $this->obj->get($it);
        case 'files':
          $file = self::_file($it, $this->path);
          $t = file_get_contents($file);
          return $t ? unserialize($t) : false;
      }
    }
    return false;
  }

  public function get($it){
    if ( $r = $this->get_raw($it) ){
      return $r['value'];
    }
    return false;
  }

  public function info(){
    if ( self::$type ){
      switch ( self::$type ){
        case 'apc':
          return apc_cache_info();
        case 'memcache':
          return $this->obj->getStats('slabs');
        case 'files':
          return \bbn\file\dir::get_files($this->path);
      }
    }
  }

  public function stat(){
    if ( self::$type ){
      switch ( self::$type ){
        case 'apc':
          return apc_cache_info();
        case 'memcache':
          return $this->obj->getStats();
        case 'files':
          return \bbn\file\dir::get_files($this->path);
      }
    }
  }

  public function items($dir = ''){
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
          return array_filter(array_map(function($a) use ($dir){
            return ( $dir ? $dir.'/' : '' ).basename($a, '.bbn.cache');
          }, \bbn\file\dir::get_files($this->path.($dir ? '/'.$dir : ''))), function($a) use ($cache){
            return $cache->has($a);
          });
      }
    }
  }

}