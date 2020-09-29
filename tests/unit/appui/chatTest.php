<?php
namespace bbn\tests\appui;

use bbn\appui\chat;

class chatTest extends PHPUnit\Framework\TestCase {

  private $obj;

  protected function setUp()
  {
    $db = new bbn\db();
    $user = new bbn\user($db);
    $this->obj = new chat($db, $user);
  }

}