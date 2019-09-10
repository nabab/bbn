<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

namespace bbn\appui;
use bbn;

if ( !\defined('BBN_DATA_PATH') ){
  die("The constant BBN_DATA_PATH must be defined in order to use note");
}

class clipboard extends bbn\models\cls\basic
{

  private $user;
  private $root;

  public function __construct(bbn\user $user){
    $this->user = $user;
  }

  public function check(): bool
  {
    return $this->user->check_session();
  }

  public function add($element): ?float
  {
    $id = microtime(true);

  }

  public function remove(float $tst): bool
  {

  }


}
