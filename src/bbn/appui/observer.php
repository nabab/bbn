<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/06/2016
 * Time: 15:32
 */

namespace bbn\appui;


class observer extends \bbn\objcache
{
  private static
    $observers = [],
    $sess_index = 'appui_observer';


  public function __construct(\bbn\db $db)
  {
    if ( $this->sess = \bbn\user\session::get_current() ){
      parent::__construct($db);
    }
  }

  public function register($value, string $name, string $group = null){
    if ( $this->has_session() && !empty($name) ){
      $hash = \bbn\cache::make_hash($value);
      if ( $group ){
        $this->sess->set($hash, self::$sess_index, $group, $name);
      }
      else{
        $this->sess->set($hash, self::$sess_index, $name);
      }
    }
    return $this;
  }

  public function has_session(){
    return $this->sess ? true : false;
  }

  public function get_all(string $group = null, $include_sub = false){
    if ( $this->has_session() ){
      if ( $group ){
        return $this->sess->get(self::$sess_index, $group);
      }
      if ( $include_sub ){
        return $this->sess->get(self::$sess_index);
      }
      return array_filter($this->sess->get(self::$sess_index), function($a){
        return !is_array($a);
      });
    }
  }

  public function get(string $name, string $group = null){
    if ( $this->has_session() && !empty($name) ){
      if ( $group ){
        return $this->sess->get(self::$sess_index, $group, $name);
      }
      return $this->sess->get(self::$sess_index, $name);
    }
    return false;
  }

  public function is_changed(string $name, string $group = null){

  }
}