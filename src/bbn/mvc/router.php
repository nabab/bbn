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
    $routes = [
      'content' => [],
      'container' => []
    ],
    $def = 'default';

  private
    /**
     * The list of used controllers with their corresponding request, so we don't have to look for them again.
     * @var array
     */
    $known = [
      'container' => [],
      'content' => []
    ],
    /**
     * The path sent to the main controller.
     * @var null|string
     */
    $path = false;


  /**
   * This will fetch the route to the controller for a given path. Chainable
   *
   * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
   * @return void
   */
  public function __construct(\bbn\mvcv2 $mvc, array $routes=[])
  {
    if ( !defined('BBN_APP_PATH') ){
      die("The constant BBN_APP_PATH must be defined!");
    }
    $this->mvc = $mvc;
    $this->routes = $routes;
  }

  private function get_mode($mode=''){
    return !empty($mode) && isset(\bbn\mvc\view::$outputs[$mode]) ? $mode : $this->mvc->get_mode();
  }

  private function get_root($mode=''){
    return BBN_APP_PATH.'mvc/controllers/'.( empty($mode) ?  $this->mvc->get_mode() : $mode ).'/';
  }

  private function get_path($path=''){
    return empty($path) ? $this->mvc->get_url() : \bbn\str\text::parse_path($path);
  }

  private function add_path($path){

  }

  private function has_route($path, $mode=''){
    return isset($this->routes[$this->get_mode($mode)][$path]);
  }

  private function get_route($path, $mode=''){
    $mode = $this->get_mode($mode);
    if ( $this->has_route($path, $mode) ) {
      if ( is_array($this->routes[$mode][$path]) ){
        return $this->routes[$mode][$path][0];
      }
      else{
        return $this->routes[$mode][$path];
      }
    }
    return false;
  }

  private function is_known($path, $mode=''){
    return isset($this->known[$this->get_mode($mode)][$path]);
  }

  private function get_known($path, $mode=''){
    if ( $this->is_known($path, $mode) ){
      return $this->known[$this->get_mode($mode)][$path];
    }
    return false;
  }

  private function set_known($path, $mode=''){
    $mode = $this->get_mode($mode);
    $root = $this->get_root($mode);
    $path0 = $path;
    if ( !isset($this->known[$mode][$path]) ){
      $this->known[$mode][$path0] = [
        'path' => $root.$path.'.php',
        'checkers' => []
      ];
      while ( strlen($path) > 0 ){
        $path = dirname($path);
        $ctrl = $root.( $path === '.' ? '' : $path.'/' ).'_ctrl.php';
        if ( is_file($ctrl) ){
          array_unshift($this->known[$mode][$path0]['checkers'], $ctrl);
        }
        if ( $path === '.' ){
          $path = '';
        }
      }
    }
    \bbn\tools::hdump($this->known);
    return $this->known[$mode][$path0];
  }

  public function add_routes(array $routes){
    $this->routes = \bbn\tools::merge_arrays($this->routes, $routes);
    return $this;
  }

  public function route($type='controller', $path='', $mode=''){

    $mode = $this->get_mode($mode);
    $root = $this->get_root($mode);
    $path = $this->get_path($path);

    // We go through each path, starting by the longest until it's empty
    while ( strlen($path) > 0 ){
      if ( $this->is_known($path, $mode) ){
        return $this->get_known($path, $mode);
      }
      else if ( $this->has_route($path, $mode) ){
        return $this->set_known($this->get_route($path, $mode));
      }
      else if ( file_exists($root.$path.'.php') ) {
        return $this->set_known($path, $mode);
      }
      else{
        $path = strpos($path, '/') === false ? '' : substr($path, 0, strrpos($path, '/'));
      }
    }
    if ( $this->is_known(self::$def, $mode) ){
      return $this->get_known(self::$def, $mode);
    }
    else if ( $this->has_route(self::$def, $mode) ){
      $path = $this->get_route(self::$def, $mode);
      return $this->set_known($path, $mode);
    }
    die("No default file defined for mode $mode");
  }
}