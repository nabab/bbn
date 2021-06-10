<?php
/**
 * Implements functions for retrieving a single filesystem object attached to the instance
 *
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:53
 */

namespace bbn\Models\Tts;

use bbn\File\System;

trait Filesystem
{

  protected $fs;

  protected function getFileSystem(): System
  {
    if (!$this->fs) {
      $this->fs = new System();
    }

    return $this->fs;
  }


}
