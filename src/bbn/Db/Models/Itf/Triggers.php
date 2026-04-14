<?php

namespace bbn\Db\Models\Itf;

use bbn\Db;

interface Triggers
{
  public function enableTrigger(): Db;

  public function disableTrigger(): Db;

  public function isTriggerEnabled(): bool;

  public function isTriggerDisabled(): bool;

  public function setTrigger(callable $function, $kind = null, $moment = null, $tables = '*' ): Db;

  public function getTriggers(): array;

}
