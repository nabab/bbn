<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 01/11/2017
 * Time: 22:48
 */

namespace bbn\Models\Tts;

use bbn;

trait Report
{
  private static $_error;
  private static $_last_error;
  private static $_debug;

  private static function _report_error($error, $class, $line){
    throw new \Exception(bbn\X::_($error));
  }
}