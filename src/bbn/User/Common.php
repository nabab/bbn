<?php

namespace bbn\User;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Mvc;
use bbn\Db;
use bbn\Mail;
use bbn\File\Dir;
use bbn\File\System;
use bbn\User\Manager;
use bbn\Util\Enc;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;
use Brick\PhoneNumber\PhoneNumberFormat;

trait Common
{
  /** @var Mail */
  private $_mailer;

  /** @var bool */
  protected $auth = false;

  /** @var string */
  protected $path;

  /** @var string */
  protected $tmp_path;

  /** @var string */
  protected $id;

  /** @var array */
  protected $data = [];

  /** @var int */
  protected $id_group;

  /** @var null */
  protected $group = null;

  /** @var mixed */
  protected $alert;

  /** @var array */
  protected $cfg;

  /** @var Db */
  protected $db;

  /** @var mixed */
  public $prev_time;

  /** @var array $class_cfg */
  protected $class_cfg;

  /**
   * An output string to be returned when in api requests.
   * Will be mainly used to return tokens response in api request.
   *
   * @var mixed
   */
  protected $api_request_output;


  /**
   * Retrieves data stored in the data property of the user, only if authenticated.
   *
   * @param string $idx
   *
   * @return void
   */
  public function getData(string $idx)
  {
    if (!$this->auth) {
      throw new Exception(X::_("Impossible to retrieve data for an authenticated user"));
    }

    return $this->data[$idx] ?? null;
  }


  /**
   * Returns the directory path for the user.
   *
   * @return string
   */
  public function getPath(): ?string
  {
    return $this->path;
  }


  /**
   * Returns the tmp directory path for the user.
   *
   * @return string
   */
  public function getTmpDir(): ?string
  {
    return $this->tmp_path;
  }


  /**
   * Returns true if authenticated false otherwise.
   *
   * @return bool
   */
  public function isAuth()
  {
    return $this->auth;
  }


  /**
   * Returns the user's ID if there is no error.
   *
   * @return null|string
   */
  public function getId(): ?string
  {
    if ($this->check()) {
      return $this->id;
    }

    return null;
  }


  /**
   * Returns the user's group's ID if there is no error.
   *
   * @return null|string
   */
  public function getFullGroup(?string $idGroup = null): ?array
  {
    if ($this->check()) {
      if (!$this->group) {
        $this->group = $this->db->rselect(
          $this->class_cfg['tables']['groups'],
          $this->class_cfg['arch']['groups'],
          [$this->class_cfg['arch']['groups']['id'] => $idGroup ?: $this->id_group]
        );
      }

      return $this->group;
    }

    return null;
  }


  /**
   * Returns the user's group's ID if there is no error.
   *
   * @return null|string
   */
  public function getIdGroup(): ?string
  {
    if ($this->check()) {
      return $this->id_group;
    }

    return null;
  }


  public function getGroup(): ?array
  {
    if ($this->check()) {
      return $this->group;
    }

    return null;
  }


  /**
   * Expires an hotlink by setting the expire column to now.
   *
   * @return int
   */
  public function expireHotlink($id): int
  {
    if ($this->check()) {
      return $this->db->update(
        $this->class_cfg['tables']['hotlinks'],
        [$this->class_cfg['arch']['hotlinks']['expire'] => date('Y-m-d H:i:s')],
        [$this->class_cfg['arch']['hotlinks']['id'] => $id]
      );
    }

    return 0;
  }


  /**
   * Retrieves a user's ID from the hotlink's magic string.
   *
   * @param string $id
   * @param string $key
   * @return null|string
   */
  public function getIdFromMagicString(string $id, string $key): ?string
  {
    if ($val = $this->db->rselect(
      $this->class_cfg['tables']['hotlinks'], [
      'magic' => $this->class_cfg['arch']['hotlinks']['magic'],
      'id_user' => $this->class_cfg['arch']['hotlinks']['id_user'],
      'expire' => $this->class_cfg['arch']['hotlinks']['expire'],
      ],[
      $this->class_cfg['arch']['hotlinks']['id'] => $id
      ]
    )
    ) {
      if ($val['expire'] < date('Y-m-d H:i:s')) {
        if (method_exists($this, 'setError')) {
          $this->setError(27);
        }
      }
      elseif (self::isMagicString($key, $val['magic'])) {
        return $val['id_user'];
      }
    }

    return null;
  }


  /**
   * Checks if an error has been thrown or not.
   *
   * @return bool
   */
  public function check()
  {
    return $this->getError() ? false : true;
  }


  /**
   * Unauthenticates, resets the config and destroys the session.
   *
   * @return void
   */
  public function logout()
  {
    $this->auth = false;
    $this->cfg  = [];
    $this->closeSession(true);
  }


  /**
   * Returns an instance of the mailer class.
   *
   * @return Mail
   * @throws Exception
   */
  public function getMailer(): Mail
  {
    if (!$this->_mailer) {
      if (class_exists($this->class_cfg['mailer'])) {
        $this->_mailer = new $this->class_cfg['mailer']();
      }
      else {
        throw new Exception(X::_("Impossible to find the mailer class %s", (string)$this->class_cfg['mailer']));
      }
    }

    return $this->_mailer;
  }


  /**
   * Changes the password in the database.
   *
   * @return bool
   */
  public function forcePassword($pass): bool
  {
    if ($this->id) {
      return (bool)$this->db->insert(
        $this->class_cfg['tables']['passwords'], [
        $this->class_cfg['arch']['passwords']['pass'] => $this->_hash($pass),
        $this->class_cfg['arch']['passwords']['id_user'] => $this->id,
        $this->class_cfg['arch']['passwords']['added'] => date('Y-m-d H:i:s')
        ]
      );
    }

    return false;
  }


  /**
   * Returns the email of the given user or the current one.
   *
   * @return string|null
   */
  public function getEmail($usr = null): ?string
  {
    if ($this->auth) {
      if (\is_null($usr)) {
        $usr = $this->getSession();
      }
      elseif (str::isUid($usr) && ($mgr = $this->getManager())) {
        $usr = $mgr->getUser($usr);
      }

      if (isset($this->fields['email'], $usr[$this->fields['email']])) {
        return $usr[$this->fields['email']];
      }
    }

    return null;
  }


  /**
   * Adds a file to the tmp folder of the user and returns its path.
   *
   * @param string $file
   * @param string $name
   * @param bool   $move
   * @return string|null
   */
  public function addToTmp(string $file, string|null $name = null, $move = true):? string
  {
    if ($this->auth) {
      $fs   = new System();
      $path = $this->getTmpDir().microtime(true).'/';
      if ($fs->isFile($file) && $fs->createPath($path)) {
        $dest = $path.($name ?: X::basename($file));
        if ($move) {
          if ($fs->move($file, X::dirname($dest)) && $fs->rename(X::dirname($dest).'/'.X::basename($file), X::basename($dest))) {
            return $dest;
          }
        }
        elseif ($fs->copy($file, $dest)) {
          return $dest;
        }
      }
    }

    return null;
  }


  /**
   * Encrypts the given string.
   *
   * @param string $st
   * @return string|null
   */
  public function crypt(string $st): ?string
  {
    if ($enckey = $this->_get_encryption_key()) {
      return Enc::crypt($st, $enckey) ?: null;
    }

    return null;
  }


  /**
   * Decrypts the given string.
   *
   * @param string $st
   * @return string|null
   */
  public function decrypt(string $st): ?string
  {
    if ($enckey = $this->_get_encryption_key()) {
      return Enc::decrypt($st, $enckey) ?: null;
    }

    return null;
  }


  /**
   * Generates a random long string (16-32 chars) used as unique fingerprint.
   * @return string
   */
  public static function makeFingerprint(): string
  {
    return Str::genpwd(32, 16);
  }


  /**
   * Returns an array with a key and a magic string used for making hotlinks.
   *
   * @return array
   */
  public static function makeMagicString(): array
  {
    $key = self::makeFingerprint();
    return [
      'key' => $key,
      'hash' => hash('sha256', $key)
    ];
  }


  /**
   * Checks if a given string corresponds to the given hash.
   *
   * @param string $key  The key
   * @param string $hash The corresponding hash
   * @return bool
   */
  public static function isMagicString(string $key, string $hash): bool
  {
    return hash('sha256', $key) === $hash;
  }


  /**
   * Sets the error property once and for all.
   *
   * @param int $err error code
   * @return self
   */
  protected function setError(string $err, $code = null): self
  {
    $this->log([$err, $this->class_cfg['errors'][$err] ?? null], 'userError');
    if (!$this->error) {
      $this->error = $err;
    }

    return $this;
  }


  /**
   * Returns the first error in an array with the code and description.
   *
   * @return null|array
   */
  public function getError(): ?array
  {
    if ($this->error) {
      return [
        'code' => $this->error,
        'text' => $this->class_cfg['errors'][$this->error]
      ];
    }

    return null;
  }


  /**
   * @param string $access_token
   * @param string $refresh_token
   * @param int $expires_in
   * @param string $account_name
   * @return bool
   * @throws Exception
   */
  public function saveNewPermissionTokens(string $access_token, string $refresh_token, int $expires_in, string $account_name): bool
  {
    if ($this->id) {
      $account_exists = $this->db->count(
        $this->class_cfg['tables']['permission_accounts'],
        [
          $this->class_cfg['arch']['permission_accounts']['id_user'] => $this->id,
          $this->class_cfg['arch']['permission_accounts']['name']    => $account_name,
        ]
      );
      
      if ($account_exists > 0 ){
        throw new Exception(X::_('Account already exists!'));
      }
      
      if ($this->db->insert(
          $this->class_cfg['tables']['permission_accounts'], [
          $this->class_cfg['arch']['permission_accounts']['id_user'] => $this->id,
          $this->class_cfg['arch']['permission_accounts']['name']    => $account_name,
        ]
      )) {
        return (bool)$this->db->insert(
          $this->class_cfg['tables']['permission_tokens'], [
            $this->class_cfg['arch']['permission_tokens']['id_account']    => $this->db->lastId(),
            $this->class_cfg['arch']['permission_tokens']['access_token']  => $access_token,
            $this->class_cfg['arch']['permission_tokens']['refresh_token'] => $refresh_token,
            $this->class_cfg['arch']['permission_tokens']['expire']        => time() + $expires_in,
          ]
        );
      }
    }

    return false;
  }

  public function getPermissionAccountFromName(string $account_name)
  {
    if ($this->id) {
      return $this->db->rselect(
        $this->class_cfg['tables']['permission_accounts'],
        $this->class_cfg['arch']['permission_accounts'],
        [
          $this->class_cfg['arch']['permission_accounts']['id_user'] => $this->id,
          $this->class_cfg['arch']['permission_accounts']['name']    => $account_name,
        ]
      );
    }

    return false;
  }

  /**
   * @param string $account_name
   * @return array|false|null
   */
  public function getPermissionTokensFromAccountName(string $account_name)
  {
    if ($this->id) {
      if ($account = $this->getPermissionAccountFromName($account_name)) {
        return $this->db->rselect(
          $this->class_cfg['tables']['permission_tokens'],
          $this->class_cfg['arch']['permission_tokens'],
          [
            $this->class_cfg['arch']['permission_tokens']['id_account'] => $account[$this->class_cfg['arch']['permission_accounts']['id']],
          ]
        ); 
      }
    }
    
    return false;
  }

  public function updatePermissionTokens(string $account_name, string $access_token, string $refresh_token, int $expire_in)
  {
    if ($this->id && $account = $this->getPermissionAccountFromName($account_name)) {
        return $this->db->update(
          $this->class_cfg['tables']['permission_tokens'], [
          $this->class_cfg['arch']['permission_tokens']['access_token']   => $access_token,
          $this->class_cfg['arch']['permission_tokens']['refresh_token']  => $refresh_token,
          $this->class_cfg['arch']['permission_tokens']['expire']         => time() + $expire_in,
        ], [
            $this->class_cfg['arch']['permission_tokens']['id_account'] => $account[$this->class_cfg['arch']['permission_accounts']['id']]
          ]
        );
    }

    return false;
  }

  public function getApiRequestOutput()
  {
    return $this->api_request_output;
  }

  public function getApiNotificationsToken(string $idUser = ''): ?string
  {
    return $this->db->selectOne([
      'table' => $this->class_cfg['tables']['api_tokens'],
      'fields' => $this->class_cfg['arch']['api_tokens']['notifications_token'],
      'where' => [ 
        $this->class_cfg['arch']['api_tokens']['id_user'] => $idUser ?: $this->id
      ],
      'order' => [[
        'field' => $this->class_cfg['arch']['api_tokens']['last'],
        'dir' => 'DESC'
      ]]
    ]);
  }

  public function getPhoneNumber(string $idUser = ''): ?string
  {
    return $this->db->selectOne($this->class_cfg['table'], $this->class_cfg['arch']['users']['phone'], [
      $this->class_cfg['arch']['users']['id'] => $idUser ?: $this->id
    ]);
  }


  public function hasSkipVerification(string $id = ''): bool
  {
    if ($cfg = $this->db->selectOne(
      $this->class_cfg['tables']['users'],
      $this->class_cfg['arch']['users']['cfg'],
      [
        $this->class_cfg['arch']['users']['id'] => $id ?: $this->id
      ]
    )) {
      $cfg = json_decode($cfg);
      return !empty($cfg->skip_verification);
    }
    return false;
  }

  /**
   * @return Db db
   */
  public function getDbInstance(): Db
  {
    return $this->db;
  }

  protected function isVerifyPhoneNumberRequest(array $params)
  {
    $f = $this->class_cfg['fields'];

    return isset($params[$f['phone_number']], $params[$f['phone_verification_code']], $params[$f['device_uid']], $params[$f['token']])
      && $params[$f['action']] === 'verifyCode';
  }

  protected function isPhoneNumberCodeSendingRequest(array $params)
  {
    $f = $this->class_cfg['fields'];

    return isset($params[$f['phone_number']], $params[$f['device_uid']], $params[$f['token']])
      && $params[$f['action']] === 'sendSmsCode';
  }

  protected function isTokenLoginRequest(array $params): bool
  {
    $f = $this->class_cfg['fields'];
    return X::hasProps($params, [$f['token'], $f['device_uid']], true);
  }

  protected function isToken(): bool
  {
    return (bool)$this->class_cfg['tables']['api_tokens'];
  }


  /**
   * @param string $phone_number
   * @return array|null
   */
  protected function findByPhoneNumber(string $phone_number)
  {
    try {
      $phone = PhoneNumber::parse($phone_number);
    }
    catch (PhoneNumberParseException $e) {
      return false;
    }
    $phone_number = $phone->format(PhoneNumberFormat::E164);
    $id_user = $this->db->selectOne(
      $this->class_cfg['tables']['users'],
      $this->class_cfg['arch']['users']['id'],
      [
        $this->class_cfg['arch']['users']['login'] => $phone_number
      ]
    );
    $user = $id_user ? $this->getInfo($id_user) : null;
    return !empty($user) && !$this->hasSkipVerification($user[$this->class_cfg['arch']['users']['id']]) && !$phone->isValidNumber() ? false : $user;
  }


  /**
   * @param string $token
   * @return array|null
   */
  protected function findUserByApiTokenAndDeviceUid(string $token, $device_uid)
  {
    if ($user_id = $this->getUserByTokenAndDeviceUid($token, $device_uid)) {
      return $this->getInfo($user_id);
    }

    return null;
  }

  protected function getInfo(string $idUser): ?array
  {
    $info = $this->db->rselect(
      $this->class_cfg['tables']['users'],
      $this->class_cfg['arch']['users'],
      [
        $this->class_cfg['arch']['users']['id'] => $idUser
      ]
    ) ?: null;
    if ($info) {
      $info['group'] = $this->getFullGroup($info['id_group']);
    }

    return $info;
  }

  /**
   * @param string|null $code
   * @return int|null
   */
  protected function updatePhoneVerificationCode($phone_number, ?string $code): bool
  {
    if ($oldCfg = $this->db->selectOne($this->class_cfg['tables']['users'], $this->class_cfg['arch']['users']['cfg'], [
      $this->class_cfg['arch']['users']['id'] => $this->id
    ])) {
      $oldCfg = json_decode($oldCfg, true);
    }
    else {
      $oldCfg = [];
    }
    $cfg = json_encode(\array_merge($oldCfg, ['phone_verification_code' => $code]));
    try {
      $phone = PhoneNumber::parse($phone_number);
    }
    catch (PhoneNumberParseException $e) {
      return false;
    }
    
    if (!$this->hasSkipVerification() && !$phone->isValidNumber()) {
      return false;
    }


    $number = $phone->format(PhoneNumberFormat::E164);

    return (bool)$this->db->update(
      $this->class_cfg['tables']['users'],
      [
        $this->class_cfg['arch']['users']['login'] => $number,
        $this->class_cfg['arch']['users']['phone'] => $number,
        $this->class_cfg['arch']['users']['cfg'] => $cfg
      ],
      [$this->class_cfg['arch']['users']['id'] => $this->id]
    );
  }



  protected function verifyTokenAndDeviceUid($device_uid, $token)
  {
    return $this->db->count(
      $this->class_cfg['tables']['api_tokens'],
      [
        $this->class_cfg['arch']['api_tokens']['token']      => $token,
        $this->class_cfg['arch']['api_tokens']['device_uid'] => $device_uid,
      ]
    );
  }

  protected function getUserByTokenAndDeviceUid($token, $device_uid)
  {
    return $this->db->selectOne(
      $this->class_cfg['tables']['api_tokens'],
      $this->class_cfg['arch']['api_tokens']['id_user'],
      [
        $this->class_cfg['arch']['api_tokens']['token']      => $token,
        $this->class_cfg['arch']['api_tokens']['device_uid'] => $device_uid,
      ]
    );
  }

  protected function updateApiTokenUserByTokenDevice(string $token, string $deviceUid, string $idUser, string $deviceLang = ''): bool
  {
    if (!empty($token) && !empty($deviceUid) && !empty($idUser)) {
      return (bool)$this->db->update(
        $this->class_cfg['tables']['api_tokens'],
        [
          $this->class_cfg['arch']['api_tokens']['id_user'] => $idUser,
          $this->class_cfg['arch']['api_tokens']['device_lang'] => $deviceLang
        ],
        [
          $this->class_cfg['arch']['api_tokens']['token'] => $token,
          $this->class_cfg['arch']['api_tokens']['device_uid'] => $deviceUid
        ]
      );
    }
    return false;
  }

  /**
   * Retrieves the encryption key from database if not defined and saves it.
   *
   * @return string|null
   */
  private function _get_encryption_key(): ?string
  {
    if (is_null($this->_encryption_key)) {
      if ($this->auth) {
        $this->_encryption_key = $this->db->selectOne(
          $this->class_cfg['table'],
          $this->class_cfg['arch']['users']['enckey'],
          ['id' => $this->id]
        );
        if (!$this->_encryption_key) {
          $this->_encryption_key = Str::genpwd(32, 16);
          $this->db->update(
            $this->class_cfg['table'],
            [$this->class_cfg['arch']['users']['enckey'] => $this->_encryption_key],
            ['id' => $this->id]
          );
        }
      }
    }

    return $this->_encryption_key;
  }


   /**
    * Use the configured hash function to encrypt a password string.
    *
    * @param string $st The string to crypt
    * @return string
    */
  private function _hash(string $st): string
  {
    if (empty($this->class_cfg['encryption']) || !function_exists($this->class_cfg['encryption'])) {
      return hash('sha256', $st);
    }

    return $this->class_cfg['encryption']($st);
  }


   /**
    * Defines user's directory and constant BBN_USER_PATH if not done yet.
    *
    * @param bool $create If true creates it and remove temp files if any
    * @return self
    */
  private function _init_dir(bool $create = false): self
  {
    if (\defined('BBN_DATA_PATH') && $this->getId()) {
      $this->path     = Mvc::getUserDataPath($this->getId());
      $this->tmp_path = Mvc::getUserTmpPath($this->getId());
      if (!\defined('BBN_USER_PATH')) {
        define('BBN_USER_PATH', $this->path);
      }

      if ($create && !empty($this->path) && !empty($this->tmp_path)) {
        Dir::createPath($this->path);
        Dir::createPath($this->tmp_path);
        Dir::delete($this->tmp_path, false);
      }
    }

    return $this;
  }
}
