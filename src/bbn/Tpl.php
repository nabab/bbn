<?php
namespace bbn;

use \LightnCandy\LightnCandy;

class Tpl {

  static private $engine, $tmp;

  /**
   * Generates a Mustache template function ready to receive parameters and returns it.
   * 
   * A temporary file is created if it does not already exists.
   * 
   * @param string $st The template's content
   * @return callable A function that can be called with the data as argument
   */
  static public function renderer(string $st): callable
  {
    if (\defined('BBN_DATA_PATH')) {
      $dir = File\Dir::createPath(BBN_DATA_PATH.'tmp/bbn-templates');
    }
    else {
      $dir = File\Dir::createPath(sys_get_temp_dir().'/bbn-templates');
    }

    if (!$dir) {
      throw new \Exception(X::_("Impossible to create the template directory"));
    }

    $md5 = md5($st);
    $file = $dir.'/tpl.'.$md5.'.php';
    if (!file_exists($file)) {
      $tpl = LightnCandy::compile(
        $st,
        [
          'flags' => LightnCandy::FLAG_MUSTACHELOOKUP |
            LightnCandy::FLAG_PARENT |
            LightnCandy::FLAG_HANDLEBARSJS |
            LightnCandy::FLAG_ERROR_LOG
        ]
      );
      file_put_contents($file, '<?php '.$tpl.'?>');
    }

    return include($file);
  }


  static public function render($st, $data){
    if ( is_callable($tpl = self::renderer($st)) ){
      return $tpl($data);
    }
    return '';
  }
}
