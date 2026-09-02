<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 01/11/2016
 * Time: 17:41
 */
namespace bbn\Models\Tts;

/**
 * Gives static props and methods to register an instance of an object and be able to retrieve the last registered one.
 */
trait DbRetriever
{
  public function getConnectionSignature(): String
  {
    return $this->db->getSignature();
  }

  /**
   * @var self An instance of the current class.
   */
  protected static $retriever_instance;
  protected static $retriever_instances = [];

  /**
   * Initialize the retriever by putting its own instance as static property.
   *
   * @param self $instance The instance object.
   * @return void
   */
  protected static function retrieverInit(self $instance): void
  {
    if (!self::$retriever_instance) {
      self::$retriever_instance = $instance;
    }

    $sign = $instance->getConnectionSignature();
    if (!isset(self::$retriever_instances[$sign])) {
      self::$retriever_instances[$sign] = $instance;
    }
  }


  /**
   * Removes the retriever.
   *
   * @param self $instance The instance object.
   * @return void
   */
  protected static function retrieverRemove(self $instance): void
  {
    if (self::$retriever_instance === $instance) {
      self::$retriever_instance = null;
    }

    $sign = $instance->getConnectionSignature();
    if (isset(self::$retriever_instances[$sign])) {
      unset(self::$retriever_instances[$sign]);
    }
  }

  /**
   * Returns the instance of the singleton or null.
   * 
   * @return self
   */
  public static function getInstance(?Db $db = null): ?self
  {
    if (!$db) {
      return self::$retriever_instance;
    }

    $sign = $db->getSignature();
    if (isset(self::$retriever_instances[$sign])) {
      return self::$retriever_instances[$sign];
    }

    return null;
  }

  /**
   * Returns true if an instance as been initiated.
   *
   * @return bool
   */
  public static function retrieverExists(?Db $db = null): bool
  {
    if (!$db) {
      return (bool)self::$retriever_instance;
    }

    $sign = $db->getSignature();
    return isset(self::$retriever_instances[$sign]);
  }

}
