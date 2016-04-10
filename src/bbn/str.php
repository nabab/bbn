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
   * <code>
   * \bbn\str::cast(1);
   * \bbn\str::cast([1, 'test', 2, 'text']);
   * </code>
   *
   * @param mixed $st The item to cast.
   *
	 * @return string
	 */
  public static function cast($st)
  {
    if ( is_array($st) || is_object($st) ){
      return '';
    }
    return (string)$st;
  }

	/**
   * Converts the case of a string.
   *
   * <code>
   * \bbn\str::change_case('TEST CASE', 'lower')); //Returns "test case"
   * \bbn\str::change_case('test case', 'upper')); //Returns "TEST CASE"
   * \bbn\str::change_case('test case')); //Returns "Test Case"
   * </code>
   *
   * @param mixed $st The item to convert.
   * @param mixed $case The case to convert to ("lower" or "upper"), default being title case.
   *
	 * @return string
	 */
  public static function change_case($st, $case = false)
  {
    $st = self::cast($st);
    $case = strtolower($case);
    switch ( $case ){
      case "lower":
        $case = MB_CASE_LOWER;
        break;
      case "upper":
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
   * Escape string in double quotes.
   *
   * <code>
   * \bbn\str::escape_dquotes('L\'infanzia di "Maria"'); //Returns "L'infanzia di \"Maria\""
   * </code>
   *
   * @param string $st The string to escape.
   *
	 * @return string
	 */
	public static function escape_dquotes($st)
	{
    return addcslashes(self::cast($st), "\"\\\r\n\t");
	}

	/**
   * Synonym of "escape_dquotes".
   *
   * <code>
   * \bbn\str::escape_dquote('L\'infanzia di "Maria"'); //Returns "L'infanzia di \"Maria\""
   * </code>
   *
   * @param string $st The string to escape.
   *
	 * @return string
	 */
	public static function escape_dquote($st)
	{
		return self::escape_dquotes($st);
	}

	/**
   * Synonym of "escape_dquotes".
   *
   * <code>
   * \bbn\str::escape_quote('L\'infanzia di "Maria"'); //Returns "L'infanzia di \"Maria\""
   * </code>
   *
   * @param string $st The string to escape.
   *
	 * @return string
	 */
	public static function escape_quote($st)
	{
		return self::escape_dquotes($st);
	}

	/**
   * Synonym of "escape_dquotes".
   *
   * <code>
   * \bbn\str::escape_quotes('L\'infanzia di "Maria"'); //Returns "L'infanzia di \"Maria\""
   * </code>
   *
   * @param string $st The string to escape.
   *
   * @return string
	 */
	public static function escape_quotes($st)
	{
		return self::escape_dquotes($st);
	}

  /**
   * Escape string in quotes.
   *
   * <code>
   * \bbn\str::escape_squotes("L'infanzia di \"maria\""); //Returns "L\'infanzia di "maria""
   * </code>
   *
   * @param string $st The string to escape.
   *
	 * @return string
	 */
	public static function escape_squotes($st)
	{
    return addcslashes(self::cast($st), "'\\\r\n\t");
	}

  /**
   * Synonym of "escape_squotes".
   *
   * <code>
   * \bbn\str::escape("L'infanzia di \"maria\""); //Returns "L\'infanzia di "maria""
   * </code>
   *
   * @param string $st The string to escape.
   *
	 * @return string
	 */
	public static function escape($st)
	{
		return self::escape_squotes($st);
	}

  /**
   * Synonym of "escape_squotes".
   *
   * <code>
   * \bbn\str::escape_apo("L'infanzia di \"maria\""); //Returns "L\'infanzia di "maria""
   * </code>
   *
   * @param string $st The string to escape.
   *
	 * @return string
	 */
	public static function escape_apo($st)
	{
		return self::escape_squotes($st);
	}

  /**
   * Synonym of "escape_squotes".
   *
   * <code>
   * \bbn\str::escape_squote("L'infanzia di \"maria\""); //Returns "L\'infanzia di "maria""
   * </code>
   *
   * @param string $st The string to escape.
   *
	 * @return string
	 */
	public static function escape_squote($st)
	{
		return self::escape_squotes($st);
	}

  /**
   * Returns a string expunged of several types of character depending of configuration.
   *
   * @param mixed $st The item to be.
   * @param string $mode A selection of configuration: "all" (default), "2n1", "html", "code".
   *
	 * @return string
	 */
	public static function clean($st, $mode='all')
	{
		if ( is_array($st) )
		{
			reset($st);
			$i = count($st);
			if ( trim($st[0]) == '' )
			{
				array_splice($st,0,1);
				$i--;
			}
			if ( $i > 0 )
			{
				if ( trim($st[$i-1]) == '' )
				{
					array_splice($st,$i-1,1);
					$i--;
				}
			}
			return $st;
		}
		else
		{
      $st = self::cast($st);
			if ( $mode == 'all' )
			{
				$st = mb_ereg_replace("\n",'\n',$st);
				$st = mb_ereg_replace("[\t\r]","",$st);
				$st = mb_ereg_replace('\s{2,}',' ',$st);
			}
			else if ( $mode == '2nl' )
			{
				$st = mb_ereg_replace("[\r]","",$st);
				$st = mb_ereg_replace("\n{2,}","\n",$st);
			}
			else if ( $mode == 'html' )
			{
				$st = mb_ereg_replace("[\t\r\n]",'',$st);
				$st = mb_ereg_replace('\s{2,}',' ',$st);
			}
			else if ( $mode == 'code' )
			{
				$st = mb_ereg_replace("!/\*.*?\*/!s",'',$st); // comment_pattern
				$st = mb_ereg_replace("[\r\n]",'',$st);
				$st = mb_ereg_replace("\t"," ",$st);
				$chars = [';','=','+','-','\(','\)','\{','\}','\[','\]',',',':'];
				foreach ( $chars as $char )
				{
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
   * Cut a string (HTML and PHP tags stripped) to maximum lenght inserted.
   *
   * <code>
   * \bbn\str::cut("<!-- HTML Document --> Example text", 7); //Returns "Example..."
   * </code>
   *
   * @param string $st The string to be cut.
   * @param int $max The maximum string lenght.
   *
	 * @return string
	 */
	public static function cut($st, $max = 15)
	{
    $st = self::cast($st);
		$st = mb_ereg_replace('&nbsp;',' ',$st);
		$st = mb_ereg_replace('\n',' ',$st);
		$st = strip_tags($st);
		$st = html_entity_decode($st, ENT_QUOTES, 'UTF-8');
		$st = self::clean($st);
		if ( mb_strlen($st) >= $max )
		{
      // Chars forbidden to finish the string with
			$chars = [' ', '.'];
      // Final chars
			$ends = [];
      // The string gets cut at $max
			$st = mb_substr($st, 0, $max);
      while ( in_array(substr($st, -1), $chars) ){
        $st = substr($st, 0, -1);
      }
      $st .= '...';
		}
		return $st;
	}

  /**
   * Returns a cross-platform filename for file.
   *
   * <code>
   * \bbn\str::encode_filename('test file/,1', 15, 'txt'); //Returns
"test_file_1.txt"
   * </code>
   *
   * @param string $st The name as string.
   * @param int $maxlength The maximum filename length (without extension), default: "50".
   * @param string $extension The extension of file.
   *
	 * @return string
	 */
	public static function encode_filename($st, $maxlength = 50, $extension = null)
	{
		$st = self::remove_accents(self::cast($st));
		$res = '';

    $allowed = ["-", "_", ".", ","];

    $args = func_get_args();
    foreach ( $args as $i => $a ){
      if ( $i > 0 ){
        if ( is_string($a) ){
          $extension = $a;
        }
        else if ( is_int($a) ){
          $maxlength = $a;
        }
      }
    }

    if ( !is_int($maxlength) ){
      $maxlength = mb_strlen($st);
    }

    if ( $extension &&
            (self::file_ext($st) === self::change_case($extension, 'lower')) ){
      $st = substr($st, 0, -(strlen($extension)+1));
    }
    else if ( $extension = self::file_ext($st) ){
      $st = substr($st, 0, -(strlen($extension)+1));
    }

		for ( $i = 0; $i < $maxlength; $i++ ){
			if ( mb_ereg_match('[A-z0-9\\-_.,]',mb_substr($st,$i,1)) ){
				$res .= mb_substr($st,$i,1);
      }
			else if ( (mb_strlen($res) > 0) &&
              !in_array(mb_substr($res,-1), $allowed) &&
              ($i < ( mb_strlen($st) - 1 )) ){
				$res .= '_';
      }
		}
    if ( substr($res, -1) === '_' ){
      $res = substr($res, 0, -1);
    }
    if ( $extension ) {
      $res .= '.' . $extension;
    }

		return $res;
	}

  /**
   * Returns a corrected string for database naming.
   *
   * <code>
   * \bbn\str::encode_dbname('my.database_name ? test  :,; !plus'); //Returns  "my_database_name_test_plus"
   * </code>
   *
   * @param string $st The name as string.
   * @param int $maxlength The maximum length, default: "50".
   *
	 * @return string
	 */
	public static function encode_dbname($st, $maxlength = 50)
	{
		$st = self::remove_accents(self::cast($st));
		$res = '';

    if ( !is_int($maxlength) ){
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
   * <code>
   * \bbn\str::file_ext('d:\test.txt'); //Returns "txt"
   * \bbn\str::file_ext('d:\test.txt', 1); //Returns ['d:\\a', 'txt']
   * </code>
   *
   * @param string $file The file path.
   * @param bool $ar If "true" returns also the file path, default: "false".
   *
	 * @return string|array
	 */
	public static function file_ext($file, $ar=false)
	{
    $file = self::cast($file);
		if ( mb_strrpos($file, '/') !== false )
			$file = substr($file, mb_strrpos($file, '/')+1);
		if ( mb_strpos($file, '.') !== false )
		{
			$p = mb_strrpos($file, '.');
			$f = mb_substr($file, 0, $p);
			$ext = mb_convert_case(mb_substr($file, $p+1), MB_CASE_LOWER);
			if ( $ar )
				return [$f, $ext];
			else
				return $ext;
		}
		else if ( $ar )
				return [$file, ''];
		else
			return '';
	}

	/**
   * Returns a random password.
   *
   * <code>
   * \bbn\str::genpwd(); //Returns "khc9P871w"
   * \bbn\str::genpwd(6, 4); //Returns "dDEtxY"
   * </code>
   *
   * @param int $int_max Maximum characters of password, default: "12".
   * @param int $int_min Minimum characters of password, default: "6".
   *
	 * @return string
	 */
	public static function genpwd($int_max=12, $int_min=6)
	{
		mt_srand();
		if ($int_min > 0)
			$longueur = mt_rand($int_min,$int_max);
		else
			$longueur = $int_max;
		$mdp = '';
		for($i=0; $i<$longueur; $i++)
		{
      // First caracter a letter
			if ( $i === 0 ){
        $quoi= mt_rand(2,3);
      }
      else{
        $quoi= mt_rand(1,3);
      }
			switch($quoi)
			{
				case 1:
					$mdp .= mt_rand(0,9);
					break;
				case 2:
					$mdp .= chr(mt_rand(65,90));
					break;
				case 3:
					$mdp .= chr(mt_rand(97,122));
					break;
			}
		}
		return $mdp;
	}

  /**
   * Checks if the string is a json string.
   *
   * <code>
   * \bbn\str::is_json('{"firstName": "John", "lastName": "Smith", "age": 25}'); //Returns true
   * </code>
   *
   * @param string $st The string.
   *
   * @return bool
	 */
  public static function is_json($st){
    if ( is_string($st) && !empty($st) &&
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
   * <code>
   * \bbn\str::is_number([1, 2]); //Returns false
   * \bbn\str::is_number(150); //Returns true
   * \bbn\str::is_number('150'); //Returns true
   * </code>
   *
   * @param mixed $st The item to be tested.
   *
	 * @return bool
	 */
	public static function is_number()
	{
    $args = func_get_args();
    foreach ( $args as $a ){
      if ( is_string($a) || (abs($a) > PHP_INT_MAX) ){
        if ( !preg_match('/^-?(?:\d+|\d*\.\d+)$/', $a) ){
          return false;
        }
      }
      else if ( !is_int($a) && !is_float($a) ) {
        return false;
      }
    }
    return 1;
  }

	/**
	 * Checks if the item is a integer.
	 * Can take as many arguments and will return false if one of them is not an integer or the string of an integer.
	 *
	 * <code>
	 * \bbn\str::is_integer(13.2); //Returns false
	 * \bbn\str::is_integer(14); //Returns true
	 * \bbn\str::is_integer('14'); //Returns true
	 * </code>
	 *
	 * @param mixed $st The item to be tested.
	 *
	 * @return bool
	 */
	public static function is_integer()
	{
		$args = func_get_args();
		foreach ( $args as $a ){
			if ( is_string($a) || (abs($a) > PHP_INT_MAX) ){
				if ( !preg_match('/^-?(\d+)$/', (string)$a) ){
					return false;
				}
			}
			else if ( !is_int($a) ){
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if the item is a integer.
	 * Can take as many arguments and will return false if one of them is not an integer or the string of an integer.
	 *
	 * <code>
	 * \bbn\str::is_integer(13.2); //Returns false
	 * \bbn\str::is_integer(14); //Returns true
	 * \bbn\str::is_integer('14'); //Returns true
	 * </code>
	 *
	 * @param mixed $st The item to be tested.
	 *
	 * @return bool
	 */
	public static function is_clean_path()
	{
		$args = func_get_args();
		foreach ( $args as $a ){
			if ( is_string($a) ){
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
   * Can take as many arguments and will return false if one of them is not a decimal or the string of a decimal (float).
   *
   * <code>
   * \bbn\str::is_decimal(13.2); //Returns true
   * \bbn\str::is_decimal('13.2'); //Returns true
   * \bbn\str::is_decimal(14); //Returns false
   * </code>
   *
   * @param mixed $st The item to be tested.
   *
	 * @return bool
	 */
	public static function is_decimal()
	{
    $args = func_get_args();
    foreach ( $args as $a ){
      if ( is_string($a) ){
        if ( !preg_match('/^-?(\d*\.\d+)$/', $a) ){
          return false;
        }
      }
      else if ( !is_float($a) ) {
        return false;
      }
    }
    return true;
  }

	/**
   * Converts string variable into int or float if it looks like it and returns the argument anyway.
   *
   * <code>
   * \bbn\str::correct_types('1230'); //Returns 1230
   * \bbn\str::correct_types(12.30); //Returns 12.3
   * \bbn\str::correct_types([1230]); //Returns [1230]
   * </code>
   *
   * @param string $st The string.
   *
	 * @return mixed
	 */
  public static function correct_types($st)
  {
    if ( is_string($st) ){
      if ( self::is_integer($st) && ((substr($st, 0, 1) !== '0') || ($st === '0')) ){
        $tmp = (int)$st;
        if ( ($tmp < PHP_INT_MAX) && ($tmp > -PHP_INT_MAX) ){
          return $tmp;
        }
      }
      // If it's a decimal, not ending with a zero
      else if ( self::is_decimal($st) && (substr($st, -1) !== '0') ){
        return (float)$st;
      }
    }
    else if ( is_array($st) ){
      foreach ( $st as $k => $v ){
        $st[$k] = self::correct_types($v);
      }
    }
    else if ( is_object($st) ){
      $vs = get_object_vars($st);
      foreach ( $vs as $k => $v ){
        $st->$k = self::correct_types($v);
      }
		}
    return $st;
  }

  /**
   * Checks if the string is a correct type of e-mail address.
   *
   * <code>
   * \bbn\str::is_email('test@email.com'); //Returns true
   * \bbn\str::is_email('test@email'); //Returns false
   * \bbn\str::is_email('test@.com'); //Returns false
   * \bbn\str::is_email('testemail.com'); //Returns false
   * </code>
   *
   * @param string $email E-mail address.
   *
	 * @return bool
	 */
	public static function is_email($email)
	{
		if ( function_exists('filter_var') ){
			return filter_var($email,FILTER_VALIDATE_EMAIL) ? true : false;
    }
		else
		{
			$isValid = true;
			$atIndex = mb_strrpos($email, "@");
			if (is_bool($atIndex) && !$atIndex)
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
   * Returns an array containing any of the various components of the URL that are present.
   *
   * <code>
   * \bbn\str::parse_url('http://localhost/phpmyadmin/?db=test&table=users&server=1&target=&token=e45a102c5672b2b4fe84ae75d9148981');
   * /*Returns [
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
   * ] *
   * </code>
   *
   * @param string $url The url.
   *
	 * @return array
	 */
	public static function parse_url($url)
	{
    $url = self::cast($url);
		$r = \bbn\x::merge_arrays(parse_url($url), ['url' => $url,'query' => '','params' => []]);
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
   * Replace backslash with slash in a path string. Forbids the use of ../
   *
   * <code>
   * \bbn\str::parse_path('C:\TedstDir\New\New folder'); //Returns "C:/TedstDir/New/New folder"
   * </code>
   *
   * @param string $path The path.
   *
	 * @return string
	 */
	public static function parse_path($path)
	{
    $path = str_replace('\\', '/', strval($path));
		$path = str_replace('/./', '/', strval($path));
    while ( strpos($path, '//') !== false ) {
      $path = str_replace('//', '/', $path);
    }
    if ( strpos($path, '../') !== false ){
      return '';
    }
		return $path;
	}

	/**
   * Replaces accented characters with their character without accent.
   *
   * <code>
   * \bbn\str::remove_accents("TÃ¨st FÃ¬lÃ¨ Ã²Ã¨Ã Ã¹"); //Returns "Test File oeau"
   * </code>
   *
   * @param string $st The string.
   *
	 * @return string
	 */
	public static function remove_accents($st)
	{
		$st = trim(mb_ereg_replace('&(.)(tilde|circ|grave|acute|uml|ring|oelig);', '\\1', self::cast($st)));
		$search = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u,ą,ń,ł,ź,ę,À,Á,Â,Ã,Ä,Ç,È,É,Ê,Ë,Ì,Í,Î,Ï,Ñ,Ò,Ó,Ô,Õ,Ö,Ù,Ú,Û,Ü,Ý,Ł,Ś");
		$replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u,a,n,l,z,e,A,A,A,A,A,C,E,E,E,E,I,I,I,I,N,O,O,O,O,O,U,U,U,U,Y,L,S");
    foreach ( $search as $i => $s )
			$st = mb_ereg_replace($s, $replace[$i], $st);
   	return $st;
	}

	/**
	 * Checks if a string comply with SQL naming convention.
	 *
	 * <code>
	 * \bbn\str::check_name("Paul"); //Returns true
	 * \bbn\str::check_name("PÃ ul"); //Returns false
	 * </code>
	 *
	 * @return bool
	 */
	public static function check_name(){

		$args = func_get_args();
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
	 * Checks if a string doesn't contain a filesystem path
	 *
	 * <code>
	 * \bbn\str::check_name("Paul"); //Returns true
	 * \bbn\str::check_name("PA/ul"); //Returns false
	 * </code>
	 *
	 * @return bool
	 */
	public static function check_filename(){

		$args = func_get_args();
		// Each argument must be a string starting with a letter, and having than one character made of letters, numbers and underscores
		foreach ( $args as $a ){
			if ( !is_string($a) || (strpos($a, '/') !== false) || (strpos($a, '\\') !== false) ){
				return false;
			}
		}
		return true;
	}


	/**
   * Checks if a string comply with SQL naming convention.
   * Returns "true" if slash or backslash are present.
   *
   * <code>
   * \bbn\str::has_slash("Paul"); //Returns false
   * \bbn\str::has_slash("Paul/"); //Returns true
   * \bbn\str::has_slash("Paul\\"); //Returns true
   * </code>
   *
   * @return bool
	 */
	public static function has_slash(){

		$args = func_get_args();
		// Each argument must be a string starting with a letter, and having than one character made of letters, numbers and underscores
		foreach ( $args as $a ){
      if ( (strpos($a, '/') !== false) || (strpos($a, '\\') !== false) ){
        return 1;
			}
		}

		return false;
	}

  /**
   * Extracts all digits from a string.
   *
   * <code>
   * \bbn\str::get_numbers("test 13 example 24"); //Returns 1324
   * </code>
   *
   * @param string $st The string.
   *
   * @return string
   */
	public static function get_numbers($st){
		return preg_replace("/[^0-9]/", '', self::cast($st));
	}

  /**
   * returns the argumented value, replacing non standard objects (not stdClass) by their class name.
   *
   * @param mixed $o The item.
   *
   * @return mixed
   */
  public static function make_readable($o)
  {
    $is_array = false;
    if ( is_object($o) ){
      $class = get_class($o);
      if ( $class === 'stdClass' ){
        $is_array = 1;
      }
      else{
        return $class;
      }
    }
    if ( is_array($o) || $is_array ){
      $r = [];
      foreach ( $o as $k => $v ){
        $r[$k] = self::make_readable($v);
      }
      return $r;
    }
    return $o;
  }

  /**
   * Returns a variable in a fashion that is directly usable by PHP.
   *
   * @param mixed $o The item to be.
   * @param bool $remove_empty Default: "false".
   * @param int $lev Default: "1".
   *
   * @return mixed
   */
  public static function export($o, $remove_empty=false, $lev=1){
    $st = '';
    if ( is_object($o) && ($cls = get_class($o)) && ($cls !== 'stdClass') ){
      $st .= "Object ".get_class($o).PHP_EOL;
    }
    if ( is_object($o) || is_array($o) ){
      $is_assoc = (is_object($o) || \bbn\x::is_assoc($o));
      //$st .= $is_assoc ? '{' : '[';
      $st .= '[';
      $st .= PHP_EOL;
      foreach ( $o as $k => $v ){
        if ( $remove_empty && ( ( is_string($v) && empty($v) ) || ( is_array($v) && count($v) === 0 ) ) ){
          continue;
        }
        $st .= str_repeat('    ', $lev);
        if ( $is_assoc ){
          $st .= ( is_string($k) ? '"'.self::escape_dquote($k).'"' : $k ). " => ";
        }
        if ( is_array($v) ){
          $st .= self::export($v, $remove_empty, $lev+1);
        }
        else if ( is_object($v) ){
          $cls = get_class($v);
          if ( $cls === 'stdClass' ){
            $st .= self::export($v, $remove_empty, $lev+1);
          }
          else{
            $st .= "Object $cls";
          }
        }
        else if ( $v === 0 ){
          $st .= '0';
        }
        else if ( is_null($v) ){
          $st .= 'null';
        }
        else if ( is_bool($v) ){
          $st .= $v === false ? 'false' : 'true';
        }
        else if ( is_int($v) || is_float($v) ){
          $st .= $v;
        }
        else if ( !$remove_empty || !empty($v) ){
          $st .= '"'.self::escape_dquote($v).'"';
        }
        $st .= ','.PHP_EOL;
      }
      $st .= str_repeat('    ', $lev-1);
      //$st .= $is_assoc ? '}' : ']';
      $st .= ']';
      return $st;
    }
    return $o;
  }

	public static function replace_once($search, $replace, $subject){
		$pos = strpos($subject, $search);
		if ($pos !== false) {
			return substr_replace($subject, $replace, $pos, strlen($search));
		}
		return $subject;
	}

	public static function is_url($st){
		return filter_var($st, FILTER_VALIDATE_URL);
	}

	public static function is_domain($st){
		return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $st) //valid chars check
			&& preg_match("/^.{1,253}$/", $st) //overall length check
			&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $st)   ); //length of each label
	}

	/**
	 * Validates if a stringg is SQL formatted date
	 *
	 * @param $st
	 * @return bool
	 */
	public static function is_date_sql($st){
		return \bbn\date::validateSQL($st);
	}
}
