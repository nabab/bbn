<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:33
 */

namespace bbn\mvc;


interface api {

  function reroute($path='', $check = 1);

  function get_model($path, array $data=null);

  function get_view($path, $data=[], $mode);

  function is_cli();

}