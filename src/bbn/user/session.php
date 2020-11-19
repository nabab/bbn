<?php
/**
 * @package user
 */
namespace bbn\user;

use bbn;

/**
 * A session management object for asynchronous PHP tasks
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

if (!defined('BBN_APP_NAME')) {
  define('BBN_APP_NAME', 'bbn-app');
}

class session
{
  use bbn\models\tts\singleton;

  /** @var string */
  protected static $name = BBN_APP_NAME;

  protected $was_opened = false;

  protected $once_opened = false;

  protected $data;

  protected $id;


  public function __construct(array $defaults = null)
  {
    if (!self::singleton_exists()) {
      /*
      if (defined('BBN_DATA_PATH') && !$this->is_opened()) {
        session_save_path(BBN_DATA_PATH.'sessions');
      }
      */
      self::singleton_init($this);

      if ($id = session_id()) {
        $this->was_opened = true;
        $this->once_opened = true;
      }

      $this->open();
      if ($this->id = session_id()) {
        if (!isset($_SESSION[self::$name])) {
          $_SESSION[self::$name] = \is_array($defaults) ? $defaults : [];
        }

        $this->data = $_SESSION[self::$name];
        $this->close();
      }
    }
  }


  public function is_opened(): bool
  {
    return session_status() !== PHP_SESSION_NONE;
  }


  public function get()
  {
    if ($this->id) {
      return $this->_get_value(\func_get_args());
    }
  }


  public function fetch($arg=null)
  {
    if ($this->id) {
      $this->open();
      $this->data = $_SESSION[self::$name];
      $this->close();
      if (\is_null($arg)) {
        return $this->data;
      }

      return $this->_get_value(\func_get_args());
    }
  }


  public function has()
  {
    return !\is_null($this->_get_value(\func_get_args()));
  }


  public function set($val)
  {
    if ($this->id) {
      $this->_set_value(\func_get_args());
      $this->open();
      $_SESSION[self::$name] = $this->data;
      $this->close();
    }

    return $this;
  }


  public function uset($val)
  {
    if ($this->id) {
      $args = \func_get_args();
      array_unshift($args, null);
      $this->_set_value($args);
      $this->open();
      $_SESSION[self::$name] = $this->data;
      $this->close();
    }

    return $this;
  }


  public function transform(callable $fn)
  {
    if ($this->id) {
      $args = \func_get_args();
      array_shift($args);
      $transformed = \call_user_func($fn, $this->_get_value($args));
      array_unshift($args, $transformed);
      $this->_set_value($args);
      $this->open();
      $_SESSION[self::$name] = $this->data;
      $this->close();
    }

    return $this;
  }


  public function work(callable $fn)
  {
    return $this->transform(...\func_get_args());
  }


  public function push($value)
  {
    if ($this->id) {
      $args = \func_get_args();
      array_shift($args);
      $var = $this->get(...$args);
      if (!\is_array($var)) {
        $var = [];
      }

      if (!\in_array($value, $var)) {
        array_push($var, $value);
        array_unshift($args, $var);
        $this->set(...$args);
      }

      return $this;
    }
  }


  public function destroy()
  {
    if ($this->id) {
      $this->open();
      $args = \func_get_args();
      $var  =& $_SESSION[self::$name];
      $var2 =& $var;
      foreach ($args as $i => $a){
        if (!\is_array($var)) {
          $var = [];
        }

        if (!isset($var[$a])) {
          if (\count($args) >= $i) {
            $var[$a] = [];
          }
          else{
            break;
          }
        }

        unset($var2);
        $var2 =& $var[$a];
        unset($var);
        $var =& $var2;
      }

      $var        = null;
      $this->data = isset($_SESSION[self::$name]) ? $_SESSION[self::$name] : [];
      $this->close();
      return $this;
    }
  }


  /**
   * Executes a function on the session or a part of the session
   * @param function $func
   * @return session
   */
  public function get_id()
  {
    return $this->id;
  }


  public function set_data_state($name, $data)
  {
    if ($this->id) {
      $this->set(md5(serialize($data)), $name, 'bbn-data-state');
    }
  }


  public function get_data_state( $name)
  {
    if ($this->id) {
      $this->get($name, 'bbn-data-state');
    }
  }


  public function has_data_state($name)
  {
    if ($this->id) {
      return $this->get($name, 'bbn-data-state') ? true : false;
    }

    return false;
  }


  public function is_data_state($name, $data)
  {
    if ($this->id) {
      return $this->get($name, 'bbn-data-state') === md5(serialize($data));
    }

    return false;
  }


  protected function open()
  {
    if (!$this->was_opened && !$this->is_opened()) {
      if (!$this->once_opened) {
        $this->once_opened = true;

        if (defined('BBN_SESS_LIFETIME')) {
          ini_set('session.gc_maxlifetime', BBN_SESS_LIFETIME);
        }
      }

      session_start();
    }

    return $this;
  }


  protected function close()
  {
    if (!$this->was_opened && session_id()) {
      session_write_close();
    }

    return $this;
  }


  /**
   * Gets a reference to the part of the data corresponding to an array of indexes
   *
   * ```php
   * $this->_get_value(['index1', 'index2'])
   * // Will return the content of $this->data['index1']['index2']
   * ```
   * @param $args
   * @return null
   */
  private function _get_value($args)
  {
    if ($this->id) {
      $var =& $this->data;
      foreach ($args as $a){
        if (!isset($var[$a])) {
          return null;
        }

        $var =& $var[$a];
      }

      return $var;
    }
  }


  private function _set_value($args)
  {
    if ($this->id) {
      // The value is the first argument
      $value = array_shift($args);
      // Except if it's an array and there is only one argument
      if (!count($args) && \is_array($value) && bbn\x::is_assoc($value)) {
        $this->data = bbn\x::merge_arrays($this->data, $value);
      }
      else{
        $var =& $this->data;
        foreach ($args as $i => $a){
          if ($i === (\count($args) - 1)) {
            if (\is_null($value)) {
              unset($var[$a]);
            }
            else{
              $var[$a] = $value;
            }
          }
          else{
            $var =& $var[$a];
          }
        }
      }
    }

    return $this;
  }


}
/*
$sess = new bbn\user\session();
$sess->set("value of \$_SESSION[BBN_APP_NAME][foo][bar1]", "foo", "bar1");
$sess->set("value of \$_SESSION[BBN_APP_NAME][foo][bar2]", "foo", "bar2");
$sess->set(10, "myProp");
$sess->set(10, "myProp2");
$sess->uset("myProp2");
$sess->transform(function($a){return $a+1;}, "myProp");
bbn\x::hdump($sess->get("myProp"), $sess->get("hhhh"));
*/
