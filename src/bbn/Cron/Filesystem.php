<?php
/**
 * User: BBN
 * Date: 04/01/2020
 * Time: 15:17
 */

namespace bbn\Cron;

use bbn\Str;
use bbn\Mvc;
use bbn\Db;
use bbn\File\System;
use bbn\Mvc\Controller;
use function count;

trait Filesystem {
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

