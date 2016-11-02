<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 01/11/2016
 * Time: 17:57
 */

namespace bbn\models\tts;


trait singleton
{
  protected static
    $singleton_instance,
    $singleton_exists;

  protected static function singleton_init($instance){
    if ( self::singleton_exists($instance) ){
      self::$singleton_exists = 1;
      self::$singleton_instance = $instance;
    }
  }

  public static function get_instance(){
    return self::singleton_exists() ? self::$singleton_instance : false;
  }

  public static function singleton_exists(){
    return self::$singleton_exists ? true : false;
  }


}