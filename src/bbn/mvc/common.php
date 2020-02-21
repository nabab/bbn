<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:17
 */

namespace bbn\mvc;
use bbn;


trait common {

  /**
   * The MVC class from which the controller is called
   * @var mvc
   */
  private $_mvc;
  /**
   * @var null|string If the controller is inside a plugin this property will be set to its name
   */
  private $_plugin;

  /**
   * This checks whether an argument used for getting controller, view or model - which are files - doesn't contain malicious content.
   *
   * @param string $p The request path <em>(e.g books/466565 or html/home)</em>
   * @return bool
   */
  private function check_path(){
    $ar = \func_get_args();
    foreach ( $ar as $a ){
      $b = bbn\str::parse_path($a, true);
      if ( empty($b) && !empty($a) ){
        $this->error("The path $a is not an acceptable value");
        return false;
      }
    }
    return 1;
  }

  private function error($msg){
    $msg = "Error from ".\get_class($this).": ".$msg;
    $this->log($msg, 'mvc');
    die($msg);
  }

  public function log(){
    if ( bbn\mvc::get_debug() ){
      $ar = \func_get_args();
      bbn\x::log(\count($ar) > 1 ? $ar : $ar[0], 'mvc');
    }
  }

  public function plugin_data_path($plugin = null): ?string
  {
    if ( ($this->_plugin || $plugin) && \defined ('BBN_DATA_PATH') ){
      return BBN_DATA_PATH.'plugins/'.$this->plugin_name($plugin ?: $this->_plugin).'/';
    }
    return null;
  }

  public function get_plugins(){
    return $this->_mvc->get_plugins();
  }

  public function has_plugin($plugin){
    return $this->_mvc->has_plugin($plugin);
  }

  public function is_plugin($plugin = null){
    return $this->_mvc->is_plugin($plugin ?: $this->plugin_name($this->_plugin));
  }

  public function plugin_path($plugin = null, $raw = false){
    return $this->_mvc->plugin_path($plugin ?: $this->plugin_name($this->_plugin), $raw);
  }

  public function plugin_url($plugin = null){
    return $this->_mvc->plugin_url($plugin ?: $this->plugin_name($this->_plugin));
  }

  public function plugin_name($path = null){
    return $this->_mvc->plugin_name($path ?: $this->_plugin);
  }

  public function get_cookie(){
    return $this->_mvc->get_cookie();
  }

  public function get_routes(){
    return $this->_mvc->get_routes();
  }

  public function get_aliases(){
    return $this->_mvc->get_routes('alias');
  }

  public function get_route($path, $mode, $root = null){
    return $this->_mvc->get_route($path, $mode, $root);
  }

  public function app_path($raw = false): string
  {
    return \bbn\mvc::get_app_path($raw);
  }

  public function lib_path(): string
  {
    return \bbn\mvc::get_lib_path();
  }

  public function data_path(string $plugin = null): string
  {
    return \bbn\mvc::get_data_path().($plugin ? 'plugins/'.$plugin.'/' : '');
  }

  public function tmp_path(string $plugin = null): string
  {
    return \bbn\mvc::get_tmp_path($plugin);
  }

  public function log_path(string $plugin = null): string
  {
    return \bbn\mvc::get_log_path($plugin);
  }

  public function cache_path(string $plugin = null): string
  {
    return \bbn\mvc::get_cache_path($plugin);
  }

  public function content_path(string $plugin = null): string
  {
    return \bbn\mvc::get_content_path($plugin);
  }

  public function user_tmp_path(string $id_user = null, string $plugin = null):? string
  {
    return \bbn\mvc::get_user_tmp_path($id_user, $plugin);
  }

  public function user_data_path(string $id_user = null, string $plugin = null):? string
  {
    return \bbn\mvc::get_user_data_path($id_user, $plugin);
  }
}