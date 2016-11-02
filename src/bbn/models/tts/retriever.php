<?php
namespace bbn\models\tts;
use bbn;

/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 01/11/2016
 * Time: 17:41
 */
trait retriever
{
  protected static
    $retriever_instance,
    $retriever_exists;

  protected static function retriever_init($instance){
    self::$retriever_exists = 1;
    self::$retriever_instance = $instance;
  }

  /**
   * @return $this
   */
  public static function get_instance(){
    return self::$retriever_instance;
  }

  public static function retriever_exists(){
    return self::$retriever_exists ? true : false;
  }

  public function __construct(){
    self::retriever_init($this);
  }

}