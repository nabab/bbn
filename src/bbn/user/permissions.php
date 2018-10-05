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
      bbn\models\tts\optional,
      bbn\models\tts\current;

  protected
          $opt,
          $pref,
          $user;

  /**
   * @param string|null $id_option
   * @param string $type
   * @return null|string
   */
  private function _get_id_option(string $id_option = null, $type = 'page'): ?string
  {
    if ( $id_option && !bbn\str::is_uid($id_option) ){
      $id_option = $this->from_path($id_option, $type);
    }
    else if ( null === $id_option ){
      $id_option = $this->get_current();
    }
    if ( bbn\str::is_uid($id_option) ){
      return $id_option;
    }
    return null;
  }

  /**
   * permissions constructor.
   */
  public function __construct()
  {
		if ( !($this->opt = bbn\appui\options::get_instance()) ){
      die('Impossible to construct permissions: you need to instantiate options before');
    }
    if ( !($this->user = bbn\user::get_instance()) ){
      die('Impossible to construct permissions: you need to instantiate user before');
    }
    if ( !($this->pref = bbn\user\preferences::get_instance()) ){
      die('Impossible to construct permissions: you need to instantiate preferences before');
    }
    self::retriever_init($this);
    self::optional_init();
	}

  /**
   * Returns the option's ID corresponds to the given path.
   *
   * @param string $path
   * @param string $type
   * @return null|string
   */
  public function from_path(string $path, $type = 'page'): ?string
  {
    $parent = null;
    if ( $root = $this->opt->from_code($type, self::$option_root_id) ){
      $parts = explode('/', $path);
      $parent = $root;
      foreach ( $parts as $i => $p ){
        $is_not_last = $i < (\count($parts) - 1);
        if ( !empty($p) ){
          $prev_parent = $parent;
          $parent = $this->opt->from_code($p.($is_not_last ? '/' : ''), $prev_parent);
          if ( !$parent && $is_not_last ){
            $parent = $this->opt->from_code($p, $prev_parent);
          }
        }
      }
    }
    return $parent ?: null;
  }

  /**
   * Returns the result of appui\options::options filtered with only the ones authorized to the current user.
   *
   * @param string|null $id_option
   * @param string $type
   * @return array|null
   */
  public function options(string $id_option = null, string $type = 'page'): ?array
  {
    if (
      ($id_option = $this->_get_id_option($id_option, $type)) &&
      ($os = $this->opt->options(\func_get_args()))
    ){
      $res = [];
      foreach ( $os as $o ){
        if ( $this->pref->has($o['id']) ){
          $res[] = $o;
        }
      }
      return $res;
    }
    return null;
  }

  /**
   * Returns the result of appui\options::full_options filtered with only the ones authorized to the current user.
   *
   * @param string|null $id_option
   * @param string $type
   * @return array|null
   */
  public function full_options(string $id_option = null, string $type = 'page'): ?array
  {
    if (
      ($id_option = $this->_get_id_option($id_option, $type)) &&
      ($os = $this->opt->full_options(\func_get_args()))
    ){
      $res = [];
      foreach ( $os as $o ){
        if ( ($ids = $this->pref->retrieve_ids($o['id'])) && ($cfg = $this->pref->get($ids[0])) ){
          $res[] = bbn\x::merge_arrays($o, $cfg);
        }
      }
      return $res;
    }
    return null;
  }

  /**
   * Returns the full list of permissions existing in the given option
   *
   * @param null|string $id_option
   * @param string $type
   * @return null|array
   */
  public function get_all(string $id_option = null, string $type = 'page'): ?array
  {
    if ( $id_option = $this->_get_id_option($id_option, $type) ){
      return $this->pref->options($id_option ?: $this->get_current());
    }
    return null;
  }

  /**
   * Returns the full list of permissions existing in the given option with all the current user's preferences
   *
   * @param null|string $id_option
   * @param string $type
   * @return array|bool|false
   */
  public function get_full($id_option = null, string $type = 'page'): ?array
  {
    if ( $id_option = $this->_get_id_option($id_option, $type) ){
      return $this->pref->full_options($id_option ?: $this->get_current());
    }
    return null;
  }

  /**
   * Returns an option combined with its sole/first permission
   *
   * @param string $id_option
   * @param string $type
   * @param bool $force
   * @return array|bool
   */
  public function get(string $id_option = null, string $type = 'page', bool $force = false): ?array
  {
    if ( $all = $this->get_all($id_option, $type) ){
      $r = [];
      foreach ( $all as $a ){
        if ( $this->has($a['id'], '', $force) ){
          $r[] = $a;
        }
      }
      return $r;
    }
    return null;
  }

  /**
   * Checks if a user and/or a group has a permission.
   *
   * @param mixed $id_option
   * @param string $type
   * @param bool $force
   * @return bool
   */
  public function has(string $id_option = null, string $type = 'page', bool $force = false): bool
  {
    if ( !$force && $this->user && $this->user->is_dev() ){
      return true;
    }
    if ( $id_option = $this->_get_id_option($id_option, $type) ){
      $option = $this->opt->option($id_option);
      if ( !empty($option['public']) ){
        return true;
      }
      return $this->pref->has($id_option, $force);
    }
    return false;
  }

  /**
   * Checks if an option corresponds to the given path.
   *
   * @param string $path
   * @param string $type
   * @return null|string
   */
  public function is(string $path, string $type = 'page'): ?string
  {
    return $this->from_path($path, $type) ?: null;
  }

  /**
   * Adapts a given array of options' to user's permissions
   *
   * @param array $arr
   * @return array
   */
  public function customize(array $arr): array
  {
    $res = [];
    if ( isset($arr[0]) ){
      foreach ( $arr as $a ){
        if ( isset($a['id']) && $this->has($a['id']) ){
          $res[] = $a;
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
          $res['items'][] = $a;
        }
      }
    }
    return $res;
  }

  /**
   * Grants a new permission to a user or a group
   * @param null|string $id_option
   * @param string $type
   * @return int
   */
  public function add(string $id_option, string $type = 'page'): ?int
  {
    if ( $id_option = $this->_get_id_option($id_option, $type) ){
      return $this->pref->set_by_option($id_option, []);
    }
    return null;
  }

  /**
   * Deletes a preference for a path or an ID.
   *
   * @param null|string $id_option
   * @param string $type
   * @return null|int
   */
  public function remove($id_option, string $type = 'page'): ?int
  {
    if ( $id_option = $this->_get_id_option($id_option, $type) ){
      return $this->pref->delete($id_option);
    }
    return null;
  }

  /**
   * Checks if the category represented by the given option ID is readable by the current user
   *
   * @param string|null $id_option
   * @return bool|null
   */
  public function read_option(string $id_option = null): ?bool
  {
    if ( bbn\str::is_uid($id_option) ){
      $root = self::get_option_id('options');
      $id_to_check = $this->opt->from_code('opt'.$id_option, $root);
      return $this->has($id_to_check, 'options');
    }
    return null;
  }

  /**
   * Checks if the category represented by the given option ID is writable by the current user
   *
   * @param string|null $id_option
   * @return bool|null
   */
  public function write_option(string $id_option): ?bool
  {
    if ( bbn\str::is_uid($id_option) ){
      $root = self::get_option_id('opt'.$id_option, 'options');
      $id_to_check = $this->opt->from_code('write', $root);
      return $this->has($id_to_check, 'options');
    }
    return null;
  }
}
