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

	protected static
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
			$opt,
		/** @var \bbn\db\connection */
			$db,
		/** @var array */
			$cfg = [],
		/** @var int */
		$id_user,
		/** @var int */
		$id_group;

  /**
	 * @return \bbn\user\permissions
	 */
	public function __construct(\bbn\appui\options $opt, \bbn\db\connection $db, array $cfg = []){
		$this->cfg = \bbn\x::merge_arrays(self::$_defaults, $cfg);
		$this->opt = $opt;
		$this->db = $db;
		$this->id_user = $this->cfg['id_user'] ?: false;
		$this->id_group = $this->cfg['id_group'] ?: false;
	}

  public function set_user($id_user){
    if ( is_int($id_user) ){
      $this->id_user = $id_user;
    }
    return $this;
  }

  public function set_group($id_group){
    if ( is_int($id_group) ){
      $this->id_group = $id_group;
    }
    return $this;
  }

  public function get_user($id_user){
    return $this->id_user;
  }

  public function get_group($id_group){
    return $this->id_group;
  }

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
	 * Returns all the current user's permissions
	 *
	 * @return
	 */
	public function get($id_option){
		if ( $this->id_user ){
			$res = $this->db->rselect($this->cfg['table'], $this->cfg['cols'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_user'] => $this->id_user
			]);
		}
		if ( empty($res) && $this->id_group ) {
			$res = $this->db->rselect($this->cfg['table'], $this->cfg['cols'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_group'] => $this->id_group
			]);
		}
		else{
			return false;
		}
		if ( $res ) {
			$this->get_cfg($res['id'], $res);
			return $res;
		}
		return false;
	}

	/**
	 * Returns a preference's ID for a given option
	 *
	 * @return int|false
	 */
	public function get_id($id_option, $id_user = null, $id_group = null){
    $res = false;
		if ( $this->id_user ){
			$res = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_user'] => $id_user ?: $this->id_user
			]);
		}
		if ( !$id_user && empty($res) && $this->id_group ) {
			$res = $this->db->select_one($this->cfg['table'], $this->cfg['cols']['id'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_group'] => $id_group ?: $this->id_group
			]);
		}
		return $res;
	}

	/**
	 * Returns true if a user/group has a preference, false otherwise
	 *
	 * @return bool
	 */
	public function has($id_option, $id_user = null, $id_group = null){
		return
      ($this->id_group === 1) ||
      $this->get_id($id_option, $id_user, $id_group) ?
        true : false;
	}

	/**
	 * Returns all the current user's permissions
	 *
	 * @return
	 */
	public function set($id_option, array $cfg, $id_user = null, $id_group = null){
		if ( $id_group ) {
			$id = $this->db->get_val($this->cfg['table'], $this->cfg['cols']['id'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_group'] => $id_group
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
