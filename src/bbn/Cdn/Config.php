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
namespace bbn\Cdn;

use bbn;
use bbn\X;

/**
 * Makes a usable configuration array out of a request string.
 * 
 * @category CDN
 * @package  BBN
 * @author   Theomas Nabet <thomas.nabet@gmail.com>
 * @license  https://opensource.org/licenses/mit-license.php MIT
 * @link     https://bbnio2.thomas.lan/bbn-php/doc/class/cdn/library
 */
class Config extends bbn\Models\Cls\Basic
{
  use Common;

  /**
   * @var string The default language for the libraries
   */
  protected static $default_language = 'en';

  /**
   * @var array The configuration array
   */
  protected $cfg = [];

  /**
   * Constructor.
   * 
   * @example
   * ```php
   * // @var bbn\Db $db
   * $cfg = new \bbn\Cdn\Config('/lib?lib=moment,vuejs', $db);
   * ```
   * 
   * @param string      $request A request string
   * @param bbn\Db|null $db      A DB connection to the libraries' tables (if needed)
   */
  public function __construct(string|null $request = null, bbn\Db $db = null)
  {
    // Need to be in a bbn environment, this is the absolute path of the server's root directory
    if (!defined('BBN_PUBLIC')) {
      $this->error('You must define the constant BBN_PUBLIC as the root of your public document');
    }
    $this->_set_prefix();
    if (!$db) {
      $db = bbn\Db::getInstance();
    }
    if (!$db) {
      die(X::_('Impossible to initialize the CDN without a DB connection'));
    }
    $this->db = $db;
    if ($request) {
      $this->setCfgFromRequest($request);
    }
  }

  /**
   * Gets the default language of the libraries requested.
   * 
   * @return string
   */
  public static function getDefaultLanguage(): string
  {
    return self::$default_language;
  }

  /**
   * Sets the default language of the libraries requested.
   * 
   * @param string $default_language The default language
   * @return void
   */
  public static function setDefaultLanguage(string $default_language): void
  {
    self::$default_language = $default_language;
  }

  /**
   * Returns the configuration array.
   * 
   * @example
   * ```php
   * // @var bbn\Cdn\Config $cfg
   * X::hdump($cfg->get());
   * // {
   * //     "url": "lib",
   * //     "params": {
   * //         "lib": "jquery",
   * //     },
   * //     "hash": "34b6416f721c044661972951310895a8",
   * //     "cache_file": "/home/bbn/public_html/cache/34b6416f721c044661972951310895a8.cache",
   * //     "ext": "",
   * //     "grouped": false,
   * //     "test": false,
   * //     "lang": "en",
   * //     "nocompil": false,
   * //     "has_css": true,
   * //     "has_dep": true,
   * //     "latest": false,
   * //     "is_component": false,
   * //     "components": false,
   * //     "content": {
   * //         "libraries": {
   * //             "jquery": "3.3.1",
   * //         },
   * //         "prepend": [
   * //         ],
   * //         "includes": [
   * //             {
   * //                 "version": "3.3.1",
   * //                 "prepend": [
   * //                 ],
   * //                 "name": "jquery",
   * //                 "path": "lib/jquery/3.3.1/",
   * //                 "js": [
   * //                     "dist/jquery.min.js",
   * //                 ],
   * //             },
   * //         ],
   * //         "js": [
   * //             "lib/jquery/3.3.1/dist/jquery.min.js",
   * //         ],
   * //     },
   * //     "num": 2,
   * // }
   * ```
   * 
   * @return array
   */
  public function get(): array
  {
    return $this->cfg;
  }

  /**
   * Sets the config based on a URL.
   * 
   * @example
   * ```php
   * $this->setCfgFromRequest('https://example.com/?lib=bbn-vue|latest|dark');
   * ```
   * 
   * @param string $request The requested URL
   * @return self
   */
  protected function setCfgFromRequest(string $request): self
  {
    $parsed = parse_url($request);
    // URL without the root slash
    $this->cfg['url'] = empty($parsed['path']) ? '' : substr($parsed['path'], 1 + strlen($this->prefix));
    if (!empty($parsed['query'])) {
      parse_str($parsed['query'], $params);
    }
    // contains the parameters sent by the query
    $this->cfg['params'] = $params ?? [];
    // The hash is an md5 of URL + parameters
    $this->cfg['hash'] = md5($this->cfg['url'].serialize($this->cfg['params']));
    // The cache file is in the cache directory and has the hash as name
    $this->cfg['cache_file'] = $this->fpath.'cache/'.$this->cfg['hash'].'.cache';
    // If a specific file is pointed to, ext will be its extension
    $this->cfg['ext'] = bbn\Str::fileExt($this->cfg['url']);
    // Grouped
    $this->cfg['grouped'] = !empty($this->cfg['params']['grouped']);
    // Processing the config
    $this->_setCfg();
    // Setting the list of needed files 
    $this->setFiles();
    $file = false;
    // We give the number already when it's components
    if (empty($this->cfg['num'])) {
      $this->cfg['num'] = 0;
      foreach ($this->cfg['content'] as $type => $content) {
        if (($type !== 'libraries') && \is_array($content)) {
          if (\count($content)) {
            $this->cfg['num'] += \count($content);
            // For a sole file
            if ($this->cfg['num'] === 1) {
              $file = $content[0];
            }
          }
        }
      }
    }
    // If there are javascript files, mode is js
    if (!empty($this->cfg['content']['js']) || $this->cfg['is_component']) {
      $this->mode = 'js';
    }
    // Otherwise if there are css files, mode is css
    elseif (!empty($this->cfg['content']['css'])) {
      $this->mode = 'css';
    }
    // Otherwise if a single file is pointed to we define the property file
    elseif ($file && ($this->cfg['num'] === 1)) {
      $this->cfg['file'] = $file;
    }
    return $this;
  }

  /**
   * Returns an array of arrays of types and files.
   * 
   * It will use one of the different methods for retrieving files depending on the params sent.
   * 
   * @return self
   */
  protected function setFiles(): self
  {
    // Shortcuts for files and dir
    if (X::hasProp($this->cfg['params'], 'f', true)) {
      $this->cfg['params']['files'] = $this->cfg['params']['f'];
      unset($this->cfg['params']['f']);
    }
    if (X::hasProp($this->cfg['params'], 'd', true)) {
      $this->cfg['params']['dir'] = $this->cfg['params']['d'];
      unset($this->cfg['params']['d']);
    }
    // File
    if (!empty($this->cfg['ext']) && is_file($this->fpath.$this->cfg['url'])) {
      $res = $this->getFile();
    }
    // Preconfigured
    elseif (X::hasProp($this->cfg['params'], 'id', true)) {
      $res = $this->getPreconfig();
    }
    // List of files
    elseif (X::hasProp($this->cfg['params'], 'files', true) && is_dir($this->fpath.$this->cfg['url'])) {
      $res = $this->getFiles();
    }
    // Directory content
    // Vue component
    elseif (!empty($this->cfg['params']['dir']) && is_dir($this->fpath.$this->cfg['url'])) {
      $res = $this->getDir();
    }
    elseif ($this->cfg['is_component'] && is_dir($this->fpath.$this->cfg['url'])) {
      $res = [];
      $this->cfg['num'] = 0;
      foreach ($this->cfg['components'] as $cp) {
        if (!isset($res[$cp])) {
          $res[$cp] = $this->getDir($this->cfg['url'].$cp);
          if ($res[$cp]) {
            $dir = false;
            foreach ($res[$cp] as $type => $files) {
              if (!$dir && count($files)) {
                $dir = $this->fpath.X::dirname($files[0]);
              }
              $this->cfg['num'] += \count($files);
            }
            /*
            if ( $dir && is_file($dir.'/bbn.json') ){
              $json = json_decode(file_get_contents($dir.'/bbn.json'));
              if ( isset($json->components) ){
                foreach ( $json->components as $tmp ){
                  if ( !isset($res[$tmp]) && !\in_array($tmp, $this->cfg['components'], true) ){
                    $this->cfg['components'][] = $tmp;
                    goto cpStart;
                    break;
                  }
                }
              }
            }
            */
          }
        }
      }
    }
    // Last but not least, libraries!
    elseif (X::hasProp($this->cfg['params'], 'lib', true)) {
      $res = $this->getLibraries();
      // Adding dirs to config
      if (!empty($this->cfg['params']['dirs'])) {
        $dirs = explode(',', $this->cfg['params']['dirs']);
        foreach ($dirs as $d) {
          if ($r = $this->getDir($d)) {
            $this->add($res, $r);
          }
        }
      }
    }
    $this->cfg['content'] = $res ?? [];
    return $this;
  }

  /**
   * Adds a configuration to an existing one (combine them).
   * 
   * @param array $r1 The original configuration
   * @param array $r2 The configuration to add
   * @return array
   */
  protected function add(array &$r1, array $r2): array
  {
    foreach ($r2 as $i => $r) {
      if (!isset($r1[$i])) {
        $r1[$i] = $r;
      }
      else{
        foreach ($r as $f) {
          if (!in_array($f, $r1[$i])) {
            $r1[$i][] = $f;
          }
        }
      }
    }
    return $r1;
  }

  /**
   * Sanitizes a URL.
   * 
   * @param string $url A URL
   * @return mixed|string
   */
  protected function sanitize(string $url): string
  {
    $url = str_replace('//', '/', $url);
    $url = str_replace('..', '', $url);
    return $url;
  }

  /**
   * @return array
   */
  protected function getFile(): array
  {
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
      $this->cfg['cache_file'] = $this->fpath.$this->cfg['url'];
    }
    return $res;
  }

  /**
   * @return array
   */
  protected function getPreconfig(): array
  {
    $lib = new Library($this->db, $this->cfg['params']['id']);
    $cfg = $lib->getConfig();
    return $cfg;
  }

  /**
   * @return array
   */
  protected function getFiles(): array
  {
    $res = [
      'js' => [],
      'css' => [],
      'html' => []
    ];
    $files = explode(",", $this->cfg['params']['files']);
    foreach ($files as $f) {
      if (is_file($this->fpath.$this->cfg['url'].'/'.$f)) {
        $ext = bbn\Str::fileExt($f);
        foreach (self::$types as $type => $extensions) {
          if (in_array($ext, $extensions, true)) {
            $res[$type][] = $this->sanitize($this->cfg['url'].'/'.$f);
          }
        }
      }
    }
    return $res;
  }

  /**
   * @param string $dir The directory name
   * @return array
   */
  protected function getDir(string $dir = ''): array
  {
    if (!$dir) {
      $dir = $this->cfg['url'];
    }
    if (!empty($dir) && (substr($dir, -1) !== '/')) {
      $dir .= '/';
    }
    $res = [
      'js' => [],
      'css' => [],
      'html' => [],
      'lang' => []
    ];
    $files = bbn\File\Dir::getFiles($this->fpath.$dir);
    foreach ($files as $f) {
      if (is_file($f)) {
        $ext = bbn\Str::fileExt($f);
        $file = X::basename($f);
        if ($file !== '_def.less') {
          foreach (self::$types as $type => $extensions) {
            if (\in_array($ext, $extensions, true) ){
              $res[$type][] = $this->sanitize($dir.$file);
            }
          }
        }
      }
    }
    return $res;
  }

  /**
   * @return array
   */
  protected function getLibraries(): array
  {
    if (!empty($this->cfg['params']['lib'])) {
      $lib = new Library($this->db, $this->cfg['lang']);
      $libs = explode(',', $this->cfg['params']['lib']);
      foreach ($libs as $l) {
        $lib->add($l, $this->cfg['has_dep']);
      }
      return $lib->getConfig();
    }
    return [];
  }

  /**
   * Sets the config array with all the - default or no - config parameters.
   * 
   * @return void
   */
  private function _setCfg()
  {
    if (\is_array($this->cfg)) {
      $p =& $this->cfg['params'];
      $components = false;
      if (!empty($p['components'])) {
        $components = explode(',', $p['components']);
      }
      $this->cfg = X::mergeArrays(
        $this->cfg, [
        'test' => !empty($p['test']),
        'lang' => empty($p['lang']) ? self::$default_language : $p['lang'],
        'nocompil' => !empty($p['nocompil']),
        'has_css' => !isset($p['css']) || $p['css'],
        'has_dep' => !isset($p['dep']) || $p['dep'],
        'latest' => isset($p['latest']) ? 1 : false,
        'is_component' => !empty($p['components']),
        'components' => $components
        ]
      );
    }
  }

}
