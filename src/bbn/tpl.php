<?php
namespace bbn;

use LightnCandy\LightnCandy;

if ( !defined('BBN_DATA_PATH') ){
  die("BBN_DATA_PATH must be defined");
}

class tpl {

  static private $engine, $tmp;

  static public function renderer($st){
    if ( !is_string($st) ){
      die("The template parameter is not a string");
    }
    $md5 = md5($st);
    $file = BBN_DATA_PATH.'tmp/tpl.'.$md5.'.php';
    if ( file_exists($file) ){
      return include($file);
    }
    $tpl = LightnCandy::compile($st, [
      'flags' => LightnCandy::FLAG_MUSTACHELOOKUP |
        LightnCandy::FLAG_PARENT |
        LightnCandy::FLAG_HANDLEBARSJS |
        LightnCandy::FLAG_ERROR_LOG
    ]);
    file_put_contents($file, '<?php '.$tpl.'?>');
    return include($file);
  }

  static public function render($st, $data){
    if ( is_callable($tpl = self::renderer($st)) ) {
      return $tpl($data);
    }
    return '';
  }
}
