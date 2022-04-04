<?php
namespace bbn;

/**
 * A container of tools.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */

if (!defined('BBN_X_MAX_LOG_FILE')) {
  define('BBN_X_MAX_LOG_FILE', 1048576);
}

class X
{

  private static $_counters = [];

  private static $_last_curl;

  private static $_cli;

  private static $_textdomain;

  /**
   * @param string $name
   */
  private static function _init_count(string $name = 'num'): void
  {
    if (!isset(self::$_counters[$name])) {
      self::$_counters[$name] = 0;
    }
  }


  /**
   * @param string $name
   * @param int    $i
   */
  public static function increment(string $name = 'num', int $i = 1)
  {
    self::_init_count($name);
    self::$_counters[$name] += $i;
  }


  /**
   * @param string $name
   * @param int    $i
   */
  public static function decrement(string $name = 'num', int $i = 1)
  {
    self::_init_count($name);
    self::$_counters[$name] -= $i;
  }


  /**
   * @param string $name
   * @return mixed
   */
  public static function count(string $name = 'num', bool $delete = false): int
  {
    self::_init_count($name);
    $tmp = self::$_counters[$name];
    if ($delete) {
      unset(self::$_counters[$name]);
    }

    return $tmp;
  }


  /**
   * @param bool $delete
   * @return array
   */
  public static function countAll(bool $delete = false): array
  {
    $tmp = self::$_counters;
    if ($delete) {
      self::$_counters = [];
    }

    return $tmp;
  }


  /**
   * @return string
   */
  public static function tDom(): string
  {
    if (!self::$_textdomain) {
      $td = 'bbn';
      $f = self::dirname(__DIR__).'/version.txt';
      if (is_file($f)) {
        $td .= file_get_contents($f);
      }

      self::$_textdomain = $td;
    }

    return self::$_textdomain;
  }


  /**
   * @param string $string
   * @return string
   */
  public static function _(string $string): string
  {
    $res = dgettext(X::tDom(), $string);
    $args = func_get_args();
    if (count($args) > 1) {
      array_shift($args);
      return sprintf($res, ...$args);
    }

    return $res;
  }

  /**
   * Returns a microtime with 4 digit after the dot
   *
   * @return void
   */
  public static function microtime(): float
  {
    return round(\microtime(true), 4);
  }


  /**
   * Returns true if each string argument is defined as a constant
   *
   * @param string $name
   * @return bool
   */
  public static function isDefined(string $name): bool
  {
    foreach (func_get_args() as $a) {
      if (!is_string($a) || !defined($a)) {
        return false;
      }
    }

    return true;
  }


  /**
   * Saves logs to a file.
   *
   * ```php
   * X::log('My text', 'FileName');
   * ```
   *
   * @param mixed  $st   Item to log.
   * @param string $file Filename, default: "misc".
   * @return void
   */
  public static function log($st, string $file = 'misc'): void
  {
    if (\defined('BBN_DATA_PATH') && is_dir(BBN_DATA_PATH.'logs')) {
      $log_file  = BBN_DATA_PATH.'logs/'.$file.'.log';
      $backtrace = array_filter(
        debug_backtrace(), function ($a) {
          return $a['function'] === 'log';
        }
      );
      $i         = end($backtrace);
      $r         = "[".date('d/m/Y H:i:s')."]\t".$i['file']." - line ".$i['line'].
        self::getDump($st).PHP_EOL;

      if (php_sapi_name() === 'cli') {
        global $argv;
        if (isset($argv[2]) && ($argv[2] === 'log')) {
          echo self::getDump($st).PHP_EOL;
        }
      }

      if (file_exists($log_file) && filesize($log_file) > BBN_X_MAX_LOG_FILE) {
        file_put_contents($log_file.'.old', file_get_contents($log_file), FILE_APPEND);
        file_put_contents($log_file, $r);
      }
      else{
        file_put_contents($log_file, $r, FILE_APPEND);
      }
    }
  }


  /**
   * Puts the PHP errors into a JSON file.
   *
   * @param string  $errno  The text to save.
   * @param string  $errstr The file's name, default: "misc".
   * @param $errfile
   * @param $errline
   * @return void
   */
  public static function logError($errno, $errstr, $errfile, $errline): void
  {
    if (\defined('BBN_DATA_PATH') && is_dir(BBN_DATA_PATH.'logs')) {
      $file      = BBN_DATA_PATH.'logs/_php_error.json';
      $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
      foreach ($backtrace as &$b) {
        if (!empty($b['file'])) {
          $b['file'] = str_replace(BBN_APP_PATH, '', $b['file']);
        }
      }

      $r = false;
      if (is_file($file)) {
        $r = json_decode(file_get_contents($file), 1);
      }

      if (!$r) {
        $r = [];
      }

      $t = date('Y-m-d H:i:s');
      if (class_exists('\\bbn\\Mvc')) {
        $mvc = Mvc::getInstance();
      }

      $errfile = str_replace(BBN_APP_PATH, '', $errfile);
      $idx     = self::find(
        $r, [
        'type' => $errno,
        'error' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'request' => ''
        ]
      );
      if ($idx !== null) {
        $r[$idx]['count']++;
        $r[$idx]['last_date'] = $t;
        $r[$idx]['backtrace'] = $backtrace;
      }
      else{
        $r[] = [
          'first_date' => $t,
          'last_date' => $t,
          'count' => 1,
          'type' => $errno,
          'error' => $errstr,
          'file' => $errfile,
          'line' => $errline,
          'backtrace' => $backtrace,
          'request' => ''
          //'context' => $context
        ];
      }

      self::sortBy($r, 'last_date', 'DESC');
      file_put_contents($file, Json_encode($r, JSON_PRETTY_PRINT));
    }
  }


  /**
   * Check if an array or an object has the given property
   *
   *```php
   *
   * $arr = [
   *  'a' => 1,
   *  'b' => '',
   *  'c' => 0
   *  ];
   *
   * X::hasProp($arr, 'a');
   * // (bool) true
   *
   * X::hasProp($arr, 'b');
   * // (bool) true
   *
   * X::hasProp($arr, 'b', true);
   * // (bool) false
   *
   * X::hasProp($arr, 'c');
   * // (bool) true
   *
   * X::hasProp($arr, 'c', true);
   * // (bool) false
   *
   * X::hasProp($arr, 'd');
   * // (bool) false
   *
   * X::hasProp('string', 'd');
   * // null
   *
   *```
   *
   * @param array|object $obj
   * @param string       $prop
   * @return boolean|null
   */
  public static function hasProp($obj, string $prop, bool $check_empty = false): ?bool
  {
    if (is_array($obj)) {
      return \array_key_exists($prop, $obj) && (!$check_empty || !empty($obj[$prop]));
    }
    elseif (is_object($obj)) {
      return \property_exists($obj, $prop) && (!$check_empty || !empty($obj->$prop));
    }

    return null;
  }


  /**
   * Check if an array or an object has the given properties
   *
   * ```php
   * $arr = [
   *    'a' => 1,
   *    'b' => '',
   *    'c' => 0
   *  ];
   *
   * X::hasProps($arr, ['a', 'b', 'c']);
   * // (bool) true
   *
   * X::hasProps($arr, ['a', 'b', 'c'], true);
   * // (bool) false
   *
   * * X::hasProps('string', ['a']);
   * // null
   *
   * ```
   *
   * @param array|object $obj
   * @param array        $props
   * @return boolean|null
   */
  public static function hasProps($obj, array $props, bool $check_empty = false): ?bool
  {
    foreach ($props as $p) {
      $test = self::hasProp($obj, $p, $check_empty);
      if ($test === null) {
        return null;
      }
      elseif (!$test) {
        return false;
      }
    }

    return true;
  }


  /**
   * Check if an array or an object has the given property.
   *
   * ```php
   * $arr = [
   *    'a' => ['d' => [], 'e'],
   *    'b' => 'g',
   *    'c' => 0
   *  ];
   *
   * X::hasDeepProp($arr, ['a']);
   * // (bool) true
   *
   * X::hasDeepProp($arr, ['a'], true);
   * // (bool) true
   *
   * X::hasDeepProp($arr, ['a', 'd']);
   * // (bool) true
   *
   * X::hasDeepProp($arr, ['a', 'd'], true);
   * // (bool) false
   *
   * X::hasDeepProp($arr, ['a', 'e']);
   * // (bool) false
   *
   * X::hasDeepProp($arr, ['b']);
   * // (bool) true
   *
   * X::hasDeepProp($arr, ['b'], true);
   * // (bool) true
   *
   * X::hasDeepProp($arr, ['b', 'g']);
   * // (bool) false
   *
   * X::hasDeepProp($arr, ['c']);
   * // (bool) true
   *
   * X::hasDeepProp($arr, ['c'], true);
   * // (bool) false
   *
   * ```
   *
   * @param array|object $obj
   * @param array $prop_path
   * @param bool $check_empty
   * @return boolean|null
   */
  public static function hasDeepProp(
      $obj,
      array $prop_path,
      bool $check_empty = false
  ): ?bool
  {
    $o =& $obj;
    foreach ($prop_path as $p) {
      if (is_array($o)) {
        if (!\array_key_exists($p, $o)) {
          return false;
        }

        if ($check_empty && !$o[$p]) {
          return false;
        }

        $o =& $o[$p];
      }
      elseif (\is_object($o)) {
        if (!\property_exists($o, $p)) {
          return false;
        }

        if ($check_empty && !$o->$p) {
          return false;
        }

        $o =& $o->$p;
      }
      else {
        return false;
      }
    }

    return true;
  }


  /**
   *
   * ```php
   *
   * X::makeStoragePath('foo/bar', 'd/m/Y');
   * // (string) "/foo/bar/27/06/2021/1/"
   *
   *  X::makeStoragePath('foo/bar');
   * // (string) "/foo/bar/2021/06/27/1/"
   *
   * X::makeStoragePath('foo/bar', 'Y/m/d', 1); // path contains a "1" dir which contains 2 dirs or files
   * // (string) "/foo/bar/2021/06/27/2/"
   *
   * ```
   * @param string $path
   * @param string $format
   * @param int $max
   * @param File\System|null $fs
   * @return string|null
   */
  public static function makeStoragePath(
      string $path,
      $format = 'Y/m/d',
      $max = 100,
      File\System $fs = null
  ): ?string
  {
    if (empty($format)) {
      $format = 'Y/m/d';
    }

    if (!$max) {
      $max = 100;
    }

    if (!$fs) {
      $fs = new File\System();
    }

    // One dir per $format
    $spath = date($format);
    if ($spath) {
      $path = $fs->createPath($path.(substr($path, -1) === '/' ? '' : '/').$spath);
      if ($fs->isDir($path)) {
        $num = count($fs->getDirs($path));
        if ($num) {
          // Dir or files
          $num_files = count($fs->getFiles($path.'/'.$num, true));
          if ($num_files >= $max) {
            $num++;
          }
        }
        else {
          $num = 1;
        }

        if ($fs->createPath($path.'/'.$num)) {
          return $path.'/'.$num.'/';
        }
      }
    }

    return null;
  }


  /**
   * Deletes the for form the given path and date format if it's empty.
   *
   * @param string $path
   * @param string $format
   * @param File\System|null $fs
   * @return int|null
   */
  public static function cleanStoragePath(
      string $path,
      $format = 'Y/m/d',
      File\System $fs = null
  ): ?int
  {
    if (empty($format)) {
      $format = 'Y/m/d';
    }

    if (!$fs) {
      $fs = new File\System();
    }

    if (!$fs->isDir($path)) {
      return null;
    }

    $limit = count(self::split($format, '/')) + 1;
    $res   = 0;
    while ($limit > 0) {
      if (!$fs->getNumFiles($path) && $fs->delete($path)) {
        $limit--;
        $res++;
        $path = self::dirname($path);
      }
      else{
        break;
      }
    }

    return $res;
  }


  /**
   * Returns to a merged object from two or more objects.
   * Property values from later objects overwrite the previous objects.
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
   * * class C {
   *  public $c = 35;
   *  public $e = 50;
   * };
   *
   * $obj1 = new A;
   * $obj2 = new B;
   * $obj3 = new C;
   *
   * X::mergeObjects($obj1, $obj2, $obj3);
   * // object {'a': 10, 'b': 20, 'c': 35, 'd': 40, 'e': 50}
   * ```
   *
   * @param object $o1 The first object to merge.
   * @param object $o2 The second object to merge.
   * @return object The merged object.
   * @throws \Exception
   */
  public static function mergeObjects(object $o1, object $o2): \stdClass
  {
    $args = \func_get_args();

    if (\count($args) > 2) {
      for ($i = \count($args) - 1; $i > 1; $i--) {
        if (!is_object($args[$i])) {
          throw new \Exception('The provided argument must be an object, ' . gettype($args[$i]) . ' given.');
        }
        $args[$i - 1] = self::mergeObjects($args[$i - 1], $args[$i]);
      }

      $o2 = $args[1];
    }

    $a1  = self::toArray($o1);
    $a2  = self::toArray($o2);
    $res = self::mergeArrays($a1, $a2);
    return self::toObject($res);
  }


  /**
   * Flattens a multi-dimensional array for the given children index name.
   *
   * ```php
   *
   * $arr = [
   *    [
   *      'name'  => 'John Doe',
   *      'age'   => '35',
   *      'children' => [
   *          ['name' => 'Carol', 'age' => '4'],
   *          ['name' => 'Jack', 'age' => '6'],
   *       ]
   *    ],
   *    [
   *      'name'  => 'Paul',
   *      'age'   => '33',
   *      'children' => [
   *          ['name' => 'Alan', 'age' => '8'],
   *          ['name' => 'Allison 'age' => '2'],
   *       ]
   *    ],
   *  ];
   *
   * X::flatten($arr, 'children');
   * /* (array)
   * [
   *   ['name' => 'John Doe', 'age' => '35'],
   *   ['name' => 'Paul', 'age' => '33'],
   *   ['name' => 'Carol', 'age' => '4'],
   *   ['name' => 'Jack', 'age' => '6'],
   *   ['name' => 'Alan', 'age' => '8'],
   *   ['name' => 'Allison', 'age' => '2']
   *  ]
   *
   * ```
   *
   * @param array $arr
   * @param string $children
   * @return array
   */
  public static function flatten(array $arr, string $children)
  {
    $toAdd = [];
    $res = self::rmap(
      function ($a) use (&$toAdd, $children) {
        if (isset($a[$children]) && is_array($a[$children])) {
          foreach ($a[$children] as &$c) {
            $toAdd[] = $c;
          }

          unset($c);
          unset($a[$children]);
        }

        return $a;
      },
      $arr,
      $children
    );
    if (count($toAdd)) {
      array_push($res, ...$toAdd);
    }

    return $res;
  }


  /**
   * Merges two or more arrays into one.
   * Values from later array overwrite the previous array.
   *
   * ```php
   * X::mergeArrays([1, 'Test'], [2, 'Example']);
   * // array [1, 'Test', 2, 'Example']
   *
   * $arr1 = ['a' => 1, 'b' => 2];
   * $arr2 = ['b' => 3, 'c' => 4, 'd' => 5];
   * $arr3 = ['e' => 6, 'b' => 33];
   *
   * X::mergeArrays($arr1, $arr2, $arr3)
   * // (array) ['a' => 1, 'b' => 33, 'c' => 4, 'd' => 5, 'e' => 6]
   *
   * ```
   *
   * @param array $a1 The first array to merge.
   * @param array $a2 The second array to merge.
   * @return array The merged array.
   * @throws \Exception
   */
  public static function mergeArrays(array $a1, array $a2): array
  {
    $args = \func_get_args();
    if (\count($args) > 2) {
      for ($i = \count($args) - 1; $i > 1; $i--) {
        if (!is_array($args[$i])) {
          throw new \Exception('The provided argument must be an array, ' . gettype($args[$i]) . ' given.' );
        }
        $args[$i - 1] = self::mergeArrays($args[$i - 1], $args[$i]);
      }

      $a2 = $args[1];
    }

    if ((self::isAssoc($a1) || empty($a1)) && (self::isAssoc($a2) || empty($a2))) {
      $keys = array_unique(array_merge(array_keys($a1), array_keys($a2)));
      $r    = [];
      foreach ($keys as $k) {
        if (!array_key_exists($k, $a1) && !array_key_exists($k, $a2)) {
          continue;
        }
        elseif (!array_key_exists($k, $a2)) {
          $r[$k] = $a1[$k];
        }
        elseif (!array_key_exists($k, $a1) || !\is_array($a2[$k]) || !\is_array($a1[$k]) || is_numeric(key($a2[$k]))) {
          $r[$k] = $a2[$k];
        }
        else{
          $r[$k] = self::mergeArrays($a1[$k], $a2[$k]);
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
   * X::toObject([[1, 'Test'], [2, 'Example']]);
   * // (object) {[1, 'Test'], [2, 'Example']}
   * ```
   *
   * @param mixed $ar The array or JSON to convert.
   * @return \stdClass|null
   */
  public static function toObject($ar): ?\stdClass
  {
    if (\is_string($ar)) {
      $ar = json_decode($ar);
    }
    elseif (\is_array($ar)) {
      $ar = json_decode(json_encode($ar));
    }

    return (object)$ar;
  }


  /**
   * Converts a JSON string or an object into an array.
   *
   * ```php
   * $file = new stdClass();
   * $file->foo = "bar";
   * $file->bar = "foo";
   * X::toArray($file);
   * /* array [
   *     'foo' => 'bar',
   *     'bar' => 'foo'
   * ]
   * ```
   *
   * @param mixed $obj The object or JSON to convert.
   * @return false|null|array
   */
  public static function toArray($obj): ?array
  {
    $obj = \is_string($obj) ? $obj : json_encode($obj);
    return json_decode($obj, true);
  }


  /**
   *
   * Converts the provided iterable to a json string.
   *
   * ```php
   * $arr = [
   *    'a' => 1,
   *    'b' => ['c' => 2,'d' => 3],
   *    'c' => 'let data = "{"foo":"bar"}"'
   * ];
   *
   * X::jsObject($arr);
   * /* (string)
   * {
   *   "a": 1,
   *   "b": {
   *      "c": 2,
   *      "d": 3
   *      },
   *   "c": "let data = \"{\"foo\":\"bar\"}\""
   * }
   * ```
   *
   * @param iterable $obj
   * @return string
   */
  public static function jsObject(iterable $obj): string
  {
    $value_arr    = [];
    $replace_keys = [];

    //$obj = X::convertUids($obj);
    $transform = function ($o, $idx = 0) use (&$transform, &$value_arr, &$replace_keys) {
      foreach($o as $key => &$value) {
        $idx++;
        if (\is_array($value) || \is_object($value)) {
          $value = $transform($value, $idx);
        }
        elseif (\is_string($value)
            // Look for values starting with 'function('
            && (strpos(trim($value), 'function(') === 0)
        ) {
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
    $json = json_encode($transform($obj), JSON_PRETTY_PRINT);
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
    return \count($replace_keys) ? str_replace($replace_keys, $value_arr, $json) : $json;
  }


  /**
   * Indents a flat JSON string to make it human-readable.
   *
   * ```php
   * echo X::indentJson('{"firstName": "John", "lastName": "Smith", "age": 25}');
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
  public static function indentJson(string $json): string
  {
    $result      = '';
    $pos         = 0;
    $strLen      = \strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i = 0; $i <= $strLen; $i++) {
      // Grab the next character in the string.
      $char = substr($json, $i, 1);

      // Are we inside a quoted string?
      if ($char == '"' && $prevChar != '\\') {
        $outOfQuotes = !$outOfQuotes;

        // If this character is the end of an element,
        // output a new line and indent the next line.
      } elseif(($char == '}' || $char == ']') && $outOfQuotes) {
        $result .= $newLine;
        $pos --;
        for ($j = 0; $j < $pos; $j++) {
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
   * Returns an object or an array cleaned of all empty values.
   * @todo Add a preserve_keys option?
   *
   * ```php
   * X::removeEmpty(['Allison', 'Mike', '', 'John', ' ']);
   * // array [0 => 'Allison', 1 => 'Mike', 3 => 'John', 4 => ' ']
   *
   * X::removeEmpty(['Allison', 'Mike', '', 'John', ' '], 1));
   * // array [0 => 'Allison', 1 => 'Mike', 3 => 'John']
   * ```
   *
   * @param array|object $arr          An object or array to clean.
   * @param bool         $remove_space If "true" the spaces are removed, default: "false".
   * @return array|object The cleaned result.
   */
  public static function removeEmpty($arr, $remove_space = false)
  {
    $isAssoc = X::isAssoc($arr);
    foreach ($arr as $k => $v) {
      if (\is_object($arr)) {
        if (\is_array($v) || \is_object($v)) {
          $arr->$k = self::removeEmpty($v, $remove_space);
        }
        else {
          if (empty($v)) {
            if (isset($arr->$k)) {
              unset($arr->$k);
            }
          }
          else {
            $arr->$k = $v;
          }
        }
      }
      else{
        if (\is_array($v) || \is_object($v)) {
          $arr[$k] = self::removeEmpty($v, $remove_space);
        }
        elseif ($remove_space && is_string($v)) {
          $arr[$k] = trim($arr[$k]);
        }

        if (empty($arr[$k])) {
          unset($arr[$k]);
        }
      }
    }
    if (!$isAssoc) {
      $arr = array_values($arr);
    }

    return $arr;
  }


  /**
   * Converts an indexed array into a numeric array where the original index is a property.
   * @todo the name is not fitted
   *
   * ```php
   * X::toGroups([25 => 'Allison', 33 => 'Mike', 19 => 'John']);
   * // array [['value' => 25, 'text' => 'Allison'], ['value' => 33, 'text' => 'Francis'], ['value' => 19, 'text' => 'John']]
   *
   * X::toGroups(['Allison', 'Mike', 'John'],'id', 'name');
   * // array [['id' => 25, 'name' => 'Allison'], ['id' => 33, 'name' => 'Francis'], ['id' => 19, 'name' => 'John']]
   * ```
   *
   * @param array  $arr     The original array.
   * @param string $keyname Alias for the index.
   * @param string $valname Alias for the value.
   * @return array Groups array.
   */
  public static function toGroups(array $arr, $keyname = 'value', $valname = 'text'): array
  {
    $r = [];
    foreach ($arr as $k => $v) {
      $r[] = [$keyname => $k, $valname => $v];
    }

    return $r;
  }


  /**
   * Checks if the given array is associative.

   * ```php
   * \bbn\\X::isAssoc(['id' => 0, 'name' => 'Allison']);
   *
   * \bbn\\X::isAssoc(['Allison', 'John', 'Bert']);
   *
   * \bbn\\X::isAssoc([0 => "Allison", 1 => "John", 2 => "Bert"]);
   *
   * \bbn\\X::isAssoc([0 => "Allison", 1 => "John", 3 => "Bert"]);
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
  public static function isAssoc(array $r): bool
  {
    $keys = array_keys($r);
    $c    = \count($keys);
    for ($i = 0; $i < $c; $i++) {
      if ($keys[$i] !== $i) {
        return 1;
      }
    }

    return false;
  }


  /**
   * @return bool
   */
    public static function isCli(): bool
    {
      if (!isset(self::$_cli)) {
        self::$_cli = (php_sapi_name() === 'cli');
      }

      return self::$_cli;
    }


  /**
   * Returns a dump of the given variable.
   *
   * @param mixed
   * @return string
   */
  public static function getDump(): string
  {
    $args = \func_get_args();
    $st   = '';
    foreach ($args as $a) {
      $r = $a;
      if (\is_null($a)) {
        $r = 'null';
      }
      elseif ($a === false) {
        $r = 'false';
      }
      elseif ($a === true) {
        $r = 'true';
      }
      elseif ($a === 0) {
        $r = '0';
      }
      elseif ($a === '') {
        $r = '""';
      }
      elseif ($a === []) {
        $r = '[]';
      }
      elseif (!$a) {
        $r = '0';
      }
      elseif (!\is_string($a) && \is_callable($a)) {
        $r = 'Function';
      }
      elseif (\is_object($a)) {
        $n = \get_class($a);
        if ($n === 'stdClass') {
          $r = Str::export($a);
        }
        else{
          $r = $n.' Object';
        }
      }
      elseif (\is_array($a)) {
        $r = Str::export($a);
      }
      elseif (\is_resource($a)) {
        $r = 'Resource '.get_resource_type($a);
      }
      elseif (Str::isBuid($a)) {
        $tmp = bin2hex($a);
        if (strlen($tmp) === 32) {
          $r = '0x'.bin2hex($a);
        }
      }

      $st .= $r.PHP_EOL;
    }

    return PHP_EOL.$st;
  }


  /**
   * Returns an HTML dump of the given variable.
   *
   * @param mixed
   * @return string
   */
  public static function getHdump(): string
  {
    return nl2br(str_replace("  ", "&nbsp;&nbsp;", htmlentities(self::getDump(...\func_get_args()))), false);
  }


  /**
   * Dumps the given variable.
   *
   * @param mixed
   * @return void
   *
   */
  public static function dump(): void
  {
    echo self::getDump(...\func_get_args());
  }


  /**
   * Dumps the given variable and dies.
   *
   * @param mixed
   * @return void
   *
   */
  public static function ddump(): void
  {
    self::dump(...\func_get_args());
    die();
  }


  /**
   * Dumps the given variable in HTML.
   *
   * @param mixed
   * @return void
   */
  public static function hdump(): void
  {
    echo self::getHdump(...\func_get_args());
  }


  /**
   * Dumps the given variable in HTML and dies.
   *
   * @param mixed
   * @return void
   */
  public static function hddump(): void
  {
    self::hdump(...\func_get_args());
    die();
  }


  /**
   * Adaptive dump, i.e. dumps in text if CLI, HTML otherwise.
   *
   * @param mixed
   * @return void
   */
  public static function adump(): void
  {
    self::isCli() ? self::dump(...\func_get_args()) : self::hdump(...\func_get_args());
  }


  /**
   * Returns the pathinfo, working with multibytes strings.
   *
   * @param string $file
   * @param string $options
   * @return string
   */
  public static function pathinfo(string $path, $options = null)
  {
    $ret = ['dirname' => '', 'basename' => '', 'extension' => '', 'filename' => ''];
    $pathinfo = [];
    if (preg_match('#^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^.\\\\/]+?)|))[\\\\/.]*$#m', $path, $pathinfo)) {
      if (array_key_exists(1, $pathinfo)) {
        $ret['dirname'] = $pathinfo[1];
      }

      if (array_key_exists(2, $pathinfo)) {
        $ret['basename'] = $pathinfo[2];
      }

      if (array_key_exists(5, $pathinfo)) {
        $ret['extension'] = $pathinfo[5];
      }

      if (array_key_exists(3, $pathinfo)) {
        $ret['filename'] = $pathinfo[3];
      }
    }
    switch ($options) {
      case PATHINFO_DIRNAME:
      case 'dirname':
        return $ret['dirname'];
      case PATHINFO_BASENAME:
      case 'basename':
        return $ret['basename'];
      case PATHINFO_EXTENSION:
      case 'extension':
        return $ret['extension'];
      case PATHINFO_FILENAME:
      case 'filename':
        return $ret['filename'];
      default:
        return $ret;
    }
  }


  /**
   * Returns the basename, working with multibytes strings.
   *
   * @param string $file
   * @param string $suffix
   * @return string
   */
  public static function basename(string $path, string $suffix = ''): string
  {
    $res = '';
    // works both in windows and unix
    if (preg_match('@^.*[\\\\/]([^\\\\/]+)$@s', $path, $matches)) {
      $res = $matches[1];
    }
    else if (preg_match('@^([^\\\\/]+)$@s', $path, $matches)) {
      $res = $matches[1];
    }

    if ($res && $suffix && (substr($res, - strlen($suffix)) === $suffix)) {
      return substr($res, 0, - strlen($suffix));
    }

    return $res;
  }


  /**
   * Returns the dirname, working with multibytes strings.
   *
   * @param string $file
   * @param string $suffix
   * @return string
   */
  public static function dirname(string $path): string
  {
    return self::pathinfo($path, 'dirname');
  }


  /**
   * Returns the extension of a path, working with multibytes strings.
   *
   * @param string $file
   * @param string $suffix
   * @return string
   */
  public static function extension(string $path): string
  {
    return self::pathinfo($path, 'extension');
  }


  /**
   * Returns the filename, working with multibytes strings.
   *
   * @param string $file
   * @param string $suffix
   * @return string
   */
  public static function filename(string $path): string
  {
    return self::pathinfo($path, 'filename');
  }


  /**
   * Returns the HTML code for creating the &lt;option&gt; tag(s) based on an array.
   * If the array is indexed, the index will be used as value
   *
   * ```php
   * X::buildOptions(['yes', 'no']);
   * // string "<option value="yes">yes</option><option value="no">no</option>"
   * X::buildOptions(['yes', 'no'], 'no');
   * // string "<option value="yes">yes</option><option value="no" selected="selected">no</option>"
   * X::buildOptions(['yes', 'no'], 'no', 'LabelForEmpty');
   * // string "<option value="">LabelForEmpty</option><option value="yes">yes</option><option value="no" selected="selected">no</option>"
   * X::dump(X::buildOptions([3 => "Allison", 4 => "Mike", 5 => "Andrew"], 5, 'Who?'));
   * // string "<option  value="">Who?</option><option  value="3">Allison</option><option  value="4">Mike</option><option  value="5"  selected="selected">Andrew</option>"
   * ```
   *
   * @param array   $values      The source array for the options
   * @param mixed   $selected    The selected value
   * @param boolean $empty_label A label for empty value
   * @return string The HTML code.
   */
  public static function buildOptions(array $values, $selected='', $empty_label=false): string
  {
    $r = '';
    if ($empty_label !== false) {
      $r .= '<option value="">'.$empty_label.'</option>';
    }

    $is_assoc = self::isAssoc($values);
    foreach ($values as $k => $v)
    {
      if (\is_array($v) && \count($v) == 2) {
        $value = $v[0];
        $title = $v[1];
      }
      elseif (!isset($values[0]) && $is_assoc) {
        $value = $k;
        $title = $v;
      }
      else {
        $value = $title = $v;
      }

      if (isset($value,$title)) {
        $r .= '<option value="'.$value.'"'.
          ($value == $selected ? ' selected="selected"' : '').
          '>'.$title.'</option>';
      }

      unset($value,$title);
    }

    return $r;
  }


  /**
   * Converts a numeric array into an associative one, alternating key and value.
   *
   * ```php
   * X::toKeypair(['Test', 'TestFile', 'Example', 'ExampleFile']);
   * // string ['Test' => 'TestFile', 'Example' => 'ExampleFile']
   * ```
   *
   * @param array $arr       The array. It must contain an even number of values
   * @param bool  $protected If false no index protection will be performed
   * @return false|array
   */
  public static function toKeypair(array $arr, bool $protected = true)
  {
    $num = \count($arr);
    $res = [];
    if (($num % 2) === 0) {
      $i = 0;
      while (isset($arr[$i])) {
        if (!\is_string($arr[$i]) || ($protected && preg_match('/[^0-9A-Za-z\-_]/', Str::cast($arr[$i])))) {
          return false;
        }

        $res[$arr[$i]] = $arr[$i + 1];
        $i            += 2;
      }
    }

    return $res;
  }


  /**
   * Returns the maximum value of a given property from a 2 dimensions array.
   * @todo Add a custom callable as last parameter
   *
   * ```php
   * X::maxWithKey([
   *  ['age' => 1, 'name' => 'Michelle'],
   *  ['age' => 8, 'name' => 'John'],
   *  ['age' => 45, 'name' => 'Sarah'],
   *  ['age' => 45, 'name' => 'Camilla'],
   *  ['age' => 2, 'name' => 'Allison']
   * ], 'age');
   * // int  45
   * ```
   *
   * @param array  $ar  A multidimensional array
   * @param string $key Where to check the property value from
   * @return mixed
   */
  public static function maxWithKey(array $ar, $key)
  {
    if (\count($ar) == 0) {
      return null;
    }

    $max = current($ar)[$key] ?? null;

    if (!$max) {
      return null;
    }

    foreach ($ar as $a) {
      if (is_float($a[$key]) || is_float($max)) {
        if (self::compareFloats($a[$key], $max, '>')) {
          $max = $a[$key];
        }
      }
      elseif ($a[$key] > $max) {
        $max = $a[$key];
      }
    }

    return $max;
  }


  /**
   * Returns the minimum value of an index from a multidimensional array.
   *
   * ```php
   * X::minWithKey([
   *  ['age' => 1, 'name' => 'Michelle'],
   *  ['age' => 8, 'name' => 'John'],
   *  ['age' => 45, 'name' => 'Sarah'],
   *  ['age' => 45, 'name' => 'Camilla'],
   *  ['age' => 2, 'name' => 'Allison']
   * ], 'age');
   * // int  1
   * ```
   *
   * @param array  $array A multidimensional array.
   * @param string $key   The index where to search.
   * @return mixed value
   */
  public static function minWithKey(array $array, $key)
  {
    if (\count($array) == 0) {
      return null;
    }

    $min = $array[0][$key] ?? null;

    if (!$min) {
      return null;
    }

    foreach($array as $a) {
      if($a[$key] < $min) {
        $min = $a[$key];
      }
    }

    return $min;
  }


  /**
   * Gets the backtrace and dumps or logs it into a file.
   *
   * ```php
   * X::dump(X::debug());
   * ```
   * @param string $file The file to debug
   * @return void
   */
  public static function debug($file='')
  {
    $debug = array_map(
      function ($a) {
        if (isset($a['object'])) {
          unset($a['object']);
        }

        return $a;
      }, debug_backtrace()
    );
    if (empty($file)) {
      self::hdump($debug);
    }
    else{
      self::log($debug, $file);
    }
  }


  /**
   * Applies the given function at all levels of a multidimensional array (if defined param $item).
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
   * X::hdump(X::map(function($a) {
   *  if ($a['age']>20) {
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
   * @param callable    $fn    The function to be applied to the items of the array
   * @param array       $ar
   * @param string|null $items If null the function will be applied just to the item of the parent array
   * @return array
   */
  public static function map(callable $fn, array $ar, string $items = null): array
  {
    $res = [];
    foreach ($ar as $key => $a) {
      $is_false = $a === false;
      $r        = $fn($a, $key);
      if ($is_false) {
        $res[] = $r;
      }
      elseif ($r !== false) {
        if (\is_array($r) && $items && isset($r[$items]) && \is_array($r[$items])) {
          $r[$items] = self::map($fn, $r[$items], $items);
        }

        $res[] = $r;
      }
    }

    return $res;
  }




  /**
   * Applies the given function at all levels of a multidimensional array after picking the items (if defined param $item).
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
   * X::hdump(X::map(function($a) {
   *  if ($a['age']>20) {
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
   * @param callable    $fn    The function to be applied to the items of the array
   * @param array       $ar
   * @param string|null $items If null the function will be applied just to the item of the parent array
   * @return array
   */
  public static function rmap(callable $fn, array $ar, string $items = null): array
  {
    $res = [];
    foreach ($ar as $key => $a) {
      if (\is_array($a) && $items && isset($a[$items]) && \is_array($a[$items])) {
        $a[$items] = self::map($fn, $a[$items], $items);
      }
      $is_false = $a === false;
      $r        = $fn($a, $key);
      if ($is_false) {
        $res[] = $r;
      }
      elseif ($r !== false) {
        $res[] = $r;
      }
    }

    return $res;
  }


  /**
   * Returns the array's first index, which satisfies the 'where' condition.
   *
   * ```php
   * X::hdump(X::find([[
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
   * X::hdump(X::find([[
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
   * @param array $ar    The search within the array
   * @param array|callable $where The where condition
   * @return null|int
   */
  public static function find(array $ar, $where, int $from = 0)
  {
    //die(var_dump($where));
    if (!empty($where)) {
      foreach ($ar as $i => $v) {
        if (!$from || ($i >= $from)) {
          $ok = 1;
          if (is_callable($where)) {
            $ok = (bool)$where($v);
          }
          elseif (is_array($where)) {
            $v = (array)$v;
            foreach ($where as $k => $w) {
              if (!array_key_exists($k, $v)
                || (Str::isNumber($v[$k], $w) && ($v[$k] != $w))
                || (!Str::isNumber($v[$k], $w) && ($v[$k] !== $w))
              ) {
                $ok = false;
                break;
              }
            }
          }
          else {
            $ok = $v === $where;
          }

          if ($ok) {
            return $i;
          }
        }
      }
    }

    return null;
  }


  /**
   * Filters the given array which satisfies the 'where' condition.
   *
   * ```php
   * $arr = [
   *    ['id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
   *    ['id' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
   *    ['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
   *    ['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith'],
   *    ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
   * ];
   *
   * X::filter($arr, ['first_name' => 'Mike']);
   * // (array) [
   * //     ['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith'],
   * //     ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams']
   * // ]
   *
   * X::filter($arr, function ($item) {
   *    return $item['first_name'] === 'Mike' && $item['last_name'] === 'Smith';
   * });
   * // (array) [['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith']]
   *
   *
   * ```
   *
   * @param array $ar
   * @param array|callable $where
   * @return array
   */
  public static function filter(array $ar, $where): array
  {
    $res = [];
    $num = count($ar);
    $i   = 0;
    while ($i < $num) {
      $idx = self::find($ar, $where, $i);
      if ($idx === null) {
        break;
      }
      else{
        $res[] = $ar[$idx];
        $i     = $idx + 1;
      }
    }

    return $res;
  }


  /**
   * Filters the given array which satisfies the 'where' condition.
   *
   * ```php
   * $arr = [
   *    ['id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
   *    ['id' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
   *    ['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
   *    ['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith'],
   *    ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
   * ];
   *
   * X::getRows($arr, ['last_name' => 'Williams']);
   * // (array) [
   * //     ['id' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
   * //     ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
   * // ]
   *
   * X::getRows($arr, function ($item) {
   *    return $item['first_name'] === 'Mike' && $item['last_name'] === 'Smith';
   * });
   * // (array) [['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith']]
   * ```
   *
   * @param array $ar
   * @param array|callable $where
   * @return array
   */
  public static function getRows(array $ar, $where): array
  {
    return self::filter($ar, $where);
  }


  /**
   * Returns the sum of all values of the given field in the given array
   * Using an optional where condition to filter the result.
   *
   * ```php
   * $arr = [
   *    ['age' => 19, 'first_name' => 'John', 'last_name' => 'Doe'],
   *    ['age' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
   *    ['age' => 25, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
   *    ['age' => 36.5, 'first_name' => 'Mike', 'last_name' => 'Smith'],
   *    ['age' => 33, 'first_name' => 'Andrew', 'last_name' => 'Smith'],
   * ];
   *
   * X::sum($arr, 'age');
   * // (float) 19 + 11 + 25 + 36.5 + 33
   *
   * X::sum($arr, 'age', ['first_name' => 'Andrew']);
   * // (float) 11 + 33
   *
   * X::sum($arr, 'age', function ($item) {
   *     return $item['first_name'] === 'John' || $item['first_name'] === 'Mike';
   *  });
   * // (float) 19 + 36.5
   * ```
   *
   * @param array $ar
   * @param string $field
   * @param array|callable|null $where
   * @return float
   */
  public static function sum(array $ar, string $field, $where = null): float
  {
    $tot = 0;
    if ($res = $where ? self::filter($ar, $where) : $ar) {
      foreach ($res as $r) {
        $r    = (array)$r;
        $tot += (float)($r[$field]);
      }
    }

    return $tot;
  }


  /**
   * Returns the first row of an array that satisfies the where parameters ({@link find()).
   *
   * ```php
   * X::dump(X::getRow([[
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
   * @param array $r     The array
   * @param array|callable $where The where condition
   * @return bool|mixed
   *
   */
  public static function getRow(array $r, $where): ?array
  {
    if (($res = self::find($r, $where)) !== null) {
      return $r[$res];
    }

    return null;
  }


  /**
   * Returns the first value of a specific field of an array that satisfies the where condition.
   *
   * ```php
   * X::dump(X::getField([[
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
   * @param array  $r     The array
   * @param array|callable  $where The where condition
   * @param string $field The field where to look for
   * @return bool|mixed
   */
  public static function getField(array $r, $where, string $field)
  {
    if (($res = self::getRow($r, $where)) && isset($res[$field])) {
      return $res[$field];
    }

    return false;
  }


  /**
   * Returns a reference to a subarray targeted by an array $keys.
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
   * X::hdump(X::pick($ar,['session', 'user', 'profile', 'admin', 'email']));
   * // string test@test.com
   *
   * X::hdump(X::pick($ar,['session', 'user', 'profile', 'admin']));
   * // ["email"  =>  "test@test.com",]
   * ```
   * @param array $ar   The array
   * @param array $keys The array's keys
   * @return array|mixed
   */
  public static function pick(array $ar, array $keys)
  {
    while (\count($keys)) {
      $r = array_shift($keys);
      if (is_array($ar) && array_key_exists($r, $ar)) {
        $ar = $ar[$r];
        if (!count($keys)) {
          return $ar;
        }
      }
    }
  }


  /**
   * Sorts the items of an array.
   *
   * ```php
   * $var = [3, 2, 5, 6, 1];
   * X::sort($var);
   * X::hdump($var);
   * // array [1,2,3,5,6]
   * ```
   *
   * @param $ar array The reference of the array to sort
   * @return void
   */
  public static function sort(array &$ar, bool $backward = false)
  {
    usort(
      $ar,
      function ($a, $b) use ($backward) {
        if (!Str::isNumber($a, $b)) {
          $a = str_replace('.', '0', str_replace('_', '1', Str::changeCase($a, 'lower')));
          $b = str_replace('.', '0', str_replace('_', '1', Str::changeCase($b, 'lower')));
          return $backward ? strcmp($b, $a) : strcmp($a, $b);
        }

        if ($a > $b) {
          return $backward ? -1 : 1;
        }
        elseif ($a == $b) {
          return 0;
        }

        return $backward ? 1 : -1;
      }
    );
  }


  /**
   * Sorts the items of an indexed array based on a given $key.
   *
   * ```php
   *  $v = [
   *    ['age'=>10, 'name'=>'thomas'],
   *    ['age'=>22, 'name'=>'John'],
   *    ['age'=>37, 'name'=>'Michael']
   *  ];
   *  X::sortBy($v,'name','desc');
   *  X::hdump($v);
   *  X::sortBy($v,'name','asc');
   *  X::hdump($v);
   *  X::sortBy($v,'age','asc');
   *  X::hdump($v);
   *  X::sortBy($v,'age','desc');
   *  X::hdump($v);
   * ```
   *
   * @param array            $ar  The array of data to sort
   * @param string|int|array $key The key to sort by
   * @param string           $dir The direction of the sort ('asc'|'desc')
   * @return void
   */
  public static function sortBy(array &$ar, $key, $dir = ''): array
  {
    $args = \func_get_args();
    array_shift($args);
    if (\is_string($key)) {
      $args = [[
        'key' => $key,
        'dir' => $dir
      ]];
    }

    usort(
      $ar,
      function ($a, $b) use ($args) {
        foreach ($args as $arg) {
          $key = $arg['key'];
          $dir = $arg['dir'] ?? 'asc';
          if (!\is_array($key)) {
            $key = [$key];
          }

          $v1 = self::pick($a, $key);
          $v2 = self::pick($b, $key);
          $a1 = strtolower($dir) === 'desc' ? ($v2 ?? null) : ($v1 ?? null);
          $a2 = strtolower($dir) === 'desc' ? ($v1 ?? null) : ($v2 ?? null);
          if (!Str::isNumber($v1, $v2)) {
            $a1  = str_replace('.', '0', str_replace('_', '1', Str::changeCase($a1, 'lower')));
            $a2  = str_replace('.', '0', str_replace('_', '1', Str::changeCase($a2, 'lower')));
            $cmp = strcmp($a1, $a2);
            if (!empty($cmp)) {
              return $cmp;
            }
          }

          if ($a1 > $a2) {
            return 1;
          }
          elseif ($a1 < $a2) {
            return -1;
          }
        }

        return 0;
      }
    );
    return $ar;
  }


  /**
   * Checks if the operating system, from which PHP is executed, is Windows or not.
   * ```php
   * X::dump(X::isWindows());
   * // boolean false
   * ```
   *
   * @return bool
   */
  public static function isWindows(): bool
  {
    return strtoupper(substr(PHP_OS, 0, 3)) == 'WIN';
  }


  /**
   * Makes a Curl call towards a URL and returns the result as a string.
   *
   * ```php
   *  $url = 'https://www.omdbapi.com/';
   *  $param = ['t'=>'la vita è bella'];
   *  X::hdump(X::curl($url,$param, ['POST' => false]));
   *
   * // object {
   * //   "Title":"La  vita  è  bella",
   * //   "Year":"1943",
   * //   "Rated":"N/A",
   * //   "Released":"26  May  1943",
   * //   "Runtime":"76  min",
   * //   "Genre":"Comedy"
   * //   "imdbRating":"7.9",
   * //   "imdbVotes":"50",
   * //   "imdbID":"tt0036502",
   * //   "Type":"movie",
   * //   "Response":"True"
   * // }
   * ```
   *
   * @param string $url
   * @param array  $param
   * @param array  $options
   * @return mixed
   */
  public static function curl(string $url, $param = null, array $options = ['post' => 1])
  {
    $ch               = curl_init();
    self::$_last_curl = $ch;
    $defined          = array_map('strtolower', array_keys($options));
   
    if (!in_array('returntransfer', $defined)) {
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    }

    if (\is_object($param)) {
      $param = self::toArray($param);
    }

    if (\defined('BBN_IS_SSL') && \defined('BBN_IS_DEV') && BBN_IS_SSL && BBN_IS_DEV) {
      if (!in_array('ssl_verifypeer', $defined)) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      }

      if (!in_array('ssl_verifyhost', $defined)) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      }

      //curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
    }

    $options = array_change_key_case($options, CASE_UPPER);
    foreach ($options as $opt => $val) {
      if (\defined('CURLOPT_'.$opt)) {
        curl_setopt($ch, constant('CURLOPT_'.$opt), $val);
      }
    }

    if ($param) {
      if (!empty($options['POST'])) {
        if (!in_array('url', $defined)) {
          curl_setopt($ch, CURLOPT_URL, $url);
        }

        if (!in_array('postfields', $defined)) {
          curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        }
      }
      elseif (!empty($options['DELETE'])) {
        //die($url.'?'.http_build_query($param));
        if (!in_array('url', $defined)) {
          curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($param));
        }

        if (!in_array('customrequest', $defined)) {
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
      }
      elseif (!in_array('url', $defined)) {
        curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($param));
      }
    }
    else{
      if (!in_array('url', $defined)) {
        curl_setopt($ch, CURLOPT_URL, $url);
      }

      if (!empty($options['DELETE']) && !in_array('customrequest', $defined)) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
      }
    }

    $r = curl_exec($ch);
    
    if (!$r) {
      self::log(["PROBLEME AVEC L'URL $url", curl_error($ch), curl_getinfo($ch)], 'curl');
    }

    return $r;
  }


  public static function lastCurlError()
  {
    if (self::$_last_curl) {
      return curl_error(self::$_last_curl);
    }

    return null;
  }


  public static function lastCurlCode()
  {
    if (self::$_last_curl) {
      $infos = curl_getinfo(self::$_last_curl);
      if ($infos) {
        return $infos['http_code'];
      }
    }

    return null;
  }


  public static function lastCurlInfo()
  {
    if (self::$_last_curl) {
      return curl_getinfo(self::$_last_curl);
    }

    return null;
  }


  /**
   * Returns the given array or object as a tree structure ready for a JS tree.
   *
   * ```php
   * X::hdump(X::getTree([['id' => 1,'name' => 'Andrew','fname' => 'Williams','children' =>[['name' => 'Emma','age' => 6],['name' => 'Giorgio','age' => 9]]], ['id' => 2,'name' => 'Albert','fname' => 'Taylor','children' =>[['name' => 'Esther','age' => 6],['name' => 'Paul','age' => 9]]], ['id' => 3,'name' => 'Mike','fname' => 'Smith','children' =>[['name' => 'Sara','age' => 6],['name' => 'Fred','age' => 9]]]]));
   * /* array [
   *    [ "text" => 0, "items" => [ [ "text" => "id: 1", ], [ "text" => "name: Andrew", ], [ "text" => "fname: Williams", ], [ "text" => "children", "items" => [ [ "text" => 0, "items" => [ [ "text" => "name: Emma", ], [ "text" => "age: 6", ], ], ], [ "text" => 1, "items" => [ [ "text" => "name: Giorgio", ], [ "text" => "age: 9", ], ], ], ], ], ], ], [ "text" => 1, "items" => [ [ "text" => "id: 2", ], [ "text" => "name: Albert", ], [ "text" => "fname: Taylor", ], [ "text" => "children", "items" => [ [ "text" => 0, "items" => [ [ "text" => "name: Esther", ], [ "text" => "age: 6", ], ], ], [ "text" => 1, "items" => [ [ "text" => "name: Paul", ], [ "text" => "age: 9", ], ], ], ], ], ], ], [ "text" => 2, "items" => [ [ "text" => "id: 3", ], [ "text" => "name: Mike", ], [ "text" => "fname: Smith", ], [ "text" => "children", "items" => [ [ "text" => 0, "items" => [ [ "text" => "name: Sara", ], [ "text" => "age: 6", ], ], ], [ "text" => 1, "items" => [ [ "text" => "name: Fred", ], [ "text" => "age: 9", ], ], ], ], ], ], ], ]
   * ```
   *
   * @param array $ar
   * @return array
   */
  public static function getTree($ar): array
  {
    $res = [];
    foreach ($ar as $k => $a) {
      $r = ['text' => $k];
      if (\is_object($a)) {
        $a = self::toArray($a);
      }

      if (\is_array($a)) {
        $r['items'] = self::getTree($a);
      }
      elseif (\is_null($a)) {
        $r['text'] .= ': null';
      }
      elseif ($a === false) {
        $r['text'] .= ': false';
      }
      elseif ($a === true) {
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
   * Moves an index in the given array to a new index.
   *
   * ```php
   * $arr = [
   *    ['a' => 1, 'b' => 2],
   *    ['c' => 3, 'd' => 4],
   *    ['e' => 5, 'f' => 6]
   * ];
   *
   * X::move($arr, 0, 2);
   * // (array) [
   * //    ['c' => 3, 'd' => 4],
   * //    ['e' => 5, 'f' => 6],
   * //    ['a' => 1, 'b' => 2]
   * // ]
   * ```
   *
   * @param array $ar
   * @param int $old_index
   * @param int $new_index
   */
  public static function move(array &$ar, int $old_index, int $new_index): void
  {
    $out = array_splice($ar, $old_index, 1);
    array_splice($ar, $new_index, 0, $out);
  }


  /**
   * Returns a view of an array or object as a JS tree.
   *
   * ```php
   * X::dump(X::makeTree([['id' => 1,'name' => 'Andrew','fname' => 'Williams','children' =>[['name' => 'Emma','age' => 6],['name' => 'Giorgio','age' => 9]]], ['id' => 2,'name' => 'Albert','fname' => 'Taylor','children' =>[['name' => 'Esther','age' => 6],['name' => 'Paul','age' => 9]]], ['id' => 3,'name' => 'Mike','fname' => 'Smith','children' =>[['name' => 'Sara','age' => 6],['name' => 'Fred','age' => 9]]]]));
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
  public static function makeTree(array $ar): string
  {
    return "<bbn-tree :source='".\bbn\Str::escapeSquotes(json_encode(self::getTree($ar)))."'></bbn-tree>";
  }


  /**
   * Formats a CSV line(s) and returns it as an array.
   * Adapted from http://us3.php.net/manual/en/function.fputcsv.php#87120
   *
   * ```php
   *  X::dump(X::fromCsv(
   *      '"141";"10/11/2002";"350.00";"1311742251"
   *      "142";"12/12/2002";"349.00";"1311742258"'
   * ));
   * // [ [ "141", "10/11/2002", "350.00", "1311742251", ], [ "142", "12/12/2002", "349.00", "1311742258", ], ]
   * ```
   *
   * @param string $st  The Csv string to format
   * @param string $del Deimiter
   * @param string $enc Enclosure
   * @param string $sep Separator
   * @return array
   */
  public static function fromCsv(string $st, $del = ';', $enc = '"', $sep = PHP_EOL): array
  {
    $r     = [];
    $lines = explode($sep, $st);
    foreach ($lines as $line) {
      $r[] = str_getcsv($line, $del, $enc);
    }

    return $r;
  }


  /**
   * Formats an array as a CSV string.
   * Adapted from http://us3.php.net/manual/en/function.fputcsv.php#87120
   *
   * ```php
   * X::dump(X::toCsv([["John", "Mike", "David", "Clara"],["White", "Red", "Green", "Blue"]]));
   * // John;Mike;David;Clara
   * // White;Red;Green;Blue
   * ```
   *
   * @param array  $data            The array to format
   * @param string $delimiter
   * @param string $enclosure
   * @param string $separator
   * @param bool   $encloseAll
   * @param bool   $nullToMysqlNull
   * @return string
   */


  public static function toCsv(array $data, $delimiter = ';', $enclosure = '"', $separator = PHP_EOL, $encloseAll = false, $nullToMysqlNull = false): string
  {
    $delimiter_esc = preg_quote($delimiter, '/');
    $enclosure_esc = preg_quote($enclosure, '/');

    $lines = [];
    foreach ($data as $d) {
      $output = [];
      foreach ($d as $field) {
        if ($field === null && $nullToMysqlNull) {
          $output[] = 'NULL';
          continue;
        }

        // Enclose fields containing $delimiter, $enclosure or whitespace
        if ($encloseAll || preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field)) {
          $output[] = $enclosure.str_replace($enclosure, '\\'.$enclosure, $field) . $enclosure;
        }
        else {
          $output[] = $field;
        }
      }

      $lines[] = implode($delimiter, $output);
    }

    return self::join($lines, $separator);
  }


  /**
   * Checks if two files are the same.
   *
   * @param string $file1
   * @param string $file2
   * @param bool $strict
   * @return bool
   * @throws \Exception
   */
  public static function isSame(string $file1, string $file2, $strict = false): bool
  {
    if (!is_file($file1) || !is_file($file2)) {
      throw new \Exception("Boo! One of the files given to the X::is_same function doesn't exist");
    }

    $same = filesize($file1) === filesize($file2);
    if (!$strict || !$same) {
      return $same;
    }

    return filemtime($file1) === filemtime($file2);
  }


  /**
   * Retrieves values from the given array based on the given keys.
   *
   * ```php
   * $arr = ['a' => ['e' => 33, 'f' => 'foo'], 'b' => 2, 'c' => 3, 'd' => ['g' => 11]];
   *
   * X::retrieveArrayVar(['a', 'e'], $arr);
   * // (int) 33
   *
   * X::retrieveArrayVar(['a', 'f'], $arr);
   * // (string) "foo"
   *
   * X::retrieveArrayVar(['d'], $arr);
   * // (array) ['g' => 11]
   * ```
   *
   * @param array $props
   * @param array $ar
   * @return array|mixed
   * @throws \Exception
   */
  public static function retrieveArrayVar(array $props, array &$ar)
  {
    $cur = &$ar;
    foreach ($props as $p) {
      if (\is_array($cur) && array_key_exists($p, $cur)) {
        $cur =& $cur[$p];
      }
      else{
        throw new \Exception("Impossible to find the value in the array");
      }
    }

    return $cur;
  }


  /**
   * Retrieves values from the given object based on the given properties.
   *
   * ```php
   * $obj = (object)['a' => (object)['e' => 33, 'f' => 'foo'], 'b' => 2, 'c' => 3, 'd' => (object)['g' => 11]];
   *
   *  X::retrieveObjectVar(['a', 'e'], $obj);
   * // (int) 33
   *
   * X::retrieveObjectVar(['a', 'f'], $obj);
   * // (string) foo
   *
   * X::retrieveObjectVar(['d'], $obj);
   * // (object) {'g' : 11}
   * ```
   *
   * @param array $props
   * @param object $obj
   * @return object
   * @throws \Exception
   */
  public static function retrieveObjectVar(array $props, object $obj)
  {
    $cur = $obj;
    foreach ($props as $p) {
      if (is_object($cur) && property_exists($cur, $p)) {
        $cur = $cur->{$p};
      }
      else{
        throw new \Exception("Impossible to find the value in the object");
      }
    }

    return $cur;
  }


   /**
   * Counts the properties of an object.
   *
   *```php
   * $obj = (object)[
   *      'a' => 1,
   *      'b' => false,
   *      'c' => null
   * ];
   *
   * X::countProperties($obj);
   * // (int) 3
   *
   * ```
   *
   * @parma $obj
   * @return int
   */
  public static function countProperties(object $obj): int
  {
    return \count(get_object_vars($obj));
  }


  /**
   * Creates an Excel file from a given array.
   *
   * @param array $data
   * @param string $file The file path
   * @param bool $with_titles Set it to false if you don't want the columns titles. Default true
   * @param array $cfg
   * @return bool
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
   */
  public static function toExcel(array $data, string $file, bool $with_titles = true, array $cfg = []): bool
  {
    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
      throw new \Exception(X::_("You need the PhpOffice library to use this function"));
    }

    $excel    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet    = $excel->getActiveSheet();
    $ow       = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($excel);
    $can_save = false;
    if (empty($cfg)) {
      $todo    = [];
      $checked = false;
      foreach ($data as $d) {
        if (!$checked && self::isAssoc($d)) {
          if ($with_titles) {
            $line1 = [];
            $line2 = [];
            foreach ($d as $k => $v) {
              $line1[] = $k;
              $line2[] = '';
            }

            $todo[] = $line1;
            $todo[] = $line2;
          }

          $checked = true;
        }

        $todo[] = array_values($d);
      }

      if (count($todo)) {
        $sheet->fromArray($todo, null, 'A1');
        $excel->getDefaultStyle()->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $can_save = true;
      }
    }
    else {
      foreach ($cfg['fields'] as $i => $field) {
        // Get cell object
        $cell = $sheet->getCellByColumnAndRow($i + 1, 0);
        // Get colum name
        $col_idx = $cell->getColumn();
        // Set auto width to the column
        $sheet->getColumnDimension($col_idx)->setAutoSize(true);
        // Cell style object
        $style = $sheet->getStyle("$col_idx:$col_idx");
        // Get number format object
        $format = $style->getNumberFormat();
        // Set the vertical alignment to center
        $style->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
        // Set the correct data type
        switch ($field['type']) {
          case 'integer':
            // Set code's format to number
            $format->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            break;
          case 'decimal':
            // Set code's format to decimal
            $format->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
            break;
          case 'money':
            // Set code's format to currency
            $format->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE);
            break;
          case 'date':
            // Set code's format to date
            $format->setFormatCode('dd/mm/yyyy');
            // Set the horizontal alignment to center
            $style->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            break;
          case 'datetime':
            // Set code's format to datetime
            $format->setFormatCode('dd/mm/yyyy hh:mm');
            // Set the horizontal alignment to center
            $style->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            break;
          case 'boolean':
            // Set the horizontal alignment to center
            $style->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            break;
          case 'phone':
            // Set the custom format
            $format->setFormatCode('+#');
            break;
          case 'string':
          default:
            // Set code's format to text
            $format->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            // Set wrap text
            $style->getAlignment()->setWrapText(true);
            break;
        }

        if ($with_titles) {
          $cell  = $sheet->getCellByColumnAndRow($i + 1, 1);
          $style = $cell->getStyle();
          // Set code's format to text
          $style->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
          // Set the horizontal alignment to center
          $style->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
          // Set bold to true
          $style->getFont()->setBold(true);
          // Set the column's title
          $cell->setValue($field['title'] ?? $field['field']);
        }
      }

      if (isset($cfg['map'], $cfg['map']['callable'])
          && is_callable($cfg['map']['callable'])
      ) {
        array_walk($data, $cfg['map']['callable'], is_array($cfg['map']['params']) ? $cfg['map']['params'] : []);
      }

      $sheet->fromArray($data, null, 'A' . ($with_titles ? '2' : '1'));
      $can_save = true;
    }

    if ($can_save
        && \bbn\File\Dir::createPath(self::dirname($file))
    ) {
      $ow->save($file);
      return \is_file($file);
    }

    return false;
  }


  /**
  * Makes a UID.
  *
  * @param bool $binary Set it to true if you want a binary UID
  * @param bool $hypens Set it to true if you want hypens to seperate the UID
  * @return string|bynary
  */
  public static function makeUid($binary = false, $hyphens = false): string
  {
    $tmp = sprintf(
      $hyphens ? '%04x%04x-%04x-%04x-%04x-%04x%04x%04x' : '%04x%04x%04x%04x%04x%04x%04x%04x',
      // 32 bits for "time_low"
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      // 16 bits for "time_mid"
      mt_rand(0, 0xffff),
      // 16 bits for "time_hi_and_version",
      // four most significant bits holds version number 4
      mt_rand(0, 0x0fff) | 0x4000,
      // 16 bits, 8 bits for "clk_seq_hi_res",
      // 8 bits for "clk_seq_low",
      // two most significant bits holds zero and one for variant DCE1.1
      mt_rand(0, 0x3fff) | 0x8000,
      // 48 bits for "node"
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    return $binary ? hex2bin($tmp) : $tmp;
  }


  /**
  * Converts a hex UID to a binary UID. You can also give an array or an object to convert the array's items or the object's properties.
  *
  *```php
  *
  * X::convertUids('b39e594c261e4bba85f4994bc08657dc');
  * // (string) b"³žYL&\x1EKº…ô™KÀ†WÜ"
  *
  * X::convertUids(['b39e594c261e4bba85f4994bc08657dc, 'b39e594c261e4bba85f4994bc08657dc]);
  * // (array) [b"³žYL&\x1EKº…ô™KÀ†WÜ", b"³žYL&\x1EKº…ô™KÀ†WÜ"]
  *
  * X::convertUids((object)['uid' => 'b39e594c261e4bba85f4994bc08657dc, 'uid2' => 'b39e594c261e4bba85f4994bc08657dc]);
  * // (object) {'uid': b"³žYL&\x1EKº…ô™KÀ†WÜ", 'uid2': b"³žYL&\x1EKº…ô™KÀ†WÜ"}
  *
  *```
  *
  * @param string|array|object $st
  * @return string
  */
  public static function convertUids($st)
  {
    if (\is_array($st) || \is_object($st)) {
      foreach ($st as &$s) {
        $s = self::convertUids($s);
      }
    }
    elseif (\bbn\Str::isUid($st)) {
      $st = hex2bin($st);
    }

    return $st;
  }


  /**
  * Compares two float numbers with the given operator.
  *
  *```php
  * X::compareFloats(2.0, 4.0, '<');
  * // (bool) true
  *
  *  X::compareFloats(2.56222223, 2.56222223, '<=')
  * // (bool) true
  *
  * X::compareFloats(2.5623, 2.5623, '<')
  * // (bool) false
  *```
  *
  * @param float  $v1 Value 1
  * @param float  $v2 Value 2
  * @param string $op Operator
  * @param int    $pr Precision
  * @return boolean
  */
  public static function compareFloats($v1, $v2, string $op = '===', int $pr = 4): bool
  {
    $v1 = round((float)$v1 * pow(10, $pr));
    $v2 = round((float)$v2 * pow(10, $pr));
    switch ($op) {
      case '===':
        return $v1 === $v2;
      case '==':
        return $v1 == $v2;
      case '>=':
        return $v1 >= $v2;
      case '<=':
        return $v1 <= $v2;
      case '>':
        return $v1 > $v2;
      case '<':
        return $v1 < $v2;
    }

    return false;
  }

  public static function fixJson($json) {
    $newJSON = '';

    $jsonLength = strlen($json);
    $escaped = false;
    $opened_b = 0;
    $opened_cb = 0;
    $unescaped = false;
    $squotes = false;
    $dquotes = false;
    $current = '';
    $end_value = false;
    $end_prop = false;
    $last_quotes = '';
    $prop = false;
    $last_char = '';
    for ($i = 0; $i < $jsonLength; $i++) {
      //var_dump($a);
      $add = '';
      $a = $json[$i];
      switch ($a) {
        case '\\':
          if ($escaped) {
            $escaped = false;
            $unescaped = true;
          }
          else {
            $escaped = true;
          }
          break;
        case '"':
          if (!$escaped && !$squotes) {
            $dquotes = !$dquotes;
            $last_quotes = '"';
          }
          break;
        case "'":
          if (!$escaped && !$dquotes) {
            $squotes = !$squotes;
            $last_quotes = "'";
          }
          break;
        case '{':
          if (!$dquotes && !$squotes) {
            $opened_cb++;
            $last_quotes = "";
          }
          break;
        case '}':
          if (!$dquotes && !$squotes) {
            $opened_cb--;
            $end_value = true;
            if ($last_char === ',') {
              $newJSON = substr($newJSON, 0, -1);
            }
          }
          break;
        case '[':
          if (!$dquotes && !$squotes) {
            $opened_b++;
            $last_quotes = "";
          }
          break;
        case ']':
          if (!$dquotes && !$squotes) {
            $opened_b--;
            $end_value = true;
            if ($last_char === ',') {
              $newJSON = substr($newJSON, 0, -1);
            }
          }
          break;
        case ':':
          if (!$dquotes && !$squotes) {
            $end_prop = true;
          }
          break;
        case ',':
          if (!$dquotes && !$squotes) {
            $end_value = true;
          }
          break;
        case '/':
          if ($last_char !== '\\') {
            //$current .= '\\';
          }
          break;
        default:
          if ($escaped) {
            $escaped = false;
          }
          if ($unescaped) {
            $unescaped = false;
          }
      }
      if ($end_prop) {
        if ($last_quotes === '"') {
          $add .= $current;
        }
        elseif ($last_quotes === "'") {
          $current = trim($current);
          $add .= '"'.Str::escapeDquote(Str::unescapeSquote(substr($current, 1, -1))).'":';
        }
        else {
          $add .= '"'.Str::escapeDquote($current).'":';
        }

        $end_prop = false;
      }
      elseif ($end_value) {
        if ($current) {
          if ($last_quotes) {
            $current = trim($current);
            $add .= '"'.Str::escapeDquote(substr($current, 1, -1)).'"';
          }
          else {
            $add .= Str::escapeDquote($current);
          }

          if ($a !== ' ') {
            $add .= $a;
          }
        }
        else {
          $current .= $a;
        }
        $last_quotes = "";
        $end_value = false;
      }
      elseif (!$dquotes && !$squotes && (($a === '[') || ($a === '{'))) {
        $add .= $a;
      }
      elseif ($dquotes || $squotes || ($a !== ' ')) {
        $current .= $a;
      }

      if ($add) {
        $newJSON .= $add;
        $current = '';
      }

      if ($a !== ' ') {
        $last_char = $a;
      }
    }

    if ($current) {
      $newJSON .= $current;
    }

    return $newJSON;
  }


  /**
  * Encodes an array's values to the base64 encoding scheme. You can also convert the resulting array into a JSON string (default).
   *
   * ```php
   *
   * X::jsonBase64Encode(['a' => 'Hello World!', 'b' => 2]);
   * // (string) '{"a":"SGVsbG8gV29ybGQh","b":2}'
   *
   * X::jsonBase64Encode(['a' => 'Hello World!'], false);
   * // (array) ['a' => 'SGVsbG8gV29ybGQh']
   *
   * ```
  *
  * @param array   $arr
  * @param boolean $json
  * @return string|array
  */
  public static function jsonBase64Encode(array $arr, $json = true)
  {
    $res = [];
    foreach ($arr as $i => $a) {
      if (is_array($a)) {
        $res[$i] = self::jsonBase64Encode($a, false);
      }
      elseif (is_string($a)) {
        $res[$i] = base64_encode($a);
      }
      else{
        $res[$i] = $a;
      }
    }

    return $json ? json_encode($res) : $res;
  }


  /**
  * Decodes the base64 array's values. You can also give a JSON string of an array.
   *
   * ```php
   *
   * X::jsonBase64Decode(['a' => 'SGVsbG8gV29ybGQh', 'b' => ['c' => base64_encode('Rm9v')]]);
   * // (array) ['a' => 'Hello World!', 'b' => ['c' => 'Foo']]
   *
   * X::jsonBase64Decode('{"a":"SGVsbG8gV29ybGQh","b":{"c":"Rm9v"}}');
   * // (array) ['a' => 'Hello World!', 'b' => ['c' => 'Foo']]
   *
   * ```
  *
  * @param string|array $st
  * @return array|null
  */
  public static function jsonBase64Decode($st): ?array
  {
    $res = \is_string($st) ? json_decode($st, true) : $st;
    if (\is_array($res)) {
      foreach ($res as $i => $a) {
        if (\is_array($a)) {
          $res[$i] = self::jsonBase64Decode($a);
        }
        elseif (\is_string($a)) {
          $res[$i] = base64_decode($a);
        }
        else{
          $res[$i] = $a;
        }
      }

      return $res;
    }

    return null;
  }


  /**
  * Creates an associative array based on the first array's value.
  *
  *```php
  * $arr = [
  *          [
  *            'a' => 'foo',
  *            'b' => 'bar'
  *          ],
  *          [
  *            'a' => 'foo2',
  *            'b' => 'bar2'
  *          ]
  *        ];
  *
  * X::indexByFirstVal($arr);
  * // (array) ['foo' => 'bar', 'foo2' => 'bar2']
  *```
  *
  * @param array $ar
  * @return array
  */
  public static function indexByFirstVal(array $ar): array
  {
    if (empty($ar) || !isset($ar[0]) || !\count($ar[0])) {
      return $ar;
    }

    $cols     = array_keys($ar[0]);
    $idx      = array_shift($cols);
    $num_cols = \count($cols);
    $res      = [];
    foreach ($ar as $d) {
      $index = $d[$idx];
      unset($d[$idx]);
      $res[$index] = $num_cols > 1 ? $d : $d[$cols[0]];
    }

    return $res;
  }


  /**
   * Join array elements with a string
   *
   * ```php
   *
   * X::join(['foo', 'bar']);
   * // (string) "foobar"
   *
   * X::join(['foo', 'bar'], ' ');
   * // (string) "foo bar"
   *
   * ```
   *
   * @param array $ar
   * @param string $glue
   * @return string
   */
  public static function join(array $ar, string $glue = ''): string
  {
    return implode($glue, $ar);
  }


  /**
   * Split a string by a string
   *
   * ```php
   *
   * X::concat('foo bar', ' ');
   * // (array) ['foo', 'bar']
   *
   * X::concat('foo,bar', ',');
   * // (array) ['foo', 'bar']
   *
   * ```
   *
   * @param string $st
   * @param string $separator
   * @return array
   */
  public static function concat(string $st, string $separator): array
  {
    return explode($separator, $st);
  }


  /**
   * Split a string by a string
   *
   * ```php
   *
   * X::split('foo bar', ' ');
   * // (array) ['foo', 'bar']
   *
   * X::split('foo,bar', ',');
   * // (array) ['foo', 'bar']
   *
   * ```
   *
   * @param string $st
   * @param string $separator
   * @return array
   */
  public static function split(string $st, string $separator): array
  {
    return explode($separator, $st);
  }


  /**
   * Searches from start to end.
   *
   * ```php
   *
   * X::indexOf(['a', 'b', 'c'], 'b');
   * // (int) 1
   *
   * X::indexOf(['a', 'b', 'c'], 'b', 2);
   * // (int) -1
   *
   * X::indexOf('foobar', 'bar');
   * // (int) 3
   *
   * X::indexOf('foobar', 'bar', 4);
   * // (int) -1
   *
   * ```
   *
   * @param $subject
   * @param $search
   * @param int $start
   * @return int
   */
  public static function indexOf($subject, $search, int $start = 0): int
  {
    $res = false;
    if (is_array($subject)) {
      $i = 0;
      foreach ($subject as $s) {
        if (($i >= $start) && ($s === $search)) {
          $res = $i;
          break;
        }
        else{
          $i++;
        }
      }
    }
    elseif (is_string($subject)) {
      $res = strpos($subject, $search, $start);
    }

    return $res === false ? -1 : $res;
  }


  /**
   * Searches from end to start
   *
   * ```php
   *
   * X::lastIndexOf(['a', 'b', 'c', 'd'], 'c', 3);
   * // (int) 1
   *
   * X::lastIndexOf('foobar', 'bar');
   * // (int) 3
   *
   * X::lastIndexOf('foobar', 'bar', 4);
   * // (int) -1
   *
   * X::lastIndexOf('foobarbar', 'bar');
   * // (int) 6
   *
   * ```
   * @param $subject
   * @param $search
   * @param int|null $start
   * @return int
   */
  public static function lastIndexOf($subject, $search, int $start = null): int
  {
    $res = false;
    if (is_array($subject)) {
      $i = count($subject) - 1;
      if ($i) {
        if ($start > 0) {
          if ($start > $i) {
            return -1;
          }

          $i = $start;
        }
        elseif ($start < 0) {
          $i -= $start;
          if ($i < 0) {
            return -1;
          }
        }

        foreach ($subject as $s) {
          if (($i <= $start) && ($s === $search)) {
            $res = $i;
            break;
          }
          else{
            $i--;
          }
        }
      }
    }
    elseif (is_string($subject)) {
      if ($start > 0) {
        $start = strlen($subject) - (strlen($subject) - $start);
      }

      $res = strrpos($subject, $search, $start);
    }

    return $res === false ? -1 : $res;
  }


  /**
   * ```php
   *
   * X::output(1, true, null, 'foo', ['a', 'b'], (object)['a' => 1, 'b' => ['c' => 2, 'd' => 3]]);
   * // (string)
   * // "1
   * // true
   * // null
   * // foo
   * //
   * // [
   * //   "a",
   * //   "b",
   * // ]
   * //
   * //
   * // {
   * //   "a": 1,
   * //   "b": {
   * //     "c": 2,
   * //     "d": 3,
   * //   },
   * // }
   *
   *
   * "
   *
   * ```
   */
  public static function output()
  {
    $wrote = false;
    foreach (func_get_args() as $a) {
      if ($a === null) {
        $st = 'null';
      }
      elseif ($a === true) {
        $st = 'true';
      }
      elseif ($a === false) {
        $st = 'false';
      }
      elseif (\bbn\Str::isNumber($a)) {
        $st = $a;
      }
      elseif (!is_string($a)) {
        $st = self::getDump($a);
      }
      else {
        $st = $a;
      }

      if ($st) {
        $wrote = true;
        echo $st.PHP_EOL;
      }
    }

    if ($wrote) {
      //ob_end_flush();
    }
  }


  /**
   * @param $name
   * @param $arguments
   * @return mixed|null
   */
  public static function __callStatic($name, $arguments)
  {
    if ((strpos($name, 'is_') === 0) && function_exists($name)) {
      $res = null;
      foreach ($arguments as $a) {
        $res = $name($a);
        if (!$res) {
          return $res;
        }
      }

      return $res;
    }

    if (!method_exists(self::class, $name)) {
      throw new \Exception(self::_("Undefined Method $name"));
    }
  }


}
