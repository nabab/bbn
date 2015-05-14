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

  private static $routes = [
    'content' => [],
    'container' => []
  ];

  private
    /**
     * The list of used controllers with their corresponding request, so we don't have to look for them again.
     * @var array
     */
    $known_controllers = [
      'container' => [],
      'content' => []
    ],
    /**
     * Is set to null while not routed, then 1 if routing was successful, and false otherwise.
     * @var null|boolean
     */
    $is_routed,
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

  private function get_mode(){
    return $this->mvc->get_mode();
  }

  private function get_url(){
    return $this->mvc->get_url();
  }

  public function add_routes(array $routes){
    $this->routes = \bbn\tools::merge_arrays($this->routes, $routes);
    return $this;
  }

  public function route($path='', $mode=''){

    $root = BBN_APP_PATH.'mvc/controllers/'.( empty($mode) ?  $this->mvc->get_mode() : $mode ).'/';

    $path = empty($path) ? $this->get_url() : \bbn\str\text::parse_path($path);

    // We go through each path, starting by the longest until it's empty
    while ( strlen($path) > 0 ){
      if ( isset($this->known_controllers[$this->get_mode()][$path]) ){
        return $this->known_controllers[$this->get_mode()][$path];
      }
      else if ( isset($this->routes[$this->get_mode()][$path]) ){

        $s1 = strlen($this->get_url());
        $s2 = strlen($path);

        /* @todo Needs comment */
        $add = ($s1 !== $s2) ? substr($this->get_url(), $s2) : '';
        if ( is_array($this->routes[$this->get_mode()][$path]) ){
          return $this->routes[$this->get_mode()][$path][0];
        }
        else{
          return $this->routes[$this->get_mode()][$path].$add;
        }
      }
      else if ( file_exists($root.$path.'.php') ) {
        return $path;
      }
      else{
        $path = strpos($path, '/') === false ? '' : substr($path, 0, strrpos($path, '/'));
      }
    }
    if ( isset($this->routes[$this->get_mode()]['default']) &&
                file_exists($root.$this->routes[$this->get_mode()]['default'].'.php') ){
      return $this->routes[$this->get_mode()]['default'];
    }
    return false;
  }






}