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
if ( !defined('BBN_FINGERPRINT') ){
	define('BBN_FINGERPRINT', 'define_me');
}
if ( !defined('BBN_SESS_NAME') ){
	define('BBN_SESS_NAME', 'define_me');
}
class connection extends \bbn\obj 
{
	use \bbn\util\info;

	/**
	 * @var mixed
	 */
	private static $fingerprint = BBN_FINGERPRINT;

	protected static $_defaults=array(
		'fields' => array(
			'id' => 'id',
			'user' => 'email',
			'pass' => 'pass',
			'sess_id' => 'sess_id',
			'info_auth' => 'info_auth',
			'reset_link' => 'reset_link',
			'ip' => 'ip',
			'last_connection' => 'last_connection'
		),
		'encryption' => 'sha1',
		'table' => 'users',
		'condition' => array(),
		'additional_fields' => array(),
		'user_group' => false,
		'group' => false,
		'sess_name' => BBN_SESS_NAME,
		'sess_user' => 'user'
	);

	/**
	 * @var bool
	 */
	protected
		/** @var bool */
		$auth=false,
		/** @var string */
		$sql,
		/** @var \bbn\db\connection */
		$db,
		/** @var array */
		$cfg = array();

	public
		/** @var mixed */
		$alert,
		/** @var int */
		$id,
		/** @var mixed */
		$prev_time;


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
	public function __construct($cfg, \bbn\db\connection $db, $credentials='')
	{
		$this->db = $db;
		
		foreach ( self::$_defaults as $n => $c ){
			if ( is_array($c) ){
				$this->cfg[$n] = ( isset($cfg[$n]) && is_array($cfg[$n]) ) ? array_merge($c, $cfg[$n]) : $c;
			}
			else{
				$this->cfg[$n] = isset($cfg[$n]) ? $cfg[$n] : $c;
			}
		}
		$this->sql = $this->db->get_select(
            $this->cfg['table'],
            array_merge($this->cfg['fields'], $this->cfg['additional_fields'])
            ).
            PHP_EOL."WHERE 1 ";
    foreach ( $this->cfg['condition'] as $col => $cond ){
      $this->sql .= " AND ( `$col` = `$cond` ) ";
    }
		if ( is_array($credentials) && isset($credentials['user'], $credentials['pass'], $cfg['fields']) ){
			$this->_identify($credentials);
		}
		
		else if ( $this->check_session() ){
			$this->auth = 1;
		}
	}

	/**
	 * @return void 
	 */
	private function _identify($credentials)
	{
		$res = 0;
		if ( isset($credentials['user'],$credentials['pass']) )
		{
      $qte = $this->db->qte;
			$cols =& $this->cfg['fields'];
			$table = $this->db->get_full_name($this->cfg['table'], 1);
			$query = $this->sql."
				AND $qte$cols[user]$qte LIKE ?
				LIMIT 1 ";
			$r = $this->db->query($query,$credentials['user']);
			
			if ( $r->count() > 0 ){
				$d = $r->get_row();
				if ( empty($d['info_auth']) ){
					$info_auth = new \stdClass();
					$info_auth->log_tries = 0;
					$info_auth->last_attempt = 0;
					$info_auth->fingerprint = self::make_fingerprint();
				}
				else{
					$info_auth = json_decode();
				}

				$an_hour_ago = time() - 3600;
				$pass = \bbn\str\text::escape_squote($credentials['pass']);
				if ( $info_auth->log_tries > 3 && $info_auth->last_attempt > $an_hour_ago ){
					$res = 4;
				}
				else if ( $d[$cols['pass']] === eval("return {$this->cfg['encryption']}('$pass');") ){
					$this->auth = 1;
				}
				else
				{
					$info_auth->log_tries++;
					$info_auth->last_attempt = time();
					$this->db->query("
						UPDATE $table
						SET $qte$cols[info_auth]$qte = %s,
						$qte$cols[ip]$qte = %s
						WHERE $qte$cols[id]$qte = %u",
						json_encode($info_auth),
						$_SERVER['REMOTE_ADDR'],
						$d[$cols['id']]);
				}
				if ( $this->auth )
				{
					$this->id = $d[$cols['id']];
					$addon1 = $cols['sess_id'] ? " ".$cols['sess_id']." = '".session_id()."'," : '';
					$addon2 = $cols['ip'] ? " ".$cols['ip']." = '".$_SERVER['REMOTE_ADDR']."'," : '';
					/* If the IP and last connection fields exist it updates the table */
					$info_auth->log_tries = 0;
					$info_auth->last_attempt = 0;
					$info_auth->fingerprint = self::make_fingerprint();
					$this->db->query("
						UPDATE $table
						SET $qte$cols[last_connection]$qte = NOW(),
						$addon1
						$addon2
						$qte$cols[info_auth]$qte = %s
						WHERE $qte$cols[id]$qte = %s",
						json_encode($info_auth),
						$this->id);
					$this->_refresh_info();
					$res = 1;
				}
			}
			if ( $res != 1 && $res != 4 ){
				$res = 6;
			}
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
	protected function _refresh_info($search=array())
	{
    $qte = $this->db->qte;
		$s =& $_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']];
		$s = array();

		if ( $this->auth && $this->id ){
      $args = array($this->id);
      if ( is_array($this->cfg['condition']) && count($this->cfg['condition']) > 0 ){
        $args = array_merge(array_values($this->cfg['condition']),$args);
      }
      $d = $this->db->get_row($this->sql."
        AND $qte{$this->cfg['fields']['id']}$qte = ?",
        $args);
			$s['id'] = $this->id;
			$s['info'] = array();
			foreach ( $this->cfg['additional_fields'] as $f ){
				$s['info'][$f] = $d[$f];
			}
		}
		return $s;
	}

	/**
	 * @return bool 
	 */
	public function check_session()
	{
		if ( !isset($this->id) ){
      $qte = $this->db->qte;
			if ( isset($_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']]['id']) && $this->auth !== 1 )
			{
				$id = $_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']]['id'];
				$cols =& $this->cfg['fields'];
				/* Adding a bbn_change field would allow us to update auytomatically the user's info if something has been changed */
				$addon1 = $cols['sess_id'] ? " AND ".$cols['sess_id']." LIKE '".session_id()."'" : '';
        $table = $this->db->get_full_name($this->cfg['table'], 1);
                
				$d = $this->db->query("
					SELECT $qte$cols[id]$qte, $qte$cols[info_auth]$qte
					FROM $table
					WHERE $qte$cols[id]$qte = '%s'
					$addon1",
					$id)->get_row();
				$info_auth = json_decode($d[$cols['info_auth']]);
				
				if ( is_object($info_auth) && $info_auth->fingerprint === self::make_fingerprint() ){
					
					$this->id = $id;
					session_regenerate_id();
					if ( $cols['sess_id'] )
						$this->db->query("
							UPDATE $table
							SET $qte$cols[sess_id]$qte = '%s',
              $qte$cols[last_connection]$qte = NOW()
							WHERE $qte$cols[id]$qte = %u",
							session_id(),
							$this->id);
					$this->auth = 1;
					$this->_refresh_info();
				}
			}
		}
		return $this->auth;
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
		if ( isset($_SESSION[$this->cfg['sess_name']]) ){
			$_SESSION[$this->cfg['sess_name']][$this->cfg['sess_user']] = false;
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
		}
		return false;
	}

}
?>