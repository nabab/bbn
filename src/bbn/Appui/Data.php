<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 02/06/2016
 * Time: 03:55
 */

namespace bbn\Appui;

use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Cache;


class Data extends DbCls
{
  use Cache;
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
    return $this->defDisplay($table, $where);
  }

  public function defDisplay($table, array $where){

  }
}