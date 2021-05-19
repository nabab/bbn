<?php

namespace tests;

trait Mockable
{


    /**
     * @param string $class
     * @param string $method
     * @param        $value
     *
     * @return \Mockery\MockInterface
     */
  protected function mockClassMethod(string $class, string $method, $value)
  {
      return ReflectionHelpers::mockClassMethod(
        $class,
        $method,
        $value
      );
  }


}
