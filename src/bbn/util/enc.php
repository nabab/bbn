<?php
/**
 * @package util
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



  protected static $method = "AES-256-CBC";

  private static function get_key($key = ''){
    if ( empty($key) ){
      $key = defined('BBN_ENCRYPTION_KEY') ? BBN_ENCRYPTION_KEY : 'dsjfjsdvcb34YhXZLW';
    }
    return hash( 'sha256', $key);
  }

  private static function get_iv($size){
    $key = defined('BBN_ENCRYPTION_KEY') ? BBN_ENCRYPTION_KEY : 'dsjfjsdvcb34YhXZLW';
    return substr(hash( 'sha256', 'bbn_'.$key), 0, $size);
  }

  private function get_size($method){
    return openssl_cipher_iv_length($method);
  }

  function my_simple_crypt( $string, $action = 'e' ) {
    // you may change these values to your own
    $secret_key = 'my_simple_secret_key';
    $secret_iv = 'my_simple_secret_iv';

    $output = false;
    $encrypt_method = "AES-256-CBC";
    $key = hash( 'sha256', $secret_key );
    $iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

    if( $action == 'e' ) {
      $output = base64_encode( openssl_encrypt( $string, $encrypt_method, $key, 0, $iv ) );
    }
    else if( $action == 'd' ){
      $output = openssl_decrypt( base64_decode( $string ), $encrypt_method, $key, 0, $iv );
    }

    return $output;
  }


  /**
	 * @return void 
	 */
	public static function crypt($string, $key='')
	{
	  $key = self::get_key($key);
    $method = "AES-256-CBC";
    $iv_size = openssl_cipher_iv_length($method);
    $iv = self::get_iv($iv_size);
    return base64_encode(openssl_encrypt( $string, $method, $key, 0, $iv));
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