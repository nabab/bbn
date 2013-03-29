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
 * @todo Implement APC Cache for session requests' results?
 */
if ( !defined('BBN_FINGERPRINT') ) {
	define('BBN_FINGERPRINT', 'define_me');
}
if ( !defined('BBN_SESS_NAME') ) {
	define('BBN_SESS_NAME', 'define_me');
}
class connection
{

	private static
          /** @var string */
          $fingerprint = BBN_FINGERPRINT,
          $error = false;

	protected static
          /** @var array */
          $errors = [
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
          /** @var array */
          $_defaults = [
              'tables' => [
                  'users' => 'bbn_users',
                  'sessions' => 'bbn_users_sessions',
                  'hotlinks' => 'bbn_users_hotlinks',
                  'groups' => 'bbn_users_groups',
                  'usergroups' => 'bbn_users_usergroups'
              ],
              'arch' => [
                  'users' => [
                      'id' => 'id',
                      'email' => 'email',
                      'login' => 'email',
                      'pass' => 'pass',
                      'config' => 'config',
                      'status' => 'status'
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
                  'hotlinks' => [
                      'id' => 'id',
                      'id_user' => 'id_user',
                      'magic_string' => 'magic_string',
                      'expire' => 'expire'
                  ],
                  'groups' => [
                      'id' => 'id',
                      'group' => 'group',
                      'config' => 'config'
                  ],
                  'usergroups' => [
                      'id_group' => 'id_group',
                      'id_user' => 'id_user',
                  ]
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
          $user_agent,
          /** @var string */
          $ip_address,
          /** @var bool */
          $auth = false,
          /** @var string */
          $sql,
          /** @var \bbn\db\connection */
          $db,
          /** @var int */
          $id,
          /** @var mixed */
          $alert,
          /** @var array */
          $cfg = [],
          /** @var array */
          $sess_config,
          /** @var array */
          $user_config;
          

	public
		/** @var mixed */
		$prev_time;


	/**
	 * @return string
	 */
  private static function _make_fingerprint()
  {
    return \bbn\str\text::genpwd(32, 16);
  }
  
	/**
	 * @return string
	 */
  protected static function set_error($err){
    self::$error = $err;
    return self::get_error($err);
  }
  
	/**
   * @param int $id Error Code
	 * @return string 
	 */
  protected static function get_error($id){
    if ( isset(self::$errors[$id]) ){
      return self::$errors[$id];
    }
  }
  
	/**
	 * @return void 
	 */
  protected static function create_tables($cfg, \bbn\db\connection $db) {
    $cfg = \bbn\tools\merge_arrays($cfg, self::$_defaults);
    // @todo!!!
    $sql = "
      CREATE TABLE IF NOT EXISTS {$cfg['tables']['users']} (
        {$cfg['users']['id']} int(10) unsigned NOT NULL AUTO_INCREMENT,
        {$cfg['users']['email']} varchar(100) NOT NULL,
        {$cfg['users']['login']} varchar(35) NOT NULL,
        {$cfg['users']['pass']} varchar(64) NOT NULL,
        {$cfg['users']['config']} text NOT NULL,
        PRIMARY KEY ({$cfg['users']['id']}),
        UNIQUE KEY {$cfg['users']['email']} ({$cfg['users']['email']})
      ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$cfg['tables']['groups']} (
        {$cfg['groups']['id']} int(10) unsigned NOT NULL AUTO_INCREMENT,
        {$cfg['groups']['group']} varchar(100) NOT NULL,
        {$cfg['groups']['config']} text NOT NULL,
        PRIMARY KEY ({$cfg['groups']['id']})
      ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS apst_users_hotlinks (
        {$cfg['groups']['id']} int(10) unsigned NOT NULL AUTO_INCREMENT,
        magic_string varchar(64) NOT NULL,
        id_user int(10) unsigned NOT NULL,
        expire datetime NOT NULL,
        PRIMARY KEY (id),
        KEY id_user (id_user)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS apst_users_mdp (
        id_user int(10) unsigned NOT NULL,
        mdp varchar(128) NOT NULL,
        added datetime NOT NULL,
        KEY id_user (id_user)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS apst_users_sessions (
        id_user int(10) unsigned NOT NULL,
        sess_id varchar(128) NOT NULL,
        ip_address varchar(15),
        user_agent varchar(255),
        auth int(1) unsigned NOT NULL,
        opened int(1) unsigned NOT NULL,
        last_activity datetime NOT NULL,
        config text NOT NULL,
        PRIMARY KEY (id_user,sess_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS apst_users_usergroups (
        id_groupe int(10) unsigned NOT NULL,
        id_utilisateur int(10) unsigned NOT NULL,
        actif tinyint(1) unsigned NOT NULL DEFAULT '1',
        PRIMARY KEY (id_groupe,id_utilisateur),
        KEY id_groupe (id_groupe),
        KEY id_utilisateur (id_utilisateur)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;


      ALTER TABLE `apst_users_hotlinks`
        ADD CONSTRAINT apst_users_hotlinks_ibfk_1 FOREIGN KEY (id_user) REFERENCES apst_users (id) ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE `apst_users_mdp`
        ADD CONSTRAINT apst_users_mdp_ibfk_1 FOREIGN KEY (id_user) REFERENCES apst_users (id) ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE `apst_users_sessions`
        ADD CONSTRAINT apst_users_sessions_ibfk_1 FOREIGN KEY (id_user) REFERENCES apst_users (id) ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE `apst_users_usergroups`
        ADD CONSTRAINT apst_users_usergroups_ibfk_1 FOREIGN KEY (id_groupe) REFERENCES apst_users_groups (id) ON DELETE CASCADE ON UPDATE NO ACTION,
        ADD CONSTRAINT apst_users_usergroups_ibfk_10 FOREIGN KEY (id_utilisateur) REFERENCES apst_users (id) ON DELETE CASCADE ON UPDATE NO ACTION;";
      $db->raw_query($sql);
   }


  
  /**
	 * @return \bbn\user\connection 
	 */
	public function __construct(\bbn\db\connection $db, array $cfg, $credentials='')
	{
		$this->db = $db;
		
    $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $this->ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    $cfg = \bbn\tools::merge_arrays($cfg, self::$_defaults);
    
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

      $fingerprint = self::_make_fingerprint();
      
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
	 * @return string|false 
	 */
  
  /*
   * return \bbn\user\connection
   */
  private function _user_info(array $d=null){
    if ( $this->id ){
      if ( is_null($d) ){
        $d = $this->db->rselect(
              $this->cfg['tables']['users'],
              $this->fields,
              \bbn\tools::merge_arrays(
                    $this->cfg['conditions'],
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
        $this->user_config = json_decode($d['config']);
      }
    }
    return $this;
  }

  /*
   * return \bbn\user\connection
   */
  private function _sess_info(array $d=null){
    if ( $this->id ){
      if ( is_null($d) ){
        $d = $this->db->rselect(
            $this->cfg['tables']['sessions'],
            [],
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
    $pass_given = \bbn\str\text::escape_squote($pass_given);
    if ( $this->_crypt($pass_given) ===  $pass_stored ) {
      return 1;
    }
    return false;
  }
  
  private function _crypt($st){
    if ( !function_exists($this->cfg['encryption']) ){
      die("You need the PHP function {$this->cfg['encryption']} to have the user connection class working");
    }
    return eval("return {$this->cfg['encryption']}('$pass_given');");
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
      // Query starts with sql defined in __construct
      if ( $d = $this->db->rselect(
              $this->cfg['tables']['users'],
              $this->fields,
              \bbn\tools::merge_arrays(
                    $this->cfg['conditions'],
                    [$arch['users']['login'] => $credentials['user']])
              ) ){

        $this->id = $d['id'];
        $this->_user_info($d);
        
       // Canceling authentication if num_attempts > max_attempts
        if ( !$this->check_attempts() ){
          return self::set_error(4);
        }
        
        if ( $this->_check_password($credentials['pass'], $d['pass']) ){
          $this->auth = 1;
          $this->_login();
        }
        else{
          $this->record_attempt();
          return self::set_error(6);
        }
      }
      else{
        return self::set_error(6);
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
  
  protected function find_sessions($id_user=null)
  {
    return $this->db->get_rows("
      SELECT *
      FROM {$this->db['cfg']['tables']['sessions']}
      WHERE {$this->db['cfg']['arch']['sessions']['id_user']} = ?
        AND {$this->db['cfg']['arch']['sessions']['last_activity']} > DATE_SUB(NOW, INTERVAL {$this->db['cfg']['sess_length']} MINUTES)
      ", $id_user);
  }

  /*
   * @return bool
   */
  protected function check_attempts()
  {
    if ( !isset($this->user_config) ){
      return false;
    }
    if ( isset($this->user_cfg->num_attempts) && $this->user_cfg->num_attempts > $this->cfg['max_attempts'] ){
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

	/**
	 * @return void 
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
		if ( $this->auth )
		{
      $stored_pass = $this->db->get_val(
              $this->cfg['tables']['users'],
              $this->cfg['fields']['pass'],
              $this->cfg['fields']['id'],
              $this->id);
      if ( $this->_check_password($old_pass, $stored_pass) ){
        return $this->db->update(
                $this->cfg['tables']['users'],
                [$this->cfg['fields']['pass'] => $this->_crypt($new_pass)],
                [$this->cfg['fields']['id'] => $this->id]);
      }
		}
		return false;
	}

}
?>