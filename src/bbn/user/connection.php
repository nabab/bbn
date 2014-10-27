<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
/**
 * A user authentication Class
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
if ( !defined('BBN_FINGERPRINT') ) {
	define('BBN_FINGERPRINT', 'define_me!!');
}
if ( !defined('BBN_SESS_NAME') ) {
	define('BBN_SESS_NAME', 'define_me!!!!');
}

class connection
{

	private static
          /** @var string */
          $fingerprint = BBN_FINGERPRINT;

	protected static
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
                'config' => 'config'
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
                'config' => 'config',
              ],
              'usergroups' => [
                'id_group' => 'id_group',
                'id_user' => 'id_user',
              ],
              'users' => [
                'id' => 'id',
                'email' => 'email',
                'login' => 'email',
                'config' => 'config',
                'status' => 'status'
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
            'sess_name' => BBN_SESS_NAME,
            /*
             * In the session array the index on which user info will be stored
             * i.e. the default storage will be $_SESSION[BBN_SESS_NAME]['user']
             */
            'sess_user' => 'user',
            /*
             * length in minutes of the session regeneration (can be doubled)
             * @var integer 
             */
            'sess_length' => 5,
            /*
             * Number of times a user can try to log in in the period retry_length
             * @var integer 
             */
            'max_attempts' => 5,
            /*
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
            'hotlinks' => false
          ];
  
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
          /** @var array */
          $cfg = [],
          /** @var array */
          $sess_config,
          /** @var array */
          $user_config,
          /** @var array */
          $fields;
          

	public
          /** @var \bbn\db\connection */
          $db,
          /** @var mixed */
          $prev_time;


	/**
	 * @return string
	 */
  public static function make_fingerprint()
  {
    return \bbn\str\text::genpwd(32, 16);
  }
  
	/**
   * Creates a magic string which will be used for hotlinks
   * The hash is stored in the database
   * The key is sent to the user
   * 
	 * @return array
	 */
  public static function make_magic_string()
  {
    $key = self::make_fingerprint();
    return [
      'key' => $key,
      'hash' => hash('sha256', $key)
    ];
  }
  
  protected static function is_magic_string($key, $hash)
  {
    return ( hash('sha256', $key) === $hash );
  }
  
  
	/**
	 * @return string 
	 */
  public function get_error(){
    return ( !is_null($this->error) && isset($this->cfg['errors'][$this->error]) ) ?
              $this->cfg['errors'][$this->error] : false;
  }
  
  public function get_config(){
    return $this->cfg;
  }
  
  /**
	 * @return \bbn\user\connection 
	 */
	public function __construct(\bbn\db\connection $db, array $cfg, $credentials='')
	{
		$this->db = $db;
		
    $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $this->ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    $this->cfg = \bbn\tools::merge_arrays(self::$_defaults, $cfg);
    // As we'll give the object the properties of these additional field they should not conflict with existing ones
    foreach ( $this->cfg['additional_fields'] as $f ){
      if ( property_exists($this, $f) ) {
        die("Wrong configuration: the column's name $f is illegal!");
      }
    }
    
    /*
     * The selection comprises the defined fields of the users table
     * Plus a bunch of user-defined additional fields in the same table
     */
    $this->fields = \bbn\tools::merge_arrays($this->cfg['arch']['users'], $this->cfg['additional_fields']);

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
    
    return $this;
	}
  
	/**
	 * @return \bbn\user\connection 
	 */
	private function _init_session()
  {
    if ( $this->check() ){
      if ( session_id() == '' ){
        session_start();
      }

      if ( !isset($_SESSION[$this->cfg['sess_name']]) ){
        $_SESSION[$this->cfg['sess_name']] = [];
      }

      $fingerprint = self::make_fingerprint();
      
      $_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']] = [
          'id' => $this->id,
          'fingerprint' => $fingerprint
      ];
      
      $this->sess_config = new \stdClass();
      $this->sess_config->fingerprint = $this->get_print($fingerprint);
      $this->sess_config->last_renew = time();
      
      $this->save_session();
      
      $this->auth = 1;
      
    }
    return $this;
  }
  
	/**
	 * @return \bbn\user\connection 
	 */
	private function _login()
  {
    if ( $this->check() ){
      $this->_init_session()->save_session();
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
    if ( $this->id ){
      if ( is_null($d) ){
        $d = $this->db->rselect(
              $this->cfg['tables']['users'],
              $this->fields,
              \bbn\tools::merge_arrays(
                    $this->cfg['conditions'],
                    [$this->cfg['arch']['users']['status'] => 1],
                    [$this->cfg['arch']['users']['id'] => $this->id]));
      }
      if ( is_array($d) ){
        $r = [];
        foreach ( $d as $key => $val ){
          if ( ($key !== 'id') && ($key !== 'config') && ($key !== 'auth') && ($key !== 'pass') ){
            $this->$key = $val;
            $r[$key] = $val;
          }
        }
        $this->set_session('info', $r);
        $this->user_config = empty($d['config']) ?
                        ['log_tries' => 0] : json_decode($d['config'], true);
        $this->set_session('config', $this->user_config);
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
            $this->cfg['arch']['groups']['config'],
            $this->cfg['arch']['groups']['id'],
            $gr) ){
            $this->permissions = array_merge(json_decode($p, 1), $this->permissions);
          }
        }
        $this->set_session('permissions', $this->permissions);
        $this->set_session('groups', $this->groups);
      }
    }
    return $this;
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
  public function has_permission($name){
    if ( isset($this->permissions[$name]) && $this->permissions[$name] ){
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
   * Changes the data in the user's table 
   * 
   * @param array $d The new data
   * 
   * @return bool
   */
  public function update_info(array $d)
  {
    if ( $this->check() ){
      $update = [];
      foreach ( $d as $key => $val ){
        if ( ($key !== 'id') && ($key !== 'config') && ($key !== 'auth') && ($key !== 'pass') && in_array($key, $this->fields) ){
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

  /*
   * return \bbn\user\connection
   */
  private function _sess_info(array $d=null){
    if ( $this->id ){
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
        $this->sess_config = json_decode($d['config']);
      }
    }
    return $this;
  }
  
  private function _check_password($pass_given, $pass_stored)
  {
    return ($this->_crypt($pass_given) ===  $pass_stored);
  }
  
  private function _crypt($st){
    if ( !function_exists($this->cfg['encryption']) ){
      die("You need the PHP function {$this->cfg['encryption']} to have the user connection class working");
    }
    return eval("return {$this->cfg['encryption']}('$st');");
  }

  /**
	 * @return mixed
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
              \bbn\tools::merge_arrays(
                    $this->cfg['conditions'],
                    [$arch['users']['status'] => 1],
                    [$arch['users']['login'] => $credentials['user']])
              ) ){

        $this->id = $d['id'];
        $this->_user_info($d);
        
       // Canceling authentication if num_attempts > max_attempts
        if ( !$this->check_attempts() ){
          $this->error = 4;
        }
        $pass = $this->db->select_one(
                $this->cfg['tables']['passwords'],
                $arch['passwords']['pass'],
                [$arch['passwords']['id_user'] => $this->id],
                [$arch['passwords']['added'] => 'DESC']);
        if ( $this->_check_password($credentials['pass'], $pass) ){
          $this->auth = 1;
          $this->_login();
        }
        else{
          $this->record_attempt();
          $this->error = 6;
        }
      }
      else{
        $this->error = 6;
      }
    }
    return $this->auth;
	}

  protected function get_print()
	{
    if ( ($fp = $this->get_session('fingerprint')) ){
      return sha1($this->user_agent . $this->ip_address . $fp);
    }
    return false;
	}
  
	/**
	 * @return \bbn\user\connection 
	 */
  protected function set_session($attr){
    if ( isset($_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']]) ){
      $s =& $_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']];
      $args = func_get_args();
      if ( (count($args) === 2) && is_string($args[0]) ){
        $attr = [$args[0] => $args[1]];
      }
      foreach ( $attr as $key => $val ){
        if ( is_string($key) ){
          $s[$key]  = $val;
        }
      }
    }
    return $this;
  }

	/**
	 * @return mixed
	 */
  protected function get_session($attr){
    if ( $this->has_session($attr) ){
      return $_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']][$attr];
    }
  }

	/**
	 * @return bool
	 */
  protected function has_session($attr){
    return ( 
            is_string($attr) && 
            isset($_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']][$attr])
           );
  }

  /**
	 * @return \bbn\user\connection 
	 */
  protected function save_session() {
    $p =& $this->cfg['arch']['sessions'];
    $this->db->insert_update($this->cfg['tables']['sessions'], [
      $p['id_user'] => $this->id,
      $p['sess_id'] => session_id(),
      $p['ip_address'] => $this->ip_address,
      $p['user_agent'] => $this->user_agent,
      $p['auth'] => $this->auth ? 1 : 0,
      $p['opened'] => 1,
      $p['last_activity'] => date('Y-m-d H:i:s'),
      $p['config'] => json_encode($this->sess_config)
    ]);
    return $this;
  }

  /**
	 * @return \bbn\user\connection 
	 */
  protected function close_session() {
    $p =& $this->cfg['arch']['sessions'];
    $this->db->update($this->cfg['tables']['sessions'], [
        $p['ip_address'] => $this->ip_address,
        $p['user_agent'] => $this->user_agent,
        $p['auth'] => $this->auth ? 1 : 0,
        $p['opened'] => 0,
        $p['last_activity'] => date('Y-m-d H:i:s'),
        $p['config'] => json_encode($this->sess_config)
      ],[
        $p['id_user'] => $this->id,
        $p['sess_id'] => session_id()
      ]);
    $this->auth = false;
    $this->id = null;
    $this->user_config = null;
    $this->sess_config = null;
		$_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']] = [];
    return $this;
  }
  
  /*
   * @return bool
   */
  protected function check_attempts()
  {
    if ( !isset($this->user_config) ){
      return false;
    }
    if ( isset($this->user_config->num_attempts) && $this->user_config->num_attempts > $this->cfg['max_attempts'] ){
      return false;
    }
    return true;
  }
  
  /*
   * return \bbn\user\connection
   */
  protected function save_config()
  {
    if ( $this->check() ){
      $this->db->update(
          $this->cfg['tables']['users'],
          [$this->cfg['arch']['users']['config'] => json_encode($this->user_config)],
          [$this->cfg['arch']['users']['id'] => $this->id]);
    }
    return $this;
  }
  
  /*
   * return \bbn\user\connection
   */
  protected function set_config($attr, $type='user')
  {
    if ( isset($this->{$type.'_cfg'}) ){
      $args = func_get_args();
      if ( (count($args) === 2) && is_string($attr) ){
        $attr = [$args[0] => $args[1]];
      }
      foreach ( $attr as $key => $val ){
        if ( is_string($key) ){
          $this->{$type.'_cfg'}->$key = $val;
        }
      }
    }
    return $this;
  }

  /*
   * return \bbn\user\connection
   */
  protected function unset_config($attr, $type='user')
  {
    if ( isset($this->{$type.'_cfg'}) ){
      $args = func_get_args();
      if ( is_string($attr) ){
        $attr = [$attr];
      }
      foreach ( $attr as $val ){
        if ( isset($key) ){
          unset($this->{$type.'_cfg'}->$key);
        }
      }
    }
    return $this;
  }
  
  /*
   * return \bbn\user\connection
   */
  protected function record_attempt()
  {
    $this->user_config['attempts'] = isset($this->user_config['attempts']) ?
            $this->user_config['attempts']+1 : 1;
    $this->set_config(['attempts'=>$this->user_config['attempts']], "user");
    return $this;
  }

  /**
	 * @return \bbn\user\connection
	 */
	protected function refresh_info()
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
		if ( !$this->id ) {
      
      // The user ID must be in the session
			if ( $this->has_session('id') ) {
        $this->id = $this->get_session('id');
        
        $this->_sess_info();
        
        if ( isset($this->sess_config->fingerprint) && $this->has_session('fingerprint') && $this->get_print($this->get_session('fingerprint')) === $this->sess_config->fingerprint ){
          $this->auth = 1;
          $this->_user_info()->save_session();
          
        }
			}
		}
		return $this->auth;
	}
  
  public function get_id()
  {
    if ( $this->check() ) {
      return $this->id;
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
        $this->_login();
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
                $this->cfg['arch']['passwords']['pass'] => $this->_crypt($pass),
                $this->cfg['arch']['passwords']['id_user'] => $this->id,
                $this->cfg['arch']['passwords']['added'] => date('Y-m-d H:i:s')]);
		}
		return false;
	}
}