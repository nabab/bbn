<?php
namespace bbn;

use Exception;
use LightnCandy\LightnCandy;
use LightnCandy\Flags;
use bbn\File\Dir;

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
      $dir = Dir::createPath(Mvc::getTmpPath() . '/bbn-templates');
    }
    else {
      $dir = Dir::createPath(sys_get_temp_dir().'/bbn-templates');
    }

    if (!$dir) {
      throw new Exception(X::_("Impossible to create the template directory"));
    }

    $md5 = md5($st);
    $file = $dir.'/tpl.'.$md5.'.php';
    $fp = @fopen($file, 'x');

    if ($fp !== false) {
      $tpl = LightnCandy::compile(
        $st,
        [
          'flags' => Flags::FLAG_MUSTACHELOOKUP |
            Flags::FLAG_PARENT |
            Flags::FLAG_HANDLEBARSJS |
            Flags::FLAG_ERROR_LOG
        ]
      );
      fwrite($fp, '<?php '.$tpl.'?>');
      fclose($fp);
    }

    return include($file);
  }


  static public function render($st, $data): string
  {
    if ( is_callable($tpl = self::renderer($st)) ){
      return $tpl($data);
    }
    return '';
  }
}
