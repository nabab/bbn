<?php
namespace bbn\Cron;

use Exception;
use bbn\Str;
use bbn\X;
use bbn\Db;
use bbn\Cron;
use bbn\Util\Timer;
use bbn\Mvc\Controller;
use bbn\Net\Websocket;
use bbn\Appui\Observer;
use bbn\Models\Cls\Basic;
use function count;
use function defined;
use function call_user_func;
use function is_array;
use function in_array;
use function is_bool;
use function is_string;
use function array_key_exists;

//php -f router.php cron/run "{\"type\":\"poll\",\"exe_path\":\"cron\\/run\",\"log_file\":\"\\/app\\/data\\/plugins\\/appui-cron\\/log\\/poll\\/2026\\/08\\/24\\/15\\/".date('Y-m-d-H-i-s').".txt\"}"

/**
 * Cron runner.
 * This class runs the jobs properly. It has three modalities:
 * - `poll` will run the poller, continuously
 * - `run_task_system` will run the task system, continuously
 * - `run_task` will run a given task, once
 */
class Poller extends Basic
{

  use Config;
  use Filesystem;

  protected Controller $controller;

  protected Db $db;
  /**
   * Timer
   *
   * @var Timer
   */
  protected Timer $timer;

  /**
   * @var Cron
   */
  protected Cron $cron;

  /**
   * @var string|null
   */
  protected ?string $log_file;

  /**
   * @var string
   */
  protected string $type;

  /**
   * Runner constructor.
   *
   * @param Cron $cron
   * @param array $cfg
   */
  public function __construct(Cron $cron, array $cfg)
  {
    //if ( defined('BBN_DATA_PATH') ){
    if ($cron->check()) {
      $this->controller = $cron->getController();
      $this->cron = $cron;
      $this->log_file = $cron->getLogFile();
      $this->db = $this->controller->db;
      // It must be called from a plugin (appui-cron actually)
      //$this->path = BBN_DATA_PATH.'plugins/appui-cron/';
      $this->path = $this->controller->dataPath('appui-cron');
      $this->data = $cfg;
      $this->timer = new Timer();
    }
  }

  /**
   * Returns the $data property.
   *
   * @param Observer|null $observer
   * @return void
   */
  public function poll(?Observer $observer = null)
  {
    X::log('inside poll function from poller', 'poller');
    if ($this->check()) {
      X::log('inside poll function: checked', 'poller');
      while ($this->isPollActive()) {
        sleep(1);
      }
    }
  }

  private function isTestingEnvironment(): bool
  {
    return !defined('BBN_IS_PROD') || (BBN_IS_PROD === false);
  }
}
