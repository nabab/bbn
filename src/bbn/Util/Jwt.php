<?php

namespace bbn\Util;

use Exception;
use bbn\X;
use bbn\Models\Cls\Basic;
use Firebase\JWT\JWT as FibebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use DomainException;
use InvalidArgumentException;
use UnexpectedValueException;
class Jwt extends Basic
{

  protected $payload;

  protected $key;

  protected $sub;

  protected $aud;

  protected $ttl;

  public function prepare(string $id_user, string $fingerprint, int $ttl = 300)
  {
    $this->sub = $fingerprint;
    $this->aud = $id_user;
    $this->ttl = $ttl;
    $this->reset();
  }

  public function reset(): self
  {
    $this->payload = [
      "iss" => defined('BBN_SERVER_NAME') ? constant('BBN_SERVER_NAME') : gethostname(),
      "iat" => time(),
      "exp" => time() + $this->ttl,
      "sub" => $this->sub,
      "aud" => $this->aud,
      "data" => []
    ];
    return $this;
  }

  public function setKey($cert): self
  {
    $this->key = $cert;
    return $this;
  }

  public function set(array $data): string
  {
    $this->payload['data'] = $data;
    try {
      if ($this->key) {
        $jwt = FibebaseJWT::encode($this->payload, $this->key, 'RS512');
      }
      else {
        $jwt = FibebaseJWT::encode($this->payload, $this->payload['sub'], 'HS256');
      }
    }
    catch (ExpiredException $e) {
      X::hdump($e->getMessage());
      throw $e;
    }
    catch (Exception $e) {
      X::hdump($e->getMessage());
      throw $e;
    }

    return $jwt;
  }

  public function get(string $jwt): ?array
  {

    try {
      $payload = FibebaseJWT::decode($jwt, new Key($this->key, 'RS512'));
    }
    catch (InvalidArgumentException $e) {
      // provided key/key-array is empty or malformed.
      X::hdump($e->getMessage());
      throw $e;
    }
    catch (DomainException $e) {
      // provided algorithm is unsupported OR
      // provided key is invalid OR
      // unknown error thrown in openSSL or libsodium OR
      // libsodium is required but not available.
      X::hdump($e->getMessage());
      throw $e;
    }
    catch (SignatureInvalidException $e) {
      // provided JWT signature verification failed.
      X::hdump($e->getMessage());
      throw $e;
    }
    catch (BeforeValidException $e) {
      // provided JWT is trying to be used before "nbf" claim OR
      // provided JWT is trying to be used before "iat" claim.
      X::hdump($e->getMessage());
      throw $e;
    }
    catch (ExpiredException $e) {
      // provided JWT is trying to be used after "exp" claim.
      X::hdump($e->getMessage());
      throw $e;
    }
    catch (UnexpectedValueException $e) {
      // provided JWT is malformed OR
      // provided JWT is missing an algorithm / using an unsupported algorithm OR
      // provided JWT algorithm does not match provided key OR
      // provided key ID in key/key-array is empty or invalid.
      X::hdump($e->getMessage());
      throw $e;
    }

    if (!empty($payload->data)) {
      return X::toArray($payload->data);
    }

    return null;
  }
}