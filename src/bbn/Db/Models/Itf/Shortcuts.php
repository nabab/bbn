<?php

namespace bbn\Db\Models\Itf;

interface Shortcuts
{
  public function tfn(string $table, bool $escaped = false): ?string;

  public function tsn(string $table, bool $escaped = false): ?string;

  public function cfn(string $col, ?string $table = null, bool $escaped = false): ?string;

  public function csn(string $col, bool $escaped = false): ?string;

}
