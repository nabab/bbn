<?php

namespace bbn\Db\Models\Itf;

interface Native
{
  public function fetch(string $query);

  public function fetchAll(string $query);

  public function fetchColumn($query, int $num = 0);

  public function fetchObject($query);

  public function query($statement);

  public function executeStatement(string $statement);

  public function rawQuery(string $st);

}
