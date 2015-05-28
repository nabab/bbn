<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 07/12/2014
 * Time: 18:26
 */

namespace bbn;

if ( !defined('BBN_DATA_PATH') ){
  die("BBN_DATA_PATH must be defined");
}

class tpl {

  static private $engine, $tmp;

  static private function _init(){

  }

  static public function renderer($st){
    self::_init();
    if ( !is_string($st) ){
      die("The template parameter is not a string");
    }
    $md5 = md5($st);
    $file = BBN_DATA_PATH.'tmp/function.'.$md5.'.php';
    if ( file_exists($file) ){
      return include($file);
    }
    $tpl = \LightnCandy::compile($st, [
      'flags' => \LightnCandy::FLAG_MUSTACHELOOKUP |
        \LightnCandy::FLAG_PARENT |
        \LightnCandy::FLAG_HANDLEBARSJS |
        \LightnCandy::FLAG_ERROR_LOG
    ]);
    file_put_contents($file, $tpl);
    return include($file);
  }

  static public function render($st, $data){
    self::_init();
    if ( $tpl = self::renderer($st) ) {
      return $tpl($data);
    }
    return '';
  }

} 