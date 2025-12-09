<?php
namespace bbn;

use bbn\X;
use bbn\Db;
use bbn\File\System;
use bbn\Mvc;
use bbn\Mvc\Controller;
use bbn\Cron\Config;
use bbn\Cron\Filesystem;
use bbn\Cron\Launcher;
use bbn\Cron\Runner;
use bbn\Cron\Manager;
use bbn\Models\Cls\Basic;
use bbn\Util\Timer;
/**
 * (Static) content delivery system through requests using filesystem and internal DB for libraries.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Jan 3, 2016, 12:24:36 +0000
 * @category  Cache
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 */
class Cron extends Basic
{

  use Config;
  use Filesystem;

  private ?Runner $runner   = null;
  private ?Launcher $launcher = null;
  private ?Manager $manager   = null;

  protected ?string $exe_path = null;
  protected ?string $log_file = null;
  protected string $table;

  protected Timer $timer;


  /**
   * @param Db $db
   * @param Controller|null $ctrl
   * @param array $cfg
   */
  public function __construct(
    protected Db $db,
    protected ?Controller $controller = null,
    array $cfg = []
  ) {
    if ($db->check()) {
      $this->path = $cfg['data_path'] ?? Mvc::getDataPath('appui-cron');
      $this->timer = new Timer();
      $this->table = ($cfg['prefix'] ?? $this->prefix).'cron';
      if (!empty($cfg['exe_path'])) {
        $this->exe_path = $cfg['exe_path'];
      }

      if (!empty($cfg['log_file'])) {
        $this->log_file = $cfg['log_file'];
      }

      if ($this->controller) {
        if (empty($this->exe_path)) {
          $this->exe_path = $this->controller->pluginUrl('appui-cron');
          if ($this->exe_path) {
            $this->exe_path .= '/run';
          }
        }

      }
    }
  }


  /**
   * @return Launcher|null
   */
  public function getLauncher(): ?Launcher
  {
    if (!$this->launcher && $this->check() && $this->exe_path && $this->controller) {
      $this->launcher = new Launcher($this);
    }

    return $this->launcher;
  }


  /**
   * @param array $cfg
   * @return Runner|null
   */
  public function getRunner(array $cfg = []): ?Runner
  {
    X::log($cfg, 'cron');
    if ($this->check() && $this->controller) {
      return new Runner($this, $cfg);
    }

    return null;
  }


  /**
   * @param array $cfg
   * @return Controller|null
   */
  public function getController(array $cfg = []): ?Controller
  {
    if ($this->check() && $this->controller) {
      return $this->controller;
    }

    return null;
  }


  /**
   * @return Manager|null
   */
  public function getManager(): ?Manager
  {
    if (!$this->manager && $this->check() && $this->controller) {
      $this->manager = new Manager($this->db);
    }

    return $this->manager;
  }


  /**
   * @return bool
   */
  public function check(): bool
  {
    return $this->db->check();
  }

  /**
   * @return string|null
   */
  public function getExePath()
  {
    return $this->exe_path;
  }

  /**
   * @return string|null
   */
  public function getLogFile()
  {
    return $this->log_file;
  }

  /**
   * @return string|null
   */
  public function getPath(): ?string
  {
    return $this->path;
  }

  public function getDayLogs(array $cfg): ?array
  {
    if ( Str::isUid($cfg['id']) && Str::isDateSql($cfg['day']) ){
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


  public function getLogTree(array $cfg, bool $error = false)
  {
    $fs = new System();
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

  public function  get_log_prev_next(array $cfg): ?string
  {
    $fs = new System();
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
    $fs = new System();
    if (($path = $this->getLogPath($cfg, $error, true)) && $fs->isDir($path)) {
      $res = [];
      $fs->cd($path);
      $years = array_reverse($fs->getDirs($path));
      foreach ($years as $y) {
        $months = array_reverse($fs->getDirs($y));
        foreach ($months as $m) {
          $days = array_reverse($fs->getDirs($m));
          foreach ($days as $d) {
            $numbers = array_reverse($fs->getDirs($d));
            foreach ($numbers as $number) {
              foreach (array_reverse($fs->getFiles($number)) as $f) {
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

  /**
   * @return string|null
   */
  public function launchPoll()
  {
    if ($launcher = $this->getLauncher()) {
      return $launcher->launch(['type' => 'poll']);
    }

    return null;
  }

  /**
   * @return string|null
   */
  public function launchTaskSystem()
  {
    if ($launcher = $this->getLauncher()) {
      return $launcher->launch(['type' => 'cron']);
    }

    return null;
  }
}
