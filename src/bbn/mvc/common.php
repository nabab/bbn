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
    if ( ($this->plugin || $plugin) && \defined ('BBN_DATA_PATH') ){
      return BBN_DATA_PATH.'plugins/'.$this->plugin_name($plugin ?: $this->plugin).'/';
    }
    return null;
  }

  public function get_plugins(){
    return $this->mvc->get_plugins();
  }

  public function has_plugin($plugin){
    return $this->mvc->has_plugin($plugin);
  }

  public function is_plugin($plugin = null){
    return $this->mvc->is_plugin($plugin ?: $this->plugin_name($this->plugin));
  }

  public function plugin_path($plugin = null){
    return $this->mvc->plugin_path($plugin ?: $this->plugin_name($this->plugin));
  }

  public function plugin_url($plugin = null){
    return $this->mvc->plugin_url($plugin ?: $this->plugin_name($this->plugin));
  }

  public function plugin_name($path = null){
    return $this->mvc->plugin_name($path ?: $this->plugin);
  }

  public function get_cookie(){
    return $this->mvc->get_cookie();
  }

}