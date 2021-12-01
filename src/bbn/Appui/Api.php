<?php

namespace bbn\Appui;

use bbn;
use bbn\Util\Jwt;
use bbn\X;


class Api extends bbn\Models\Cls\Basic
{

  /** @var string The certificate used to decrypt messages from appui server without own cert */
  public const RSA_IN_TMP = 'src/cfg/from_appui_rsa.pub';

  /** @var string The certificate used to encrypt messages to appui server without own cert */
  public const RSA_OUT_TMP = 'src/cfg/to_appui_rsa';

  /** @var string The certificate used to encrypt messages */
  public const RSA_OUT = 'src/cfg/cert_rsa';

  /** @var string The public certificate used to decrypt the messages */
  public const RSA_PUBLIC = 'src/cfg/cert_rsa.pub';

  /** The address of the appui server */
  public const REMOTE = 'https://central.app-ui.com/api/home';

  public $jwt;

  protected $db;

  private static $_rsa_in_tmp;

  private static $_rsa_out_tmp;

  private static $_rsa_in;

  private static $_rsa_out;

  private static $_rsa_public;

  /**
   * Constructor
   *
   */
  public function __construct(bbn\User $user, bbn\Db $db, int $ttl = 300)
  {
    if ($user->getId()) {
      $this->db = $db;
      $this->jwt = new Jwt();
      $this->jwt->prepare($user->getId(), $user->getOsession('fingerprint'), $ttl);
    }
  }


  public function registerProject(array $cfg): array
  {
    if ($this->jwt) {
      $key_out = self::_get_tmp_key(true);
      $jwt = $this->jwt->setKey($key_out)->set(['data' => $cfg]);
      if ($res = X::curl(
        self::REMOTE,
        ['action' => 'register_project', 'data' => $jwt]
      )
      ) {
        $key_in = self::_get_tmp_key();
        return $this->jwt->setKey($key_in)->get($res);
      }
      else {
        throw new \Exception(X::_("Impossible to register"));
      }
    }
    else {
      throw new \Exception(X::_("No JWT"));
    }
  }


  public function hasKey()
  {
    return (bool)$this->_get_key();
  }


  public function request(string $action, array $cfg = []): array
  {
    if ($this->jwt) {
      $key = self::_get_key(true);
      $jwt = $this->jwt->setKey($key)->set(['data' => $cfg]);
      if ($res = X::curl(
        self::REMOTE.'/'.BBN_ID_APP,
        ['action' => $action, 'data' => $jwt]
      )
      ) {
        $key = self::_get_key();
        return $this->jwt->setKey($key)->get($res);
      }
      else {
        throw new \Exception(X::_("Impossible to send the request"));
      }
    }
    else {
      throw new \Exception(X::_("No JWT"));
    }
  }


  public function getAppInfo(): array
  {
    return $this->request('app_info');
  }


  /**
   * Returns the key to read or write JWT.
   *
   * @param bool $out True if the private key for writing out is requested.
   *
   * @return string
   */
  private function _get_key(bool $out = false): ?string
  {
    if ($out) {
      if (empty(self::$_rsa_out)) {
        self::_set_key(file_get_contents(BBN_APP_PATH.self::RSA_OUT), true);
      }

      return self::$_rsa_out;
    }

    if (empty(self::$_rsa_in)) {
      $opt = bbn\Appui\Option::getInstance();
      $id_envs = $opt->fromCode('env', BBN_APP_NAME, 'project', 'appui');
      $id_app = $this->db->selectOne(
        'bbn_options',
        'id',
        [
          'id_parent' => $id_envs,
          'text' => BBN_APP_PATH,
          'code' => BBN_SERVER_NAME.(BBN_CUR_PATH === '/' ? '' : BBN_CUR_PATH)
        ]
      );
      $passwords = new Passwords($this->db);
      if ($tmp = $passwords->get($id_app)) {
        self::_set_key($tmp);
      }
    }

    return self::$_rsa_in;
  }


  /**
   * Sets the keys static properties.
   *
   * @param string $key The string to set as key
   * @param bool   $out Requests or not the private key for writing out
   *
   * @return void
   */
  private static function _set_key(string $key, bool $out = false): void
  {
    if ($out) {
      self::$_rsa_out = $key;
    }
    else {
      self::$_rsa_in = $key;
    }
  }


  /**
   * Returns the temporary key to read or write JWT.
   *
   * @param bool $out True if the private key for writing out is requested.
   *
   * @return string
   */
  private static function _get_tmp_key(bool $out = false): string
  {
    if ($out) {
      if (empty(self::$_rsa_out_tmp)) {
        self::$_rsa_out_tmp = file_get_contents(BBN_APP_PATH.self::RSA_OUT_TMP);
      }
      return self::$_rsa_out_tmp;
    }
    if (empty(self::$_rsa_in_tmp)) {
      self::$_rsa_in_tmp = file_get_contents(BBN_APP_PATH.self::RSA_IN_TMP);
    }
    return self::$_rsa_in_tmp;
  }


}