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
 */
class connection extends \bbn\obj 
{
	use \bbn\util\info;

	/**
	 * @var mixed
	 */
	private static $fingerprint=BBN_FINGERPRINT;

	/**
	 * @var bool
	 */
	private $auth=false;

	/**
	 * @var mixed
	 */
	protected static $db;

	/**
	 * @var array
	 */
	protected static $info=array();

	/**
	 * @var bool
	 */
	protected static $cli=false;

	/**
	 * @var array
	 */
	protected static $cfg=array(
		'fields' => array(
			'id' => 'id',
			'user' => 'email',
			'pass' => 'pass',
			'sess_id' => 'sess_id',
			'log_tries' => 'log_tries',
			'last_attempt' => 'last_attempt',
			'fingerprint' => 'fingerprint',
			'reset_link' => 'reset_link',
			'ip' => 'ip',
			'last_connection' => 'last_connection'
		),
		'encryption' => 'sha1',
		'table' => 'users',
		'condition' => "acces > 0",
		'additional_fields' => array(),
		'num_attempts' => 3,
		'session' => 'user'
	);

	/**
	 * @var mixed
	 */
	public $alert;

	/**
	 * @var mixed
	 */
	public $id;

	/**
	 * @var mixed
	 */
	public $prev_time;


	/**
	 * @return void 
	 */
	public static function set_config($cfg)
	{
		foreach ( $cfg as $i => $v )
		{
			if ( isset(self::$cfg[$i]) )
			{
				if ( $i === 'fields' && is_array($v) )
				{
					foreach ( $v as $k => $w )
					{
						if ( isset(self::$cfg[$i][$k]) )
							self::$cfg[$i][$k] = $w;
					}
				}
				else
					self::$cfg[$i] = $v;
			}
		}
	}

	/**
	 * @return void 
	 */
	protected static function make_fingerprint()
	{
		return sha1($_SERVER['HTTP_USER_AGENT'].self::$fingerprint);
	}

	/**
	 * @return void 
	 */
	public function __construct($credentials=array())
	{
		if ( !isset(self::$db) )
			self::init();
		if ( is_array($credentials) && isset($credentials['user'],$credentials['pass']) )
			$this->_identify($credentials);
		else if ( $this->check_session() )
			$this->auth = 1;
	}

	/**
	 * @return void 
	 */
	private function _identify($credentials)
	{
		$res = 0;
		if ( isset($credentials['user'],$credentials['pass']) )
		{
			$fields =& self::$cfg['fields'];
			$query = "SELECT ";
			foreach ( $fields as $f )
				$query .= $f.', ';
			foreach ( self::$cfg['additional_fields'] as $f )
				$query .= $f.', ';
			$query = substr($query,0,-2)." FROM ".self::$cfg['table'].
				" WHERE ".$fields['user']." LIKE '%s' ".
				( !empty(self::$cfg['condition']) ? " AND ( ".self::$cfg['condition']." ) " : "" ).
				" LIMIT 1 ";
			$r = self::$db->query($query,$credentials['user']);
			if ( $r->count() > 0 )
			{
				$d = $r->get_row();
				$an_hour_ago = time() - 3600;
				if ( $d[$fields['log_tries']] > 3 && $d[$fields['last_attempt']] > $an_hour_ago )
					$res = 4;
				else if ( $d[$fields['pass']] === eval('return '.self::$cfg['encryption'].'("'.$credentials['pass'].'");') )
				{
					$this->auth = 1;
					/* If the IP and last connection fields exist it updates the table */
					if ( $fields['last_connection'] && $fields['ip'] )
						self::$db->query("
							UPDATE ".self::$cfg['table']."
							SET ".$fields['last_connection']." = NOW(),
							".$fields['ip']." = %s
							WHERE ".$fields['id']." = %u",
							$_SERVER['REMOTE_ADDR'],
							$d[$fields['id']]);
				}
				else
				{
					$log_tries = $d['bbn_log_tries'] + 1;
					self::$db->query("
						UPDATE ".self::$cfg['table']."
						SET ".$fields['log_tries']." = %u,
						".$fields['last_attempt']." = NOW(),
						".$fields['ip']." = %s
						WHERE ".$fields['id']." = %u",
						$log_tries,
						$_SERVER['REMOTE_ADDR'],
						$d[$fields['id']]);
				}
				if ( $this->auth )
				{
					$this->id = $d[$fields['id']];
					$_SESSION[self::$cfg['session']]['appuiUser'] = array(
						'id' => $this->id,
						'info' => array()
					);
					foreach ( self::$cfg['additional_fields'] as $f )
						$_SESSION[self::$cfg['session']]['appuiUser']['info'][$f] = $d[$f];
					$addon1 = $fields['sess_id'] ? " ".$fields['sess_id']." = '".session_id()."'," : '';
					$addon2 = $fields['ip'] ? " ".$fields['ip']." = '".$_SERVER['REMOTE_ADDR']."'," : '';
					self::$db->query("
						UPDATE ".self::$cfg['table']."
						SET ".$fields['last_attempt']." = NOW(),
						$addon1
						$addon2
						".$fields['fingerprint']." = '%s'
						WHERE ".$fields['id']." = %u",
						self::make_fingerprint(),
						$d['id']);
					/*
					\bbn\file\dir::delete($bbn_data_path.'users/'.$_SESSION['id_user']['id'].'/tmp',0);
					include_once($bbn_core_path.'config/register.php');
					*/
					$res = 1;
				}
			}
			if ( $res != 1 && $res != 4 )
				$res = 6;
		}
		/*
		$res
		0 = login failed
		1 = login ok
		2 = password sent
		3 = no email such as
		4 = too many attempts
		5 = impossible to create the user
		6 = wrong user and/or password
		7 = different passwords
		8 = less than 5 mn between emailing password
		9 = user already exists
		10 = problem during user creation
		*/
		switch ( $res )
		{
			case 0:
				$this->error = defined('BBN_LOGIN_FAILED0') ? BBN_LOGIN_FAILED0 : 'login failed';
				break;
			case 2:
				$this->error = defined('BBN_LOGIN_FAILED2') ? BBN_LOGIN_FAILED2 : 'password sent';
				break;
			case 3:
				$this->error = defined('BBN_LOGIN_FAILED3') ? BBN_LOGIN_FAILED3 : 'no email such as';
				break;
			case 4:
				$this->error = defined('BBN_LOGIN_FAILED4') ? BBN_LOGIN_FAILED4 : 'too many attempts';
				break;
			case 5:
				$this->error = defined('BBN_LOGIN_FAILED5') ? BBN_LOGIN_FAILED5 : 'impossible to create the user';
				break;
			case 6:
				$this->error = defined('BBN_LOGIN_FAILED6') ? BBN_LOGIN_FAILED6 : 'wrong user and/or password';
				break;
			case 7:
				$this->error = defined('BBN_LOGIN_FAILED7') ? BBN_LOGIN_FAILED7 : 'different passwords';
				break;
			case 8:
				$this->error = defined('BBN_LOGIN_FAILED8') ? BBN_LOGIN_FAILED8 : 'less than 5 mn between emailing password';
				break;
			case 9:
				$this->error = defined('BBN_LOGIN_FAILED9') ? BBN_LOGIN_FAILED9 : 'user already exists';
				break;
			case 10:
				$this->error = defined('BBN_LOGIN_FAILED10') ? BBN_LOGIN_FAILED0 : 'problem during user creation';
				break;
		}
	}

	/**
	 * @return array | false 
	 */
	private function _refresh_info()
	{
		return ( $this->auth && $this->id ) ?
			$this->info = 
				$this->db
					->query("
						SELECT *
						FROM id_users
						WHERE id = %u",
						$this->id)
					->get_row()
			: false;
	}

	/**
	 * @return bool 
	 */
	public function check_session()
	{
		$this->auth = false;
		if ( isset($_SESSION[self::$cfg['session']]['appuiUser']['id']) )
		{
			$f =& self::$cfg['fields'];
			/* Adding a bbn_change field would allow us to update auytomatically the user's info if something has been changed */
			$addon1 = $f['sess_id'] ? " AND ".$f['sess_id']." LIKE '".session_id()."'" : '';
			if ( $id = self::$db->query("
				SELECT ".$f['id']."
				FROM ".self::$cfg['table']."
				WHERE ".$f['id']." = %u
				$addon1
				AND ".$f['fingerprint']." LIKE '%s'",
				$_SESSION[self::$cfg['session']]['appuiUser']['id'],
				self::make_fingerprint())->fetchColumn() )
			{
				$this->id = $_SESSION[self::$cfg['session']]['appuiUser']['id'];
				session_regenerate_id();
				if ( $f['sess_id'] )
					self::$db->query("
						UPDATE ".self::$cfg['table']."
						SET ".$f['sess_id']." = '%s'
						WHERE ".$f['id']." = %u",
						session_id(),
						$this->id);
				$this->auth = 1;
				return 1;
			}
		}
		return false;
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
		$this->auth = false;
		if ( isset($_SESSION[self::$cfg['session']]) ){
			$_SESSION[self::$cfg['session']]['appuiUser'] = false;
		}
		session_destroy();
	}

	/**
	 * @return void 
	 */
	public function set_password($pass)
	{
		if ( $this->auth )
		{
			$users = self::get_users();
			foreach ( $users as $key => $user )
			{
				if ( $user['user'] == $this->user )
				{
					$users[$key]['pass'] = sha1($this->pass);
					if ( self::set_users($users) )
						return true;
				}
			}
		}
		return false;
	}

}
?>