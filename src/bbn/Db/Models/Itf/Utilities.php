<?php

namespace bbn\Db\Models\Itf;

use bbn\Db;

interface Utilities
{
  public function escapeValue(string $value, $esc = "'"): string;

  public function setLastInsertId($id = ''): Db;

  public function last(): ?string;

  public function lastId();

  public function flush(): int;

  public function newId($table, int $min = 1);

  public function randomValue($col, $table);

  public function countQueries(): int;

}
