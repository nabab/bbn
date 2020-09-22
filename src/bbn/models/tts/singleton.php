<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 01/11/2016
 * Time: 17:57
 */

namespace bbn\models\tts;


/**
 * Gives static props and methods to register an instance of an object and be able to retrieve the last registered one.
 */
trait singleton
{
  /**
   * @var self An instance of the current class.
   */
  protected static $singleton_instance;

  /**
   * @var bool Will be true from the moment the instance exists.
   */
  protected static $singleton_exists = false;

  /**
   * Initialize the singleton by putting its own instance as static property.
   *
   * @param self $instance The instance object.
   * @return void
   */
  protected static function singleton_init(self $instance)
  {
    if (self::singleton_exists()) {
      throw new \Exception(_("Impossible to create a new instance of").' '.\get_class($instance));
    }
    self::$singleton_exists = 1;
    self::$singleton_instance = $instance;
  }

  /**
   * Returns the instance of the singleton or null.
   * 
   * @return self
   */
  public static function get_instance(): ?self
  {
    return self::singleton_exists() ? self::$singleton_instance : null;
  }

  /**
   * Returns true if the instance as been initiated.
   *
   * @return bool
   */
  public static function singleton_exists(): bool
  {
    return self::$singleton_exists ? true : false;
  }

}
