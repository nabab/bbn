<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
/**
 * A user authentication and session management Class
 *
 * The user session will have the following structure:
 *
 * [
 *  'id' => x,
 *  'salt' => 'xxxxx',
 *  'auth' => [
 *    'fingerprint' => 'xxx',
 *    'last_renew' => 123456789
 *  ],
 *  'info' => [
 *    'log_tries' => 0,
 *    'last_attempt' => 123456789,
 *    'login' => 'xxx',
 *    'email' => 'xxx@xx.xx',
 *    ...
 *  ],
 *  'data' => [],
 *  'properties' => [],
 *  'permissions' => []
 * ]
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Authentication
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Groups and hotlinks features
 * @todo Implement Cache for session requests' results?
 */

class connection
{

	protected static
          /** @var array Contains the error messages and the architecture of the database */
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
              10 => 'problem during user creation'
            ],
            'tables' => [
              'groups' => 'bbn_users_groups',
              'hotlinks' => 'bbn_users_hotlinks',
              'passwords' => 'bbn_users_passwords',
              'sessions' => 'bbn_users_sessions',
              'usergroups' => 'bbn_users_usergroups',
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
                'id_user' => 'id_user',
                'sess_id' => 'sess_id',
                'ip_address' => 'ip_address',
                'user_agent' => 'user_agent',
                'auth' => 'auth',
                'opened' => 'opened',
                'last_activity' => 'last_activity',
                'cfg' => 'cfg',
              ],
              'usergroups' => [
                'id_group' => 'id_group',
                'id_user' => 'id_user',
              ],
              'users' => [
                'id' => 'id',
                'email' => 'email',
                'login' => 'email',
                'cfg' => 'cfg'
              ],
            ],
            /*
             * Password saving encryption
             * @var string
             */
            'encryption' => 'sha1',
            /*
             * Additional conditions when querying the users' table
             * @var array
             */
            'conditions' => [],
            /*
             * Additional fields to select from the users' table
             * They will become property
             * Their names mustn't interfere with existing properties
             * @var array
             */
            'additional_fields' => [],
            /*
             * The session name
             * @var string
             */
            'sess_name' => false,
            /*
             * In the session array the index on which user info will be stored
             * i.e. the default storage will be $_SESSION[BBN_SESS_NAME]['user']
             */
            'sess_user' => 'user',
            /*
             * length in minutes of the session regeneration
             * @var integer
             */
            'sess_length' => 5,
            /*
             * Number of times a user can try to log in during the period retry_length
             * @var integer
             */
            'max_attempts' => 5,
            /*0
             * User ban's length in minutes after max attempts is reached
             * @var integer
             */
            'retry_length' => 5,
            /*
             * Sets if the groups features should be in used
             * @var bool
             */
            'groups' => false,
            /*
             * Sets if the hotlinks features should be in used
             * @var bool
             */
            'hotlinks' => false,
            /*
             *
             */
            'refresh_delay' => 300
          ];

  private
          /** @var array Contains the configuration of the class as the combination of defaults and constructor config */
          $cfg,
          /** @var integer Timestamp from the last refresh of information from the database */
          $last_refresh;
  
	protected
          /** @var string */
          $error = null,
          /** @var array */
          $groups = [],
          /** @var array */
          $permissions = [],
          /** @var string */
          $user_agent,
          /** @var string */
          $ip_address,
          /** @var bool */
          $auth = false,
          /** @var string */
          $sql,
          /** @var int */
          $id,
          /** @var mixed */
          $alert,
          /** @var array The current user's session's config (fingerprint, last_renew) */
          $sess_cfg,
          /** @var array The current user configuration (i.e. preferences) */
          $user_cfg,
          /** @var array The list of fields to get from the users table */
          $fields;
          

	public
          /** @var \bbn\db */
          $db,
          /** @var mixed */
          $prev_time;


	/**
	 * @return string Makes a random string between 16 and 32 chars
	 */
  public static function make_fingerprint()
  {
    return \bbn\str::genpwd(32, 16);
  }
  
  /**
   * Checks the correspondance between a key and a hash
   *
   * @param string $key
   * @param string $hash
   *
   * @return bool
   */
  protected static function is_magic_string($key, $hash)
  {
    return ( hash('sha256', $key) === $hash );
  }

  /**
   * Sets the session name in the default config if constant BBN_SESS_NAME is defined
   *
   * @return void
   */
  private static function init(){
    if ( defined('BBN_SESS_NAME') ){
      self::$_defaults['sess_name'] = BBN_SESS_NAME;
    }
  }
  
  /**
   * @param \bbn\db $db
   * @param array $cfg
   * @param array|string $credentials
   *
	 * @return \bbn\user\connection
	 */
	public function __construct(\bbn\db $db, array $cfg, $credentials='')
	{
    self::init();

		$this->db = $db;
		
    $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $this->ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    /** The class configuration is the result of a merge between the class' default values and the configuration sent to the constructor */
    $this->cfg = \bbn\x::merge_arrays(self::$_defaults, $cfg);

    // As we'll give the object the properties of these additional fields they should not conflict with existing ones
    foreach ( $this->cfg['additional_fields'] as $f ){
      if ( property_exists($this, $f) ) {
        die("Wrong configuration: the column's name $f is illegal!");
      }
    }
    
    // The selection comprises the defined fields of the users table
    // Plus a bunch of user-defined additional fields in the same table
    $this->fields = \bbn\x::merge_arrays($this->cfg['arch']['users'], $this->cfg['additional_fields']);

    // Case where the user logs in
    // Allowing the use of a simple array [user, pass]
    if ( isset($credentials[0], $credentials[1]) ) {
      $credentials['user'] = $credentials[0];
      $credentials['pass'] = $credentials[1];
    }
    
    // Expecting array with user and pass keys
    if ( isset($credentials['user'], $credentials['pass']) ) {
      $this->_identify($credentials);
    }
    // Otherwise the session is checked
		else {
      $this->check_session();
		}
    if ( $err = $this->get_error() ){
      die($err);
    }

    return $this;
	}


  /**
   * Gets the message corresponding to an error code if the error property is not null
   *
   * @return string|false
   */
  public function get_error(){
    return ( !is_null($this->error) && isset($this->cfg['errors'][$this->error]) ) ?
      $this->cfg['errors'][$this->error] : false;
  }

  /**
   * Gets the class configuration i.e. error messages and database structure
   *
   * @return array
   */
  public function get_class_cfg(){
    return $this->cfg;
  }

  /**
   * Sets a new identified session with its proper fingerprint (after $this->auth has been set to true)
   *
	 * @return \bbn\user\connection
	 */
	private function _init_session()
  {
    if ( $this->check() ){

      $this->delete_session(false);

      $fingerprint = self::make_fingerprint();

      $s = $this->get_full_session();

      $this->set_session([
        'id' => $this->id,
        'fingerprint' => $fingerprint,
        'tokens' => []
      ]);

      $this->sess_cfg = [
        'fingerprint' => $this->get_print($fingerprint),
        'last_renew' => time()
      ];

      $this->save_session();

    }
    return $this;
  }
  
  /**
   * Gathers all the information about a user and puts it in the session
   * The user's table data can be sent as argument if it has already been fetched
   * 
   * @param array $d The user's table data
   * 
   * @return \bbn\user\connection
   */
  private function _user_info(array $d=null){
    // $this->id must be defined i.e. user must be identified but doesn't have to be yet authenticated
    if ( !empty($this->id) ){
      if ( is_null($d) ){
        $d = $this->db->rselect(
              $this->cfg['tables']['users'],
              $this->fields,
              \bbn\x::merge_arrays(
                    $this->cfg['conditions'],
                    [$this->cfg['arch']['users']['id'] => $this->id]));
      }
      if ( is_array($d) ){
        $r = [];
        foreach ( $d as $key => $val ){
          if ( ($key !== 'id') && ($key !== 'cfg') && ($key !== 'auth') && ($key !== 'pass') ){
            $this->$key = $val;
            $r[$key] = $val;
          }
        }
        /*
        $this->set_session('info', $r);

        $this->user_cfg = empty($d['cfg']) ?
                        ['log_tries' => 0] : json_decode($d['cfg'], true);
        */
        $this->set_info($r);
        if ( empty($d['cfg']) ){
          $this->set_info(['log_tries' => 0]);
        }
        else {
          $this->set_cfg(json_decode($d['cfg'], true));
        }
        // Groups
        $this->permissions = [];
        $this->groups = $this->db->get_col_array("
          SELECT {$this->cfg['arch']['usergroups']['id_group']}
          FROM {$this->cfg['tables']['usergroups']}
          WHERE {$this->cfg['arch']['usergroups']['id_user']} = ?",
          $this->id);
        foreach ( $this->groups as $gr ){
          if ( $p = $this->db->get_val(
            $this->cfg['tables']['groups'],
            $this->cfg['arch']['groups']['cfg'],
            $this->cfg['arch']['groups']['id'],
            $gr) ){
            $this->permissions = array_merge(json_decode($p, 1), $this->permissions);
          }
        }
        $this->set_session('permissions', $this->permissions);
        $this->set_session('groups', $this->groups);
      }
    }
    $this->last_refresh = time();
    return $this;
  }

  /**
   * Changes the corresponding columns' values in the user's table
   * 
   * @param array $d The new data
   * 
   * @return bool
   */
  public function update_info(array $d)
  {
    if ( $this->check() ){
      $update = [];
      $cols = $this->cfg['arch']['users'];
      foreach ( $d as $key => $val ){
        if ( ($key !== $cols['id']) && ($key !== $cols['cfg']) && in_array($key, $this->fields) ){
          $update[$key] = $val;
        }
      }
      if ( count($update) > 0 ){
        return $this->db->update(
                $this->cfg['tables']['users'],
                $update,
                [$this->cfg['arch']['users']['id'] => $this->id]);
      }
    }
    return false;
  }

  /**
   * Returns the sessions' table's content for the current session
   *
   * @return \bbn\user\connection
   */
  private function _sess_info(array $d=null){
    // $this->id must be defined i.e. user must be identified but doesn't have to be yet authenticated
    if ( !empty($this->id) ){
      if ( is_null($d) ){
        $d = $this->db->rselect(
            $this->cfg['tables']['sessions'],
            $this->cfg['arch']['sessions'],
            [
                $this->cfg['arch']['sessions']['sess_id'] => session_id(),
                $this->cfg['arch']['sessions']['id_user'] => $this->id
            ]);
      }
      if ( is_array($d) ){
        $this->sess_cfg = json_decode($d['cfg'], 1);
      }
    }
    return $this;
  }


  /**
   * Checks if the password given corresponds to the password stored once encrypted
   *
   * @param string $pass_given
   * @param string $pass_stored
   *
   * @return bool
   */
  private function _check_password($pass_given, $pass_stored)
  {
    return ($this->crypt($pass_given) ===  $pass_stored);
  }

  /**
   * Encrypt a string with the encryption algorithm chosen in the class' config
   *
   * @param string $st
   *
   * @return string
   */
  public function crypt($st){
    if ( !function_exists($this->cfg['encryption']) ){
      die("You need the PHP function {$this->cfg['encryption']} to have the user connection class working");
    }
    if ( empty($st) || !is_string($st) ){
      die("You need to provide a non empty string in order to crypt it");
    }
    return $this->cfg['encryption']($st);
  }

  /**
   * Identify a user inside the users' table and returns its authentication status according to his credentials
   *
   * @param array $credentials (with 2 values or with user and pass keys)
   *
	 * @return bool
	 */
	private function _identify($credentials)
	{
    if ( $this->check_session() ){
      $this->close_session();
    }
		if ( isset($credentials['user'],$credentials['pass']) ) {
      // Table structure
			$arch =& $this->cfg['arch'];
      
      // Database Query 
      if ( $d = $this->db->rselect(
              $this->cfg['tables']['users'],
              $this->fields,
              \bbn\x::merge_arrays(
                    $this->cfg['conditions'],
                    [$arch['users']['login'] => $credentials['user']])
              ) ){

        $this->id = $d['id'];
        $this->_user_info($d);

       // Canceling authentication if num_attempts > max_attempts
        if ( !$this->check_attempts() ){
          $this->error = 4;
        }
        else if ( $pass = $this->db->select_one(
            $this->cfg['tables']['passwords'],
            $arch['passwords']['pass'],
            [$arch['passwords']['id_user'] => $this->id],
            [$arch['passwords']['added'] => 'DESC'])
        ){
          if ($this->_check_password($credentials['pass'], $pass)) {
            // From this point the user is considered identified
            $this->auth = 1;
            $this->_init_session();
          }
          else {
            // Wrong combination user/pass, adding attempt
            $this->record_attempt();
            $this->error = 6;
          }
        }
      }
      else{
        $this->error = 6;
      }
    }
    return $this->auth;
	}

  /**
   * Creates a string based on the session fingerprint, the user agent and the IP address provided that the user has a session fingerprint
   *
   * @return string|bool
   */
  protected function get_print()
	{
    if ( ($fp = $this->get_session('fingerprint')) ){
      return sha1($this->user_agent . $this->ip_address . $fp);
    }
    return false;
	}

  /**
   * Returns the whole app's session
   *
   * @return false|array
   */
  public function get_full_session()
  {
    if ( !isset($this->cfg) ){
      return false;
    }
    return $this->cfg['sess_name'] ? $_SESSION[$this->cfg['sess_name']] : $_SESSION;
  }

  /**
   * Checks if the number of login attempts has gone over the config value max_attempts
   *
   * @return bool
   */
  public function check_attempts(){
    //if ( !isset($this->user_cfg) ){
    $info = $this->get_info();
    if ( empty($info) ){
      return false;
    }
    //if ( isset($this->user_cfg['log_tries']) && ($this->user_cfg['log_tries'] > $this->cfg['max_attempts']) ){
    if ( isset($info['log_tries']) && ($info['log_tries'] > $this->cfg['max_attempts']) ){
      return false;
    }
    return true;
  }

  /**
   * Adds one to the user configuration
   *
   * @return \bbn\user\connection
   */
  protected function record_attempt(){
    //if ( isset($this->user_cfg) ){
    $info = $this->get_info();
    if ( !empty($info) ){
      /*
      $this->user_cfg['log_tries'] = isset($this->user_cfg['log_tries']) ?
        $this->user_cfg['log_tries']+1 : 1;
      $this->set_session('log_tries', $this->user_cfg['log_tries']);
      */
      $this->set_info(['log_tries' => isset($info['log_tries']) ? $info['log_tries']+1 : 1]);
      $this->save_cfg();
    }
    return $this;
  }

  /**
	 * @return \bbn\user\connection
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
	 * @return bool 
	 */
	public function check_session()
	{
    // If this->id is set it means we've already looked it up
    //\bbn\x::hdump($this->sess_cfg, $this->has_session('fingerprint'), $this->get_print($this->get_session('fingerprint')), $this->sess_cfg['fingerprint']);
		if ( empty($this->id) ) {
      
      // The user ID must be in the session
			if ( $this->has_session('id') ) {
        $this->id = $this->get_session('id');
        
        $this->_sess_info();

        if ( !empty($this->sess_cfg['fingerprint']) && $this->has_session('fingerprint') &&
          ($this->get_print($this->get_session('fingerprint')) === $this->sess_cfg['fingerprint']) ){
          $this->auth = 1;
          if ( (time() - $this->last_refresh) > $this->cfg['refresh_delay'] ) {
            $this->_user_info();
          }
          $this->save_session();
        }
			}
		}
		return $this->auth;
	}
  
  public function get_id()
  {
    if ( $this->check() ) {
      return isset($this->id) ? $this->id : false;
    }
  }
  
  public function expire_hotlink($id){
    return $this->db->update($this->cfg['tables']['hotlinks'],
            [$this->cfg['arch']['hotlinks']['expire'] => date('Y-m-d H:i:s')],
            [$this->cfg['arch']['hotlinks']['id'] => $id]);
  }


  public function check_magic_string($id, $key)
  {
    if ( $val = $this->db->rselect($this->cfg['tables']['hotlinks'], [
      $this->cfg['arch']['hotlinks']['magic_string'],
      $this->cfg['arch']['hotlinks']['id_user'],
      ],[
        $this->cfg['arch']['hotlinks']['id'] => $id
            ]) ){
      if ( self::is_magic_string($key, $val[$this->cfg['arch']['hotlinks']['magic_string']]) ){
        $this->id = $val['id_user'];
        $this->_user_info();
        $this->auth = 1;
        $this->_init_session();
        return $this->id;
      }
    }
    return false;
  }
  
	/**
	 * @return boolean
	 */
  public function is_admin()
  {
    return $this->has_permission("admin");
  }

	/**
	 * @return boolean
	 */
  public function is_allowed($perm)
  {
    return ( $this->has_permission("admin") || $this->has_permission($perm) );
  }

	/**
	 * @return boolean
	 */
	public function check()
	{
		return $this->auth;
	}

  /**
   * @return void
   */
  public function logout()
  {
    $this->close_session();
    session_destroy();
  }

  /**
   * @return void
   */
  public function get_name()
  {
    if ( $this->auth ){
      return $_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']]['login'];
    }
    return false;
  }

  /**
   * @return void
   */
  public function get_token($st)
  {
    if ( $this->auth ){
      $s =& $_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']];
      if ( isset($s['tokens']) ) {
        $s['tokens'][$st] = \bbn\str::genpwd();
        return $s['tokens'][$st];
      }
    }
    return false;
  }

  /**
   * @return void
   */
  public function check_token($st, $token)
  {
    if ( $this->auth ){
      $s =& $_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']];
      if ( isset($s['tokens'], $s['tokens'][$st]) ) {
        return $s['tokens'][$st] === $token;
      }
    }
    return false;
  }

	/**
	 * @return void 
	 */
	public function set_password($old_pass, $new_pass)
	{
		if ( $this->auth ){
      $stored_pass = $this->db->select_one(
              $this->cfg['tables']['passwords'],
              $this->cfg['arch']['passwords']['pass'],
              [$this->cfg['arch']['passwords']['id_user'] => $this->id],
              [$this->cfg['arch']['passwords']['added'] => 'DESC']);
      if ( $this->_check_password($old_pass, $stored_pass) ){
        return $this->force_password($new_pass);
      }
		}
		return false;
	}

  	/**
	 * @return boolean 
	 */
	public function force_password($pass)
	{
		if ( $this->auth )
		{
      return $this->db->insert(
              $this->cfg['tables']['passwords'], [
                $this->cfg['arch']['passwords']['pass'] => $this->crypt($pass),
                $this->cfg['arch']['passwords']['id_user'] => $this->id,
                $this->cfg['arch']['passwords']['added'] => date('Y-m-d H:i:s')]);
		}
		return false;
	}

  /**
   * Returns all the current user's permissions
   *
   * @return array
   */
  public function get_permissions(){
    return $this->permissions;
  }

  /**
   * Checks if the user has the given permission
   *
   * @param string $name The name of the permission
   *
   * @return bool
   */
  public function has_permission($name, $check_admin=1){
    if ( !is_string($name) ){
      throw new \InvalidArgumentException('Has permission have a string as argument');
    }
    if ( isset($this->permissions[$name]) && $this->permissions[$name] ){
      return 1;
    }
    else if ( $check_admin && !empty($this->permissions["admin"]) ){
      return 1;
    }
    return false;
  }

  /**
   * Checks if the user has the given permission and dies otherwise
   *
   * @param string $name The name of the permission
   *
   * @return void
   */
  public function check_permission($name){
    if ( !$this->has_permission($name) ){
      die("You don't have the requested permission ($name)");
    }
  }

  /**
   * Sets the given indexes of the session to their respective values. If 2 parameters are given and the first is a string, the first one will be considered as the index and the second as the value
   *
   * @param array $attr
   *
   * @return \bbn\user\connection
   */
  public function set_auth($attr){
    if ( $this->cfg['sess_name'] ){
      $s =& $_SESSION[$this->cfg['sess_name']];
    }
    else{
      $s =& $_SESSION;
    }
    if ( !isset($s[$this->cfg['sess_user']]) ){
      $s[$this->cfg['sess_user']] = [];
    }
    if ( isset($s[$this->cfg['sess_user']]) ){
      $su =& $s[$this->cfg['sess_user']];
      $args = func_get_args();
      if ( (count($args) === 2) && is_string($args[0]) ){
        $attr = [$args[0] => $args[1]];
      }
      foreach ( $attr as $key => $val ){
        if ( is_string($key) ){
          $su[$key]  = $val;
        }
      }
    }
    return $this;
  }

  /**
   * Sets the given indexes of the session to their respective values.
   *
   * @param array $attr
   * @param mixed $type
   *
   * @return \bbn\user\connection
   */
  public function set_session(array $attr, $type = false){
    if ( $this->cfg['sess_name'] ){
      $s =& $_SESSION[$this->cfg['sess_name']];
    }
    else{
      $s =& $_SESSION;
    }
    if ( $type && is_string($type) ){
      if ( !isset($s[$type]) ){
        $s[$type] = [];
      }
      $s =& $s[$type];
    }
    foreach ( $attr as $key => $val ){
      if ( is_string($key) ){
        $s[$key] = $val;
      }
    }
    return $this;
  }

  /**
   * Delete the given indexes from the session
   *
   * @param array|string $attr
   * @param mixed $type
   *
   * @return \bbn\user\connection
   */
  public function unset_session($attr, $type = false){
    if ( $this->cfg['sess_name'] ){
      $s =& $_SESSION[$this->cfg['sess_name']];
    }
    else{
      $s =& $_SESSION;
    }
    if ( $type && is_string($type) ){
      $s =& $s[$type];
    }
    if ( !is_array($attr) ){
      $attr = [$attr];
    }
    foreach ( $attr as $key ){
      if ( is_string($key) && isset($s[$key]) ){
        unset($s[$key]);
      }
    }
    return $this;
  }

  /**
   * Resets the session information if they are not defined or if $force is true
   *
   * @return false|\bbn\user\connection
   */
  public function delete_session($force=1){
    if (!isset($this->cfg)) {
      return false;
    }
    if ( $this->cfg['sess_name'] ) {
      if ( empty($_SESSION[$this->cfg['sess_name']]) || $force ) {
        $_SESSION[$this->cfg['sess_name']] = [];
      }
    }
    else if ( empty($_SESSION) || $force ){
      $_SESSION = [];
    }
    return $this;
  }

  /**
   * Returns a given property of the session
   *
   * @param string $attr The property name
   *
   * @return mixed
   */
  public function get_session($attr){
    if ( $this->has_session($attr) ){
      $s = $this->get_full_session();
      return $s[$this->cfg['sess_user']][$attr];
    }
  }

  /**
   * Checks if the given property exists in the app's session
   *
   * @return bool
   */
  public function has_session($attr){
    $s = $this->get_full_session();
    return (
      is_string($attr) &&
      isset($s[$this->cfg['sess_user']][$attr])
    );
  }

  /**
   * Saves the session state in the database
   *
   * @return \bbn\user\connection
   */
  public function save_session() {

    // Sessions' table structure
    $p =& $this->cfg['arch']['sessions'];

    $this->db->insert_update($this->cfg['tables']['sessions'], [
      $p['id_user'] => $this->id,
      $p['sess_id'] => session_id(),
      $p['ip_address'] => $this->ip_address,
      $p['user_agent'] => $this->user_agent,
      $p['auth'] => $this->auth ? 1 : 0,
      $p['opened'] => 1,
      $p['last_activity'] => date('Y-m-d H:i:s'),
      $p['cfg'] => json_encode($this->sess_cfg)
    ]);
    return $this;
  }

  /**
   * Destroys the session information and saves the session's new state
   *
   * @return \bbn\user\connection
   */
  public function close_session() {

    // The sessions table's architecture
    $p =& $this->cfg['arch']['sessions'];

    $this->db->update($this->cfg['tables']['sessions'], [
      $p['ip_address'] => $this->ip_address,
      $p['user_agent'] => $this->user_agent,
      $p['auth'] => $this->auth ? 1 : 0,
      $p['opened'] => 0,
      $p['last_activity'] => date('Y-m-d H:i:s'),
      $p['cfg'] => json_encode($this->sess_cfg)
    ],[
      $p['id_user'] => $this->id,
      $p['sess_id'] => session_id()
    ]);
    $this->auth = false;
    $this->id = null;
    //$this->user_cfg = null;
    $this->unset_info();
    $this->unset_cfg();
    $this->unset_properties();
    $this->unset_permissions();
    //$this->sess_cfg = null;
    $this->delete_session();
    return $this;
  }

  /**
   * Gets the user's configuration, whole if $attr is empty, only the corresponding property otherwise
   *
   * @return array|string|false
   */
  public function get_cfg($attr = ''){
    if ( $this->check() ){
      $s = $this->get_full_session();
      return $s[$this->cfg['sess_user']];
      /*
      if ( !$this->user_cfg ){
        $this->user_cfg = $this->get_session('cfg');
      }
      if ( empty($attr) ){
        return $this->user_cfg;
      }
      else if ( isset($this->user_cfg[$attr]) ){
        return $this->user_cfg[$attr];
      }
      */
    }
    return false;
  }

  /**
   * Writes in the database the user's configuration (only if authenticated)
   *
   * @return \bbn\user\connection
   */
  public function save_cfg()
  {
    if ( $this->check() ){
      $this->db->update(
        $this->cfg['tables']['users'],
        [$this->cfg['arch']['users']['cfg'] => json_encode($this->user_cfg)],
        [$this->cfg['arch']['users']['id'] => $this->id]);
    }
    return $this;
  }

  /**
   * Sets one or more properties of either the session or the user's configuration
   *
   * @param array|string $attr Attribute's name or properties' indexed array of values
   * @param string|mixed $type Attribute's value or type of the config affected
   *
   * @return \bbn\user\connection
   */
  public function set_cfg($attr, $type='user')
  {
    $type = $type === 'sess' ? 'sess_cfg' : 'user_cfg';
    // Possible to pass 2 arguments property's name/value
    $args = func_get_args();
    if ( (count($args) === 2) && is_string($attr) ){
      $attr = [$args[0] => $args[1]];
      $type = 'user_cfg';
    }
    foreach ( $attr as $prop => $val ){
      // $this->auth can't be modified through this method
      if ( ($prop !== 'auth') && is_string($prop) ){
        $this->{$type}[$prop] = $val;
      }
    }
    return $this;
  }

  /**
   * Unsets a given property of either the session or the user's configuration as definde by $type
   *
   * @param string|array $attr the property's name
   * @param string $type the configuration's type (user or sess)
   *
   * @return \bbn\user\connection
   */
  public function unset_cfg($attr=false, $type='user'){
    $type = $type === 'sess' ? 'sess_cfg' : 'user_cfg';
    // Possible to pass either a single property's name or a group of properties in an array
    if ( is_string($attr) ){
      $attr = [$attr];
      $type = 'user_cfg';
    }
    foreach ( $attr as $prop ){
      if ( ($prop !== 'auth') && isset($this->{$type}[$prop]) ){
        unset($this->{$type}[$prop]);
      }
    }
    return $this;
  }

  /**
   * Sets the given properties to session
   *
   * @param string|array $attr
   *
   * @return \bbn\user\connection
   */
  public function set_properties($attr){
    $args = func_get_args();
    if ( (count($args) === 2) && is_string($attr) ){
      $attr = [$args[0] => $args[1]];
    }
    $this->set_session($attr, 'properties');
    return $this;
  }

  /**
   * Unsets the given properties from session
   * If you give an empty array it resets 'properties'
   *
   * @param string|array $attr
   *
   * @return \bbn\user\connection
   */
  public function unset_properties($attr=false){
    if ( empty($attr) ){
      $this->set_session(['properties' => []]);
    }
    else {
      $this->unset_session($attr, 'properties');
    }
    return $this;
  }

  /**
   * Gets properties from session
   *
   * @return array
   */
  public function get_properties(){
    return $this->get_session('properties');
  }

  /**
   * Sets the given info to session
   *
   * @param string|array $attr
   *
   * @return \bbn\user\connection
   */
  public function set_info($attr){
    $args = func_get_args();
    if ( (count($args) === 2) && is_string($attr) ){
      $attr = [$args[0] => $args[1]];
    }
    $this->set_session($attr, 'info');
    return $this;
  }

  /**
   * Unsets the given info from session
   * If you give an empty array it resets 'info'
   *
   * @param string|array $attrs
   *
   * @return \bbn\user\connection
   */
  public function unset_info($attr=false){
    if ( empty($attr) ){
      $this->set_session(['info' => []]);
    }
    else {
      $this->unset_session($attr, 'info');
    }
    return $this;
  }

  /**
   * Gets info from session
   *
   * @return array
   */
  public function get_info(){
    return $this->get_session('info');
  }

  /**
   * Sets the given auth to session
   *
   * @param string|array $attr
   *
   * @return \bbn\user\connection
   */
  public function set_auth2($attr){
    $args = func_get_args();
    if ( (count($args) === 2) && is_string($attr) ){
      $attr = [$args[0] => $args[1]];
    }
    $this->set_session($attr, 'auth');
    return $this;
  }

  /**
   * Unsets the given auth from session
   * If you give an empty array it resets 'auth'
   *
   * @param string|array $attrs
   *
   * @return \bbn\user\connection
   */
  public function unset_auth($attr=false){
    if ( empty($attr) ){
      $this->set_session(['auth' => []]);
    }
    else {
      $this->unset_session($attr, 'auth');
    }
    return $this;
  }

  /**
   * Gets auth from session
   *
   * @return array
   */
  public function get_auth(){
    return $this->get_session('auth');
  }

  /**
   * Sets the given permissions to session
   *
   * @param string|array $attr
   *
   * @return \bbn\user\connection
   */
  public function set_permissions($attr){
    $args = func_get_args();
    if ( (count($args) === 2) && is_string($attr) ){
      $attr = [$args[0] => $args[1]];
    }
    $this->set_session($attr, 'permissions');
    return $this;
  }

  /**
   * Unsets the given permissions from session
   * If you give an empty array it resets 'permissions'
   *
   * @param string|array $attrs
   *
   * @return \bbn\user\connection
   */
  public function unset_permissions($attr=false){
    if ( empty($attr) ){
      $this->set_session(['permissions' => []]);
    }
    else {
      $this->unset_session($attr, 'permissions');
    }
    return $this;
  }

  /**
   * Gets permissions from session
   *
   * @return array
   */
  public function get_permissions2(){
    return $this->get_session('permissions');
  }



}

