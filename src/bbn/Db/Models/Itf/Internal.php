<?php

namespace bbn\Db\Models\Itf;

use bbn\Db;

interface Internal
{
  public function getHash(): string;

  public function replaceTableInConditions(array $conditions, $old_name, $new_name): array;

  public function treatConditions(array $where, bool $full = true);

  public function reprocessCfg(array $cfg): ?array;

  public function processCfg(array $args, bool $force = false): ?array;

  public function check(): bool;

  public function log($st): Db;

  public function setErrorMode(string $mode): Db;

  public function getErrorMode(): string;

  public function clearCache(string $item, string $mode): Db;

  public function clearAllCache(): Db;

  public function stopFancyStuff(): Db;

  public function startFancyStuff(): Db;

}
