<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:17
 */

namespace bbn\mvc;


trait common {

  /**
   * This checks whether an argument used for getting controller, view or model - which are files - doesn't contain malicious content.
   *
   * @param string $p The request path <em>(e.g books/466565 or html/home)</em>
   * @return bool
   */
  private function check_path()
  {
    $ar = func_get_args();
    foreach ( $ar as $a ){
      $a = \bbn\txt::parse_path($a);
      if ( !is_string($a) ||
        (strpos($a,'./') !== false) ||
        (strpos($a,'/') === 0) ){
        $this->error("The path $a is not an acceptable value");
      }
    }
    return 1;
  }

  private function error($msg){
    $msg = "Error from ".get_class($this).": ".$msg;
    $this->log($msg, 'mvc');
    die($msg);
  }

  public function log(){
    if ( \bbn\mvc::get_debug() ){
      $ar = func_get_args();
      foreach ( $ar as $a ){
        \bbn\x::log($a, 'mvc');
      }
    }
  }

}