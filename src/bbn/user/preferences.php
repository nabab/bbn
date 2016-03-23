<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
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

class preferences
{

  private static
    $id_permission_root;

  protected static
    $permission_root = 'bbn_permissions',
		/** @var array */
		$_defaults = [
			'errors' => [
			],
			'table' => 'bbn_user_options',
			'cols' => [
				'id' => 'id',
				'id_option' => 'id_option',
				'id_user' => 'id_user',
				'id_group' => 'id_group',
				'id_link' => 'id_link',
				'cfg' => 'cfg'
			],
			'id_user' => null,
			'id_group' => null
		];

	protected
		/** @var \bbn\appui\options */
    $options,
		/** @var \bbn\db\connection */
    $db,
		/** @var array */
    $cfg = [],
		/** @var int */
		$id_user,
		/** @var int */
		$id_group;

  private static function _set_permission_root($root){
    self::$id_permission_root = $root;
  }

  private function _get_permission_root(){
    if ( is_null(self::$id_permission_root) &&
      ($id = $this->options->from_path(self::$permission_root))
    ){
      self::_set_permission_root($id);
    }
    return self::$id_permission_root;
  }

  /**
	 * @return \bbn\user\permissions
	 */
	public function __construct(\bbn\appui\options $options, \bbn\db\connection $db, array $cfg = []){
		$this->cfg = \bbn\x::merge_arrays(self::$_defaults, $cfg);
		$this->options = $options;
		$this->db = $db;
		$this->id_user = $this->cfg['id_user'] ?: false;
		$this->id_group = $this->cfg['id_group'] ?: false;
	}

  /**
   * Changes the current user
   * @param $id_user
   * @return $this
   */
  public function set_user($id_user){
    if ( is_int($id_user) ){
      $this->id_user = $id_user;
    }
    return $this;
  }

  /**
   * Changes the current user's group
   * @param $id_group
   * @return $this
   */
  public function set_group($id_group){
    if ( is_int($id_group) ){
      $this->id_group = $id_group;
    }
    return $this;
  }

  /**
   * Returns the current user's ID
   * @return int|false
   */
  public function get_user(){
    return $this->id_user;
  }

  /**
   * Returns the current user's group's ID
   * @return int|false
   */
  public function get_group(){
    return $this->id_group;
  }

  /**
   * Sets the cfg field of a given preference based on its ID
   * @param int $id
   * @param array $cfg
   * @return int
   */
  public function set_cfg($id, $cfg){
		if ( is_array($cfg) ){
			foreach ( $cfg as $k => $v ){
				if ( in_array($k, $this->cfg['cols']) ){
					unset($cfg[$k]);
				}
			}
			$cfg = json_encode($cfg);
		}
		return $this->db->update($this->cfg['table'], [
			$this->cfg['cols']['cfg'] => $cfg
		], [
			$this->cfg['cols']['id'] => $id
		]);
	}

  /**
   * Gets the cfg field of a given preference based on its ID
   * @param $id
   * @param null $cfg
   * @return array
   */
  public function get_cfg($id, &$cfg=null){
		if ( is_null($cfg) ){
			$cfg = $this->db->rselect(
				$this->cfg['table'],
				[$this->cfg['cols']['cfg']],
				[ $this->cfg['cols']['id'] => $id ]
			);
		}
		if ( isset($cfg[$this->cfg['cols']['cfg']]) && \bbn\str::is_json($cfg[$this->cfg['cols']['cfg']]) ) {
			$cfg = \bbn\x::merge_arrays(json_decode($cfg[$this->cfg['cols']['cfg']], 1), $cfg);
		}
		$new = [];
		if ( is_array($cfg) ){
			foreach ( $cfg as $k => $v) {
				if ( !in_array($k, $this->cfg['cols']) ) {
					$cfg[$k] = $v;
					$new[$k] = $v;
				}
			}
			unset($cfg[$this->cfg['cols']['cfg']]);
		}
		return $new;
	}

	/**
	 * Returns the current user's preference based on his own profile and his group's
	 * @param int $id_option
	 * @return
	 */
	public function get($id_option){
    $res = [];
    if ( $this->id_group &&
      ($res1 = $this->db->rselect($this->cfg['table'], $this->cfg['cols'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_group'] => $this->id_group
			]))
    ){
      $this->get_cfg($res['id'], $res1);
      $res = \bbn\x::merge_arrays($res, $res1);
		}
    if ( $this->id_user &&
      ($res2 = $this->db->rselect($this->cfg['table'], $this->cfg['cols'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_user'] => $this->id_user
			]))
    ){
      $this->get_cfg($res['id'], $res2);
      $res = \bbn\x::merge_arrays($res, $res2);
		}
    return empty($res) ? false : $res;
	}

	/**
	 * Returns a preference's ID for a given option
	 *
	 * @return int|false
	 */
	public function get_id($id_option, $id_user = null, $id_group = null){
    $res = false;
    if ( !\bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option);
    }
		if ( !$id_group && $this->id_user ){
			$res = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_user'] => $id_user ?: $this->id_user
			]);
		}
		if ( !$id_user && empty($res) && ($this->id_group || $id_group) ) {
			$res = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
				$this->cfg['cols']['id_option'] => (int)$id_option,
				$this->cfg['cols']['id_group'] => $id_group ?: $this->id_group
			]);
			//die(var_dump($id_option, $res));
		}
		return $res;
	}

	/**
	 * Returns true if a user/group has a preference, false otherwise
	 *
	 * @return bool
	 */
	public function has($id_option, $id_user = null, $id_group = null, $force = false){
    if ( !\bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option);
    }
		return
      (!$force && $this->id_group === 1) ||
      $this->get_id($id_option, $id_user, $id_group, $force) ?
        true : false;
	}

  public function has_permission($path, $type = 'page', $id_user = null, $id_group = null, $force = false){
		if ( is_null($id_group) && is_null($id_user) && ($this->id_group === 1) ){
			return 1;
		}
    if ( $id_option = $this->from_path($path, $type) ){
      return $this->has($id_option, $id_user, $id_group, $force);
    }
  }

  public function is_permission($path, $type = 'page'){
    return $this->from_path($path, $type) ? true : false;
  }

  public function from_path($path, $type = 'page'){
    $parent = null;
    if ( $root = $this->options->from_code($type, $this->_get_permission_root()) ){
      $parts = explode('/', $path);
      $num = count($parts);
      foreach ( $parts as $i => $p ){
        if ( !empty($p) ){
          if ( is_null($parent) ){
            $parent = $root;
          }
          $parent = $this->options->from_code($p.($i < $num-1 ? '/' : ''), $parent);
        }
      }
    }
    return $parent ?: false;
  }


  /**
   * Returns all the current user's permissions
   *
   * @return
   */
  public function add_permission($id_option, $type = 'page', $id_user = null, $id_group = null){
    if ( !\bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option, $type);
    }
    if ( $id = $this->get_id($id_option, $id_user, $id_group) ){
      return $id;
    }
    $d = [
      'id_option' => $id_option,
    ];
    if ( !empty($id_user) ){
      $d['id_user'] = $id_user;
    }
    else if ( !empty($id_group) ){
      $d['id_group'] = $id_group;
    }
    else{
      return false;
    }
    if ( $r = $this->db->insert($this->cfg['table'], $d) ){
      return $this->db->last_id();
    }
    return false;
  }

  /**
   * Returns all the current user's permissions
   *
   * @return
   */
  public function remove_permission($id_option, $type = 'page', $id_user = null, $id_group = null){
    if ( !\bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option, $type);
    }
    if ( $id_user && ($id = $this->get_id($id_option, $id_user)) ){
      return $this->db->delete($this->cfg['table'], [$this->cfg['cols']['id'] => $id]);
    }
    if ( $id_group && ($id = $this->get_id($id_option, null, $id_group)) ){
      return $this->db->delete($this->cfg['table'], [$this->cfg['cols']['id'] => $id]);
    }
    return false;
  }

  /**
	 * Sets permission's config to user or group - and adds the permission if needed
	 *
	 * @return
	 */
	public function set($id_option, array $cfg, $id_user = null, $id_group = null){
    if ( !\bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option);
    }
    if ( !$id_user && $this->id_group ) {
			$id = $this->db->get_val($this->cfg['table'], $this->cfg['cols']['id'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_group'] => $id_group ?: $this->id_group
			]);
		}
		else if ( $id_user || $this->id_user ){
			$id = $this->db->get_val($this->cfg['table'], $this->cfg['cols']['id'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_user'] => $id_user ?: $this->id_user
			]);
		}
		else{
			return false;
		}
		if ( $id ) {
			return $this->set_cfg($id, $cfg);
		}
		$r = $this->db->insert($this->cfg['table'], [
			'id_option' => $id_option,
			'id_user' => !$id_group && ($id_user || $this->id_user) ? ($id_user ? $id_user : $this->id_user)  : null,
			'id_group' => $id_group ?: null,
			'cfg' => json_encode($this->get_cfg(false, $cfg))
		]);
    return $r;
	}

	/**
	 * Returns all the current user's permissions
	 *
	 * @return array
	 */
	public function delete($id_option, $id_user = null, $id_group = null){
    if ( !\bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option);
    }
		if ( $id_group ) {
			return $this->db->delete($this->cfg['table'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_group'] => $id_group
			]);
		}
		if ( $id_user || $this->id_user ) {
			return $this->db->delete($this->cfg['table'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_user'] => $id_user ? $id_user : $this->id_user
			]);
		}
	}

}
