<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:33
 */

namespace bbn\Mvc;


interface Api {

  function reroute($path='', $post = false, $arguments = false);

  function isCli();

}