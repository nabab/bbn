<?php

namespace bbn;

/**
 * Manages the whole CDN process from analyzing the request to releasing the output
 * @package cdn
 */
class cdn extends models\cls\basic
{

  use cdn\common;

  /**
   *
   */
  protected const head_comment = '/* This file has been created by the cdn class from BBN PHP library
 * Please visit http://www.bbn.solutions
 * To update this script, go to:
 * %s
 * %s
 * Enjoy!
 */

';
  /**
   * We show this phrase in the header of a non compressed (test) file
   */
  protected const test_st    = 'You can remove the test parameter to the URL to get a minified version';

  /**
   * We show this phrase in the header of a compressed (non test) file
   */
  protected const no_test_st = 'You can add &test=1 to get an uncompressed version';

  /**
   * @var null|string
   */
  protected $mode;
  /**
   * @var array
   */
  protected $extensions = ['js', 'css'];
  /**
   * @var array
   */
  protected $files = [];
  /**
   * @var
   */
  protected $dir;
  /**
   * @var null|string
   */
  protected $cache_path = 'cache/';
  /**
   * @var int
   */
  protected $cache_length = 3600;
  /**
   * @var
   */
  protected $file_mtime;
  /**
   * @var string
   */
  protected $request;
  /**
   * @var
   */
  protected $o;
  /**
   * @var string
   */
  protected $url = '';
  /**
   * @var
   */
  protected $hash;
  /**
   * @var
   */
  protected $language;
  /**
   * @var array
   */
  protected $cfg;
  /**
   * @var
   */
  protected $list;
  /**
   * @var compiler
   */
  protected $cp;
  /**
   * @var string
   */
  protected $ext = '';

  /**
   * @var
   */
  /**
   * @var
   */
  public
    $alert,
    $code;

  /**
   * cdn constructor.
   * @param string $request The original request
   * @param string|null $cache If given will point the cache file to serve
   */
  public function __construct(string $request, string $cache = null)
  {
    if ( !defined('BBN_PUBLIC') ){
      $this->error('You must define the constant BBN_PUBLIC as the root of your public document');
      die('You must define the constant BBN_PUBLIC as the root of your public document');
    }
    if ( $cache && is_dir(BBN_PUBLIC.$cache) ){
      if ( substr($cache, -1) !== '/' ){
        $cache .= '/';
      }
      $this->cache_path = $cache;
    }
    $this->request = $request;
    // Creation of a config object
    $config = new cdn\config($request);
    // Checking request validity
    if ( $config->check() ){
      // Getting a configuration array
      $this->cfg = $config->get();
      if ( !empty($this->cfg['content']['js']) || $this->cfg['is_component'] ){
        $this->mode = 'js';
      }
      else{
        if ( !empty($this->cfg['content']['css']) ){
          $this->mode = 'css';
        }
      }
      if ( $this->mode ){
        $this->cp = new cdn\compiler();
      }
    }
  }

  /**
   * @param $code
   * @return string
   */
  protected function js_mask(string $code): string
  {
    $test = empty($this->cfg['test']) ? 'false' : 'true';
    return <<<JS
(function(window){
  if ( this.bbnAddGlobalScript === undefined ){
    this.bbnAddGlobalScript = function(fn){
      return fn();
    }
    this.bbnLoadedFiles = [];
    this.bbnMinified = $test;
    this.bbnLoadFile = function(file){
      if ( file.substr(0, 1) === '/' ){
        file = file.substr(1);
      }
      if (
        (window.bbnLoadedFiles !== undefined) &&
        (window.bbnLoadedFiles.length !== undefined)
      ){
        for ( var j = 0; j < bbnLoadedFiles.length; j++ ){
          if ( bbnLoadedFiles[j] === file ){
            return false;
          }
        }
        bbnLoadedFiles.push(file);
        return true;
      }
    };
  }
  $code
  
})(window);

JS;
  }

  /**
   * @param array $codes
   * @param bool $encapsulated
   * @return string
   */
  protected function get_js(array $codes, $encapsulated = true): string
  {
    $code = '';
    if ( !empty($codes['js']) ){
      $num = count($codes['js']);
      $root_url = BBN_URL;
      foreach ( $codes['js'] as $c ){
        $tmp = $c['code'];
        if ( empty($this->cfg['nocompil']) ){
          $tmp = <<<JS
bbnAddGlobalScript(function(){
  // $num
  bbnLoadFile("$c[dir]/$c[file]");
  var bbn_language = "{$this->cfg['lang']}",
      bbn_root_dir = "$c[dir]/",
      bbn_root_url = "$root_url";
      $tmp
});
JS;
        }
        if ( !empty($tmp) ){
          $code .= $tmp.($this->cfg['test'] ? str_repeat(PHP_EOL, 5) : PHP_EOL);
        }
      }
      if ( !empty($this->cfg['content']['css']) ){
        $code .= <<<JS
    return (new Promise(function(bbn_resolve, bbn_reject){
      bbn_resolve('ok')
    }))

JS;

        $code .= $this->cp->css_links($this->cfg['content']['css'], $this->cfg['test']);
      }
      if ( $encapsulated ){
        $code = $this->js_mask($code);
      }
    }
    return $code;
  }

  /**
   * @param array $codes
   * @return string
   */
  protected function get_css(array $codes)
  {
    $code = '';
    if ( !empty($codes['css']) ){
      foreach ( $codes['css'] as $c ){
        $code .= $c['code'].($this->cfg['test'] ? str_repeat(PHP_EOL, 5) : PHP_EOL);
      }
    }
    return $code;
  }

  /**
   * @param array $codes
   * @return string
   */
  protected function get_components()
  {
    $code = '';
    $codes = [];
    $c =& $this->cfg;
    if ( \is_array($c['content']) ){
      $i = 0;
      $includes = '';
      foreach ( $c['content'] as $name => $cp ){
        foreach ( $cp['js'] as $js ){
          $ext = str::file_ext($js, true);
          //x::dump($codes);
          // A js file with the component name is mandatory
          if ( $ext[0] === $name ){
            // Once found only this js file will be used as it should just define the component
            $jsc = $this->cp->compile([$js], $c['test']);
            $codes[$i] = [
              'name' => $name,
              'js' => $jsc['js'][0]['code']
            ];
            if ( !empty($cp['css']) ){
              $cssc = $this->cp->compile($cp['css'], $c['test']);
              foreach ( $cssc['css'] as $css ){
                if ( !isset($css['code']) ){
                  die(var_dump($css));
                }
                if ( $this->cp->has_links($css['code']) ){
                  $includes .= $this->cp->css_links($cp['css'], $c['test']);
                  unset($cp['css']);
                  break;
                }
              }
              if ( isset($cp['css']) ){
                $codes[$i]['css'] = array_map(function($a){
                  return $a['code'];
                }, $cssc['css']);
              }
            }

            // Dependencies links
            $dep_path = BBN_PUBLIC.$jsc['js'][0]['dir'].'/';
            if ( is_file($dep_path.'bbn.json') ){
              $json = json_decode(file_get_contents($dep_path.'bbn.json'), true);
            }
            else{
              if ( is_file($dep_path.'bower.json') ){
                $json = json_decode(file_get_contents($dep_path.'bower.json'), true);
              }
            }
            if (
              !empty($json) &&
              !empty($json['dependencies']) &&
              ($db = db::get_instance())
            ){
              $lib = new cdn\library($db, $this->cfg['lang'], true);
              foreach ( $json['dependencies'] as $l => $version ){
                $lib->add($l);
              }
              if ( $cfg = $lib->get_config() ){
                if ( !empty($cfg['css']) ){
                  $includes .= $this->cp->css_links($cfg['css'], $this->cfg['test']);
                }
                if ( !empty($cfg['js']) ){
                  $includes .= $this->cp->js_links($cfg['js'], $this->cfg['test']);
                }
              }
            }


            // HTML inclusion
            $html = [];
            if ( !empty($cp['html']) ){
              foreach ( $cp['html'] as $f ){
                if ( $tmp = $this->cp->get_content($f, $c['test']) ){
                  $component_name = str::file_ext($f, true)[0];
                  if ( $name !== $component_name ){
                    $component_name = $name.'-'.$component_name;
                  }
                  $html[] = [
                    'name' => $component_name,
                    'content' => $tmp
                  ];
                }
              }
            }
            if ( !empty($html) ){
              $codes[$i]['html'] = $html;
            }
            $i++;
            break;
          }
        }
      }

      if ( $codes ){
        $str = '';
        foreach ( $codes as $cd ){
          $str .= "{name: '$cd[name]', script: function(){bbn.fn.info('Loading component $cd[name]... :)');$cd[js]}";
          if ( !empty($cd['css']) ){
            $str .= ', css: '.json_encode($cd['css']);
          }
          if ( !empty($cd['html']) ){
            $str .= ', html: '.json_encode($cd['html']);
          }
          $str .= '},';
        }
        $code = <<<JS

(function(){
  return (new Promise(function(bbn_resolve, bbn_reject){
    setTimeout(function(){
      bbn_resolve('ok');
    }, 1)
  }))
  $includes
  .then(function(){
    return bbnAddGlobalScript(function(){
      return [$str]
    })
  })
})()
JS;
      }
      return $code;
    }
  }

  /**
   * @return $this
   */
  public function process()
  {
    $code = '';
    // One file at least
    if ( $this->cfg['num'] ){
      // Cache should be checked quickly if in prod, deeply if in dev
      /** Do not check the files, send the cache file if not in dev */
      if ( !$this->check_cache($this->cfg['test']) ){
        $c =& $this->cfg;
        // New cache file time
        $this->file_mtime = time();
        if ( $c['is_component'] ){
          $code = $this->get_components();
        }
        else if ( $this->mode && ($codes = $this->cp->compile($this->mode === 'css' ? $c['content']['css'] : $c['content']['js'], $c['test'])) ){
          if ( $this->mode === 'css' ){
            $code = $this->get_css($codes);
          }
          else if ( $this->mode === 'js' ){
            $code = $this->get_js($codes, empty($c['nocompil']) ? true : false);
          }
        }
        if ( $code ){
          $code = sprintf(
              self::head_comment,
              BBN_URL.$this->request,
              $c['test'] ? self::test_st : self::no_test_st
            ).$code;
          file_put_contents($c['cache_file'], $code);
          file_put_contents($c['cache_file'].'.gzip', gzencode($code));
        }
      }
    }
    return $this;
  }

  /**
   * @return array|bool|compiler|string
   */
  public function get_cfg()
  {
    return $this->cfg;
  }

  /**
   * @return bool
   */
  public function check()
  {
    if ( !parent::check() ){
      return false;
    }
    $file = empty($this->cfg['file']) || $this->cfg['is_component'] ? $this->cfg['cache_file'] : BBN_PUBLIC.$this->cfg['file'];
    return $file && is_file($file);
  }

  /**
   * @param bool $real
   * @return bool
   */
  public function check_cache($real = true)
  {
    if ( is_file($this->cfg['cache_file']) ){
      $last_modified = time();
      $this->file_mtime = filemtime($this->cfg['cache_file']);
      $c =& $this->cfg;
      // Only checks if the file exists and is valid
      if (
        !$real &&
        \is_array($c['content']) &&
        (($last_modified - $this->file_mtime) < $this->cache_length)
      ){
        return true;
      }
      clearstatcache();
      // Real research for last mods and generation timestamps
      if ( $c['is_component'] ){
        foreach ( $c['content'] as $name => $cp ){
          foreach ( $cp as $type => $files ){
            foreach ( $files as $f ){
              if ( is_file(BBN_PUBLIC.$f) ){
                $last_modified = filemtime(BBN_PUBLIC.$f);
                if ( $last_modified > $this->file_mtime ){
                  return false;
                }
              }
              else{
                die("I can't find the file $f kkk!");
              }
            }
          }
        }
      }
      else{
        foreach ( $this->cfg['content'][$this->mode] as $f ){
          if ( is_file(BBN_PUBLIC.$f) ){
            $last_modified = filemtime(BBN_PUBLIC.$f);
            if ( $last_modified > $this->file_mtime ){
              return false;
            }
          }
          else{
            \bbn\x::hdump($this->cfg);
            die("I can't find the file $f  mmm!");
          }
        }
      }
      return true;
    }
    return false;
  }

  /**
   *
   */
  public function output()
  {
    $file = empty($this->cfg['file']) || $this->cfg['is_component'] ? $this->cfg['cache_file'] : BBN_PUBLIC.$this->cfg['file'];
    if ( $file && is_file($file) ){
      // get the HTTP_IF_MODIFIED_SINCE header if set
      $client_if_modified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? false;
      // get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash)
      $client_tag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim(str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH']))) : false;


      // We get a unique hash of this file (etag)
      $file_tag = md5($file.$this->file_mtime);


      //die(var_dump($this->file_mtime, $client_tag, $etagFile, $client_if_modified, $_SERVER));
      if ( $this->mode === 'css' ){
        header('Content-type: text/css; charset=utf-8');
      }
      else{
        if ( $this->mode === 'js' ){
          header('Content-type: text/javascript; charset=utf-8');
        }
        else{
          $mime = finfo_open(FILEINFO_MIME_TYPE);
          header('Content-type: '.finfo_file($mime, $file));
        }
      }
      // make sure caching is turned on
      header('Cache-Control: max-age=14400');
      header('Expires: '.gmdate('D, d M Y H:i:s', time() + 14400).' GMT');
      // set last-modified header
      header('Date: '.gmdate('D, d M Y H:i:s', $this->file_mtime).' GMT');
      header('Last-Modified: '.gmdate('D, d M Y H:i:s', $this->file_mtime).' GMT');
      // set etag-header
      header("ETag: $file_tag");
      //header('Pragma: public');

      // check if page has changed. If not, send 304 and exit
      if (
        $client_if_modified &&
        (
          (strtotime($client_if_modified) == $this->file_mtime) ||
          ($client_tag == $file_tag)
        )
      ){
        header('HTTP/1.1 304 Not Modified');
      }
      else{
        if ( empty($this->cfg['file']) && (
            ($this->mode === 'js') ||
            ($this->mode === 'css')
          ) ){
          if (
            isset($_SERVER['HTTP_ACCEPT_ENCODING']) &&
            (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
          ){
            header('Content-Encoding: gzip');
            $file .= '.gzip';
          }
        }
        readfile($file);
      }
      exit;
    }
    die('No cache file '.$file);
  }
}