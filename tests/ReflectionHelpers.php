<?php

namespace tests;

class ReflectionHelpers
{
    /**
     * @param string      $name
     * @param object|null $object $object
     *
     * @return mixed|\ReflectionProperty
     * @throws \ReflectionException
     */
  public static function getNonPublicProperty(string $name, ?object $object)
  {
      $reflectionClass = new \ReflectionClass($object);
      $property        = $reflectionClass->getProperty($name);
      $property->setAccessible(true);

      return $property->getValue($object);
  }


    /**
     * @param string      $name
     * @param object|null $object
     * @param             $value
     *
     * @throws \ReflectionException
     */
  public static function setNonPublicPropertyValue(string $name, ?object $object, $value)
  {
        $reflectionClass = new \ReflectionClass($object);
        $property        = $reflectionClass->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
  }


    /**
     * @param string $name
     * @param object $object
     *
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
  public static function getNonPublicMethod(string $name, object $object)
  {
      $reflectionClass = new \ReflectionClass($object);
      $method          = $reflectionClass->getMethod($name);
      $method->setAccessible(true);

      return $method;
  }


    /**
     * @param string $class
     * @param string $method
     * @param        $value
     *
     * @return \Mockery\MockInterface
     */
  public static function mockClassMethod(string $class, string $method, $value)
  {
        $mockery = \Mockery::mock($class);
        $mockery->shouldReceive($method)->andReturn($value);

        return $mockery;
  }


}
