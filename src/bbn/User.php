<?php

/**
 * @package user
 */

namespace bbn;

use AllowDynamicProperties;
use Exception;
use bbn\X;
use bbn\Str;
use bbn\File\System;
use bbn\Models\Tts\Retriever;
use bbn\Models\Tts\DbActions;
use bbn\Models\Cls\Basic;
use bbn\User\Common;
use bbn\User\Implementor;
use bbn\User\Manager;
use bbn\Models\Tts\DbUauth;
use bbn\User\Session;
use bbn\Appui\Option;
use bbn\Db;
use bbn\Appui\Database;
use bbn\Db\Languages\Sqlite;
use bbn\Cache;

/**
 * A user authentication Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Authentication
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.2r89
 * @todo Groups and hotlinks features
 * @todo Implement Cache for session requests' results?
 */

 #[AllowDynamicProperties]
 
 class User extends Basic implements Implementor
{
  use Retriever;
  use DbActions;
  use Common;

  /** @var array */
  protected static $default_class_cfg = [
    'errors' => [
      0 => 'login failed',
      2 => 'password sent',
      3 => 'no email such as',
      4 => 'too many attempts',
      5 => 'impossible to create the user',
      6 => 'wrong user and/or password',
      7 => 'different passwords',
      8 => 'less than 5 mn between emailing password',
      9 => 'user already exists',
      10 => 'problem during user creation',
      11 => 'no salt in session',
      12 => 'login and password are mandatory',
      13 => 'impossible to save the session',
      14 => 'impossible to retrieve the session',
      15 => 'no session in memory',
      16 => 'impossible to add session in the database',
      17 => 'non matching salt',
      18 => 'incorrect magic string',
      19 => 'wrong fingerprint',
      20 => 'invalid token',
      21 => 'invalid phone number',
      22 => 'impossible to update the phone number or the verification code',
      23 => 'unknown phone number',
      24 => 'invalid verification code',
      25 => 'you have exhausted the number of hotlinks sent, try again later',
      26 => 'An email has been sent in order to reset your password',
      27 => 'The hotlink is expired'
    ],
    'table' => 'bbn_users',
    'tables' => [
      'groups' => 'bbn_users_groups',
      'hotlinks' => 'bbn_users_hotlinks',
      'passwords' => 'bbn_users_passwords',
      'sessions' => 'bbn_users_sessions',
      'tokens' => 'bbn_users_tokens',
      'api_tokens' => 'bbn_users_api_tokens', // String because array_flip() in DbActions only works with integers and string
      'access_tokens' => 'bbn_users_access_tokens',
      'users' => 'bbn_users',
      'permission_accounts' => 'bbn_users_permission_accounts',
      'permission_tokens' => 'bbn_users_permission_account_tokens'
    ],
    'arch' => [
      'groups' => [
        'id' => 'id',
        'group' => 'group',
        'type' => 'type',
        'code' => 'code',
        'home' => 'home',
        'cfg' => 'cfg'
      ],
      'hotlinks' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'magic' => 'magic',
        'expire' => 'expire'
      ],
      'passwords' => [
        'id_user' => 'id_user',
        'pass' => 'pass',
        'added' => 'added',
      ],
      'sessions' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'sess_id' => 'sess_id',
        'ip_address' => 'ip_address',
        'user_agent' => 'user_agent',
        'opened' => 'opened',
        'creation' => 'creation',
        'last_activity' => 'last_activity',
        'cfg' => 'cfg',
      ],
      'tokens' => [
        'id' => 'id',
        'id_session' => 'id_session',
        'content' => 'content',
        'creation' => 'creation',
        'dt_creation' => 'dt_creation',
        'last' => 'last',
        'dt_last' => 'dt_last'
      ],
      'api_tokens' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'token' => 'token',
        'creation' => 'creation',
        'last' => 'last',
        'device_uid' => 'device_uid',
        'device_platform' => 'device_platform',
        'device_lang' => 'device_lang',
        'notifications_token' => 'notifications_token'
      ],
      'access_tokens' => [
        'id_user' => 'id_user',
        'token' => 'token',
        'pass' => 'pass',
        'validity' => 'validity'
      ],
      'users' => [
        'id' => 'id',
        'id_group' => 'id_group',
        'email' => 'email',
        'username' => 'username',
        'phone' => 'phone',
        'login' => 'login',
        'admin' => 'admin',
        'dev' => 'dev',
        'theme' => 'theme',
        'cfg' => 'cfg',
        'active' => 'active',
        'enckey' => 'enckey',
        //'phone_verification_code' => 'phone_verification_code'
      ],
      'permission_accounts' => [
        'id'      => 'id',
        'id_user' => 'id_user',
        'name'    => 'name' // The combination of 'name' and 'id_user' should be unique.
      ],
      'permission_tokens' => [
        'id'            => 'id',
        'id_account'    => 'id_account',
        'access_token'  => 'access_token',
        'refresh_token' => 'refresh_token',
        'expire'        => 'expire'
      ]
    ],
    'fields' => [
      'user' => 'user',
      'pass' => 'pass',
      'salt' => 'appui_salt',
      'key' => 'key',
      'id' => 'id',
      'pass1' => 'pass1',
      'pass2' => 'pass2',
      'action' => 'action',
      'token'  => 'appui_token',
      'access_token' => 'appui_access_token',
      'access_token_pass' => 'appui_access_token_pass',
      'device_uid'  => 'device_uid',
      'device_lang' => 'device_lang',
      'phone_number' => 'phone_number',
      'phone_verification_code'  => 'phone_verification_code'
    ],
    /**
     * Password saving encryption
     * @var string
     */
    'encryption' => 'sha1',
    /**
     * Additional conditions when querying the users' table
     * @var array
     */
    'conditions' => [],
    /**
     * Number of times a user can try to log in the period
     * @var integer
     */
    'max_attempts' => 10,
    /**
     * Number of times a user can try to log in the period
     * @var integer
     */
    'verification_code_length' => 4,
    /**
     * User ban's length in minutes after max attempts is reached
     * @var integer
     */
    'max_sessions' => 5,
    /**
     * Sets if the hotlinks features should be in used
     * @var bool
     */
    'hotlinks' => false,
    'show' => 'username',
    'mailer' => '\\bbn\\Mail',
    'ip_address' => true
  ];

  /** @var bool Will be true when the user has just logged in. */
  private $_just_login = false;

  private $_encryption_key = null;

  /** @var string The name of the session index in for session data */
  protected $sessIndex = 'bbn_session';

  /** @var string The name of the session index in for user data */
  protected $userIndex = 'bbn_user';

  protected $password_reset = false;

  /** @var User\Session */
  protected $session = null;

  /** @var string */
  protected $user_agent;

  /** @var string */
  protected $ip_address;

  /** @var string */
  protected $email;

  /** @var string */
  protected $accept_lang;

  /** @var string */
  protected $sql;

  /** @var int */
  protected $id;

  /** @var array */
  protected $data = [];

  /** @var int */
  protected $id_group;

  /** @var array */
  protected $group;

  /** @var mixed */
  protected $alert;

  /** @var array */
  protected $cfg;

  /** @var array */
  protected $sess_cfg;

  /** @var Db */
  protected $db;

  /** @var mixed */
  public $prev_time;

  /** @var array $class_cfg */
  protected $class_cfg;

  /** @var string */
  protected $cache_path;

  const MAX_EMPTY_ATTEMPTS = 5;


  /**
   * User constructor.
   *
   * @param Db    $db
   * @param array $cfg
   * @param array $params
   */
  public function __construct(Db $db, array $params = [], array $cfg = [])
  {
    // The database connection
    $this->db = $db;

    // Setting up the class configuration
    $this->initClassCfg($cfg);

    $f = &$this->class_cfg['fields'];
    self::retrieverInit($this);

    if ($this->isToken() && !empty($params[$f['token']])) {
      if (!isset($this->class_cfg['tables']['api_tokens'])) {
        throw new Exception(X::_('The class %s is not configured properly to work with API tokens', get_class($this)));
      }

      if ($this->isPhoneNumberCodeSendingRequest($params)) {
        // Verify that the received token is associated with the device uid
        if (!($user_id = $this->getUserByTokenAndDeviceUid($params[$f['token']], $params[$f['device_uid']]))) {
          $this->setError(20);
          $this->api_request_output =  [
            'success' => false,
            'error'   => X::_('Invalid token'),
            'errorCode' => 20
          ];
          return;
        }

        // Check if the phone number is already registered
        if (($exUser = $this->findByPhoneNumber($params[$f['phone_number']]))
          && ($exUser[$f['id']] !== $user_id)
          && $this->updateApiTokenUserByTokenDevice(
            $params[$f['token']],
            $params[$f['device_uid']],
            $exUser[$f['id']],
            !empty($params[$f['device_lang']]) ? str_replace('"', '', $params[$f['device_lang']]) : ''
          )
        ) {
          if (!$this->db->selectOne($this->class_cfg['table'], $this->class_cfg['arch']['users']['login'], [
            $this->class_cfg['arch']['users']['id'] => $user_id
          ])) {
            $this->db->delete($this->class_cfg['table'], [
              $this->class_cfg['arch']['users']['id'] => $user_id
            ]);
          }
          $user_id = $exUser[$f['id']];
        }

        $this->id = $user_id;
        // Generate a code
        $code = random_int(1001, 9999);

        try {
          $phone = \Brick\PhoneNumber\PhoneNumber::parse($params[$f['phone_number']]);
        } catch (\Brick\PhoneNumber\PhoneNumberParseException $e) {
          $this->setError(21);
          $this->api_request_output = [
            'success' => false,
            'error' => X::_('Invalid phone number'),
            'errorCode' => 21
          ];
          return;
        }

        if (
          !$this->hasSkipVerification()
          && !$phone->isValidNumber()
        ) {
          $this->setError(21);
          $this->api_request_output = [
            'success' => false,
            'error' => X::_('Invalid phone number'),
            'errorCode' => 21
          ];
          return;
        }

        // Save it
        if ($this->updatePhoneVerificationCode($params[$f['phone_number']], $code)) {
          // Send the sms with code here
          $this->api_request_output = [
            'success' => true,
            'phone_verification_code' => $code
          ];
          return;
        } else {
          $this->setError(22);
          return;
        }
      } elseif ($this->isVerifyPhoneNumberRequest($params)) {
        // Verify that the received token is associated to the device uid
        if (!$this->verifyTokenAndDeviceUid($params[$f['device_uid']], $params[$f['token']])) {
          $this->setError(20);
          return;
        }

        // find the user using phone_number in db
        $user = $this->findByPhoneNumber($params[$f['phone_number']]);

        if (!$user) {
          $this->setError(23);
          return;
        }

        $this->id = $user[$this->class_cfg['arch']['users']['id']];
        $this->id_group = $user[$this->class_cfg['arch']['users']['id_group']];

        if (!$this->hasSkipVerification()) {
          // Verify that the code is correct
          $user_cfg = json_decode($user[$this->class_cfg['arch']['users']['cfg']], true);

          if (
            !$user_cfg
            || !isset($user_cfg['phone_verification_code'])
            || ((string)$user_cfg['phone_verification_code'] !== (string)$params[$f['phone_verification_code']])
          ) {
            $this->setError(24);
            return;
          }
        }

        // Update verification code to null
        $this->updatePhoneVerificationCode($params[$f['phone_number']], null);

        // Generate a new token
        $new_token = Str::genpwd(32, 16);

        // Update user id and the new token in the row with the old token and device uid.
        $this->db->update(
          $this->class_cfg['tables']['api_tokens'],
          [
            $this->class_cfg['arch']['api_tokens']['id_user']  => $user[$this->class_cfg['arch']['users']['id']],
            $this->class_cfg['arch']['api_tokens']['token']    => $new_token,
          ],
          [
            $this->class_cfg['arch']['api_tokens']['token']      => $params[$f['token']],
            $this->class_cfg['arch']['api_tokens']['device_uid'] => $params[$f['device_uid']],
          ]
        );

        // Send the new token here
        $this->api_request_output =  [
          'token'   => $new_token,
          'success' => true
        ];
      } elseif ($this->isTokenLoginRequest($params)) {
        // Find the token associated to the device uid in db then get it's associated user.
        if (!$user = $this->findUserByApiTokenAndDeviceUid($params[$f['token']], $params[$f['device_uid']])) {
          $this->setError(20);
          $this->api_request_output =  [
            'success' => false,
            'error'   => X::_('Invalid token'),
            'errorCode' => 20
          ];
          return;
        }

        // Update device_lang and last
        $toUdp = [
          $this->class_cfg['arch']['api_tokens']['last'] => date('Y-m-d H:i:S')
        ];
        if (isset($params[$f['device_lang']])) {
          $toUdp[$this->class_cfg['arch']['api_tokens']['device_lang']] = $params[$f['device_lang']];
        }
        $this->db->update($this->class_cfg['tables']['api_tokens'], $toUdp, [
          $this->class_cfg['arch']['api_tokens']['token']      => $params[$f['token']],
          $this->class_cfg['arch']['api_tokens']['device_uid'] => $params[$f['device_uid']]
        ]);

        // Now the user is authenticated
        $this->auth = true;
        $this->id = $user[$this->class_cfg['arch']['users']['id']];
        $this->id_group = $user[$this->class_cfg['arch']['users']['id_group']];

        $this->api_request_output = [
          'token'   => $params[$f['token']],
          'success' => true
        ];
      }
    }
    else {
      // The client environment variables
      $this->user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? (isset($_SERVER['argv'][1]) ? 'CLI' : 'Unknown');
      $this->ip_address  = $this->class_cfg['ip_address'] && isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : (isset($_SERVER['shell']) ? '127.0.0.1' : '');
      $this->accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ($_SERVER['LANG'] ?? '');
      // Creating the session's variables if they don't exist yet
      $this->_init_session();

      // CLI user
      if (x::isCli() && isset($params['id'])) {
        $this->id = $params['id'];
        $this->auth = true;
      }

      // The user logs in
      if ($this->isLoginRequest($params)) {
        /** @todo separate credentials and salt checking */
        if (!empty($this->sess_cfg['fingerprint'])
            && $this->getPrint($this->_get_session('fingerprint')) === $this->sess_cfg['fingerprint']
        ) {
          /** @todo separate credentials and salt checking */
          $this->_check_credentials($params);
        } else {
          $this->setError(19);
          $this->session->destroy();
        }
      }

      /** @todo revise the process: dying is not the solution! */
      // The user is not known yet
      elseif ($this->isResetPasswordRequest($params)) {
        if ($id = $this->getIdFromMagicString($params[$f['id']], $params[$f['key']])) {
          $this->password_reset = true;
          if (($params[$f['pass1']] === $params[$f['pass2']])) {
            $this->expireHotlink($params[$f['id']]);
            $this->id = $id;
            $this->forcePassword($params[$f['pass2']]);
            $this->session->set([]);
          }
          else {
            $this->setError(7);
          }
        }
        elseif ($this->check()) {
          $this->setError(18);
        }
      }
      elseif (!empty($params[$f['access_token']])
        && !empty($params[$f['access_token_pass']])
        && ($idUser = $this->getIdByAccessToken($params[$f['access_token']], $params[$f['access_token_pass']]))
      ) {
        $this->id = $idUser;
        $this->id_group = $this->db->selectOne(
          $this->class_cfg['tables']['users'],
          $this->class_cfg['arch']['users']['id_group'],
          [$this->class_cfg['arch']['users']['id'] => $idUser]
        );
        $this->auth = true;
      }
      else {
        $this->checkSession();
      }
    }
  }

  /**
   * Checks if the it's a login request.
   *
   * @param array $params
   * @return bool
   */
  protected function isLoginRequest(array $params)
  {
    $f = $this->class_cfg['fields'];

    return isset($params[$f['user']], $params[$f['pass']], $params[$f['salt']]);
  }

  /**
   * Checks if it's a reset password request.
   *
   * @param array $params
   * @return bool
   */
  protected function isResetPasswordRequest(array $params)
  {
    $f = $this->class_cfg['fields'];

    return isset(
      $params[$f['key']],
      $params[$f['id']],
      $params[$f['pass1']],
      $params[$f['pass2']],
      $params[$f['action']]
    )
      && $params[$f['action']] === 'init_password';
  }


  public function isReset(): bool
  {
    return $this->password_reset;
  }


  /**
   * Returns the salt string kept in session.
   *
   * @return null|string
   */
  public function getSalt(): ?string
  {
    return $this->_get_session('salt');
  }


  /**
   * Confronts the given string with the salt string kept in session.
   *
   * @return bool
   */
  public function checkSalt(string $salt): bool
  {
    return $this->getSalt() === $salt;
  }


  public function getLastActivity(?string $id_session = null): ?string
  {
    if ($this->checkSession() && $id_session) {
      $filter = [
        $this->class_cfg['arch']['sessions']['id_user'] => $this->getId()
      ];
      if ($id_session) {
        $filter[$this->class_cfg['arch']['sessions']['sess_id']] = $id_session;
      }

      $last = $this->db->selectOne(
        $this->class_cfg['tables']['sessions'],
        'MAX(' . $this->class_cfg['arch']['sessions']['last_activity'] . ')',
        $filter
      );

      return $last ?: null;
    }

    return null;

  }


  /**
   * Returns the current user's configuration.
   *
   * @param string $attr
   * @return mixed
   */
  public function getCfg($attr = '')
  {
    if ($this->check()) {
      if (!$this->cfg) {
        $this->cfg = $this->session->get('cfg');
      }

      if (empty($attr)) {
        return $this->cfg;
      }

      if (isset($this->cfg[$attr])) {
        return $this->cfg[$attr];
      }
    }

    return null;
  }


  /**
   * Stores or deletes data in the object for the current authenticated user.
   *
   * @param string|array $index The name of the index to set, or an associative array of key/values
   * @param mixed        $data  The data to store; if null the given index will be unset
   *
   * @return self Chainable
   */
  public function setData($index, $data = null): self
  {
    if (!$this->auth) {
      throw new Exception(X::_("Impossible to store data on an unauthenticated user"));
    }

    if (is_array($index) && X::isAssoc($index)) {
      foreach ($index as $k => $v) {
        // Unsetting if null
        if (is_null($v) && array_key_exists($k, $this->data)) {
          unset($this->data[$k]);
        } else {
          $this->data[$k] = $v;
        }
      }
    } elseif (is_string($index)) {
      $this->data[$index] = $data;
    } else {
      throw new Exception(X::_("Invalid parameters for function setData in user class"));
    }

    return $this;
  }


  /**
   * Changes the data in the user's table.
   *
   * @param array $d The new data
   * @return bool
   */
  public function updateInfo(array $d): bool
  {
    if ($this->checkSession()) {
      $update = [];
      foreach ($d as $key => $val) {
        if (($key !== $this->fields['id'])
          && ($key !== $this->fields['cfg'])
          && ($key !== 'auth')
          && ($key !== 'admin')
          && ($key !== 'dev')
          && ($key !== 'pass')
          && ($key !== 'res')
        ) {
          $update[$key] = $val;
        }
      }

      if (\count($update) > 0) {
        $r = (bool)$this->dbTraitUpdate($this->getId(), $update);
        /** @todo Why did I do this?? */
        if ($r) {
          /** @todo WTF?? */
          $this->setSession(['cfg' => false]);
          $this->_user_info();
        }
      }
      return $r ?? false;
    }

    return false;
  }


  /**
   * Encrypts the given string to match the password.
   *
   * @param string $st
   * @return string
   */
  public function getPassword(string $st): string
  {
    return $this->_hash($st);
  }


  /**
   * Returns true after the log in moment.
   *
   * @return bool
   */
  public function isJustLogin(): bool
  {
    return (bool)$this->_just_login;
  }


  /**
   * Sets the given attribute(s) in the user's session.
   *
   * @return self
   */
  public function setSession($attr): self
  {
    if ($this->session->has($this->userIndex)) {
      $args = \func_get_args();
      if ((\count($args) === 2) && \is_string($args[0])) {
        $attr = [$args[0] => $args[1]];
      }

      if (is_array($attr)) {
        foreach ($attr as $key => $val) {
          if (\is_string($key)) {
            $this->session->set($val, $this->userIndex, $key);
          }
        }
      }
    }

    return $this;
  }


  /**
   * Unsets the given attribute(s) in the user's session if exists.
   *
   * @return self
   */
  public function unsetSession(): self
  {
    $args = \func_get_args();
    array_unshift($args, $this->userIndex);
    if ($this->session->has(...$args)) {
      $this->session->uset(...$args);
    }

    return $this;
  }


  /**
   * Returns session property from the session's user array (userIndex).
   *
   * @param null|string The property to get
   * @return mixed
   */
  public function getSession($attr = null)
  {
    if ($this->session && $this->session->has($this->userIndex)) {
      return $attr ? $this->session->get($this->userIndex, $attr) : $this->session->get($this->userIndex);
    }

    return null;
  }


  /**
   * Gets an attribute or the whole the "session" part of the session  (sessIndex).
   *
   * @param string|null $attr Name of the attribute to get.
   * @return mixed|null
   */
  public function getOsession($attr = null)
  {
    return $this->_get_session($attr);
  }


  /**
   * Sets an attribute the "session" part of the session (sessIndex).
   *
   * @return self
   */
  public function setOsession(): self
  {
    return $this->_set_session(...func_get_args());
  }


  /**
   * Checks if the given attribute exists in the user's session.
   *
   * @return bool
   */
  public function hasSession($attr): bool
  {
    return $this->session->has($this->userIndex, $attr);
  }


  /**
   * Updates last activity value for the session in database.
   *
   * @return self
   */
  public function updateActivity(): self
  {
    if (X::isCli()) {
      return $this;
    }
 
    if (($id_session = $this->getSessionDbId()) && $this->check()) {
      $p = &$this->class_cfg['arch']['sessions'];
      $this->db->update(
        $this->class_cfg['tables']['sessions'],
        [$p['last_activity'] => date('Y-m-d H:i:s')],
        [$p['id'] => $id_session]
      );
    } else {
      $this->setError(13);
    }

    return $this;
  }


  /**
   * Saves the session config in the database.
   *
   * @todo Use it only when needed!
   * @return self
   */
  public function saveSession(bool $force = false): self
  {
    $id_session = $this->getSessionDbId();
    if ($this->check()) {
      if ($id_session) {
        $p = &$this->class_cfg['arch']['sessions'];
        // It is normal this is sometimes not changing as different actions can happen in the same
        $time = time();
        if ($force || empty($this->sess_cfg['last_renew']) || ($time - $this->sess_cfg['last_renew'] >= 2)) {
          $this->sess_cfg['last_renew'] = $time;
          $this->db->update(
            $this->class_cfg['tables']['sessions'],
            [
              $p['id_user'] => $this->id,
              $p['sess_id'] => $this->session->getId(),
              $p['ip_address'] => $this->ip_address,
              $p['user_agent'] => $this->user_agent,
              $p['opened'] => 1,
              $p['last_activity'] => date('Y-m-d H:i:s', $time),
              $p['cfg'] => json_encode($this->sess_cfg)
            ],
            [$p['id'] => $id_session]
          );
        }
      } else {
        $this->setError(13);
      }
    }

    return $this;
  }


  /**
   * Closes the session in the database.
   *
   * @param bool $with_session If true deletes also the session information
   * @return self
   */
  public function closeSession($with_session = false): self
  {
    if ($this->id && !X::isCli()) {
      if ($this->session) {
        $p = &$this->class_cfg['arch']['sessions'];
        $this->db->update(
          $this->class_cfg['tables']['sessions'],
          [
            $p['ip_address'] => $this->ip_address,
            $p['user_agent'] => $this->user_agent,
            $p['opened'] => 0,
            $p['last_activity'] => date('Y-m-d H:i:s'),
            $p['cfg'] => json_encode($this->sess_cfg)
          ],
          [
            $p['id_user'] => $this->id,
            $p['sess_id'] => $this->session->getId()
          ]
        );
        if ($with_session) {
          $this->session->set([]);
        }
        
        $this->session->set([], $this->userIndex);
      }

      $this->auth     = false;
      $this->id       = null;
      $this->sess_cfg = null;
      $this->session  = null;
      Session::destroyInstance();
    }

    return $this;
  }


  /**
   * Returns false if the max number of connections attempts has been reached
   * @return bool
   */
  public function checkAttempts(): bool
  {
    if (!isset($this->cfg)) {
      return true;
    }

    if (isset($this->cfg['num_attempts']) && $this->cfg['num_attempts'] > $this->class_cfg['max_attempts']) {
      return false;
    }

    return true;
  }


  /**
   * Saves the user's config in the cfg field of the users' table.
   *
   * return self
   */
  public function saveCfg(): self
  {
    if ($this->check()) {
      $this->db->update(
        $this->class_cfg['tables']['users'],
        [$this->fields['cfg'] => json_encode($this->cfg)],
        [$this->fields['id'] => $this->id]
      );
    }

    return $this;
  }


  /**
   * Saves the attribute(s) values into the session config.
   *
   * return self
   */
  public function setCfg($attr): self
  {
    if (null !== $this->cfg) {
      $args = \func_get_args();
      if ((\count($args) === 2) && \is_string($attr)) {
        /** @var array $attr */
        $attr = [$args[0] => $args[1]];
      }

      if (is_array($attr)) {
        foreach ($attr as $key => $val) {
          if (\is_string($key)) {
            $this->cfg[$key] = $val;
          }
        }

        $this->setSession(['cfg' => $this->cfg]);
      }
    }

    return $this;
  }


  /**
   * Unsets the attribute(s) in the session config.
   *
   * @param $attr
   * @return self
   */
  public function unsetCfg($attr): self
  {
    if (null !== $this->cfg) {
      if (\is_string($attr)) {
        /** @var array $attr */
        $attr = [$attr];
      }

      if (is_array($attr)) {
        foreach ($attr as $key) {
          if (isset($key)) {
            unset($this->cfg[$key]);
          }
        }

        $this->setSession(['cfg' => $this->cfg]);
      }
    }

    return $this;
  }


  /**
   * Regathers information from the database.
   *
   * @return self
   */
  public function refreshInfo(): self
  {
    if ($this->check()) {
      $this->_user_info();
      $this->_sess_info();
    }

    return $this;
  }


  /**
   * Retrieves user's info from session if needed and checks if authenticated.
   *
   * @return bool
   */
  public function checkSession(): bool
  {
    if ($this->check()) {
      $this->_retrieve_session();
      return $this->auth;
    }

    return false;
  }


  /**
   * Checks whether the user is an admin or not.
   *
   * @return bool
   */
  public function isAdmin(): bool
  {
    return (bool)$this->getSession('admin');
  }


  /**
   * Checks whether the user is an (admin or developer) or not.
   *
   * @return bool
   */
  public function isDev(): bool
  {
    return (bool)($this->isAdmin() || (bool)$this->getSession('dev'));
  }


  /**
   * Gets a bbn\User\Manager instance.
   *
   * @return User\Manager
   */
  public function getManager()
  {
    return new User\Manager($this);
  }


  /**
   * Change the password in the database after checking the current one.
   *
   * @param string $old_pass The current password
   * @param string $new_pass The new password
   * @return bool
   */
  public function setPassword(string $old_pass, string $new_pass): bool
  {
    if ($this->auth) {
      $pwt         = $this->class_cfg['tables']['passwords'];
      $pwa         = $this->class_cfg['arch']['passwords'];
      $stored_pass = $this->db->selectOne(
        $pwt,
        $pwa['pass'],
        [
          $this->class_cfg['arch']['passwords']['id_user'] => $this->id
        ],
        [
          $this->class_cfg['arch']['passwords']['added'] => 'DESC'
        ]
      );
      if ($this->_check_password($old_pass, $stored_pass)) {
        return $this->forcePassword($new_pass);
      }
    }

    return false;
  }


  /**
   * Returns the full name of the given user or the current one.
   *
   * @return string|null
   */
  public function getName($usr = null): ?string
  {
    if ($this->auth) {
      if (\is_null($usr)) {
        $usr = $this->getSession();
      } elseif (str::isUid($usr)) {
        $mgr = $this->getManager();
        $usr = $mgr->getUser($usr);
      }

      if (isset($this->class_cfg['show'], $usr[$this->class_cfg['show']])) {
        return $usr[$this->class_cfg['show']];
      }
    }

    return null;
  }


  /**
   * Generates and insert a token in database.
   *
   * @return string|null
   */
  public function addToken(): ?string
  {
    if ($this->auth) {
      $token = Str::genpwd(32, 16);
      $f     = &$this->class_cfg['arch']['tokens'];
      if ($this->db->insert(
        $this->class_cfg['tables']['tokens'],
        [
          $f['id_session'] => $this->getSessionDbId(),
          $f['content'] => $token,
          $f['creation'] => X::microtime(),
          $f['last'] => X::microtime()
        ]
      )) {
        return $token;
      }
    }

    return null;
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
      } elseif (Str::isUid($usr) && ($mgr = $this->getManager())) {
        $usr = $mgr->getUser($usr);
      }

      if (isset($this->fields['email'], $usr[$this->fields['email']])) {
        return $usr[$this->fields['email']];
      }
    }

    return null;
  }


  /**
   * Returns the latest created connection, ie the current user's object.
   * @return self
   */
  public static function getUser(): ?self
  {
    return self::getInstance();
  }

  public function getDataPath(string|null $plugin = null): ?string
  {
    if ($this->check()) {
      return Mvc::getUserDataPath($this->id, $plugin);
    }

    return null;
  }

  public function getTmpPath(string|null $plugin = null): ?string
  {
    if ($this->check()) {
      return Mvc::getUserTmpPath($this->id, $plugin);
    }

    return null;
  }


  public function getLocaleDatabase(?string $idUser = null, bool $createIfNotExists = true): ?Db
  {
    /** @var Option $options */
    $options = Option::getInstance();
    if (empty($options)) {
      throw new Exception(X::_('Impossible to get the options class instance'));
    }

    $idHost = $options->fromCode('BBN_USER_PATH', 'connections', 'sqlite', 'engines', 'database', 'appui');
    if (empty($idHost)) {
      throw new Exception(X::_('Impossible to find the SQLite host for user\'s database'));
    }

    $dbName = 'locale_' . $this->id . '.sqlite';
    if (!empty($idUser) && Str::isUid($idUser)) {
      $dbName = 'locale_' . $idUser . '.sqlite';
      $idHost = str_replace($this->id, $idUser, Sqlite::getHostPath($idHost));
    }

    if (!Sqlite::hasHostDatabase($idHost, $dbName)) {
      if (!$createIfNotExists) {
        return null;
      }

      Sqlite::createDatabaseOnHost($dbName, $idHost);
    }

    if (!Sqlite::hasHostDatabase($idHost, $dbName)) {
      throw new Exception(X::_('Impossible to find the user\'s database'));
    }

    $d = new Database($this->db);
    return $d->connection($idHost, 'sqlite', $dbName);

  }


  /**
   * Gets the cache path for the user.
   *
   * @return string|null
   */
  public function getCachePath()
  {
    if (empty($this->cache_path)) {
      $this->cacheInit();
    }

    return $this->cache_path;
  }


  /**
   * Checks if a cache file exists for the user.
   *
   * @param string $path The path of the cache file, relative to the user's cache folder
   * @return bool
   */
  public function hasCache(string $path): bool
  {
    return $this->cacheInit() && (bool)$this->getCache($path, true);
  }


  /**
   * Gets a cache file for the user.
   *
   * @param string $key The path of the cache file, relative to the user's cache folder
   * @return mixed
   */
  public function getCache(string $key, bool $raw = false): mixed
  {
    if ($this->cacheInit()
      && ($file = Cache::_file($key, $this->getCachePath()))
    ) {
      $fs = new System();
      if ($fs->isFile($file)
        && ($t = $fs->getContents($file))
        && ($t = json_decode($t, true))
      ) {
        if (empty($t['ttl'])
          || empty($t['expire'])
          || ($t['expire'] > time())
        ) {
          return $raw ? $t : $t['value'];
        }
        else {
          $this->deleteCache($key);
        }
      }
    }

    return null;
  }


  /**
   * Sets a cache file for the user.
   *
   * @param string $key The path of the cache file, relative to the user's cache folder
   * @param mixed  $val The value to store
   * @param int    $ttl Time to live in seconds (0 for infinite)
   * @return bool
   */
  public function setCache(string $key, $val, $ttl = null): bool
  {
    $fs = new System();
    if ($this->cacheInit()
      && ($file = Cache::_file($key, $this->getCachePath()))
      && $fs->createPath(X::dirname($file))
    ) {
      $ttl = Cache::ttl($ttl);
      $value = [
        'timestamp' => microtime(1),
        'hash' => Cache::makeHash($val),
        'expire' => $ttl ? time() + $ttl : 0,
        'ttl' => $ttl,
        'value' => $val
      ];
      return (bool)$fs->putContents($file, json_encode($value, JSON_PRETTY_PRINT));
    }

    return false;
  }


  /**
   * Deletes a cache file for the user.
   *
   * @param string $key The path of the cache file, relative to the user's cache folder
   * @return bool
   */
  public function deleteCache(string $key): bool
  {
    return $this->cacheInit()
      && ($file = Cache::_file($key, $this->getCachePath()))
      && ($fs = new System())
      && $fs->delete($file);
  }


  /**
   * Completes the steps for a full authentication of the user.
   *
   * @param string $id
   * @return self
   */
  protected function logIn($id): self
  {
    $this->error = null;
    if ($this->check() && $id) {
      $this->_authenticate($id)->_user_info(true)->_init_dir(true)->saveSession();
    }

    return $this;
  }


  /**
   * Returns a "print" based on the user agent + the fingerprint.
   *
   * @param null|string $fp
   * @return null|string
   */
  protected function getPrint(string|null $fp = null): ?string
  {
    if (!$fp) {
      $fp = $this->_get_session('fingerprint');
    }

    if ($fp) {
      return sha1($this->user_agent . $this->accept_lang . $fp);
    }

    return null;
  }


  /**
   * Returns the database ID for the session's row if it is in the session.
   *
   * @return null|string
   */
  protected function getSessionDbId(): ?string
  {
    return $this->_get_session('id_session');
  }


  /**
   * Increments the num_attempt variable (after unsuccessful login attempt).
   *
   * @return self
   */
  protected function recordAttempt(): self
  {
    $this->cfg['num_attempts'] = isset($this->cfg['num_attempts']) ? $this->cfg['num_attempts'] + 1 : 1;
    $this->_set_session('num_attempts', $this->cfg['num_attempts']);
    $this->saveSession();
    return $this;
  }


  /**
   * Returns the user's ID from the magic string.
   */
  protected function getIdByAccessToken(string $accessToken, string $accessTokenPass): ?string
  {
    return $this->db->selectOne([
      'table' => $this->class_cfg['tables']['access_tokens'],
      'fields' => $this->class_cfg['arch']['access_tokens']['id_user'],
      'where' => [[
        'field' => $this->class_cfg['arch']['access_tokens']['token'],
        'value' => $accessToken
      ], [
        'field' => $this->class_cfg['arch']['access_tokens']['pass'],
        'value' => \bbn\Util\Enc::decrypt64($accessTokenPass)
      ], [
        'logic' => 'OR',
        'conditions' => [[
          'field' => $this->class_cfg['arch']['access_tokens']['validity'],
          'operator' => 'isnull'
        ], [
          'field' => $this->class_cfg['arch']['access_tokens']['validity'],
          'operator' => '<=',
          'value' => date('Y-m-d H:i:s')
        ]]
      ]]
    ]);
  }


  /**
   * Gets or creates (also in database) the user's session for the first time.
   *
   * @return self
   */
  protected function _init_session($defaults = []): self
  {
    // Getting or creating the session is it doesn't exist yet
    /** @var User\Session */
    $this->session = User\Session::getInstance();
    if (!$this->session) {
      $session_cls   = defined('BBN_SESSION')
        && is_string(constant('BBN_SESSION'))
        && class_exists(constant('BBN_SESSION')) ? constant('BBN_SESSION') : '\\bbn\\User\\Session';
      $this->session = new $session_cls($defaults);
    }

    /** @var int $id_session The ID of the session row in the DB */
    if (
      !($id_session = $this->getSessionDbId())
      || !($tmp = $this->db->selectOne(
        $this->class_cfg['tables']['sessions'],
        $this->class_cfg['arch']['sessions']['cfg'],
        [$this->class_cfg['arch']['sessions']['id'] => $id_session]
      ))
    ) {
      /** @var string $salt */
      $salt = self::makeFingerprint();

      /** @var string $fingerprint */
      $fingerprint = self::makeFingerprint();

      /** @var array $p The fields of the sessions table */
      $p = &$this->class_cfg['arch']['sessions'];

      $this->sess_cfg = [
        'fingerprint' => $this->getPrint($fingerprint),
        'last_renew' => time()
      ];

      $id_session = $this->session->getId();

      // Inserting the session in the database
      if (
        $id_session && $this->db->insert(
          $this->class_cfg['tables']['sessions'],
          [
            $p['sess_id'] => $id_session,
            $p['ip_address'] => $this->ip_address,
            $p['user_agent'] => $this->user_agent,
            $p['opened'] => 1,
            $p['last_activity'] => date('Y-m-d H:i:s'),
            $p['creation'] => date('Y-m-d H:i:s'),
            $p['cfg'] => json_encode($this->sess_cfg)
          ]
        )
      ) {
        // Setting the session with its ID
        $id = $this->db->lastId();
        if (!$id) {
          throw new Exception(X::_("No session ID, check if your tables have the indexes defined"));
        }

        $this->session->set(
          [
            'fingerprint' => $fingerprint,
            'tokens' => [],
            'id_session' => $id,
            'salt' => $salt
          ],
          $this->sessIndex
        );

        $this->saveSession();
      } else {
        $this->setError(16);
      }
    } else {
      $this->sess_cfg = json_decode($tmp, true);
    }

    return $this;
  }


  /**
   * Gets an attribute or the whole the "session" part of the session (sessIndex).
   *
   * @param string|null $attr Name of the attribute to get.
   * @return mixed
   */
  protected function _get_session(string|null $attr = null)
  {
    if ($this->session && $this->session->has($this->sessIndex)) {
      return $attr ? $this->session->get($this->sessIndex, $attr) : $this->session->get($this->sessIndex);
    }

    return null;
  }


  /**
   * Checks the credentials of a user.
   *
   * @param array $params Credentials
   * @return bool
   */
  protected function _check_credentials($params, bool $makeHotlink = true): bool
  {
    if ($this->check()) {

      /** @var array $f The form fields sent to identify the users */
      $f = &$this->class_cfg['fields'];

      if (!isset($params[$f['salt']])) {
        $this->setError(11);
      } else {
        if (!$this->checkSalt($params[$f['salt']])) {
          $this->setError(17);
          $this->session->destroy();
        }
      }

      if ($this->check()) {
        if (isset($params[$f['user']], $params[$f['pass']])) {
          // Table structure
          $arch = &$this->class_cfg['arch'];

          $this->_just_login = 1;
          if (!$this->check()) {
            $this->setError(19);
            //$this->session->destroy();
            //$this->_init_session();
          }

          // Database Query
          elseif ($id = $this->db->selectOne(
            $this->class_cfg['tables']['users'],
            $this->fields['id'],
            X::mergeArrays(
              $this->class_cfg['conditions'],
              [$arch['users']['active'] => 1],
              [($arch['users']['login'] ?? $arch['users']['email']) => $params[$f['user']]]
            )
          )) {
            $numPasses = $this->db->count(
              $this->class_cfg['tables']['passwords'],
              [$arch['passwords']['id_user'] => $id]
            );
            // If no password is recorded we send a connection link
            if (!$numPasses) {
              $cfg = json_decode($this->db->selectOne($this->class_cfg['tables']['users'], $this->fields['cfg'], [$arch['users']['id'] => $id]) ?: '[]', true);
              if (empty($cfg['empty_attempts'])) {
                $cfg['empty_attempts'] = [
                  'num' => 0,
                  'last' => time()
                ];

              }
              if ($cfg['empty_attempts']['num'] >= self::MAX_EMPTY_ATTEMPTS) {
                if ($cfg['empty_attempts']['last'] > (time() - (3*3600))) {
                  $this->setError(25);
                }
                else {
                  $cfg['empty_attempts']['num'] = 0;
                  $cfg['empty_attempts']['last'] = time();
                }
              }

              if ($this->check()) {
                $cfg['empty_attempts']['num']++;
                $this->db->update($this->class_cfg['tables']['users'], [$this->fields['cfg'] => json_encode($cfg)], [$arch['users']['id'] => $id]);
                if ($makeHotlink) {
                  $this->getManager()->makeHotlink($id);
                  $this->setError(26);
                }
              }
            }
            else {
              $pass = $this->db->selectOne(
                $this->class_cfg['tables']['passwords'],
                $arch['passwords']['pass'],
                [$arch['passwords']['id_user'] => $id],
                [$arch['passwords']['added'] => 'DESC']
              );
              if ($this->_check_password($params[$f['pass']], $pass)) {
                $this->_login($id);
              } else {
                $this->recordAttempt();
                // Canceling authentication if num_attempts > max_attempts
                $this->setError($this->checkAttempts() ? 6 : 4);
              }
            }
          } else {
            $this->setError(6);
          }
        } else {
          $this->setError(12);
        }
      }
    }

    return $this->auth;
  }


  /**
   * Initializes the cache path for the user.
   * @return self
   */
  protected function cacheInit(): bool
  {
    if (!empty($this->id)) {
      $this->cache_path = Mvc::getUserTmpPath($this->id) . 'cache/';
      $fs = new System();
      if (!$fs->isDir($this->cache_path)) {
        $fs->mkdir($this->cache_path);
      }

      return $fs->isDir($this->cache_path);
    }

    return false;
  }


  /**
   * Initialize and saves the session after authentication.
   *
   * @param string $id The user's ID (as stored in the database).
   * @return self
   */
  private function _login($id): self
  {
    if ($this->check() && $id) {
      $this->_authenticate($id)->_user_info()->_init_dir(true)->saveSession();
    }

    return $this;
  }


  /**
   * Gathers the user's data from the database and puts it in the session.
   *
   * @return self
   */
  private function _user_info(bool $force = false): self
  {
    if ($this->getId()) {
      // Removing the encryption key to prevent it being saved in the session
      if (isset($this->fields['enckey'])) {
        unset($this->fields['enckey']);
      }

      if (!empty($this->getSession('id_group') && !$force)) {
        $this->cfg      = $this->getSession('cfg');
        $this->id_group = $this->getSession('id_group');
        $this->group = $this->getSession('group');
      }
      elseif ($d = $this->db->rselect(
        $this->class_cfg['tables']['users'],
        array_unique(array_values($this->fields)),
        X::mergeArrays(
          $this->class_cfg['conditions'],
          [$this->fields['active'] => 1],
          [$this->fields['id'] => $this->id]
        )
      )) {
        $r = [];
        foreach ($d as $key => $val) {
          $this->$key = $val;
          $r[$key]    = ($key === $this->fields['cfg']) && $val ? json_decode($val, true) : $val;
        }

        $this->cfg = $r['cfg'] ?? [];
        // Group
        $this->id_group = $r['id_group'];
        $this->group = $this->getFullGroup();
        $r['group'] = $this->group;
        $this->session->set($r, $this->userIndex);
        $this->saveSession();
      }
    }

    return $this;
  }



  /**
   * Gathers all the information about the user's session.
   *
   * @param string $id_session The session's table data or its ID
   * @return self
   */
  private function _sess_info(string|null $id_session = null): self
  {
    if (!Str::isUid($id_session)) {
      $id_session = $this->getSessionDbId();
    } else {
      $cfg = $this->_get_session('cfg');
    }

    if (
      empty($cfg)
      && Str::isUid($id_session)
      && ($id = $this->getSession('id'))
      && ($d = $this->db->rselect(
        $this->class_cfg['tables']['sessions'],
        $this->class_cfg['arch']['sessions'],
        [
          $this->class_cfg['arch']['sessions']['id'] => $id_session,
          $this->class_cfg['arch']['sessions']['id_user'] => $id,
          $this->class_cfg['arch']['sessions']['opened'] => 1,
        ]
      ))
    ) {
      $cfg = json_decode($d['cfg'], true);
    }

    if (isset($cfg) && \is_array($cfg)) {
      $this->sess_cfg = $cfg;
    } else {
      if (isset($id_session, $id)) {
        $this->_init_session();
        $new_id_session = $this->getSessionDbId();
        if ($id_session !== $new_id_session) {
          return $this->_sess_info($new_id_session);
        }
      }

      $this->setError(14);
    }

    return $this;
  }


  /**
   * Checks the conformity of a given string with a hash.
   *
   * @param string $pass_given  The password to check
   * @param string $pass_stored The stored encrypted password to check against
   * @return bool
   */
  private function _check_password(string $pass_given, string $pass_stored): bool
  {
    return $this->_hash($pass_given) === $pass_stored;
  }


  /**
   * Retrieves all user info from its session and populates the object.
   *
   * @param bool $force
   * @return self
   */
  private function _retrieve_session(bool $force = false): self
  {
    // $id mustn't be already defined
    if (!$this->id || $force) {
      // The user ID must be in the session
      $id_session = $this->getSessionDbId();
      $id         = $this->getSession('id');
      if ($id_session && $id) {
        $this->_sess_info($id_session);
        if (
          isset($this->sess_cfg['fingerprint'])
          && ($this->getPrint($this->_get_session('fingerprint')) === $this->sess_cfg['fingerprint'])
        ) {
          $this->_authenticate($id)->_user_info()->_init_dir()->saveSession();
        } else {
          $this->setError(19);
        }
      } else {
        $this->setError(15);
      }
    }

    return $this;
  }


  /**
   * Sets an attribute the "session" part of the session (sessIndex).
   *
   * @param mixed $attr Attribute if value follows, or an array with attribute of value key pairs
   * @return self
   */
  private function _set_session($attr): self
  {
    if ($this->session->has($this->sessIndex)) {
      $args = \func_get_args();
      if ((\count($args) === 2) && \is_string($args[0])) {
        $attr = [$args[0] => $args[1]];
      }

      if (is_array($attr)) {
        foreach ($attr as $key => $val) {
          if (\is_string($key)) {
            $this->session->set($val, $this->sessIndex, $key);
          }
        }
      }
    }

    return $this;
  }


  /**
   * Sets a user as authenticated ($this->auth = true).
   *
   * @param string $id
   * @return self
   */
  private function _authenticate(string $id): self
  {
    if ($this->check() && $id) {
      $this->id   = $id;
      $this->auth = true;
      if (!X::isCli()) {
        $update = [
          $this->class_cfg['arch']['sessions']['id_user'] => $id
        ];
        if ($this->isJustLogin()) {
          $newId = $this->session->regenerate();
          $update[$this->class_cfg['arch']['sessions']['sess_id']] = $newId;
          if ($this->getLastActivity() < date('Y-m-d H:i:s', strtotime('-' . constant('BBN_SESS_LIFETIME') . ' seconds'))) {
            $fs = new System();
            $fs->delete($this->getTmpPath(), false);
          }
        }
        $this->db->update(
          $this->class_cfg['tables']['sessions'],
          $update, [
            $this->class_cfg['arch']['sessions']['id'] => $this->getSessionDbId()
          ]
        );
      }
    }

    return $this;
  }
}
