<?php
/**
 * User: BBN
 * Date: 04/01/2020
 * Time: 15:17
 */

namespace bbn\Cron;
use bbn;


trait Common {
  /**
   * @var string The tables' prefix (the tables will be called ?cron and ?journal)
   */
  private $prefix = 'bbn_';
  /**
   * @var string The full path to the plugin data folder where the actions and log files are/will be located
   */
  private $path;

  /**
   * @var bbn\Db The DB connection
   */
  protected $db;
  /**
   * @var bbn\Mvc\Controller The controller
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
    $this->path = $cfg['data_path'] ?? bbn\Mvc::getDataPath('appui-cron');
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

  /**
   * @param $type
   * @return string|null
   */
  public function getStatusPath($type): ?string
  {
    return $this->path && $type ? $this->path.'status/.'.$type : null;
  }

  /**
   * @param array $cfg
   * @return string|null
   */
  public function getPidPath(array $cfg): ?string
  {
    if ($this->path && (isset($cfg['type']) || isset($cfg['id']))) {
      return $this->path.'pid/.'.($cfg['id'] ?? $cfg['type']);
    }
    return null;
  }

  /**
   * @param array $cfg
   * @param bool $error
   * @param bool $no_path
   * @return string|null
   */
  public function getLogPath(array $cfg, bool $error = false, bool $no_path = false): ?string
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
        $path = \bbn\X::makeStoragePath($path);
      }
    }

    return $path;
  }

  public function getLogTree(array $cfg, bool $error = false)
  {
    $fs = new bbn\File\System();
    $fpath = !empty($cfg['fpath']) ? $cfg['fpath'] . '/' : '';
    if (($path = $this->getLogPath($cfg, $error, true)) && $fs->isDir($path.$fpath)) {
      $fs->cd($path.$fpath);
      $dirs = array_reverse($fs->getFiles('./', true, true, null, 'cts'));
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

  public function getDayLogs(array $cfg): ?array
  {
    if ( bbn\Str::isUid($cfg['id']) && bbn\Str::isDateSql($cfg['day']) ){
      $p = \explode('-', $cfg['day']);
      \array_pop($p);
      $p = \implode('/', $p).'/';
      if (
        ($task = $this->getManager()->getCron($cfg['id'])) &&
        !empty($task['file']) &&
        ($path = $this->getLogPath($cfg, false, true)) &&
        ($file = $path.$p.$cfg['day'].'.json') &&
        \is_file($file) &&
        ($file = \json_decode(\file_get_contents($file), true))
      ){
        return array_reverse(array_filter($file, function($f) use($task){
          return isset($f['file']) && ($f['file'] === $task['file']);
        }));
      }
      return [];
    }
    return null;
  }

  public function  get_log_prev_next(array $cfg): ?string
  {
    $fs = new bbn\File\System();
    $fpath = $cfg['fpath'] ?: '';
    if ( ($path = $this->getLogPath($cfg, false, true)) && $fs->isDir($path.$fpath) ){
      $fs->cd($path.$fpath);
      $files = array_reverse($fs->getFiles('./', true, true, null, 'cts'));
      foreach ( $files as $i => $f ){
        if ( $f['name'] === $cfg['filename'] ){
          $tf = $files[$i + ($cfg['action'] === 'prev' ? 1 : -1)];
          return $path . $fpath . (!empty($tf) ? $tf['name'] : $f['name']);
        }
      }
    }
    return null;
  }

  public function getLastLogs(array $cfg, bool $error = false, $start = 0, $num = 10): ?array
  {
    $fs = new \bbn\File\System();
    if (($path = $this->getLogPath($cfg, $error, true)) && $fs->isDir($path)) {
      $res = [];
      $fs->cd($path);
      $years = array_reverse($fs->getDirs($path));
      foreach ($years as $y) {
        $months = array_reverse($fs->getDirs($y));
        foreach ($months as $m) {
          $days = array_reverse($fs->getDirs($m));
          foreach ($days as $d) {
            $nums = array_reverse($fs->getDirs($d));
            foreach ($nums as $num) {
              foreach (array_reverse($fs->getFiles($num)) as $f) {
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

  public function getLastLog(array $cfg, bool $error = false): ?string
  {
    if ($tmp = $this->getLastLogs($cfg, $error, 0, 1)) {
      return $tmp[0];
    }
    return null;

  }

  /*public function get_error_log_path(array $cfg): ?string
  {
    if ( isset($cfg['type']) && $this->path ){
      $dir = isset($cfg['id']) ? bbn\File\Dir::createPath($this->path.'error/tasks/'.$cfg['id']) : $this->path.'error/'.$cfg['type'];
      return $dir ? $dir.'/'.date('Y-m-d-H-i-s').'.txt' : null;
    }
    return null;
  }*/

  /**
   * Returns true if the file data_folder/.active exists, false otherwise.
   *
   * @return bool
   */
  public function isActive(): bool
  {
    if ( $this->check() ){
      return file_exists($this->getStatusPath('active'));
    }

    return false;
  }

  /**
   * Returns true if the file data_folder/.cron exists, false otherwise.
   *
   * @return bool
   */
  public function isCronActive(): bool
  {
    if ( $this->check() ){
      return file_exists($this->getStatusPath('cron'));
    }

    return false;
  }

  /**
   * Returns true if the file data_folder/.poll exists, false otherwise.
   *
   * @return bool
   */
  public function isPollActive(): bool
  {
    if ( $this->check() ){
      return file_exists($this->getStatusPath('poll'));
    }

    return false;
  }

}