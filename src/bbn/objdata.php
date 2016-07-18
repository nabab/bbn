<?php
/**
 * @package bbn
 */
namespace bbn;
/**
 * Data Class with Db and cache
 *
 *
 * All change of properties will be sunced with the database
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since July 15, 2016, 14:10:55 +0000
 * @category  Data Observer
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.1
 */
class objdata extends objcache{

  private static
    $_bbn_reflection_class = false,
    $_bbn_protected_properties,
    $_bbn_primary;

  private static function _init($obj, db $db){
    self::$_bbn_reflection_class = new ReflectionObject($obj);
    self::$_bbn_protected_properties = array_map(function($a){
      return $a->getName();
    }, self::$_bbn_reflection_class->getProperties(ReflectionProperty::IS_PROTECTED));
    self::$_bbn_primary = "$primary";
  }

  public function __construct($schema, db $db, $uid){
    parent::__construct($db);
    $this->$primary = $uid;
    if ( $this->db->count('$table', ['$primary' => $uid]) ){
      if ( !self::$_bbn_reflection_class ){
        self::_init($this, $this->db);
      }
      $this->_bbn_ok = 1;
    }
  }

  public function __get($name){
    if ( $this->is_ok() ){
      if ( in_array($name, self::$_bbn_protected_properties) && ($name !== 'db') ){
        if ( method_exists($this, 'get_'.$name) ){
          return $this->{'get_'.$name}();
        }
      }
    }
  }

  public function __set($name, $value){
    if ( $this->is_ok() ){
      if ( in_array($name, self::$_bbn_protected_properties) && ($name !== 'db') ){
        if ( method_exists($this, 'set_'.$name) ){
          $this->{'set_'.$name}($value);
        }
      }
    }
  }

  public function __unset($name){
    if ( $this->is_ok() ){
      if ( in_array($name, self::$_bbn_protected_properties) && ($name !== 'db') ){
        if ( method_exists($this, 'unset_'.$name) ){
          $this->{'unset_'.$name}();
        }
      }
    }
  }

  public function is_ok(){
    return $this->_bbn_ok;
  }

  protected function update($change, $val = ''){
    if ( $this->is_ok() ){
      if ( !is_array($change) ){
        $change = [$change => $val];
      }
      return $this->db->update('$table', $change, ['$primary' => $this->$primary]);
    }
    return false;
  }
  
}