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
}
