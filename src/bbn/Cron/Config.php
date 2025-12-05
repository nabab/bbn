<?php
/**
 * User: BBN
 * Date: 04/01/2020
 * Time: 15:17
 */

namespace bbn\Cron;

use bbn\Str;
use bbn\Mvc;
use bbn\Db;
use bbn\File\System;
use bbn\Mvc\Controller;
use function count;

trait Config {
  /**
   * @var string The tables' prefix (the tables will be called ?cron and ?journal)
   */
  private $prefix = 'bbn_';
  /**
   * @var string The full path to the plugin data folder where the actions and log files are/will be located
   */
  private $path;

  /**
   * @var Db The DB connection
   */
  protected $db;
  /**
   * @var Controller The controller
   */
  protected $ctrl;
  /**
   * @todo The class shouldn't send emails directly
   * @var string
   */
  protected $mail;
  /**
   * @var array This corresponds to the post property from $ctrl
   */
  protected $data;
  /**
   * @var string
   */
  protected $enabled = true;
  /**
   * @var string
   */
  protected $timeout = 50;

  protected static $cron_timeout = 300;

  protected static $poll_timeout = 600;

  protected static $user_timeout = 480;

  protected static $cron_check_timeout = 60;

  /**
   * @return int
   */
  public static function getCronTimeout(): int
  {
    return self::$cron_timeout;
  }

  /**
   * @param int $cron_timeout
   */
  public static function setCronTimeout(int $cron_timeout): void
  {
    self::$cron_timeout = $cron_timeout;
  }

  /**
   * @return int
   */
  public static function getPollTimeout(): int
  {
    return self::$poll_timeout;
  }

  /**
   * @param int $poll_timeout
   */
  public static function setPollTimeout(int $poll_timeout): void
  {
    self::$poll_timeout = $poll_timeout;
  }

  /**
   * @return int
   */
  public static function getUserTimeout(): int
  {
    return self::$user_timeout;
  }

  /**
   * @param int $user_timeout
   */
  public static function setUserTimeout(int $user_timeout): void
  {
    self::$user_timeout = $user_timeout;
  }


  /**
   * @param array $cfg
   */
  public function init(array $cfg = [])
  {
    $this->path = $cfg['data_path'] ?? Mvc::getDataPath('appui-cron');
  }

  /**
   * Returns the $path property.
   *
   * @return array|null
   */
  public function getPath(): ?string
  {
    return $this->path;
  }
}

