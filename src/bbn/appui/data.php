<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 02/06/2016
 * Time: 03:55
 */

namespace bbn\appui;


class data extends \bbn\objcache
{
  protected static $registry = [];

  public static function register($table, $fn, $variant = 'default'){
    if ( !isset(self::$registry[$table]) ){
      self::$registry[$table] = [];
    }
    self::$registry[$table][$variant] = $fn;
  }

  public function display($table, array $where, $variant = 'default'){

    if ( isset(self::$registry[$table][$variant]) ){
      $fn = self::$registry[$table][$variant];
      return $fn();
    }
    return def_display($table, $where);
  }

  public function def_display($table, array $where){

  }
}