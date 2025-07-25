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

use bbn\Compilers\Markdown;
use HTMLPurifier;
use HTMLPurifier_Config;

class Str
{

  /**
   * Markdown parser if needed only one instance is created.
   *
   * @var Markdown
   */
  private static $_markdownParser;


  /**
   * HTML purifier if needed only one instance is created.
   *
   * @var ?HTMLPurifier
   */
  private static $_htmlSanitizer = null;


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
    if (is_array($st) || is_object($st)) {
      return '';
    }

    if (is_string($st)) {
      return normalizer_normalize($st);
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
  public static function escapeAllQuotes($st, $as_html = false): string
  {
    return self::escapeDquotes(self::escapeSquotes($st, $as_html), $as_html);
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
  public static function escapeDquotes($st, $as_html = false): string
  {
    if ($as_html) {
      return str_replace('"', '&#34;', $st);
    }

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
  public static function escapeDquote($st, $as_html = false): string
  {
    return self::escapeDquotes($st, $as_html);
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
  public static function escapeQuote($st, $as_html = false): string
  {
    return self::escapeAllQuotes($st, $as_html);
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
  public static function escapeQuotes($st, $as_html = false): string
  {
    return self::escapeAllQuotes($st, $as_html);
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
  public static function escapeSquotes($st, $as_html = false): string
  {
    if ($as_html) {
      return str_replace("'", '&#39;', $st);
    }

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
  public static function unescapeSquotes($st, $as_html = false): string 
  {
    if ($as_html) {
      return str_replace('&#39;', "'", $st);
    }

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
  public static function unescapeSquote($st, $as_html = false): string
  {
    return self::unescapeSquotes($st, $as_html);
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
  public static function escape($st, $as_html = false): string
  {
    return self::escapeAllQuotes($st, $as_html);
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
  public static function escapeApo($st, $as_html = false): string
  {
    return self::escapeSquotes($st, $as_html);
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
  public static function escapeSquote($st, $as_html = false): string
  {
    return self::escapeSquotes($st, $as_html);
  }


  /**
   * Trims, and removes extra spaces (all more than one)
   *
   * ```php
   * X::dump(Str::cleanSpaces(" Hello     World\n\n\n  (bool)!    "));
   * // (string)  "Hello World (bool)!"
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function cleanSpaces(string $st): string
  {
    return trim(preg_replace('/\s+/', ' ', $st));
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
    $st = self::html2text($st);
    $st = mb_ereg_replace('\n', ' ', $st);
    $st = self::cleanSpaces($st);
    if (mb_strlen($st) >= $max) {
      // Chars forbidden to finish with a string
      $chars = [' ', '.'];
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
  public static function fileExt(string $file, bool $ar = false, bool $keepCase = false)
  {
    $file = self::cast($file);
    if (mb_strrpos($file, '/') !== false) {
      $file = substr($file, mb_strrpos($file, '/') + 1);
    }

    if (mb_strpos($file, '.') !== false) {
      $p   = mb_strrpos($file, '.');
      $ext = mb_substr($file, $p + 1);
      if (!$keepCase) {
        $ext = mb_convert_case($ext, MB_CASE_LOWER);
      }

      if (!$ar) {
        return $ext;
      }
 
      return [mb_substr($file, 0, $p), $ext];
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
  public static function genpwd(?int $int_max = null, ?int $int_min = null): string
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


  public static function isHTML($st)
  {
    if (\is_string($st) && !empty($st)) {
      return strip_tags($st) !== $st;
    }

    return false;
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
    if (!$st || !is_string($st)) {
      return false;
    }

    $st = trim($st);
    $first = substr($st, 0, 1);
    if (!in_array($first, ['{', '['])) {
      return false;
    }
    $last = substr($st, -1);
    if (($first === '[') && ($last !== ']')) {
      return false;
    }
    if (($first === '{') && ($last !== '}')) {
      return false;
    }

    try {
      json_decode($st);
      return (json_last_error() == JSON_ERROR_NONE);
    }
    catch (\Exception $e) {
      // If an exception is thrown, it is not a valid JSON
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

  public static function getNameFromIndex($num) {
    $numeric = $num % 26;
    $letter = chr(65 + $numeric);
    $num2 = intval($num / 26);
    if ($num2 > 0) {
        return self::getNameFromIndex($num2 - 1) . $letter;
    } else {
        return $letter;
    }
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
      if (!is_string($a) && !is_int($a) && !is_float($a)) {
        return false;
      }

      if (is_float($a)) {
        $a = (string)$a;
      }

      if (is_string($a)) {
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
   * ```php
   * Str::isUid('22e4f42122e4f42122e4f42122e4f421');
   * // (bool) true
   * $this->assertFalse(Str::isUid('22e4f42122e4f4212'));
   * // (bool) false
   * ```
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
      if (!mb_check_encoding($st, 'UTF-8')) {
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
    return (bool)filter_var($st, FILTER_VALIDATE_URL);
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


  /**
   * Checks if the argument is a valid ip address.
   *
   * ```php
   * X::dump(\bbn\Str::isIp('198.162.0.1'));
   * // (bool) true
   *
   * X::dump(\bbn\Str::isIp('29e4:4068:a401:f273:dcec:af8f:c8b3:c01c'));
   * // (bool) true
   *
   * X::dump(\bbn\Str::isIp('198.162'));
   * // (bool) false
   * ```
   *
   * @param $st
   * @return bool
   */
  public static function isIp($st): bool
  {
    return (bool)filter_var($st, FILTER_VALIDATE_IP);
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

        return normalizer_normalize($st);
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
   *
   *  X::dump(\bbn\Str::parsePath('..\home\user\Desktop'));
   * // (string) ""
   *
   * X::dump(\bbn\Str::parsePath('..\home\user\Desktop', true));
   * // (string) "home/user/Desktop"
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
   * // (string) TA¨st FA¬lA¨ A²A¨A A¹e"
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
    // Each argument must be a string made of letters, numbers and underscores
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

      return \preg_match('/^[A-z0-9_]*$/', $a);
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
    foreach ($args as $a){
      if (($a === '..' || $a === '.') || !\is_string($a) || (strpos($a, '/') !== false) || (strpos($a, '\\') !== false)) {
        return false;
      }
    }

    return true;
  }


  /**
   * Checks if every bit of a string doesn't contain a filesystem path.
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
      foreach ($args as $a){
        if (!is_string($a) || strpos($a, '/', -1) !== false || strpos($a, '\\', -1) !== false ) {
          return false;
        }

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
   * // (string) "1324"
   *
   * X::dump(\bbn\Str::getNumbers("test example"));
   * // (string) ""
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
   * @return array|string
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
   * @param int   $level          Default: "1".
   * @param int   $maxDepth          Default: "0".
   * @return string
   */
  public static function export($o, bool $remove_empty = false, int $maxDepth = 0, int $maxLength = 0, int $level = 1): string
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
      $num      = 0;
      foreach ($o as $k => $v){
        if ($maxLength && ($num >= $maxLength)) {
          $st .= str_repeat($space, $level);
          $st .= '...' . PHP_EOL;
          break;
        }
        if ($remove_empty && ( ( \is_string($v) && empty($v) ) || ( \is_array($v) && \count($v) === 0 ) )) {
          continue;
        }

        $st .= str_repeat($space, $level);
        if ($is_assoc) {
          $st .= ( \is_string($k) ? '"'.self::escapeDquote($k).'"' : $k ). ': ';
        }

        if (\is_array($v)) {
          if ($maxDepth && ($level >= $maxDepth)) {
            $st .= '[...]';
          }
          else{
            $st .= self::export($v, $remove_empty, $maxDepth, $maxLength, $level + 1);
          }
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
            $st .= '"' . self::escapeDquote($v) . '"';
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
              $st .= self::export($v, $remove_empty, $maxDepth, $maxLength, $level + 1);
            }
            else{
              $st .= 'Object '.$cls;
            }
          }
        }

        $st .= ','.PHP_EOL;
        $num++;
      }

      $st .= str_repeat($space, $level - 1);
      $st .= $is_assoc ? '}' : ']';
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
   *
   * var_dump(\bbn\Str::removeComments("// this is a comment"));
   *  // (string) ""
   *
   * var_dump(\bbn\Str::removeComments("/** this is a comment *\/"));
   *  // (string) ""
   * ```
   *
   * @param string $st
   * @return string
   */
  public static function removeComments(string $st): string
  {
    $pattern = '/(?=<!--)([\s\S]*?)-->|(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/';
    return trim(preg_replace($pattern, '', $st));
  }


  /**
   * Converts the bytes to another unit form.
   *
   * ```php
   *  var_dump(\bbn\Str::saySize(50000000000, 'G'));
   *  // (string) "46.57 G"
   *
   * var_dump(\bbn\Str::saySize(1048576, 'M', 0));
   *  // (string) "1 M"
   *
   * var_dump(\bbn\Str::saySize(1048576, 'T', 6));
   *  // (string) "0.000001 T"
   * ```
   *
   * @param int    $bytes     The bytes
   * @param string $unit
   * @param int    $percision
   * @return string
   * @throws \Exception
   */
  public static function saySize($bytes, $unit = 'B', $percision = 2): string
  {
    // pretty printer for byte values
    $i     = 0;
    $units = ['B', 'K', 'M', 'G', 'T'];

    if (!in_array(($unit = strtoupper($unit)), $units, true)) {
      throw new \Exception(X::_('Invalid provided unit'));
    }

    while (isset($units[$i]) && $unit !== $units[$i]){
      $i++;
      $bytes /= 1024;
    }

    $st = $unit === 'B' ? "%d %s" : "%0.{$percision}f %s";

    return sprintf($st, $bytes, $units[$i]);
  }


  /**
   * Converts size from one unit to another.
   *
   * ```php
   *  var_dump(\bbn\Str::convertSize(1, 'GB', 'B'));
   *  // (string) "1073741824B"
   *
   * var_dump(\bbn\Str::convertSize(1, 'TB', 'GB'));
   *  // (string) "1024GB"
   *
   * var_dump(\bbn\Str::convertSize(500000, 'MB', 'TB', 6));
   *  // (string) "0.47684TB"
   * ```
   *
   * @param int $size
   * @param string $unit_orig
   * @param string $unit_dest
   * @param int    $percision
   * @return string
   * @throws \Exception
   */
  public static function convertSize($size, $unit_orig = 'B', $unit_dest = 'MB', $percision = 0)
  {
    $unit_orig = strtoupper($unit_orig);
    $unit_dest = strtoupper($unit_dest);

    if (strlen($unit_orig) <= 1 && $unit_orig !== 'B') {
      $unit_orig .= 'B';
    }

    if (strlen($unit_dest) <= 1 && $unit_dest !== 'B') {
      $unit_dest .= 'B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    if (!in_array($unit_orig, $units)) {
      throw new \Exception(X::_("Invalid original unit"));
    }

    if (!in_array($unit_dest, $units)) {
      throw new \Exception(X::_("Invalid destination unit"));
    }

    $bytes      = $size;
    $orig_index = array_search($unit_orig, $units);
    $dest_index = array_search($unit_dest, $units);

    // If destination unit is smaller than the original then reverse the units array
    if ($dest_index < $orig_index) {
      $units      = array_reverse($units);
      $orig_index = array_search($unit_orig, $units);
      $dest_index = array_search($unit_dest, $units);
      $reversed   = true;
    }

    for ($i = $orig_index + 1; $i <= $dest_index; $i++) {
      if (isset($reversed)) {
        $bytes *= 1024;
      } else {
        $bytes /= 1024;
      }
    }

    return round($bytes, $percision) . $unit_dest;
  }


  /**
   * Checks whether a JSON string is valid or not. If $return_error is set to true, the error will be returned.
   *
   * ```php
   *  var_dump(\bbn\Str::checkJson("{"foo":"bar"}"));
   *  // (bool) true
   *
   * var_dump(\bbn\Str::checkJson("foo"));
   *  // (bool) false
   *
   * var_dump(\bbn\Str::checkJson("foo", true));
   *  // (string) "Syntax error, malformed JSON"
   * ```
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


  /**
   * Places quotes around a string
   *
   * ```php
   *  var_dump(\bbn\Str::asVar('foo'));
   *  // (string) '"foo"'
   *
   *  var_dump(\bbn\Str::asVar("foo", "'"));
   *  // (string) "'foo'"
   *
   * var_dump(\bbn\Str::asVar("foo'bar"));
   *  // (string) '"foo\'bar"'
   *
   * var_dump(\bbn\Str::asVar("foo'bar", "'"));
   *  // (string) "'foo\'bar'"
   *
   * ```
   *
   * @param string $var
   * @param string $quote
   * @return string
   */
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
   * ```php
   *  var_dump(\bbn\Str::markdown2html("# foo"));
   *  // (string) '<h1>foo</h1>'
   *
   * var_dump(\bbn\Str::markdown2html("**foo**"));
   *  // (string) '<p><strong>foo</strong></p>'
   *
   * var_dump(\bbn\Str::markdown2html("**foo**", true));
   *  // (string) '<strong>foo</strong>'
   * ```
   *
   * @param string  $st          The markdown string
   * @param boolean $single_line If true the result will not contain paragraph or block element
   * @return string The HTML string
   */
  public static function markdown2html(string $st, bool $single_line = false): string
  {
    if (!self::$_markdownParser) {
      self::$_markdownParser = new Markdown();
    }


    return self::$_markdownParser->compile($st);
    //return $single_line ? self::$_markdownParser->line($st) : self::$_markdownParser->text($st);
  }


  /**
   * Converts the given string to camel case.
   *
   * ```php
   *  var_dump(\bbn\Str::toCamel("foo bar"));
   *  // (string) 'fooBar'
   * ```
   *
   * @param string $st
   * @param string $sep   A separator
   * @param bool   $first Capitalize first character if true
   * @return string
   */
  public static function toCamel(string $st, string $sep = '_', bool $first = false): string
  {
    $st = strtolower($st);

    $res = str_replace(' ', '', ucwords(str_replace($sep, ' ', $st)));
    if (!$first) {
        $res[0] = strtolower($res[0]);
    }

    return $res;
  }


  /**
   * Converts the given camel case string to a lower case separated string.
   *
   * ```php
   *  var_dump(\bbn\Str::fromCamel("fooBar"));
   *  // (string) 'foo bar'
   * ```
   *
   * @param string $st
   * @param string $sep   A separator
   * @param bool   $first Capitalize first character if true
   * @return string
   */
  public static function fromCamel(string $input, string $separator = '_'): string
  {
    return ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $input)), $separator);
  }


  /**
   * Converts HTML to text replacing paragraphs and brs with new lines.
   *
   * ```php
   *  var_dump(\bbn\Str::html2text("<h1>foo bar</h1><br>baz"));
   *  // (string) 'foo bar
   * baz'
   *
   * var_dump(\bbn\Str::html2text('<h1>foo bar</h1><br>', false));
   *  // (string) 'foo bar'
   * ```
   *
   * @param string $st The HTML string
   * @return string
   */
  public static function html2text(string $st, bool $nl = true): string
  {
    $st = trim(self::sanitizeHtml($st));
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
   * ```php
   *  var_dump(\bbn\Str::text2html("foo\n bar"));
   *  // (string) '<p>foo<br> bar</p>'
   *
   * var_dump(\bbn\Str::text2html("foo\n bar", false));
   *  // (string) 'foo<br> bar'
   * ```
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


  /**
   * Remove malicious code and makes HTML standard compliant.
   *
   * @param string $html
   * @param array $allowed_tags
   * @param array $allowed_attr
   * @todo There is something to do about the constant here...
   * @return string
   */
  public static function sanitizeHtml(string $html, array $allowed_tags = [], array $allowed_attr = []): string
  {
    if (!self::$_htmlSanitizer) {
      $config = HTMLPurifier_Config::createDefault();
      $config->set('Core.Encoding', 'UTF-8');
      //$config->set('HTML', 'Doctype', 'HTML 4.01 Transitional');
      if (defined('PURIFIER_CACHE')) {
        $config->set('Cache.SerializerPath', constant('PURIFIER_CACHE'));
      }
      else {
        # Disable the cache entirely
        $config->set('Cache.DefinitionImpl', null);
      }

      self::$_htmlSanitizer = new HTMLPurifier($config);
    }

    return self::$_htmlSanitizer->purify($html);
  }


  /**
   * From https://github.com/symfony/polyfill-php72/blob/v1.26.0/Php72.php#L24-L38
   *
   * @param string $s
   * @return string
   */
  public static function toUtf8(string $s): string {
    if (mb_check_encoding($s, 'UTF-8')) {
      return $s;
    }

    if (mb_check_encoding($s, 'ISO-8859-1')) {
      return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }

    return mb_convert_encoding($s, 'UTF-8', mb_list_encodings());
  }


}
