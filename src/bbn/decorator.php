<?php
/**
 * @package bbn
 */
namespace bbn;
/**
 * Basic object Class
 *
 *
 * This class implements basic functions and vars
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @link http://stackoverflow.com/questions/3011910/how-to-add-a-method-to-an-existing-class-in-php Comes from there...
 * @copyright BBN Solutions
 * @since Apr 24, 2013, 23:23:55 +0000
 * @category  Generic classes
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.1
 */
class decorator
{
  protected $_instance;

  public function __construct($classname, $instance) {
    
    if ( is_object($classname) && strpos($classname, '\\') === 0 ){
      $classname = substr($classname, 1);
      if ( $classname === get_class($this) ){
        $this->_instance = $instance;
      }
    }
  }

  public function __call($method, $args) {
    if ( $this->_instance ){
      return call_user_func_array(array($this->_instance, $method), $args);
    }
  }

  public function __get($key) {
    if ( $this->_instance ){
      return $this->_instance->$key;
    }
  }

  public function __set($key, $val) {
    if ( $this->_instance ){
      return $this->_instance->$key = $val;
    }
  }

  // can implement additional (magic) methods here ...}
}
?>