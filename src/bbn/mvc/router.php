<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 12/05/2015
 * Time: 12:55
 */

namespace bbn\mvc;

class router {

  use common;

  private static
    /**
     * The path for the default controller
     * @var array
     */
    $def = 'default',
    /**
     * The list of types of controllers
     * @var array
     */
    $controllers = ['cli', 'dom', 'content', 'public', 'private'],
    /**
     * The list of filetypes for each non controller element
     * @var array
     */
    $filetypes = [
      'model' => ['php'],
      'html' => ['html', 'php'],
      'js' => ['js', 'coffee'],
      'css' => ['css', 'less', 'scss']
    ],
    /**
     * The list of types
     * @var array
     */
    $types = [
      'image',
      'file',
      'cli',
      'private',
      'dom',
      'public',
      'model',
      'html',
      'js',
      'css'
    ];

  private
    $mode = false,
    $prepath,
    /**
     * The path to the app root (where is ./mvc)
     * @var string
     */
    $root,
    /**
     * The path to an alternate root (where is ./mvc)
     * @var string
     */
    $alt_root = false,
    /**
     * The list of known external controllers routes.
     * @var array
     */
    $routes = [],
    /**
     * The list of used controllers with their corresponding request, so we don't have to look for them again.
     * @var array
     */
    $known = [
      'cli' => [],
      'dom' => [],
      'public' => [],
      'private' => [],
      'model' => [],
      'html' => [],
      'js' => [],
      'css' => []
    ];

  public static function is_mode($mode){
    return in_array($mode, self::$types);
  }

  /**
   * This will fetch the route to the controller for a given path. Chainable
   *
   * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
   * @return void
   */
  public function __construct(\bbn\mvc $mvc, array $routes=[])
  {
    $this->mvc = $mvc;
    $this->routes = $routes;
    $this->root = BBN_APP_PATH;
  }

  private function get_root($mode){
    if ( self::is_mode($mode) ){
      return $this->root.'mvc/'.( $mode === 'dom' ? 'public' : $mode ).'/';
    }
    return false;
  }

  private function get_alt_root($mode, $path = false){
    if ( ($path || $this->alt_root) &&
      self::is_mode($mode) &&
      isset($this->routes['root'][$path ?: $this->alt_root])
    ){
      return $this->parse($this->routes['root'][$path ?: $this->alt_root].'/mvc/'.( $mode === 'dom' ? 'public' : $mode ).'/');
    }
    return false;
  }

  private function set_alt_root($path){
    $path = $this->parse($path);
    if ( strpos($path, '/') === 0 ){
      $path = substr($path, 1);
    }
    $prepath = $this->get_prepath();
    if ( $prepath && (strpos($path, $prepath.'/') === 0) ){
      $path = substr($path, strlen($prepath));
    }
    if ( !isset($this->routes['root'][$path]) ){
      die("The alternative root $path doesn't exist!");
    }
    $this->alt_root = $path;
    return $this;
  }

  private function parse($path){
    return \bbn\str::parse_path($path);
  }

  private function has_route($path){
    return is_string($path) && isset($this->routes['alias'][$path]);
  }

  private function get_route($path){
    if ( $this->has_route($path) ) {
      if ( is_array($this->routes['alias'][$path]) ){
        return $this->routes['alias'][$path][0];
      }
      else{
        return $this->routes['alias'][$path];
      }
    }
    return false;
  }

  private function is_known($path, $mode){
    return self::is_mode($mode) && isset($this->known[$mode][$path]);
  }

  private function get_known($path, $mode){
    if ( $this->is_known($path, $mode) ){
      if ( in_array($mode, self::$controllers) && is_string($this->known[$mode][$path]) && isset($this->known[$mode][$this->known[$mode][$path]]) ){
        $path = $this->known[$mode][$path];
      }
      //$this->log("known", $this->known);
      return $this->known[$mode][$path];
    }
    return false;
  }

  private function set_known(array $o){
    if ( !isset($o['mode'], $o['path'], $o['file']) || !self::is_mode($o['mode']) || !is_string($o['path']) || !is_string($o['file']) ){
      return false;
    }
    $mode = $o['mode'];
    $path = $o['path'];
    $root = $this->get_root($mode);

    if ( !isset($this->known[$mode][$path]) ){
      if ( in_array($mode, self::$controllers) ){
        $this->known[$mode][$path] = $o;
        $this->known[$mode][$path]['checkers'] = [];
        $tmp = $path;
        while ( strlen($tmp) > 0 ){
          //$this->log("WHILE", $tmp);
          $tmp = $this->parse(dirname($tmp));
          $ctrl = ( $tmp === '.' ? '' : $tmp.'/' ).'_ctrl.php';
          if ( $this->alt_root ){
            if ( strpos($tmp, $this->alt_root) === 0 ){
              $alt_ctrl = $this->get_alt_root($mode).
                ( strlen($tmp) === strlen($this->alt_root) ?
                  '' : substr($tmp, strlen($this->alt_root)+1).'/'
                ).'_ctrl.php';
              //$this->log("ALT", $alt_ctrl);
              if ( is_file($alt_ctrl) ){
                if ( !in_array($alt_ctrl, $this->known[$mode][$path]['checkers']) ){
                  array_unshift($this->known[$mode][$path]['checkers'], $alt_ctrl);
                }
              }
            }
          }
          if ( is_file($root.$ctrl) ){
            if ( !in_array($root.$ctrl, $this->known[$mode][$path]['checkers']) ){
              array_unshift($this->known[$mode][$path]['checkers'], $root.$ctrl);
            }
          }
          if ( $tmp === '.' ){
            $tmp = '';
          }
        }
        if ( $o['path'] !== $o['request'] ){
          $this->known[$mode][$o['request']] = $o['path'];
        }
      }
      else if ( !empty($o['ext']) ){
        $this->known[$mode][$path] = $o;
      }
    }
    //$this->log($this->known[$mode][$path]);
    return $this->known[$mode][$path];
  }

  /**
   * Return the actual controller file corresponding to a gievn path
   * @param string $path
   * @param string $mode
   * @return mixed
   */
  private function find_controller($path, $mode){
    /** @var string $root Where the files will be searched for by default */
    $root = $this->get_root($mode);
    /** @var boolean|string $file Once found, full path and filename */
    $file = false;
    $tmp = $path ? $path : '.';
    $args = [];
    // We go through each path, starting by the longest until it's empty
    while (strlen($tmp) > 0) {
      //var_dump($tmp);
      if ($this->is_known($tmp, $mode)) {
        return $this->get_known($tmp, $mode);
      }
      // initial load
      if ( $mode === 'dom' ){
        // Root index file
        if ( $tmp === '.' ){
          if ( file_exists($root.'index.php') ){
            $npath = 'index';
            $file = $root . 'index.php';
          }
          else{
            break;
          }
        }
        // Index file in a subfolder
        else if ( file_exists($root.$tmp.'/index.php') ){
          $npath = $tmp. '/index';
          $file = $root . $tmp . '/index.php';
        }
        // An alternative root exists, we look into it
        else if ( $this->alt_root &&
          file_exists($this->get_alt_root($mode).$tmp.'/index.php')
        ){
          $npath = $tmp. '/index';
          $file = $this->get_alt_root($mode).$tmp.'/index.php';
        }
        // $tmp corresponds to a root index
        else if ( isset($this->routes['root'][$tmp]) ){
          $this->set_alt_root($tmp);
          return $this->route(substr($path, strlen($tmp)+1), $mode);
        }
      }
      if ( !$file ){
        // navigation (we are in dom and dom is default or we are not in dom, i.e. public)
        if ( (($mode === 'dom') && (BBN_DEFAULT_MODE === 'dom')) || ($mode !== 'dom') ){
          if ( $this->has_route($tmp) ){
            $npath = $this->get_route($tmp);
            if ( is_file($root.$npath.'.php') ){
              $file = $root.$npath.'.php';
            }
          }
          else if (file_exists($root.$tmp.'.php')) {
            $npath = $tmp;
            $file = $root.$tmp.'.php';
          }
          else if ( is_dir($root.$tmp) && is_file($root.$tmp.'/home.php') ){
            $npath = $tmp.'/home';
            $file = $root.$tmp.'/home.php';
          }
          // An alternative root exists, we look into it
          else if ( $this->alt_root && (strpos($tmp, $this->alt_root) === 0) ){
            $name = substr($tmp, strlen($this->alt_root)+1);
            if ( file_exists($this->get_alt_root($mode).$name.'.php') ){
              $npath = $tmp;
              $file = $this->get_alt_root($mode).$name.'.php';
            }
            else if ( is_dir($this->get_alt_root($mode).$name) && is_file($this->get_alt_root($mode).$name.'/home.php') ){
              $npath = $tmp.'/home';
              $file = $this->get_alt_root($mode).$name.'/home.php';
            }
          }
          // $tmp corresponds to a root
          else if ( isset($this->routes['root'][$tmp]) ){
            $this->set_alt_root($tmp);
            return $this->route($path, $mode);
          }
        }
      }
      if ( $file ) {
        break;
      }
      array_unshift($args, basename($tmp));
      $tmp = strpos($tmp, '/') === false ? '' : substr($tmp, 0, strrpos($tmp, '/'));
      if ( empty($tmp) && ($mode === 'dom') ){
        $tmp = '.';
      }
      else if ( $tmp === '.' ){
        $tmp = '';
      }
    }
    // Not found, sending the default controllers
    if ( !$file ){
      if ( (
          ($mode === 'dom') &&
          (BBN_DEFAULT_MODE === 'dom')
        ) || (
          ($mode !== 'dom') &&
          !empty($this->routes[self::$def])
        ) ){
        $npath = $this->routes[self::$def];
        $file = $root.$this->routes[self::$def].'.php';
      }
    }
    if ( $file ) {
      return $this->set_known([
        'file' => $file,
        'path' => $npath,
        'request' => $path,
        'mode' => $mode,
        'args' => $args
      ]);
    }
    // Aaaargh!
    die(\bbn\x::hdump("No default file defined for mode $mode $tmp", self::$def, $this->has_route(self::$def)));
  }

  private function find_in_roots($path){
    foreach ( $this->routes['root'] as $p => $real ){
      if ( (strpos($path, $p.'/') === 0) || ($p === $path) ){
        return $p;
      }
    }
    return false;
  }

  private function find_mv($path, $mode){
    /** @var string $root Where the files will be searched for by default */
    $root = $this->get_root($mode);
    /** @var boolean|string $file Once found, full path and filename */
    $file = false;
    $alt_path = false;
    if ( $alt_path = $this->find_in_roots($path) ){
      $alt_root = $this->get_alt_root($mode, $alt_path);
    }
    else if ( $alt_root = $this->get_alt_root($mode) ){
      $alt_path = $this->alt_root;
    }
    foreach ( self::$filetypes[$mode] as $t ){
      if ( is_file($root.$path.'.'.$t) ) {
        $file = $root . $path . '.' . $t;
      }
      else if ( $alt_path && is_file($alt_root.substr($path, strlen($alt_path)+1).'.'.$t) ) {
        $file = $alt_root . substr($path, strlen($alt_path)+1) . '.' . $t;
      }
      if ( $file ){
        return $this->set_known([
          'file' => $file,
          'path' => $path,
          'ext' => $t,
          'mode' => $mode
        ]);
      }
    }
    return false;
  }

  public function add_routes(array $routes){
    $this->routes = \bbn\x::merge_arrays($this->routes['alias'], $routes);
    return $this;
  }

  public function set_prepath($path){
    if ( !$this->check_path($path) ) {
      die("The prepath $path is not valid");
    }
    $this->prepath = $path;
    if ( substr($this->prepath, -1) !== '/' ){
      $this->prepath = $this->prepath.'/';
    }
    if ( $this->mode ){
      $this->route($this->mvc->get_url(), $this->mode);
    }
    return 1;
  }

  public function get_prepath($with_slash = 1){
    if ( !empty($this->prepath) ){
      return $with_slash ? $this->prepath : substr($this->prepath, 0, -1);
    }
    return '';
  }

  public function route($path, $mode){

    if ( self::is_mode($mode) ) {


      /** @var string $path The path to the file from $root */
      $path = $this->parse($path);

      // If there is a prepath defined we prepend it to the path
      if ( $this->prepath && (strpos($path, '/') !== 0) && (strpos($path, $this->prepath) !== 0) ){
        $path = $this->prepath.$path;
      }


      // We only try to retrieve a file path through a whole URL for controllers
      if (in_array($mode, self::$controllers)) {
        $this->mode = $mode;
        //$this->log($path);
        return $this->find_controller($path, $mode);
      }
      else{
        return $this->find_mv($path, $mode);
      }
    }
    return false;
  }

  public function fetch_dir($dir, $mode){

    // Only for views and models
    if ( self::is_mode($mode) && !in_array($mode, self::$controllers) ){


      /** @var string $path The path to the file from $root */
      $path = $this->parse($dir);

      // If there is a prepath defined we prepend it to the path
      if ( $this->prepath && (strpos($path, '/') !== 0) && (strpos($path, $this->prepath) !== 0) ){
        $path = $this->prepath.$path;
      }

      /** @var string $root Where the files will be searched for by default */
      $root = $this->get_root($mode);
      if ( $alt_path = $this->find_in_roots($path) ){
        $alt_root = $this->get_alt_root($mode, $alt_path);
      }
      else if ( $alt_root = $this->get_alt_root($mode) ){
        $alt_path = $this->alt_root;
      }
      $dir = false;
      foreach ( self::$filetypes[$mode] as $t ){
        if ( is_dir($root.$path) ) {
          $dir = $root . $path;
        }
        else if ( $alt_path && is_dir($alt_root.substr($path, strlen($alt_path)+1)) ) {
          $dir = $alt_root . substr($path, strlen($alt_path)+1) . '.' . $t;
        }
        if ( $dir ){
          $res = [];
          $files = \bbn\file\dir::get_files($dir);
          foreach ( $files as $f ){
            if ( in_array(\bbn\str::file_ext($f), self::$filetypes[$mode]) ){
              array_push($res, $dir.'/'.\bbn\str::file_ext($f)[0]);
            }
          }
          return $res;
        }
      }
      return false;
    }
    return false;
  }

  public function get_routes(){
    return $this->routes;
  }
}
