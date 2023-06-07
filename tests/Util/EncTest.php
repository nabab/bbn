<?php

namespace bbn\tests\Util;

use bbn\Util\Enc;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\ReflectionHelpers;

class EncTest extends TestCase
{
  use Files;

  protected function setUp(): void
  {
    $this->cleanTestingDir();
  }

  protected function tearDown(): void
  {
//    $this->cleanTestingDir();
  }


  /** @test */
  public function get_key_method_returns_encryption_key_for_the_given_key()
  {
    $method = ReflectionHelpers::getNonPublicMethod('_get_key', Enc::class);

    $this->assertSame(
      hash('sha256', 'foo'),
      $method->invoke(null, 'foo')
    );

    if (!defined('BBN_ENCRYPTION_KEY')) {
      $this->assertSame(
        hash('sha256', ReflectionHelpers::getNonPublicProperty('salt', Enc::class)),
        $method->invoke(null)
      );

      define('BBN_ENCRYPTION_KEY', 'Hello world!');
    }

    $this->assertSame(
      hash('sha256', 'Hello world!'),
      $method->invoke(null)
    );
  }

  /** @test */
  public function encryptOpenssl_method_encrypts_the_given_string_using_openssl()
  {
    $this->assertIsString(
      Enc::encryptOpenssl('foo', 'bar', null,  \bbn\Str::genpwd(500, 500))
    );

    $this->assertIsString(
      Enc::encryptOpenssl('foo', 'bar', 'aes-256-cbc-hmac-sha256',  'pass')
    );

    $this->assertIsString(
      Enc::encryptOpenssl('foo')
    );

    $this->assertNull(
      Enc::encryptOpenssl('foo', 'bar', 'unknown_method', 'password')
    );

    $this->assertNull(
      Enc::encryptOpenssl('foo', 'bar', 'aria-192-gcm',  'pass')
    );
  }

  /**
   * @test
   * @depends encryptOpenssl_method_encrypts_the_given_string_using_openssl
   */
  public function decryptOpenssl_method_decrypts_the_given_string_using_openssl()
  {
    $pass = Enc::encryptOpenssl('foo', 'bar', 'aes-256-cbc-hmac-sha256', 'pass');

    $this->assertSame(
      'foo',
      Enc::decryptOpenssl($pass, 'bar', 'aes-256-cbc-hmac-sha256', 'pass')
    );

    $this->assertNull(
      Enc::decryptOpenssl($pass, 'bar', 'aes-256-cbc-hmac-sha256', 'another pass')
    );

    $this->assertNull(
      Enc::decryptOpenssl($pass, 'bar', 'aes-192-ocb', 'pass')
    );

    $this->assertNotSame(
      'foo',
      Enc::decryptOpenssl($pass, 'bar')
    );

    $this->assertNull(
      Enc::decryptOpenssl($pass, 'bar', 'unknown_method')
    );

    $this->assertNull(
      Enc::decryptOpenssl($pass, 'bar', 'aria-192-gcm', 'pass')
    );
  }

  /** @test */
  public function crypt_method_encrypts_the_given_string_with_an_optional_encryption_key()
  {
    $this->assertIsString(Enc::crypt('foo'));
    $this->assertIsString(Enc::crypt('foo', 'bar'));
  }

  /**
   * @test
   * @depends crypt_method_encrypts_the_given_string_with_an_optional_encryption_key
   */
  public function decrypt_method_decrypts_the_given_string_with_an_optional_encryption_key()
  {
    $pass = Enc::crypt('foo', 'bar');

    $this->assertSame('foo', Enc::decrypt($pass, 'bar'));
    $this->assertNotSame('foo', Enc::decrypt($pass));
  }

  /**
   * @test
   * @depends crypt_method_encrypts_the_given_string_with_an_optional_encryption_key
   */
  public function crypt64_method_encrypts_the_given_string_and_returns_a_base64_of_the_result_with_an_optional_encryption_key()
  {
    $crypt = Enc::crypt('foo', 'bar');

    $this->assertSame(
      base64_encode($crypt),
      Enc::crypt64('foo', 'bar')
    );

    $this->assertNotSame(
      base64_encode($crypt),
      Enc::crypt64('foo')
    );
  }

  /**
   * @test
   * @depends crypt64_method_encrypts_the_given_string_and_returns_a_base64_of_the_result_with_an_optional_encryption_key
   */
  public function decrypt64_method_decrypts_the_given_base64_string_with_an_optional_encryption_key()
  {
    $result = Enc::crypt64('foo', 'bar');

    $this->assertSame(
      'foo',
      Enc::decrypt64($result, 'bar')
    );

    $this->assertNotSame(
      'foo',
      Enc::decrypt64($result)
    );
  }

  /** @test */
  public function generateCert_method_generates_private_and_public_ssl_certificates_strings_and_return_it_as_an_array()
  {
    $result = Enc::generateCert();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('private', $result);
    $this->assertArrayHasKey('public', $result);
    $this->assertIsString($result['public']);
    $this->assertIsString($result['private']);
  }

  /** @test */
  public function generateCertFiles_method_generates_private_and_public_ssl_certificates_files()
  {
    $this->createDir('cert');

    $dir = $this->getTestingDirName() . 'cert';

    $this->assertTrue(
      Enc::generateCertFiles("$dir/id")
    );

    $this->assertFileExists($private_key = "$dir/id_rsa");
    $this->assertFileExists($public_key = "$dir/id_rsa.pub");

    $this->assertNotEmpty(file_get_contents($private_key));
    $this->assertNotEmpty(file_get_contents($public_key));
  }

  /** @test */
  public function generateCertFiles_method_returns_false_if_the_given_algo_does_not_exist()
  {
    $this->createDir('cert');

    $this->assertFalse(
      Enc::generateCertFiles($this->getTestingDirName() . 'cert/id', 'foo')
    );
  }

  /** @test */
  public function generateCertFiles_method_returns_false_if_the_given_path_has_an_existing_private_key()
  {
    $this->createDir('cert');

    $this->createFile('id_rsa', 'foo|bar', 'cert');

    $this->assertFalse(
      Enc::generateCertFiles($this->getTestingDirName() . 'cert/id')
    );
  }

  /** @test */
  public function sshEncodePublicKey_method_test()
  {
    $rsaKey = openssl_pkey_new([
      'digest_alg' => 'sha256',
      'private_key_bits' => 4096,
      'private_key_type' => OPENSSL_KEYTYPE_RSA
    ]);

    $method = ReflectionHelpers::getNonPublicMethod('_sshEncodePublicKey', Enc::class);

    $this->assertIsString(
     $result = $method->invoke(null, $rsaKey)
    );

    $this->assertStringContainsString('ssh-rsa', $result);
  }
}