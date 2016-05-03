<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
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
if ( !defined('BBN_FINGERPRINT') ) {
  die('define BBN_FINGERPRINT');
}
if ( !defined('BBN_SESS_NAME') ) {
  die('define BBN_SESS_NAME');
}
if ( !defined('BBN_SESS_LIFETIME') ) {
  die('define BBN_SESS_LIFETIME');
}
ini_set('session.gc_maxlifetime', BBN_SESS_LIFETIME);

class session
{

  private static
    /** @var string */
    $fingerprint = BBN_FINGERPRINT,
    /** session */
    $inst,
    $exist = false;

  protected
    $data,
    $id;

  private static function exists(){
    return self::$exist;
  }

  private static function init(session $inst){
    if ( !self::exists() ){
      self::$exist = 1;
      self::$inst = $inst;
    }
  }

  public static function get_current(){
    if ( self::exists() ){
      return self::$inst;
    }
  }

  public function __construct(array $defaults = null){
    if ( !self::exists() ){
      self::init($this);
      $this->open();
      if ( !isset($_SESSION[BBN_SESS_NAME]) ){
        $_SESSION[BBN_SESS_NAME] = is_array($defaults) ? $defaults : [];
      }
      $this->data = $_SESSION[BBN_SESS_NAME];
      $this->id = session_id();
      $this->close();
    }
  }

  protected function open(){
    if ( session_status() == PHP_SESSION_NONE ){
      session_start();
    }
    return $this;
  }

  protected function close(){
    if ( session_id() != '' ){
      session_write_close();
    }
    return $this;
  }

  public function get(){
    if ( $this->id ){
      $var = $this->data;
      $args = func_get_args();
      foreach ( $args as $a ){
        if ( !isset($var[$a]) ){
          return null;
        }
        $var = $var[$a];
      }
      return $var;
    }
  }

  public function fetch($arg){
    if ( $this->id ){
      $this->open();
      $this->data = $_SESSION[BBN_SESS_NAME];
      $r = call_user_func_array([$this, 'get'], func_get_args());
      $this->close();
      return $r;
    }
  }

  public function has(){
    return !is_null(call_user_func_array([$this, 'get'], func_get_args()));
  }

  public function set($val){
    if ( $this->id ){
      $args = func_get_args();
      array_shift($args);
      $this->open();
      $var =& $_SESSION[BBN_SESS_NAME];
      $var2 =& $var;
      foreach ( $args as $i => $a ){
        if ( !is_array($var) ){
          $var = [];
        }
        if ( !isset($var[$a]) ){
          if ( count($args) >= $i ){
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
      $var = $val;
      $this->data = $_SESSION[BBN_SESS_NAME];
      $this->close();
      return $this;
    }
  }

  public function destroy(){
    if ( $this->id ){
      $this->open();
      $args = func_get_args();
      $var =& $_SESSION[BBN_SESS_NAME];
      $var2 =& $var;
      foreach ( $args as $i => $a ){
        if ( !is_array($var) ){
          $var = [];
        }
        if ( !isset($var[$a]) ){
          if ( count($args) >= $i ){
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
      $var = null;
      $this->data = isset($_SESSION[BBN_SESS_NAME]) ? $_SESSION[BBN_SESS_NAME] : [];
      $this->close();
      return $this;
    }
  }

  /**
   * Executes a function on the session or a part of the session
   * @param function $func
   * @return session
   */
  public function work(callable $func){
    if ( $this->id ){
      $args = func_get_args();
      array_shift($args);
      $this->open();
      foreach ( $args as $a ){
        if ( !isset($_SESSION[BBN_SESS_NAME][$a]) ){
          return false;
        }
        $var =& $_SESSION[BBN_SESS_NAME][$a];
      }
      $r = call_user_func_array([$this, 'get'], $args);
      $func($var);
      $this->data = $_SESSION[BBN_SESS_NAME];
      $this->close();
      return $this;
    }
  }

  /**
   * Executes a function on the session or a part of the session
   * @param function $func
   * @return session
   */
  public function get_id(){
    return $this->id;
  }

  public function set_data_state($name, $data){
    if ( $this->id ){
      $this->set(md5(serialize($data)), $name, 'appui-data-state');
    }
  }

  public function get_data_state( $name){
    if ( $this->id ){
      $this->get($name, 'appui-data-state');
    }
  }

  public function has_data_state($name){
    if ( $this->id ){
      return $this->get($name, 'appui-data-state') ? true : false;
    }
    return false;
  }

  public function is_data_state($name, $data){
    if ( $this->id ){
      return $this->get($name, 'appui-data-state') === md5(serialize($data));
    }
    return false;
  }
}