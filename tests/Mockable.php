<?php

namespace tests;

trait Mockable
{


  /**
   *
   * Mock a class then set expectations of a method $method to be called $times times
   * Then return the mock instance.
   *
   * @param string  $class
   * @param mixed  $arg Callback or the value
   * @param null   $return
   * @return \Mockery\MockInterface
   */
  protected function mockClassMethod(string $class, $arg, $return = null)
  {
    $mockery = \Mockery::mock($class);

    if (is_callable($arg)) {
      $arg($mockery);
    } else {
      $mockery->shouldReceive($arg)->andReturn($return);
    }

    return $mockery;
  }


}
