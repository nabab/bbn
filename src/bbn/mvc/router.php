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

  private
    $is_routed,
    /**
     * The list of used controllers with their corresponding request, so we don't have to look for them again.
     * @var array
     */
    $known_controllers = [],
    /**
     * The path sent to the main controller.
     * @var null|string
     */
    $path;


  /**
   * This will fetch the route to the controller for a given path. Chainable
   *
   * @param string $path The request path <em>(e.g books/466565 or xml/books/48465)</em>
   * @return void
   */
  public function __construct(\bbn\mvcv2 $mvc)
  {
    $this->mvc = $mvc;
  }

  public function route(){
    if ( !$this->is_routed ){
      $this->is_routed = 1;
      $path = $this->mvc->get_url();
      $fpath = $path;

      // We go through each path, starting by the longest until it's empty
      while ( strlen($fpath) > 0 ){
        if ( isset($this->known_controllers[$fpath]) ){

        }
        else if ( isset($this->routes[$fpath]) ){
          $s1 = strlen($path);
          $s2 = strlen($fpath);
          $add = ($s1 !== $s2) ? substr($path, $s2) : '';
          $this->path = (is_array($this->routes[$fpath]) ? $this->routes[$fpath][0] :
              $this->routes[$fpath]).$add;
        }
        else{
          $fpath = strpos($fpath,'/') === false ? '' : substr($this->path,0,strrpos($fpath,'/'));
        }
      }
      if ( !isset($this->path) ) {
        $this->path = $path;
      }

      $this->controller = new \bbn\mvc\controller($this->mvc, $this->path);
      if ( !$this->controller->exists() ){
        if ( isset($this->routes['default']) ) {
          $this->controller = new \bbn\mvc\controller($this->mvc, $this->routes['default'] . '/' . $this->path);
        }
        else {
          $this->controller = new \bbn\mvc\controller($this->mvc, '404');
          if ( !$this->controller->exists() ){
            header('HTTP/1.0 404 Not Found');
            exit();
          }
        }
      }
    }
    return $this;
  }


}