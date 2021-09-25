<?php
/*
 * Copyright (C) 2014 BBN
 *
 */

namespace bbn\Cron;
use bbn;

/**
 * Class cron
 * @package bbn\Appui
 */
class Launcher extends bbn\Models\Cls\Basic {

  use Common;

  protected $exe_path;

  protected $cron;

  /**
   * Constructor
   *
   * @param bbn\Cron $cron
   */
  public function __construct(bbn\Cron $cron)
  {
    if ($cron->check()) {
      $this->cron = $cron;
      $this->exe_path = $cron->getExePath();
    }
  }

  /**
   * Launch a parallel process
   *
   * @param array $cfg
   * @return string|null
   */
  public function launch(array $cfg): ?string
  {
    if ($this->exe_path) {
      $cfg['exe_path'] = $this->exe_path;
      $log = $this->cron->getLogPath($cfg).date('Y-m-d-H-i-s').'.txt';
      $cfg['log_file'] = $log;
      exec(sprintf('php -f router.php %s "%s" > %s 2>&1 &',
        $this->exe_path,
        bbn\Str::escapeDquotes(json_encode($cfg)),
        $log
      ));

      return $log;
    }

    return null;
  }

  /**
   * @return string|null
   */
  public function launchPoll(): ?string
  {
    if ($this->isPollActive()) {
      return $this->launch(['type' => 'poll']);
    }

    return null;
  }

  /**
   * @return string|null
   */
  public function launchTaskSystem(): ?string
  {
    if ($this->isCronActive()) {
      return $this->launch(['type' => 'cron']);
    }
    return null;
  }

}
