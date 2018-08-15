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



  protected static $method = "AES-256-CFB";

  protected static $salt = 'dsjfjsdvcb34YhXZLW';

  protected static $prefix = 'bbn-';

  private static function get_key($key = ''){
    if ( empty($key) ){
      $key = \defined('BBN_ENCRYPTION_KEY') ? BBN_ENCRYPTION_KEY : 'dsjfjsdvcb34YhXZLW';
    }
    return hash( 'sha256', $key);
  }

  private static function get_iv($size){
    $key = \defined('BBN_ENCRYPTION_KEY') ? BBN_ENCRYPTION_KEY : 'dsjfjsdvcb34YhXZLW';
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
	 * @return string
	 */
	public static function crypt($string, $key='')
	{
	  $key = self::get_key($key);
    $method = "AES-256-CBC";
    return base64_encode(openssl_encrypt($string, $method, $key, 0, openssl_random_pseudo_bytes()));
	}

	/**
	 * @return string
	 */
	public static function decrypt($encstring, $key='')
	{
    $key = self::get_key($key);
    $encstring = base64_decode($encstring);
    $method = "AES-256-CBC";
		return openssl_decrypt($encstring, $method, $key, 0, openssl_random_pseudo_bytes());
	}

  /**
   * Encrypt string using openSSL module
   * @param string $textToEncrypt
   * @param string $encryptionMethod One of built-in 50 encryption algorithms
   * @param string $secretHash Any random secure SALT string for your website
   * @param bool $raw If TRUE return base64 encoded string
   * @param string $password User's optional password
   * @return null|string
   */
  public static function encryptOpenssl($textToEncrypt, string $encryptionMethod = null, string $secretHash = null, bool $raw = false, string $password = ''): ? string
  {
    if ( !$encryptionMethod ){
      $encryptionMethod = self::$method;
    }
    if ( !$secretHash ){
      $secretHash = self::$salt;
    }
    if ( $length = openssl_cipher_iv_length($encryptionMethod) ){
      $iv = substr(md5(self::$prefix.$password), 0, $length);
      return openssl_encrypt($textToEncrypt, $encryptionMethod, $secretHash, $raw, $iv);
    }
    return null;
  }

  /**
   * Decrypt string using openSSL module
   * @param string $textToDecrypt
   * @param string $encryptionMethod One of built-in 50 encryption algorithms
   * @param string $secretHash Any random secure SALT string for your website
   * @param bool $raw If TRUE return base64 encoded string
   * @param string $password User's optional password
   * @return null|string
   */
  public static function decryptOpenssl($textToDecrypt, string $encryptionMethod = null, string $secretHash = null, bool $raw = false, string $password = ''): ? string
  {
    if ( !$encryptionMethod ){
      $encryptionMethod = self::$method;
    }
    if ( !$secretHash ){
      $secretHash = self::$salt;
    }
    if ( $length = openssl_cipher_iv_length($encryptionMethod) ){
      $iv = substr(md5(self::$prefix.$password), 0, $length);
      return openssl_decrypt($textToDecrypt, $encryptionMethod, $secretHash, $raw, $iv);
    }
    return null;
  }

}
