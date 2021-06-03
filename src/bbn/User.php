<?php
/**
 * @package user
 */
namespace bbn;

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
class User extends Models\Cls\Basic
{
  use Models\Tts\Retriever;
  use Models\Tts\Dbconfig;

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
      19 => 'wrong fingerprint'
    ],
    'table' => 'bbn_users',
    'tables' => [
      'groups' => 'bbn_users_groups',
      'hotlinks' => 'bbn_users_hotlinks',
      'passwords' => 'bbn_users_passwords',
      'sessions' => 'bbn_users_sessions',
      'tokens' => 'bbn_users_tokens',
      'users' => 'bbn_users',
    ],
    'arch' => [
      'groups' => [
        'id' => 'id',
        'group' => 'group',
        'type' => 'type',
        'code' => 'code',
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
      'users' => [
        'id' => 'id',
        'id_group' => 'id_group',
        'email' => 'email',
        'username' => 'username',
        'login' => 'login',
        'admin' => 'admin',
        'dev' => 'dev',
        'theme' => 'theme',
        'cfg' => 'cfg',
        'active' => 'active',
        'enckey' => 'enckey'
      ],
    ],
    'fields' => [
      'user' => 'user',
      'pass' => 'pass',
      'salt' => 'appui_salt',
      'key' => 'key',
      'id' => 'id',
      'pass1' => 'pass1',
      'pass2' => 'pass2',
      'action' => 'appui_action'
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
     * User ban's length in minutes after max attempts is reached
     * @var integer
     */
    'max_sessions' => 5,
    /**
     * Sets if the hotlinks features should be in used
     * @var bool
     */
    'hotlinks' => false,
    'show' => 'name',
    'mailer' => '\\bbn\\Mail'
  ];

  private $_mailer;

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

  /** @var int */
  protected $error = null;

  /** @var string */
  protected $user_agent;

  /** @var string */
  protected $ip_address;

  /** @var string */
  protected $accept_lang;

  /** @var bool */
  protected $auth = false;

  /** @var string */
  protected $path;

  /** @var string */
  protected $tmp_path;

  /** @var string */
  protected $sql;

  /** @var int */
  protected $id;

  /** @var int */
  protected $id_group;

  /** @var mixed */
  protected $alert;

  /** @var array */
  protected $cfg;

  /** @var array */
  protected $sess_cfg;

  /** @var db */
  public $db;

  /** @var mixed */
  public $prev_time;


  /**
   * User constructor.
   *
   * @param db    $db
   * @param array $cfg
   * @param array $params
   */
  public function __construct(Db $db, array $params = [], array $cfg = [])
  {
    // The database connection
    $this->db = $db;
    // The client environment variables
    $this->user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $this->ip_address  = $_SERVER['REMOTE_ADDR'] ?? '';
    $this->accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

    // Setting up the class configuration
    $this->_init_class_cfg($cfg);

    // Creating the session's variables if they don't exist yet
    $this->_init_session();
    self::retrieverInit($this);

    $f =& $this->class_cfg['fields'];

    /*
    if (x::isCli() && isset($params['id'])) {
      $this->id = $params['id'];
      $this->auth = true;
    }
    */
    // The user logs in
    if ($this->isLoginRequest($params)) {
      /** @todo separate credentials and salt checking */
      if (!empty($this->sess_cfg['fingerprint'])
          && $this->getPrint($this->_get_session('fingerprint')) === $this->sess_cfg['fingerprint']
      ) {
        /** @todo separate credentials and salt checking */
        $this->_check_credentials($params);
      }
      else{
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
        else{
          $this->setError(7);
        }
      }
      else{
        $this->setError(18);
      }
    }
    else {
      $this->checkSession();
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

  public function isReset()
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
  public function checkSalt($salt): bool
  {
    return $this->getSalt() === $salt;
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
   * Returns the current configuration of this very class.
   *
   * @return array
   */
  public function getClassCfg(): array
  {
    return $this->class_cfg;
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
   * Returns the list of tables used by the current class.
   * @return array
   */
  public function getTables(): ?array
  {
    if (!empty($this->class_cfg)) {
      return $this->class_cfg['tables'];
    }

    return null;
  }


  /**
   * Returns the list of fields of the given table, and if empty for each table.
   *
   * @param string $table
   * @return array|null
   */
  public function getFields(string $table = ''): ?array
  {
    if (!empty($this->class_cfg)) {
      if ($table) {
        return $this->class_cfg['arch'][$table] ?? null;
      }

      return $this->class_cfg['arch'];
    }

    return null;
  }


  /**
   * Changes the data in the user's table.
   *
   * @param array $d The new data
   * @return bool
   */
  public function updateInfo(array $d)
  {
    if ($this->checkSession()) {
      $update = [];
      foreach ($d as $key => $val){
        if (($key !== $this->fields['id'])
            && ($key !== $this->fields['cfg'])
            && ($key !== 'auth')
            && ($key !== 'pass')
            && \in_array($key, $this->fields)
        ) {
          $update[$key] = $val;
        }
      }

      if (\count($update) > 0) {
        $r = (bool)$this->db->update(
          $this->class_cfg['tables']['users'],
          $update,
          [$this->fields['id'] => $this->id]
        );
        /** @todo Why did I do this?? */
        if ($r) {
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
  public function isJustLogin()
  {
    return $this->_just_login;
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

      foreach ($attr as $key => $val){
        if (\is_string($key)) {
          $this->session->set($val, $this->userIndex, $key);
        }
      }
    }

    return $this;
  }


  /**
   * Sets the given attribute(s) in the user's session.
   *
     * @return self
     */
  public function unsetSession($attr): self
  {
    $args = \func_get_args();
    array_unshift($args, $this->userIndex);
    if ($this->session->has(...$args)) {
      $this->session->uset(...$args);
    }

    return $this;
  }


    /**
   * Returns session property from the session's user array.
   *
   * @param null|string The property to get
     * @return mixed
     */
  public function getSession($attr = null)
  {
    if ($this->session->has($this->userIndex)) {
      return $attr ? $this->session->get($this->userIndex, $attr) : $this->session->get($this->userIndex);
    }

    return null;
  }


  public function getOsession($attr = null)
  {
    return $this->_get_session($attr);
  }


  public function setOsession($attr)
  {
    return $this->_set_session($attr);
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


  public function updateActivity(): self
  {
    $id_session = $this->getIdSession();
    //die(var_dump($id_session, $this->check()));
    if ($id_session && $this->check()) {
      $p =& $this->class_cfg['arch']['sessions'];
      $this->db->update(
        $this->class_cfg['tables']['sessions'], [
        $p['last_activity'] => date('Y-m-d H:i:s')
        ], [
        $p['id'] => $id_session
        ]
      );
    }
    else{
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
    $id_session = $this->getIdSession();
    //die(var_dump($id_session, $this->check()));
    if ($this->check()) {
      if ($id_session) {
        $p =& $this->class_cfg['arch']['sessions'];
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
      }
      else{
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
    if ($this->id) {
      $p =& $this->class_cfg['arch']['sessions'];
      $this->db->update(
        $this->class_cfg['tables']['sessions'], [
          $p['ip_address'] => $this->ip_address,
          $p['user_agent'] => $this->user_agent,
          $p['opened'] => 0,
          $p['last_activity'] => date('Y-m-d H:i:s'),
          $p['cfg'] => json_encode($this->sess_cfg)
        ],[
          $p['id_user'] => $this->id,
          $p['sess_id'] => $this->session->getId()
        ]
      );
      $this->auth     = false;
      $this->id       = null;
      $this->sess_cfg = null;
      if ($with_session) {
        $this->session->set([]);
      }
      else{
        $this->session->set([], $this->userIndex);
      }
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
      //x::log("Checking attempts without user config", 'user_login');
      return false;
    }

    if (isset($this->cfg['num_attempts']) && $this->cfg['num_attempts'] > $this->class_cfg['max_attempts']) {
      //x::log("Checking attempts maxed out!", 'user_login');
      return false;
    }

    //x::log("Checking attempts ok", 'user_login');
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

      foreach ($attr as $key => $val){
        if (\is_string($key)) {
          $this->cfg[$key] = $val;
        }
      }

      $this->setSession(['cfg' => $this->cfg]);
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
      $args = \func_get_args();
      if (\is_string($attr)) {
        /** @var array $attr */
        $attr = [$attr];
      }

      foreach ($attr as $key){
        if (isset($key)) {
          unset($this->cfg[$key]);
        }
      }

      $this->setSession(['cfg' => $this->cfg]);
    }

    return $this;
  }


  /**
   * Regathers informations from the database.
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
   * Returns true if authenticated false otherwise.
   *
   * @return bool
   */
  public function isAuth()
  {
      return $this->auth;
  }


    /**
   * Retrieves user's info from session if needed and checks if authenticated.
   *
     * @return bool
     */
  public function checkSession()
  {
    if ($this->check()) {
      $this->_retrieve_session();
      return $this->auth;
    }
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
  public function getGroup(): ?string
  {
    if ($this->check()) {
      return $this->id_group;
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
      $this->class_cfg['arch']['hotlinks']['magic'],
      $this->class_cfg['arch']['hotlinks']['id_user'],
      ],[
      $this->class_cfg['arch']['hotlinks']['id'] => $id,
      [$this->class_cfg['arch']['hotlinks']['expire'], '>', Date('Y-m-d H:i:s')]
      ]
    )
    ) {
      if (self::isMagicString($key, $val[$this->class_cfg['arch']['hotlinks']['magic']])) {
        return $val['id_user'];
      }
    }

    return null;
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
   * Checks whether the user is an dev(eloper) or not.
   *
   * @return bool
   */
  public function isDev(): bool
  {
    return (bool)($this->isAdmin() || !!$this->getSession('dev'));
  }


  /**
   * Gets a bbn\User\Manager instance.
   *
   * @param Mail $mail
   * @return User\Manager
   */
  public function getManager(Mail $mail = null)
  {
    $mgr = new User\Manager($this, $mail);
    return $mgr;
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
    $this->closeSession();
  }


  /**
   * Returns an instance of the mailer class.
   *
   * @return Mail
   */
  public function getMailer()
  {
    if (!$this->_mailer) {
      if (class_exists($this->class_cfg['mailer'])) {
        $this->_mailer = new $this->class_cfg['mailer']();
      }
      else {
        throw new \Exception(X::_("Impossible to find the mailer class %s", (string)$this->class_cfg['mailer']));
      }
    }

    return $this->_mailer;
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
        $pwt, $pwa['pass'], [
        $this->class_cfg['arch']['passwords']['id_user'] => $this->id
        ], [
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
   * Returns the full name of the given user or the current one.
   *
   * @return string|null
   */
  public function getName($usr = null): ?string
  {
    if ($this->auth) {
      if (\is_null($usr)) {
        $usr = $this->getSession();
      }
      elseif (str::isUid($usr)) {
        $mgr = $this->getManager();
        $usr = $mgr->getUser($usr);
      }

      if (isset($usr[$this->class_cfg['show']])) {
        return $usr[$this->class_cfg['show']];
      }
    }

    return null;
  }


  public function addToken(): ?string
  {
    if ($this->auth) {
      $token = Str::genpwd(32, 16);
      $f     =& $this->class_cfg['arch']['tokens'];
      if ($this->db->insert(
        $this->class_cfg['tables']['tokens'],
        [
          $f['id_session'] => $this->getIdSession(),
          $f['content'] => $token,
          $f['creation'] => X::microtime(),
          $f['last'] => X::microtime()
        ]
      )
      ) {
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
      }
      elseif (str::isUid($usr) && ($mgr = $this->getManager())) {
        $usr = $mgr->getUser($usr);
      }

      if (isset($usr[$this->fields['email']])) {
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
  public function addToTmp(string $file, string $name = null, $move = true):? string
  {
    if ($this->auth) {
      $fs   = new File\System();
      $path = $this->getTmpDir().microtime(true).'/';
      if ($fs->isFile($file) && $fs->createPath($path)) {
        $dest = $path.($name ?: basename($file));
        if ($move) {
          if ($fs->move($file, Dirname($dest)) && $fs->rename(dirname($dest).'/'.basename($file), basename($dest))) {
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


  public function crypt(string $st): ?string
  {
    if ($enckey = $this->_get_encryption_key()) {
      return Util\Enc::crypt($st, $enckey) ?: null;
    }

    return null;
  }


  public function decrypt(string $st): ?string
  {
    if ($enckey = $this->_get_encryption_key()) {
      return Util\Enc::decrypt($st, $enckey) ?: null;
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
  protected function setError($err): self
  {
    if (!$this->error) {
      $this->error = $err;
      //x::log($this->getError(), 'user_login');
      //die(x::dump($err));
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
   * Completes the steps for a full authentication of the user.
   *
   * @param string $id
   * @return self
   */
  protected function logIn($id): self
  {
    $this->error = null;
    if ($this->check() && $id) {
      $this->_authenticate($id)->_user_info()->_init_dir(true)->saveSession();
    }

    return $this;
  }


  /**
   * Returns a "print" based on the user agent + the fingerprint.
   *
   * @param null|string $fp
   * @return null|string
   */
  protected function getPrint(string $fp = null): ?string
  {
    if (!$fp) {
      $fp = $this->_get_session('fingerprint');
    }

    if ($fp) {
      return sha1($this->user_agent.$this->accept_lang./*$this->ip_address .*/ $fp);
    }

    return null;
  }


  /**
   * Returns the database ID for the session's row if it is in the session.
   *
   * @return null|string
   */
  protected function getIdSession(): ?string
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
    * Gathers the user'data from the database and puts it in the session.
    *
    * @param array $data User's table data argument if it is already available
    * @return self
    */
  private function _user_info(array $data = null): self
  {
    if ($this->getId()) {
      // Removing the encryption key to prevent it being saved in the session
      if (isset($this->fields['enckey'])) {
        unset($this->fields['enckey']);
      }

      if (!empty($this->getSession('cfg'))) {
        $this->cfg      = $this->getSession('cfg');
        $this->id_group = $this->getSession('id_group');
      }
      elseif ($d = $this->db->rselect(
        $this->class_cfg['tables']['users'],
        array_unique(array_values($this->fields)),
        X::mergeArrays(
          $this->class_cfg['conditions'],
          [$this->fields['active'] => 1],
          [$this->fields['id'] => $this->id]
        )
      )
      ) {
        $r = [];
        foreach ($d as $key => $val){
          $this->$key = $val;
          $r[$key]    = $key === $this->fields['cfg'] ? json_decode($val, true) : $val;
        }

        $this->cfg = $r['cfg'] ?: [];
        // Group
        $this->id_group = $r['id_group'];
        $this->session->set($r, $this->userIndex);
        $this->saveSession();
      }
    }

    return $this;
  }


  private function _get_encryption_key(): ?string
  {
    if (is_null($this->_encryption_key)) {
      if ($this->auth) {
        $this->_encryption_key = $this->db->selectOne($this->class_cfg['table'], $this->class_cfg['arch']['users']['enckey'], ['id' => $this->id]);
      }
    }

    return $this->_encryption_key;
  }


   /**
    * Gathers all the information about the user's session.
    *
    * @param string $d The session's table data or its ID
    * @return self
    */
  private function _sess_info(string $id_session = null): self
  {
    if ($id_session !== $this->getIdSession() && !str::isUid($id_session)) {
      $id_session = $this->getIdSession();
    }
    else{
      $cfg = $this->_get_session('cfg');
    }

    if (empty($cfg)
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
    }
    else{
      if (isset($id)) {
        $this->_init_session();
        $new_id = $this->getSession('id');
        if ($new_id !== $id) {
          return $this->_sess_info($new_id);
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
    * Use the configured hash function to encrypt a password string.
    *
    * @param string $st The string to crypt
    * @return string
    */
  private function _hash(string $st): string
  {
    if (!function_exists($this->class_cfg['encryption'])) {
      $this->class_cfg['encryption'] = 'sha256';
    }

    return $this->class_cfg['encryption']($st);
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
      $id_session = $this->getIdSession();
      $id         = $this->getSession('id');
      if ($id_session && $id) {
        $this->_sess_info($id_session);
        //x::log([$this->sess_cfg, $this->_get_session('fingerprint'), $this->getPrint($this->_get_session('fingerprint'))], 'user_login');
        if (isset($this->sess_cfg['fingerprint'])
            && ($this->getPrint($this->_get_session('fingerprint')) === $this->sess_cfg['fingerprint'])
        ) {
          //x::log("THe auth should have worked for id $id", 'user_login');
          $this->_authenticate($id)->_user_info()->_init_dir()->saveSession();
        }
        else {
          $this->setError(19);
        }
      }
      else {
        //x::log([$id_session, $id], 'user_login');
        $this->setError(15);
      }
    }

    return $this;
  }


  /**
   * Gets or creates (also in database) the user's session for the first time.
   *
   * @return self
   */
  private function _init_session($defaults = []): self
  {
    // Getting or creating the session is it doesn't exist yet
    /** @var User\Session */
    $this->session = User\Session::getInstance();
    if (!$this->session) {
      $session_cls   = defined('BBN_SESSION') 
          && is_string(BBN_SESSION)
          && class_exists(BBN_SESSION) ? BBN_SESSION : '\\bbn\\User\\Session';
      $this->session = new $session_cls($defaults);
    }

    /** @var int $id_session The ID of the session row in the DB */
    if (!($id_session = $this->getIdSession())
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
      $p =& $this->class_cfg['arch']['sessions'];

      $this->sess_cfg = [
        'fingerprint' => $this->getPrint($fingerprint),
        'last_renew' => time()
      ];

      // Inserting the session in the database
      if ($this->db->insert(
        $this->class_cfg['tables']['sessions'], [
        $p['sess_id'] => $this->session->getId(),
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
        $id_session = $this->db->lastId();
        $this->session->set(
          [
          'fingerprint' => $fingerprint,
          'tokens' => [],
          'id_session' => $id_session,
          'salt' => $salt
           ], $this->sessIndex
        );
        $this->saveSession();
      }
      else{
        $this->setError(16);
      }
    }
    else {
      $this->sess_cfg = json_decode($tmp, true);
    }

    return $this;
  }


   /**
    * Sets an attribute the "session" part of the session.
    *
    * @param mixed $attr Attribute if value follows, or an array with attribute/value keypairs
    * @return self
    */
  private function _set_session($attr): self
  {
    if ($this->session->has($this->sessIndex)) {
      $args = \func_get_args();
      if ((\count($args) === 2) && \is_string($args[0])) {
        $attr = [$args[0] => $args[1]];
      }

      foreach ($attr as $key => $val){
        if (\is_string($key)) {
          $this->session->set($val, $this->sessIndex, $key);
        }
      }
    }

    return $this;
  }


  /**
   * Gets an attribute or the whole the "session" part of the session.
   *
   * @param string|null $attr Name of the attribute to get
   * @return mixed
   */
  private function _get_session(string $attr = null)
  {
    if ($this->session->has($this->sessIndex)) {
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
  private function _check_credentials($params): bool
  {
    if ($this->check()) {

      /** @var array $f The form fields sent to identify the users */
      $f =& $this->class_cfg['fields'];

      if (!isset($params[$f['salt']])) {
        $this->setError(11);
      }
      else{
        if (!$this->checkSalt($params[$f['salt']])) {
          $this->setError(17);
          $this->session->destroy();
        }
      }

      if ($this->check()) {
        if (isset($params[$f['user']], $params[$f['pass']])) {
          // Table structure
          $arch =& $this->class_cfg['arch'];

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
          )
          ) {
            $pass = $this->db->selectOne(
              $this->class_cfg['tables']['passwords'],
              $arch['passwords']['pass'],
              [$arch['passwords']['id_user'] => $id],
              [$arch['passwords']['added'] => 'DESC']
            );
            if ($this->_check_password($params[$f['pass']], $pass)) {
              $this->_login($id);
            }
            else{
              $this->recordAttempt();
              // Canceling authentication if num_attempts > max_attempts
              $this->setError($this->checkAttempts() ? 6 : 4);
            }
          }
          else{
            $this->setError(6);
          }
        }
        else{
          $this->setError(12);
        }
      }
    }

    return $this->auth;
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
        File\Dir::createPath($this->path);
        File\Dir::createPath($this->tmp_path);
        File\Dir::delete($this->tmp_path, false);
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
      $this->db->update(
        $this->class_cfg['tables']['sessions'], [
        $this->class_cfg['arch']['sessions']['id_user'] => $id
         ], [
         $this->class_cfg['arch']['sessions']['id'] => $this->getIdSession()
         ]
      );
    }

    return $this;
  }


}
