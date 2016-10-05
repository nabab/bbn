<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
use bbn;
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
if ( !defined('BBN_DATA_PATH') ){
  die("BBN_DATA_PATH must be defined");
}
class connection
{

	private static
          /** @var connection */
          $current;

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
              'users' => [
                'id' => 'id',
                'id_group' => 'id_group',
                'email' => 'email',
                'login' => 'email',
                'admin' => 'admin',
                'cfg' => 'cfg',
                'status' => 'active'
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
             * Sets if the hotlinks features should be in used
             * @var bool
             */
            'hotlinks' => false
          ];

  /**
   * @param connection $usr
   */
  protected static function _init(connection $usr){
    if ( $id = $usr->get_id() ){
      self::$current =& $usr;
      if ( !defined('BBN_USER_PATH') ){
        define('BBN_USER_PATH', BBN_DATA_PATH.'users/'.$id.'/');
      }
    }
  }

  /**
   * @return connection
   */
  public static function get_user(){
    return self::$current;
  }

  private $just_login = false;

	protected
          /** @var string */
          $error = null,
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
          /** @var int */
          $id_group,
          /** @var mixed */
          $alert,
          /** @var array */
          $cfg = [],
          /** @var array */
          $sess_cfg,
          /** @var array */
          $user_cfg,
          /** @var array */
          $fields,
          /** @var bool */
          $has_preference = false;


	public
          /** @var db */
          $db,
          /** @var mixed */
          $prev_time;


	/**
	 * @return string
	 */
  public static function make_fingerprint()
  {
    return bbn\str::genpwd(32, 16);
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

  /**
   * Checks if a magic string complies with a hash
   *
   * @return bool
   */
  protected static function is_magic_string($key, $hash)
  {
    return ( hash('sha256', $key) === $hash );
  }


	/**
   * Reurns the last known error and false if there was no error
	 * @return mixed
	 */
  public function get_error(){
    return ( !is_null($this->error) && isset($this->cfg['errors'][$this->error]) ) ?
              $this->cfg['errors'][$this->error] : false;
  }

  /**
   * Give the current user's configuration
   * @param string $attr
   * @return mixed
   */
  public function get_cfg($attr = ''){
    if ( $this->check() ){
      if ( !$this->user_cfg ){
        $this->user_cfg = $this->session->get('cfg');
      }
      if ( empty($attr) ){
        return $this->user_cfg;
      }
      if ( isset($this->user_cfg[$attr]) ){
        return $this->user_cfg[$attr];
      }
      return false;
    }
  }

  /**
   * Returns the current configuration of this very class
   * @return array
   */
  public function get_class_cfg(){
    if ( $this->check() ){
      return $this->cfg;
    }
  }

  /**
   * connection constructor.
   * @param db $db
   * @param session $session
   * @param array $cfg
   * @param array $params
   */
  public function __construct(bbn\db $db, array $cfg = [], array $params = []){
		$this->db = $db;
    $this->session = session::get_current();
    if ( !$this->session ){
      $this->session = new session();
    }

    $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $this->ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    $this->cfg = bbn\x::merge_arrays(self::$_defaults, $cfg);

    $this->preferences = preferences::get_preferences();

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
    $this->fields = bbn\x::merge_arrays($this->cfg['arch']['users'], $this->cfg['additional_fields']);
    
    
    $f =& $this->cfg['fields'];
    // The user logs in
    if ( isset($params[$f['user']], $params[$f['pass']], $params[$f['salt']]) ){
      if ( !$this->session->has('salt') ){
        die("You have no salt in your session, please reload");
      }
      $this->_identify($params);
      if ( $this->get_id() ){
        if ( !defined('BBN_USER_PATH') ){
          define('BBN_USER_PATH', BBN_DATA_PATH.'users/'.$this->get_id().'/');
        }
        bbn\file\dir::create_path(BBN_USER_PATH.'tmp');
        bbn\file\dir::delete(BBN_USER_PATH.'tmp', false);
      }
    }
    // The user is not known yet
    else if (
      isset($params[$f['key']], $params[$f['id']], $params[$f['pass1']], $params[$f['pass2']], $params[$f['action']]) &&
      ($params[$f['action']] === 'init_password') &&
      ($params[$f['pass1']] === $params[$f['pass2']]) &&
      $this->check_magic_string($params[$f['id']], $params[$f['key']])
    ){
      $this->expire_hotlink($params[$f['id']]);
      $this->force_password($params[$f['pass2']]);
      $this->session->set([]);
      header('Location: .');
      die();
    }
    else if ( $this->check_session() ){
      if ( !defined('BBN_USER_PATH') ){
        define('BBN_USER_PATH', BBN_DATA_PATH.'users/'.$this->get_id().'/');
      }
    }

    self::_init($this);

    return $this;
	}

	/**
	 * @return connection
	 */
	private function _init_session(){
    if ( $this->check() ){
      $fingerprint = self::make_fingerprint();

      if ( !$this->session->has('user') ){
        $this->session->set([
          'id' => $this->id,
          'id_group' => $this->id_group,
          'fingerprint' => $fingerprint,
          'tokens' => []
        ], 'user');
      }

      $this->sess_cfg = [
        'fingerprint' => $this->get_print($fingerprint),
        'last_renew' => time()
      ];

      $this->save_session();

      $this->auth = 1;

    }
    return $this;
  }

	/**
	 * @return connection
	 */
	private function _login(){
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
   * @return connection
   */
  private function _user_info(array $d=null){
    if ( $this->id ){
      if ( is_null($d) ){
        $d = $this->db->rselect(
              $this->cfg['tables']['users'],
              $this->fields,
              bbn\x::merge_arrays(
                    $this->cfg['conditions'],
                    [$this->cfg['arch']['users']['status'] => 1],
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
        $this->session->set($r, 'info');
        $this->user_cfg = empty($d['cfg']) ?
                        ['log_tries' => 0] : json_decode($d['cfg'], true);
        $this->set_session('id', $this->id);
        $this->set_session('cfg', $this->user_cfg);
        // Group
        $this->id_group = $d['id_group'];
        $this->set_session('id_group', $this->id_group);
      }
    }
    return $this;
  }

  public function get_tables(){
    if ( !empty($this->cfg) ){
      return $this->cfg['tables'];
    }
    return false;
  }

  public function get_fields($table=''){
    if ( !empty($this->cfg) ){
      return empty($table) ? $this->cfg['arch'] : ( isset($this->cfg['arch'][$table]) ? $this->cfg['arch'][$table] : false );
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
   * Sets the current user's permissions (only if admin)
   *
   * @return array
   */
  public function set_admin_permissions($perms){
    if ( $this->is_admin() ){
      $x = function(array $ar, $res = [], $prefix = '') use (&$x){
        foreach ( $ar as $a ){
          $pref = isset($a['prefix']) ? $prefix.$a['prefix'] : $prefix;
          if ( !empty($a['link']) ){
            $res[$pref.$a['link']] = 1;
          }
          if ( !empty($a['items']) ){
            $res = $x($a['items'], $res, $pref);
          }
        }
        return $res;
      };
      $this->permissions = $x($perms);
      //die(bbn\x::hdump($this->permissions ));
      if ( !isset($this->permissions['admin']) ){
        $this->permissions['admin'] = 1;
      }
      $this->session->set($this->permissions, 'user', 'permissions');
      return 1;
    }
    return false;
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
    return ( $check_admin && $this->is_admin() );
  }

  /**
   * Checks if the user has the given permission and dies otherwise
   *
   * @param string $name The name of the permission
   *
   * @return void
   */
  public function check_permission($name, $check_admin=1){
    if ( isset($this->permissions[$name]) && $this->permissions[$name] ){
      return 1;
    }
    if ( !( $check_admin && $this->is_admin() ) ){
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
        if ( ($key !== 'id') && ($key !== 'cfg') && ($key !== 'auth') && ($key !== 'pass') && in_array($key, $this->fields) ){
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
   * return connection
   */
  private function _sess_info($d=null){
    if ( is_int($d) ){
      $id = $d;
    }
    else if ( $this->id ){
      $id = $this->id;
    }
    if ( !is_array($d) && isset($id) ){
      $d = $this->db->rselect(
          $this->cfg['tables']['sessions'],
          $this->cfg['arch']['sessions'],
          [
              $this->cfg['arch']['sessions']['sess_id'] => $this->session->get_id(),
              $this->cfg['arch']['sessions']['id_user'] => $id
          ]);
    }
    if ( is_array($d) ){
      $this->sess_cfg = json_decode($d['cfg'], 1);
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

  public function get_password($st){
    return $this->_crypt($st);
  }

  public function is_just_login(){
    return $this->just_login;
  }

  /**
	 * @return mixed
	 */
	private function _identify($params)
	{
    if ( $this->check_session() ){
      $this->close_session();
    }
    $f =& $this->cfg['fields'];
		if ( isset($params[$f['user']],$params[$f['pass']]) ) {
      // Table structure
			$arch =& $this->cfg['arch'];

      $this->just_login = 1;
      // Database Query
      if ( $d = $this->db->rselect(
              $this->cfg['tables']['users'],
              $this->fields,
              bbn\x::merge_arrays(
                    $this->cfg['conditions'],
                    [$arch['users']['status'] => 1],
                    [$arch['users']['login'] => $params[$f['user']]])
              ) ){

        $this->set_id($d['id']);
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
        if ( $this->_check_password($params[$f['pass']], $pass) ){
          $this->_login();
        }
        else{
          $this->record_attempt();
          $this->error = 6;
        }
        if ( !$this->error ){
          $this->auth = 1;
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
      return sha1($this->user_agent . /*$this->ip_address .*/ $fp);
    }
    return false;
	}

	/**
	 * @return connection
	 */
  public function set_session($attr){
    if ( $this->session->has('user') ){
      $args = func_get_args();
      if ( (count($args) === 2) && is_string($args[0]) ){
        $attr = [$args[0] => $args[1]];
      }
      foreach ( $attr as $key => $val ){
        if ( is_string($key) ){
          $this->session->set($val, 'user', $key);
        }
      }
    }
    return $this;
  }

	/**
	 * @return mixed
	 */
  public function get_session($attr){
    if ( $this->session->has('user') ){
      return $this->session->get('user', $attr);
    }
  }

	/**
	 * @return bool
	 */
  public function has_session($attr){
    return $this->session->has('user', $attr);
  }

  /**
	 * @return connection
	 */
  public function save_session() {
    $p =& $this->cfg['arch']['sessions'];
    $this->db->insert_update($this->cfg['tables']['sessions'], [
      $p['id_user'] => $this->id,
      $p['sess_id'] => $this->session->get_id(),
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
	 * @return connection
	 */
  public function close_session() {
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
        $p['sess_id'] => $this->session->get_id()
      ]);
    $this->auth = false;
    $this->id = null;
    $this->user_cfg = null;
    $this->sess_cfg = null;
    $this->session->set([], 'user');
    return $this;
  }

  /*
   * @return bool
   */
  public function check_attempts()
  {
    if ( !isset($this->user_cfg) ){
      return false;
    }
    if ( isset($this->user_cfg['num_attempts']) && $this->user_cfg['num_attempts'] > $this->cfg['max_attempts'] ){
      return false;
    }
    return true;
  }

  /*
   * return connection
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

  /*
   * return connection
   */
  public function set_cfg($attr, $type='user')
  {
    $prop = $type === 'sess' ? 'sess_cfg' : 'user_cfg';
    if ( isset($this->{$prop}) ){
      $args = func_get_args();
      if ( (count($args) === 2) && is_string($attr) ){
        $attr = [$args[0] => $args[1]];
        $prop = 'user_cfg';
      }
      foreach ( $attr as $key => $val ){
        //bbn\x::dump($key, $val);
        if ( is_string($key) ){
          $this->{$prop}[$key] = $val;
        }
      }
    }
    return $this;
  }

  /*
   * return connection
   */
  public function unset_cfg($attr, $type='user')
  {
    if ( isset($this->{$type.'_cfg'}) ){
      $args = func_get_args();
      if ( is_string($attr) ){
        $attr = [$attr];
      }
      foreach ( $attr as $val ){
        if ( isset($key) ){
          unset($this->{$type.'_cfg'}[$key]);
        }
      }
    }
    return $this;
  }

  /*
   * return connection
   */
  protected function record_attempt()
  {
    $this->user_cfg['num_attempts'] = isset($this->user_cfg['num_attempts']) ?
            $this->user_cfg['num_attempts']+1 : 1;
    $this->set_cfg(['num_attempts' => $this->user_cfg['num_attempts']], "user");
    return $this;
  }

  protected function set_id($id){
    $this->id = $id ?: $this->get_session('id');
    $this->auth = 1;
    if ( $this->preferences ){
      $this->preferences->set_user($this->id);
      /** @todo Redo this!!! Bad! */
      $this->preferences->set_group($this->get_session('id_group'));
    }
  }

  /**
	 * @return connection
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
       // bbn\x::hdump($this->sess_cfg, $this->has_session('fingerprint'), $this->get_print($this->get_session('fingerprint')), $this->sess_cfg['fingerprint']);
    //die(bbn\x::dump($this->id));
		if ( !$this->id ) {

      //die(var_dump($this->sess_cfg['fingerprint']));
      // The user ID must be in the session
      $id = $this->get_session('id');
			if ( $id ) {

        $this->_sess_info($id);

        if ( isset($this->sess_cfg['fingerprint']) && $this->has_session('fingerprint') &&
          ($this->get_print($this->get_session('fingerprint')) === $this->sess_cfg['fingerprint']) ){
          $this->set_id($id);
          $this->_user_info();
          $this->_login();
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

  public function get_group()
  {
    if ( $this->check() ) {
      return $this->id_group;
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
        $this->set_id($val['id_user']);
        $this->_user_info();
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
    return (isset($this->permissions["admin"]) && $this->permissions["admin"]) || $this->session->get('info', 'admin');
  }

  public function get_manager($mail = false){
    $mgr = new manager($this, $mail);
    return $mgr;
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
    $this->session->destroy();
    return $this;
  }

  /**
   * @return void
   */
  public function get_name(array $usr = null){
    if ( $this->auth ){
      if ( is_null($usr) ){
        return $this->session->get('info', 'login');
      }
      else if ( isset($usr['login']) ){
        return $usr['login'];
      }
    }
    return false;
  }

  /**
   * @return void
   */
  public function get_token($st)
  {
    if ( $this->auth && $this->session->has('user', 'tokens', $st) ){
      $this->session->transform(function(&$a) use($st){
        if ( isset($a['tokens']) ){
          $a['tokens'][$st] = bbn\str::genpwd();
        }
      }, 'user');
      return $this->session->get('user', 'tokens', $st);
    }
    return false;
  }

  /**
   * @return void
   */
  public function check_token($st, $token)
  {
    if ( $this->auth && $this->session->has('user', 'tokens', $st) ){
      return $this->session->get('user', 'tokens', $st) === $token;
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
                $this->cfg['arch']['passwords']['pass'] => $this->_crypt($pass),
                $this->cfg['arch']['passwords']['id_user'] => $this->id,
                $this->cfg['arch']['passwords']['added'] => date('Y-m-d H:i:s')]);
		}
		return false;
	}

}
