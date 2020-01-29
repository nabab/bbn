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
class launcher extends bbn\models\cls\basic {

  use common;

  protected $exe_path;

  protected $cron;

  /**
   * Constructor
   *
   * @param string $exe_path
   */
  public function __construct(bbn\cron $cron)
  {
    if ($cron->check()) {
      $this->cron = $cron;
      $this->exe_path = $cron->get_exe_path();
    }
  }

  /**
   * Launch a parallel process
   *
   * @param array $cfg
   * @return string
   */
  public function launch(array $cfg): string
  {
    if ($this->exe_path) {
      $cfg['exe_path'] = $this->exe_path;
      $log = $this->cron->get_log_path($cfg).date('Y-m-d-H-i-s').'.txt';
      $this->log(['launch', $log, $cfg]);
      exec(sprintf('php -f router.php %s "%s" > %s 2>&1 &',
        $this->exe_path,
        bbn\str::escape_dquotes(json_encode($cfg)),
        $log
      ));
      return $log;
    }
    return null;
  }

  public function launch_poll(): ?string
  {
    if ($this->is_poll_active()) {
      return $this->launch(['type' => 'poll']);
    }
    return null;
  }

  public function launch_task_system(): ?string
  {
    if ($this->is_cron_active()) {
      return $this->launch(['type' => 'cron']);
    }
    return null;
  }

}
