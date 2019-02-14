<?php
namespace bbn\cdn;

use bbn;
use JShrink;
use CssMin;

class compiler extends bbn\models\cls\basic
{
  use common;

  private static $min_suffixes = ['.min', '-min', '.pack'];

  protected
    /**
     * @var array
     */
    $cfg,
    $final_file;

  public function __construct()
  {
    $this->set_prefix();
  }

  public function minify(string $st, string $lang){
    $tmp = false;
    $st = trim($st);
    if ( $st ){
      try {
        if ( $lang === 'js' ){
          $tmp = JShrink\Minifier::minify($st);
        }
        else if ( $lang === 'css' ){
          $tmp = CssMin::minify($st);
        }
      }
      catch (\Exception $e){
        $this->set_error("Error during $lang minification with string - {$e->getMessage()}");
        //die('Error during $lang minification with string - '.$e->getMessage());
      }
    }
    return $tmp ?: $st;
  }

  public function get_content($file, $test = false){
    $ext = bbn\str::file_ext($file);
    $minified = false;
    if ( !is_file($this->fpath.$file) ){
      return false;
    }
    foreach ( self::$min_suffixes as $s ){
      if ( strpos($file, $s.'.') ){
        $minified = true;
        if ( $test && file_exists($this->fpath.str_replace($s.'.', '.', $file)) ){
          $c = file_get_contents($this->fpath.str_replace($s.'.', '.', $file));
        }
        break;
      }
    }
    if ( !isset($c) ){
      $c = file_get_contents($this->fpath.$file);
    }
    if ( \is_string($c) ){
      $c = trim($c);
    }
    if ( $c ){
      switch ( $ext ){

        case 'js':
          if ( !$test && !$minified ){
            $c = $this->minify($c, 'js');
          }
          break;

        case 'css':
          if ( !$test && !$minified ){
            $c = $this->minify($c, 'css');
          }
          break;

        case 'coffee':
          try {
            $tmp = \CoffeeScript\Compiler::compile($c);
            if ( $tmp && !$test ){
              $c = $this->minify($c, 'js');
            }
          }
          catch (\Exception $e){
            $this->set_error("Error during CoffeeScript compilation with file $file: ".$e->getMessage());
            die("Compilation error with file $file : ".$e->getMessage());
          }
          break;

        case 'less':
          $less = new \lessc();
          $less->setImportDir([\dirname($this->fpath.$file)]);
          if ( is_file(\dirname($this->fpath.$file).'/_def.less') ){
            $c = file_get_contents((\dirname($this->fpath.$file).'/_def.less')).$c;
          }
          try {
            $c = $less->compile($c);
            if ( $c && !$test ){
              $c = $this->minify($c, 'css');
            }
          }
          catch ( \Exception $e ){
            $this->set_error("Error during LESS compilation with file $file :".$e->getMessage());
            die($e->getMessage());
          }
          break;

        case 'scss':
          try{
            $scss = new \Leafo\ScssPhp\Compiler();
            $scss->setImportPaths([\dirname($this->fpath.$file)]);
            if ( is_file(\dirname($this->fpath.$file).'/_def.scss') ){
              $c = file_get_contents((\dirname($this->fpath.$file).'/_def.scss')).$c;
            }
            $c = $scss->compile($c);
            if ( $c && !$test ){
              $c = $this->minify($c, 'css');
            }
          }
          catch ( \Exception $e ){
            $this->set_error("Error during SCSS compilation with file $file :".$e->getMessage());
            die($e->getMessage());
          }
          break;

        case 'sass':
          $sass = new \SassParser([
            'cache' => false,
            'syntax' => 'sass'
          ]);
          try {
            $c = $sass->toCss($c, false);
            if ( $c && !$test ){
              $c = $this->minify($c, 'css');
            }
          }
          catch ( \Exception $e ){
            $this->set_error("Error during SASS compilation with file $file :".$e->getMessage());
            die($e->getMessage());
          }
          break;
      }
      if ( !$this->check() ){
        die("File $file \n{$this->get_error()}");
      }
      return $c;
    }
    return false;
  }
  
  public function js_links(array $files, $test = false){
    $code = '';
    $num_files = \count($files);
    if (  $num_files ){
      $url = $this->furl.'?files=%s&v='.($this->o['v'] ?? (\defined('BBN_IS_PROD') && BBN_IS_PROD ? '' : time()));
      $files_json = json_encode($files);
            $code .= <<<JS
  .then(function(){
    return new Promise(function(bbn_resolve, bbn_reject){
      var files = $files_json,
          rFiles = [];
      for ( var i = 0; i < files.length; i++ ){
        if ( bbnLoadFile(files[i]) ){
          rFiles.push(files[i]);
        }
      }
      if ( !rFiles.length ){
        bbn_resolve();
        return;
      }
      var script = document.createElement("script");
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
JS;
    }
    return $code;
  }

  public function has_links($css){
    return strpos($css, 'url(') || (strpos($css, '@import') !== false);
  }

  public function css_links(array $files, $test = false){
    $code = '';
    $num_files = \count($files);
    if (  $num_files ){
      $dirs = [];
      for ( $i = 0; $i < $num_files; $i++ ){
        if ( is_file($this->fpath.$files[$i]) ){
          $css = $this->get_content($files[$i], false);
          if ( $this->has_links($css) ){
            if ( !isset($dirs[\dirname($files[$i])]) ){
              $dirs[\dirname($files[$i])] = [];
            }
            $dirs[\dirname($files[$i])][] = basename($files[$i]);
          }
          else{
            if ( !isset($dirs['.']) ){
              $dirs['.'] = [];
            }
            $dirs['.'][] = $files[$i];
          }
        }
      }
      if ( \count($dirs) ){
        foreach ( $dirs as $dir => $dfiles ){
          if ( \count($dfiles) ){
            $files_json = json_encode($dfiles);

            $url = $this->furl.'~~~BBN~~~';

            $params = [];
            // The v parameter is passed between requests (to refresh)
            if ( $this->o['v'] || (\defined('BBN_IS_DEV') && BBN_IS_DEV) ){
              $params['v'] = $this->o['v'] ?? time();
            }
            // The test parameter also (for minification)
            if ( $test ){
              $params['test'] = 1;
            }
            $url .= http_build_query($params);
            $jsdir = $dir === '.' ? '' : $dir.'/';
            $code .= <<<JS
            
  .then(function(){
    return new Promise(function(bbn_resolve, bbn_reject){
      var dir = "$jsdir",
          files = $files_json,
          url = "$url",
          rFiles = [];
      for ( var i = 0; i < files.length; i++ ){
        if ( bbnLoadFile(dir + files[i]) ){
          rFiles.push(files[i]);
        }
      }
      if ( !rFiles.length ){
        bbn_resolve();
        return;
      }
      var css = document.createElement("link");
      css.rel = "stylesheet";
        css.href = url.replace('~~~BBN~~~', dir + '?f=' + rFiles.join(",") + '&');
      css.onload = function(){
        bbn_resolve();
      };
      css.onerror = function(){
        bbn_reject();
      };
      document.getElementsByTagName("head")[0].appendChild(css);
    })
  })
JS;
          }
        }
        //$code .= ";\nreturn promise;\n})()";
        if ( !$test ){
          $code = $this->minify($code, 'js');
        }
      }
    }
    return $code;
  }

  public function css_content($css){
    $css = str_replace('`', '\\``', str_replace('\\', '\\\\', $css));
    //$css = bbn\str::escape_squotes($css);
    $code = bbn\str::genpwd(25, 20);
    $head = $code.'2';
    $style = $code.'3';
    return <<<JS
  let $code = `$css`,
      $head = document.head || document.getElementsByTagName('head')[0],
      $style = document.createElement('style');
  $style.type = 'text/css';
  if ( $style.styleSheet ){
    $style.styleSheet.cssText = $code;
  }
  else {
    $style.appendChild(document.createTextNode($code));
  }
  return $head.appendChild($style);
JS;

  }

  public function compile(array $files, $test = false){
    if ( !empty( $files) ){
      /** @var string $insert_precode Will be used in the sprintf on $precode */
      $insert_precode = '';
      /** @var string $code Will contain all the code to add to our file */
      $code = '';
      /** @var array $codes Will contain the raw content of each files */
      $codes = [];
      // Mix of CSS and javascript: the JS adds the CSS to the head before executing
      foreach ( $files as $f ){
        if ( $c = $this->get_content($f, $test) ){
          $e = bbn\str::file_ext($f);
          foreach ( self::$types as $type => $exts ){
            foreach ( $exts as $ext ){
              if ( $ext === $e ){
                $mode = $type;
                break;
              }
            }
          }
          $codes[$mode ?? $e][] = [
            'code' => $c,
            'file' => basename($f),
            'dir' => \dirname($f)
          ];
        }
        else{
          //die("I can't find the file $f !");
        }
      }
      return $codes;
    }
  }

}
