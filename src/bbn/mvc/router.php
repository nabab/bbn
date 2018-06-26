<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 12/05/2015
 * Time: 12:55
 */

namespace bbn\mvc;
use bbn;

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
    ],
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

  private
    /**
     * The MVC class from which the router is called
     * @var mvc
     */
    $mvc,
    /**
     * @var bool
     */
    $mode = false,
    /**
     * @var string
     */
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
    $routes = [];

  public static function is_mode($mode){
    return \in_array($mode, self::$types);
  }

  /**
   * This will fetch the route to the controller for a given path. Chainable
   *
   * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
   * @return void
   */
  public function __construct(bbn\mvc $mvc, array $routes=[])
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

  private function get_alt_root(string $mode, string $path = null){
    if (
      ($path || $this->alt_root) &&
      self::is_mode($mode) &&
      isset($this->routes['root'][$path ?: $this->alt_root])
    ){
      return bbn\str::parse_path($this->routes['root'][$path ?: $this->alt_root]['path'].'/mvc/'.( $mode === 'dom' ? 'public' : $mode ).'/');
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
      $path = substr($path, \strlen($prepath));
    }
    if ( !isset($this->routes['root'][$path]) ){
      die("The alternative root $path doesn't exist!");
    }
    $this->alt_root = $path;
    return $this;
  }

  private function parse($path){
    //return $path;
    return bbn\str::parse_path($path, true);
  }

  private function has_route($path){
    return \is_string($path) && isset($this->routes['alias'][$path]);
  }

  private function get_route($path){
    if ( $this->has_route($path) ){
      if ( \is_array($this->routes['alias'][$path]) ){
        return $this->routes['alias'][$path][0];
      }
      else{
        return $this->routes['alias'][$path];
      }
    }
    return false;
  }

  private function is_known($path, $mode){
    return self::is_mode($mode) && isset(self::$known[$mode][$path]);
  }

  private function get_known($path, $mode){
    if ( $this->is_known($path, $mode) ){
      if (
        \in_array($mode, self::$controllers, true) &&
        \is_string(self::$known[$mode][$path]) &&
        isset(self::$known[$mode][self::$known[$mode][$path]])
      ){
        $path = self::$known[$mode][$path];
      }
      //$this->log("known", self::$known);
      return self::$known[$mode][$path];
    }
    return false;
  }

  private function set_known(array $o){
    if ( !isset($o['mode'], $o['path'], $o['file']) || !self::is_mode($o['mode']) || !\is_string($o['path']) || !\is_string($o['file']) ){
      return false;
    }
    $mode = $o['mode'];
    $path = $o['path'];
    $root = $this->get_root($mode);

    if ( !isset(self::$known[$mode][$path]) ){
      self::$known[$mode][$path] = $o;
      $s =& self::$known[$mode][$path];
      if ( isset($o['ext']) && ($o['ext'] === 'less') ){
        $checker_file = '_mixins.less';
      }
      else if ( isset($o['ext']) && ($o['mode'] === 'model') ){
        $checker_file = '_data.php';
      }
      else if ( \in_array($mode, self::$controllers, true) ){
        $checker_file = '_ctrl.php';
      }
      if ( !empty($checker_file) ){
        $s['checkers'] = [];
        $tmp = $path;
        while ( \strlen($tmp) > 0 ){
          //$this->log("WHILE", $tmp);
          $tmp = $this->parse(\dirname($tmp));

          $checker = ( $tmp === '.' ? '' : $tmp.'/' ).$checker_file;
          if ( $this->alt_root ){
            if ( strpos($path, $this->alt_root) === 0 ){
              $alt_ctrl = $this->get_alt_root($mode).
                ( \strlen($tmp) === \strlen($this->alt_root) ?
                  '' : substr($tmp, \strlen($this->alt_root)+1).'/'
                ).$checker_file;
              //$this->log("ALT", $alt_ctrl);
              if ( is_file($alt_ctrl) && !\in_array($alt_ctrl, $s['checkers'], true) ){
                array_unshift($s['checkers'], $alt_ctrl);
              }
            }
          }
          if ( is_file($root.$checker) && !\in_array($root.$checker, $s['checkers'], true) ){
            array_unshift($s['checkers'], $root.$checker);
          }
          if ( $tmp === '.' ){
            $tmp = '';
          }
        }
        if ( isset($o['request']) && ($o['path'] !== $o['request']) ){
          //self::$known[$mode][$o['request']] = $o['path'];
          self::$known[$mode][$o['request']] = $s;
        }
      }
      else if ( !empty($o['ext']) ){
        self::$known[$mode][$path] = $o;
      }
    }
    //$this->log(self::$known[$mode][$path]);
    //\bbn\x::hdump(self::$known[$mode][$path]);
    return self::$known[$mode][$path];
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
    /** @var string $tmp Will contain the different states of the path along searching for the file */
    $tmp = $path ? $path : '.';
    /** @var array $args Each element of the URL outside the file path */
    $args = [];
    /** @var boolean|string $plugin Name of the controller's plugin if it's inside one */
    $plugin = false;
    /** @var string $real_path The application path */
    $real_path = null;
    // We go through the path, removing a bit each time until we find the corresponding file
    while ( \strlen($tmp) > 0){
      // We might already know it!
      if ($this->is_known($tmp, $mode)){
        return $this->get_known($tmp, $mode);
      }

      // if $tmp is a plugin root index setting $this->alt_root and rerouting to reprocess the path
      if ( isset($this->routes['root'][$tmp]) && ($this->alt_root !== $tmp) ){
        $this->set_alt_root($tmp);
        return $this->route($path, $mode, $this->get_alt_root($mode));
      }

      // navigation (we are in dom and dom is default or we are not in dom, i.e. public)
      if ( (($mode === 'dom') && (BBN_DEFAULT_MODE === 'dom')) || ($mode !== 'dom') ){
        // Checking first if the specific route exists (through $routes['alias'])
        if ( $this->has_route($tmp) ){
          $real_path = $this->get_route($tmp);
          if ( is_file($root.$real_path.'.php') ){
            $file = $root.$real_path.'.php';
          }
        }
        // Then looks for a corresponding file in the regular MVC
        else if (file_exists($root.$tmp.'.php')){
          $real_path = $tmp;
          $file = $root.$tmp.'.php';
        }
        // Then looks for a home.php file in the corresponding directory
        else if ( is_dir($root.$tmp) && is_file($root.$tmp.'/home.php') ){
          $real_path = $tmp.'/home';
          $file = $root.$tmp.'/home.php';
        }
        // If an alternative root exists (plugin), we look into it for the same
        else if ( $this->alt_root && (strpos($tmp, $this->alt_root) === 0) ){
          $name = substr($tmp, \strlen($this->alt_root)+1);
          // Corresponding file
          if ( file_exists($this->get_alt_root($mode).$name.'.php') ){
            $plugin = $this->alt_root;
            $real_path = $tmp;
            $file = $this->get_alt_root($mode).$name.'.php';
            $root = $this->get_alt_root($mode);
          }
          // home.php in corresponding dir
          else if ( is_dir($this->get_alt_root($mode).$name) && is_file($this->get_alt_root($mode).$name.'/home.php') ){
            $plugin = $this->alt_root;
            $real_path = $tmp.'/home';
            $file = $this->get_alt_root($mode).$name.'/home.php';
            $root = $this->get_alt_root($mode);
          }
        }
      }
      // Full DOM requested
      if ( !$file && ($mode === 'dom') ){
        // Root index file (if $tmp is at the root level)
        if ( ($tmp === '.') && !$this->alt_root ){
          // If file exists
          if ( file_exists($root.'index.php') ){
            $real_path = 'index';
            $file = $root.'index.php';
          }
          // Otherwise $file will remain undefined
          else{
            /** @todo throw an alert as there is no default index */
            die('Impossible to find a route');
            break;
          }
        }
        // There is an index file in a subfolder
        else if ( file_exists($root.$tmp.'/index.php') ){
          $real_path = $tmp.'/index';
          $file = $root.$tmp.'/index.php';
        }
        // An alternative root exists, we look into it
        else if ( $this->alt_root && (strpos($tmp, $this->alt_root) === 0) ){
          if ( $tmp === $this->alt_root ){
            $name = '';
          }
          else{
            $name = substr($tmp, \strlen($this->alt_root)+1);
          }
          // Corresponding file
          if ( file_exists($this->get_alt_root($mode).$name.'/index.php') ){
            $plugin = $this->alt_root;
            $real_path = $tmp;
            $file = $this->get_alt_root($mode).$name.'/index.php';
            $root = $this->get_alt_root($mode);
          }
          // home.php in corresponding dir
        }
      }
      if ( $file ){
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
    if ( !$file && is_file($root.'404.php') ){
      $real_path = '404';
      $file = $root.'404.php';
    }
    if ( $file ){
      if ( $plugin && \defined('BBN_LOCALE') && isset($this->routes['root'][$plugin]['name']) ){
        bindtextdomain($this->routes['root'][$plugin]['name'], $this->routes['root'][$plugin]['path'].'../src/locale');
        bind_textdomain_codeset($this->routes['root'][$plugin]['name'], 'UTF-8');
        textdomain($this->routes['root'][$plugin]['name']);
      }
      return $this->set_known([
        'file' => $file,
        'path' => $real_path,
        'root' => \dirname($root, 2).'/',
        'request' => $path,
        'mode' => $mode,
        'plugin' => $plugin,
        'args' => $args
      ]);
    }
    // Aaaargh!
    die(bbn\x::dump("No default file defined for mode $mode $tmp (and no 404 file either)"));
  }

  private function find_in_roots($path){
    if ( $this->routes['root'] ){
      foreach ( $this->routes['root'] as $p => $real ){
        if ( (strpos($path, $p.'/') === 0) || ($p === $path) ){
          return $p;
        }
      }
    }
    return false;
  }

  private function find_mv(string $path, string $mode){
    if ( self::is_mode($mode) ){
      /** @var string $root Where the files will be searched for by default */
      $root = $this->get_root($mode);
      /** @var boolean|string $file Once found, full path and filename */
      $file = false;
      $plugin = false;
      if ( $alt_path = $this->find_in_roots($path) ){
        $alt_root = $this->get_alt_root($mode, $alt_path);
      }
      else if ( $alt_root = $this->get_alt_root($mode) ){
        $alt_path = $this->alt_root;
      }
      foreach ( self::$filetypes[$mode] as $t ){
        if ( is_file($root.$path.'.'.$t) ){
          $file = $root . $path . '.' . $t;
        }
        else if ( $alt_path && is_file($alt_root.substr($path, \strlen($alt_path)+1).'.'.$t) ){
          $file = $alt_root . substr($path, \strlen($alt_path)+1) . '.' . $t;
          $plugin = $alt_path;
        }
        if ( $file ){
          return $this->set_known([
            'file' => $file,
            'path' => $path,
            'ext' => $t,
            'mode' => $mode,
            'plugin' => $plugin
          ]);
        }
      }
    }
    return false;
  }

  private function find_alt_mv(string $path, string $mode, string $root){
    /** @var boolean|string $file Once found, full path and filename */
    $file = false;
    foreach ( self::$filetypes[$mode] as $t ){
      if ( is_file($root.$path.'.'.$t) ){
        $file = $root . $path . '.' . $t;
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

  public function reset(){
    $this->alt_root = false;
  }

  public function add_routes(array $routes){
    $this->routes = bbn\x::merge_arrays($this->routes['alias'], $routes);
    return $this;
  }

  public function set_prepath($path){
    if ( !$this->check_path($path) ){
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

  public function route($path, $mode, $root = null){

    if ( self::is_mode($mode) ){

      // If there is a prepath defined we prepend it to the path
      if ( $this->prepath && (strpos($path, '/') !== 0) && (strpos($path, $this->prepath) !== 0) ){
        $path = $this->prepath.$path;
      }

      // We only try to retrieve a file path through a whole URL for controllers
      if ( \in_array($mode, self::$controllers, true) ){
        $this->mode = $mode;
        //$this->log($path);
        return $this->find_controller($path, $mode);
      }
      else if ( $root ){
        return $this->find_alt_mv($path, $mode, $root);
      }
      return  $this->find_mv($path, $mode);
    }
    return false;
  }

  public function fetch_dir($path, $mode){

    // Only for views and models
    if ( self::is_mode($mode) && !\in_array($mode, self::$controllers) ){


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
        $dir1 = $this->parse($root.$path);
        if ( is_dir($dir1) && (strpos($dir1, $root) === 0) ){
          $dir = $dir1;
        }
        else if (
          $alt_path &&
          ($dir2 = $this->parse($alt_root.substr($path, \strlen($alt_path)+1))) &&
          (strpos($dir2, $alt_root) === 0) &&
          is_dir($dir2)
        ){
          $dir = $dir2;
        }
        if ( $dir ){
          $res = [];
          $files = bbn\file\dir::get_files($dir);
          foreach ( $files as $f ){
            if ( \in_array(bbn\str::file_ext($f), self::$filetypes[$mode], true) ){
              $res[] = $path.'/'.bbn\str::file_ext($f, true)[0];
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
