<?php
namespace bbn;

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


  public function __construct(Db $db, Mvc\Controller $ctrl = null, array $cfg = [])
  {
    //if ( defined('BBN_DATA_PATH') ){
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


  public function getLauncher(): ?Cron\Launcher
  {
    if (!$this->_launcher && $this->check() && $this->exe_path && $this->controller) {
      $this->_launcher = new Cron\Launcher($this);
    }

    return $this->_launcher;
  }


  public function getRunner(array $cfg = []): ?Cron\Runner
  {
    if ($this->check() && $this->controller) {
      return new Cron\Runner($this, $cfg);
    }

    return null;
  }


  public function getController(array $cfg = []): ?Mvc\Controller
  {
    if ($this->check() && $this->controller) {
      return $this->controller;
    }

    return null;
  }


  public function getManager(): ?Cron\Manager
  {
    if (!$this->_manager && $this->check() && $this->controller) {
      $this->_manager = new Cron\Manager($this->db);
    }

    return $this->_manager;
  }


  public function check(): bool
  {
    return $this->db->check();
  }

  public function getExePath()
  {
    return $this->exe_path;
  }

  public function getLogFile()
  {
    return $this->log_file;
  }

  public function getPath(): ?string
  {
    return $this->path;
  }

  public function launchPoll()
  {
    if ($launcher = $this->getLauncher()) {
      return $launcher->launch(['type' => 'poll']);
    }
    return null;
  }

  public function launchTaskSystem()
  {
    if ($launcher = $this->getLauncher()) {
      return $launcher->launch(['type' => 'cron']);
    }
    return null;
  }




}
