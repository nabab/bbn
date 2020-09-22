<?php
/**
 * User: BBN
 * Date: 04/01/2020
 * Time: 15:17
 */

namespace bbn\cron;
use bbn;


trait common {
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

  protected static $user_timeout = 480;

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
  public static function get_user_timeout(): int
  {
    return self::$user_timeout;
  }

  /**
   * @param int $user_timeout
   */
  public static function set_user_timeout(int $user_timeout): void
  {
    self::$user_timeout = $user_timeout;
  }


  /**
   * cron constructor.
   * @param bbn\mvc\controller $ctrl
   * @param array $cfg
   */
  public function init(array $cfg = []){
    //if ( defined('BBN_DATA_PATH') ){
    $this->path = bbn\mvc::get_data_path('appui-cron');
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
    if ($this->path && (isset($cfg['type']) || isset($cfg['id']))) {
      return $this->path.'pid/.'.($cfg['id'] ?? $cfg['type']);
    }
    return null;
  }

  public function get_log_path(array $cfg, bool $error = false, bool $no_path = false): ?string
  {
    $path = null;
    if ($this->path && (isset($cfg['type']) || isset($cfg['id']))) {
      if ( $error ){
        $path = $this->path.'error/'.(isset($cfg['id']) ? 'tasks/'.$cfg['id'] : $cfg['type']);
      }
      else {
        $path = $this->path.'log/'.(isset($cfg['id']) ? 'tasks/'.$cfg['id'] : $cfg['type']);
      }
      if ($error || $no_path) {
        $path .= '/';
      }
      else {
        $path = \bbn\x::make_storage_path($path);
      }
    }
    return $path;
  }

  public function get_log_tree(array $cfg, bool $error = false)
  {
    $fs = new bbn\file\system();
    $fpath = !empty($cfg['fpath']) ? $cfg['fpath'] . '/' : '';
    if (($path = $this->get_log_path($cfg, $error, true)) && $fs->is_dir($path.$fpath)) {
      $fs->cd($path.$fpath);
      $dirs = array_reverse($fs->get_files('./', true, true, null, 'cts'));
      foreach ( $dirs as &$t ){
        $t['numChildren'] = $t['num'] ?? 0;
        $t['fpath'] = $fpath . $t['name'];
        if ( isset($t['num']) ){
          unset($t['num']);
        }
      }
      return $dirs;
    }
  }

  public function get_day_logs(array $cfg): ?array
  {
    if ( bbn\str::is_uid($cfg['id']) && bbn\str::is_date_sql($cfg['day']) ){
      $p = \explode('-', $cfg['day']);
      \array_pop($p);
      $p = \implode('/', $p).'/';
      if (
        ($task = $this->get_manager()->get_cron($cfg['id'])) &&
        !empty($task['file']) &&
        ($path = $this->get_log_path($cfg, false, true)) &&
        ($file = $path.$p.$cfg['day'].'.json') &&
        \is_file($file) &&
        ($file = \json_decode(\file_get_contents($file), true))
      ){
        return array_reverse(array_filter($file, function($f) use($task){
          return $f['file'] === $task['file'];
        }));
      }
      return [];
    }
    return null;
  }

  public function  get_log_prev_next(array $cfg): ?string
  {
    $fs = new bbn\file\system();
    $fpath = $cfg['fpath'] ?: '';
    if ( ($path = $this->get_log_path($cfg, false, true)) && $fs->is_dir($path.$fpath) ){
      $fs->cd($path.$fpath);
      $files = array_reverse($fs->get_files('./', true, true, null, 'cts'));
      foreach ( $files as $i => $f ){
        if ( $f['name'] === $cfg['filename'] ){
          $tf = $files[$i + ($cfg['action'] === 'prev' ? 1 : -1)];
          return $path . $fpath . (!empty($tf) ? $tf['name'] : $f['name']);
        }
      }
    }
    return null;
  }

  public function get_last_logs(array $cfg, bool $error = false, $start = 0, $num = 10): ?array
  {
    $fs = new \bbn\file\system();
    if (($path = $this->get_log_path($cfg, $error, true)) && $fs->is_dir($path)) {
      $res = [];
      $fs->cd($path);
      $years = array_reverse($fs->get_dirs($path));
      foreach ($years as $y) {
        $months = array_reverse($fs->get_dirs($y));
        foreach ($months as $m) {
          $days = array_reverse($fs->get_dirs($m));
          foreach ($days as $d) {
            $nums = array_reverse($fs->get_dirs($d));
            foreach ($nums as $num) {
              foreach (array_reverse($fs->get_files($num)) as $f) {
                if ($start) {
                  $start--;
                }
                if (!$start) {
                  $res[] = $f;
                  if (count($res) >= $num) {
                    return $res;
                  }
                }
              }
            }
          }
        }
      }
      return $res;
    }
    return null;
  }

  public function get_last_log(array $cfg, bool $error = false): ?string
  {
    if ($tmp = $this->get_last_logs($cfg, $error, 0, 1)) {
      return $tmp[0];
    }
    return null;

  }

  /*public function get_error_log_path(array $cfg): ?string
  {
    if ( isset($cfg['type']) && $this->path ){
      $dir = isset($cfg['id']) ? bbn\file\dir::create_path($this->path.'error/tasks/'.$cfg['id']) : $this->path.'error/'.$cfg['type'];
      return $dir ? $dir.'/'.date('Y-m-d-H-i-s').'.txt' : null;
    }
    return null;
  }*/

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

}