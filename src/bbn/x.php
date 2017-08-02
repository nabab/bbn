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
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */

class x
{

  private static $counters = [];

  private static function _init_count(string $name){
    if ( !$name ){
      $name = 'num';
    }
    if ( !isset(self::$counters[$name]) ){
      self::$counters[$name] = 0;
    }
  }

  /**
   * @param string $name
   * @param int $i
   */
  public static function increment(string $name = 'num', int $i = 1){
    self::_init_count($name);
    self::$counters[$name] += $i;
  }

  /**
   * @param string $name
   * @return mixed
   */
  public static function count(string $name = 'num'){
    self::_init_count($name);
    $tmp = self::$counters[$name];
    unset(self::$counters[$name]);
    return $tmp;
  }

  public static function count_all($delete = false){
    $tmp = self::$counters;
    if ( $delete ){
      self::$counters = [];
    }
    return $tmp;
  }

  /**
   * Saves logs into a file.
   *
   * ```php
   * \bbn\x::log('My text', 'FileName');
   * ```
   *
   * @param string $st Text to save.
   * @param string $file Filename, default: "misc".
   * @return void
   */
  public static function log($st, $file='misc'){
    if ( defined('BBN_DATA_PATH') ){
      if ( !is_string($file) ){
        $file = 'misc';
      }
      $log_file = BBN_DATA_PATH.'logs/'.$file.'.log';
      $backtrace = array_filter(debug_backtrace(), function($a){
        return $a['function'] === 'log';
      });
      $i = end($backtrace);
      $r = "[".date('d/m/Y H:i:s')."]\t".$i['file']." - line ".$i['line'].
        self::get_dump($st).PHP_EOL;

      if ( php_sapi_name() === 'cli' ){
        global $argv;
        if ( isset($argv[2]) && ($argv[2] === 'log') ){
          echo self::get_dump($st).PHP_EOL;
        }
      }
      $s = ( file_exists($log_file) ) ? filesize($log_file) : 0;
      if ( $s > 1048576 ){
        file_put_contents($log_file.'.old', file_get_contents($log_file), FILE_APPEND);
        file_put_contents($log_file, $r);
      }
      else{
        file_put_contents($log_file, $r, FILE_APPEND);
      }
    }
  }

  /**
   * Puts the PHP errors into a JSON file
   *
   * @param string $errno The text to save.
   * @param string $errstr The file's name, default: "misc".
   * @param $errfile
   * @param $errline
   * @param array $context
   * @return bool
   */

  public static function log_error($errno, $errstr, $errfile, $errline, $context = []){
    if ( defined('BBN_DATA_PATH') ){
      if ( is_dir(BBN_DATA_PATH.'logs') ){
        $file = BBN_DATA_PATH.'logs/_php_error.json';
        $r = false;
        if ( is_file($file) ){
          $r = json_decode(file_get_contents($file), 1);
        }
        if ( !$r ){
          $r = [];
        }
        $t = date('Y-m-d H:i:s');
        $idx = self::find($r, [
          'type' => $errno,
          'error' => $errstr,
          'file' => $errfile,
          'line' => $errline
        ]);
        if ( $idx !== false ){
          $r[$idx]['count']++;
          $r[$idx]['last_date'] = $t;
        }
        else{
          array_push($r, [
            'first_date' => $t,
            'last_date' => $t,
            'count' => 1,
            'type' => $errno,
            'error' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            //'context' => $context
          ]);
        }
        file_put_contents($file, json_encode($r));
      }
      if ( $errno > 8 ){
        die($errstr);
      }
    }
    return false;
  }

  /**
   * Returns an object merging two objects.
   *
   * ```php
   * class A {
   *  public $a = 10;
   *  public $b = 20;
   * };
   *
   * class B {
   *  public $c = 30;
   *  public $d = 40;
   * };
   *
   * $obj1 = new A;
   * $obj2 = new B;
   *
   * \bbn\x::merge_objects($obj1, $obj2);
   * // object {'a': 10, 'b': 20, 'c': 30, 'd': 40}
   * ```
   *
   * @param object $o1 The first object to merge.
   * @param object $o2 The second object to merge.
   * @return object The merged object.
   */
  public static function merge_objects($o1, $o2){
    $args = func_get_args();
    /* @todo check if it's working with more than 2 object arguments */
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
   * Returns an array merging several arrays.
   *
   * ```php
   * \bbn\x::merge_arrays([1, 'Test'], [2, 'Example']);
   * // array [1, 'Test', 2, 'Example']
   * ```
   *
   * @param array $a1 The first array to merge.
   * @param array $a2 The second array to merge.
   * @return array The merged array.
   */
  public static function merge_arrays(array $a1, array $a2){
    $args = func_get_args();
    if ( count($args) > 2 ){
      for ( $i = count($args) - 1; $i > 1; $i-- ){
        $args[$i-1] = self::merge_arrays($args[$i-1], $args[$i]);
      }
      $a2 = $args[1];
    }
    if ( (self::is_assoc($a1) || empty($a1)) && (self::is_assoc($a2) || empty($a2)) ){
      $keys = array_unique(array_merge(array_keys($a1), array_keys($a2)));
      $r = [];
      foreach ( $keys as $k ){
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

  /**
   * Converts a JSON string or an array into an object.
   *
   * ```php
   * \bbn\x::to_object([[1, 'Test'], [2, 'Example']]);
   * // object {[1, 'Test'], [2, 'Example']}
   * ```
   *
   * @param array $ar The array to convert.
   * @return false | object
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

  /**
   * Converts a JSON string or an object into an array.
   *
   * ```php
   * $file = new stdClass();
   * $file->foo = "bar";
   * $file->bar = "foo";
   * echo \bbn\x::to_array($file);
   * /* array [
   *     'foo' => 'bar',
   *     'bar' => 'foo'
   * ]
   * ```
   *
   * @param object $obj The object to convert.
   * @return false | array
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

  public static function js_object($obj){
    $value_arr = [];
    $replace_keys = [];

    $transform = function($o, $idx = 0) use(&$transform, &$value_arr, &$replace_keys){
      foreach( $o as $key => &$value ){
        $idx++;
        if ( is_array($value) || is_object($value) ){
          $value = $transform($value, $idx);
        }
        else if (
          is_string($value) &&
          // Look for values starting with 'function('
          (strpos(trim($value), 'function(') === 0)
        ){
          // Store function string.
          $value_arr[] = $value;
          // Replace function string in $foo with a ‘unique’ special key.
          $value = "%bbn%$key%bbn%$idx%bbn%";
          // Later on, we’ll look for the value, and replace it.
          $replace_keys[] = '"'.$value.'"';
        }
      }
      return $o;
    };
    // Now encode the array to json format
    $json = json_encode($transform($obj));
    /* $json looks like:
    {
      “number”:1,
      “float”:1.5,
      “array”:[1,2],
      “string”:”bar”,
      “function”:”%bbn%function%bbn%5%bbn%”
    }
    */
    // Replace the special keys with the original string.
    return count($replace_keys) ? str_replace($replace_keys, $value_arr, $json) : $json;
  }

  /**
   * Indents a flat JSON string to make it more human-readable.
   *
   * ```php
   * echo \bbn\x::indent_json('{"firstName": "John", "lastName": "Smith", "age": 25}');
   * /*
   * {
   *   "firstName": "John",
   *   "lastName": "Smith",
   *   "isAlive": true,
   *   "age": 25
   * }
   * ```
   *
   * @param string $json The original JSON string to process.
   * @return string Indented version of the original JSON string.
   */
  public static function indent_json($json){

    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i=0; $i<=$strLen; $i++){

      // Grab the next character in the string.
      $char = substr($json, $i, 1);

      // Are we inside a quoted string?
      if ($char == '"' && $prevChar != '\\'){
        $outOfQuotes = !$outOfQuotes;

        // If this character is the end of an element,
        // output a new line and indent the next line.
      } else if(($char == '}' || $char == ']') && $outOfQuotes){
        $result .= $newLine;
        $pos --;
        for ($j=0; $j<$pos; $j++){
          $result .= $indentStr;
        }
      }

      // Add the character to the result string.
      $result .= $char;

      // If the last character was the beginning of an element,
      // output a new line and indent the next line.
      if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes){
        $result .= $newLine;
        if ($char == '{' || $char == '['){
          $pos ++;
        }

        for ($j = 0; $j < $pos; $j++){
          $result .= $indentStr;
        }
      }

      $prevChar = $char;
    }

    return $result;
  }

  /**
   * Returns an object or an array cleaned up from all empty values.
   * @todo Add a preserve_keys option?
   *
   * ```php
   * \bbn\x::remove_empty(['Allison', 'Mike', '', 'John', ' ']);
   * // array [0 => 'Allison', 1 => 'Mike', 3 => 'John', 4 => ' ']
   *
   * \bbn\x::remove_empty(['Allison', 'Mike', '', 'John', ' '], 1));
   * // array [0 => 'Allison', 1 => 'Mike', 3 => 'John']
   * ```
   *
   * @param array|object $arr An object or array to clean.
   * @param bool $remove_space If "true" the spaces are removed, default: "false".
   * @return array The clean result.
   */
  public static function remove_empty($arr, $remove_space = false){
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

  /**
   * Converts an indexed array into a numeric array where the original index is a property.
   * @todo the name is not fitted
   *
   * ```php
   * \bbn\x::to_groups([25 => 'Allison', 33 => 'Mike', 19 => 'John']);
   * // array [['value' => 25, 'text' => 'Allison'], ['value' => 33, 'text' => 'Francis'], ['value' => 19, 'text' => 'John']]
   *
   * \bbn\x::to_groups(['Allison', 'Mike', 'John'],'id', 'name');
   * // array [['id' => 25, 'name' => 'Allison'], ['id' => 33, 'name' => 'Francis'], ['id' => 19, 'name' => 'John']]
   * ```
   *
   * @param array $arr The original array.
   * @param string $keyname Alias for the index.
   * @param string $valname Alias for the value.
   * @return array Groups array.
   */
  public static function to_groups(array $arr, $keyname = 'value', $valname = 'text'){
    $r = [];
    foreach ( $arr as $k => $v ){
      $r[] = [$keyname => $k, $valname => $v];
    }
    return $r;
  }

  /**
   * Checks if the given array is associative.

   * ```php
   * \bbn\\x::is_assoc(['id' => 0, 'name' => 'Allison']);
   *
   * \bbn\\x::is_assoc(['Allison', 'John', 'Bert']);
   *
   * \bbn\\x::is_assoc([0 => "Allison", 1 => "John", 2 => "Bert"]);
   *
   * \bbn\\x::is_assoc([0 => "Allison", 1 => "John", 3 => "Bert"]);
   *
   * // boolean true
   * // boolean false
   * // boolean false
   * // boolean true
   * ```
   *
   * @param array $r The array to check.
   * @return bool
   */
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

  /**
   * Returns a dump of the given variable.
   *
   * @param mixed
   * @return string
   */
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
      else if ( $a === [] ){
        $r = '[]';
      }
      else if ( !$a ){
        $r = '0';
      }
      else if ( is_object($a) || is_array($a) ){
        $r = str::export($a);
      }
      $st .= $r.PHP_EOL;
    }
    return PHP_EOL.$st.PHP_EOL;
  }

  /**
   * Returns an HTML dump of the given variable.
   *
   * @param mixed
   * @return string
   */
  public static function get_hdump(){
    return '<p>'.nl2br(str_replace("  ", "&nbsp;&nbsp;", htmlentities(call_user_func_array('self::get_dump', func_get_args()))), false).'</p>';
  }

  /**
   * Dumps the given variable.
   *
   * @param mixed
   * @return void
   *
   */
  public static function dump(){
    echo call_user_func_array('self::get_dump', func_get_args());

  }

  /**
   * Dumps the given variable in HTML.
   *
   * @param mixed
   * @return void
   */
  public static function hdump(){
    echo call_user_func_array('self::get_hdump', func_get_args());
  }

  /**
   * Return HTML code for creating the &lt;option&gt; tag(s) based on an array.
   * If the array is indexed, the index will be used as value
   *
   * ```php
   * \bbn\x::build_options(['yes', 'no']);
   * // string "<option value="yes">yes</option>;<option value="no">no</option>"
   * \bbn\x::build_options(['yes', 'no'], 'no');
   * // string "<option value="yes">yes</option><option value="no" selected="selected">no</option>"
   * \bbn\x::build_options(['yes', 'no'], 'no', 'LabelForEmpty');
   * // string "<option value="">LabelForEmpty</option><option value="yes">yes</option><option value="no" selected="selected">no</option>"
   * \bbn\x::dump(\bbn\x::build_options([3 => "Allison", 4 => "Mike", 5 => "Andrew"], 5, 'Who?'));
   * // string "<option  value="">Who?</option><option  value="3">Allison</option><option  value="4">Mike</option><option  value="5"  selected="selected">Andrew</option>"
   * ```
   *
   * @param array $values The source array for the options
   * @param mixed $selected The selected value
   * @param boolean $empty_label A label for empty value
   * @return string The HTML code.
   */
  public static function build_options($values, $selected='', $empty_label=false){
    if ( is_array($values) )
    {
      $r = '';
      if ( $empty_label !== false ){
        $r .= '<option value="">'.$empty_label.'</option>';
      }
      $is_assoc = self::is_assoc($values);
      foreach ( $values as $k => $v )
      {
        if ( is_array($v) && count($v) == 2 )
        {
          $value = $v[0];
          $title = $v[1];
        }
        else if ( !isset($values[0]) && $is_assoc ){
          $value = $k;
          $title = $v;
        }
        else {
          $value = $title = $v;
        }
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

  /**
   * Converts a numeric array to an associative one, using the values alternatively as key or value.
   *
   * ```php
   * \bbn\x::to_keypair(['Test', 'TestFile', 'Example', 'ExampleFile']);
   * // string ['Test' => 'TestFile', 'Example' => 'ExampleFile']
   * ```
   *
   * @param array $arr must contain an even number of values
   * @param bool $protected if false no index protection will be performed
   * @return array|false
   */
  public static function to_keypair($arr, $protected = 1){
    $num = count($arr);
    $res = [];
    if ( ($num % 2) === 0 ){
      $i = 0;
      while ( isset($arr[$i]) ){
        if ( !is_string($arr[$i]) || ( !$protected && !preg_match('/[0-9A-z\-_]+/8', str::cast($arr[$i])) ) ){
          return false;
        }
        $res[$arr[$i]] = $arr[$i+1];
        $i += 2;
      }
    }
    return $res;
  }

  /**
   * Returns the maximum value of a given property from a 2 dimensions array.
   * @todo Add a custom callable as last parameter
   *
   * ```php
   * \bbn\x::max_with_key([
   *  ['age' => 1, 'name' => 'Michelle'],
   *  ['age' => 8, 'name' => 'John'],
   *  ['age' => 45, 'name' => 'Sarah'],
   *  ['age' => 45, 'name' => 'Camilla'],
   *  ['age' => 2, 'name' => 'Allison']
   * ], 'age');
   * // int  45
   * ```
   *
   * @param array $ar A multidimensional array
   * @param string $key The property where to check the value from
   * @return mixed
   */
  public static function max_with_key($ar, $key){
    if (!is_array($ar) || count($ar) == 0) return false;
    $max = current($ar)[$key];
    foreach ( $ar as $a ){
      if( $a[$key] > $max ){
        $max = $a[$key];
      }
    }
    return $max;
  }

  /**
   * Returns the minimum value of an index of a multidimensional array.
   *
   * ```php
   * \bbn\x::min_with_key([
   *  ['age' => 1, 'name' => 'Michelle'],
   *  ['age' => 8, 'name' => 'John'],
   *  ['age' => 45, 'name' => 'Sarah'],
   *  ['age' => 45, 'name' => 'Camilla'],
   *  ['age' => 2, 'name' => 'Allison']
   * ], 'age');
   * // int  1
   * ```
   *
   * @param array $array A multidimensional array.
   * @param string $key The index where to search.
   * @return mixed value
   */
  public static function min_with_key($array, $key){
    if (!is_array($array) || count($array) == 0) return false;
    $min = $array[0][$key];
    foreach($array as $a){
      if($a[$key] < $min){
        $min = $a[$key];
      }
    }
    return $min;
  }

  /**
   * Gets the backtrace and dumps it or logs it into a file
   *
   * ```php
   * \bbn\x::dump(\bbn\x::debug());
   * ```
   * @param string $file The file to debug
   * @return void
   */
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

  /**
   * Apply the given function at all level of a multidimensional array (if defined param $item).
   *
   * ```php
   * $ar = [
   *        ['age' => 45,
   *          'name' => 'John',
   *          'children' => [
   *            ['age' => 8, 'name' => 'Carol'],
   *            ['age' => 24, 'name' => 'Jack'],
   *          ]
   *        ],
   *        ['age' => 44, 'name' => 'Benjamin'],
   *        ['age' => 60, 'name' => 'Paul', 'children' =>
   *          [
   *            ['age' => 36, 'name' => 'Mike'],
   *            ['age' => 46, 'name' => 'Alan', 'children' =>
   *              ['age' => 8, 'name' => 'Allison'],
   *            ]
   *          ]
   *        ]
   *      ];
   * \bbn\x::hdump(\bbn\x::map(function($a){
   *  if ( $a['age']>20){
   *    $a['name'] = 'Mr. '.$a['name'];
   *  }
   *  return $a;
   * }, $ar,'children'));
   * /* array [
   *            [
   *              "age"  =>  45,
   *              "name"  =>  "Mr.  John",
   *              "children"  =>  [
   *                [
   *                  "age"  =>  8,
   *                  "name"  =>  "Carol",
   *                ],
   *                [
   *                  "age"  =>  24,
   *                  "name"  =>  "Mr.  Jack",
   *                ],
   *              ],
   *            ],
   *            [
   *              "age"  =>  44,
   *              "name"  =>  "Mr.  Benjamin",
   *            ],
   *            [
   *              "age"  =>  60,
   *              "name"  =>  "Mr.  Paul",
   *              "children"  =>  [
   *                [
   *                  "age"  =>  36,
   *                  "name"  =>  "Mr.  Mike",
   *                ],
   *                [
   *                  "age"  =>  46,
   *                  "name"  =>  "Mr.  Alan",
   *                  "children"  =>  [
   *                    "age"  =>  8,
   *                    "name"  =>  "Allison",
   *                  ],
   *                ],
   *            ],
   *          ]
   *
   * ```
   * @param callable $fn The function to be applied to the items of the array
   * @param array $ar
   * @param string|null $items If null the function will be applied just to the item of parent array
   * @return array
   */
  public static function map(callable $fn, array $ar, string $items = null){
    $res = [];
    foreach ( $ar as $i => $a ){
      $is_false = $a === false;
      $r = $fn($a);
      if ( $is_false ){
        array_push($res, $r);
      }
      else if ( $r !== false ){
        if ( is_array($r) && $items && isset($r[$items]) && is_array($r[$items]) ){
          $r[$items] = self::map($fn, $r[$items], $items);
        }
        array_push($res, $r);
      }
    }
    return $res;
  }

  /**
   * Returns the array's first index which satisfies the where condition.
   *
   * ```php
   * \bbn\x::hdump(\bbn\x::find([[
   *    'id' => 1,
   *    'name' => 'Andrew',
   *    'fname' => 'Williams'
   *    ], [
   *   'id' => 2,
   *    'name' => 'Albert',
   *    'fname' => 'Taylor'
   *    ], [
   *    'id' => 3,
   *    'name' => 'Mike',
   *    'fname' => 'Smith'
   *    ], [
   *    'id' => 4,
   *    'name' => 'John',
   *    'fname' => 'White'
   *    ]], ['id' => 4]));
   * // int 3
   * \bbn\x::hdump(\bbn\x::find([[
   *    'id' => 1,
   *    'name' => 'Andrew',
   *    'fname' => 'Williams'
   *    ], [
   *   'id' => 2,
   *    'name' => 'Albert',
   *    'fname' => 'Taylor'
   *    ], [
   *    'id' => 3,
   *    'name' => 'Mike',
   *    'fname' => 'Smith'
   *    ], [
   *    'id' => 4,
   *    'name' => 'John',
   *    'fname' => 'White'
   *    ]], ['name' => 'Albert', 'fname' => 'Taylor']));
   * // int 1
   * ```
   *
   * @param array $ar
   * @param array $where The where condition
   * @return bool|int
   */
  public static function find(array $ar, array $where){
    if ( !empty($where) ){
      foreach ( $ar as $i => $v ){
        $ok = 1;
        foreach ( $where as $k => $w ){
          if ( !isset($v[$k]) || ($v[$k] !== $w) ){
            $ok = false;
            break;
          }
        }
        if ( $ok ){
          return $i;
        }
      }
    }
    return false;
  }

  /**
   * Returns the first row of an array satisfying the where parameters ({@link find()).
   *
   * ```php
   * \bbn\x::dump(\bbn\x::get_row([[
   *    'id' => 1,
   *    'name' => 'Andrew',
   *    'fname' => 'Williams'
   *    ], [
   *   'id' => 2,
   *    'name' => 'Albert',
   *    'fname' => 'Taylor'
   *    ], [
   *    'id' => 3,
   *    'name' => 'Mike',
   *    'fname' => 'Smith'
   *    ], [
   *    'id' => 4,
   *    'name' => 'John',
   *    'fname' => 'White'
   *    ]], ['name' => 'Albert']));
   * // array [ "id" => 2, "name" => "Albert", "fname" => "Taylor", ]
   * ```
   *
   * @param array $r
   * @param array $where The where condition
   * @return bool|mixed
   *
   */
  public static function get_row(array $r, array $where){
    if ( ($res = self::find($r, $where)) !== false ){
      return $r[$res];
    }
    return false;
  }

  /**
   * Returns the first value of a specific field of an array.
   *
   * ```php
   * \bbn\x::dump(\bbn\x::get_row([[
   *    'id' => 1,
   *    'name' => 'Andrew',
   *    'fname' => 'Williams'
   *    ], [
   *   'id' => 2,
   *    'name' => 'Albert',
   *    'fname' => 'Taylor'
   *    ], [
   *    'id' => 3,
   *    'name' => 'Mike',
   *    'fname' => 'Smith'
   *    ], [
   *    'id' => 4,
   *    'name' => 'John',
   *    'fname' => 'White'
   *    ]], ['name' => 'Albert'],'id'));
   * // int 2
   * ```
   *
   * @param array $r
   * @param array $where The where condition
   * @param string $field The field where to look for
   * @return bool|mixed
   */
  public static function get_field(array $r, array $where, string $field){
    if ( ($res = self::get_row($r, $where)) && isset($res[$field]) ){
      return $res[$field];
    }
    return false;
  }

  /**
   * Returns a reference to a subarray targeted by an array $keys
   *
   * ```php
   * $ar = [
   *  'session' => [
   *    'user' => [
   *      'profile' => [
   *        'admin' => [
   *          'email' => 'test@test.com'
   *        ]
   *      ]
   *    ]
   *  ]
   * ];
   * \bbn\x::hdump(\bbn\x::pick($ar,['session', 'user', 'profile', 'admin', 'email']));
   * // string test@test.com
   *
   * \bbn\x::hdump(\bbn\x::pick($ar,['session', 'user', 'profile', 'admin']));
   * // ["email"  =>  "test@test.com",]
   * ```
   * @param array $ar
   * @param array $keys
   * @return array|mixed
   */
  public static function pick(array $ar, array $keys){
    while ( count($keys) ){
      $r = array_shift($keys);
      if ( isset($ar[$r]) ){
        $ar = $ar[$r];
        if ( !count($keys) ){
          return $ar;
        }
      }
    }
  }

  /**
   * Sort the item of an array.
   *
   * ```php
   * $var = [3, 2, 5, 6, 1];
   * \bbn\x::sort($var);
   * \bbn\x::hdump($var);
   * // array [1,2,3,5,6]
   * ```
   *
   * @param $ar The reference to the array t7o sort
   * @return void
   */

  public static function sort(&$ar){
    usort($ar, function($a, $b){
      if ( !str::is_number($a, $b) ){
        $a = str_replace('.', '0', str_replace('_', '1', str::change_case($a, 'lower')));
        $b = str_replace('.', '0', str_replace('_', '1', str::change_case($b, 'lower')));
        return strcmp($a, $b);
      }
      if ( $a > $b ){
        return 1;
      }
      else if ($a == $b){
        return 0;
      }
      return -1;
    });
  }

  /**
   * Sort the item of an indexed array basing on a given $key.
   *
   * ```php
   *  $v = [['age'=>10, 'name'=>'thomas'], ['age'=>22, 'name'=>'John'], ['age'=>37, 'name'=>'Michael']];
   *  \bbn\x::sort_by($v,'name','desc');
   *  \bbn\x::hdump($v);
   *  \bbn\x::sort_by($v,'name','asc');
   *  \bbn\x::hdump($v);
   *  \bbn\x::sort_by($v,'age','asc');
   *  \bbn\x::hdump($v);
   *  \bbn\x::sort_by($v,'age','desc');
   *  \bbn\x::hdump($v);
   * ```
   *
   * @param array $ar The array of data to sort
   * @param string|int $key The key to sort by
   * @param string $dir The direction of the sort ('asc'|'desc')
   * @return void
   */
  public static function sort_by(&$ar, $key, $dir = ''){
    usort($ar, function($a, $b) use($key, $dir){
      if ( !is_array($key) ){
        $key = [$key];
      }
      $v1 = self::pick($a, $key);
      $v2 = self::pick($b, $key);
      if ( !isset($v1, $v2) ){
        return 0;
      }
      $a1 = strtolower($dir) === 'desc' ? $v2 : $v1;
      $a2 = strtolower($dir) === 'desc' ? $v1 : $v2;
      if ( !str::is_number($v1, $v2) ){
        $a1 = str_replace('.', '0', str_replace('_', '1', str::change_case($a1, 'lower')));
        $a2 = str_replace('.', '0', str_replace('_', '1', str::change_case($a2, 'lower')));
        return strcmp($a1, $a2);
      }
      if ( $a1 > $a2 ){
        return 1;
      }
      else if ($a1 == $a2){
        return 0;
      }
      return -1;
    });
  }


  /**
   * Checks if the operating system from which PHP is executed is Windows or not
   * ```php
   * \bbn\x::dump(\bbn\x::is_windows());
   * // boolean false
   * ```
   *
   * @return bool
   */
  public static function is_windows()
  {
    return strtoupper(substr(PHP_OS, 0, 3)) == 'WIN';
  }

  /**
   * Makes a Curl call towards a URL and returns the result as a string
   *
   * ```php
   *  $url = 'https://www.omdbapi.com/';
   *  $param = ['t'=>'la vita è bella'];
   *  \bbn\x::hdump(\bbn\x::curl($url,$param, ['POST' => false]));
   *
   * // object {"Title":"La  vita  è  bella","Year":"1943","Rated":"N/A","Released":"26  May  1943","Runtime":"76  min","Genre":"Comedy","Director":"Carlo  Ludovico  Bragaglia","Writer":"Carlo  Ludovico  Bragaglia  (story  and  screenplay)","Actors":"Alberto  Rabagliati,  María  Mercader,  Anna  Magnani,  Carlo  Campanini","Plot":"N/A","Language":"Italian","Country":"Italy","Awards":"N/A","Poster":"http://ia.media-imdb.com/images/M/MV5BYmYyNzA2YWQtNDgyZC00OWVkLWIwMTEtNTdhNDQwZjcwYTMwXkEyXkFqcGdeQXVyNTczNDAyMDc@._V1_SX300.jpg","Metascore":"N/A","imdbRating":"7.9","imdbVotes":"50","imdbID":"tt0036502","Type":"movie","Response":"True"}
   * ```
   *
   * @param string $url
   * @param array $param
   * @param array $options
   * @return mixed
   */
  public static function curl(string $url, array $param = null, array $options = ['post' => 1]){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (is_object($param) ){
      $param = self::to_array($param);
    }
    if ( defined('BBN_IS_SSL') && defined('BBN_IS_DEV') && BBN_IS_SSL && BBN_IS_DEV ){
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      //curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
    }
    $options = array_change_key_case($options, CASE_UPPER);
    foreach ( $options as $opt => $val ){
      if ( defined('CURLOPT_'.strtoupper($opt)) ){
        curl_setopt($ch, constant('CURLOPT_'.strtoupper($opt)), $val);
      }
    }
    if ( $param ){
      if ( !empty($options['POST']) ){
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
      }
      else{
        curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($param));
      }
    }
    else{
      curl_setopt($ch, CURLOPT_URL, $url);
    }
    $r = curl_exec($ch);
    if ( !$r ){
      self::log(curl_error($ch), 'curl');
    }
    return $r;
  }

  /**
   * Returns the given array or object as a tree structure ready for a JS tree
   *
   * ```php
   * \bbn\x::hdump(\bbn\x::get_tree([['id' => 1,'name' => 'Andrew','fname' => 'Williams','children' =>[['name' => 'Emma','age' => 6],['name' => 'Giorgio','age' => 9]]], ['id' => 2,'name' => 'Albert','fname' => 'Taylor','children' =>[['name' => 'Esther','age' => 6],['name' => 'Paul','age' => 9]]], ['id' => 3,'name' => 'Mike','fname' => 'Smith','children' =>[['name' => 'Sara','age' => 6],['name' => 'Fred','age' => 9]]]]));
   * /* array [
   *    [ "text" => 0, "items" => [ [ "text" => "id: 1", ], [ "text" => "name: Andrew", ], [ "text" => "fname: Williams", ], [ "text" => "children", "items" => [ [ "text" => 0, "items" => [ [ "text" => "name: Emma", ], [ "text" => "age: 6", ], ], ], [ "text" => 1, "items" => [ [ "text" => "name: Giorgio", ], [ "text" => "age: 9", ], ], ], ], ], ], ], [ "text" => 1, "items" => [ [ "text" => "id: 2", ], [ "text" => "name: Albert", ], [ "text" => "fname: Taylor", ], [ "text" => "children", "items" => [ [ "text" => 0, "items" => [ [ "text" => "name: Esther", ], [ "text" => "age: 6", ], ], ], [ "text" => 1, "items" => [ [ "text" => "name: Paul", ], [ "text" => "age: 9", ], ], ], ], ], ], ], [ "text" => 2, "items" => [ [ "text" => "id: 3", ], [ "text" => "name: Mike", ], [ "text" => "fname: Smith", ], [ "text" => "children", "items" => [ [ "text" => 0, "items" => [ [ "text" => "name: Sara", ], [ "text" => "age: 6", ], ], ], [ "text" => 1, "items" => [ [ "text" => "name: Fred", ], [ "text" => "age: 9", ], ], ], ], ], ], ], ]
   * ```
   *
   * @param array $ar
   * @return array
   */
  public static function get_tree($ar){
    $res = [];
    foreach ( $ar as $k => $a ){
      $r = ['text' => $k];
      if ( is_object($a) ){
        $a = self::to_array($a);
      }
      if ( is_array($a) ){
        $r['items'] = self::get_tree($a);
      }
      else if ( is_null($a) ){
        $r['text'] .= ': null';
      }
      else if ( $a === false ){
        $r['text'] .= ': false';
      }
      else if ( $a === true ){
        $r['text'] .= ': true';
      }
      else {
        $r['text'] .= ': '.(string)$a;
      }
      array_push($res, $r);
    }
    return $res;
  }

  /**
   * Returns a view of an array or object as a JS tree
   *
   * ```php
   * \bbn\x::dump(\bbn\x::make_tree([['id' => 1,'name' => 'Andrew','fname' => 'Williams','children' =>[['name' => 'Emma','age' => 6],['name' => 'Giorgio','age' => 9]]], ['id' => 2,'name' => 'Albert','fname' => 'Taylor','children' =>[['name' => 'Esther','age' => 6],['name' => 'Paul','age' => 9]]], ['id' => 3,'name' => 'Mike','fname' => 'Smith','children' =>[['name' => 'Sara','age' => 6],['name' => 'Fred','age' => 9]]]]));
   * /* string
   *    0
   *      id: 1
   *      name: Andrew
   *      fname: Williams
   *      children:
   *        0
   *          name: Emma
   *          age: 6
   *        1
   *          name: Giorgio
   *          age: 9
   *    1
   *      id: 2
   *      name: Albert
   *      fname: Taylor
   *      children
   *        0
   *          name: Esther
   *          age: 6
   *        1
   *          name: Paul
   *          age: 9
   *    2
   *      id: 3
   *      name: Mike
   *      fname: Smith
   *      children
   *      0
   *        name: Sara
   *        age: 6
   *      1
   *        name: Fred
   *        age: 9
   * ```
   *
   * @param array $ar
   * @return string
   */
  public static function make_tree(array $ar){
    $id = str::genpwd();
    return '<div id="'.$id.'"></div><script>$("#'.$id.'").kendoTreeView({dataSource: '.
      json_encode(self::get_tree($ar)).'});</script>';
  }

  /**
   * Formats a CSV line(s) and returns an array.
   * Adapted from http://us3.php.net/manual/en/function.fputcsv.php#87120
   *
   * ```php
   *  \bbn\x::dump(\bbn\x::from_csv(
   *      '"141";"10/11/2002";"350.00";"1311742251"
   *      "142";"12/12/2002";"349.00";"1311742258"'
   *  ));
   * // [ [ "141", "10/11/2002", "350.00", "1311742251", ], [ "142", "12/12/2002", "349.00", "1311742258", ], ]
   * ```
   *
   * @param $st The Csv string to format
   * @param string $delimiter
   * @param string $enclosure
   * @param string $separator
   * @return array
   */
  public static function from_csv($st, $delimiter = ';', $enclosure = '"', $separator = PHP_EOL){
    if ( is_string($st) ){
      $r = [];
      $lines = explode($separator, $st);
      foreach ( $lines as $line ){
        array_push($r, str_getcsv($line, $delimiter, $enclosure));
      }
      return $r;
    }
    return [];
  }

  /**
   * Formats an array as a CSV string.
   * Adapted from http://us3.php.net/manual/en/function.fputcsv.php#87120
   *
   * ```php
   * \bbn\x::dump(\bbn\x::to_csv([["John", "Mike", "David", "Clara"],["White", "Red", "Green", "Blue"]]));
   * /* string  John;Mike;David;Clara
   *            White;Red;Green;Blue
   * ```
   *
   * @param array $data The array to format
   * @param string $delimiter
   * @param string $enclosure
   * @param string $separator
   * @param bool $encloseAll
   * @param bool $nullToMysqlNull
   * @return string
   */

  public static function to_csv(array $data, $delimiter = ';', $enclosure = '"', $separator = PHP_EOL, $encloseAll = false, $nullToMysqlNull = false ){
    $delimiter_esc = preg_quote($delimiter, '/');
    $enclosure_esc = preg_quote($enclosure, '/');

    $lines = [];
    foreach ( $data as $d ){
      $output = [];
      foreach ( $d as $field ){
        if ($field === null && $nullToMysqlNull){
          $output[] = 'NULL';
          continue;
        }

        // Enclose fields containing $delimiter, $enclosure or whitespace
        if ( $encloseAll || preg_match( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field ) ){
          $output[] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
        }
        else {
          $output[] = $field;
        }
      }
      array_push($lines, implode( $delimiter, $output ));
    }
    return implode( $separator, $lines );
  }

  /**
   * @param string $file1
   * @param string $file2
   * @param bool $strict
   * @return bool
   */
  public static function is_same(string $file1, string $file2, $strict = false){
    if ( !is_file($file1) || !is_file($file2) ){
      throw Exception("Boo! One of the files given to the x::is_same function doesn't exist");
    }
    else{
      $same = filesize($file1) === filesize($file2);
      if ( !$strict || !$same ){
        return $same;
      }
      return filemtime($file1) === filemtime($file2);
    }
  }

  public static function check_properties($obj){
    $props = func_get_args();
    array_shift($props);
    foreach ( $props as $p ){
      if ( is_array($p) ){
        if ( (count($p) !== 2) ){
          /** @todo proper error */
          die("Boo with check properties");
        }
        if ( function_exists('is_'.$p[1]) ){

        }
      }
    }
  }
}
