<?php
namespace bbn;

use bbn\X;

/**
 * (Static) content delivery system through requests using filesystem and internal DB for libraries.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Jan 3, 2016, 12:24:36 +0000
 * @category  Cache
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 */
class Cron extends Models\Cls\Basic
{

  use Cron\Common;

  private $_runner;

  private $_launcher;

  private $_manager;

  protected $controller;

  protected $exe_path;

  protected $log_file;

  protected $table;

  /**
   * @var Util\Timer
   */
  protected $timer;


  /**
   * @param Db $db
   * @param Mvc\Controller|null $ctrl
   * @param array $cfg
   */
  public function __construct(Db $db, Mvc\Controller $ctrl = null, array $cfg = [])
  {
    if ($db->check()) {
      $this->path = $cfg['data_path'] ?? Mvc::getDataPath('appui-cron');
      $this->db = $db;
      $this->timer = new Util\Timer();
      $this->table = ($cfg['prefix'] ?? $this->prefix).'cron';
      if (!empty($cfg['exe_path'])) {
        $this->exe_path = $cfg['exe_path'];
      }

      if (!empty($cfg['log_file'])) {
        $this->log_file = $cfg['log_file'];
      }

      if ($ctrl) {
        if (empty($this->exe_path)) {
          $this->exe_path = $ctrl->pluginUrl('appui-cron');
          if ($this->exe_path) {
            $this->exe_path .= '/run';
          }
        }

        $this->controller = $ctrl;
      }
    }
  }


  /**
   * @return Cron\Launcher|null
   */
  public function getLauncher(): ?Cron\Launcher
  {
    if (!$this->_launcher && $this->check() && $this->exe_path && $this->controller) {
      $this->_launcher = new Cron\Launcher($this);
    }

    return $this->_launcher;
  }


  /**
   * @param array $cfg
   * @return Cron\Runner|null
   */
  public function getRunner(array $cfg = []): ?Cron\Runner
  {
    X::log($cfg, 'cron');
    if ($this->check() && $this->controller) {
      return new Cron\Runner($this, $cfg);
    }

    return null;
  }


  /**
   * @param array $cfg
   * @return Mvc\Controller|null
   */
  public function getController(array $cfg = []): ?Mvc\Controller
  {
    if ($this->check() && $this->controller) {
      return $this->controller;
    }

    return null;
  }


  /**
   * @return Cron\Manager|null
   */
  public function getManager(): ?Cron\Manager
  {
    if (!$this->_manager && $this->check() && $this->controller) {
      $this->_manager = new Cron\Manager($this->db);
    }

    return $this->_manager;
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
