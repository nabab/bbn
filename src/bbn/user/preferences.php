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
					'value' => 'value',
					'active' => 'active'
			]
	];

	protected
		/** @var \bbn\appui\options */
			$opt,
		/** @var \bbn\db\connection */
			$db,
		/** @var array */
			$cfg = [],
		/** @var int */
			$id_user;

  /**
	 * @return \bbn\user\permissions
	 */
	public function __construct(\bbn\appui\options $opt, \bbn\db\connection $db, array $cfg = []){
		$this->cfg = \bbn\tools::merge_arrays(self::$_defaults, $cfg);
		$this->opt = $opt;
		$this->db = $db;
	}

	public function set_user($id_user){
		if ( is_int($id_user) ){
			$this->id_user = $id_user;
		}
		return $this;
	}

	public function set_value($id, $val){
		if ( is_array($val) ){
			foreach ( $val as $k => $v ){
				if ( in_array($k, $this->cfg['cols']) ){
					unset($val[$k]);
				}
			}
			$val = json_encode($val);
		}
		return $this->db->update($this->cfg['table'], [
			$this->cfg['cols']['value'] => $val
		], [
			$this->cfg['cols']['id'] => $id
		]);
	}

	public function get_value($id, &$val=null){
		if ( is_null($val) ){
			$val = $this->db->rselect(
				$this->cfg['table'],
				[$this->cfg['cols']['value']],
				[ $this->cfg['cols']['id'] => $id ]
			);
		}
		if ( isset($val[$this->cfg['cols']['value']]) && \bbn\str\text::is_json($val[$this->cfg['cols']['value']]) ) {
			$val = \bbn\tools::merge_arrays(json_decode($val[$this->cfg['cols']['value']], 1), $val);
		}
		$new = [];
		if ( is_array($val) ){
			foreach ( $val as $k => $v) {
				if ( !in_array($k, $this->cfg['cols']) ) {
					$val[$k] = $v;
					$new[$k] = $v;
				}
			}
			unset($val[$this->cfg['cols']['value']]);
		}
		return $new;
	}

	/**
	 * Returns all the current user's permissions
	 *
	 * @return
	 */
	public function get($id_option, $id_user = null, $id_group = null){
		if ( $id_group ) {
			$res = $this->db->rselect($this->cfg['table'], $this->cfg['cols'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_group'] => $id_group
			]);
		}
		else if ( $id_user || $this->id_user ){
			$res = $this->db->rselect($this->cfg['table'], $this->cfg['cols'], [
				$this->cfg['cols']['id_option'] => $id_option,
				$this->cfg['cols']['id_user'] => $id_user ? $id_user : $this->id_user
			]);
		}
		else{
			return false;
		}
		if ( $res ) {
			$this->get_value($res['id'], $res);
			return $res;
		}
		return false;
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
				$this->cfg['cols']['id_user'] => $id_user ? $id_user : $this->id_user
			]);
		}
		else{
			return false;
		}
		if ( $id ) {
			return $this->set_value($id, $cfg);
		}
		$r = $this->db->insert($this->cfg['table'], [
			'id_option' => $id_option,
			'id_user' => !$id_group && ($id_user || $this->id_user) ? ($id_user ? $id_user : $this->id_user)  : null,
			'id_group' => $id_group ? $id_group : null,
			'value' => json_encode($this->get_value(false, $cfg))
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
