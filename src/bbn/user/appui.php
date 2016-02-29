<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
/**
 * Class for appui authentication
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Authentication
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class appui extends \bbn\user\connection
{
	/**
	 * @var int
	 */
	private static $nabab=1;

	/**
	 * @var int
	 */
	private static $admin_account=573052;

	/**
	 * @var array
	 */
	private $infos=[];

	/**
	 * @var mixed
	 */
	public $privilege;

	/**
	 * @var mixed
	 */
	public $id_account;


	/**
	 * @return void 
	 */
	public function __construct($cfg=[])
	{
		self::set_cfg([
			'fields' => [
				'id' => 'id',
				'user' => 'bbn_login',
				'pass' => 'bbn_pass',
				'sess_id' => 'bbn_id_session',
				'log_tries' => 'bbn_log_tries',
				'last_attempt' => 'bbn_last_attempt',
				'fingerprint' => 'bbn_fingerprint',
				'ip' => 'bbn_ip',
				'lang' => 'bbn_lang',
				'last_connection' => 'bbn_last_time'
			],
			'encryption' => 'sha1',
			'table' => 'id_users',
			'condition' => "bbn_active LIKE 'y'",
			'additional_fields' => ['bbn_id_account'],
			'num_attempts' => 3,
			'session' => 'id_user'
		]);
		parent::__construct($cfg);
		if ( $this->auth )
		{
			self::$db->query("UPDATE id_users SET bbn_nb_conn = bbn_nb_conn + 1");
			$this->refresh_info();
		}
	}

	/**
	 * @return void 
	 */
	private function get_info()
	{
		if ( $this->auth() )
		{
			if ( isset($this->info) )
				return $this->info;
			else
				return refresh_info();
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function is_nabab()
	{
		if ( $this->check() && $this->id == self::$nabab && $this->id_account == self::$admin_account )
			return true;
		return false;
	}

	/**
	 * @return void 
	 */
	private function refresh_info()
	{
		return ( $this->auth() && $this->id ) ?
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
	 * @return void 
	 */
	public function get_lang()
	{
		return $this->infos['lang'];
	}

	/**
	 * @return void 
	 */
	public function register()
	{
		if ( $this->auth || $this->is_nabab() )
		{
			if (
				( $d = self::$db->query("
					SELECT *
					FROM id_users
					WHERE id = %u
					AND bbn_active = 'y'
					LIMIT 1",
					$this->id)->get_row()
				)
				&&
				(	$p = self::$db->query("
					SELECT bbn_accounts.bbn_societe AS company,
					bbn_priv_accounts.bbn_bills,bbn_priv_accounts.id_users,
					bbn_priv_accounts.bbn_admin_msg,bbn_priv_accounts.bbn_site_creation
					FROM bbn_accounts
						JOIN bbn_priv_accounts
							ON bbn_priv_accounts.bbn_id_account = bbn_accounts.id
							AND bbn_priv_accounts.bbn_id_user = %u
					WHERE bbn_accounts.id = %u
					AND bbn_priv_accounts.bbn_type = 333
					LIMIT 1",
					$this->id,
					$this->id_account)->get_row()
				)
			)
			{
				$this->infos = [
					'ps' => $this->ps,
					'prev_time' => $this->prev_time,
					'company' => $p['company'],
					'fenster' => [],
					'js' => [],
					'priv' => [],
					'sites' => [],
					'online_users' => [],
					'chat' => [
						'on' => 0,
						'last_id' => 0,
						'last_time' => 0,
						'interval' => 0
					],
					'mails' => [],
					'sync_mail' => [
						'acc' => [],
						'dir' => [],
						'num' => []
					],
					'socket_akt' => 'init'
				];
				foreach ( $d as $n => $v )
				{
					if ( substr($n,-4) != 'pass' )
					{
						if ( strpos($n,'bbn_') === 0 )
							$n = substr($n,4);
						$this->infos[$n] = $v;
					}
				}
				foreach ( $p as $n => $v )
				{
					if ( strpos($n,'bbn_') === 0 )
						$this->infos['priv'][substr($n,4)] = $v;
				}
				/*We check the emails */
				$r = self::$db->query("
					SELECT bbn_email_accounts.*,bbn_contact_emails.bbn_email,bbn_contact_emails.bbn_name
					FROM bbn_email_accounts
						JOIN bbn_contact_emails
							ON bbn_contact_emails.id = bbn_email_accounts.bbn_id_contact_email
					WHERE bbn_email_accounts.bbn_id_user = %u
					AND bbn_email_accounts.bbn_active = 'y'
					ORDER BY bbn_contact_emails.bbn_email",
					$this->infos['id']);
				if ( $r->count() > 0 )
				{
					while ( $d = $r->get_row() )
					{
						$this->infos['mails'][$d['id']] = [
							'bbn_email' => $d['bbn_email'],
							'bbn_name' => $d['bbn_name'],
							'bbn_type' => $d['bbn_type'],
							'bbn_host' => $d['bbn_host'],
							'bbn_login' => $d['bbn_login'],
							'bbn_pass' => $d['bbn_pass'],
							'bbn_selected_dir' => $d['bbn_inbox'],
							'bbn_last_time' => 1,
							'bbn_inbox' => $d['bbn_inbox'],
							'bbn_sent' => $d['bbn_sent'],
							'bbn_trash' => $d['bbn_trash'],
							'bbn_draft' => $d['bbn_draft'],
							'bbn_spam' => $d['bbn_spam'],
							'bbn_folders' => $d['bbn_folders'],
							'bbn_dir' => []
						];
						$mbox_dirs = explode("\n",$d['bbn_folders']);
						if ( !empty($d['bbn_spam']) )
							array_unshift($mbox_dirs,$d['bbn_spam']);
						if ( !empty($d['bbn_draft']) )
							array_unshift($mbox_dirs,$d['bbn_draft']);
						if ( !empty($d['bbn_trash']) )
							array_unshift($mbox_dirs,$d['bbn_trash']);
						if ( !empty($d['bbn_sent']) )
							array_unshift($mbox_dirs,$d['bbn_sent']);
						array_unshift($mbox_dirs,$d['bbn_inbox']);
						foreach ( $mbox_dirs as $mbox_dir )
						{
							if ( !empty($mbox_dir) )
							{
								$r1 = self::$db->query("
									SELECT MAX(bbn_uid)
									FROM bbn_emails
									WHERE bbn_id_email_account= %u
									AND bbn_id_user = %u
									AND bbn_folder LIKE '%s'",
									$d['id'],
									$u['id'],
									$mbox_dir);
								if ( $r1->count() > 0 )
									$last_uid = $r1->fetchColumn();
								else
									$last_uid = 0;
								$this->infos['mails'][$bbn['id']]['bbn_dir'][$mbox_dir] = [
									'bbn_num_msg' => $r1->count(),
									'bbn_last_uid' => $last_uid
								];
							}
						}
					}
				}
				/* We check the online users */
				$c = $this->is_nabab() ? '' : sprintf("bbn_id_account = %u AND ",$this->id_account);
				$r = self::$db->query("
					SELECT id,bbn_fname,bbn_name,bbn_login
					FROM id_users
					WHERE $c
					bbn_last_time > '%s'
					AND id != %u
					ORDER BY bbn_login",
					date('Y-m-d H:i:s',time()-180),
					$u['id']);
				while ( $d = $r->get_row() )
					$this->infos['online_users'][$d['id']] = [
						'bbn_login' => $d['bbn_login'],
						'bbn_fname' => $d['bbn_fname'],
						'bbn_name' => $d['bbn_name']
					];
					
				/* Check the sites privileges for this user */
				$r = self::$db->query("
					SELECT bbn_id_site
					FROM bbn_priv_sites
					WHERE bbn_type = 333
					AND bbn_id_user = %u",
					$this->id);
				if ( $r->count() > 0 )
				{
					/* add privileges for sites in the session through an array */
					while ( $d = $r->get_row() )
						array_push($this->infos['sites'],$d['bbn_id_site']);
				}
				if ( !in_array($this->infos['id_last_site'],$this->infos['sites']) )
					$this->infos['id_last_site'] = 0;
				return $this->infos;
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function has_site($id)
	{
		if ( $this->is_nabab() )
			return true;
		return in_array($id,$this->infos['sites']);
	}

	/**
	 * @return void 
	 */
	public function change_last_site($id)
	{
		if ( $this->has_site($id) && self::$db->query("
			UPDATE id_users
			SET bbn_id_last_site = %u
			WHERE id = %u
			LIMIT 1",
			$id,
			$this->id)
		)
			return true;
		return false;
	}

}
?>