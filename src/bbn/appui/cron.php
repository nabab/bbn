<?php
/*
 * Copyright (C) 2014 BBN
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bbn\appui;
use bbn;

/**
 * Class cron
 * @package bbn\appui
 */
class cron extends bbn\models\cls\basic{

  /**
   * @var string The tables' prefix (the tables will be called ?cron and ?journal)
   */
  private $prefix = 'bbn_';
  /**
   * @var string The full path to the plugin data folder where the actions and log files are/will be located
   */
  private $path;

  /**
   * @var bbn\db The DB connection
   */
  protected $db;
  /**
   * @var bbn\mvc\controller The controller
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

  protected static $token_timeout = 120;

  /**
   * @return int
   */
  public static function get_cron_timeout(): int
  {
    return self::$cron_timeout;
  }

  /**
   * @param int $cron_timeout
   */
  public static function set_cron_timeout(int $cron_timeout): void
  {
    self::$cron_timeout = $cron_timeout;
  }

  /**
   * @return int
   */
  public static function get_poll_timeout(): int
  {
    return self::$poll_timeout;
  }

  /**
   * @param int $poll_timeout
   */
  public static function set_poll_timeout(int $poll_timeout): void
  {
    self::$poll_timeout = $poll_timeout;
  }

  /**
   * @return int
   */
  public static function get_token_timeout(): int
  {
    return self::$token_timeout;
  }

  /**
   * @param int $token_timeout
   */
  public static function set_token_timeout(int $token_timeout): void
  {
    self::$token_timeout = $token_timeout;
  }

  /**
   * @var bbn\util\timer
   */
  public $timer;

  /**
   * The script as executed by the CLI in which the real task will come executed.
   * @param string $type
   */
  private function _run(): void
  {
    // The DB and the controller exist
    if ( $this->check() && isset($this->data['type'])){
      if ( !defined('BBN_EXTERNAL_USER_ID') && defined('BBN_EXTERNAL_USER_EMAIL') ){
        define('BBN_EXTERNAL_USER_ID', $this->db->select_one('bbn_users', 'id', ['email' => BBN_EXTERNAL_USER_EMAIL]));
      }
      if ( defined('BBN_EXTERNAL_USER_ID') && class_exists('\\bbn\\appui\\history') ){
        \bbn\appui\history::set_user(BBN_EXTERNAL_USER_ID);
      }
      $type = $this->data['type'];
      // Removing file cache
      clearstatcache();
      // only 2 types: poll or cron
      if ( $type !== 'poll' ){
        $type = 'cron';
      }
      $pid = $this->get_pid_path($this->data);

      // Checking for the presence of the manual files
      if (
        !$this->is_active() ||
        (($type === 'cron') && !$this->is_cron_active()) ||
        (($type === 'poll') && !$this->is_poll_active())
      ){
        // Exiting the script if one is missing
        if ( is_file($pid) ){
          @unlink($pid);
        }
        exit("GETTING OUT of $type BECAUSE one of the manual files is missing");
      }
      // Loooking for a running PID
      if ( is_file($pid) && ($file_content = @file_get_contents($pid)) ){
        $pid_content = explode('|', $file_content);
        if ( $pid_content[1] && file_exists('/proc/'.$pid_content[0]) ){
          // If it's currently running we exit
          exit("There is already a process running with PID ".file_get_contents($pid)." in $pid");
        }
        else{
          // Otherwise we delete the PID file
          echo "DELETING FILEPID AS THE PROCESS IS DEAD ".implode(PHP_EOL, $pid_content).PHP_EOL;
          @unlink($pid);
        }
      }
      // We create the PID file corresponding to the current process
      file_put_contents($pid, getmypid().'|'.time());
      $cron =& $this;
      // Shutdown function, will be always executed, except if the server is stopped
      register_shutdown_function(function() use($cron){
        $data = $cron->get_data();
        $pid = $this->get_pid_path($data);
        $file_content = @file_get_contents($pid);
        // Write the error log if an error is present
        if ( $error = error_get_last() ){
          @file_put_contents($cron->get_error_log_path($data), \bbn\x::get_dump($error));
          if ( defined('BBN_DATA_PATH') && is_dir(BBN_DATA_PATH.'logs') ){
            bbn\x::log([$data, $error], 'cron');
          }
        }
        $ok = true;
        // We check if there is a problem with the PID file (it's only debug it shouldn't be necessary)
        if ( $file_content ){
          echo $file_content.PHP_EOL;
          $pid_content = explode('|', $file_content);
          if ( $pid_content[1] && ($pid_content[0] != getmypid()) ){
            echo 'Different processes: '.$pid_content[0].'/'.getmypid().PHP_EOL;
            $ok = false;
          }
        }
        if ( $ok && isset($data['type']) ){
          // We output the ending time (as all output will be logged in the output file
          echo 'SHUTDOWN '.date('H:i:s');
          // Removing PID file
          if ( is_file($pid) ){
            @unlink($pid);
          }
          // And relaunching the continuous tasks if we are in the poller...
          if ( ($data['type'] === 'poll') && $cron->is_poll_active() ){
            $cron->launch_poll();
          }
          else if ( !array_key_exists('id', $data) && ($data['type'] === 'cron') && $cron->is_cron_active() ){
            $cron->launch_task_system();
          }
        }
      });
      // And here we really do what we have to do
      // Poll case
      if ( $type === 'poll' ){
        $this->poll();
      }
      else if ( $type === 'cron' ){
        if ( array_key_exists('id', $this->data) ){
          echo 'Launching task...';
          $this->run_task($this->data);
        }
        else{
          $this->run_task_system();
        }
      }
    }
  }

  /**
   * Executes the cli/run file in order to run another script from within the CRON process
   *
   * @param $path
   * @param string|null $output
   */
  public static function execute($path, string $output = null){
    if ( $output ){
      exec(sprintf('php -f router.php %s > %s 2>&1 &', $path, $output));
    }
    else{
      exec(sprintf('php -f router.php %s > /dev/null 2>&1 &', $path));
    }
  }

  /**
   * cron constructor.
   * @param bbn\mvc\controller $ctrl
   * @param array $cfg
   */
  public function __construct(bbn\mvc\controller $ctrl, array $cfg = []){
    if ( defined('BBN_DATA_PATH') ){
      $this->ctrl = $ctrl;
      // It must be called from a plugin (appui-cron actually)
      $this->path = BBN_DATA_PATH.'plugins/appui-cron/';
      $this->db = $ctrl->db;
      if ( !empty($ctrl->post) ){
        $this->data = $ctrl->post;
      }
      $vars = get_class_vars('\\bbn\appui\\cron');
      foreach ( $cfg as $cf_name => $cf_value ){
        if ( array_key_exists($cf_name, $vars) ){
          $this->{$cf_name} = $cf_value;
        }
      }
      $this->timer = new bbn\util\timer();
      $this->table = $this->prefix.'cron';
    }
  }

  public function launch(array $cfg): void
  {
    $to_exec = $this->ctrl->plugin_url('appui-cron').'/run';
    exec(sprintf('php -f router.php %s "%s" > %s 2>&1 &',
      $to_exec,
      bbn\str::escape_dquotes(json_encode($cfg)),
      $this->get_log_path($cfg)
    ));
  }

  public function launch_poll(){
    $this->launch(['type' => 'poll']);
  }

  public function launch_task_system(){
    $this->launch(['type' => 'cron']);
  }

  public function run_task(array $cfg){
    \bbn\x::dump("We are in the task...", $cfg);
    if ( isset($cfg['id'], $cfg['file']) && $this->check() ){
      if ( !defined('BBN_EXTERNAL_USER_ID') && defined('BBN_EXTERNAL_USER_EMAIL') ){
        define('BBN_EXTERNAL_USER_ID', $this->db->select_one('bbn_users', 'id', ['email' => BBN_EXTERNAL_USER_EMAIL]));
      }
      $this->start($cfg['id']);
      $this->ctrl->reroute($cfg['file']);
    }
  }

  public function run($type): void
  {
    $this->_run($type);
  }

  /**
   * Returns the $data property.
   * @return array|null
   */
  public function get_data(): ?array
  {
    return $this->data;
  }

  /**
   * Returns the $data property.
   * @return array|null
   */
  public function get_path(): ?string
  {
    return $this->path;
  }

  public function get_status_path($type): ?string {
    return $this->path && $type ? $this->path.'status/.'.$type : null;
  }

  public function get_pid_path(array $cfg): ?string
  {
    if ( $this->path && isset($cfg['type']) ){
      return $this->path.'pid/.'.(isset($cfg['file'], $cfg['id']) ? $cfg['id'] : $cfg['type']);
    }
    return null;
  }

  public function get_log_path(array $cfg): ?string
  {
    if ( isset($cfg['type']) && $this->path ){
      $dir = isset($cfg['id']) ? bbn\file\dir::create_path($this->path.'log/tasks/'.$cfg['id']) : $this->path.'log/'.$cfg['type'];
      return $dir ? $dir.'/'.date('Y-m-d-H-i-s').'.txt' : null;
    }
    return null;
  }

  public function get_error_log_path(array $cfg): ?string
  {
    if ( isset($cfg['type']) && $this->path ){
      $dir = isset($cfg['id']) ? bbn\file\dir::create_path($this->path.'error/tasks/'.$cfg['id']) : $this->path.'error/'.$cfg['type'];
      return $dir ? $dir.'/'.date('Y-m-d-H-i-s').'.txt' : null;
    }
    return null;
  }

  /**
   * Returns true if the file data_folder/.active exists, false otherwise.
   * @return bool
   */
  public function is_active(): bool
  {
    if ( $this->check() ){
      return file_exists($this->get_status_path('active'));
    }
    return false;
  }

  /**
   * Returns true if the file data_folder/.cron exists, false otherwise.
   * @return bool
   */
  public function is_cron_active(): bool
  {
    if ( $this->check() ){
      return file_exists($this->get_status_path('cron'));
    }
    return false;
  }

  /**
   * Returns true if the file data_folder/.poll exists, false otherwise.
   * @return bool
   */
  public function is_poll_active(): bool
  {
    if ( $this->check() ){
      return file_exists($this->get_status_path('poll'));
    }
    return false;
  }

  /**
   */

  public function poll(){
    if ( $this->check() ){
      $this->timer->start('timeout');
      $this->timer->start('tokens');
      $admin = new bbn\user\users($this->db);
      $obs = new bbn\appui\observer($this->db);
      echo "START: ".date('H:i:s').PHP_EOL;
      foreach ( $admin->get_old_tokens() as $t ){
        $id_user = $admin->get_user_from_token($t['id']);
        @bbn\file\dir::delete(BBN_DATA_PATH."users/$id_user/tmp/tokens/$t[id]", true);
        if ( $this->db->delete('bbn_users_tokens', ['id' => $t['id']]) ){
          echo '-';
        }
      }
      while ( $this->is_poll_active() ){
        $res = $obs->observe();
        if ( \is_array($res) ){
          foreach ( $res as $id_token => $o ){
            $id_user = $admin->get_user_from_token($id_token);
            $file = BBN_DATA_PATH."users/$id_user/tmp/tokens/$id_token/poller/queue/observer-".time().'.json';
            if ( bbn\file\dir::create_path(\dirname($file)) ){
              file_put_contents($file, json_encode(['observers' => $o]));
            }
          }
        }
        sleep(1);
        if ( $this->timer->measure('tokens') > self::$token_timeout ){
          echo '?';
          $admin->clean_tokens();
          $this->timer->stop('tokens');
          $this->timer->start('tokens');
        }
        if ( $this->timer->measure('timeout') > self::$poll_timeout ){
          exit("Ending because of timeout: ".date('H:i:s'));
        }
        if ( ob_get_contents() ){
          ob_end_flush();
        }
      }
    }
  }
  
  public function run_task_system(){
    if ( $this->check() ){
      echo "START: ".date('H:i:s').PHP_EOL;
      ob_end_flush();
      $this->timer->start('timeout');
      $admin = new bbn\user\users($this->db);
      while ( $this->is_cron_active() ){
        if ( $secs = date('s') ){
          sleep(60 - $secs);
        }
        $rows = $this->get_next_rows(0);
        foreach ( $rows as $r ){
          $param = [
            'type' => 'cron',
            'id' => $r['id'],
            'file' => $r['file']
          ];
          bbn\x::dump("Launching", $param);
          $this->launch($param);
        }
        if ( $this->timer->measure('timeout') > self::$cron_timeout ){
          exit("Ending because of timeout: ".date('H:i:s'));
        }
        if ( ob_get_contents() ){
          ob_end_flush();
        }
        sleep(1);
      }
    }
  }

  /**
   *

  public function launch(){
    if ( $path = $this->ctrl->plugin_data_path() ){
      $runner_output = $path.'cron/'.date('YmdHis').'.txt';
      self::execute($this->ctrl->plugin_url('appui-cron').'/cron', $runner_output);
    }
  }

  /**
   * Returns true if the object has been correctly built i.e. it includes $ctrl and $db, false otherwise.
   * @return bool
   */
  public function check(){
    return $this->ctrl && $this->db;
  }

  /**
   * @param $id
   * @return bool
   */
  public function get_article($id){
    if ( $this->check() && ($data = $this->db->rselect($this->jtable, [], ['id' => $id])) ){
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
    return false;
  }

  /**
   * Returns the full row as an indexed array for the given CRON ID.
   * @param $id
   * @return null|array
   */
  public function get_cron($id): ?array
  {
    if ( $this->check() && ($data = $this->db->rselect($this->table, [], ['id' => $id])) ){
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
    return null;
  }

  /**
   * Writes in the given CRON row the next start time, the current as previous, and the new running status.
   * @param $id_cron
   * @return bool
   */
  public function start($id_cron): bool
  {
    $res = false;
    if ( $this->check() && ($cron = $this->get_cron($id_cron)) ){
      bbn\appui\history::disable();
      $start = date('Y-m-d H:i:s');
      if ( $this->db->update($this->table, [
        'prev' => $start,
        'next' => date('Y-m-d H:i:s', $this->get_next_date($cron['cfg']['frequency']))
      ], [
        'id' => $id_cron
      ]) ){
        $res = true;
      }
      bbn\appui\history::enable();
    }
    return $res;
  }

  /**
   * Writes in the given CRON row the duration and the new finished status.
   * @param $id
   * @param string $res
   * @return bool|int
   */
  public function finish($id, $res = ''){
    if ( ($article = $this->get_article($id)) &&
            ($cron = $this->get_cron($article['id_cron'])) ){
      bbn\appui\history::disable();
      $time = $this->timer->has_started('cron_'.$article['id_cron']) ? $this->timer->stop('cron_'.$article['id_cron']): 0;
      if ( !empty($res) ){
        bbn\x::hdump($id, $res);
        $this->db->update($this->jtable, [
          'finish' => date('Y-m-d H:i:s'),
          'duration' => $time,
          'res' => $res
        ], [
          'id' => $id
        ]);
      }
      else{
        $this->db->delete($this->jtable, ['id' => $id]);
        $prev = $this->db->rselect($this->jtable, ['res', 'id'], ['id_cron' => $article['id_cron']], ['finish' => 'DESC']);
        if ( $prev['res'] === 'error' ){
          $this->db->update($this->jtable, ['res' => 'Restarted after error'], ['id' => $prev['id']]);
        }
      }
      bbn\appui\history::enable();
      return $time;
    }
    return false;
  }

  /**
   * Returns a SQL date for the next event given a frequency and a time to count from (now if 0).
   * @param $frequency
   * @param int $from_time
   * @return null|string
   */
  public function get_next_date(string $frequency, int $from_time = 0): ?string
  {
    if ( \is_string($frequency) && (\strlen($frequency) >= 2) ){
      if ( !$from_time ){
        $from_time = time();
      }
      $letter = bbn\str::change_case(substr($frequency, 0, 1), 'lower');
      $number = (int)substr($frequency, 1);
      $unit = null;
      if ( $number > 0 ){
        switch ( $letter ){
          case 'i':
            $unit = 60;
            break;
          case 'h':
            $unit = 3600;
            break;
          case 'd':
            $unit = 86400;
            break;
          case 'w':
            $unit = 604800;
            break;
        }
        $r = null;
        if ( null !== $unit ){
          $r = $from_time + ($unit * $number);
        }
        if ( $letter === 'm' ){
          $r = mktime(date('H', $from_time), date('i', $from_time), date('s', $from_time), date('n', $from_time)+$number, date('j', $from_time), date('Y', $from_time));
        }
        if ( $letter === 'y' ){
          $r = mktime(date('H', $from_time), date('i', $from_time), date('s', $from_time), date('n', $from_time)+$number, date('j', $from_time), date('Y', $from_time));
        }
        if ( null !== $r ){
          if ( $r < time() ){
            return $this->get_next_date($frequency);
          }
          return $r;
        }
      }
    }
    return null;
  }

  /**
   * Returns the whole row for the next CRON to be executed from now if there is any.
   * @param null $id_cron
   * @return null|array
   */
  public function get_next($id_cron = null): ?array
  {
    if ( $this->check() && ($data = $this->db->get_row("
        SELECT *
        FROM {$this->table}
        WHERE `active` = 1
        AND `next` < NOW()".
        ( bbn\str::is_uid($id_cron) ? " AND `id` = '$id_cron'" : '' )."
        ORDER BY `priority` ASC, `next` ASC
        LIMIT 1")) ){
      // Dans cfg: timeout, et soit: latency, minute, hour, day of month, day of week, date
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
  }

  public function get_next_rows(int $limit = 10, int $sec = 1): ?array
  {
    if ( $limit === 0 ){
      $limit = 1000;
    }
    if ( $this->check() ){
      return array_map(function($a){
        $cfg = $a['cfg'] ? json_decode($a['cfg'], true) : [];
        unset($a['cfg']);
        return \bbn\x::merge_arrays($a, $cfg);
      }, $this->db->get_rows("
        SELECT *
        FROM {$this->table}
        WHERE `active` = 1
        AND `next` < DATE_ADD(NOW(), INTERVAL $sec SECOND)
        ORDER BY `priority` ASC, `next` ASC
        LIMIT $limit"));
    }
    return null;
  }

  /**
   * @param $id_cron
   * @return bool
   */
  public function is_running($id_cron){
    if ( $this->check() && \is_int($id_cron) ){
      return $this->db->get_one("
        SELECT COUNT(*)
        FROM {$this->jtable}
        WHERE id_cron = ?
        AND finish IS NULL",
        $id_cron) ? true : false;
    }
  }

  /**
   * @param $id_cron
   * @return mixed
   */
  private function get_runner($id_cron){
    if ( $this->check() && \is_int($id_cron) ){
      $d = $this->db->get_row("
        SELECT *
        FROM {$this->jtable}
        WHERE id_cron = ?
        AND finish IS NULL",
        $id_cron);
      $d['cfg'] = json_decode($d['cfg'], 1);
      return $d;
    }
  }


  /**
   * @return bool|int
   */
  public function run_all(){
    $time = 0;
    $done = [];
    while ( ($time < $this->timeout) &&
           ($cron = $this->get_next()) &&
           !\in_array($cron['id'], $done) ){
      if ( $ctx = $this->run($cron['id']) ){
        $time += $ctx;
      }
      array_push($done, $cron['id']);
    }
    return $time;
  }

  /**
   * @param $id_cron
   * @return bool
   */
  public function is_timeout($id_cron){
    if ( $this->check() && \is_int($id_cron) && $this->is_running($id_cron)){
      $c = $this->get_cron($id_cron);
      $r = $this->get_runner($id_cron);
      if ( (strtotime($r['start']) + $c['cfg']['timeout']) < time() ){
        return true;
      }
    }
    return false;
  }

  /**
   * Sets the active column to 1 for the given CRON ID.
   * @param $id_cron
   * @return mixed
   */
  public function activate($id_cron){
    return $this->db->update($this->table, ['active' => 1], ['id' => $id_cron]);
  }

  /**
   * Sets the active column to 0 for the given CRON ID.
   * @param $id_cron
   * @return mixed
   */
  public function deactivate($id_cron){
    return $this->db->update($this->table, ['active' => 0], ['id' => $id_cron]);
  }

}
