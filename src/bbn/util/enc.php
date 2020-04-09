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
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
class enc 
{

  protected static $method = "AES-256-CFB";

  protected static $salt = 'dsjfjsdvcb34YhXZLW';

  protected static $prefix = 'bbn-';

  private static function _get_key($key = ''){
    if ( empty($key) ){
      $key = \defined('BBN_ENCRYPTION_KEY') ? BBN_ENCRYPTION_KEY : self::$salt;
    }
    return hash( 'sha256', $key);
  }

  private static function _sshEncodePublicKey($privKey) {
    $keyInfo = openssl_pkey_get_details($privKey);
    $buffer  = pack("N", 7) . "ssh-rsa" .
      self::_sshEncodeBuffer($keyInfo['rsa']['e']) .
      self::_sshEncodeBuffer($keyInfo['rsa']['n']);
    return "ssh-rsa " . base64_encode($buffer);
  }

  private static function _sshEncodeBuffer($buffer) {
    $len = strlen($buffer);
    if (ord($buffer[0]) & 0x80) {
      $len++;
      $buffer = "\x00" . $buffer;
    }
    return pack("Na*", $len, $buffer);
  }



  /**
   * @param string $s
   * @param string $key
   * @return string
   */
	public static function crypt(string $s, string $key=''): string
	{
	  $key = self::_get_key($key);
	  return self::encryptOpenssl($s, $key);
	}

  /**
   * @param string $s
   * @param string $key
   * @return string
   */
	public static function decrypt(string $s, string $key=''): string
	{
    $key = self::_get_key($key);
    return self::decryptOpenssl($s, $key);
	}

  /**
   * Encrypt string using openSSL module
   * @param string $textToEncrypt
   * @param string $secretHash Any random secure SALT string for your website
   * @param string $password User's optional password
   * @return null|string
   */
  public static function encryptOpenssl($textToEncrypt, string $secretHash = null, string $password = ''): ? string
  {
    if ( !$secretHash ){
      $secretHash = self::$salt;
    }
    if ( $length = openssl_cipher_iv_length(self::$method) ){
      $iv = substr(md5(self::$prefix.$password), 0, $length);
      $res = null;
      try{
        $res = openssl_encrypt($textToEncrypt, self::$method, $secretHash, true, $iv);
      }
      catch ( \Exception $e ){
        bbn\x::log("Impossible to decrypt");
      }
      return $res;
    }
    return null;
  }

  /**
   * Decrypt string using openSSL module
   * @param string $textToDecrypt
   * @param string $secretHash Any random secure SALT string for your website
   * @param string $password User's optional password
   * @return null|string
   */
  public static function decryptOpenssl($textToDecrypt, string $secretHash = null, string $password = ''): ? string
  {
    if ( !$secretHash ){
      $secretHash = self::$salt;
    }
    if ( $length = openssl_cipher_iv_length(self::$method) ){
      $iv = substr(md5(self::$prefix.$password), 0, $length);
      try {
        $res = openssl_decrypt($textToDecrypt, self::$method, $secretHash, true, $iv);
      }
      catch ( \Exception $e ){
        \bbn\x::log($e->getMessage());
      }
      if ( $res ){
        return $res;
      }
    }
    return null;
  }

  public static function generateCert(string $path, string $algo = 'sha512', int $key_bits = 4096): bool
  {
    $res = false;
    if ( !is_dir(dirname($path)) || file_exists($path.'_rsa') || !in_array($algo, hash_algos()) ){
      return false;
    }
    $public = $path.'_rsa.pub';
    $private = $path.'_rsa';
    $params = [
      'digest_alg' => $algo,
      'private_key_bits' => $key_bits,
      'private_key_type' => OPENSSL_KEYTYPE_RSA
    ];
    $rsaKey = openssl_pkey_new($params);
    openssl_pkey_export($rsaKey, $privKey);
    $umask = umask(0066);
    $privKey = openssl_pkey_get_private($rsaKey);
    if (
      openssl_pkey_export_to_file($privKey, $private) && //Private Key
      ($pubKey = self::_sshEncodePublicKey($rsaKey)) && //Public Key
      file_put_contents($public, $pubKey) //save public key into file
    ){
      $res = true;
    }
    umask($umask);
    return $res;
  }

  public static function generateCert2(string $algo = 'sha512', int $key_bits = 4096): ?array
  {
    $res = null;
    $params = [
      'digest_alg' => $algo,
      'private_key_bits' => $key_bits,
      'private_key_type' => OPENSSL_KEYTYPE_RSA
    ];
    $rsaKey = openssl_pkey_new($params);
    //openssl_pkey_export($rsaKey, $priv);
    $umask = umask(0066);
    $privKey = openssl_pkey_get_private($rsaKey);
    $pubKey = openssl_pkey_get_details($rsaKey);
    if (openssl_pkey_export($privKey, $priv)
        && ($pub = $pubKey['key'])
    ){
      $res = [
        'private' => $priv,
        'public' => $pub
      ];
    }
    umask($umask);
    return $res;
  }

}
