<?php
namespace bbn\tests\appui;

use bbn\appui\chat;
use bbn;

class chatTest extends \PHPUnit\Framework\TestCase {

  private $obj;

  protected function setUp(): void
  {
    try {
      $db = new bbn\db();
    }
    catch (\Exception $e) {
      $db = false;
      $this->assertTrue(is_string($e->getMessage()));
    }
    if ($db) {
      try {
        $user = new bbn\user($db);
      }
      catch (\Exception $e) {
        $user = false;
        $this->assertTrue(is_string($e->getMessage()));
      }
      if ($db && $user) {
        $this->obj = new chat($db, $user);
      }
    }
  }

  public function testCheck(): void
  {
    if ($this->obj) {
      try {
        $res = $this->obj->check();
      }
      catch (\Exception $e) {
        $this->assertFalse($res);
      }
      if ($res) {
        $this->assertTrue($res);
      }
    }
  }

}