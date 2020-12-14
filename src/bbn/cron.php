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
class cron extends models\cls\basic
{

  use cron\common;

  private $_runner;

  private $_launcher;

  private $_manager;

  protected $controller;

  protected $exe_path;

  protected $log_file;


  public function __construct(db $db, mvc\controller $ctrl = null, array $cfg = [])
  {
    //if ( defined('BBN_DATA_PATH') ){
    if ($db->check()) {
      $this->path = $cfg['data_path'] ?? mvc::get_data_path('appui-cron');
      $this->db = $db;
      $this->timer = new util\timer();
      $this->table = ($cfg['prefix'] ?? $this->prefix).'cron';
      if (!empty($cfg['exe_path'])) {
        $this->exe_path = $cfg['exe_path'];
      }

      if (!empty($cfg['log_file'])) {
        $this->log_file = $cfg['log_file'];
      }

      if ($ctrl) {
        if (empty($this->exe_path)) {
          $this->exe_path = $ctrl->plugin_url('appui-cron');
          if ($this->exe_path) {
            $this->exe_path .= '/run';
          }
        }

        $this->controller = $ctrl;
      }
    }
  }


  public function get_launcher(): ?cron\launcher
  {
    if (!$this->_launcher && $this->check() && $this->exe_path && $this->controller) {
      $this->_launcher = new cron\launcher($this);
    }

    return $this->_launcher;
  }


  public function get_runner(array $cfg = []): ?cron\runner
  {
    if ($this->check() && $this->controller) {
      return new cron\runner($this, $cfg);
    }

    return null;
  }


  public function get_controller(array $cfg = []): ?mvc\controller
  {
    if ($this->check() && $this->controller) {
      return $this->controller;
    }

    return null;
  }


  public function get_manager(): ?cron\manager
  {
    if (!$this->_manager && $this->check() && $this->controller) {
      $this->_manager = new cron\manager($this->db);
    }

    return $this->_manager;
  }


  public function check(): bool
  {
    return $this->db->check();
  }

  public function get_exe_path()
  {
    return $this->exe_path;
  }

  public function get_log_file()
  {
    return $this->log_file;
  }

  public function get_path(): ?string
  {
    return $this->path;
  }

  public function launch_poll()
  {
    if ($launcher = $this->get_launcher()) {
      return $launcher->launch(['type' => 'poll']);
    }
    return null;
  }

  public function launch_task_system()
  {
    if ($launcher = $this->get_launcher()) {
      return $launcher->launch(['type' => 'cron']);
    }
    return null;
  }




}
