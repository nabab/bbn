<?php
namespace bbn\Models\Tts;

use bbn\Mvc;
use bbn\Util\Timer as TimerCls;

trait Timer
{
  protected function getTimer(): TimerCls
  {
    $mvc = Mvc::getInstance();
    if ($mvc) {
      return $mvc->getTimer();
    }

    return new TimerCls();
  }
}