<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 19:51
 */

namespace bbn\models\tts;

use bbn;

trait current
{
  /** @var string The current ID */
  private static $current;

  /**
   * @param string $current
   */
  private static function _set_current(string $current): void
  {
    self::$current = $current;
  }

  /**
   * @param string $id_option
   */
  public function set_current(string $id_option): void
  {
    self::_set_current($id_option);
  }

  /**
   * @return null|string
   */
  public function get_current(): ?string
  {
    return self::$current;
  }

}