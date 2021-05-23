<?php

namespace tests;

class ReflectionHelpers
{


    /**
     * Get the value of non public properties in an object.
     *
     * @param string $name
     * @param object $object $object
     *
     * @return mixed
     * @throws \ReflectionException
     */
  public static function getNonPublicProperty(string $name, object $object)
  {
      $reflectionClass = new \ReflectionClass($object);
      $property        = $reflectionClass->getProperty($name);
      $property->setAccessible(true);

      return $property->getValue($object);
  }


    /**
     * Set the value of non public properties in an object.
     * And Convert it to be accessible.
     *
     * @param string $name
     * @param object $object
     * @param        $value
     *
     * @throws \ReflectionException
     */
  public static function setNonPublicPropertyValue(string $name, object $object, $value)
  {
        $reflectionClass = new \ReflectionClass($object);
        $property        = $reflectionClass->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
  }


    /**
     *
     * Convert a non public method to be accessible in an object and return a ReflectionMethod.
     *
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


}
