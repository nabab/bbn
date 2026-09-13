<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:33
 */

namespace bbn\Mvc;


interface Api {

  function reroute(string $path = '', ?array $post = null, ?array $arguments = null);

  function isCli();

}