<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 29/06/2016
 * Time: 18:23
 */

namespace bbn\user;


class retriever
{
  public static function get_user_name($id){
    if ( $user = \bbn\user\connection::get_user() ){
      $mgr = $user->get_manager();
      return $mgr->get_name($id);
    }
    return false;
  }
}