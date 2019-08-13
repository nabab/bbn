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
if ( !\defined('BBN_DATA_PATH') ){
  exit('The constant BBN_DATA_PATH must be defined');
}
class user extends models\cls\basic
{
  use
    models\tts\retriever,
    models\tts\dbconfig;

	protected static
    /** @var string The name of the session index in for session data */
    $sn = 'bbn_session',
    /** @var string The name of the session index in for user data */
    $un = 'bbn_user',
    /** @var array */
    $_defaults = [
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
        'users' => 'bbn_users',
      ],
      'arch' => [
        'groups' => [
          'id' => 'id',
          'group' => 'group',
          'cfg' => 'cfg'
        ],
        'hotlinks' => [
          'id' => 'id',
          'id_user' => 'id_user',
          'magic_string' => 'magic_string',
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
        'users' => [
          'id' => 'id',
          'id_group' => 'id_group',
          'email' => 'email',
          'username' => 'username',
          'login' => 'login',
          'admin' => 'admin',
          'dev' => 'dev',
          'cfg' => 'cfg',
          'active' => 'active'
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

  private
    /** @var bool */
    $just_login = false;

	protected
    /** @var user\session */
    $session = null,
    /** @var int */
    $error = null,
    /** @var string */
    $user_agent,
    /** @var string */
    $ip_address,
    /** @var string */
    $accept_lang,
    /** @var bool */
    $auth = false,
    /** @var string */
    $path,
    /** @var string */
    $sql,
    /** @var int */
    $id,
    /** @var int */
    $id_group,
    /** @var mixed */
    $alert,
    /** @var array */
    $cfg,
    /** @var array */
    $sess_cfg;

	public
    /** @var db */
    $db,
    /** @var mixed */
    $prev_time;

  /**
   * Returns the latest created connection, ie the current user's object
   * @return $this
   */
  public static function get_user(){
    return self::get_instance();
  }

  /**
   * Generates a random long string (16-32 chars)
   * @return string
   */
  public static function make_fingerprint(){
    return str::genpwd(32, 16);
  }

  /**
   * Creates a magic string which will be used for hotlinks
   * The hash is stored in the database
   * The key is sent to the user
   *7
	 * @return array
	 */
  public static function make_magic_string(){
    $key = self::make_fingerprint();
    return [
      'key' => $key,
      'hash' => hash('sha256', $key)
    ];
  }

  /**
   * Checks if a magic string complies with a hash
   * @param string $key
   * @param string $hash
   * @return bool
   */
  public static function is_magic_string($key, $hash){
    return ( hash('sha256', $key) === $hash );
  }

  /**
   * initialize and saves session
   * @return $this
   */
  private function _login($id){
    if ( $this->check() && $id ){
      $this
        ->_authenticate($id)
        ->_user_info()
        ->_init_dir(true)
        ->save_session();
    }
    return $this;
  }

  /**
   * Gathers all the information about a user and puts it in the session
   * The user's table data can be sent as argument if it has already been fetched
   *
   * @param array $d The user's table data
   *
   * @return $this
   */
  private function _user_info(){
    if ( $this->get_id() ){
      if ( !empty($this->get_session('cfg')) ){
        $this->cfg = $this->get_session('cfg');
        $this->id_group = $this->get_session('id_group');
      }
      else if ( $d = $this->db->rselect(
        $this->class_cfg['tables']['users'],
        array_unique(array_values($this->fields)),
        x::merge_arrays(
          $this->class_cfg['conditions'],
          [$this->fields['active'] => 1],
          [$this->fields['id'] => $this->id]))
      ){
        $r = [];
        foreach ( $d as $key => $val ){
          $this->$key = $val;
          $r[$key] = $key === $this->fields['cfg'] ? json_decode($val, true) : $val;
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

  /**
   * Gathers all the information about the user's session
   * The session's table data can be sent as argument if it has already been fetched
   *
   * @param mixed $d The session's table data or its ID
   * @return $this
   */
  private function _sess_info($id_session = null){
    if ( !str::is_uid($id_session) ){
      $id_session = $this->get_id_session();
    }
    else{
      $cfg = $this->_get_session('cfg');
    }
    if (
      empty($cfg) &&
      str::is_uid($id_session) &&
      ($id = $this->get_session('id')) &&
      ($d = $this->db->rselect(
        $this->class_cfg['tables']['sessions'],
        $this->class_cfg['arch']['sessions'],
        [
          $this->class_cfg['arch']['sessions']['id'] => $id_session,
          $this->class_cfg['arch']['sessions']['id_user'] => $id,
          $this->class_cfg['arch']['sessions']['opened'] => 1,
        ]))
    ){
      $cfg = json_decode($d['cfg'], true);
    }
    if ( \is_array($cfg) ){
      $this->sess_cfg = $cfg;
    }
    else{
      $this->set_error(14);
    }
    return $this;
  }

  /**
   * Checks the conformity of a given string with a hash
   * @param string $pass_given
   * @param string $pass_stored
   * @return bool
   */
  private function _check_password($pass_given, $pass_stored){
    return ($this->_crypt($pass_given) ===  $pass_stored);
  }

  /**
   * Use the configured hash function to convert a string into hash
   * @param string $st
   * @return string
   */
  private function _crypt($st){
    if ( !function_exists($this->class_cfg['encryption']) ){
      die("You need the PHP function {$this->class_cfg['encryption']} to have the user connection class working");
    }
    return eval("return {$this->class_cfg['encryption']}('$st');");
  }

  /**
   * Retrieves all user info from its session and populates the object
   * @return $this
   */
  private function _retrieve_session(){
    if ( !$this->id ){
      // The user ID must be in the session
      $id_session = $this->get_id_session();
      $id = $this->get_session('id');
      if ( $id_session && $id ){
        $this->_sess_info($id_session);
        if (
          isset($this->sess_cfg['fingerprint']) &&
          ($this->get_print($this->_get_session('fingerprint')) === $this->sess_cfg['fingerprint'])
        ){
          $this
            ->_authenticate($id)
            ->_user_info()
            ->_init_dir()
            ->save_session();
        }
        else{
          $this->set_error(19);
        }
      }
      else{
        //$this->set_error(15);
      }
    }
    return $this;
  }

  /**
   * Sets the user's session for the first time and creates the session's DB row
   * @return $this
   */
  private function _init_session(){

    // Getting or creating the session is it doesn't exist yet
    /** @var user\session */
    $this->session = user\session::get_instance();
    if ( !$this->session ){
      $this->session = new user\session();
    }

    /** @var int $id_session The ID of the session row in the DB */
    if ( $id_session = $this->get_id_session() ){
      $this->sess_cfg = json_decode($this->db->select_one(
        $this->class_cfg['tables']['sessions'],
        $this->class_cfg['arch']['sessions']['cfg'],
        [$this->class_cfg['arch']['sessions']['id'] => $id_session]
      ), true);
    }
    else{

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
      if ( $this->db->insert($this->class_cfg['tables']['sessions'], [
        $p['sess_id'] => $this->session->get_id(),
        $p['ip_address'] => $this->ip_address,
        $p['user_agent'] => $this->user_agent,
        $p['opened'] => 1,
        $p['last_activity'] => date('Y-m-d H:i:s'),
        $p['creation'] => date('Y-m-d H:i:s'),
        $p['cfg'] => json_encode($this->sess_cfg)
      ]) ){
        // Setting the session with its ID
        $id_session = $this->db->last_id();
        $this->session->set([
          'fingerprint' => $fingerprint,
          'tokens' => [],
          'id_session' => $id_session,
          'salt' => $salt
        ], self::$sn);
        $this->save_session();
      }
      else{
        $this->set_error(16);
      }
    }
    return $this;
  }

  /**
   * Sets the "session" part of the session
   * @param mixed $attr
   * @return $this $this
   */
  private function _set_session($attr){
    if ( $this->session->has(self::$sn) ){
      $args = \func_get_args();
      if ( (\count($args) === 2) && \is_string($args[0]) ){
        $attr = [$args[0] => $args[1]];
      }
      foreach ( $attr as $key => $val ){
        if ( \is_string($key) ){
          $this->session->set($val, self::$sn, $key);
        }
      }
    }
    return $this;
  }

  /**
   * Gets the "session" part of the session
   * @param string $attr
   * @return mixed
   */
  private function _get_session($attr){
    //die(\bbn\x::hdump(self::$sn, $this->session->has(self::$sn), $attr, $this->session->get(self::$sn)));
    if ( $this->session->has(self::$sn) ){
      return $attr ?
        $this->session->get(self::$sn, $attr) :
        $this->session->get(self::$sn);
    }
  }

  /**
   * Checks the credentials of a user
   * @param array $params
   * @return mixed
   */
  private function _check_credentials($params){
    if ( $this->check() ){
      /** @var array $f The form fields sent to identify the users */
      $f =& $this->class_cfg['fields'];

      if ( !isset($params[$f['salt']]) ){
        $this->set_error(11);
      }
      else{
        if ( !$this->check_salt($params[$f['salt']]) ){
          $this->set_error(17);
        }
      }
      if ( $this->check() ){
        if ( isset($params[$f['user']], $params[$f['pass']]) ){
          // Table structure
          $arch =& $this->class_cfg['arch'];

          $this->just_login = 1;

          // Database Query
          if (
            $id = $this->db->select_one(
              $this->class_cfg['tables']['users'],
              $this->fields['id'],
              x::merge_arrays(
                $this->class_cfg['conditions'],
                [$arch['users']['active'] => 1],
                [($arch['users']['login'] ?? $arch['users']['email']) => $params[$f['user']]])
            )
          ){
            $pass = $this->db->select_one(
              $this->class_cfg['tables']['passwords'],
              $arch['passwords']['pass'],
              [$arch['passwords']['id_user'] => $id],
              [$arch['passwords']['added'] => 'DESC']);
            if ( $this->_check_password($params[$f['pass']], $pass) ){
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
    else{

      die(var_dump($this->get_error()));
    }
    return $this->auth;
  }

  /**
   * If BBN_DATA_PATH is defined creates a directory and removes temp files
   * @return $this $this
   */
  private function _init_dir($create = false){
    if ( \defined('BBN_DATA_PATH') && $this->get_id() ){
      $this->path = BBN_DATA_PATH.'users/'.$this->get_id().'/';
      if ( !\defined('BBN_USER_PATH') ){
        define('BBN_USER_PATH', $this->path);
      }
      if ( $create ){
        file\dir::create_path(BBN_USER_PATH.'tmp');
        file\dir::delete(BBN_USER_PATH.'tmp', false);
      }
    }
    return $this;
  }

  /**
   * Sets a user as authenticated ($this->auth = 1)
   * @param int $id
   * @return $this
   */
  private function _authenticate($id){
    if ( $this->check() && $id ){
      $this->id = $id;
      $this->auth = 1;
      $this->db->update($this->class_cfg['tables']['sessions'], [
        $this->class_cfg['arch']['sessions']['id_user'] => $id
      ], [
        $this->class_cfg['arch']['sessions']['id'] => $this->get_id_session()
      ]);
    }
    return $this;
  }

  protected function set_error($err){
    $this->error = $err;
    //die(var_dump($code, $this->class_cfg['errors'][$code]));
  }

  /**
   * Returns the last known error and false if there was no error
   * @return mixed
   */
  public function get_error(){
    if ( $this->error ){
      return [
        'code' => $this->error,
        'text' => $this->class_cfg['errors'][$this->error]
      ];
    }
    return false;
  }

  protected function log_in($id): self
  {
    if ( $this->check() && $id ){
      $this
        ->_authenticate($id)
        ->_user_info()
        ->_init_dir(true)
        ->save_session();
    }
    return $this;
  }

  /**
   * Returns a "print", ie an identifier based on the user agent
   * @param false|string $fp
   * @return string
   */
  protected function get_print(string $fp = null){
    if ( !$fp ){
      $fp = $this->_get_session('fingerprint');
    }
    if ( $fp ){
      return sha1($this->user_agent.$this->accept_lang./*$this->ip_address .*/ $fp);
    }
    return false;
  }

  /**
   * Returns the database ID for the session's row if it is in the session
   * @return int
   */
  protected function get_id_session(){
    return $this->_get_session('id_session');
  }

  /**
   * Increment the num_attempt variable
   * @return $this
   */
  protected function record_attempt(){
    $this->cfg['num_attempts'] = isset($this->cfg['num_attempts']) ?
      $this->cfg['num_attempts']+1 : 1;
    $this->_set_session('num_attempts', $this->cfg['num_attempts']);
    $this->save_session();
    return $this;
  }

  /**
   * connection constructor
   * @param db $db
   * @param array $cfg
   * @param array $params
   */
  public function __construct(db $db, array $cfg = [], array $params = []){

    // The database connection
    $this->db = $db;

    // The client environment variables
    $this->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $this->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $this->accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

    // Setting up the class configuration
    $this->_init_class_cfg($cfg);

    // Creating the session's variables if they don't exist yet
    $this->_init_session();
    self::retriever_init($this);

    $f =& $this->class_cfg['fields'];

    // The user logs in
    if ( isset($params[$f['user']], $params[$f['pass']], $params[$f['salt']]) ){
      /** @todo separate credentials and salt checking */
      $this->_check_credentials($params);
    }
    /** @todo revise the process: dying is not the solution! */
    // The user is not known yet
    else if (
      isset($params[$f['key']], $params[$f['id']], $params[$f['pass1']], $params[$f['pass2']], $params[$f['action']]) &&
      ($params[$f['action']] === 'init_password') &&
      ($params[$f['pass1']] === $params[$f['pass2']])
    ){
      if ( $id = $this->get_id_from_magic_string($params[$f['id']], $params[$f['key']]) ){
        $this->expire_hotlink($params[$f['id']]);
        $this->force_password($params[$f['pass2']], $id);
        $this->session->set([]);
        // Reloads the page
        header('Location: ./');
        die();
      }
      else{
        $this->set_error(18);
      }
    }
    else {
      $this->check_session();
    }
  }

  /**
   * Returns the salt string kept in session.
   *
   * @return string
   */
  public function get_salt(){
    return $this->_get_session('salt');
  }

  /**
   * Confronts the given string with the salt string kept in session.
   *
   * @return boolean
   */
  public function check_salt($salt){
    return $this->get_salt() === $salt;
  }

  /**
   * Returns the current user's configuration.
   *
   * @param string $attr
   * @return mixed
   */
  public function get_cfg($attr = ''){
    if ( $this->check() ){
      if ( !$this->cfg ){
        $this->cfg = $this->session->get('cfg');
      }
      if ( empty($attr) ){
        return $this->cfg;
      }
      if ( isset($this->cfg[$attr]) ){
        return $this->cfg[$attr];
      }
      return false;
    }
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
    return $this->path.'tmp/';
  }

  /**
   * Returns the list of tables used by the current class.
   * @return array
   */
  public function get_tables(): ?array
  {
    if ( !empty($this->class_cfg) ){
      return $this->class_cfg['tables'];
    }
    return null;
  }

  /**
   * Returns the list of fields of the given table, and if empty the list of fields for each table.
   * @param string $table
   * @return array|null
   */
  public function get_fields(string $table = ''): ?array
  {
    if ( !empty($this->class_cfg) ){
      if ( $table ){
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
   *
   * @return bool
   */
  public function update_info(array $d)
  {
    if ( $this->check_session() ){
      $update = [];
      foreach ( $d as $key => $val ){
        if (
          ($key !== $this->fields['id']) &&
          ($key !== $this->fields['cfg']) &&
          ($key !== 'auth') &&
          ($key !== 'pass') &&
          \in_array($key, $this->fields)
        ){
          $update[$key] = $val;
        }
      }
      if ( \count($update) > 0 ){
        $r = $this->db->update(
                $this->class_cfg['tables']['users'],
                $update,
                [$this->fields['id'] => $this->id]);
        /** @todo Why did I do this?? */
        $this->set_session(['cfg' => false]);
        $this->_user_info();
        return $r;
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
    return $this->just_login;
  }

	/**
   * Sets the given attribute(s) in the user's session.
   *
	 * @return $this
	 */
  public function set_session($attr){
    if ( $this->session->has(self::$un) ){
      $args = \func_get_args();
      if ( (\count($args) === 2) && \is_string($args[0]) ){
        $attr = [$args[0] => $args[1]];
      }
      foreach ( $attr as $key => $val ){
        if ( \is_string($key) ){
          $this->session->set($val, self::$un, $key);
        }
      }
    }
    return $this;
  }

	/**
   * Returns session property from the session's user array.
   *
   * @param null|string The property to get
	 * @return mixed
	 */
  public function get_session($attr = null){
    if ( $this->session->has(self::$un) ){
      return $attr ? $this->session->get(self::$un, $attr) : $this->session->get(self::$un);
    }
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
    if ( $id_session && $this->check() ){
      $p =& $this->class_cfg['arch']['sessions'];
      $this->db->update($this->class_cfg['tables']['sessions'], [
        $p['last_activity'] => date('Y-m-d H:i:s')
      ], [
        $p['id'] => $id_session
      ]);
    }
    else{
      $this->set_error(13);
    }
    return $this;
  }

  /**
   * Saves the session config in the database.
   *
	 * @return $this
	 */
  public function save_session(): self
  {
    $id_session = $this->get_id_session();
    //die(var_dump($id_session, $this->check()));
    if ( $id_session && $this->check() ){
      $p =& $this->class_cfg['arch']['sessions'];
      $this->db->update($this->class_cfg['tables']['sessions'], [
        $p['id_user'] => $this->id,
        $p['sess_id'] => $this->session->get_id(),
        $p['ip_address'] => $this->ip_address,
        $p['user_agent'] => $this->user_agent,
        $p['opened'] => 1,
        $p['last_activity'] => date('Y-m-d H:i:s'),
        $p['cfg'] => json_encode($this->sess_cfg)
      ], [
        $p['id'] => $id_session
      ]);
    }
    else{
      $this->set_error(13);
    }
    return $this;
  }

  /**
   * Closes the session in the database, and if $with_session is true, deletes also the session information.
   *
   * @param bool $with_session
   * @return user
   */
  public function close_session($with_session = false): self
  {
    $p =& $this->class_cfg['arch']['sessions'];
    $this->db->update($this->class_cfg['tables']['sessions'], [
        $p['ip_address'] => $this->ip_address,
        $p['user_agent'] => $this->user_agent,
        $p['opened'] => 0,
        $p['last_activity'] => date('Y-m-d H:i:s'),
        $p['cfg'] => json_encode($this->sess_cfg)
      ],[
        $p['id_user'] => $this->id,
        $p['sess_id'] => $this->session->get_id()
      ]);
    $this->auth = false;
    $this->id = null;
    $this->sess_cfg = null;
    if ( $with_session ){
      $this->session->set([]);
    }
    else{
      $this->session->set([], self::$un);
    }
    return $this;
  }

  /**
   * Returns false if the max number of connections attempts has been reached
   * @return bool
   */
  public function check_attempts(): bool
  {
    if ( !isset($this->cfg) ){
      return false;
    }

    //die(var_dump($this->session->get('num_attempts'), $this->sess_cfg, $this->session->get('attempts')));
    if ( isset($this->cfg['num_attempts']) && $this->cfg['num_attempts'] > $this->class_cfg['max_attempts'] ){
      return false;
    }
    return true;
  }

  /**
   * Saves the user's config in the cfg field of the users' table
   * return connection
   */
  public function save_cfg()
  {
    if ( $this->check() ){
      $this->db->update(
          $this->class_cfg['tables']['users'],
          [$this->fields['cfg'] => json_encode($this->cfg)],
          [$this->fields['id'] => $this->id]);
    }
    return $this;
  }

  /**
   * return connection
   */
  public function set_cfg($attr){
    if ( null !== $this->cfg ){
      $args = \func_get_args();
      if ( (\count($args) === 2) && \is_string($attr) ){
        /** @var array $attr */
        $attr = [$args[0] => $args[1]];
      }
      foreach ( $attr as $key => $val ){
        if ( \is_string($key) ){
          $this->cfg[$key] = $val;
        }
      }
      $this->set_session(['cfg' => $this->cfg]);
    }
    return $this;
  }

  /**
   * @param $attr
   * @return $this
   */
  public function unset_cfg($attr){
    if ( null !== $this->cfg ){
      $args = \func_get_args();
      if ( \is_string($attr) ){
        /** @var array $attr */
        $attr = [$attr];
      }
      foreach ( $attr as $key ){
        if ( isset($key) ){
          unset($this->cfg[$key]);
        }
      }
      $this->set_session(['cfg' => $this->cfg]);
    }
    return $this;
  }

  /**
	 * @return $this
	 */
	public function refresh_info()
	{
    if ( $this->check() ){
      $this->_user_info();
      $this->_sess_info();
    }
    return $this;
	}

  /**
   * Returns true if authenticated false otherwise
   * @return bool
   */
  public function is_auth(){
	  return $this->auth;
  }

	/**
   * Retrieves user's info from session if needed and checks if authenticated
	 * @return bool
	 */
	public function check_session(){
	  if ( $this->check() ){
      $this->_retrieve_session();
      return $this->auth;
    }
	}

  /**
   * Returns the user's ID if there is no error
   * @return false|int
   */
  public function get_id(){
    if ( $this->check() ){
      return $this->id;
    }
    return false;
  }

  public function get_group()
  {
    if ( $this->check() ){
      return $this->id_group;
    }
  }

  public function expire_hotlink($id){
    return $this->db->update($this->class_cfg['tables']['hotlinks'],
            [$this->class_cfg['arch']['hotlinks']['expire'] => date('Y-m-d H:i:s')],
            [$this->class_cfg['arch']['hotlinks']['id'] => $id]);
  }

  /**
   * @param $id
   * @param $key
   * @return bool
   */
  public function get_id_from_magic_string($id, $key){
    if ( $val = $this->db->rselect($this->class_cfg['tables']['hotlinks'], [
      $this->class_cfg['arch']['hotlinks']['magic_string'],
      $this->class_cfg['arch']['hotlinks']['id_user'],
    ],[
      $this->class_cfg['arch']['hotlinks']['id'] => $id
    ]) ){
      if ( self::is_magic_string($key, $val[$this->class_cfg['arch']['hotlinks']['magic_string']]) ){
        return $val['id_user'];
      }
    }
    return false;
  }

  /**
   * @return boolean
   */
  public function is_admin()
  {
    return !!$this->get_session('admin');
  }

  /**
   * @return boolean
   */
  public function is_dev()
  {
    return $this->is_admin() || !!$this->get_session('dev');
  }

  public function get_manager($mail = false){
    $mgr = new user\manager($this, $mail);
    return $mgr;
  }

	/**
   * Checks if an error has been thrown or not
	 * @return boolean
	 */
	public function check(){
		return $this->get_error() ? false : true;
	}

  /**
   * Unauthenticates, resets the config and destroys the session
   * @return void
   */
  public function logout(){
    $this->auth = false;
    $this->cfg = [];
    $this->close_session();
  }

  /** Returns an instance of the mailer class */
  public function get_mailer(){
    return new mail();
  }

  /**
   * Change the password in the database after checking the current one
   * @return boolean
   */
  public function set_password($old_pass, $new_pass){
    if ( $this->auth ){
      $pwt = $this->class_cfg['tables']['passwords'];
      $pwa = $this->class_cfg['arch']['passwords'];
      $stored_pass = $this->db->select_one($pwt, $pwa['pass'], [
        $this->class_cfg['arch']['passwords']['id_user'] => $this->id
      ], [
        $this->class_cfg['arch']['passwords']['added'] => 'DESC'
      ]);
      if ( $this->_check_password($old_pass, $stored_pass) ){
        return $this->force_password($new_pass, $this->get_id());
      }
    }
    return false;
  }

  /**
   * Change the password in the database
   * @return boolean
   */
  public function force_password($pass, $id){
    if ( $this->check() ){
      return $this->db->insert($this->class_cfg['tables']['passwords'], [
        $this->class_cfg['arch']['passwords']['pass'] => $this->_crypt($pass),
        $this->class_cfg['arch']['passwords']['id_user'] => $id,
        $this->class_cfg['arch']['passwords']['added'] => date('Y-m-d H:i:s')
      ]);
    }
    return false;
  }

  /**
   * Returns the written name of this or a user
   * @return string|false
   */
  public function get_name($usr = null){
    if ( $this->auth ){
      if ( \is_null($usr) ){
        $usr = $this->get_session();
      }
      else if ( str::is_uid($usr) ){
        $mgr = $this->get_manager();
        $usr = $mgr->get_user($usr);
      }
      if ( isset($usr[$this->class_cfg['show']]) ){
        return $usr[$this->class_cfg['show']];
      }
    }
    return false;
  }

  /**
   * Returns the written name of this or a user
   * @return string|false
   */
  public function get_email($usr = null){
    if ( $this->auth ){
      if ( \is_null($usr) ){
        $usr = $this->get_session();
      }
      else if ( str::is_uid($usr) ){
        $mgr = $this->get_manager();
        $usr = $mgr->get_user($usr);
      }
      if ( isset($usr[$this->class_cfg['email']]) ){
        return $usr[$this->class_cfg['email']];
      }
    }
    return false;
  }

}
