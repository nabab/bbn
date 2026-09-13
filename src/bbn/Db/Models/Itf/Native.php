<?php

namespace bbn\Db\Models\Itf;

interface Native
{
  public function fetch(string $query, ...$additionalArgs);

  public function fetchAll(string $query, ...$additionalArgs);

  public function fetchColumn(string $query, int $num = 0, ...$additionalArgs);

  public function fetchObject(string $query, ...$additionalArgs);

  public function query(string $statement, ...$additionalArgs);

  public function executeStatement(string $statement);

  public function rawQuery(string $st);

}
