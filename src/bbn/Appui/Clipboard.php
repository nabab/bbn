<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

namespace bbn\Appui;
use bbn;

if ( !\defined('BBN_DATA_PATH') ){
  die("The constant BBN_DATA_PATH must be defined in order to use Note");
}

class Clipboard extends bbn\Models\Cls\Basic
{

  private $user;
  private $root;

  public function __construct(bbn\User $user){
    $this->user = $user;
  }

  public function check(): bool
  {
    return $this->user->checkSession();
  }

  public function add($element): ?float
  {
    $id = microtime(true);

  }

  public function remove(float $tst): bool
  {

  }


}
