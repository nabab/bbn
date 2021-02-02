<?php
/**
 * @package util
 */
namespace bbn\Util;

use bbn\X;

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
class Enc
{

  protected static $method = "AES-256-CFB";

  protected static $salt = 'dsjfjsdvcb34YhXZLW';

  protected static $prefix = 'bbn-';

  /**
   * @param string $s
   * @param string $key
   * @return string
   */
  public static function crypt(string $s, String $key=''): string
  {
    $key = self::_get_key($key);
    return self::encryptOpenssl($s, $key);
  }

  /**
   * @param string $s
   * @param string $key
   * @return string
   */
  public static function decrypt(string $s, String $key=''): string
  {
    $key = self::_get_key($key);
    return self::decryptOpenssl($s, $key);
  }

  public static function crypt64(string $s, String $key=''): string
  {
    return base64_encode(self::crypt($s, $key));
  }

  public static function decrypt64(string $s, String $key=''): string
  {
    return self::decrypt(base64_decode($s), $key);
  }

  /**
   * Encrypt string using openSSL module
   * @param string $s
   * @param string $key      Any random secure SALT string for your website
   * @param string $password User's optional password
   * @return null|string
   */
  public static function encryptOpenssl(string $s,
      string $key = null,
      string $method = null,
      string $password = ''
  ): ?string {
    if (!$key) {
      $key = self::$salt;
    }
    if ($length = openssl_cipher_iv_length($method ?: self::$method)) {
      $iv = substr(md5(self::$prefix.$password), 0, $length);
      $res = null;
      try{
        $res = openssl_encrypt($s, $method ?: self::$method, $key, true, $iv);
      }
      catch (\Exception $e) {
        bbn\X::log("Impossible to decrypt");
      }
      return $res;
    }
    return null;
  }

  /**
   * Decrypt string using openSSL module.
   * 
   * @param string $s
   * @param string $key      Any random secure SALT string for your website
   * @param string $password User's optional password
   * @return null|string
   */
  public static function decryptOpenssl(string $s,
      string $key = null,
      string $method = null,
      string $password = ''
  ): ?string {
    if (!$key) {
      $key = self::$salt;
    }
    if ($length = openssl_cipher_iv_length($method ?: self::$method)) {
      $iv = substr(md5(self::$prefix.$password), 0, $length);
      try {
        $res = openssl_decrypt($s, $method ?: self::$method, $key, true, $iv);
      }
      catch (\Exception $e){
        X::log($e->getMessage(), 'decryptOpenssl');
      }
      if ($res) {
        return $res;
      }
    }
    return null;
  }

  /**
   * Generates a private and a public SSL certificate files.
   *
   * @param string $path
   * @param string $algo
   * @param int    $key_bits
   *
   * @return bool
   */
  public static function generateCertFiles(string $path, String $algo = 'sha512', int $key_bits = 4096): bool
  {
    $res = false;
    if (is_dir(dirname($path))
        && !file_exists($path.'_rsa')
        && in_array($algo, Hash_algos(), true)
        && ($key = self::generateCert($algo, $key_bits))
    ) {
      if (is_dir($path) && (substr($path, -1) !== '/')) {
        $path .= '/';
      }
      $public = $path.'_rsa.pub';
      $private = $path.'_rsa';
      if (\file_put_contents($public, $key['public'])
          && \file_put_contents($private, $key['private'])
      ) {
        $res = true;
      }
    }
    return $res;
  }

  public static function generateCert(string $algo = 'sha512', int $key_bits = 4096): ?array
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
    ) {
      $res = [
        'private' => $priv,
        'public' => $pub
      ];
    }
    umask($umask);
    return $res;
  }

  private static function _get_key($key = '')
  {
    if (empty($key)) {
      $key = \defined('BBN_ENCRYPTION_KEY') ? BBN_ENCRYPTION_KEY : self::$salt;
    }
    return hash('sha256', $key);
  }

  private static function _sshEncodePublicKey($privKey)
  {
    $keyInfo = openssl_pkey_get_details($privKey);
    $buffer  = pack("N", 7) . "ssh-rsa" .
      self::_sshEncodeBuffer($keyInfo['rsa']['e']) .
      self::_sshEncodeBuffer($keyInfo['rsa']['n']);
    return "ssh-rsa " . base64_encode($buffer);
  }

  private static function _sshEncodeBuffer($buffer)
  {
    $len = strlen($buffer);
    if (ord($buffer[0]) & 0x80) {
      $len++;
      $buffer = "\x00" . $buffer;
    }
    return pack("Na*", $len, $buffer);
  }
}
