<?php

namespace tests;

trait Mockable
{


  /**
   * @param string $class
   * @param string $method
   * @param $return_value
   * @param string $times
   * @return \Mockery\MockInterface
   */
  protected function mockClassMethod(string $class, string $method, $return_value, string $times = 'once')
  {
      return ReflectionHelpers::mockClassMethod(
        $class,
        $method,
        $return_value,
        $times
      );
  }


}
