<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
/**
 * A simple user Class based on config file authentication
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Authentication
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class simple extends \bbn\obj 
{
	/**
	 * @var string
	 */
	private static $cf='include/users.cfg';

	/**
	 * @var string
	 */
	private static $sp=':::';

	/**
	 * @var mixed
	 */
	private $pass;

	/**
	 * @var mixed
	 */
	public $login;

	/**
	 * @var mixed
	 */
	public $alert;

	/**
	 * @var mixed
	 */
	public $identified;

	/**
	 * @var mixed
	 */
	public $privilege;


	/**
	 * @return void 
	 */
	private static function get_users()
	{
		if ( file_exists(self::$cf) )	{
			$res = array();
			$users = explode("\n",file_get_contents(self::$cf));
			foreach ( $users as $i => $user ){
				$credentials = explode(self::$sp,$user);
				$res[$i] = array(
					'login' => $credentials[0],
					'priv' => $credentials[1],
					'pass' => $credentials[2],
					'sess' => ''
				);
				if ( isset($credentials[3]) ){
					$res[$i]['sess'] = $credentials[3];
				}
			}
			return $res;
		}
		return false;
	}

	/**
	 * @return void 
	 */
	private static function set_users($users)
	{
		if ( file_exists(self::$cf) && is_array($users) ){
			$res = array();
			foreach ( $users as $i => $user ){
				$res[$i] = 
					$users[$i]['login'].self::$sp.
					$users[$i]['priv'].self::$sp.
					$users[$i]['pass'].self::$sp.
					$users[$i]['sess'];
			}
			if ( file_put_contents(self::$cf,implode("\n",$res)) ){
				return 1;
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function __construct($cfg=array())
	{
		self::init();
		if ( is_array($cfg) && isset($cfg['login']) ){
			if ( !$this->_identify($cfg) ){
				$this->error = defined('BBN_IMPOSSIBLE_TO_IDENTIFY_USER') ? BBN_IMPOSSIBLE_TO_IDENTIFY_USER : 'Impossible to identify user';
			}
		}
		if ( !$this->error && !$this->identified ){
			$this->error = defined('BBN_USER_DOESNT_EXIST') ? BBN_USER_DOESNT_EXIST : 'User doesn\'t exist';
			$this->login = $cfg['login'];
			if ( isset($cfg['pass']) ){
				$this->pass = sha1($cfg['pass']);
			}
		}
		else if ( !isset($cfg) ){
			$users = self::get_users();
			foreach ( $users as $user ){
				if ( $user['sess'] == session_id() ){
					$this->identified = 1;
					$this->login = $user['login'];
					break;
				}
			}
			if ( !$this->identified ){
				$this->error = defined('BBN_IMPOSSIBLE_TO_IDENTIFY_USER') ? BBN_IMPOSSIBLE_TO_IDENTIFY_USER : 'Impossible to identify user';
			}
		}
		if ( !$this->identified ){
			$this->error = defined('BBN_LOGIN_NOT_FOUND') ? BBN_LOGIN_NOT_FOUND : 'Login not found';
		}
	}

	/**
	 * @return void 
	 */
	private function _identify($cfg)
	{
		if ( is_array($cfg) && isset($cfg['login']) ){
			$users = self::get_users();
			$t = '';
			foreach ( $users as $key => $user ){
				if ( $user['login'] == $cfg['login'] ){
					$t .= $user['login'] .'/'. $cfg['login'];
					$this->login = $user['login'];
					$this->privilege = $user['priv'];
					if ( isset($cfg['pass']) && $user['pass'] == sha1($cfg['pass']) ){
						$users[$key]['sess'] = session_id();
						self::set_users($users);
						$this->identified = 1;
					}
					else{
						$this->error = defined('BBN_IMPOSSIBLE_TO_IDENTIFY_USER') ? BBN_IMPOSSIBLE_TO_IDENTIFY_USER : 'Impossible to identify user';
					}
					break;
				}
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function add($priv=1)
	{
		if ( $this->login && $this->pass && !$this->identified && strpos($this->login,self::$sp) === false && strpos($this->pass,self::$sp) === false )
		{
			$users = self::get_users();
			array_push($users,array(
				'login' => $this->login,
				'priv' => $priv,
				'pass' => $this->pass,
				'sess' => ''
			));
			if ( self::set_users($users) )
				return true;
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function delete()
	{
		if ( $this->login )
		{
			$users = self::get_users();
			foreach ( $users as $key => $user )
			{
				if ( $user['login'] == $this->login )
				{
					unset($users[$key]);
					if ( self::set_users($users) )
						return true;
					break;
				}
			}
		}
	}

	/**
	 * @return void 
	 */
	public function logout()
	{
		if ( defined('BBN_SESS_NAME') && is_array($_SESSION[BBN_SESS_NAME]['session']) )
		{
			$users = self::get_users();
			foreach ( $users as $key => $user )
			{
				if ( $user['login'] == $this->login )
				{
					$users[$key]['sess'] = '';
					self::set_users($users);
				}
				$this->identified = $_SESSION[BBN_SESS_NAME]['session'] = false;
				return true;
			}
		}
	}

	/**
	 * @return void 
	 */
	public function change_password($old, $new)
	{
		if ( $this->identified )
			$cfg = array();
	}

	/**
	 * @return void 
	 */
	public function set_password($pass)
	{
		if ( $this->identified )
		{
			$users = self::get_users();
			foreach ( $users as $key => $user )
			{
				if ( $user['login'] == $this->login )
				{
					$users[$key]['pass'] = sha1($this->pass);
					if ( self::set_users($users) )
						return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function set_privilege($level)
	{
		if ( $this->login )
		{
			$users = self::get_users();
			foreach ( $users as $key => $user )
			{
				if ( $user['login'] == $this->login )
				{
					$users[$key]['priv'] = $level;
					if ( self::set_users($users) )
						return true;
				}
			}
		}
		return false;
	}

}
?>