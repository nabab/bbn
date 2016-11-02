<?php
/**
 * @package user
 */
namespace bbn\user;
use bbn;
/**
 * A permission system linked to options and user classes
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Oct 28, 2015, 10:23:55 +0000
 * @category  Authentication
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.1
 * @todo Groups and hotlinks features
 */

class permissions
{

	private static
					/** @var int */
					$is_init = false,
					/** @var int the ID of the root option for the permission (it should have the option's root as id_parent and bbn_permissions as code */
					$root;

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
          $sess_cfg,
          /** @var array */
          $user_cfg,
          /** @var array */
          $fields;


	public
          /** @var db */
          $db,
          /** @var mixed */
          $prev_time;

	private static function _init(permissions $perm){
		if ( !self::$is_init ){
			self::$is_init = 1;
			self::$root = $perm->get('bbn_permissions');
		}
	}

	private function _check(){
		self::init($this);
		return self::$root > 0;
	}

	public static function update_all($path, array $roots = [], $local = ''){

	}

	/**
	 * @return bbn\user\permissions
	 */
	public function __construct(bbn\db $db, bbn\appui\options $opt){
		$this->db = $db;
		$this->opt = $opt;
	}

  /**
   * Returns all the current user's permissions
   *
   * @return
   */
  public function get($code){
		if ( $this->_check() ){
			$ids = $this->opt->get_ids_by_code($code);
			foreach ( $ids as $id ){
				if ( $this->opt->is_parent($id, self::$root) ){
					return $id;
				}
			}
		}
    return false;
  }

  /**
   * Returns all the current user's permissions
   *
   * @return array
   */
  public function has($name, $id){
    return $this->permissions;
  }

}
