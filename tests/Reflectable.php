<?php

namespace tests;

trait Reflectable
{


  abstract public function getInstance();


  /**
   * Set the value of non public properties in an object.
   * And Convert it to be accessible.
   *
   * @param string $name
   * @param mixed $value
   * @param null $object
   * @return void
   * @throws \ReflectionException
   */
  protected function setNonPublicPropertyValue(string $name, $value, $object = null)
  {
    ReflectionHelpers::setNonPublicPropertyValue($name, $object ?? $this->getInstance(), $value);
  }


  /**
   * Get the value of non public property in an object.
   *
   * @param string $name
   * @param null $object
   * @return mixed
   * @throws \ReflectionException
   */
  protected function getNonPublicProperty(string $name, $object = null)
  {
    return ReflectionHelpers::getNonPublicProperty($name, $object ?? $this->getInstance());
  }

  /**
   * Convert a non public method to be accessible in an object and return a ReflectionMethod.
   *
   * @param string $name
   * @param object|null $object
   * @return \ReflectionMethod
   * @throws \ReflectionException
   */
  protected function getNonPublicMethod(string $name, ?object $object = null)
  {
    return ReflectionHelpers::getNonPublicMethod($name, $object ?? $this->getInstance());
  }
}
