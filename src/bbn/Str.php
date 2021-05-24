<?php
namespace bbn;

/**
 * Class text
 * string manipulation class
 *
 * This class only uses static methods and has lots of alias for the escaping methods
 *
 * @package bbn
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Strings
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 *
 */

class Str
{

  /**
   * Markdown parser if needed only one instance is created.
   *
   * @var ?\Parsedown
   */
  private static $_markdownParser = null;


  /**
   * Converts any type of variable to a string.
   *
   * ```php
   * $st = 122
   * X::dump(\bbn\Str::cast($st));
   * // (string) "122"
   * X::dump(\bbn\Str::cast(1));
   * // (string) "1"
   * X::dump(\bbn\Str::cast(['foo' => 'bar'])
   * // (string) ""
   * ```
   *
   * @param mixed $st The item to cast.
   * @return string
   */
  public static function cast($st)
  {
    if (\is_array($st) || \is_object($st)) {
      return '';
    }

    return (string)$st;
  }


  /**
   * Converts the case of a string.
   *
   * ```php
   * $st = 'TEST CASE';
   * X::dump(\bbn\Str::changeCase($st, 'lower'));
   * // (string) "test case"
   * X::dump(\bbn\Str::changeCase('TEsT Case', 'upper'));
   * // (string) "TEST CASE"
   * X::dump(\bbn\Str::changeCase('test case'));
   * // (string) "Test Case"
   * ```
   *
   * @param mixed $st   The item to convert.
   * @param mixed $case The case to convert to ("lower" or "upper"), default being the title case.
   * @return string
   */
  public static function changeCase($st, $case = 'x'): string
  {
    $st   = self::cast($st);
    $case = substr(strtolower((string)$case), 0, 1);
    switch ($case){
      case "l":
        $case = MB_CASE_LOWER;
        break;
      case "u":
        $case = MB_CASE_UPPER;
        break;
      default:
        $case = MB_CASE_TITLE;
    }

    if (!empty($st)) {
      $st = mb_convert_case($st, $case);
    }

    return $st;
  }


  /**
   * Escapes all quotes (single and double) from a given string.
   *
   * ```php
   * X::dump(\bbn\Str::escapeDquotes('the "Gay Pride" is is Putin\'s favorite'));
   * // (string) "the \"Gay Pride\" is is Putin\'s favorite"
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escapeAllQuotes($st): string
  {
    return addcslashes(self::cast($st), "'\"\\\r\n\t");
  }


  /**
   * Escapes the string in double quotes.
   *
   * ```php
   * X::dump(\bbn\Str::escapeDquotes('this is the house "Mary"'));
   * // (string) "this is the house \"Mary\""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escapeDquotes($st): string
  {
    return addcslashes(self::cast($st), "\"\\\r\n\t");
  }


  /**
   * Synonym of "escape_dquotes".
   *
   * ```php
   * X::dump(\bbn\Str::escapeDquote('this is the house "Mary"'));
   * // (string) "this is the house \"Mary\""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escapeDquote($st): string
  {
    return self::escapeDquotes($st);
  }


  /**
   * Synonym of "escape_dquotes".
   *
   * ```php
   * X::dump(\bbn\Str::escapeQuote('this is the house "Mary"'));
   * // (string) "this is the house \"Mary\""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escapeQuote($st): string
  {
    return self::escapeDquotes($st);
  }


  /**
   * Synonym of "escape_dquotes".
   *
   * ```php
   * X::dump(\bbn\Str::escapeQuotes('this is the house "Mary"'));
   * // (string) "this is the house \"Mary\""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escapeQuotes($st): string
  {
    return self::escapeDquotes($st);
  }


  /**
   * Escapes the string in quotes.
   *
   * ```php
   * X::dump(\bbn\Str::escapeSquotes("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escapeSquotes($st): string
  {
    return addcslashes(self::cast($st), "'\\\r\n\t");
  }


  /**
   * Unescapes the string in quotes.
   *
   * ```php
   * X::dump(\bbn\Str::escapeSquotes("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function unescapeSquotes($st): string
  {
    return stripcslashes(self::cast($st));
  }


  /**
   * Unescapes the string in quotes.
   *
   * ```php
   * X::dump(\bbn\Str::escapeSquotes("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function unescapeSquote($st): string
  {
    return self::unescapeSquotes($st);
  }


  /**
   * Synonym of "escape_squotes".
   *
   * ```php
   * X::dump(\bbn\Str::escape("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape($st): string
  {
    return self::escapeSquotes($st);
  }


  /**
   * Synonym of "escape_squotes".
   *
   * ```php
   * X::dump(\bbn\Str::escapeApo("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escapeApo($st): string
  {
    return self::escapeSquotes($st);
  }


  /**
   * Synonym of "escape_squotes".
   *
   * ```php
   * X::dump(\bbn\Str::escapeSquote("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escapeSquote($st): string
  {
    return self::escapeSquotes($st);
  }


  /**
   * Returns an expunged string of several types of character(s) depending on the configuration.
   *
   * ```php
   * $test="this      is
   * cold";
   *
   * X::dump(\bbn\Str::clean($test));
   * // (string)  "this is\n cold"
   *
   * $test1="this is
   *
   *
   * cold";
   *
   * X::dump(\bbn\Str::clean($test1,'2nl'));
   * /* (string)
   * "this is
   *  cold"
   *
   * X::dump(\bbn\Str::clean($test1,'html'));
   * // (string)  "this is cold"
   *
   * X::dump(\bbn\Str::clean('$x = 9993','code'));
   * // (string)  "$x=9993"
   * ```
   *
   * @param mixed  $st   The item to be.
   * @param string $mode A selection of configuration: "all" (default), "2nl", "html", "code".
   * @return string
   */
  public static function clean($st, $mode='all'): string
  {
    //TODO: How this should work if it's an array!
    if (\is_array($st)) {
      reset($st);
      $i = \count($st);
      if (trim($st[0]) == '') {
        array_splice($st,0,1);
        $i--;
      }

      if ($i > 0) {
        if (trim($st[$i - 1]) === '') {
          array_splice($st, $i - 1, 1);
          $i--;
        }
      }

      return $st;
    }
    else{
      $st = self::cast($st);
      if ($mode == 'all') {
        $st = mb_ereg_replace("\n",'\n',$st);
        $st = mb_ereg_replace("[\t\r]","",$st);
        $st = mb_ereg_replace('\s{2,}',' ',$st);
      }
      elseif ($mode == '2nl') {
        $st = mb_ereg_replace("[\r]","",$st);
        $st = mb_ereg_replace("(\s*\n){2,}","\n",$st);
      }
      elseif ($mode == 'html') {
        $st = mb_ereg_replace("[\t\r\n]",'',$st);
        $st = mb_ereg_replace('\s{2,}',' ',$st);
      }
      elseif ($mode == 'code') {
        $st    = mb_ereg_replace("!/\*.*?\*/!s",'',$st); // comment_pattern
        $st    = mb_ereg_replace("[\r\n]",'',$st);
        $st    = mb_ereg_replace("\t"," ",$st);
        $chars = [';','=','+','-','\(','\)','\{','\}','\[','\]',',',':'];
        foreach ($chars as $char){
          while (mb_strpos($st,$char.' ') !== false){
            $st = mb_ereg_replace($char.' ',$char,$st);
          }

          while (mb_strpos($st,' '.$char) !== false){
            $st = mb_ereg_replace(' '.$char,$char,$st);
          }
        }

        $st = mb_ereg_replace('<\?p'.'hp','<?p'.'hp ',$st);
        $st = mb_ereg_replace('\?'.'>','?'.'> ',$st);
        $st = mb_ereg_replace('\s{2,}',' ',$st);
      }

      return trim($st);
    }
  }


  /**
   * Cuts a string (HTML and PHP tags stripped) to maximum length inserted.
   *
   * ```php
   * X::dump(\bbn\Str::cut("<!-- HTML Document --> Example text", 7));
   * // (string) "Example..."
   * ```
   *
   * @param string $st  The string to be cut.
   * @param int    $max The maximum string length.
   * @return string
   */
  public static function cut(string $st, int $max = 15): string
  {
    $st = self::cast($st);
    $st = mb_ereg_replace('&nbsp;',' ',$st);
    $st = mb_ereg_replace('\n',' ',$st);
    $st = strip_tags($st);
    $st = html_entity_decode($st, ENT_QUOTES, 'UTF-8');
    $st = self::clean($st);
    if (mb_strlen($st) >= $max) {
      // Chars forbidden to finish with a string
      $chars = [' ', '.'];
      // Final chars
      $ends = [];
      // The string gets cut at $max
      $st = mb_substr($st, 0, $max);
      while (\in_array(substr($st, -1), $chars)){
        $st = substr($st, 0, -1);
      }

      $st .= '...';
    }

    return $st;
  }


  /**
   * Strip special characters except the below:
   * - ~ , ; [ ] ( ) .
   * And removes more that two trailing periods
   *
   * @param string $st
   * @return string
   */
  public static function sanitize(string $st): string
  {
    $file = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $st);
    // Removes any run of periods (thanks falstro!)
    $file = mb_ereg_replace("([\.]{2,})", '', $file);
    return $file;
  }


  /**
   * Returns a cross-platform filename for the file.
   *
   * ```php
   * X::dump(\bbn\Str::encodeFilename('test file/,1', 15, 'txt'));
   * // (string) "test_file_1.txt"
   * ```
   *
   * @param string $st        The name as string.
   * @param int    $maxlength The maximum filename length (without extension), default: "50".
   * @param string $extension The extension of the file.
   * @param bool   $is_path   Tells if the slashes (/) are authorized in the string
   * @return string
   */
  public static function encodeFilename($st, $maxlength = 50, $extension = null, $is_path = false): string
  {
    $st      = self::removeAccents(self::cast($st));
    $allowed = '~\-_.\(\[\)\]';

    // Arguments order doesn't matter
    $args = \func_get_args();
    foreach ($args as $i => $a){
      if ($i > 0) {
        if (\is_string($a)) {
          $extension = $a;
        }
        elseif (\is_int($a)) {
          $maxlength = $a;
        }
        elseif (\is_bool($a)) {
          $is_path = $a;
        }
      }
    }

    if (!\is_int($maxlength)) {
      $maxlength = mb_strlen($st);
    }

    if ($is_path) {
      $allowed .= '/';
    }

    if ($extension
        && (self::fileExt($st) === self::changeCase($extension, 'lower'))
    ) {
      $st = substr($st, 0, -(\strlen($extension) + 1));
    }
    elseif ($extension = self::fileExt($st)) {
      $st = substr($st, 0, -(\strlen($extension) + 1));
    }

    // Replace non allowed character with single space
    $st = mb_ereg_replace("([^\w\d".$allowed.".])", ' ', $st);

    // Replace two or more spaces to one space
    $st = mb_ereg_replace("\s{2,}", ' ', $st);

    // Replace single spaces to under score
    $st = mb_ereg_replace("\s", '_', $st);

    // Remove the . character
    $st = mb_ereg_replace("\.", '', $st);
    ;
    $res = mb_substr($st, 0, $maxlength);
    if ($extension) {
      $res .= '.' . $extension;
    }

    return $res;
  }


  /**
   * Returns a corrected string for database naming.
   *
   * ```php
   * X::dump(\bbn\Str::encodeDbname('my.database_name ? test  :,; !plus'));
   * // (string) "my_database_name_test_plus"
   * ```
   *
   * @param string $st        The name as string.
   * @param int    $maxlength The maximum length, default: "50".
   * @return string
   */
  public static function encodeDbname($st, $maxlength = 50): string
  {
    $st  = self::removeAccents(self::cast($st));
    $res = '';

    if (!\is_int($maxlength)) {
      $maxlength = mb_strlen($st);
    }

    for ($i = 0; $i < $maxlength; $i++){
      if (mb_ereg_match('[A-z0-9]', $substr = mb_substr($st, $i, 1))) {
        $res .= $substr;
      }
      elseif ((mb_strlen($res) > 0)
          && (mb_substr($res, -1) != '_')
          && ($i < ( mb_strlen($st) - 1 ))
      ) {
        $res .= '_';
      }
    }

    if (substr($res, -1) === '_') {
      $res = substr($res, 0, -1);
    }

    return $res;
  }


  /**
   * Returns the file extension.
   *
   * ```php
   * // (string) "txt"
   * X::dump(Str::fileExt("/test/test.txt"));
   *
   * // (array) [ "test", "txt", ]
   * X::dump(\bbn\Str::fileExt('/home/user/Desktop/test.txt', true));
   * ```
   *
   * @param string $file The file path.
   * @param bool   $ar   If "true" also returns the file path, default: "false".
   * @return string|array
   */
  public static function fileExt(string $file, bool $ar = false)
  {
    $file = self::cast($file);
    if (mb_strrpos($file, '/') !== false) {
      $file = substr($file, mb_strrpos($file, '/') + 1);
    }

    if (mb_strpos($file, '.') !== false) {
      $p   = mb_strrpos($file, '.');
      $f   = mb_substr($file, 0, $p);
      $ext = mb_convert_case(mb_substr($file, $p + 1), MB_CASE_LOWER);
      return $ar ? [$f, $ext] : $ext;
    }

    return $ar ? [$file, ''] : '';
  }


  /**
   * Returns a random password.
   *
   * ```php
   * X::dump(\bbn\Str::genpwd());
   * // (string) "khc9P871w"
   * X::dump(\bbn\Str::genpwd(6, 4));
   * // (string) "dDEtxY"
   * ```
   *
   * @param int $int_max Maximum password characters, default: "12".
   * @param int $int_min Minimum password characters, default: "6".
   * @return string
   */
  public static function genpwd(int $int_max = null, int $int_min = null): string
  {
    if (is_null($int_max) && is_null($int_min)) {
      $int_max = 12;
      $int_min = 8;
    }
    elseif (is_null($int_min)) {
      $int_min = $int_max;
    }
    elseif (is_null($int_max)) {
      $int_max = $int_min;
    }

    mt_srand();
    $len = ($int_min > 0) && ($int_min < $int_max) ? random_int($int_min, $int_max) : $int_max;
    $mdp = '';
    for($i = 0; $i < $len; $i++){
      // First character is a letter
      $type = $i === 0 ? random_int(2, 3) : random_int(1, 3);
      switch ($type){
        case 1:
          $mdp .= random_int(0,9);
          break;
        case 2:
          $mdp .= \chr(random_int(65,90));
          break;
        case 3:
          $mdp .= \chr(random_int(97,122));
          break;
      }
    }

    return $mdp;
  }


  /**
   * Checks if the string is a json string.
   *
   * ```php
   * X::dump(\bbn\Str::isJson('{"firstName": "John", "lastName": "Smith", "age": 25}'));
   * // (bool) true
   * ```
   *
   * @param string $st The string.
   * @return bool
   */
  public static function isJson($st)
  {
    if (\is_string($st) && !empty($st)
        && ( (substr($st, 0, 1) === '{') || (substr($st, 0, 1) === '[') )
    ) {
      json_decode($st);
      return (json_last_error() == JSON_ERROR_NONE);
    }

    return false;
  }


  /**
   * Checks if the item is a number.
   * Can take as many arguments and will return false if one of them is not a number.
   *
   * ```php
   * X::dump(\bbn\Str::isNumber([1, 2]));
   * // (bool) false
   * X::dump(\bbn\Str::isNumber(150);
   * // (bool) 1
   * X::dump(\bbn\Str::isNumber('150'));
   * // (bool)  1
   * X::dump(\bbn\Str::isNumber(1.5);
   * // (bool) 1
   * ```
   *
   * @param mixed $st The item to be tested.
   * @return bool
   */
  public static function isNumber(): bool
  {
    if (empty($args = \func_get_args())) {
      return false;
    }

    foreach ($args as $a){
      if (\is_string($a)) {
        if (!preg_match('/^-?(?:\d+|\d*\.\d+)$/', $a)) {
          return false;
        }
      }
      elseif (!\is_int($a) && !\is_float($a)) {
        return false;
      }
    }

    return true;
  }


  /**
   * Checks if the item is a integer.
   * Can take as many arguments and will return false if one of them is not an integer or the string of an integer.
   *
   * ```php
   * X::dump(\bbn\Str::isInteger(13.2));
   * // (bool) false
   * X::dump(\bbn\Str::isInteger(14));
   * // (bool) true
   * X::dump(\bbn\Str::isInteger('14'));
   * // (bool) true
   * ```
   *
   * @param mixed $st The item to be tested.
   * @return bool
   */
  public static function isInteger(): bool
  {
    $args = \func_get_args();
    foreach ($args as $a){
      if (\is_string($a) || (abs($a) > PHP_INT_MAX)) {
        if (!preg_match('/^-?(\d+)$/', (string)$a)) {
          return false;
        }
      }
      elseif (!\is_int($a)) {
        return false;
      }
    }

    return true;
  }


  /**
   * Checks if ".. \\" or "../" is contained in the parameter and it will return false if true.
   *
   * ```php
   * X::dump(\bbn\Str::isCleanPath("/home/user/Images"));
   * // (bool) true
   * X::dump(\bbn\Str::isCleanPath("../home/user/Images"));
   * // (bool) false
   * X::dump(\bbn\Str::isCleanPath("..\\home\user\Images"));
   * // (bool) false
   * ```
   *
   * @param mixed $st The item to be tested.
   * @return bool
   */
  public static function isCleanPath(): bool
  {
    $args = \func_get_args();
    foreach ($args as $a){
      if (\is_string($a)) {
        if ((strpos($a, '../') !== false) || (strpos($a, '..\\') !== false)) {
          return false;
        }
      }
      else {
        return false;
      }
    }

    return true;
  }


  /**
   * Checks if the item is a decimal.
   * Can take many arguments and it will return false if one of them is not a decimal or the string of a decimal (float).
   *
   * ```php
   * X::dump(\bbn\Str::isDecimal(13.2));
   * // (bool) true
   * X::dump(\bbn\Str::isDecimal('13.2'));
   * // (bool) true
   * X::dump(\bbn\Str::isDecimal(14));
   * // (bool) false
   * ```
   *
   * @param mixed $st The item to be tested.
   * @return bool
   */
  public static function isDecimal(): bool
  {
    $args = \func_get_args();
    foreach ($args as $a){
      if (\is_string($a)) {
        if (!preg_match('/^-?(\d*\.\d+)$/', $a)) {
          return false;
        }
      }
      elseif (!\is_float($a)) {
        return false;
      }
    }

    return true;
  }


  /**
   * Checks if the string is a valid UID string.
   *
   * @param string $st
   * @return boolean
   */
  public static function isUid($st): bool
  {
    return \is_string($st) && (\strlen($st) === 32) && ctype_xdigit($st);// && !mb_detect_encoding($st);
  }


  /**
   * Checks if the string is a valid binary UID string.
   *
   * @param string $st
   * @return boolean
   */
  public static function isBuid($st): bool
  {
    if (\is_string($st) && (\strlen($st) === 16) && !ctype_print($st) && !ctype_space($st)) {
      $enc = mb_detect_encoding($st, ['8bit', 'UTF-8']);
      if (!$enc || ($enc === '8bit')) {
        return preg_match('~[^\x20-\x7E\t\r\n]~', $st) > 0;
      }
    }

    return false;
  }


  /**
   * Checks if the string is the correct type of e-mail address.
   *
   * ```php
   * X::dump(\bbn\Str::isEmail('test@email.com'));
   * // (bool) true
   * X::dump(\bbn\Str::isEmail('test@email'));
   * // (bool) false
   * X::dump(\bbn\Str::isEmail('test@.com'));
   * // (bool) false
   * X::dump(\bbn\Str::isEmail('testemail.com'));
   * // (bool) false
   * ```
   *
   * @param string $email E-mail address.
   * @return bool
   */
  public static function isEmail($email): bool
  {
    if (function_exists('filter_var')) {
      return filter_var($email,FILTER_VALIDATE_EMAIL) ? true : false;
    }
    else
    {
      $isValid = true;
      $atIndex = mb_strrpos($email, "@");
      if (\is_bool($atIndex) && !$atIndex) {
        $isValid = false;
      }
      else
      {
        $domain    = mb_substr($email, $atIndex + 1);
        $local     = mb_substr($email, 0, $atIndex);
        $localLen  = mb_strlen($local);
        $domainLen = mb_strlen($domain);
        //  local part length exceeded
        if ($localLen < 1 || $localLen > 64) {
          $isValid = false;
        }
        //  domain part length exceeded
        elseif ($domainLen < 1 || $domainLen > 255) {
          $isValid = false;
        }
        // local part starts or ends with '.'
        elseif ($local[0] == '.' || $local[$localLen - 1] == '.') {
          $isValid = false;
        }
        // local part has two consecutive dots
        elseif (mb_ereg_match('\\.\\.', $local)) {
          $isValid = false;
        }
        // character not valid in domain part
        elseif (!mb_ereg_match('^[A-Za-z0-9\\-\\.]+$', $domain)) {
          $isValid = false;
        }
        //  domain part has two consecutive dots
        elseif (mb_ereg_match('\\.\\.', $domain)) {
          $isValid = false;
        }
        //  character not valid in local part unless
        elseif (!mb_ereg_match(
          '^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$'
          ,str_replace("\\\\","",$local)
        )
        ) {
          // local part is quoted
          if (!mb_ereg_match('^"(\\\\"|[^"])+"$',str_replace("\\\\","",$local))) {
            $isValid = false;
          }
        }
      }

      return $isValid;
    }
  }


  /**
   * Checks if the argument is a valid URL string.
   *
   * ```php
   * X::dump(\bbn\Str::isUrl("http://bbn.so"));
   * // (string) "https://bbn.so"
   *
   * X::dump(\bbn\Str::isUrl("bbn.so"));
   * // (bool) false
   * ```
   *
   * @param string $st The string to perform
   * @return string|false
   */
  public static function isUrl($st)
  {
    return filter_var($st, FILTER_VALIDATE_URL);
  }


  /**
   * Checks if the argument is a valid domain name.
   *
   * ```php
   * X::dump(\bbn\Str::isDomain("http://bbn.so"));
   * // (string) false
   *
   * X::dump(\bbn\Str::isDomain("bbn.so"));
   * // (bool) true
   * ```
   *
   * @param string $st The string to perform
   * @return bool
   */
  public static function isDomain($st): bool
  {
    return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $st) //valid chars check
      && preg_match("/^.{1,253}$/", $st) //overall length check
      && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $st)   ); //length of each label
  }


  public static function isIp($st): bool
  {
    $valid = filter_var($st, FILTER_VALIDATE_IP);
    return $valid;
  }


  /**
   * Checks if the argument is in a valid SQL date format.
   *
   * ```php
   * X::dump(\bbn\Str::isDateSql("1999-12-05 11:10:22"));
   * // (bool) true
   * X::dump(\bbn\Str::isDateSql("1999-12-05"));
   * // (bool) true
   * X::dump(\bbn\Str::isDateSql("19-12-1999"));
   * // (bool) false
   * ```
   *
   * @param string $st
   * @return bool
   */
  public static function isDateSql($st): bool
  {
    foreach (func_get_args() as $a) {
      if (!Date::validateSQL($a)) {
        return false;
      }
    }

    return true;
  }


  /**
   * If it looks like an int or float type, the string variable is converted into the correct type.
   *
   * ```php
   * X::dump(\bbn\Str::correctTypes(1230));
   * // (int) 1230
   * X::dump(\bbn\Str::correctTypes(12.30));
   * // (float) 12.3
   * X::dump(\bbn\Str::correctTypes("12.3"));
   * // (float) 12.3
   * X::dump(\bbn\Str::correctTypes([1230]));
   * // (int) [1230]
   * ```
   *
   * @param mixed $st
   * @return mixed
   */
  public static function correctTypes($st)
  {
    if (\is_string($st)) {
      if (self::isBuid($st)) {
        $st = bin2hex($st);
      }
      else{
        if (self::isJson($st)) {
          if (strpos($st, '": ') && ($json = json_decode($st))) {
            return json_encode($json);
          }

          return $st;
        }

        $st = trim(trim($st, " "), "\t");
        if (self::isInteger($st)
            && ((substr((string)$st, 0, 1) !== '0') || ($st === '0'))
        ) {
          $tmp = (int)$st;
          if (($tmp < PHP_INT_MAX) && ($tmp > -PHP_INT_MAX)) {
            return $tmp;
          }
        }
        // If it is a decimal, not starting or ending with a zero
        elseif (self::isDecimal($st)) {
          return (float)$st;
        }
      }
    }
    elseif (\is_array($st)) {
      foreach ($st as $k => $v) {
        $st[$k] = self::correctTypes($v);
      }
    }
    elseif (\is_object($st)) {
      $vs = get_object_vars($st);
      foreach ($vs as $k => $v) {
        $st->$k = self::correctTypes($v);
      }
    }

    return $st;
  }


  /**
   * Returns an array containing any of the various components of the URL that are present.
   *
   * ```php
   * X::hdump(\bbn\Str::parseUrl('http://localhost/phpmyadmin/?db=test&table=users&server=1&target=&token=e45a102c5672b2b4fe84ae75d9148981');
   * /* (array)
   * [
   *     'scheme' => 'http',
   *     'host' => 'localhost',
   *     'path' => '/phpmyadmin/',
   *     'query' => 'db=test&table=users&server=1&target=&token=e45a102c5672b2b4fe84ae75d9148981',
   *     'url' => 'http://localhost/phpmyadmin/',
   *     'params' => [
   *         'db' => 'test',
   *         'table' => 'users',
   *         'server' => '1',
   *         'target' => '',
   *         'token' => 'e45a102c5672b2b4fe84ae75d9148981',
   *     ],
   * ]
   * ```
   *
   * @param string $url The url.
   * @return array
   */
  public static function parseUrl($url): array
  {
    $url = self::cast($url);
    $r   = X::mergeArrays(parse_url($url), ['url' => $url,'query' => '','params' => []]);
    if (strpos($url,'?') > 0) {
      $p          = explode('?',$url);
      $r['url']   = $p[0];
      $r['query'] = $p[1];
      $ps         = explode('&',$r['query']);
      foreach ($ps as $p){
        $px                  = explode('=',$p);
        $r['params'][$px[0]] = $px[1];
      }
    }

    return $r;
  }


  /**
   * Replaces backslash with slash in a path string. Forbids the use of ../
   *
   * ```php
   * X::dump(\bbn\Str::parsePath('\home\user\Desktop'));
   * // (string) "/home/user/Desktop"
   * ```
   *
   * @param string  $path         The path.
   * @param boolean $allow_parent If true ../ is allowed in the path (and will become normalized).
   * @return string
   */
  public static function parsePath(string $path, $allow_parent = false): string
  {
    $path = str_replace('\\', '/', \strval($path));
    $path = str_replace('/./', '/', \strval($path));
    while (strpos($path, '//') !== false){
      $path = str_replace('//', '/', $path);
    }

    if (strpos($path, '../') !== false) {
      if (!$allow_parent) {
        return '';
      }

      $bits       = array_reverse(explode('/', $path));
      $path       = '';
      $num_parent = 0;
      foreach ($bits as $i => $b){
        if ($b === '..') {
          $num_parent++;
        }
        elseif ($b !== '.') {
          if ($num_parent) {
            $num_parent--;
          }
          else{
            $path = empty($path) ? $b : $b.'/'.$path;
          }
        }
      }
    }

    return $path;
  }


  /**
   * Replaces accented characters with their character without the accent.
   *
   * ```php
   * X::dump(\bbn\Str::removeAccents("TÃ¨st FÃ¬lÃ¨ Ã²Ã¨Ã Ã¹è"));
   * // (string) "TA¨st  FA¬lA¨  A²A¨A A¹e"
   * ```
   *
   * @param string $st The string.
   * @return string
   */
  public static function removeAccents($st): string
  {
    $st      = trim(\mb_ereg_replace('&(.)(tilde|circ|grave|acute|uml|ring|oelig);', '\\1', self::cast($st)));
    $search  = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u,ą,ń,ł,ź,ę,À,Á,Â,Ã,Ä,Ç,È,É,Ê,Ë,Ì,Í,Î,Ï,Ñ,Ò,Ó,Ô,Õ,Ö,Ù,Ú,Û,Ü,Ý,Ł,Ś");
    $replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u,a,n,l,z,e,A,A,A,A,A,C,E,E,E,E,I,I,I,I,N,O,O,O,O,O,U,U,U,U,Y,L,S");
    foreach ($search as $i => $s) {
      $st = \mb_ereg_replace($s, $replace[$i], $st);
    }

    return $st;
  }


  /**
   * Checks if a string complies with SQL naming convention.
   *
   * ```php
   * X::dump(\bbn\Str::checkName("Paul"));
   * // (bool) true
   * X::dump(\bbn\Str::checkName("Pa ul"));
   * // (bool) false
   * ```
   *
   * @return bool
   */
  public static function checkName(): bool
  {
    $args = \func_get_args();
    // Each argument must be a string starting with a letter, and having only one character made of letters, numbers and underscores
    foreach ($args as $a) {
      if (\is_array($a)) {
        foreach ($a as $b) {
          if (!self::checkName($b)) {
            return false;
          }
        }
      }

      if (!\is_string($a)) {
        return false;
      }

      return \preg_match('/^[A-z]{1}[A-z0-9_]*$/', $a);
    }

    return true;
  }


  /**
   * Checks if a string doesn't contain a filesystem path.
   *
   * ```php
   * X::dump(\bbn\Str::checkFilename("Paul"));
   * // (bool) true
   * X::dump(\bbn\Str::checkFilename("Paul/"));
   * // (bool) false
   * ```
   *
   * @return bool
   */
  public static function checkFilename(): bool
  {
    $args = \func_get_args();
    // Each argument must be a string starting with a letter, and having than one character made of letters, numbers and underscores
    foreach ($args as $a){
      if (($a === '..') || !\is_string($a) || (strpos($a, '/') !== false) || (strpos($a, '\\') !== false)) {
        return false;
      }
    }

    return true;
  }


  /**
   * Checks if a string doesn't contain a filesystem path.
   *
   * ```php
   * X::dump(\bbn\Str::checkFilename("Paul"));
   * // (bool) true
   * X::dump(\bbn\Str::checkFilename("Paul/"));
   * // (bool) false
   * ```
   *
   * @return bool
   */
  public static function checkPath(): bool
  {
    if ($args = \func_get_args()) {
      // Each argument must be a string starting with a letter, and having than one character made of letters, numbers and underscores
      foreach ($args as $a){
        $bits = X::split($a, DIRECTORY_SEPARATOR);
        foreach ($bits as $b){
          if (!self::checkFilename($b)) {
            return false;
          }
        }
      }

      return true;
    }

    return false;
  }


  /**
   * Checks if a string complies with SQL naming convention.
   * Returns "true" if slash or backslash are present.
   *
   * ```php
   * X::dump(\bbn\Str::hasSlash("Paul"));
   * // (bool) false
   * X::dump(\bbn\Str::hasSlash("Paul/");
   * // (bool) 1
   * X::dump(\bbn\Str::hasSlash("Paul\\");
   * // (bool) 1
   * ```
   *
   * @return bool
   */
  public static function hasSlash(): bool
  {
    $args = \func_get_args();
    // Each argument must be a string starting with a letter, and having than one character made of letters, numbers and underscores
    foreach ($args as $a){
      if ((strpos($a, '/') !== false) || (strpos($a, '\\') !== false)) {
        return true;
      }
    }

    return false;
  }


  /**
   * Extracts all digits from a string.
   *
   * ```php
   * X::dump(\bbn\Str::getNumbers("test 13 example 24"));
   * // (string) 1324
   * ```
   *
   * @param string $st The string.
   * @return string
   */
  public static function getNumbers($st): string
  {
    return preg_replace("/[^0-9]/", '', self::cast($st));
  }


  /**
   * Returns the argumented value, replacing not standard objects (not stdClass) by their class name.
   *
   * ```php
   * $myObj = new stdClass();
   * $myObj->myProp1 = 23;
   * $myObj->myProp2 = "world";
   * $myObj->myProp3 = [1, 5, 6];
   *
   * $user = \bbn\User::getInstance();
   *
   * $myArray = [
   *  'user' => $user,
   *  'obj' => $myObj,
   *  'val' => 23,
   *  'text' => "Hello!"
   * ];
   *
   * X::hdump(\bbn\Str::makeReadable($user));
   * // (string) "appui/user"
   *
   * X::hdump(\bbn\Str::makeReadable($myArray));
   * /* (array)
   * [
   *   "user" => "appui\\user",
   *   "obj" => [
   *             "myProp1" => 23,
   *             "myProp2" => "world",
   *             "myProp3" => [1, 5, 6,],
   *       ],
   *   "val" => 23,
   *   "text" => "Hello!",
   * ]
   * ```
   *
   * @param mixed $o The item.
   * @return array
   */
  public static function makeReadable($o)
  {
    $is_array = false;
    if (\is_object($o)) {
      $class = \get_class($o);
      if ($class === 'stdClass') {
        $is_array = 1;
      }
      else{
        return $class;
      }
    }

    if (\is_array($o) || $is_array) {
      $r = [];
      foreach ($o as $k => $v){
        $r[$k] = self::makeReadable($v);
      }

      return $r;
    }

    return $o;
  }


  /**
   * Returns a variable in a mode that is directly usable by PHP.
   *
   * ```php
   * $myObj = new stdClass();
   * $myObj->myProp1 = 23;
   * $myObj->myProp2 = "world";
   * $myObj->myProp3 = [1, 5, 6];
   * $myObj->myProp4 ="";
   *
   * X::hdump(\bbn\Str::export($myObj,true));
   * /*(string)
   * "{
   *      "myProp1"  =>  23,
   *      "myProp2"  =>  "world",
   *      "myProp3"  =>  [ 1, 5, 6, ],
   * }"
   * ```
   *
   * @param mixed $o            The item to be.
   * @param bool  $remove_empty Default: "false".
   * @param int   $lev          Default: "1".
   * @return string
   */
  public static function export($o, $remove_empty=false, $lev=1): string
  {
    $st    = '';
    $space = '    ';
    if (\is_object($o) && ($cls = \get_class($o)) && (strpos($cls, 'stdClass') === false)) {
      $st .= "Object ".$cls;
      /*
      $o = array_filter((array)$o, function($k) use ($cls){
        if ( strpos($k, '*') === 0 ){
          return false;
        }
        if ( strpos($k, $cls) === 0 ){
          return false;
        }
        return true;
      }, ARRAY_FILTER_USE_KEY);
      */
    }
    elseif (\is_object($o) || \is_array($o)) {
      $is_object = \is_object($o);
      $is_array  = !$is_object && \is_array($o);
      $is_assoc  = $is_object || ($is_array && X::isAssoc($o));
      $st       .= $is_assoc ? '{' : '[';
      $st       .= PHP_EOL;
      foreach ($o as $k => $v){
        if ($remove_empty && ( ( \is_string($v) && empty($v) ) || ( \is_array($v) && \count($v) === 0 ) )) {
          continue;
        }

        $st .= str_repeat($space, $lev);
        if ($is_assoc) {
          $st .= ( \is_string($k) ? '"'.self::escapeDquote($k).'"' : $k ). ': ';
        }

        if (\is_array($v)) {
          $st .= self::export($v, $remove_empty, $lev + 1);
        }
        elseif ($v === 0) {
          $st .= '0';
        }
        elseif (null === $v) {
          $st .= 'null';
        }
        elseif (\is_bool($v)) {
          $st .= $v === false ? 'false' : 'true';
        }
        elseif (\is_int($v) || \is_float($v)) {
          $st .= $v;
        }
        elseif (is_string($v)) {
          if (self::isBuid($v)) {
            $st .= '0x'.bin2hex($v);
          }
          elseif (!$remove_empty || !empty($v)) {
            $st .= '"'.self::escapeDquote($v).'"';
          }
        }
        else {
          try{
            $cls = get_class($v);
          }
          catch (\Exception $e){
            $st .= '"Unknown"';
          }

          if ($cls) {
            if ($cls === 'stdClass') {
              $st .= self::export($v, $remove_empty, $lev + 1);
            }
            else{
              $st .= 'Object '.$cls;
            }
          }
        }

        $st .= ','.PHP_EOL;
      }

      $st .= str_repeat($space, $lev - 1);
      $st .= $is_assoc ? '}' : ']';
      //$st .= \is_object($o) ? '}' : ']';
    }

    return $st;
  }


  /**
   * Replaces part of a string. If the part is not found, the method returns the string without change.
   *
   * ```php
   * X::hdump(\bbn\Str::replaceOnce("cold","hot", "Today there is cold"));
   * // (string)  "Today there is hot"
   * X::hdump(\bbn\Str::replaceOnce("rain","hot", "Today there is cold"));
   * // (string)  "Today there is cold"
   * ```
   *
   * @param string $search  The string to search
   * @param string $replace The string to replace
   * @param string $subject The string into search
   * @return string
   */
  public static function replaceOnce($search, $replace, $subject): string
  {
    $pos = strpos($subject, $search);
    if ($pos !== false) {
      return substr_replace($subject, $replace, $pos, \strlen($search));
    }

    return $subject;
  }


  /**
   * Removes the comments.
   *
   * ```php
   *  var_dump(\bbn\Str::removeComments("<!--this is a comment-->"));
   *  // (string) ""
   * ```
   *
   * @param string $st
   * @return string
   */
  public static function removeComments(string $st): string
  {
    $pattern = '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/';
    return preg_replace($pattern, '', $st);
  }


  /**
  * Converts the bytes to another unit form.
  *
  * @param int                                                           $bytes The bytes
  * @param string The unit you want to convert ('B', 'K', 'M', 'G', 'T')
  * @parma boolean $stop
  * @return string
  */
  public static function saySize($bytes, $unit = 'B', $stop = false): string
  {
    // pretty printer for byte values
    //
    $i     = 0;
    $units = ['', 'K', 'M', 'G', 'T'];
    while ($stop || ($bytes > 2000)){
      $i++;
      $bytes /= 1024;
      if ($stop === $units[$i]) {
        break;
      }
    }

    $st = $unit === 'B' ? "%d %s" : "%5.2f %s";
    return sprintf($st.$unit, $bytes, $units[$i]);
  }


  /**
   * @param $size
   * @param string $unit_orig
   * @param string $unit_dest
   * @return string
   */
  public static function convertSize($size, $unit_orig = 'B', $unit_dest = 'MB')
  {
    if (strlen($unit_orig) <= 1) {
      $unit_orig .= 'B';
    }

    if (strlen($unit_dest) <= 1) {
      $unit_dest .= 'B';
    }

    $base   = log($size) / log(1024);
    $suffix = array("", "KB", "MB", "GB", "TB");
    $f_base = floor($base);
    return round(pow(1024, $base - floor($base)), 1) . $suffix[$f_base];
  }


  /**
   * Checks whether a JSON string is valid or not. If $return_error is set to true, the error will be returned.
   *
   * @param string $json
   * @param bool   $return_error
   * @return bool|string
   */
  public static function checkJson(string $json, bool $return_error = false)
  {
    json_decode($json);
    $error = json_last_error();
    if ($error === JSON_ERROR_NONE) {
      return true;
    }

    if (!$return_error) {
      return false;
    }

    switch ($error) {
      case JSON_ERROR_DEPTH:
        return X::_('Maximum stack depth exceeded');
      case JSON_ERROR_STATE_MISMATCH:
        return X::_('State mismatch (invalid or malformed JSON)');
      case JSON_ERROR_CTRL_CHAR:
        return X::_('Unexpected control character found');
      case JSON_ERROR_SYNTAX:
        return X::_('Syntax error, malformed JSON');
      case JSON_ERROR_UTF8:
        return X::_('Malformed UTF-8 characters, possibly incorrectly encoded');
      default:
        return X::_('Unknown error');
    }
  }


  public static function asVar(string $var, $quote = '"')
  {
    if (($quote !== "'") && ($quote !== '"')) {
      $quote = '"';
    }

    $st = $quote === "'" ? self::escapeSquotes($var) : self::escapeDquotes($var);
    return $quote.$st.$quote;
  }


  /**
   * Transforms a markdown string into HTML.
   *
   * @param string  $st          The markdown string
   * @param boolean $single_line If true the result will not contain paragraph or block element
   * @return string The HTML string
   */
  public static function markdown2html(string $st, bool $single_line = false): string
  {
    if (!self::$_markdownParser) {
      self::$_markdownParser = new \Parsedown();
    }

    return $single_line ? self::$_markdownParser->line($st) : self::$_markdownParser->text($st);
  }


  /**
   * Converts the given string to camel case.
   *
   * @param string $st
   * @param string $sep   A separator
   * @param bool   $first Capitalize first character if true
   * @return string
   */
  public static function toCamel(string $st, string $sep = '_', bool $first = false): string
  {
    $res = str_replace(' ', '', ucwords(str_replace($sep, ' ', $st)));
    if (!$first) {
        $res[0] = strtolower($res[0]);
    }

    return $res;
  }


  /**
   * Converts HTML to text replacing paragraphs and brs with new lines.
   *
   * @param string $st The HTML string
   * @return string
   */
  public static function html2text(string $st, bool $nl = true): string
  {
    $st = trim($st);
    if (empty($st)) {
      return '';
    }

    $filter = $nl ? ['p', 'br'] : [];
    $tmp    = strip_tags($st, $filter);
    if (empty($tmp)) {
      $config = array(
        'clean' => 'yes',
        'output-html' => 'yes',
      );
      $tidy   = tidy_parse_string($st, $config, 'utf8');
      $tidy->cleanRepair();
      $st = strip_tags((string)$tidy, $filter);
    }
    else {
      $st = $tmp;
    }

    if (empty($st)) {
      return '';
    }

    if (!$nl) {
      return $st;
    }

    $st = preg_replace("/<p[^>]*?>/i", "", $st);
    $st = str_ireplace("</p>", PHP_EOL.PHP_EOL, $st);
    $p  = '/<br[^>]*>/i';
    $r  = PHP_EOL;
    return trim(html_entity_decode(preg_replace($p, $r, $st)));
  }


  /**
   * Converts text to HTML replacing new lines with brs.
   *
   * @param string $st The text string
   * @return string
   */
  public static function text2html(string $st, bool $paragraph = true): string
  {
    if ($paragraph) {
      $bits = X::split($st, PHP_EOL.PHP_EOL);
      $st   = '<p>'.X::join($bits, '</p><p>').'</p>';
    }

    return str_replace(PHP_EOL, '<br>', $st);
  }


}
