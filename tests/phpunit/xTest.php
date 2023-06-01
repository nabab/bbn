<?php
namespace bbn\bbn;

use bbn\X;

class xTest extends \PHPUnit\Framework\TestCase {

  public function testCount()
  {
    $this->assertEquals(0, X::count('t'));
    X::increment('t');
    $this->assertEquals(1, X::count('t'));
    X::increment('t');
    X::increment('t');
    $this->assertEquals(3, X::count('t'));
    X::decrement('t');
    $this->assertEquals(2, X::count('t'));

    $this->assertEquals(0, X::count());
    X::increment();
    $this->assertEquals(1, X::count());
    X::increment();
    X::increment();
    $this->assertEquals(3, X::count());
    X::decrement();
    $this->assertEquals(2, X::count());
  }
}