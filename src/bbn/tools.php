<?php
namespace bbn;
/**
 * Model View Controller Class
 *
 *
 * This class will route a request to the according model and/or view through its controller.
 * A model and a view can be automatically associated if located in the same directory branch with the same name than the controller in their respective locations
 * A view can be directly imported in the controller through this very class
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */

class tools
{
	/**
	 * Add information to the $info array
	 *
	 * @param string $st
	 * @return null
	 */
	public static function report($st)
	{
		if ( !isset(self::$cli) )
		{
			global $argv;
			self::$cli = isset($argv) ? 1 : false;
		}
		if ( self::$cli )
		{
			if ( is_string($st) )
				echo $st."\n";
			else
				var_dump($st)."\n";
		}
		else
		{
			if ( is_string($st) )
				array_push(self::$info,$st);
			else
				array_push(self::$info,print_r($st,true));
		}
	}
	/**
	 * Add information to the $info array
	 *
	 * @param string $st
	 * @param string $file
	 * @return null
	 */
	public static function log($st, $file='misc')
	{
		if ( defined('BBN_DATA_PATH') ){
			$log_file = BBN_DATA_PATH.'logs/'.$file.'.log';
      $backtrace = array_filter(debug_backtrace(), function($a){
        return $a['function'] === 'log';
      });
      $i = end($backtrace);
			$r = "[".date('d/m/Y H:i:s')."]\t".$i['file']." - line ".$i['line'].
              self::get_dump($st).PHP_EOL;
      
      if ( php_sapi_name() === 'cli' ){
        echo $r;
      }
      $s = ( file_exists($log_file) ) ? filesize($log_file) : 0;
			if ( $s > 1048576 )
			{
				file_put_contents($log_file.'.old',file_get_contents($log_file),FILE_APPEND);
				file_put_contents($log_file,$r);
			}
			else{
				file_put_contents($log_file,$r,FILE_APPEND);
      }
		}
	}
  public static function merge_objects($o1, $o2){
    $args = func_get_args();
    if ( count($args) > 2 ){
      for ( $i = count($args) - 1; $i > 1; $i-- ){
        $args[$i-1] = self::merge_arrays($args[$i-1], $args[$i]);
      }
      $o2 = $args[1];
    }
    $a1 = self::to_array($o1);
    $a2 = self::to_array($o2);
    $res = self::merge_arrays($a1, $a2);
    return self::to_object($res);
  }
 	/**
	 * Removes all the elements from the items array, and reset the default config
	 * @return void
	 */
  public static function merge_arrays(array $a1, array $a2) {
    $args = func_get_args();
    if ( count($args) > 2 ){
      for ( $i = count($args) - 1; $i > 1; $i-- ){
        $args[$i-1] = self::merge_arrays($args[$i-1], $args[$i]);
      }
      $a2 = $args[1];
    }
    if ( self::is_assoc($a1) && self::is_assoc($a2) ){
      $keys = array_unique(array_merge(array_keys($a1), array_keys($a2)));
      $r = [];
      foreach ( $keys as $k ) {
        if ( !array_key_exists($k, $a1) && !array_key_exists($k, $a2) ){
          continue;
        }
        else if ( !array_key_exists($k, $a2) ){
          $r[$k] = $a1[$k];
        }
        else if ( !array_key_exists($k, $a1) || !is_array($a2[$k]) || !is_array($a1[$k]) || is_numeric(key($a2[$k])) ){
          $r[$k] = $a2[$k];
        }
        else{
          $r[$k] = self::merge_arrays($a1[$k], $a2[$k]);
        }
      }
    }
    else{
      $r = array_merge($a1, $a2);
    }
    return $r;
  }
  
  /*
   * Makes an object of an array
   * @return false|object
   */
  public static function to_object($ar){
    if ( is_string($ar) ){
      return json_decode($ar);
    }
    if (is_object($ar) ){
      $ar = self::to_array($ar);
    }
    if ( count($ar) === 0 ){
      return new \stdClass();
    }
    if ( ($r = json_encode($ar)) ){
      return json_decode($r);
    }
    return false;
  }

  /*
   * Makes an object of an array
   * @return false|object
   */
  public static function to_array($obj){
    if ( is_string($obj) ){
      return json_decode($obj, 1);
    }
    if ( is_object($obj) || is_array($obj) ){
      foreach ( $obj as $i => $o ){
        if ( is_array($o) || is_object($o) ){
          if ( is_array($obj) ){
            $obj[$i] = self::to_array($o);
          }
          else{
            $obj->$i = self::to_array($o);
          }
        }
      }
    }
    return (array) $obj;
  }

  /**
   * Indents a flat JSON string to make it more human-readable.
   *
   * @param string $json The original JSON string to process.
   * @return string Indented version of the original JSON string.
   */
  public static function indent_json($json) {

      $result      = '';
      $pos         = 0;
      $strLen      = strlen($json);
      $indentStr   = '  ';
      $newLine     = "\n";
      $prevChar    = '';
      $outOfQuotes = true;

      for ($i=0; $i<=$strLen; $i++) {

          // Grab the next character in the string.
          $char = substr($json, $i, 1);

          // Are we inside a quoted string?
          if ($char == '"' && $prevChar != '\\') {
              $outOfQuotes = !$outOfQuotes;

          // If this character is the end of an element,
          // output a new line and indent the next line.
          } else if(($char == '}' || $char == ']') && $outOfQuotes) {
              $result .= $newLine;
              $pos --;
              for ($j=0; $j<$pos; $j++) {
                  $result .= $indentStr;
              }
          }

          // Add the character to the result string.
          $result .= $char;

          // If the last character was the beginning of an element,
          // output a new line and indent the next line.
          if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
              $result .= $newLine;
              if ($char == '{' || $char == '[') {
                  $pos ++;
              }

              for ($j = 0; $j < $pos; $j++) {
                  $result .= $indentStr;
              }
          }

          $prevChar = $char;
      }

      return $result;
  }
  
  /**
   * Returns an object or an array cleaned up from all empty values
   *
   * @param array|object $arr An object or array to clean
   * @return string The clean result
   */
  public static function remove_empty($arr, $remove_space=false){
    foreach ( $arr as $k => $v ){
      if ( is_object($arr) ){
        if ( is_array($v) || is_object($v) ){
          $arr->$k = self::remove_empty($v);
        }
        if ( empty($arr->$k) ){
          unset($arr->$k);
        }
      }
      else{
        if ( is_array($v) || is_object($v) ){
          $arr[$k] = self::remove_empty($v);
        }
        else if ( $remove_space ){
          $arr[$k] = trim($arr[$k]);
        }
        if ( empty($arr[$k]) ){
          unset($arr[$k]);
        }
      }
    }
    return $arr;
  }
  
  public static function to_groups(array $arr, $keyname = 'value', $valname = 'text'){
    $r = [];
    foreach ( $arr as $k => $v ){
      $r[] = [$keyname => $k, $valname => $v];
    }
    return $r;
  }
  
  public static function is_assoc(array $r){
    $keys = array_keys($r);
    $c = count($keys);
    for ( $i = 0; $i < $c; $i++ ){
      if ( $keys[$i] !== $i ){
        return 1;
      }
    }
    return false;
  }
  
  public static function get_dump(){
    $args = func_get_args();
    $st = '';
    foreach ( $args as $a ){
      $r = $a;
      if ( is_null($a) ){
        $r = 'null';
      }
      else if ( $a === false ){
        $r = 'false';
      }
      else if ( $a === true ){
        $r = 'true';
      }
      else if ( $a === 0 ){
        $r = '0';
      }
      else if ( $a === '' ){
        $r = '""';
      }
      else if ( !$a ){
        $r = '0';
      }
      else if ( is_object($a) || is_array($a) ){
        $r = \bbn\str\text::export($a);
      }
      $st .= $r.PHP_EOL;
    }
    return PHP_EOL.$st.PHP_EOL;
  }
  
  public static function get_hdump(){
    return '<p style="white-space:pre">'.htmlentities(call_user_func_array('self::get_dump', func_get_args())).'</p>';
  }
  
  public static function dump(){
    echo call_user_func_array('self::get_dump', func_get_args());
    
  }
  
  public static function hdump(){
    echo call_user_func_array('self::get_hdump', func_get_args());
  }

  public static function build_options($values, $selected='', $empty_label=false){
    if ( is_array($values) )
    {
      $r = '';
      if ( $empty_label !== false ){
        $r .= '<option value="">'.$empty_label.'</option>';
      }
      foreach ( $values as $k => $v )
      {
        if ( is_array($v) && count($v) == 2 )
        {
          $value = $v[0];
          $title = $v[1];
        }
        else if ( !isset($values[0]) ){
          $value = $k;
          $title = $v;
        }
        else if ( is_string($v) )
          $value = $title = $v;
        if ( isset($value,$title) ){
          $r .= '<option value="'.$value.'"'.
                  ( $value == $selected ? ' selected="selected"' : '').
                  '>'.$title.'</option>';
        }
        unset($value,$title);
      }
      return $r;
    }
  }
  
  public static function to_keypair($arr, $protected=1){
    $num = count($arr);
    $res = [];
    if ( ($num % 2) === 0 ){
      $i = 0;
      while ( isset($arr[$i]) ){
        if ( !$protected || preg_match('/[0-9A-z\-_]+/', $arr[$i]) ){
          $res[$arr[$i]] = $arr[$i+1];
        }
        $i += 2;
      }
    }
    return $res;
  }
  
  public static function max_with_key($array, $key) {
    if (!is_array($array) || count($array) == 0) return false;
    $max = $array[0][$key];
    foreach($array as $a) {
      if($a[$key] > $max) {
        $max = $a[$key];
      }
    }
    return $max;
  }

  public static function min_with_key($array, $key) {
    if (!is_array($array) || count($array) == 0) return false;
    $min = $array[0][$key];
    foreach($array as $a) {
      if($a[$key] < $min) {
        $min = $a[$key];
      }
    }
    return $min;
  }
  
  public static function debug($file=''){
    $debug = array_map(function($a){
      if ( isset($a['object']) ){
        unset($a['object']);
      }
      return $a;
    }, debug_backtrace());
    if ( empty($file) ){
      self::hdump($debug);
    }
    else{
      self::log($debug, $file);
    }
  }
  
  public static function sort(&$ar){
    usort($ar, function($a, $b){
      $a = str_replace('.', '0', str_replace('_', '1', \bbn\str\text::change_case($a, 'lower')));
      $b = str_replace('.', '0', str_replace('_', '1', \bbn\str\text::change_case($b, 'lower')));
      return strcmp($a, $b);
    });
  }
  
}
?>