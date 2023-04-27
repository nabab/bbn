<?php

namespace tests;

class ReflectionHelpers
{


    /**
     * Get the value of non public properties in an object.
     *
     * @param string $name
     * @param mixed $object $object
     *
     * @return mixed
     * @throws \ReflectionException
     */
  public static function getNonPublicProperty(string $name, $object)
  {
      $reflectionClass = new \ReflectionClass($object);
      $property        = $reflectionClass->getProperty($name);
      //$property->setAccessible(true);

      return is_string($object) ? $property->getValue() : $property->getValue($object);
      //return $property->getValue($object);
  }


    /**
     * Set the value of non public properties in an object.
     * And Convert it to be accessible.
     *
     * @param string $name
     * @param object|string $object
     * @param        $value
     *
     * @throws \ReflectionException
     */
  public static function setNonPublicPropertyValue(string $name, $object, $value)
  {
        $reflectionClass = new \ReflectionClass($object);
        $property        = $reflectionClass->getProperty($name);
        //$property->setAccessible(true);
        $property->setValue($object, $value);
  }


    /**
     *
     * Convert a non public method to be accessible in an object and return a ReflectionMethod.
     *
     * @param string $name
     * @param mixed $object
     *
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
  public static function getNonPublicMethod(string $name, $object)
  {
      $reflectionClass = new \ReflectionClass($object);
      $method          = $reflectionClass->getMethod($name);
      $method->setAccessible(true);

      return $method;
  }

}
