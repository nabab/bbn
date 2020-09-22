<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 01/11/2016
 * Time: 17:41
 */
namespace bbn\models\tts;

/**
 * Gives static props and methods to register an instance of an object and be able to retrieve the last registered one.
 */
trait retriever
{
  /**
   * @var self An instance of the current class.
   */
  protected static $retriever_instance;

  /**
   * @var bool Will be true from the moment an instance exists.
   */
  protected static $retriever_exists = false;

  /**
   * Initialize the retriever by putting its own instance as static property.
   *
   * @param self $instance The instance object.
   * @return void
   */
  protected static function retriever_init(self $instance): void
  {
    self::$retriever_exists = true;
    self::$retriever_instance = $instance;
  }

  /**
   * Returns the instance of the singleton or null.
   * 
   * @return self
   */
  public static function get_instance(): ?self
  {
    return self::$retriever_instance;
  }

  /**
   * Returns true if an instance as been initiated.
   *
   * @return bool
   */
  public static function retriever_exists(): bool
  {
    return self::$retriever_exists;
  }

  /**
   * Constructor.
   */
  public function __construct()
  {
    self::retriever_init($this);
  }

}