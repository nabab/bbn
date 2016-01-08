<?php
/**
 * @package bbn\util
 */
namespace bbn\util;
/**
 * Encryption Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Utilities
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class enc 
{

	/**
	 * @return void 
	 */
	public static function crypt($pass, $key='')
	{
		if ( empty($key) ){
			if ( defined('BBN_ENCRYPTION_KEY') ){
				$key = BBN_ENCRYPTION_KEY;
			}
			else{
				$key = 'dsjfjsdvcb34YhXZLW';
			}
		}
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB); /* get vector size on ECB mode */
    $key_size = strlen($key);
    if ( $key_size > $iv_size ){
      $key = substr($key, 0, $iv_size);
    }
    else if ( $key_size < $iv_size ){
      $key = str_pad($key, $iv_size, 'bbn_');
    }
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND); /* Creating the vector */
		$cryptedpass = @mcrypt_encrypt (MCRYPT_RIJNDAEL_256, $key, $pass, MCRYPT_MODE_ECB, $iv); /* Encrypting using MCRYPT_RIJNDAEL_256 algorithm */
		return base64_encode($cryptedpass);
	}

	/**
	 * @return void 
	 */
	public static function decrypt($encpass, $key='')
	{
		if ( empty($key) ){
			if ( defined('BBN_ENCRYPTION_KEY') ){
				$key = BBN_ENCRYPTION_KEY;
			}
			else{
				$key = 'dsjfjsdvcb34YhXZLW';
			}
		}
		$encpass = base64_decode($encpass);
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB); /* get vector size on ECB mode */
    $key_size = strlen($key);
    if ( $key_size > $iv_size ){
      $key = substr($key, 0, $iv_size);
    }
    else if ( $key_size < $iv_size ){
      $key = str_pad($key, $iv_size, 'bbn_');
    }
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$decryptedpass = @mcrypt_decrypt (MCRYPT_RIJNDAEL_256, $key, $encpass, MCRYPT_MODE_ECB, $iv); /* Decrypting... */
		return rtrim($decryptedpass);
	}

}
?>