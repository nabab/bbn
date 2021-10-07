<?php
namespace bbn\Cron;

use bbn;
use bbn\X;

/**
 * Cron runner.
 * This class runs the jobs properly. It has three modalities:
 * - `poll` will run the poller, continuously
 * - `run_task_system` will run the task system, continuously
 * - `run_task` will run a given task, once
 */
class Runner extends bbn\Models\Cls\Basic
{

  use Common;

  protected $controller;

  /**
   * Timer
   *
   * @var bbn\Util\Timer
   */
  protected $timer;

  /**
   * @var bbn\Cron
   */
  protected bbn\Cron $cron;

  /**
   * @var string|null
   */
  protected $log_file;

  /**
   * @var string
   */
  protected $type;

  /**
   * The script as executed by the CLI in which the real task will come executed.
   */
  private function _run(): void
  {
    // The DB and the controller exist
    if ($this->check() && isset($this->data['type'])) {
      if (defined('BBN_EXTERNAL_USER_ID') && class_exists('\\bbn\\Appui\\History')) {
        \bbn\Appui\History::setUser(BBN_EXTERNAL_USER_ID);
      }
      // only 2 types: poll or cron
      $type = $this->data['type'] === 'poll' ? 'poll' : 'cron';
      // Removing file cache
      clearstatcache();
      $pid_file = $this->getPidPath($this->data);

      // Checking for the presence of the manual files
      if (
        !$this->isActive() ||
        (($type === 'cron') && !$this->isCronActive()) ||
        (($type === 'poll') && !$this->isPollActive())
      ) {
        // Exiting the script if one is missing
        /*
        if ( is_file($pid) ){
           @unlink($pid);
        }
        */
        $message = "GETTING OUT of $type BECAUSE one of the manual files is missing";

        $this->log($message);

        if ($this->isTestingEnvironment()) {
          throw new \Exception($message);
        }

        exit($message);
      }
      // Loooking for a running PID
      if (is_file($pid_file)
        && ($file_content = file_get_contents($pid_file))
      ) {
        [$pid, $time] = explode('|', $file_content);
        // If the process file really exists the process is ongoing and it stops
        if (file_exists('/proc/' . $pid)) {
          $this->log("There is already a process running with PID " . $pid);
          // If it's currently running we exit
          //$this->output(X::_('Existing process'), $pid);
          exit();
        }
        else {
          // Otherwise we delete the PID file
          $this->log("DELETING FILEPID AS THE PROCESS IS DEAD " . $pid);
          $this->output(X::_('Dead process'), $pid);
          @unlink($pid_file);
        }
      }
      // We create the PID file corresponding to the current process
      if (!is_dir(dirname($pid_file))) {
        mkdir(dirname($pid_file), 0777, true);
      }
      if (file_put_contents($pid_file, BBN_PID . '|' . time())) {
        // Shutdown function, will be always executed, except if the server is stopped
        register_shutdown_function([$this, 'shutdown']);
        // And here we really do what we have to do
        // Poll case
        if ($type === 'poll') {
          $this->poll();
        }
        // Cron
        else if ($type === 'cron') {
          // Real task
          if (array_key_exists('id', $this->data)) {
            //$this->output(X::_('Launching'), $this->data['file']);
            $this->runTask($this->data);
          }
          // Or task system
          else {
            $this->runTaskSystem();
          }
        }
      }
    }
    if (!$this->isTestingEnvironment()) {
      exit();
    }
  }

  /**
   * Runner constructor.
   *
   * @param bbn\Cron $cron
   * @param array $cfg
   */
  public function __construct(bbn\Cron $cron, array $cfg)
  {
    //if ( defined('BBN_DATA_PATH') ){
    if (!empty($cfg['type']) && $cron->check()) {
      $this->controller = $cron->getController();
      $this->cron = $cron;
      $this->log_file = $cron->getLogFile();
      $this->db = $this->controller->db;
      // It must be called from a plugin (appui-cron actually)
      //$this->path = BBN_DATA_PATH.'plugins/appui-cron/';
      $this->path = $this->controller->dataPath('appui-cron');
      $this->data = $cfg;
      $this->type = $cfg['type'];
      $this->timer = new bbn\Util\Timer();
    }
  }

  /**
   * @param string|bool $name
   * @param mixed $log
   */
  public function output($name = '', $log = ''): void
  {
    $output = '';
    if ($name === false) {
      $output = '}' . PHP_EOL;
    }
    else if ($name === true) {
      $output = '{' . PHP_EOL;
    }
    else if ($name) {
      $is_number = bbn\Str::isNumber($log);
      $is_boolean = \is_bool($log);
      $is_string = \is_string($log);
      if (!$is_number && !$is_boolean && !$is_string) {
        $log = X::getDump($log);
      }
      else if ($is_boolean) {
        $log = $log ? 'true' : 'false';
      }
      $output = '  "' .
        bbn\Str::escapeDquotes($name) .
        '": ' . ($is_string ? '"' : '') .
        ($is_string ? bbn\Str::escapeDquotes($log) : $log) .
        ($is_string ? '"' : '') . ',' .
        PHP_EOL;
    }

    if (!empty($output)) {
      ob_start();
      echo $output;
      ob_end_flush();
    }
  }

  public function shutdown()
  {
    $data = $this->getData();
    $pid = $this->getPidPath($data);
    $file_content = @file_get_contents($pid);
    // Write the error log if an error is present
    if ($error = error_get_last()) {
      //$this->output(X::_('Error'), $error);
      $this->log([$data, $error]);
    }
    $ok = true;
    if (ob_get_length()) {
      $content = ob_end_flush();
      $this->output(X::_('Content'), $content);
    }
    // We check if there is a problem with the PID file (it's only debug it shouldn't be necessary)
    if ($file_content) {
      $pid_content = explode('|', $file_content);
      if ($pid_content[1] && ($pid_content[0] != BBN_PID)) {
        $this->output(X::_('Different processes'), $pid_content[0] . '/' . BBN_PID);
        $this->log(X::_('Different processes') . ': ' . $pid_content[0] . '/' . BBN_PID);
        $ok = false;
      }
    }
    if ($ok && isset($data['type'])) {
      // Removing PID file
      if (is_file($pid)) {
        @unlink($pid);
      }
      // And relaunching the continuous tasks if we are in the poller...
      if (
        ($data['type'] === 'poll')
      ) {
        $this->cron->launchPoll();
      }
      else if ($data['type'] === 'cron') {
        if (array_key_exists('id', $data) && bbn\Str::isUid($data['id'])) {
          $this->cron->getManager()->finish($data['id']);
        }
        else {
          $this->cron->launchTaskSystem();
        }
      }
      //X::dump("FROM SHUTDOWN", $data);
      // We output the ending time (as all output will be logged in the output file
      //$this->output(X::_('Shutdown'), Date('H:i:s'));
    }
  }

  /**
   * Returns the $data property.
   *
   * @return array|null
   */
  public function getData(): ?array
  {
    return $this->data;
  }

  /**
   * @return bool
   */
  public function check()
  {
    return (bool) $this->type;
  }

  public function run(): void
  {
    $this->_run();
  }

  /**
   * Returns the $data property.
   *
   * @param bbn\Appui\Observer|null $observer
   * @return void
   */
  public function poll(?bbn\Appui\Observer $observer = null)
  {
    if ($this->check()) {
      $this->timer->start('timeout');
      $this->timer->start('users');
      $this->timer->start('cron_check');
      $obs = $observer ?? new bbn\Appui\Observer($this->db);
      //$this->output(X::_('Starting poll'), Date('Y-m-d H:i:s'));
      /*
      foreach ( $admin->get_old_tokens() as $t ){
        $id_user = $admin->get_user_from_token($t['id']);
        @bbn\File\Dir::delete(BBN_DATA_PATH."users/$id_user/tmp/tokens/$t[id]", true);
        if ( $this->db->delete('bbn_users_tokens', ['id' => $t['id']]) ){
          echo '-';
        }
      }
      */
      while ($this->isPollActive()) {
        // The only centralized action are the observers
        $res = $obs->observe();
        if (\is_array($res)) {
          $time = time();
          foreach ($res as $id_user => $o) {
            $user = bbn\User::getInstance();
            $ucfg = $user->getClassCfg();
            $sessions = $this->db->selectAll($ucfg['tables']['sessions'], [
              $ucfg['arch']['sessions']['id'],
              $ucfg['arch']['sessions']['sess_id']
            ], [
              $ucfg['arch']['sessions']['id_user'] => $id_user,
              $ucfg['arch']['sessions']['opened'] => 1
            ]);
            foreach ($sessions as $sess) {
              $file = $this->controller->userDataPath($id_user, 'appui-core')."poller/queue/{$sess->id}/observer-$time.json";
              if (bbn\File\Dir::createPath(X::dirname($file))) {
                file_put_contents($file, Json_encode(['observers' => $o]));
              }
            }
          }
        }
        if (!$this->isTestingEnvironment()) {
          sleep(1);
        }
        if ($this->timer->measure('users') > self::$user_timeout) {
          echo '?';
          //$admin->clean_tokens();
          $this->timer->stop('users');
          $this->timer->start('users');
        }
        if ($this->timer->measure('timeout') > self::$poll_timeout) {
          //$this->output(X::_('Timeout'), Date('Y-m-d H:i:s'));
          echo '.';
        }
        if ($this->timer->measure('cron_check') > self::$cron_check_timeout) {
          $this->cron->getManager()->notifyFailed();
          $this->timer->stop('cron_check');
          $this->timer->start('cron_check');
        }
      }
      $this->output(X::_('Ending poll process'), date('Y-m-d H:i:s'));
    }
  }

  public function runTaskSystem()
  {
    if ($this->check()) {
      //$this->output(X::_('Start task system'), Date('Y-m-d H:i:s'));
      $this->timer->start('timeout');
      $ok = true;
      while ($ok) {
        if (!$this->isActive() || !$this->isCronActive()) {
          //$this->output(X::_('End'), Date('Y-m-d H:i:s'));
          if ($rows = $this->cron->getManager()->getRunningRows()) {
            foreach ($rows as $r) {
              if (file_exists('/proc/' . $r['pid'])) {
                exec('kill -9 ' . $r['pid']);
                //$this->output(X::_('Killing task'), $r['pid']);
              }
              $fpid = $this->getPidPath(['type' => 'cron', 'id' => $r['id']]);
              if (is_file($fpid)) {
                unlink($fpid);
                //$this->output(X::_('Deleting PID file'), $fpid);
              }
              $this->cron->getManager()->unsetPid($r['id']);
            }
          }
          if ($this->isTestingEnvironment()) {
            return;
          }
          exit();
        }
        if ($rows = $this->cron->getManager()->getNextRows(0)) {
          foreach ($rows as $r) {
            $param = [
              'type' => 'cron',
              'id' => $r['id'],
              'file' => $r['file']
            ];
            //$this->output(X::_('Launch'), Date('Y-m-d H:i:s'));
            //$this->output(X::_('Execution'), $param['file']);
            $this->cron->getLauncher()->launch($param);
          }
        }
        if (!$this->isTestingEnvironment()) {
          sleep(10);
        }
      }
    }
  }

  /**
   * @param array $cfg
   * @throws \Exception
   */
  public function runTask(array $cfg)
  {
    if (X::hasProps($cfg, ['id', 'file', 'log_file'], true) && $this->check()) {
      if (!defined('BBN_EXTERNAL_USER_ID') && defined('BBN_EXTERNAL_USER_EMAIL')) {
        define('BBN_EXTERNAL_USER_ID', $this->db->selectOne('bbn_users', 'id', ['email' => BBN_EXTERNAL_USER_EMAIL]));
      }
      if ($this->cron->getManager()->start($cfg['id'])) {
        $log = [
          'start' => date('Y-m-d H:i:s'),
          'file' => $cfg['file'],
          'pid' => getmypid()
        ];
        $day = date('d');
        $month = date('m');
        $bits = X::split($cfg['log_file'], '/');
        $path = X::join(array_slice($bits, -5), '/');
        $path_elements = array_splice($bits, -5, 3);
        //$path_bits = array_splice($bits, -5);
        //$path = X::join($path_bits, '/');
        $json_file = X::dirname(X::dirname(X::dirname($cfg['log_file']))).'/'.X::join($path_elements, '-').'.json';
        array_pop($path_elements);
        $month_file = X::dirname(X::dirname($json_file)).'/'.X::join($path_elements, '-').'.json';
        array_pop($path_elements);
        $year_file = X::dirname(X::dirname($month_file)).'/'.X::join($path_elements, '-').'.json';
        if (!is_file($json_file)) {
          $logs = [];
        }
        elseif ($logs = file_get_contents($json_file)) {
          try {
            $logs = json_decode($logs, true, 512, JSON_THROW_ON_ERROR);
          }
          catch (\Exception $e) {
            $logs = [];
          }
        }
        if (is_array($logs)) {
          $idx = count($logs);
        }
        $logs[] = $log;
        file_put_contents($json_file, Json_encode($logs, JSON_PRETTY_PRINT));
        $this->timer->start($cfg['file']);
        $this->controller->reroute($cfg['file']);
        $this->controller->process();
        $logs[$idx]['duration'] = $this->timer->stop($cfg['file']);
        $content = file_get_contents($cfg['log_file']);
        if (empty($content)) {
          unlink($cfg['log_file']);
          $logs[$idx]['content'] = false;
        }
        else {
          $logs[$idx]['content'] = $path;
        }

        $logs[$idx]['end'] = date('Y-m-d H:i:s');
        file_put_contents($json_file, Json_encode($logs, JSON_PRETTY_PRINT));

        $default_month_data = [
          'total' => 0,
          'content' => 0,
          'first' => $logs[$idx]['start'],
          'last' => null,
          'dates' => [],
          'duration' => 0,
          'duration_content' => 0
        ];

        if (!is_file($month_file)) {
          $mlogs = $default_month_data;
        }
        else {
          $mlogs = json_decode(file_get_contents($month_file), true) ?? $default_month_data;
        }
        $mlogs['total']++;
        $mlogs['duration'] += $logs[$idx]['duration'];
        if (!empty($content)) {
          $mlogs['content']++;
          $mlogs['duration_content'] += $logs[$idx]['duration'];
        }
        $mlogs['last'] = $logs[$idx]['start'];
        if (!in_array($day, $mlogs['dates'])) {
          $mlogs['dates'][] = $day;
        }
        file_put_contents($month_file, Json_encode($mlogs, JSON_PRETTY_PRINT));

        $default_year_data = [
          'total' => 0,
          'content' => 0,
          'first' => $logs[$idx]['start'],
          'last' => null,
          'month' => [],
          'duration' => 0,
          'duration_content' => 0
        ];;

        if (!is_file($year_file)) {
          $ylogs = $default_year_data;
        }
        else {
          $ylogs = json_decode(file_get_contents($year_file), true) ?? $default_year_data;
        }
        $ylogs['total']++;
        $ylogs['duration'] += $logs[$idx]['duration'];
        if (!empty($content)) {
          $ylogs['content']++;
          $ylogs['duration_content'] += $logs[$idx]['duration'];
        }
        $ylogs['last'] = $logs[$idx]['start'];
        if (!in_array($month, $ylogs['month'])) {
          $ylogs['month'][] = $month;
        }
        file_put_contents($year_file, Json_encode($ylogs, JSON_PRETTY_PRINT));
      }
    }

    if (!$this->isTestingEnvironment()) {
      exit();
    }
  }


  /**
   * @return int
   */
  public function runAll()
  {
    $time = 0;
    $done = [];
    while (($time < $this->timeout) &&
      ($cron = $this->cron->getManager()->getNext()) &&
      !\in_array($cron['id'], $done)
    ) {
      if ($ctx = $this->run($cron['id'])) {
        $time += $ctx;
      }
      array_push($done, $cron['id']);
    }
    return $time;
  }

  private function isTestingEnvironment(): bool
  {
    return defined('BBN_TESTING_EVN') && BBN_TESTING_EVN === true;
  }
}
