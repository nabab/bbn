<?php

namespace X;

use bbn\X;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class MYTest extends TestCase
{
  /** @test */
  public function test_if_true_equals_to_false()
  {
    $this->assertSame(true, false);
  }
}