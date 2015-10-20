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
      'html' => ['html'],
      'js' => ['js', 'coffee'],
      'css' => ['css', 'less', 'scss']
    ],
    /**
     * The list of types
     * @var array
     */
    $types = [
      'cli',
      'internal',
      'dom',
      'public',
      'model',
      'html',
      'js',
      'css'
    ];

  private
    $prepath,
    /**
     * The path to the app root (where is ./mvc)
     * @var string
     */
    $root,
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
    $this->set_root();
  }

  public function set_root($dir=''){
    $ok = false;
    if ( empty($dir) ){
      $dir = BBN_APP_PATH;
      $ok = 1;
    }
    else{
      $dir = \bbn\str\text::parse_path($dir);
      if ( substr($dir, -1) !== '/' ){
        $dir .= '/';
      }
    }
    if ( $ok || is_dir($dir.'mvc') ){
      $this->root = $dir;
      return $this;
    }
    die("I CAN'T FIND ROOT in $dir... NO mvc DIRECTORY!");
  }

  private function get_root($mode){
    if ( self::is_mode($mode) ){
      return $this->root.'mvc/'.( $mode === 'dom' ? 'public' : $mode ).'/';
    }
    return false;
  }

  private function parse($path){
    return \bbn\str\text::parse_path($path);
  }

  private function has_route($path){
    return is_string($path) && isset($this->routes[$path]);
  }

  private function get_route($path){
    if ( $this->has_route($path) ) {
      if ( is_array($this->routes[$path]) ){
        return $this->routes[$path][0];
      }
      else{
        return $this->routes[$path];
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
      $this->log("known", $this->known);
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
          $tmp = $this->parse(dirname($tmp));
          $ctrl = $root.( $tmp === '.' ? '' : $tmp.'/' ).'_ctrl.php';
          if ( is_file($ctrl) ){
            array_unshift($this->known[$mode][$path]['checkers'], $ctrl);
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
    return $this->known[$mode][$path];
  }

  public function add_routes(array $routes){
    $this->routes = \bbn\tools::merge_arrays($this->routes, $routes);
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
      $root = $this->get_root($mode);
      $path = $this->parse($path);
      if ( $this->prepath && (strpos($path, '/') !== 0) && (strpos($path, $this->prepath) !== 0) ){
        $path = $this->prepath.$path;
      }

      if (in_array($mode, self::$controllers)) {
        $tmp = $path ? $path : '.';
        $args = [];
        // We go through each path, starting by the longest until it's empty
        while (strlen($tmp) > 0) {
          if ($this->is_known($tmp, $mode)) {
            $this->log("case -4");
            return $this->get_known($tmp, $mode);
          }
          if ( $mode === 'dom' ){
            if ( $tmp === '.' ){
              if ( file_exists($root.'index.php') ){
                $this->log("case -3");
                return $this->set_known([
                  'file' => $root . 'index.php',
                  'path' => 'index',
                  'request' => $path,
                  'mode' => 'dom',
                  'args' => $args
                ]);
              }
              break;
            }
            else if ( file_exists($root.$tmp.'/index.php') ){
              $this->log("case -2");
              return $this->set_known([
                'file' => $root . $tmp . '/index.php',
                'path' => $tmp. '/index',
                'request' => $path,
                'mode' => 'dom',
                'args' => $args
              ]);
            }
          }
          if ( (($mode === 'dom') && (BBN_DEFAULT_MODE === 'dom')) || ($mode !== 'dom') ){
            if ( $this->has_route($tmp) ){
              $tmp = $this->get_route($tmp);
              $file = $root.$tmp.'.php';
              if ( !is_file($file) ){
                die("The file $file specified by the route $tmp doesn't exist.");
              }
              $this->log("case -1");
              return $this->set_known([
                'file' => $root . $tmp . '.php',
                'path' => $tmp,
                'request' => $path,
                'mode' => $mode,
                'args' => $args
              ]);
            }
            else if (file_exists($root.$tmp.'.php')) {
              $this->log("case 0");
              return $this->set_known([
                'file' => $root . $tmp . '.php',
                'path' => $tmp,
                'request' => $path,
                'mode' => $mode,
                'args' => $args
              ]);
            }
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
        if ( ((($mode === 'dom') && (BBN_DEFAULT_MODE === 'dom')) || ($mode !== 'dom') ) && $this->is_known(self::$def, $mode) ){
          $this->log("case 1");
          return $this->get_known(self::$def, $mode);
        }
        if ( ((($mode === 'dom') && (BBN_DEFAULT_MODE === 'dom')) || ($mode !== 'dom') ) && $this->has_route(self::$def)) {
          $this->log("case 2");
          $tmp = $this->get_route(self::$def);
          return $this->set_known([
            'file' => $root . $tmp . '.php',
            'path' => $tmp,
            'request' => $path,
            'mode' => $mode,
            'args' => $args
          ]);
        }
        die("No default file defined for mode $mode");
      }
      else {
        foreach ( self::$filetypes[$mode] as $t ){
          $this->log("case 3");
          if ( is_file($root.$path.'.'.$t) ){
            return $this->set_known([
              'file' => $root . $path.'.'.$t,
              'path' => $path,
              'ext' => $t,
              'mode' => $mode
            ]);
          }
        }
      }
    }
    return false;
  }
}
