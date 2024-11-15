<?php
/**
 * @package bbn
 */
namespace bbn\Models\Cls;


/**
 * Nullall object Class
 *
 *
 * This class returns null to all
 *
 * Todo: create a new delegation generic function for the double underscores functions
 */
class Nullall
{
  /**
   * @param string $name
   * @param array  $arguments
   * @return void
   */
  public function __call($name, $arguments)
  {
    return null;
  }

  public function __get($name)
  {
    return null;
  }
}
