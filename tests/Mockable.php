<?php

namespace tests;

trait Mockable
{


  /**
   * @param string $class
   * @param string $method
   * @param $value
   * @param string $times
   * @return \Mockery\MockInterface
   */
  protected function mockClassMethod(string $class, string $method, $value, string $times = 'once')
  {
      return ReflectionHelpers::mockClassMethod(
        $class,
        $method,
        $value,
        $times
      );
  }


}
