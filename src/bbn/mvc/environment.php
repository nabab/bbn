<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 12/05/2015
 * Time: 12:53
 */

namespace bbn\mvc;

class environment {

  private
    /**
     * The list of views which have been loaded. We keep their content in an array to not have to include the file again. This is useful for loops.
     * @var array
     */
    $params = [],
    /**
     * The mode of the output (doc, html, json, txt, xml...)
     * @var null|string
     */
    $mode,
    /**
     * The request sent to the server to get the actual controller.
     * @var null|string
     */
    $url,
    /**
     * @var array $_POST
     */
    $post,
    /**
     * @var array $_GET
     */
    $get,
    /**
     * @var array $_FILES
     */
    $files,
    /**
     * Determines if it is sent through the command line
     * @var boolean
     */
    $cli,
    $new_url;

  private function set_params($path)
  {
    if ( !is_null($this->params) ) {
      $this->params = [];
      $tmp = explode('/', \bbn\str\text::parse_path($path));
      foreach ( $tmp as $t ) {
        if ( !empty($t) ) {
          if ( in_array($t, \bbn\mvc::$reserved) ){
            die("The controller you are asking for contains one of the following reserved strings: " .
              implode(", ", \bbn\mvc::$reserved));
          }
          array_push($this->params, $t);
        }
      }
    }
  }

  /**
   * Change the output mode (content-type)
   *
   * @param $mode
   * @return string $this->mode
   */
  private function set_mode($mode){
    if ( router::is_mode($mode) ) {
      $this->mode = $mode;
    }
    return $this->mode;
  }

  private function _init(){
    // When using CLI a first parameter can be used as route,
    // a second JSON encoded can be used as $this->post
    if ( $this->cli ){
      $this->mode = 'cli';
      $this->get_cli();
    }
    // Non CLI request
    else{
      $this->get_post();
    }
    $this->url = implode('/', $this->params);
    return $this;
  }

  public function __construct($url=false){
    $this->cli = (php_sapi_name() === 'cli');
    $this->_init();
  }

  public function set_prepath($path){
    $path = \bbn\tools::remove_empty(explode('/', $path));
    if ( count($path) ) {
      foreach ($path as $p) {
        if ($this->params[0] === $p) {
          array_shift($this->params);
        }
        else {
          die("The prepath $path doesn't seem to correspond to the current path {$this->url}");
        }
      }
    }
    return true;
  }

  /**
   * Returns true if called from CLI/Cron, false otherwise
   *
   * @return boolean
   */
  public function is_cli(){
    if ( is_null($this->cli) ){
      $this->cli = (php_sapi_name() === 'cli');
    }
    return $this->cli;
  }

  public function get_url(){
    return $this->url;
  }

  public function simulate($url){
    $this->post = null;
    $this->new_url = $url;
    $this->_init();
  }

  public function get_mode(){
    return $this->mode;
  }

  public function get_cli(){
    global $argv;
    if ( $this->is_cli() && is_null($this->post) ){
      $this->post = [];
      if ( isset($argv[1]) ){
        $this->set_params($argv[1]);
        if ( isset($argv[2]) && ($json = json_decode($argv[2], 1)) ){
          // Data are "normalized" i.e. types are changed through str\text::correct_types
          $this->post = array_map(function($a){
            return \bbn\str\text::correct_types($a);
          }, $json);
        }
      }
      return $this->post;
    }
  }

  public function get_get(){
    if ( is_null($this->get) ){
      $this->get = [];
      if ( count($_GET) > 0 ){
        $this->get = array_map(function($a){
          return \bbn\str\text::correct_types($a);
        }, $_GET);
      }
    }
    return $this->get;
  }

  public function get_post(){
    if ( is_null($this->post) ){
      $this->post = [];
      // If data is post as in the appui SPA framework, mode is assumed to be BBN_DEFAULT_MODE, json by default
      if ( count($_POST) > 0 ){
        // Data are "normalized" i.e. types are changed through str\text::correct_types
        $this->post = array_map(function($a){
          return \bbn\str\text::correct_types($a);
        }, $_POST);
        /** @todo Remove the json parameter from the appui.js functions */
        if ( isset($this->post['appui']) && ($this->post['appui'] !== 'json') ){
          $this->set_mode($this->post['appui']);
          unset($this->post['appui']);
        }
        else {
          unset($this->post['appui']);
          $this->set_mode(BBN_DEFAULT_MODE);
        }
      }
      // If no post, assuming to be a DOM document
      else if ( count($_FILES) ){
        $this->set_mode(BBN_DEFAULT_MODE);
      }
      else {
        $this->set_mode('dom');
      }
      if ( $this->new_url ){
        $current = $this->new_url;
      }
      else if ( isset($_SERVER['REQUEST_URI']) ){
        $current = $_SERVER['REQUEST_URI'];
      }
      if ( isset($current) &&
        ( BBN_CUR_PATH === '/' || strpos($current, BBN_CUR_PATH) !== false ) ){
        $url = explode("?", urldecode($current))[0];
        if ( BBN_CUR_PATH === '/' ) {
          $this->set_params($url);
        }
        else{
          $this->set_params(substr($url, strlen(BBN_CUR_PATH)));
        }
      }
    }
    return $this->post;
  }

  public function get_files(){
    if ( is_null($this->files) ){
      $this->files = [];
      // Rebuilding the $_FILES array into $this->files in a more logical structure
      if ( count($_FILES) > 0 ){
        foreach ( $_FILES as $n => $f ){
          if ( is_array($f['name']) ){
            $this->files[$n] = [];
            foreach ( $f['name'] as $i => $v ){
              array_push($this->files[$n], [
                'name' => $v,
                'tmp_name' => $f['tmp_name'][$i],
                'type' => $f['type'][$i],
                'error' => $f['error'][$i],
                'size' => $f['size'][$i],
              ]);
            }
          }
          else{
            $this->files[$n] = $f;
          }
        }
      }
    }
    return $this->files;
  }

  public function get_params(){
    return $this->params;
  }
}