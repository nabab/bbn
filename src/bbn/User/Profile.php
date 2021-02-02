<?php
/**
 * @package user
 */
namespace bbn\User;

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
class Profile
{

  protected static
    /** @var array */
    $default_class_cfg = [
      'table' => 'bbn_users_profiles',
      'cols' => [
        'id' => 'id',
        'id_group' => 'id_group',
        'id_user' => 'id_user',
      ]
    ];

  protected
    /** @var db */
    $db,
    /** @var int */
    $id,
    /** @var array */
    $cfg = [],
    /** @var array */
    $user;


  /**
   * connection constructor.
   * @param db      $db
   * @param session $session
   * @param array   $cfg
   * @param string  $credentials
   */
  public function __construct(bbn\Db $db, bbn\User $user, array $cfg = [])
  {
    if ($tmp = $user->get_profile()) {
      $this->id = $tmp['id'];
      $this->id_group = $tmp['id_group'];
      $this->id_user = $tmp['id_user'];
      $this->db = $db;
      $this->user = $user;
      $this->cfg = bbn\X::mergeArrays(self::$default_class_cfg, $cfg);
    }
    return $this;
  }

  /**
   * @return int
   */
  public function getId()
  {
    if ($this->check()) {
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
