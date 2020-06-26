<?php
/**
 * PHP version 7
 *
 * @category CDN
 * @package  BBN
 * @author   Theomas Nabet <thomas.nabet@gmail.com>
 * @license  https://opensource.org/licenses/mit-license.php MIT
 * @version  "GIT: <git_id>"
 * @link     https://www.bbn.io/bbn-php
 */
namespace bbn;

/**
 * (Static) content delivery system through requests using filesystem and internal DB for libraries.
 * 
 * ### Generates in a cache directory a javascript or CSS file based on the request received.
 * 
 * The cdn class will be using all the classes in bbn\cdn in order to 
 * treat a request URL, and return the appropriate content.  
 * 
 * - First it will parse the URL and make a first configuration array out of it, 
 * from which a hash will be calculated
 * * Then it will serve a cache file if it exists and create one otherwise by:
 * * Making a full configuration array using libraries database with all the needed file(s)
 * * Then it will compile these files into a single file that will be put in cache
 * * This file should be of type js or css
 * * If files are both types the content returned will be JS which will call the css files
 * 
 * 
 * 
 * 
 * ### Request can have the following forms:
 * * https://mycdn.net/lib=bbn-vue,jquery
 * * https://mycdn.net/lib=bbnjs|1.0.1|dark,bbn-vue|2.0.2
 * * https://mycdn.net/lib/my_library/?dir=true
 * * https://mycdn.net/lib/my_library/?f=file1.js,file2.js,file3.css
 * 
 * ```php
 * $cdn = new \bbn\cdn($_SERVER['REQUEST_URI']);
 * $cdn->process();
 * if ( $cdn->check() ){
 *   $cdn->output();
 * }
 * ```
 *
 * @category CDN
 * @package  BBN
 * @author   Theomas Nabet <thomas.nabet@gmail.com>
 * @license  https://opensource.org/licenses/mit-license.php MIT
 * @link     https://bbnio2.thomas.lan/bbn-php/doc/class/cdn
 */
class cdn extends models\cls\basic
{

  use cdn\common;


  /**
   * @var string The header that will be placed in the head of the output of a generated file.
   */
  protected const HEAD_COMMENT = '/* This file has been created by the cdn class from BBN PHP library
 * Please visit https://www.bbn.io
 * To update this script, go to:
 * %s
 * %s
 * Enjoy!
 */

';

  /**
   * @var string Will be added to the HEAD_COMMENT if it is not minified
   */
  protected const TEST_ST    = 'You can remove the test parameter to the URL to get a minified version';

  /**
   * @var string Will be added to the HEAD_COMMENT if it is minified
   */
  protected const NO_TEST_ST = 'You can add &test=1 to get an uncompressed version';

  /**
   * @var string
   */
  protected $mode;

  /**
   * @var db A connection to the CDN database
   */
  protected $db;

  /**
   * @var array The file extensions that can be generated
   */
  protected $extensions = ['js', 'css'];

  /**
   * @var array A list of the needed files
   */
  protected $files = [];

  /**
   * @var string The directory from which
   */
  protected $dir;

  /**
   * @var string The path to the cache file where the file is generated
   */
  protected $cache_path = 'cache/';

  /**
   * @var int The maximum duration of the cache in seconds
   */
  protected $cache_length = 3600;

  /**
   * @var int A timestamp of the cache file if it exists
   */
  protected $file_mtime;

  /**
   * @var string The request received
   */
  protected $request;

  /**
   * @var $o
   */
  protected $o;

  /**
   * @var string
   */
  protected $url = '';

  /**
   * @var string The unique hash of this configuration, which will be the basename of the file
   */
  protected $hash;

  /**
   * @var string The language requested if any
   */
  protected $language;

  /**
   * @var array A configuration array which will contain all the specs
   */
  protected $cfg;

  /**
   * @var $list
   */
  protected $list;

  /**
   * @var cdn\compiler The compiler object that will be used for the generation
   */
  protected $cp;

  /**
   * @var string The generated file's extension
   */
  protected $ext = '';

  /**
   * @var
   */
  public $alert;

  /**
   * @var
   */
  public $code;

  /**
   * Constructor.
   * 
   * Generates a configuration based on the given request and instantiate 
   * a compiler for the response.  
   * If *$db* is not not given the current instance if any will be used.
   * 
   * @param string  $request The original request sent to the server
   * @param db|null $db      The DB connection with the libraries tables
   */
  public function __construct(string $request, db $db = null)
  {
    // Need to be in a bbn environment, this is the absolute path of the server's root directory
    if (!defined('BBN_PUBLIC')) {
      $this->error('You must define the constant $this->fpath as the root of your public document');
      die('You must define the constant $this->fpath as the root of your public document');
    }
    /** @todo Remove? */
    $this->_set_prefix();
    if (!$db) {
      $db = db::get_instance();
    }
    if ($db) {
      $this->db = $db;
    }
    $this->request = $request;
    // Creation of a config object
    $config = new cdn\config($request, $this->db);
    // Checking request validity
    if ($config->check()) {
      // Getting a configuration array
      $this->cfg = $config->get();
      if (!empty($this->cfg['content']['js']) || $this->cfg['is_component']) {
        $this->mode = 'js';
      }
      else{
        if (!empty($this->cfg['content']['css'])) {
          $this->mode = 'css';
        }
      }
      if ($this->mode) {
        $this->cp = new cdn\compiler($this->cfg);
      }
    }
  }

  /**
   * @return self
   */
  public function process()
  {
    $code = '';
    // One file at least
    if ($this->cfg['num']) {
      // Cache should be checked quickly if in prod, deeply if in dev
      /** Do not check the files, send the cache file if not in dev */
      if (!$this->check_cache($this->cfg['test'])) {
        $c =& $this->cfg;
        // New cache file time
        $this->file_mtime = time();
        if ($c['is_component']) {
          $code = $this->get_components();
        }
        else{
          if ($c['grouped']) {
            $codes = $this->cp->group_compile($this->mode === 'css' ? $c['content']['css'] : $c['content']['js'], $c['test']);
          }
          elseif ($this->mode) {
            $codes = $this->cp->compile($this->mode === 'css' ? $c['content']['css'] : $c['content']['js'], $c['test']);
          }
          if ($codes) {
            if ($this->mode === 'css') {
              $code = $this->get_css($codes);
            }
            elseif ($this->mode === 'js') {
              $code = $this->get_js($codes, empty($c['nocompil']) ? true : false);
            }
          }
        }
        if ($code) {
          if (defined('BBN_IS_DEV') && BBN_IS_DEV) {
            $code = sprintf(
              self::HEAD_COMMENT,
              $this->furl.$this->request,
              $c['test'] ? self::TEST_ST : self::NO_TEST_ST
            ).$code;
          }
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
    if (!parent::check()) {
      return false;
    }
    $file = empty($this->cfg['file']) || $this->cfg['is_component'] ? $this->cfg['cache_file'] : $this->fpath.$this->cfg['file'];
    if ($file && is_file($file)) {
      return true;
    }
    x::log("Impossible to find $file", 'cdn_errors');
    return false;
  }

  /**
   * @param bool $real
   * @return bool
   */
  public function check_cache($real = true)
  {
    if (is_file($this->cfg['cache_file'])) {
      $last_modified = time();
      $this->file_mtime = filemtime($this->cfg['cache_file']);
      $c =& $this->cfg;
      // Only checks if the file exists and is valid
      if (!$real 
          && \is_array($c['content']) 
          && (($last_modified - $this->file_mtime) < $this->cache_length)
      ) {
        return true;
      }
      clearstatcache();
      // Real research for last mods and generation timestamps
      if ($c['is_component']) {
        foreach ($c['content'] as $name => $cp){
          foreach ($cp as $type => $files){
            foreach ($files as $f){
              if (is_file($this->fpath.$f)) {
                $last_modified = filemtime($this->fpath.$f);
                if ($last_modified > $this->file_mtime) {
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
        foreach ($this->cfg['content'][$this->mode] as $f){
          if (is_file($this->fpath.$f)) {
            $last_modified = filemtime($this->fpath.$f);
            if ($last_modified > $this->file_mtime) {
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
    $file = empty($this->cfg['file']) || $this->cfg['is_component'] ? $this->cfg['cache_file'] : $this->fpath.$this->cfg['file'];
    if ($file && is_file($file)) {
      // get the HTTP_IF_MODIFIED_SINCE header if set
      $client_if_modified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? false;
      // get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash)
      $client_tag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim(str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH']))) : false;


      // We get a unique hash of this file (etag)
      $file_tag = md5($file.$this->file_mtime);


      //die(var_dump($this->file_mtime, $client_tag, $etagFile, $client_if_modified, $_SERVER));
      if ($this->mode === 'css') {
        header('Content-type: text/css; charset=utf-8');
      }
      else{
        if ($this->mode === 'js') {
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
      if ($client_if_modified 
          && ((strtotime($client_if_modified) == $this->file_mtime) 
          || ($client_tag == $file_tag)          )
      ) {
        header('HTTP/1.1 304 Not Modified');
      }
      else{
        if (empty($this->cfg['file']) && (($this->mode === 'js') 
            || ($this->mode === 'css')            ) 
        ) {
          if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) 
              && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
          ) {
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
   * @param bool  $encapsulated
   * @return string
   */
  protected function get_js(array $codes, $encapsulated = true): string
  {
    $code = '';
    if (!empty($codes['js'])) {
      $num = count($codes['js']);
      $root_url = $this->furl;
      foreach ($codes['js'] as $c){
        $tmp = $c['code'];
        if (empty($this->cfg['nocompil'])) {
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
        if (!empty($tmp)) {
          $code .= $tmp.($this->cfg['test'] ? str_repeat(PHP_EOL, 5) : PHP_EOL);
        }
      }
      if (!empty($this->cfg['content']['css'])) {
        $code .= <<<JS
    return (new Promise(function(bbn_resolve, bbn_reject){
      bbn_resolve()
    }))

JS;
        
        foreach ($this->cfg['content']['includes'] as $lib) {
          if (!empty($lib['css'])) {
            $code .= $this->cp->css_links(
              array_map(
                function ($a) use ($lib) {
                  return $lib['path'].$a;
                },
                $lib['css']
              ),
              $this->cfg['test'],
              $lib['prepend']
            );
          }
        }
      }
      if ($encapsulated) {
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
    if (!empty($codes['css'])) {
      foreach ($codes['css'] as $c){
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
    if (\is_array($c['content'])) {
      $i = 0;
      $includes = '';
      foreach ($c['content'] as $name => $cp){
        foreach ($cp['js'] as $js){
          $ext = str::file_ext($js, true);
          //x::dump($codes);
          // A js file with the component name is mandatory
          if ($ext[0] === $name) {
            // Once found only this js file will be used as it should just define the component
            $jsc = $this->cp->compile([$js], $c['test']);
            $codes[$i] = [
              'name' => $name,
              'js' => $jsc['js'][0]['code']
            ];
            if (!empty($cp['css'])) {
              $cssc = $this->cp->compile($cp['css'], $c['test']);
              foreach ($cssc['css'] as $css){
                if (!isset($css['code'])) {
                  throw new \Exception("Impossible to get the SCSS code from component ".$cp);
                  //die(var_dump($css));
                }
                if ($this->cp->has_links($css['code'])) {
                  $includes .= $this->cp->css_links($cp['css'], $c['test']);
                  unset($cp['css']);
                  break;
                }
              }
              if (isset($cp['css'])) {
                $codes[$i]['css'] = array_map(
                  function ($a) {
                    return $a['code'];
                  }, $cssc['css']
                );
              }
            }
            if (!empty($c['lang']) 
                && !empty($cp['lang']) 
                && \in_array(\dirname($js)."/$name.$c[lang].lang", $cp['lang'], true)
            ) {
              $lang = file_get_contents($this->fpath.\dirname($js)."/$name.$c[lang].lang");
              if ($lang) {
                //$lang = json_decode($lang, true);
                $codes[$i]['js'] = "if ( window.bbn ){ bbn.fn.autoExtend('lng', $lang); }".PHP_EOL.$codes[$i]['js'];
              }
            }

            // Dependencies links
            $dep_path = $this->fpath.$jsc['js'][0]['dir'].'/';
            if (is_file($dep_path.'bbn.json')) {
              $json = json_decode(file_get_contents($dep_path.'bbn.json'), true);
            }
            else{
              if (is_file($dep_path.'bower.json')) {
                $json = json_decode(file_get_contents($dep_path.'bower.json'), true);
              }
            }
            if (!empty($json)) {
              if (!empty($json['dependencies'])) {
                $lib = new cdn\library($this->db, $this->cfg['lang'], true);
                foreach ($json['dependencies'] as $l => $version){
                  $lib->add($l);
                }
                if ($cfg = $lib->get_config()) {
                  if (!empty($cfg['css'])) {
                    $includes .= $this->cp->css_links($cfg['css'], $this->cfg['test']);
                  }
                  if (!empty($cfg['js'])) {
                    $includes .= $this->cp->js_links($cfg['js'], $this->cfg['test']);
                  }
                }
              }
              if (!empty($json['components'])) {
                /** @todo Add dependent components */
              }
            }


            // HTML inclusion
            $html = [];
            if (!empty($cp['html'])) {
              foreach ($cp['html'] as $f){
                if ($tmp = $this->cp->get_content($f, $c['test'])) {
                  $component_name = str::file_ext($f, true)[0];
                  if ($name !== $component_name) {
                    $component_name = $name.'-'.$component_name;
                  }
                  $html[] = [
                    'name' => $component_name,
                    'content' => $tmp
                  ];
                }
              }
            }
            if (!empty($html)) {
              $codes[$i]['html'] = $html;
            }
            $i++;
            break;
          }
        }
      }

      if ($codes) {
        $str = '';
        foreach ($codes as $cd){
          $str .= "{name: '$cd[name]', script: function(){try{ $cd[js] } catch(e){bbn.fn.log(e.message); throw new Error('Impossible to load component $cd[name]');}}";
          if (!empty($cd['css'])) {
            $str .= ', css: '.json_encode($cd['css']);
          }
          if (!empty($cd['html'])) {
            $str .= ', html: '.json_encode($cd['html']);
          }
          $str .= '},';
        }
        $code = <<<JAVASCRIPT

(function(){
  return (new Promise(function(bbn_resolve, bbn_reject){
    setTimeout(function(){
      bbn_resolve();
    })
  }))
  $includes
  .then(function(){
    return bbnAddGlobalScript(function(){
      return [$str]
    })
  })
})()
JAVASCRIPT;
      }
      return $code;
    }
  }
}
