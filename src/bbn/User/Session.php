<?php
/**
 * @package user
 */
namespace bbn\User;

use Exception;
use bbn\X;
use bbn\Models\Tts\Singleton;


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

class Session
{
  use Singleton;

  /** @var string */
  protected static $name = BBN_APP_NAME;

  protected $was_opened = false;

  protected $once_opened = false;

  protected $data;

  protected $id;


  public function __construct(array|null $defaults = null)
  {
    if (self::singletonExists()) {
      throw new Exception("Impossible to create a new session, one already exists");
    }
    self::singletonInit($this);

    if ($id = session_id()) {
      $this->was_opened = true;
      $this->once_opened = true;
    }
    else {
      $this->open();
      $id = session_id();
    }

    if (!$id) {
      $save_path = session_save_path();
      if (!is_dir($save_path)) {
        throw new Exception(X::_("The session path %s doesn't exist", $save_path));
      }
      elseif (!is_writable($save_path)) {
        throw new Exception(X::_("The session path %s is not writable", $save_path));
      }
      else {
        throw new Exception(X::_("Impossible to retrieve the session's ID in %s", $save_path));
      }
    }

    $this->id = $id;
    if (!isset($_SESSION[self::$name])) {
      $_SESSION[self::$name] = \is_array($defaults) ? $defaults : [];
    }

    $this->data = $_SESSION[self::$name];
    $this->close();
  }

  public function regenerate(): ?string
  {
    if (!X::isCli()) {
      $this->open();
      $oldSession = session_id();
      // Create new session without destroying the old one
      if (session_regenerate_id(false)) {
        // Grab current session ID and close both sessions to allow other scripts to use them
        $newSession = session_id();
        if (!$newSession || ($newSession === $oldSession)) {
          throw new Exception(X::_("Impossible to regenerate the session ID"));
        }

        session_write_close();

        // Set session ID to the new one, and start it back up again
        session_id($newSession);
        session_start();

        // Don't want this one to expire
        if (session_id() !== $newSession) {
          throw new Exception(X::_("The session ID %s could not be regenerated, still using %s", $newSession, session_id()));
        }
      }
      else {
        throw new Exception(X::_("The session ID %s could not be regenerated", session_id()));
      }

      $this->close();
      return $newSession;
    }

    return null;
  }

  public static function destroyInstance()
  {
    if (self::singletonExists()) {
      self::$singleton_instance = null;
      self::$singleton_exists   = false;
    }
  }

  public function isOpened(): bool
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
  public function getId()
  {
    return $this->id;
  }


  public function setDataState($name, $data)
  {
    if ($this->id) {
      $this->set(md5(serialize($data)), $name, 'bbn-data-state');
    }
  }


  public function getDataState( $name)
  {
    if ($this->id) {
      $this->get($name, 'bbn-data-state');
    }
  }


  public function hasDataState($name)
  {
    if ($this->id) {
      return $this->get($name, 'bbn-data-state') ? true : false;
    }

    return false;
  }


  public function isDataState($name, $data)
  {
    if ($this->id) {
      return $this->get($name, 'bbn-data-state') === md5(serialize($data));
    }

    return false;
  }


  protected function open()
  {
    if (!$this->was_opened && !$this->isOpened()) {
      if (!$this->once_opened) {
        $this->once_opened = true;

        if (defined('BBN_SESS_LIFETIME')) {
          ini_set('session.gc_maxlifetime', constant('BBN_SESS_LIFETIME'));
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
      if (!count($args) && \is_array($value)) {
        // If the array is empty then the intention is to delete session data.
        if (empty($value)) {
          $this->data = [];

        } elseif (X::isAssoc($value)) {
          $this->data = X::mergeArrays($this->data, $value);
        }
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
$sess = new bbn\User\Session();
$sess->set("value of \$_SESSION[BBN_APP_NAME][foo][bar1]", "foo", "bar1");
$sess->set("value of \$_SESSION[BBN_APP_NAME][foo][bar2]", "foo", "bar2");
$sess->set(10, "myProp");
$sess->set(10, "myProp2");
$sess->uset("myProp2");
$sess->transform(function($a){return $a+1;}, "myProp");
X::hdump($sess->get("myProp"), $sess->get("hhhh"));
*/
