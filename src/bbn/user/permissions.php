<?php
/**
 * @package user
 */
namespace bbn\user;
use bbn;
/**
 * A permission system linked to options, user classes and preferences.
 * From the moment a user or a group has a preference on an item, it is considered to have a permission. Deleting a permission deletes the preference
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Nov 24, 2016, 13:23:12 +0000
 * @category  Authentication
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.1
 * @todo Store the deleted preferences? And restore them if the a permission is re-given
 */

class permissions extends bbn\models\cls\basic
{
  use bbn\models\tts\retriever,
      bbn\models\tts\optional;

	private static
					/** @var int */
					$is_init = false,
					/** @var int the ID of the root option for the permission (it should have the option's root as id_parent and bbn_permissions as code */
					$root;

  protected
          $options,
          $pref,
          $user;


	/**
   * Constructor
   *
	 * @return self
	 */
	public function __construct(){
		$this->options = bbn\appui\options::get_instance();
    $this->pref = bbn\user\preferences::get_instance();
    $this->user = bbn\user::get_instance();
    self::retriever_init($this);
    self::optional_init($this);
	}

  /**
   * Return the list of permissions existing in the given option
   *
   * @param $id_option
   * @param string $type
   * @return array|bool|false
   */
  public function get_all($id_option, string $type = 'page'){
    if ( !bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option, $type);
    }
    if ( bbn\str::is_integer($id_option) ){
      return $this->options->get_codes($id_option);
    }
    return false;
  }

  /**
   * Return the list of permissions authorized in the given option
   *
   * @param $id_option
   * @param string $type
   * @param int|null $id_user
   * @param int|null $id_group
   * @param bool $force
   * @return array|bool
   */
  public function get($id_option, string $type = 'page', int $id_user = null, int $id_group = null, bool $force = false){
    if ( $all = $this->get_all($id_option, $type) ){
      $r = [];
      foreach ( $all as $k => $a ){
        if ( $this->has($k, '', $id_user, $id_group, $force) ){
          $r[$k] = $a;
        }
      }
      return $r;
    }
    return false;
  }

  /**
   * Checks if a user and/or a group has a permission.
   *
   * @param mixed $id_option
   * @param string $type
   * @param int|null $id_user
   * @param int|null $id_group
   * @param bool $force
   * @return bool
   */
  public function has($id_option, string $type = 'page', int $id_user = null, int $id_group = null, bool $force = false){
    if ( !$force && $this->user ){
      // User is admin
      if ( !$id_group && !$id_user && ($this->user->get_group() === 1) ){
        return true;
      }
      if ( $this->user->is_admin() ){
        return true;
      }
    }
    if ( !bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option, $type);
    }
    if ( bbn\str::is_integer($id_option) ){
      if ( $id_option ){
        $option = $this->options->option($id_option);
        if ( !empty($option['public']) ){
          return true;
        }
        return $this->has($id_option, $id_user ?: $this->user->get_id(), $id_group ?: $this->user->get_group(), $force);
      }
    }
    return false;
  }

  /**
   * Checks if an option corresponds to the given path.
   *
   * @param string $path
   * @param string $type
   * @return bool
   */
  public function is(string $path, string $type = 'page'){
    return $this->from_path($path, $type) ? true : false;
  }

  /**
   * Returns the option's ID corresponds to the given path.
   *
   * @param string $path
   * @param string $type
   * @return bool
   */
  public function from_path(string $path, $type = 'page'){
    $parent = null;
    if ( $root = $this->options->from_code($type, self::$option_root_id) ){
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
   * Grants a new permission to a user or a group
   * @param $id_option
   * @param string $type
   * @param null $id_user
   * @param null $id_group
   * @return bool
   */
  public function add($id_option, $type = 'page', $id_user = null, $id_group = null){
    if ( !bbn\str::is_integer($id_option) ){
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
   * Deletes a preference for a path or an ID.
   *
   * @param $id_option
   * @param string $type
   * @param int|null $id_user
   * @param int|null $id_group
   * @return array
   */
  public function remove($id_option, string $type = 'page', int $id_user = null, int $id_group = null){
    if ( !bbn\str::is_integer($id_option) ){
      $id_option = $this->from_path($id_option, $type);
    }
    if ( bbn\str::is_integer($id_option) ){
      return $this->pref->delete($id_option, $id_user, $id_group);
    }
    return false;
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

  public function read_option($id_option, $id_user = null, $id_group = null){
    if ( bbn\str::is_integer($id_option) ){
      $root = self::get_option_root();
      $id_to_check = $this->options->from_code('opt'.$id_option, $root);
      return $this->has($id_to_check, 'options', $id_user, $id_group);
    }
    return false;
  }

  public function write_option($id_option, $id_user = null, $id_group = null){
    if ( bbn\str::is_integer($id_option) ){
      $root = self::get_option_root();
      $option = $this->options->from_code('opt'.$id_option, $root);
      $id_to_check = $this->options->from_code('write', $option);
      return $this->has($id_to_check, 'options', $id_user, $id_group);
    }
    return false;
  }
}
