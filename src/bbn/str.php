<?php
namespace bbn;

/**
 * Class text
 * String manipulation class
 *
 * This class only uses static methods and has lots of alias for the escaping methods
 *
 * @package bbn
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Strings
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 *
 */

class str
{

  /**
   * Converts any type of variable to a string.
   *
   * ```php
   * $st = 122
   * \bbn\x::dump(\bbn\str::cast($st));
   * // (string) "122"
   * \bbn\x::dump(\bbn\str::cast(1));
   * // (string) "1"
   * ```
   *
   * @param mixed $st The item to cast.
   * @return string
   */
  public static function cast($st)
  {
    if ( \is_array($st) || \is_object($st) ){
      return '';
    }
    return (string)$st;
  }

  /**
   * Converts the case of a string.
   *
   * ```php
   * $st = 'TEST CASE';
   * \bbn\x::dump(\bbn\str::change_case($st, 'lower'));
   * // (string) "test case"
   * \bbn\x::dump(\bbn\str::change_case('TEsT Case', 'upper'));
   * // (string) "TEST CASE"
   * \bbn\x::dump(\bbn\str::change_case('test case'));
   * // (string) "Test Case"
   * ```
   *
   * @param mixed $st The item to convert.
   * @param mixed $case The case to convert to ("lower" or "upper"), default being the title case.
   * @return string
   */
  public static function change_case($st, $case = 'x'): string
  {
    $st = self::cast($st);
    $case = substr(strtolower((string)$case), 0, 1);
    switch ( $case ){
      case "l":
        $case = MB_CASE_LOWER;
        break;
      case "u":
        $case = MB_CASE_UPPER;
        break;
      default:
        $case = MB_CASE_TITLE;
    }
    if ( !empty($st) ){
      $st = mb_convert_case($st, $case);
    }
    return $st;
  }



  /**
   * Escapes all quotes (single and double) from a given string.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape_dquotes('the "Gay Pride" is is Putin\'s favorite'));
   * // (string) "the \"Gay Pride\" is is Putin\'s favorite"
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape_all_quotes($st): string
  {
    return self::escape_dquotes(self::escape_squotes($st));
  }


  /**
   * Escapes the string in double quotes.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape_dquotes('this is the house "Mary"'));
   * // (string) "this is the house \"Mary\""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape_dquotes($st): string
  {
    return addcslashes(self::cast($st), "\"\\\r\n\t");
  }

  /**
   * Synonym of "escape_dquotes".
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape_dquote('this is the house "Mary"'));
   * // (string) "this is the house \"Mary\""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape_dquote($st): string
  {
    return self::escape_dquotes($st);
  }

  /**
   * Synonym of "escape_dquotes".
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape_quote('this is the house "Mary"'));
   * // (string) "this is the house \"Mary\""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape_quote($st): string
  {
    return self::escape_dquotes($st);
  }

  /**
   * Synonym of "escape_dquotes".
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape_quotes('this is the house "Mary"'));
   * // (string) "this is the house \"Mary\""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape_quotes($st): string
  {
    return self::escape_dquotes($st);
  }

  /**
   * Escapes the string in quotes.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape_squotes("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape_squotes($st): string
  {
    return addcslashes(self::cast($st), "'\\\r\n\t");
  }

  /**
   * Synonym of "escape_squotes".
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape($st): string
  {
    return self::escape_squotes($st);
  }

  /**
   * Synonym of "escape_squotes".
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape_apo("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape_apo($st): string
  {
    return self::escape_squotes($st);
  }

  /**
   * Synonym of "escape_squotes".
   *
   * ```php
   * \bbn\x::dump(\bbn\str::escape_squote("Today's \"newspaper\""));
   * // (string)  "Today\'s "newspaper""
   * ```
   *
   * @param string $st The string to escape.
   * @return string
   */
  public static function escape_squote($st): string
  {
    return self::escape_squotes($st);
  }

  /**
   * Returns an expunged string of several types of character(s) depending on the configuration.
   *
   * ```php
   * $test="this      is
   * cold";
   *
   * \bbn\x::dump(\bbn\str::clean($test));
   * // (string)  "this is\n cold"
   *
   * $test1="this is
   *
   *
   * cold";
   *
   * \bbn\x::dump(\bbn\str::clean($test1,'2nl'));
   * /* (string)
   * "this is
   *  cold"
   *
   * \bbn\x::dump(\bbn\str::clean($test1,'html'));
   * // (string)  "this is cold"
   *
   * \bbn\x::dump(\bbn\str::clean('$x = 9993','code'));
   * // (string)  "$x=9993"
   * ```
   *
   * @param mixed $st The item to be.
   * @param string $mode A selection of configuration: "all" (default), "2n1", "html", "code".
   * @return string
   */
  public static function clean($st, $mode='all'): string
  {
    if ( \is_array($st) ){
      reset($st);
      $i = \count($st);
      if ( trim($st[0]) == '' ){
        array_splice($st,0,1);
        $i--;
      }
      if ( $i > 0 ){
        if ( trim($st[$i-1]) === '' ){
          array_splice($st, $i-1, 1);
          $i--;
        }
      }
      return $st;
    }
    else{
      $st = self::cast($st);
      if ( $mode == 'all' ){
        $st = mb_ereg_replace("\n",'\n',$st);
        $st = mb_ereg_replace("[\t\r]","",$st);
        $st = mb_ereg_replace('\s{2,}',' ',$st);
      }
      else if ( $mode == '2nl' ){
        $st = mb_ereg_replace("[\r]","",$st);
        $st = mb_ereg_replace("\n{2,}","\n",$st);
      }
      else if ( $mode == 'html' ){
        $st = mb_ereg_replace("[\t\r\n]",'',$st);
        $st = mb_ereg_replace('\s{2,}',' ',$st);
      }
      else if ( $mode == 'code' ){
        $st = mb_ereg_replace("!/\*.*?\*/!s",'',$st); // comment_pattern
        $st = mb_ereg_replace("[\r\n]",'',$st);
        $st = mb_ereg_replace("\t"," ",$st);
        $chars = [';','=','+','-','\(','\)','\{','\}','\[','\]',',',':'];
        foreach ( $chars as $char ){
          while ( mb_strpos($st,$char.' ') !== false ){
            $st = mb_ereg_replace($char.' ',$char,$st);
          }
          while ( mb_strpos($st,' '.$char) !== false ){
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
   * \bbn\x::dump(\bbn\str::cut("<!-- HTML Document --> Example text", 7));
   * // (string) "Example..."
   * ```
   *
   * @param string $st The string to be cut.
   * @param int $max The maximum string length.
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
    if ( mb_strlen($st) >= $max ){
      // Chars forbidden to finish with a string
      $chars = [' ', '.'];
      // Final chars
      $ends = [];
      // The string gets cut at $max
      $st = mb_substr($st, 0, $max);
      while ( \in_array(substr($st, -1), $chars) ){
        $st = substr($st, 0, -1);
      }
      $st .= '...';
    }
    return $st;
  }

  /**
  * @todo comment this
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
   * \bbn\x::dump(\bbn\str::encode_filename('test file/,1', 15, 'txt'));
   * // (string) "test_file_1.txt"
   * ```
   *
   * @param string $st The name as string.
   * @param int $maxlength The maximum filename length (without extension), default: "50".
   * @param string $extension The extension of the file.
   * @param bool $is_path Tells if the slashes (/) are authorized in the string
   * @return string
   */
  public static function encode_filename($st, $maxlength = 50, $extension = null, $is_path = false): string
  {

    $st = self::remove_accents(self::cast($st));
    $allowed = '~\-_.,\(\[\)\]';

    // Arguments order doesn't matter
    $args = \func_get_args();
    foreach ( $args as $i => $a ){
      if ( $i > 0 ){
        if ( \is_string($a) ){
          $extension = $a;
        }
        else if ( \is_int($a) ){
          $maxlength = $a;
        }
        else if ( \is_bool($a) ){
          $is_path = $a;
        }
      }
    }

    if ( !\is_int($maxlength) ){
      $maxlength = mb_strlen($st);
    }

    if ( $is_path ){
      $allowed .= '/';
    }

    if (
      $extension &&
      (self::file_ext($st) === self::change_case($extension, 'lower'))
    ){
      $st = substr($st, 0, -(\strlen($extension)+1));
    }
    else if ( $extension = self::file_ext($st) ){
      $st = substr($st, 0, -(\strlen($extension)+1));
    }
    $st = mb_ereg_replace("([^\w\s\d".$allowed.".])", '', $st);
    $st = mb_ereg_replace("([\.]{2,})", '', $st);
    $res = mb_substr($st, 0, $maxlength);
    if ( $extension ){
      $res .= '.' . $extension;
    }
    return $res;
  }

  /**
   * Returns a corrected string for database naming.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::encode_dbname('my.database_name ? test  :,; !plus'));
   * // (string) "my_database_name_test_plus"
   * ```
   *
   * @param string $st The name as string.
   * @param int $maxlength The maximum length, default: "50".
   * @return string
   */
  public static function encode_dbname($st, $maxlength = 50): string
  {
    $st = self::remove_accents(self::cast($st));
    $res = '';

    if ( !\is_int($maxlength) ){
      $maxlength = mb_strlen($st);
    }

    for ( $i = 0; $i < $maxlength; $i++ ){
      if ( mb_ereg_match('[A-z0-9]',mb_substr($st,$i,1)) ){
        $res .= mb_substr($st,$i,1);
      }
      else if ( (mb_strlen($res) > 0) &&
        (mb_substr($res,-1) != '_') &&
        ($i < ( mb_strlen($st) - 1 )) ){
        $res .= '_';
      }
    }
    if ( substr($res, -1) === '_' ){
      $res = substr($res, 0, -1);
    }
    return $res;
  }

  /**
   * Returns the file extension.
   *
   * ```php
   * \bbn\x::dump(str::file_ext(\"c:\\Desktop\\test.txt\"));
   * // (string) "txt"
   * \bbn\x::dump(\bbn\str::file_ext('/home/user/Desktop/test.txt',1));
   * // (array) [ "test", "txt", ]
   * ```
   *
   * @param string $file The file path.
   * @param bool $ar If "true" also returns the file path, default: "false".
   * @return string|array
   */
  public static function file_ext(string $file, bool $ar = false)
  {
    $file = self::cast($file);
    if ( mb_strrpos($file, '/') !== false ){
      $file = substr($file, mb_strrpos($file, '/') + 1);
    }
    if ( mb_strpos($file, '.') !== false ){
      $p = mb_strrpos($file, '.');
      $f = mb_substr($file, 0, $p);
      $ext = mb_convert_case(mb_substr($file, $p+1), MB_CASE_LOWER);
      return $ar ? [$f, $ext] : $ext;
    }
    return $ar ? [$file, ''] : '';
  }

  /**
   * Returns a random password.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::genpwd());
   * // (string) "khc9P871w"
   * \bbn\x::dump(\bbn\str::genpwd(6, 4));
   * // (string) "dDEtxY"
   * ```
   *
   * @param int $int_max Maximum password characters, default: "12".
   * @param int $int_min Minimum password characters, default: "6".
   * @return string
   */
  public static function genpwd(int $int_max = 12, int $int_min = 6): string
  {
    mt_srand();
    $len = ($int_min > 0) && ($int_min < $int_max) ? random_int($int_min, $int_max) : $int_max;
    $mdp = '';
    for( $i = 0; $i < $len; $i++ ){
      // First character is a letter
      $type = $i === 0 ? random_int(2, 3) : random_int(1, 3);
      switch ( $type ){
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
   * \bbn\x::dump(\bbn\str::is_json('{"firstName": "John", "lastName": "Smith", "age": 25}'));
   * // (bool) true
   * ```
   *
   * @param string $st The string.
   * @return bool
   */
  public static function is_json($st)
  {
    if ( \is_string($st) && !empty($st) &&
      ( (substr($st, 0, 1) === '{') || (substr($st, 0, 1) === '[') )){
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
   * \bbn\x::dump(\bbn\str::is_number([1, 2]));
   * // (bool) false
   * \bbn\x::dump(\bbn\str::is_number(150);
   * // (bool) 1
   * \bbn\x::dump(\bbn\str::is_number('150'));
   * // (bool)  1
   * \bbn\x::dump(\bbn\str::is_number(1.5);
   * // (bool) 1
   * ```
   *
   * @param mixed $st The item to be tested.
   * @return bool
   */
  public static function is_number(): bool
  {
    $args = \func_get_args();
    foreach ( $args as $a ){
      if ( \is_string($a) || (abs($a) > PHP_INT_MAX) ){
        if ( !preg_match('/^-?(?:\d+|\d*\.\d+)$/', $a) ){
          return false;
        }
      }
      else if ( !\is_int($a) && !\is_float($a) ){
        return false;
      }
    }
    return 1;
  }

  /**
   * Checks if the item is a integer.
   * Can take as many arguments and will return false if one of them is not an integer or the string of an integer.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::is_integer(13.2));
   * // (bool) false
   * \bbn\x::dump(\bbn\str::is_integer(14));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::is_integer('14'));
   * // (bool) true
   * ```
   *
   * @param mixed $st The item to be tested.
   * @return bool
   */
  public static function is_integer(): bool
  {
    $args = \func_get_args();
    foreach ( $args as $a ){
      if ( \is_string($a) || (abs($a) > PHP_INT_MAX) ){
        if ( !preg_match('/^-?(\d+)$/', (string)$a) ){
          return false;
        }
      }
      else if ( !\is_int($a) ){
        return false;
      }
    }
    return true;
  }

  /**
   * Checks if ".. \\" or "../" is contained in the parameter and it will return false if true.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::is_clean_path("/home/user/Images"));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::is_clean_path("../home/user/Images"));
   * // (bool) false
   * \bbn\x::dump(\bbn\str::is_clean_path("..\\home\user\Images"));
   * // (bool) false
   * ```
   *
   * @param mixed $st The item to be tested.
   * @return bool
   */
  public static function is_clean_path(): bool
  {
    $args = \func_get_args();
    foreach ( $args as $a ){
      if ( \is_string($a) ){
        if ( (strpos($a, '../') !== false) || (strpos($a, '..\\') !== false) ){
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
   * \bbn\x::dump(\bbn\str::is_decimal(13.2));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::is_decimal('13.2'));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::is_decimal(14));
   * // (bool) false
   * ```
   *
   * @param mixed $st The item to be tested.
   * @return bool
   */
  public static function is_decimal(): bool
  {
    $args = \func_get_args();
    foreach ( $args as $a ){
      if ( \is_string($a) ){
        if ( !preg_match('/^-?(\d*\.\d+)$/', $a) ){
          return false;
        }
      }
      else if ( !\is_float($a) ){
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
  public static function is_uid($st): bool
  {
    return \is_string($st) && (\strlen($st) === 32) && ctype_xdigit($st);// && !mb_detect_encoding($st);
  }

  /**
   * Checks if the string is a valid binary UID string.
   *
   * @param string $st
   * @return boolean
   */
  public static function is_buid($st): bool
  {
    if ( \is_string($st) && (\strlen($st) === 16) && !ctype_print($st) && !ctype_space($st) ){
      $enc = mb_detect_encoding($st, ['8bit', 'UTF-8']);
      if ( !$enc || ($enc === '8bit') ){
        return true;
      }
    }
    return false;
  }

  /**
   * Checks if the string is the correct type of e-mail address.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::is_email('test@email.com'));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::is_email('test@email'));
   * // (bool) false
   * \bbn\x::dump(\bbn\str::is_email('test@.com'));
   * // (bool) false
   * \bbn\x::dump(\bbn\str::is_email('testemail.com'));
   * // (bool) false
   * ```
   *
   * @param string $email E-mail address.
   * @return bool
   */
  public static function is_email($email): bool
  {
    if ( function_exists('filter_var') ){
      return filter_var($email,FILTER_VALIDATE_EMAIL) ? true : false;
    }
    else
    {
      $isValid = true;
      $atIndex = mb_strrpos($email, "@");
      if (\is_bool($atIndex) && !$atIndex)
      {
        $isValid = false;
      }
      else
      {
        $domain = mb_substr($email, $atIndex+1);
        $local = mb_substr($email, 0, $atIndex);
        $localLen = mb_strlen($local);
        $domainLen = mb_strlen($domain);
        //  local part length exceeded
        if ($localLen < 1 || $localLen > 64)
          $isValid = false;
        //  domain part length exceeded
        else if ($domainLen < 1 || $domainLen > 255)
          $isValid = false;
        // local part starts or ends with '.'
        else if ($local[0] == '.' || $local[$localLen-1] == '.')
          $isValid = false;
        // local part has two consecutive dots
        else if (mb_ereg_match('\\.\\.', $local))
          $isValid = false;
        // character not valid in domain part
        else if (!mb_ereg_match('^[A-Za-z0-9\\-\\.]+$', $domain))
          $isValid = false;
        //  domain part has two consecutive dots
        else if (mb_ereg_match('\\.\\.', $domain))
          $isValid = false;
        //  character not valid in local part unless
        else if ( !mb_ereg_match('^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$'
          ,str_replace("\\\\","",$local)))
        {
          // local part is quoted
          if ( !mb_ereg_match('^"(\\\\"|[^"])+"$',str_replace("\\\\","",$local)) )
            $isValid = false;
        }
      }
      return $isValid;
    }
  }

  /**
   * Checks if the argument is a valid URL string.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::is_url("http://bbn.so"));
   * // (string) "https://bbn.so"
   *
   * \bbn\x::dump(\bbn\str::is_url("bbn.so"));
   * // (bool) false
   * ```
   *
   * @param string $st The string to perform
   * @return string|false
   */
  public static function is_url($st){
    return filter_var($st, FILTER_VALIDATE_URL);
  }

  /**
   * Checks if the argument is a valid domain name.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::is_domain("http://bbn.so"));
   * // (string) false
   *
   * \bbn\x::dump(\bbn\str::is_domain("bbn.so"));
   * // (bool) true
   * ```
   *
   * @param string $st The string to perform
   * @return bool
   */
  public static function is_domain($st): bool
  {
    return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $st) //valid chars check
      && preg_match("/^.{1,253}$/", $st) //overall length check
      && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $st)   ); //length of each label
  }

  public static function is_ip($st): bool
  {
    $valid = filter_var($st, FILTER_VALIDATE_IP);
    return $valid;
  }

  /**
   * Checks if the argument is in a valid SQL date format.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::is_date_sql("1999-12-05 11:10:22"));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::is_date_sql("1999-12-05"));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::is_date_sql("19-12-1999"));
   * // (bool) false
   * ```
   *
   * @param string $st
   * @return bool
   */
  public static function is_date_sql($st): bool
  {
    return date::validateSQL($st);
  }

  /**
   * If it looks like an int or float type, the string variable is converted into the correct type.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::correct_types(1230));
   * // (int) 1230
   * \bbn\x::dump(\bbn\str::correct_types(12.30));
   * // (float) 12.3
   * \bbn\x::dump(\bbn\str::correct_types("12.3"));
   * // (float) 12.3
   * \bbn\x::dump(\bbn\str::correct_types([1230]));
   * // (int) [1230]
   * ```
   *
   * @param mixed $st
   * @return mixed
   */
  public static function correct_types($st){
    if ( \is_string($st) ){
      if ( self::is_buid($st) ){
        $st = bin2hex($st);
      }
      else{
        if ( self::is_json($st) ){
          if ( strpos($st, '": ') && ($json = json_decode($st)) ){
            return json_encode($json);
          }
          return $st;
        }
        $st = trim($st);
        // Not starting with a zero or ending with a zero decimal
        if ( !preg_match('/^0[^.]+|\.[0-9]*0$/', $st) ){
          if ( self::is_integer($st) && ((substr((string)$st, 0, 1) !== '0') || ($st === '0')) ){
            $tmp = (int)$st;
            if ( ($tmp < PHP_INT_MAX) && ($tmp > -PHP_INT_MAX) ){
              return $tmp;
            }
          }
          // If it is a decimal, not starting or ending with a zero
          else if ( self::is_decimal($st) ){
            return (float)$st;
          }
        }
      }
    }
    else if ( \is_array($st) ){
      foreach ( $st as $k => $v ){
        $st[$k] = self::correct_types($v);
      }
    }
    else if ( \is_object($st) ){
      $vs = get_object_vars($st);
      foreach ( $vs as $k => $v ){
        $st->$k = self::correct_types($v);
      }
    }
    return $st;
  }

  /**
   * Returns an array containing any of the various components of the URL that are present.
   *
   * ```php
   * \bbn\x::hdump(\bbn\str::parse_url('http://localhost/phpmyadmin/?db=test&table=users&server=1&target=&token=e45a102c5672b2b4fe84ae75d9148981');
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
  public static function parse_url($url): array
  {
    $url = self::cast($url);
    $r = x::merge_arrays(parse_url($url), ['url' => $url,'query' => '','params' => []]);
    if ( strpos($url,'?') > 0 )
    {
      $p = explode('?',$url);
      $r['url'] = $p[0];
      $r['query'] = $p[1];
      $ps = explode('&',$r['query']);
      foreach ( $ps as $p ){
        $px = explode('=',$p);
        $r['params'][$px[0]] = $px[1];
      }
    }
    return $r;
  }

  /**
   * Replaces backslash with slash in a path string. Forbids the use of ../
   *
   * ```php
   * \bbn\x::dump(\bbn\str::parse_path('\home\user\Desktop'));
   * // (string) "/home/user/Desktop"
   * ```
   *
   * @param string $path The path.
   * @param boolean $allow_parent If true ../ is allowed in the path (and will become normalized).
   * @return string
   */
  public static function parse_path(string $path, $allow_parent = false): string
  {
    $path = str_replace('\\', '/', \strval($path));
    $path = str_replace('/./', '/', \strval($path));
    while ( strpos($path, '//') !== false ){
      $path = str_replace('//', '/', $path);
    }
    if ( strpos($path, '../') !== false ){
      if ( !$allow_parent ){
        return '';
      }
      $bits = array_reverse(explode('/', $path));
      $path = '';
      $num_parent = 0;
      foreach ( $bits as $i => $b ){
        if ( $b === '..' ){
          $num_parent++;
        }
        else if ( $b !== '.' ){
          if ( $num_parent ){
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
   * \bbn\x::dump(\bbn\str::remove_accents("TÃ¨st FÃ¬lÃ¨ Ã²Ã¨Ã Ã¹è"));
   * // (string) "TA¨st  FA¬lA¨  A²A¨A A¹e"
   * ```
   *
   * @param string $st The string.
   * @return string
   */
  public static function remove_accents($st): string
  {
    $st = trim(mb_ereg_replace('&(.)(tilde|circ|grave|acute|uml|ring|oelig);', '\\1', self::cast($st)));
    $search = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u,ą,ń,ł,ź,ę,À,Á,Â,Ã,Ä,Ç,È,É,Ê,Ë,Ì,Í,Î,Ï,Ñ,Ò,Ó,Ô,Õ,Ö,Ù,Ú,Û,Ü,Ý,Ł,Ś");
    $replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u,a,n,l,z,e,A,A,A,A,A,C,E,E,E,E,I,I,I,I,N,O,O,O,O,O,U,U,U,U,Y,L,S");
    foreach ( $search as $i => $s )
      $st = mb_ereg_replace($s, $replace[$i], $st);
    return $st;
  }

  /**
   * Checks if a string complies with SQL naming convention.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::check_name("Paul"));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::check_name("Pa ul"));
   * // (bool) false
   * ```
   *
   * @return bool
   */
  public static function check_name(): bool
  {

    $args = \func_get_args();
    // Each argument must be a string starting with a letter, and having only one character made of letters, numbers and underscores
    foreach ( $args as $a ){
      $a = self::cast($a);
      $t = preg_match('#[A-z0-9_]+#',$a,$m);
      if ( $t !== 1 || $m[0] !== $a ){
        return false;
      }
    }

    return true;
  }
  /**
   * Checks if a string doesn't contain a filesystem path.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::check_filename("Paul"));
   * // (bool) true
   * \bbn\x::dump(\bbn\str::check_filename("Paul/"));
   * // (bool) false
   * ```
   *
   * @return bool
   */
  public static function check_filename(): bool
  {

    $args = \func_get_args();
    // Each argument must be a string starting with a letter, and having than one character made of letters, numbers and underscores
    foreach ( $args as $a ){
      if ( !\is_string($a) || (strpos($a, '/') !== false) || (strpos($a, '\\') !== false) ){
        return false;
      }
    }
    return true;
  }


  /**
   * Checks if a string complies with SQL naming convention.
   * Returns "true" if slash or backslash are present.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::has_slash("Paul"));
   * // (bool) false
   * \bbn\x::dump(\bbn\str::has_slash("Paul/");
   * // (bool) 1
   * \bbn\x::dump(\bbn\str::has_slash("Paul\\");
   * // (bool) 1
   * ```
   *
   * @return bool
   */
  public static function has_slash(): bool
  {

    $args = \func_get_args();
    // Each argument must be a string starting with a letter, and having than one character made of letters, numbers and underscores
    foreach ( $args as $a ){
      if ( (strpos($a, '/') !== false) || (strpos($a, '\\') !== false) ){
        return true;
      }
    }

    return false;
  }

  /**
   * Extracts all digits from a string.
   *
   * ```php
   * \bbn\x::dump(\bbn\str::get_numbers("test 13 example 24"));
   * // (string) 1324
   * ```
   *
   * @param string $st The string.
   * @return string
   */
  public static function get_numbers($st): string
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
   * $user = \bbn\user::get_instance();
   *
   * $myArray = [
   *  'user' => $user,
   *  'obj' => $myObj,
   *  'val' => 23,
   *  'text' => "Hello!"
   * ];
   *
   * \bbn\x::hdump(\bbn\str::make_readable($user));
   * // (string) "appui/user"
   *
   * \bbn\x::hdump(\bbn\str::make_readable($myArray));
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
  public static function make_readable($o)
  {
    $is_array = false;
    if ( \is_object($o) ){
      $class = \get_class($o);
      if ( $class === 'stdClass' ){
        $is_array = 1;
      }
      else{
        return $class;
      }
    }
    if ( \is_array($o) || $is_array ){
      $r = [];
      foreach ( $o as $k => $v ){
        $r[$k] = self::make_readable($v);
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
   * \bbn\x::hdump(\bbn\str::export($myObj,true));
   * /*(string)
   * "{
   *      "myProp1"  =>  23,
   *      "myProp2"  =>  "world",
   *      "myProp3"  =>  [ 1, 5, 6, ],
   * }"
   * ```
   *
   * @param mixed $o The item to be.
   * @param bool $remove_empty Default: "false".
   * @param int $lev Default: "1".
   * @return string
   */
  public static function export($o, $remove_empty=false, $lev=1): string
  {
    $st = '';
    $space = '    ';
    if ( \is_object($o) && ($cls = \get_class($o)) && (strpos($cls, 'stdClass') === false) ){
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
    else if ( \is_object($o) || \is_array($o) ){
      $is_object = \is_object($o);
      $is_array = !$is_object && \is_array($o);
      $is_assoc = $is_object || ($is_array && x::is_assoc($o));
      $st .= $is_assoc ? '{' : '[';
      $st .= PHP_EOL;
      foreach ( $o as $k => $v ){
        if ( $remove_empty && ( ( \is_string($v) && empty($v) ) || ( \is_array($v) && \count($v) === 0 ) ) ){
          continue;
        }
        $st .= str_repeat($space, $lev);
        if ( $is_assoc ){
          $st .= ( \is_string($k) ? '"'.self::escape_dquote($k).'"' : $k ). ': ';
        }
        if ( \is_array($v) ){
          $st .= self::export($v, $remove_empty, $lev+1);
        }
        else if ( $is_object ){
          $cls = \get_class($v);
          if ( $cls === 'stdClass' ){
            $st .= self::export($v, $remove_empty, $lev+1);
          }
          else{
            /*
            $rc = new \ReflectionClass($cls);
            if ( $rc->hasMethod('__toString') ){
              $st .= $v;
            }
            else{
              $st .= 'Object '.$cls;
            }
            */
            $st .= 'Object '.$cls;
          }
        }
        else if ( $v === 0 ){
          $st .= '0';
        }
        else if ( null === $v ){
          $st .= 'null';
        }
        else if ( \is_bool($v) ){
          $st .= $v === false ? 'false' : 'true';
        }
        else if ( \is_int($v) || \is_float($v) ){
          $st .= $v;
        }
        else if ( !ctype_print($v) && (\strlen($v) === 16) ){
          $st .= '0x'.bin2hex($v);
        }
        else if ( !$remove_empty || !empty($v) ){
          $st .= '"'.self::escape_dquote($v).'"';
        }
        $st .= ','.PHP_EOL;
      }
      $st .= str_repeat($space, $lev-1);
      $st .= $is_assoc ? '}' : ']';
      //$st .= \is_object($o) ? '}' : ']';
    }
    return $st;
  }

  /**
   * Replaces part of a string. If the part is not found, the method returns the string without change.
   *
   * ```php
   * \bbn\x::hdump(\bbn\str::replace_once("cold","hot", "Today there is cold"));
   * // (string)  "Today there is hot"
   * \bbn\x::hdump(\bbn\str::replace_once("rain","hot", "Today there is cold"));
   * // (string)  "Today there is cold"
   * ```
   *
   * @param string $search The string to search
   * @param string $replace The string to replace
   * @param string $subject The string into search
   * @return string
   */
  public static function replace_once($search, $replace, $subject): string
  {
    $pos = strpos($subject, $search);
    if ($pos !== false){
      return substr_replace($subject, $replace, $pos, \strlen($search));
    }
    return $subject;
  }

  /**
   * Removes the comments.
   *
   * ```php
   *  var_dump(\bbn\str::remove_comments("<!--this is a comment-->"));
   *  // (string) ""
   * ```
   *
   * @param string $st
   * @return string
   */
  public static function remove_comments(string $st): string
  {
    $pattern = '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/';
    return preg_replace($pattern, '', $st);
  }

  /**
  * Converts the bytes to another unit form.
  *
  * @param int $bytes The bytes
  * @param string The unit you want to convert ('B', 'K', 'M', 'G', 'T')
  * @parma boolean $stop
  * @return string
  */
  public static function say_size($bytes, $unit = 'B', $stop = false): string
  {
// pretty printer for byte values
//
    $i = 0;
    $units = ['', 'K', 'M', 'G', 'T'];
    while ( $stop || ($bytes > 2000) ){
      $i++;
      $bytes /= 1024;
      if ( $stop === $units[$i] ){
        break;
      }
    }
    return sprintf("%5.2f %s".$unit, $bytes, $units[$i]);
  }

  /**
   * @param $size
   * @param string $unit_orig
   * @param string $unit_dest
   * @return string
   */
  function convert_size($size, $unit_orig = 'B', $unit_dest = 'MB')
  {
    if ( strlen($unit_orig) <= 1 ){
      $unit_orig .= 'B';
    }
    if ( strlen($unit_dest) <= 1 ){
      $unit_dest .= 'B';
    }
    $base = log($size) / log(1024);
    $suffix = array("", "KB", "MB", "GB", "TB");
    $f_base = floor($base);
    return round(pow(1024, $base - floor($base)), 1) . $suffix[$f_base];
  }

  /**
   * Checks whether a JSON string is valid or not. If $return_error is set to true, the error will be returned.
   *
   * @param string $json
   * @param bool $return_error
   * @return bool|string
   */
  function check_json(string $json, bool $return_error = false)
  {
    json_decode($json);
    $error = json_last_error();
    if ( $error === JSON_ERROR_NONE ){
      return true;
    }
    if ( !$return_error ){
      return false;
    }
    switch ( $error ) {
      case JSON_ERROR_DEPTH:
        return _('Maximum stack depth exceeded');
      case JSON_ERROR_STATE_MISMATCH:
        return _('State mismatch (invalid or malformed JSON)');
      case JSON_ERROR_CTRL_CHAR:
        return _('Unexpected control character found');
      case JSON_ERROR_SYNTAX:
        return _('Syntax error, malformed JSON');
      case JSON_ERROR_UTF8:
        return _('Malformed UTF-8 characters, possibly incorrectly encoded');
      default:
        return _('Unknown error');
    }
  }
}
