<?php

namespace tests;

trait Mockable
{


  /**
   *
   * Mock a class then set expectations of a method $method to be called $times times
   * Then return the mock instance.
   *
   * @param string $class
   * @param string $method
   * @param $return_value
   * @param string $times
   * @return \Mockery\MockInterface
   */
  protected function mockClassMethod
  (
    string $class,
    string $method,
    $return_value,
    string $times = 'once'
  )
  {
    $mockery = \Mockery::mock($class);
    $mockery->shouldReceive($method)->andReturn($return_value)->{$times}();

    return $mockery;
  }


}
