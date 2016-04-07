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
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.2r89
 * @todo Groups and hotlinks features
 * @todo Implement Cache for session requests' results?
 */
class profile
{

	protected static
    /** @var array */
    $_defaults = [
      'table' => 'bbn_users_profiles',
      'cols' => [
        'id' => 'id',
        'id_group' => 'id_group',
        'id_user' => 'id_user',
      ]
    ];

	protected
    /** @var \bbn\db */
    $db,
    /** @var array */
    $permissions = [],
    /** @var int */
    $id,
    /** @var array */
    $cfg = [],
    /** @var array */
    $user;


  /**
   * connection constructor.
   * @param \bbn\db $db
   * @param session $session
   * @param array $cfg
   * @param string $credentials
   */
  public function __construct(\bbn\db $db, connection $user, array $cfg = []){
    if ( $tmp = $user->get_profile() ){
      $this->id = $tmp['id'];
      $this->id_group = $tmp['id_group'];
      $this->id_user = $tmp['id_user'];
      $this->db = $db;
      $this->user = $user;
      $this->cfg = \bbn\x::merge_arrays(self::$_defaults, $cfg);
    }
    return $this;
	}

  /**
   * @return int
   */
  public function get_id()
  {
    if ( $this->check() ) {
      return $this->id;
    }
  }

	/**
	 * @return boolean
	 */
	public function check()
	{
		return $this->auth;
	}

}
