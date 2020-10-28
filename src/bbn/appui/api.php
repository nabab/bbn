<?php

namespace bbn\appui;

use bbn;
use bbn\util\jwt;
use bbn\x;


class api extends bbn\models\cls\basic
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
  public const REMOTE = 'https://server.thomas.lan/api/home';

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
  public function __construct(bbn\user $user, bbn\db $db, int $ttl = 300)
  {
    if ($user->get_id()) {
      $this->db = $db;
      $this->jwt = new jwt();
      $this->jwt->prepare($user->get_id(), $user->get_osession('fingerprint'), $ttl);
    }
  }

  public function register(array $cfg, string $key): array
  {
    if ($this->jwt) {
      $jwt = $this->jwt->set_key($key)->set(['data' => $cfg]);
      if ($res = x::curl(
        self::REMOTE,
        ['action' => 'register', 'data' => $jwt]
      )
      ) {
        $key = self::_get_tmp_key();
        return $this->jwt->set_key($key)->get($res);
      }
      else {
        throw new \Exception(_("Impossible to register"));
      }
    }
    else {
      throw new \Exception(_("No JWT"));
    }
  }


  public function emit($cfg): array
  {
    if ($this->jwt) {
      $key = self::_get_key(true);
      $jwt = $this->jwt->set_key($key)->set(['data' => $cfg]);
      if ($res = x::curl(
        self::REMOTE.'/'.BBN_ID_APP,
        ['action' => 'emit', 'data' => $jwt]
      )
      ) {
        $key = self::_get_key();
        return $this->jwt->set_key($key)->get($res);
      }
      else {
        throw new \Exception(_("Impossible to emit"));
      }
    }
    else {
      throw new \Exception(_("No JWT"));
    }
  }


  /**
   * Returns the key to read or write JWT.
   *
   * @param bool $out True if the private key for writing out is requested.
   *
   * @return string
   */
  private function _get_key(bool $out = false): string
  {
    if ($out) {
      if (empty(self::$_rsa_out)) {
        self::_set_key(file_get_contents(BBN_APP_PATH.self::RSA_OUT), true);
      }

      return self::$_rsa_out;
    }

    if (empty(self::$_rsa_in)) {
      $opt = bbn\appui\options::get_instance();
      $id_envs = $opt->from_code('env', BBN_APP_NAME, 'projects', 'appui');
      $id_app = $this->db->select_one(
        'bbn_options',
        'id',
        [
          'id_parent' => $id_envs,
          'text' => BBN_APP_PATH,
          'code' => BBN_SERVER_NAME.(BBN_CUR_PATH === '/' ? '' : BBN_CUR_PATH)
        ]
      );
      $passwords = new passwords($this->db);
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