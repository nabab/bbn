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
class user extends models\cls\basic
{
  use models\tts\retriever;
  use models\tts\dbconfig;

  /** @var string The name of the session index in for session data */
  protected static $sn = 'bbn_session';

  /** @var string The name of the session index in for user data */
  protected static $un = 'bbn_user';

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
    'show' => 'name'
  ];

  /** @var bool Will be true when the user has just logged in. */
  private $_just_login = false;

  private $_encryption_key = null;

  protected $password_reset = false;

  /** @var user\session */
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
  public function __construct(db $db, array $params = [], array $cfg = [])
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
    self::retriever_init($this);

    $f =& $this->class_cfg['fields'];

    /*
    if (x::is_cli() && isset($params['id'])) {
      $this->id = $params['id'];
      $this->auth = true;
    }
    */
    // The user logs in
    if (isset($params[$f['user']], $params[$f['pass']], $params[$f['salt']])) {

      /** @todo separate credentials and salt checking */
      if ($this->get_print($this->_get_session('fingerprint')) === $this->sess_cfg['fingerprint']) {
        /** @todo separate credentials and salt checking */
        $this->_check_credentials($params);
      }
      else{
        $this->set_error(19);
        $this->session->destroy();
      }
    }

    /** @todo revise the process: dying is not the solution! */
    // The user is not known yet
    elseif (isset($params[$f['key']], $params[$f['id']], $params[$f['pass1']], $params[$f['pass2']], $params[$f['action']])
        && ($params[$f['action']] === 'init_password')
    ) {
      if ($id = $this->get_id_from_magic_string($params[$f['id']], $params[$f['key']])) {
        $this->password_reset = true;
        if (($params[$f['pass1']] === $params[$f['pass2']])) {
          $this->expire_hotlink($params[$f['id']]);
          $this->id = $id;
          $this->force_password($params[$f['pass2']]);
          $this->session->set([]);
        }
        else{
          $this->set_error(7);
        }
      }
      else{
        $this->set_error(18);
      }
    }
    else {
      $this->check_session();
    }
  }


  public function is_reset()
  {
    return $this->password_reset;
  }


  /**
   * Returns the salt string kept in session.
   *
   * @return null|string
   */
  public function get_salt(): ?string
  {
    $salt = $this->_get_session('salt');
    return $salt;
  }


  /**
   * Confronts the given string with the salt string kept in session.
   *
   * @return bool
   */
  public function check_salt($salt): bool
  {
    return $this->get_salt() === $salt;
  }


  /**
   * Returns the current user's configuration.
   *
   * @param string $attr
   * @return mixed
   */
  public function get_cfg($attr = '')
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
  public function get_class_cfg(): array
  {
    return $this->class_cfg;
  }


  /**
   * Returns the directory path for the user.
   *
   * @return string
   */
  public function get_path(): ?string
  {
    return $this->path;
  }


  /**
   * Returns the tmp directory path for the user.
   *
   * @return string
   */
  public function get_tmp_dir(): ?string
  {
    return $this->tmp_path;
  }


  /**
   * Returns the list of tables used by the current class.
   * @return array
   */
  public function get_tables(): ?array
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
  public function get_fields(string $table = ''): ?array
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
  public function update_info(array $d)
  {
    if ($this->check_session()) {
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
          $this->set_session(['cfg' => false]);
          $this->_user_info();
          return $r;
        }
      }
    }

    return false;
  }


  /**
   * Encrypts the given string to match the password.
   *
   * @param string $st
   * @return string
   */
  public function get_password(string $st): string
  {
    return $this->_crypt($st);
  }


  /**
   * Returns true after the log in moment.
   *
   * @return bool
   */
  public function is_just_login()
  {
    return $this->_just_login;
  }


    /**
   * Sets the given attribute(s) in the user's session.
   *
     * @return self
     */
  public function set_session($attr): self
  {
    if ($this->session->has(self::$un)) {
      $args = \func_get_args();
      if ((\count($args) === 2) && \is_string($args[0])) {
        $attr = [$args[0] => $args[1]];
      }

      foreach ($attr as $key => $val){
        if (\is_string($key)) {
          $this->session->set($val, self::$un, $key);
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
  public function unset_session($attr): self
  {
    $args = \func_get_args();
    array_unshift($args, self::$un);
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
  public function get_session($attr = null)
  {
    if ($this->session->has(self::$un)) {
      return $attr ? $this->session->get(self::$un, $attr) : $this->session->get(self::$un);
    }

    return null;
  }


  public function get_osession($attr = null)
  {
    return $this->_get_session($attr);
  }


  public function set_osession($attr)
  {
    return $this->_set_session($attr);
  }


    /**
   * Checks if the given attribute exists in the user's session.
   *
     * @return bool
     */
  public function has_session($attr): bool
  {
    return $this->session->has(self::$un, $attr);
  }


  public function update_activity(): self
  {
    $id_session = $this->get_id_session();
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
      $this->set_error(13);
    }

    return $this;
  }


  /**
   * Saves the session config in the database.
   *
   * @todo Use it only when needed!
     * @return self
     */
  public function save_session(bool $force = false): self
  {
    $id_session = $this->get_id_session();
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
              $p['sess_id'] => $this->session->get_id(),
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
        $this->set_error(13);
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
  public function close_session($with_session = false): self
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
          $p['sess_id'] => $this->session->get_id()
        ]
      );
      $this->auth     = false;
      $this->id       = null;
      $this->sess_cfg = null;
      if ($with_session) {
        $this->session->set([]);
      }
      else{
        $this->session->set([], self::$un);
      }
    }

    return $this;
  }


  /**
   * Returns false if the max number of connections attempts has been reached
   * @return bool
   */
  public function check_attempts(): bool
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
  public function save_cfg(): self
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
  public function set_cfg($attr): self
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

      $this->set_session(['cfg' => $this->cfg]);
    }

    return $this;
  }


  /**
   * Unsets the attribute(s) in the session config.
   *
   * @param $attr
   * @return self
   */
  public function unset_cfg($attr): self
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

      $this->set_session(['cfg' => $this->cfg]);
    }

    return $this;
  }


  /**
   * Regathers informations from the database.
   *
     * @return self
     */
  public function refresh_info(): self
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
  public function is_auth()
  {
      return $this->auth;
  }


    /**
   * Retrieves user's info from session if needed and checks if authenticated.
   *
     * @return bool
     */
  public function check_session()
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
  public function get_id(): ?string
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
  public function get_group(): ?string
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
  public function expire_hotlink($id): int
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
  public function get_id_from_magic_string(string $id, string $key): ?string
  {
    if ($val = $this->db->rselect(
      $this->class_cfg['tables']['hotlinks'], [
      $this->class_cfg['arch']['hotlinks']['magic'],
      $this->class_cfg['arch']['hotlinks']['id_user'],
      ],[
      $this->class_cfg['arch']['hotlinks']['id'] => $id,
      [$this->class_cfg['arch']['hotlinks']['expire'], '>', date('Y-m-d H:i:s')]
      ]
    )
    ) {
      if (self::is_magic_string($key, $val[$this->class_cfg['arch']['hotlinks']['magic']])) {
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
  public function is_admin(): bool
  {
    return (bool)$this->get_session('admin');
  }


  /**
   * Checks whether the user is an dev(eloper) or not.
   *
   * @return bool
   */
  public function is_dev(): bool
  {
    return (bool)($this->is_admin() || !!$this->get_session('dev'));
  }


  /**
   * Gets a bbn\user\manager instance.
   *
   * @param mail $mail
   * @return user\manager
   */
  public function get_manager(mail $mail = null)
  {
    $mgr = new user\manager($this, $mail);
    return $mgr;
  }


    /**
   * Checks if an error has been thrown or not.
   *
     * @return bool
     */
  public function check()
  {
    return $this->get_error() ? false : true;
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
    $this->close_session();
  }


  /**
   * Returns an instance of the mailer class.
   *
   * @return mail
   */
  public function get_mailer()
  {
    return new mail();
  }


  /**
   * Change the password in the database after checking the current one.
   *
   * @param string $old_pass The current password
   * @param string $new_pass The new password
   * @return bool
   */
  public function set_password(string $old_pass, string $new_pass): bool
  {
    if ($this->auth) {
      $pwt         = $this->class_cfg['tables']['passwords'];
      $pwa         = $this->class_cfg['arch']['passwords'];
      $stored_pass = $this->db->select_one(
        $pwt, $pwa['pass'], [
        $this->class_cfg['arch']['passwords']['id_user'] => $this->id
        ], [
        $this->class_cfg['arch']['passwords']['added'] => 'DESC'
        ]
      );
      if ($this->_check_password($old_pass, $stored_pass)) {
        return $this->force_password($new_pass);
      }
    }

    return false;
  }


  /**
   * Changes the password in the database.
   *
   * @return bool
   */
  public function force_password($pass): bool
  {
    if ($this->id) {
      return (bool)$this->db->insert(
        $this->class_cfg['tables']['passwords'], [
        $this->class_cfg['arch']['passwords']['pass'] => $this->_crypt($pass),
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
  public function get_name($usr = null): ?string
  {
    if ($this->auth) {
      if (\is_null($usr)) {
        $usr = $this->get_session();
      }
      elseif (str::is_uid($usr)) {
        $mgr = $this->get_manager();
        $usr = $mgr->get_user($usr);
      }

      if (isset($usr[$this->class_cfg['show']])) {
        return $usr[$this->class_cfg['show']];
      }
    }

    return null;
  }


  public function add_token(): ?string
  {
    if ($this->auth) {
      $token = str::genpwd(32, 16);
      $f     =& $this->class_cfg['arch']['tokens'];
      if ($this->db->insert(
        $this->class_cfg['tables']['tokens'],
        [
          $f['id_session'] => $this->get_id_session(),
          $f['content'] => $token,
          $f['creation'] => x::microtime(),
          $f['last'] => x::microtime()
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
  public function get_email($usr = null): ?string
  {
    if ($this->auth) {
      if (\is_null($usr)) {
        $usr = $this->get_session();
      }
      elseif (str::is_uid($usr) && ($mgr = $this->get_manager())) {
        $usr = $mgr->get_user($usr);
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
  public function add_to_tmp(string $file, string $name = null, $move = true):? string
  {
    if ($this->auth) {
      $fs   = new file\system();
      $path = $this->get_tmp_dir().microtime(true).'/';
      if ($fs->is_file($file) && $fs->create_path($path)) {
        $dest = $path.($name ?: basename($file));
        if ($move) {
          if ($fs->move($file, dirname($dest)) && $fs->rename(dirname($dest).'/'.basename($file), basename($dest))) {
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
      return util\enc::crypt($st, $enckey) ?: null;
    }

    return null;
  }


  public function decrypt(string $st): ?string
  {
    if ($enckey = $this->_get_encryption_key()) {
      return util\enc::decrypt($st, $enckey) ?: null;
    }

    return null;
  }


  /**
   * Returns the latest created connection, ie the current user's object.
   * @return self
   */
  public static function get_user(): ?self
  {
    return self::get_instance();
  }


  /**
   * Generates a random long string (16-32 chars) used as unique fingerprint.
   * @return string
   */
  public static function make_fingerprint(): string
  {
    return str::genpwd(32, 16);
  }


  /**
   * Returns an array with a key and a magic string used for making hotlinks.
   *
   * @return array
   */
  public static function make_magic_string(): array
  {
    $key = self::make_fingerprint();
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
  public static function is_magic_string(string $key, string $hash): bool
  {
    return hash('sha256', $key) === $hash;
  }


  /**
   * Sets the error property once and for all.
   *
   * @param $err The error code
   * @return self
   */
  protected function set_error($err): self
  {
    if (!$this->error) {
      $this->error = $err;
      //x::log($this->get_error(), 'user_login');
      //die(x::dump($err));
    }

    return $this;
  }


  /**
   * Returns the first error in an array with the code and description.
   *
   * @return null|array
   */
  public function get_error(): ?array
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
   * @param type $id
   * @return self
   */
  protected function log_in($id): self
  {
    $this->error = null;
    if ($this->check() && $id) {
      $this->_authenticate($id)->_user_info()->_init_dir(true)->save_session();
    }

    return $this;
  }


  /**
   * Returns a "print" based on the user agent + the fingerprint.
   *
   * @param null|string $fp
   * @return null|string
   */
  protected function get_print(string $fp = null): ?string
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
  protected function get_id_session(): ?string
  {
    return $this->_get_session('id_session');
  }


  /**
   * Increments the num_attempt variable (after unsuccessful login attempt).
   *
   * @return self
   */
  protected function record_attempt(): self
  {
    $this->cfg['num_attempts'] = isset($this->cfg['num_attempts']) ? $this->cfg['num_attempts'] + 1 : 1;
    $this->_set_session('num_attempts', $this->cfg['num_attempts']);
    $this->save_session();
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
      $this->_authenticate($id)->_user_info()->_init_dir(true)->save_session();
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
    if ($this->get_id()) {
      // Removing the encryption key to prevent it being saved in the session
      if (isset($this->fields['enckey'])) {
        unset($this->fields['enckey']);
      }

      if (!empty($this->get_session('cfg'))) {
        $this->cfg      = $this->get_session('cfg');
        $this->id_group = $this->get_session('id_group');
      }
      elseif ($d = $this->db->rselect(
        $this->class_cfg['tables']['users'],
        array_unique(array_values($this->fields)),
        x::merge_arrays(
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
        $this->session->set($r, self::$un);
        $this->save_session();
      }
    }

    return $this;
  }


  private function _get_encryption_key(): ?string
  {
    if (is_null($this->_encryption_key)) {
      if ($this->auth) {
        $this->_encryption_key = $this->db->select_one($this->class_cfg['table'], $this->class_cfg['arch']['users']['enckey'], ['id' => $this->id]);
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
    if (!str::is_uid($id_session)) {
      $id_session = $this->get_id_session();
    }
    else{
      $cfg = $this->_get_session('cfg');
    }

    if (empty($cfg)
        && str::is_uid($id_session)
        && ($id = $this->get_session('id'))
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

    if (\is_array($cfg)) {
      $this->sess_cfg = $cfg;
    }
    else{
      if (isset($id)) {
        $this->_init_session();
        $new_id = $this->get_session('id');
        if ($new_id !== $id) {
          return $this->_sess_info($new_id);
        }
      }

      $this->set_error(14);
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
    return $this->_crypt($pass_given) === $pass_stored;
  }


   /**
    * Use the configured hash function to encrypt a password string.
    *
    * @param string $st The string to crypt
    * @return string
    */
  private function _crypt(string $st): string
  {
    if (!function_exists($this->class_cfg['encryption'])) {
      $this->class_cfg['encryption'] = 'sha256';
    }

    return eval("return {$this->class_cfg['encryption']}('$st');");
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
      $id_session = $this->get_id_session();
      $id         = $this->get_session('id');
      if ($id_session && $id) {
        $this->_sess_info($id_session);
        //x::log([$this->sess_cfg, $this->_get_session('fingerprint'), $this->get_print($this->_get_session('fingerprint'))], 'user_login');
        if (isset($this->sess_cfg['fingerprint'])
            && ($this->get_print($this->_get_session('fingerprint')) === $this->sess_cfg['fingerprint'])
        ) {
          //x::log("THe auth should have worked for id $id", 'user_login');
          $this->_authenticate($id)->_user_info()->_init_dir()->save_session();
        }
        else {
          $this->set_error(19);
        }
      }
      else {
        //x::log([$id_session, $id], 'user_login');
        $this->set_error(15);
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
    /** @var user\session */
    $this->session = user\session::get_instance();
    if (!$this->session) {
      $session_cls   = defined('BBN_SESSION') 
          && is_string(BBN_SESSION)
          && class_exists(BBN_SESSION) ? BBN_SESSION : '\\bbn\\user\\session';
      $this->session = new $session_cls($defaults);
    }

    /** @var int $id_session The ID of the session row in the DB */
    if (!($id_session = $this->get_id_session())
        || !($tmp = $this->db->select_one(
          $this->class_cfg['tables']['sessions'],
          $this->class_cfg['arch']['sessions']['cfg'],
          [$this->class_cfg['arch']['sessions']['id'] => $id_session]
        ))
    ) {
      /** @var string $salt */
      $salt = self::make_fingerprint();

      /** @var string $fingerprint */
      $fingerprint = self::make_fingerprint();

      /** @var array $p The fields of the sessions table */
      $p =& $this->class_cfg['arch']['sessions'];

      $this->sess_cfg = [
        'fingerprint' => $this->get_print($fingerprint),
        'last_renew' => time()
      ];

      // Inserting the session in the database
      if ($this->db->insert(
        $this->class_cfg['tables']['sessions'], [
        $p['sess_id'] => $this->session->get_id(),
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
        $id_session = $this->db->last_id();
        $this->session->set(
          [
          'fingerprint' => $fingerprint,
          'tokens' => [],
          'id_session' => $id_session,
          'salt' => $salt
           ], self::$sn
        );
        $this->save_session();
      }
      else{
        $this->set_error(16);
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
    if ($this->session->has(self::$sn)) {
      $args = \func_get_args();
      if ((\count($args) === 2) && \is_string($args[0])) {
        $attr = [$args[0] => $args[1]];
      }

      foreach ($attr as $key => $val){
        if (\is_string($key)) {
          $this->session->set($val, self::$sn, $key);
        }
      }
    }

    return $this;
  }


   /**
    * Gets an attribute or the whole the "session" part of the session.
    *
    * @param string $attr Name of the attribute to get
    * @return mixed
    */
  private function _get_session(string $attr = null)
  {
    if ($this->session->has(self::$sn)) {
      return $attr ? $this->session->get(self::$sn, $attr) : $this->session->get(self::$sn);
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
        $this->set_error(11);
      }
      else{
        if (!$this->check_salt($params[$f['salt']])) {
          $this->set_error(17);
          $this->session->destroy();
        }
      }

      if ($this->check()) {
        if (isset($params[$f['user']], $params[$f['pass']])) {
          // Table structure
          $arch =& $this->class_cfg['arch'];

          $this->_just_login = 1;
          if (!$this->check()) {
            $this->set_error(19);
            //$this->session->destroy();
            //$this->_init_session();
          }

          // Database Query
          elseif ($id = $this->db->select_one(
            $this->class_cfg['tables']['users'],
            $this->fields['id'],
            x::merge_arrays(
              $this->class_cfg['conditions'],
              [$arch['users']['active'] => 1],
              [($arch['users']['login'] ?? $arch['users']['email']) => $params[$f['user']]]
            )
          )
          ) {
            $pass = $this->db->select_one(
              $this->class_cfg['tables']['passwords'],
              $arch['passwords']['pass'],
              [$arch['passwords']['id_user'] => $id],
              [$arch['passwords']['added'] => 'DESC']
            );
            if ($this->_check_password($params[$f['pass']], $pass)) {
              $this->_login($id);
            }
            else{
              $this->record_attempt();
              // Canceling authentication if num_attempts > max_attempts
              $this->set_error($this->check_attempts() ? 6 : 4);
            }
          }
          else{
            $this->set_error(6);
          }
        }
        else{
          $this->set_error(12);
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
    if (\defined('BBN_DATA_PATH') && $this->get_id()) {
      $this->path     = mvc::get_user_data_path($this->get_id());
      $this->tmp_path = mvc::get_user_tmp_path($this->get_id());
      if (!\defined('BBN_USER_PATH')) {
        define('BBN_USER_PATH', $this->path);
      }

      if ($create && !empty($this->path) && !empty($this->tmp_path)) {
        file\dir::create_path($this->path);
        file\dir::create_path($this->tmp_path);
        file\dir::delete($this->tmp_path, false);
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
         $this->class_cfg['arch']['sessions']['id'] => $this->get_id_session()
         ]
      );
    }

    return $this;
  }


}
