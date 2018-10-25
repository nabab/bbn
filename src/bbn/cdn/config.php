<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 27/02/2017
 * Time: 14:52
 */

namespace bbn\cdn;
use bbn;


/**
 * Makes a usable configuration array out of a request string
 * @package cdn
 */
class config extends bbn\models\cls\basic
{
  use common;

  /**
   * @var string
   */
  protected static
    $default_language = 'en';

  /**
   * @var array
   */
  protected $cfg = [];

  /**
   * Returns an array with all the - default or no - config parameters based on the sent ones
   */
  private function _set_cfg(){
    if ( \is_array($this->cfg) ){
      $p =& $this->cfg['params'];
      $components = false;
      if ( !empty($p['components']) ){
        $components = explode(',', $p['components']);
      }
      $this->cfg = bbn\x::merge_arrays($this->cfg, [
        'test' => !empty($p['test']),
        'lang' => empty($p['lang']) ? self::$default_language : $p['lang'],
        'nocompil' => !empty($p['nocompil']),
        'has_css' => !isset($p['css']) || $p['css'],
        'has_dep' => !isset($p['dep']) || $p['dep'],
        'latest' => isset($p['latest']) ? 1 : false,
        'is_component' => !empty($p['components']),
        'components' => $components
      ]);
    }
  }

  /**
   * Returns an array of arrays of types and files; it will use one of the different methods for retrieving files
   * depending on the params sent
   * @return $this
   */
  protected function _set_files(){
    // Shortcuts for files and dir
    if ( isset($this->cfg['params']['f']) ){
      $this->cfg['params']['files'] = $this->cfg['params']['f'];
      unset($this->cfg['params']['f']);
    }
    if ( isset($this->cfg['params']['d']) ){
      $this->cfg['params']['dir'] = $this->cfg['params']['d'];
      unset($this->cfg['params']['d']);
    }
    // File
    if ( !empty($this->cfg['ext']) && is_file(BBN_PUBLIC.$this->cfg['url']) ){
      $res = $this->get_file();
    }
    // Preconfigured
    else if ( isset($this->cfg['params']['id']) ){
      $res = $this->get_preconfig();
    }
    // List of files
    else if ( isset($this->cfg['params']['files']) && is_dir(BBN_PUBLIC.$this->cfg['url']) ){
      $res = $this->get_files();
    }
    // Directory content
    // Vue component
    else if ( isset($this->cfg['params']['dir']) && is_dir(BBN_PUBLIC.$this->cfg['url']) ){
      $res = $this->get_dir();
    }
    else if ( $this->cfg['is_component'] && is_dir(BBN_PUBLIC.$this->cfg['url']) ){
      $res = [];
      $this->cfg['num'] = 0;
      foreach ( $this->cfg['components'] as $cp ){
        $res[$cp] = $this->get_dir($this->cfg['url'].$cp);
        foreach ( $res[$cp] as $type => $files ){
          $this->cfg['num'] += \count($files);
        }
      }
    }
    // Last but not least, libraries!
    else if ( isset($this->cfg['params']['lib']) ){
      $res = $this->get_libs();
      // Adding dirs to config
      if ( !empty($this->cfg['params']['dirs']) ){
        $dirs = explode(',', $this->cfg['params']['dirs']);
        foreach ( $dirs as $d ){
          if ( $r = $this->get_dir($d) ){
            $this->add($res, $r);
          }
        }
      }
    }
    $this->cfg['content'] = $res ?? [];
    return $this;
  }

  /**
   * @param array $r1
   * @param array $r2
   * @return array
   */
  protected function add(array &$r1, array $r2){
    foreach ( $r2 as $i => $r ){
      if ( !isset($r1[$i]) ){
        $r1[$i] = $r;
      }
      else{
        foreach ( $r as $f ){
          if ( !in_array($f, $r1[$i]) ){
            $r1[$i][] = $f;
          }
        }
      }
    }
    return $r1;
  }

  /**
   * @param string $url
   * @return mixed|string
   */
  protected function sanitize(string $url){
    $url = str_replace('//', '/', $url);
    $url = str_replace('..', '', $url);
    return $url;
  }

  /**
   * @return array
   */
  protected function get_file(){
    $supported = false;
    $res = [
      'js' => [],
      'css' => [],
      'html' => []
    ];
    foreach ( self::$types as $type => $extensions ){
      if ( in_array($this->cfg['ext'], $extensions, true) ){
        $res[$type][] = $this->cfg['url'];
        $supported = 1;
        break;
      }
    }
    if ( !$supported && strpos($this->cfg['url'], 'cache/') !== 0 ){
      $this->cfg['cache_file'] = BBN_PUBLIC.$this->cfg['url'];
    }
    return $res;
  }

  /**
   * @return array
   */
  protected function get_preconfig(){
    $db = bbn\db::get_instance();
    $lib = new library($db, $this->cfg['params']['id']);
    return $lib->get_config();
  }

  /**
   * @return array
   */
  protected function get_files(){
    $res = [
      'js' => [],
      'css' => [],
      'html' => []
    ];
    $files = explode(",", $this->cfg['params']['files']);
    foreach ( $files as $f ){
      if ( is_file(BBN_PUBLIC.$this->cfg['url'].'/'.$f) ){
        $ext = bbn\str::file_ext($f);
        if ( basename($f) !== '_def.less' ){
          foreach ( self::$types as $type => $extensions ){
            if ( in_array($ext, $extensions, true) ){
              $res[$type][] = $this->sanitize($this->cfg['url'].'/'.$f);
            }
          }
        }
      }
    }
    return $res;
  }

  /**
   * @param string $dir
   * @return array
   */
  protected function get_dir(string $dir = ''){
    if ( !$dir ){
      $dir = $this->cfg['url'];
    }
    if ( !empty($dir) && (substr($dir, -1) !== '/') ){
      $dir .= '/';
    }
    $res = [
      'js' => [],
      'css' => [],
      'html' => [],
      'lang' => []
    ];
    $files = bbn\file\dir::get_files(BBN_PUBLIC.$dir);
    foreach ( $files as $f ){
      if ( is_file($f) ){
        $ext = bbn\str::file_ext($f);
        $file = basename($f);
        if ( $file !== '_def.less' ){
          foreach ( self::$types as $type => $extensions ){
            if ( \in_array($ext, $extensions, true) ){
              $res[$type][] = $this->sanitize($dir.$file);
            }
          }
        }
      }
    }
    return $res;
  }

  /**
   *
   */
  protected function get_component(){
    $files = bbn\file\dir::get_files(BBN_PUBLIC.$this->cfg['url']);
    foreach ( $files as $f ){
      $ext = bbn\str::file_ext($f);
      foreach ( self::$types as $type => $extensions ){
        if ( \in_array($ext, $extensions, true) ){
          $res[$type][] = $f;
          if ( $ext === 'js' ){
            header('Content-type: text/javascript');
            die(file_get_contents($f));
          }
        }
      }
    }
  }

  /**
   * @return array
   */
  protected function get_libs(){
    if ( $db = bbn\db::get_instance() ){
      $lib = new library($db, $this->cfg['lang'], $this->cfg['latest']);
      $libs = explode(',', $this->cfg['params']['lib']);
      foreach ( $libs as $l ){
        $lib->add($l, $this->cfg['has_dep']);
      }
      return $lib->get_config();
    }
    return [];
  }

  /**
   * config constructor.
   * @param string $request
   */
  public function __construct(string $request){
    if ( !defined('BBN_PUBLIC') ){
      $this->error('You must define the constant BBN_PUBLIC as the root of your public document');
    }
    $parsed = parse_url($request);

    $this->cfg['url'] = empty($parsed['path']) ? '' : substr($parsed['path'], 1);
    if ( !empty($parsed['query']) ){
      parse_str($parsed['query'], $params);
    }
    if ( isset($params, $params['v']) ){
      unset($params['v']);
    }
    $this->cfg['params'] = $params ?? [];
    $this->cfg['hash'] = md5($this->cfg['url'].serialize($this->cfg['params']));
    $this->cfg['cache_file'] = BBN_PUBLIC.'cache/'.$this->cfg['hash'].'.cache';
    $this->cfg['ext'] = bbn\str::file_ext($this->cfg['url']);
    $this->_set_cfg();
    $this->_set_files();
    $file = false;
    // We give the number already when it's components
    if ( empty($this->cfg['num']) ){
      $this->cfg['num'] = 0;
      foreach ( $this->cfg['content'] as $type => $content ){
        if ( ($type !== 'libraries') && \is_array($content) ){
          if ( \count($content) ){
            $this->cfg['num'] += \count($content);
            // For a sole file
            if ( $this->cfg['num'] === 1 ){
              $file = $content[0];
            }
          }
        }
      }
    }
    /*
    //die(var_dump($file, "Heho", $this->cfg['num'], BBN_PUBLIC.$this->cfg['url'], is_file(BBN_PUBLIC.$this->cfg['url'])));
    if ( !$file && !$this->cfg['num'] && is_file(BBN_PUBLIC.$this->cfg['url']) ){
      $this->cfg['num'] = 1;
      $file = $this->cfg['url'];
    }
    */
    if ( !empty($this->cfg['content']['js']) || $this->cfg['is_component'] ){
      $this->mode = 'js';
    }
    else if ( !empty($this->cfg['content']['css']) ){
      $this->mode = 'css';
    }
    else if ( $file && ($this->cfg['num'] === 1) ){
      $this->cfg['file'] = $file;
    }
  }

  /**
   * @return string
   */
  public static function getDefaultLanguage(): string
  {
    return self::$default_language;
  }

  /**
   * @param string $default_language
   */
  public static function setDefaultLanguage(string $default_language): void
  {
    self::$default_language = $default_language;
  }

  /**
   * Returns the configuration array
   * @return array
   */
  public function get(){
    return $this->cfg;
  }

}