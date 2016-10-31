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
    $current,
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
		/** @var \bbn\db */
    $db,
		/** @var array */
    $class_cfg = [],
		/** @var int */
		$id_user,
		/** @var int */
		$id_group;

  /**
   * 	Returns the ID of the option which is at the root of the permissions' path
   *
   * @return int
   */
  private static function _get_permission_root(){
		if ( is_null(self::$id_permission_root) ){
      /** @var \bbn\appui\options $opt */
      $opt = \bbn\appui\options::get_options();
			self::$id_permission_root = $opt->from_path(self::$permission_root);
		}
		return self::$id_permission_root;
	}

  protected static function _init(preferences $pref){
    self::$current =& $pref;
  }

  /**
   * @return \bbn\appui\options
   */
  public static function get_preferences(){
    return self::$current;
  }

  /**
	 * @return \bbn\user\permissions
	 */
	public function __construct(\bbn\appui\options $options, \bbn\db $db, array $cfg = []){
		$this->class_cfg = \bbn\x::merge_arrays(self::$_defaults, $cfg);
		$this->options = \bbn\appui\options::get_options();
		$this->db = $db;
		$this->id_user = $this->class_cfg['id_user'] ?: false;
		$this->id_group = $this->class_cfg['id_group'] ?: false;
    self::_init($this);
	}

  public function get_user(){
    return $this->id_user;
  }

  public function get_group(){
    return $this->id_group;
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
   * Sets the cfg field of a given preference based on its ID
   * @param int $id
   * @param array $cfg
   * @return int
   */
  public function set_cfg($id, $cfg){
		if ( is_array($cfg) ){
			foreach ( $cfg as $k => $v ){
				if ( in_array($k, $this->class_cfg['cols']) ){
					unset($cfg[$k]);
				}
			}
			$cfg = json_encode($cfg);
		}
		return $this->db->update($this->class_cfg['table'], [
			$this->class_cfg['cols']['cfg'] => $cfg
		], [
			$this->class_cfg['cols']['id'] => $id
		]);
	}

	public function get_class_cfg(){
	  return $this->class_cfg;
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
				$this->class_cfg['table'],
				[$this->class_cfg['cols']['cfg']],
				[ $this->class_cfg['cols']['id'] => $id ]
			);
		}
		if ( isset($cfg[$this->class_cfg['cols']['cfg']]) && \bbn\str::is_json($cfg[$this->class_cfg['cols']['cfg']]) ) {
			$cfg = \bbn\x::merge_arrays(json_decode($cfg[$this->class_cfg['cols']['cfg']], 1), $cfg);
		}
		$new = [];
		if ( is_array($cfg) ){
			foreach ( $cfg as $k => $v) {
				if ( !in_array($k, $this->class_cfg['cols']) ) {
					$cfg[$k] = $v;
					$new[$k] = $v;
				}
			}
			unset($cfg[$this->class_cfg['cols']['cfg']]);
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
      ($res1 = $this->db->rselect($this->class_cfg['table'], $this->class_cfg['cols'], [
				$this->class_cfg['cols']['id_option'] => $id_option,
				$this->class_cfg['cols']['id_group'] => $this->id_group
			]))
    ){
      $this->get_cfg($res1['id'], $res1);
      $res = \bbn\x::merge_arrays($res, $res1);
		}
    if ( $this->id_user &&
      ($res2 = $this->db->rselect($this->class_cfg['table'], $this->class_cfg['cols'], [
				$this->class_cfg['cols']['id_option'] => $id_option,
				$this->class_cfg['cols']['id_user'] => $this->id_user
			]))
    ){
      $this->get_cfg($res2['id'], $res2);
      $res = \bbn\x::merge_arrays($res, $res2);
		}
    return empty($res) ? false : $res;
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
    if ( !$force && $this->id_group === 1 ){
      return true;
    }
    if ( $id_user && $this->retrieve_id($id_option, $id_user, false, $force) ){
      return true;
    }
    if ( $id_group && $this->retrieve_id($id_option, false, $id_group, $force) ){
      return true;
    }
		return false;
	}

	public function get_existing_permissions($path){
		$r = [];
		if ( $id = $this->from_path($path) ){
			// Keeps the order
			$opt = $this->options->full_options($id);
			foreach ( $opt as $o ){
				$r[$o['id']] = $o['code'];
			}
			return $r;
		}
		return $r;
	}

	public function has_permission($path, $type = 'page', $id_user = null, $id_group = null, $force = false){
		if ( !$id_group && !$id_user && ($this->id_group === 1) ){
			return true;
		}
		if ( ($user = \bbn\user\connection::get_user()) && $user->is_admin() ){
			return true;
		}
		if ( is_int($path) ){
			$id_option = $path;
		}
		else{
			$id_option = $this->from_path($path, $type);
		}
		if ( $id_option ){
			$option = $this->options->option($id_option);
			if ( !empty($option['public']) ){
				return true;
			}
			return $this->has($id_option, $id_user ?: $this->id_user, $id_group ?: $this->id_group, $force);
		}
	}

  public function is_permission($path, $type = 'page'){
    return $this->from_path($path, $type) ? true : false;
  }

  public function from_path($path, $type = 'page'){
    $parent = null;
    if ( $root = $this->options->from_code($type, self::_get_permission_root()) ){
      $parts = explode('/', $path);
      $num = count($parts);
      foreach ( $parts as $i => $p ){
        if ( !empty($p) ){
          if ( is_null($parent) ){
            $parent = $root;
          }
					$prev_parent = $parent;
          $parent = $this->options->from_code($p.($i < $num-1 ? '/' : ''), $parent);
					if ( !$parent && ($i < $num-1) ){
						$parent = $this->options->from_code($p, $prev_parent);
					}
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
    if ( $id = $this->retrieve_id($id_option, $id_user, $id_group) ){
      return $id;
    }
    $d = [
      'id_option' => $id_option,
    ];
    if ( !empty($id_group) ){
      $d['id_group'] = $id_group;
    }
    else if ( !empty($id_user) ){
      $d['id_user'] = $id_user;
    }
    else if ( $this->id_user ){
      $d['id_user'] = $this->id_user;
    }
    if ( $r = $this->db->insert($this->class_cfg['table'], $d) ){
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
    if ( $id_user && ($id = $this->retrieve_id($id_option, $id_user)) ){
      return $this->db->delete($this->class_cfg['table'], [$this->class_cfg['cols']['id'] => $id]);
    }
    if ( $id_group && ($id = $this->retrieve_id($id_option, null, $id_group)) ){
      return $this->db->delete($this->class_cfg['table'], [$this->class_cfg['cols']['id'] => $id]);
    }
    return false;
  }

  /**
   * 
   * @param $id_pref
   * @param $id_link
   * @return int
   */
  public function set_link($id_option, $id_link, $id_user = null, $id_group = null){
    if ( $id = $this->retrieve_id($id_option, $id_user, $id_group) ){
      return $this->db->update($this->class_cfg['table'], [
        $this->class_cfg['cols']['id_link'] => $id_link
      ], [
        $this->class_cfg['cols']['id'] => $id
      ]);
    }
    else{
      if ( !\bbn\str::is_integer($id_option) ){
        $id_option = $this->from_path($id_option);
      }
      if ( \bbn\str::is_integer($id_option, $id_link) ){
        return $this->db->insert($this->class_cfg['table'], [
          $this->class_cfg['cols']['id_option'] => $id_option,
          $this->class_cfg['cols']['id_link'] => $id_link,
          $this->class_cfg['cols']['id_group'] => $id_group ? $id_group : null,
          $this->class_cfg['cols']['id_user'] => $id_group ? null : ( $id_user ?: $this->id_user )
        ]);
      }
    }
  }

  /**
   *
   * @param $id_pref
   * @param $id_link
   * @return int
   */
  public function unset_link($id_option, $id_user = null, $id_group = null){
    if ( $id = $this->retrieve_id($id_option, $id_user, $id_group) ){
      return $this->db->update($this->class_cfg['table'], [
        $this->class_cfg['cols']['id_link'] => null
      ], [
        $this->class_cfg['cols']['id'] => $id
      ]);
    }
  }

  public function get_links($id, $id_user = null, $id_group = null){
    if ( !empty($id_group) ){
      $cfg = ['id_group' => $id_group];
    }
    else if ( !empty($id_user) || !empty($this->id_user) ){
      $cfg = ['id_user' => $id_user ?: $this->id_user];
    }
    if ( isset($cfg) ){
      $cfg[$this->class_cfg['cols']['id_link']] = $id;
      return $this->db->get_column_values($this->class_cfg['table'], $this->class_cfg['cols']['id_option'], $cfg);
    }
  }

  /**
   * Returns the ID of a preference from the table
   *
   * @param int $id_option
   * @param null|int $id_user
   * @param null|int $id_group
   * @return false|int
   */
  public function retrieve_id($id_option, $id_user = null, $id_group = null){
    if ( !\bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option);
    }
    if ( !$id_user && $id_group ){
      return $this->db->get_val($this->class_cfg['table'], $this->class_cfg['cols']['id'], [
        $this->class_cfg['cols']['id_option'] => $id_option,
        $this->class_cfg['cols']['id_group'] => $id_group ?: $this->id_group
      ]);
    }
    else if ( $id_user || $this->id_user ){
      return $this->db->get_val($this->class_cfg['table'], $this->class_cfg['cols']['id'], [
        $this->class_cfg['cols']['id_option'] => $id_option,
        $this->class_cfg['cols']['id_user'] => $id_user ?: $this->id_user
      ]);
    }
    return false;
  }

  /**
	 * Sets permission's config to user or group - and adds the permission if needed
	 *
	 * @return
	 */
	public function set($id_option, array $cfg, $id_user = null, $id_group = null){
		if ( $id = $this->retrieve_id($id_option, $id_user, $id_group) ) {
			return $this->set_cfg($id, $cfg);
		}
		return $this->db->insert($this->class_cfg['table'], [
			'id_option' => $id_option,
			'id_user' => !$id_group && ($id_user || $this->id_user) ? ($id_user ? $id_user : $this->id_user)  : null,
			'id_group' => $id_group ?: null,
			'cfg' => json_encode($this->get_cfg(false, $cfg))
		]);
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
			return $this->db->delete($this->class_cfg['table'], [
				$this->class_cfg['cols']['id_option'] => $id_option,
				$this->class_cfg['cols']['id_group'] => $id_group
			]);
		}
		if ( $id_user || $this->id_user ) {
			return $this->db->delete($this->class_cfg['table'], [
				$this->class_cfg['cols']['id_option'] => $id_option,
				$this->class_cfg['cols']['id_user'] => $id_user ? $id_user : $this->id_user
			]);
		}
	}

	/**
	 * Adapts a given array of options' to user's permissions
	 *
	 * @param array $arr
	 * @return array
	 */
	public function customize(array $arr){
		$res = [];
		if ( isset($arr[0]) ){
			foreach ( $arr as $a ){
				if ( isset($a['id']) && $this->has($a['id']) ){
					array_push($res, $a);
				}
			}
		}
		else if ( isset($arr['items']) ){
			$res = $arr;
			unset($res['items']);
			foreach ( $arr['items'] as $a ){
				if ( isset($a['id']) && $this->has($a['id']) ){
					if ( !isset($res['items']) ){
						$res['items'] = [];
					}
					array_push($res['items'], $a);
				}
			}
		}
		return $res;
	}


}
