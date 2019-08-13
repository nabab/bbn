<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:33
 */

namespace bbn\mvc;


interface api {

  function reroute($path='', $post = false, $arguments = false);

  function is_cli();

}