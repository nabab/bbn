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

use bbn\Models\Cls\Basic;
use bbn\X;
use bbn\Str;
use JShrink\Minifier;
use CssMin;
use bbn\Compilers\Less;

/**
 * Compile files into single files, using javascript to call CSS when needed.
 *
 *
 * @category CDN
 * @package  BBN
 * @author   Theomas Nabet <thomas.nabet@gmail.com>
 * @license  https://opensource.org/licenses/mit-license.php MIT
 * @link     https://bbnio2.thomas.lan/bbn-php/doc/class/cdn/compiler
 */
class Compiler extends Basic
{
  use Common;

  /**
   * The suffixes used by minified packaged content
   *
   * @var array
   */
  private static $_min_suffixes = ['.min', '-min', '.pack', '.prod'];

  /**
   * @var array
   */
  protected $cfg;


  /**
   * Constructor.
   *
   * @param array $cfg The configuration array
   */
  public function __construct(array $cfg)
  {
    $this->_set_prefix();
    $this->cfg = $cfg;
  }


  /**
   * Minify the given string in the given lang (js or css).
   *
   * @param string $st   The string to minify
   * @param string $lang The language used by the string
   * @return void
   */
  public function minify(string $st, string $lang): string
  {
    $tmp = false;
    $st  = trim($st);
    if ($st) {
      try {
        if ($lang === 'js') {
          $tmp = Minifier::minify($st, ['flaggedComments' => false]);
        }
        elseif ($lang === 'css') {
          /** @todo Minification makes var() statements disappear! */
          $tmp = Str::cleanSpaces($st);//CssMin::minify($st);
        }
      }
      catch (\Exception $e) {
        $this->setError("Error during $lang minification with string - {$e->getMessage()}");
        //die('Error during $lang minification with string - '.$e->getMessage());
      }
    }

    return $tmp ?: $st;
  }


  /**
   * Returns the content of a file or a group of files,
   * after having compiled it if needed, and minified if test is false.
   *
   * @param string|array $file The file or list of files
   * @param boolean      $test If true the content will not be minified
   * @return void
   */
  public function getContent($file, $test = false)
  {
    if (is_array($file)) {
      $ext      = Str::fileExt($file[0]);
      $minified = false;
      $c        = '';
      foreach ($file as $f) {
        $has_content = false;
        if (!is_file($this->fpath . $f)) {
          throw new \Exception(X::_("Impossible to find the file") . ' ' . $this->fpath . $f);
          return false;
        }

        foreach (self::$_min_suffixes as $s) {
          if (strpos($f, $s . '.')) {
            $minified = true;
            if ($test && file_exists($this->fpath . str_replace($s . '.', '.', $f))) {
              $c          .= PHP_EOL . file_get_contents($this->fpath . str_replace($s . '.', '.', $f));
              $has_content = true;
            }

            break;
          }
        }

        if (!$has_content) {
          $c .= PHP_EOL . file_get_contents($this->fpath . $f);
        }

        if (!empty($c)) {
          $c = trim($c);
        }
      }

      $file = $file[0];
    }
    else {
      $ext      = Str::fileExt($file);
      $minified = false;
      if (!is_file($this->fpath . $file)) {
        throw new \Exception(X::_("Impossible to find the file") . ' ' . $this->fpath . $file);
        return false;
      }

      foreach (self::$_min_suffixes as $s) {
        if (strpos($file, $s . '.')) {
          $minified = true;
          if ($test && file_exists($this->fpath . str_replace($s . '.', '.', $file))) {
            $c = file_get_contents($this->fpath . str_replace($s . '.', '.', $file));
          }

          break;
        }
      }

      if (!isset($c)) {
        $c = file_get_contents($this->fpath . $file);
      }

      if (\is_string($c)) {
        $c = trim($c);
      }
    }

    if ($c) {
      switch ($ext) {
        case 'js':
          if (!$test && !$minified) {
            $c = $this->minify($c, 'js');
          }
              break;

        case 'css':
          if (!$test && !$minified) {
            $c = $this->minify($c, 'css');
          }
              break;

        case 'less':
          $less = new Less();
          $less->setImportDir([X::dirname($this->fpath . $file)]);
          try {
            $c = $less->compile($c);
          }
          catch (\Exception $e) {
            X::log("Error during LESS compilation with file $file :" . $e->getMessage(), 'cdn_err');
            $this->setError("Error during LESS compilation with file $file :" . $e->getMessage());
            throw $e;
          }

          if ($c && !$test) {
            try {
              $c = $this->minify($c, 'css');
            }
            catch (\Exception $e) {
              $this->setError("Error during LESS compilation with file $file :" . $e->getMessage());
              throw $e;
            }
          }
              break;

        case 'scss':
          try {
            $scss = new \ScssPhp\ScssPhp\Compiler();
            $scss->setImportPaths([X::dirname($this->fpath . $file)]);
            if (is_file(X::dirname($this->fpath . $file) . '/_def.scss')) {
              $c = file_get_contents((X::dirname($this->fpath . $file) . '/_def.scss')) . $c;
            }

            $c = $scss->compile($c);
            if ($c && !$test) {
              $c = $this->minify($c, 'css');
            }
          }
          catch (\Exception $e) {
            $this->setError("Error during SCSS compilation with file $file :" . $e->getMessage());
            die($e->getMessage());
          }
              break;

        case 'sass':
          $sass = new \SassParser(
              [
              'cache' => false,
              'syntax' => 'sass'
              ]
          );
          try {
            $c = $sass->toCss($c, false);
            if ($c && !$test) {
              $c = $this->minify($c, 'css');
            }
          }
          catch (\Exception $e) {
            $this->setError("Error during SASS compilation with file $file :" . $e->getMessage());
            die($e->getMessage());
          }
              break;
      }

      if (!$this->check()) {
        die("File $file \n{$this->getError()}");
      }

      return $c;
    }

    return false;
  }


  /**
   * Returns a javascript string invoking other javascript files.
   *
   * @param array   $files A list of files to be invoked
   * @param boolean $test  If true minification will not be applied
   * @return string
   */
  public function jsLinks(array $files, $test = false): string
  {
    $code      = '';
    $num_files = \count($files);
    if ($num_files) {
      $url    = $this->furl . '?files=%s&';
      $params = [];
      // The v parameter is passed between requests (to refresh)
      if (!empty($this->cfg['params']['v'])) {
        $params['v'] = $this->cfg['params']['v'];
      }

      // The test parameter also (for minification)
      if ($test) {
        $params['test'] = 1;
      }

      $url        .= http_build_query($params);
      $files_json  = json_encode($files);
            $code .= <<<JAVASCRIPT
  .then(function(){
    return new Promise(function(bbn_resolve, bbn_reject){
      let files = $files_json;
      let rFiles = [];
      for (let i = 0; i < files.length; i++) {
        if ( bbnLoadFile(files[i]) ){
          rFiles.push(files[i]);
        }
      }
      if ( !rFiles.length ){
        bbn_resolve();
        return;
      }
      let script = document.createElement("script");
      script.type = "text/javascript";
      script.src = "$url".replace("%s", rFiles.join(","));
      script.onload = function(){
        bbn_resolve();
      };
      script.onerror = function(){
        bbn_reject();
      };
      document.getElementsByTagName("head")[0].appendChild(script);
    })
  })
JAVASCRIPT;
    }

    return $code;
  }


  /**
   * Returns true if the given css code contains url parameters.
   *
   * @param [type] $css
   * @return boolean
   */
  public function hasLinks(string $css)
  {
    return strpos($css, 'url(') || (strpos($css, '@import') !== false);
  }


  /**
   * Returns a javascript string including css files.
   *
   * @param array   $files A list of files to be included
   * @param boolean $test  If true minification will not be applied
   * @return string
   */
  public function cssLinks(array $files, $test = false, $prepend_files = [], $root = '')
  {
    $code      = '';
    $num_files = \count($files);
    if ($num_files) {
      $dirs        = [];
      $prepended   = [];
      $unprepended = [];
      $dir         = null;
      foreach ($files as $f) {
        if (!is_file($this->fpath . $f)) {
          throw new \Exception(X::_("Impossible to find the file %s", $this->fpath . $f));
        }
        $tmp = X::dirname($f);
        if (is_null($dir)) {
          $dir = $tmp . '/';
        }
        elseif (strpos($dir, $tmp) !== 0) {
          $old_tmp = null;
          while ($tmp = X::dirname($tmp) && ($tmp !== $old_tmp)) {
            $old_tmp = $tmp;
            if ($tmp === $dir) {
              break;
            }
          }

          if ($tmp !== $dir) {
            $bits    = \bbn\X::split(X::dirname($f), '/');
            $new_dir = '';
            foreach ($bits as $b) {
              if (!empty($b)) {
                if (strpos($dir, $new_dir . $b) === 0) {
                  $new_dir .= $b . '/';
                }
                else {
                  $dir = $new_dir ?: '.';
                  break;
                }
              }
            }
          }
        }

        if (isset($prepend_files[$f])) {
          foreach ($prepend_files[$f] as $p) {
            if (!in_array($p, $prepended)) {
              $prepended[] = $p;
            }
          }
        }
      }

      if (count($prepended)) {
        foreach (array_reverse($prepended) as $p) {
          array_unshift($files, $p);
        }
      }

      foreach ($files as $ar) {
        $files_json[] = str_replace($dir, '', $ar);
      }

      $files_json = json_encode($files_json);
      $url        = $this->furl . '~~~BBN~~~';
      $params     = [];
      // The v parameter is passed between requests (to refresh)
      if (!empty($this->cfg['params']['v'])) {
        $params['v'] = $this->cfg['params']['v'];
      }

      // The test parameter also (for minification)
      if ($test) {
        $params['test'] = 1;
      }

      $url  .= http_build_query($params);
      $jsdir = $dir;
      $code .= <<<JAVASCRIPT
.then(function(){
  return new Promise(function(bbn_resolve, bbn_reject){
    let dir = "$jsdir";
    let files = $files_json;
    let url = "$url";
    let rFiles = [];
    for ( var i = 0; i < files.length; i++ ){
      if ( bbnLoadFile(dir + files[i]) ){
        rFiles.push(files[i]);
      }
    }
    if ( !rFiles.length ){
      bbn_resolve();
      return;
    }
    let css = document.createElement("link");
    css.rel = "stylesheet";
    css.href = url.replace('~~~BBN~~~', dir + '?grouped=1&f=' + rFiles.join(",") + '&');
    css.onload = function(){
      bbn_resolve();
    };
    css.onerror = function(){
      bbn_reject();
    };
    document.getElementsByTagName("body")[0].appendChild(css);
  })
})
JAVASCRIPT;
      foreach ($unprepended as $file) {
        $css = $this->getContent($file, false);
        if ($this->hasLinks($css)) {
          if ($root) {
            if (!isset($dirs[$root])) {
              $dirs[$root] = [];
            }

            $dirs[$root][] = substr($file, strlen($root));
          }
          else {
            if (!isset($dirs[X::dirname($file)])) {
              $dirs[X::dirname($file)] = [];
            }

            $dirs[X::dirname($file)][] = X::basename($file);
          }
        }
        else {
          if (!isset($dirs['.'])) {
            $dirs['.'] = [];
          }

          $dirs['.'][] = $file;
        }
      }

      if (\count($dirs)) {
        foreach ($dirs as $dir => $dfiles) {
          if (\count($dfiles)) {
            $files_json = json_encode($dfiles);

            $url = $this->furl . '~~~BBN~~~';

            $params = [];
            // The v parameter is passed between requests (to refresh)
            if (!empty($this->cfg['params']['v'])) {
              $params['v'] = $this->cfg['params']['v'];
            }

            // The test parameter also (for minification)
            if ($test) {
              $params['test'] = 1;
            }

            $url  .= http_build_query($params);
            $jsdir = $dir === '.' ? '' : $dir . '/';
            $code .= <<<JAVASCRIPT

  .then(function(){
    return new Promise(function(bbn_resolve, bbn_reject){
      let dir = "$jsdir";
      let files = $files_json;
      let url = "$url";
      let rFiles = [];
      for (let i = 0; i < files.length; i++) {
        if ( bbnLoadFile(dir + files[i]) ){
          rFiles.push(files[i]);
        }
      }
      if ( !rFiles.length ){
        bbn_resolve();
        return;
      }
      let css = document.createElement("link");
      css.rel = "stylesheet";
      css.href = url.replace('~~~BBN~~~', dir + '?f=' + rFiles.join(",") + '&');
      css.onload = function(){
        bbn_resolve();
      };
      css.onerror = function(){
        bbn_reject();
      };
      document.getElementsByTagName("body")[0].appendChild(css);
    })
  })
JAVASCRIPT;
          }
        }

        //$code .= ";\nreturn promise;\n})()";
        if (!$test) {
          $code = $this->minify($code, 'js');
        }
      }
    }

    return $code;
  }


  /**
   * Returns a string with javascript including the given CSS content in the head of the document.
   *
   * @param string $css A CSS string
   * @return string
   */
  public function cssContent(string $css): string
  {
    $css = str_replace('`', '\\``', str_replace('\\', '\\\\', $css));
    //$css = Str::escapeSquotes($css);
    $code  = Str::genpwd(25, 20);
    $head  = $code . '2';
    $style = $code . '3';
    return <<<JAVASCRIPT
  let $code = `$css`;
  let $head = document.head || document.getElementsByTagName('head')[0];
  let $style = document.createElement('style');
  $style.type = 'text/css';
  if ( $style.styleSheet ){
    $style.styleSheet.cssText = $code;
  }
  else {
    $style.appendChild(document.createTextNode($code));
  }
  return $head.appendChild($style);
JAVASCRIPT;
  }


  /**
   * Returns an array of compiled codes based on a list of files.
   *
   * @param array   $files A list of files to add
   * @param boolean $test  If true files will not be minified
   * @return array
   */
  public function compile(array $files, bool $test = false): array
  {
    /** @var array $codes Will contain the raw content of each files */
    $codes = [];
    if (!empty($files)) {
      // Mix of CSS and javascript: the JS adds the CSS to the head before executing
      foreach ($files as $f) {
        if ($c = $this->getContent($f, $test)) {
          $e = Str::fileExt($f);
          foreach (self::$types as $type => $exts) {
            foreach ($exts as $ext) {
              if ($ext === $e) {
                $mode = $type;
                break;
              }
            }
          }

          $codes[$mode ?? $e][] = [
            'code' => $c,
            'file' => X::basename($f),
            'dir' => X::dirname($f)
          ];
        }
        else {
          //die("I can't find the file $f !");
        }
      }
    }

    return $codes;
  }


  /**
   * Compiles together a group of files and returns the result as an array.
   *
   * @param array   $files A list of files to add
   * @param boolean $test  If true files will not be minified
   * @return array
   */
  public function groupCompile(array $files, bool $test = false): array
  {
    $codes = [];
    if (!empty($files)) {
      /** @var array $codes Will contain the raw content of each files */
      // Mix of CSS and javascript: the JS adds the CSS to the head before executing
      if ($c = $this->getContent($files, $test)) {
        $e = Str::fileExt($files[0]);
        foreach (self::$types as $type => $exts) {
          foreach ($exts as $ext) {
            if ($ext === $e) {
              $mode = $type;
              break;
            }
          }
        }

        $codes[$mode ?? $e][] = [
          'code' => $c,
          'file' => X::basename(end($files)),
          'dir' => X::dirname(end($files))
        ];
      }
      else {
        throw new \Exception("Impossible to get content from $f");
      }
    }

    return $codes;
  }
}
