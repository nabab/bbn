<?php
namespace bbn;

use LightnCandy\LightnCandy;

class tpl {

  static private $engine, $tmp;

  static public function renderer(string $st){
    if ( !defined('BBN_DATA_PATH') ){
      $dir = sys_get_temp_dir();
      if ( !@mkdir($dir.'/tmp') && !is_dir($dir.'/tmp') ){
        die('Impossible to create the template directory in '.$dir);
      }
      define('BBN_DATA_PATH', $dir.'/');
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
    if ( is_callable($tpl = self::renderer($st)) ){
      return $tpl($data);
    }
    return '';
  }
}
