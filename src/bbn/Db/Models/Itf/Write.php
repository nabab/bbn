<?php

namespace bbn\Db\Models\Itf;

interface Write
{
  public function insert($table, array|null $values = null, bool $ignore = false): ?int;

  public function insertUpdate($table, array|null $values = null): ?int;

  public function update($table, array|null $values = null, array|null $where = null, bool $ignore = false): ?int;

  public function updateIgnore($table, array|null $values = null, array|null $where = null): ?int;

  public function delete($table, array|null $where = null, bool $ignore = false): ?int;

  public function deleteIgnore($table, array|null $where = null): ?int;

  public function insertIgnore($table, array|null $values = null): ?int;

  public function truncate($table): ?int;

}
