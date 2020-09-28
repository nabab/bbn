<?php

use bbn\x;

class xTest extends PHPUnit\Framework\TestCase {

  public function testCount()
  {
    $this->assertEquals(0, x::count('t'));
    x::increment('t');
    $this->assertEquals(1, x::count('t'));
    x::increment('t');
    x::increment('t');
    $this->assertEquals(3, x::count('t'));
    x::decrement('t');
    $this->assertEquals(2, x::count('t'));

    $this->assertEquals(0, x::count());
    x::increment();
    $this->assertEquals(1, x::count());
    x::increment();
    x::increment();
    $this->assertEquals(3, x::count());
    x::decrement();
    $this->assertEquals(2, x::count());
  }
}