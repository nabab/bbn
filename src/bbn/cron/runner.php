<?php
/*
 * Copyright (C) 2014 BBN
 *
 */

namespace bbn\cron;

use bbn;

/**
 * Class cron
 * @package bbn\appui
 */
class runner extends bbn\models\cls\basic
{

  use common;

  protected $controller;

  /**
   * Timer
   *
   * @var bbn\util\timer
   */
  protected $timer;

  /**
   * The script as executed by the CLI in which the real task will come executed.
   * @param string $type
   */
  private function _run(): void
  {
    // The DB and the controller exist
    if ($this->check() && isset($this->data['type'])) {
      if (defined('BBN_EXTERNAL_USER_ID') && class_exists('\\bbn\\appui\\history')) {
        \bbn\appui\history::set_user(BBN_EXTERNAL_USER_ID);
      }
      $type = $this->data['type'];
      // Removing file cache
      clearstatcache();
      // only 2 types: poll or cron
      if ($type !== 'poll') {
        $type = 'cron';
      }
      $pid_file = $this->get_pid_path($this->data);
      $this->log(["PID", $pid_file, $this->data]);

      // Checking for the presence of the manual files
      if (
        !$this->is_active() ||
        (($type === 'cron') && !$this->is_cron_active()) ||
        (($type === 'poll') && !$this->is_poll_active())
      ) {
        // Exiting the script if one is missing
        /*
        if ( is_file($pid) ){
           @unlink($pid);
        }
        */
        $this->log("GETTING OUT of $type BECAUSE one of the manual files is missing");
        exit("GETTING OUT of $type BECAUSE one of the manual files is missing");
      }
      // Loooking for a running PID
      if (is_file($pid_file)) {
        [$pid, $time] = explode('|', file_get_contents($pid_file));
        if (file_exists('/proc/' . $pid)) {
          $this->log("There is already a process running with PID " . $pid);
          // If it's currently running we exit
          $this->output(_('Existing process'), $pid);
          exit();
        } else {
          // Otherwise we delete the PID file
          $this->log("DELETING FILEPID AS THE PROCESS IS DEAD " . $pid);
          $this->output(_('Dead process'), $pid);
          @unlink($pid_file);
        }
      }
      // We create the PID file corresponding to the current process
      if (file_put_contents($pid_file, BBN_PID . '|' . time())) {
        // Shutdown function, will be always executed, except if the server is stopped
        register_shutdown_function([$this, 'shutdown']);
        // And here we really do what we have to do
        // Poll case
        if ($type === 'poll') {
          $this->poll();
        } else if ($type === 'cron') {
          if (array_key_exists('id', $this->data)) {
            $this->output(_('Launching'), $this->data['file']);
            $this->run_task($this->data);
          } else {
            $this->run_task_system();
          }
        }
      }
    }
    exit();
  }

  /**
   * cron constructor.
   * @param bbn\cron $ctrl
   * @param array $cfg
   */
  public function __construct(bbn\cron $cron, array $cfg)
  {
    //if ( defined('BBN_DATA_PATH') ){
    if (!empty($cfg['type']) && $cron->check()) {
      $this->controller = $cron->get_controller();
      $this->cron = $cron;
      $this->db = $this->controller->db;
      // It must be called from a plugin (appui-cron actually)
      //$this->path = BBN_DATA_PATH.'plugins/appui-cron/';
      $this->path = $this->controller->data_path('appui-cron');
      $this->data = $cfg;
      $this->type = $cfg['type'];
      $this->timer = new bbn\util\timer();
      $this->output(true);
    }
  }

  public function __destruct()
  {
    $this->output(false);
  }

  public function output($name = '', $log = ''): void
  {
    if ($name === false) {
      echo '}' . PHP_EOL;
    } else if ($name === true) {
      echo '{' . PHP_EOL;
    } else if ($name) {
      $is_number = bbn\str::is_number($log);
      $is_boolean = \is_bool($log);
      $is_string = \is_string($log);
      if (!$is_number && !$is_boolean && !$is_string) {
        $log = bbn\x::get_dump($log);
      } else if ($is_boolean) {
        $log = $log ? 'true' : 'false';
      }
      echo '  "' .
        bbn\str::escape_dquotes($name) .
        '": ' . ($is_string ? '"' : '') .
        ($is_string ? bbn\str::escape_dquotes($log) : $log) .
        ($is_string ? '"' : '') . ',' .
        PHP_EOL;
    }
    if (ob_get_length()) {
      ob_end_flush();
    }
  }

  public function shutdown()
  {
    $data = $this->get_data();
    $pid = $this->get_pid_path($data);
    $file_content = @file_get_contents($pid);
    // Write the error log if an error is present
    if ($error = error_get_last()) {
      $this->output(_('Error'), $error);
      bbn\x::log([$data, $error], 'cron');
    }
    $ok = true;
    if (ob_get_length()) {
      $content = ob_end_flush();
      $this->output(_('Content'), $content);
    }
    // We check if there is a problem with the PID file (it's only debug it shouldn't be necessary)
    if ($file_content) {
      $pid_content = explode('|', $file_content);
      if ($pid_content[1] && ($pid_content[0] != BBN_PID)) {
        $this->output(_('Different processes'), $pid_content[0] . '/' . BBN_PID);
        bbn\x::log(_('Different processes') . ': ' . $pid_content[0] . '/' . BBN_PID, 'cron');
        $ok = false;
      }
    }
    if ($ok && isset($data['type'])) {
      // We output the ending time (as all output will be logged in the output file
      $this->output(_('Shutdown'), date('H:i:s'));
      // Removing PID file
      if (is_file($pid)) {
        @unlink($pid);
      }
      // And relaunching the continuous tasks if we are in the poller...
      if (
        ($data['type'] === 'poll')
      ) {
        $this->cron->launch_poll();
      } else if ($data['type'] === 'cron') {
        if (array_key_exists('id', $data) && bbn\str::is_uid($data['id'])) {
          $this->cron->get_manager()->finish($data['id']);
        } else {
          $this->output(_('Restarting task system'), date('H:i:s'));
          $this->cron->launch_task_system();
        }
      }
    }
  }

  /**
   * Returns the $data property.
   * @return array|null
   */
  public function get_data(): ?array
  {
    return $this->data;
  }

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
   * @return array|null
   */

  public function poll()
  {
    if ($this->check()) {
      $this->timer->start('timeout');
      $this->timer->start('users');
      $obs = new bbn\appui\observer($this->db);
      $this->output(_('Starting poll'), date('Y-m-d H:i:s'));
      /*
      foreach ( $admin->get_old_tokens() as $t ){
        $id_user = $admin->get_user_from_token($t['id']);
        @bbn\file\dir::delete(BBN_DATA_PATH."users/$id_user/tmp/tokens/$t[id]", true);
        if ( $this->db->delete('bbn_users_tokens', ['id' => $t['id']]) ){
          echo '-';
        }
      }
      */
      while ($this->is_poll_active()) {
        $res = $obs->observe();
        if (\is_array($res)) {
          foreach ($res as $id_user => $o) {
            //$file = BBN_DATA_PATH."users/$id_user/tmp/poller/queue/observer-".time().'.json';
            $file = $this->controller->data_path() . "users/$id_user/tmp/poller/queue/observer-" . time() . '.json';
            if (bbn\file\dir::create_path(\dirname($file))) {
              file_put_contents($file, json_encode(['observers' => $o]));
            }
          }
        }
        sleep(1);
        if ($this->timer->measure('users') > self::$user_timeout) {
          echo '?';
          //$admin->clean_tokens();
          $this->timer->stop('users');
          $this->timer->start('users');
        }
        if ($this->timer->measure('timeout') > self::$poll_timeout) {
          //$this->output(_('Timeout'), date('Y-m-d H:i:s'));
          echo '.';
        }
      }
    }
  }

  public function run_task_system()
  {
    if ($this->check()) {
      $this->output(_('Start task system'), date('Y-m-d H:i:s'));
      $this->timer->start('timeout');
      $ok = true;
      while ($ok) {
        if (!$this->is_active() || !$this->is_cron_active()) {
          $this->output(_('End'), date('Y-m-d H:i:s'));
          if ($rows = $this->cron->get_manager()->get_running_rows()) {
            foreach ($rows as $r) {
              if (file_exists('/proc/' . $r['pid'])) {
                exec('kill -9 ' . $r['pid']);
                $this->output(_('Killing task'), $r['pid']);
              }
              $fpid = $this->get_pid_path(['type' => 'cron', 'id' => $r['id']]);
              if (is_file($fpid)) {
                unlink($fpid);
                $this->output(_('Deleting PID file'), $fpid);
              }
              $this->cron->get_manager()->unset_pid($r['id']);
            }
          }
          exit();
        }
        if ($rows = $this->cron->get_manager()->get_next_rows(0)) {
          foreach ($rows as $r) {
            $param = [
              'type' => 'cron',
              'id' => $r['id'],
              'file' => $r['file']
            ];
            $this->output(_('Launch'), date('Y-m-d H:i:s'));
            $this->output(_('Execution'), $param['file']);
            $this->cron->get_launcher()->launch($param);
          }
        }
        sleep(10);
      }
    }
  }

  public function run_task(array $cfg)
  {
    if (isset($cfg['id'], $cfg['file']) && $this->check()) {
      if (!defined('BBN_EXTERNAL_USER_ID') && defined('BBN_EXTERNAL_USER_EMAIL')) {
        define('BBN_EXTERNAL_USER_ID', $this->db->select_one('bbn_users', 'id', ['email' => BBN_EXTERNAL_USER_EMAIL]));
      }
      if ($this->cron->get_manager()->start($cfg['id'])) {
        $this->output(_("Start time"), date('Y-m-d H:i:s'));
        $this->timer->start($cfg['file']);
        $this->controller->reroute($cfg['file']);
        $this->controller->process();
        $this->output(_("Execution time"), $this->timer->stop($cfg['file']));
        $obj = $this->controller->get_result();
        $this->output(_("Content"), $obj->content);
      }
      else{
        $this->output(_("The start function returned false"), $cfg['file']);
      }

    } else {
      $this->output(_("Task unstartable"), $cfg['file']);
    }
    exit();
  }


  /**
   * @return bool|int
   */
  public function run_all()
  {
    $time = 0;
    $done = [];
    while (($time < $this->timeout) &&
      ($cron = $this->cron->get_manager()->get_next()) &&
      !\in_array($cron['id'], $done)
    ) {
      if ($ctx = $this->run($cron['id'])) {
        $time += $ctx;
      }
      array_push($done, $cron['id']);
    }
    return $time;
  }
}