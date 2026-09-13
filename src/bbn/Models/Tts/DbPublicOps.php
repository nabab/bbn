<?php

namespace bbn\Models\Tts;

trait DbPublicOps
{
  public function exists(string|array $filter): bool
  {
    return $this->dbTraitExists($filter);
  }

  public function insert(array $data): ?string
  {
    return $this->dbTraitInsert($data);
  }

  public function insertIgnore(array $data): ?string
  {
    return $this->dbTraitInsert($data, true);
  }

  public function update(string|array $filter, array $data): int
  {
    return $this->dbTraitUpdate($filter, $data);
  }

  public function delete(string|array $filter): int
  {
    return $this->dbTraitDelete($filter);
  }

  public function rselect(
    string|array $filter = [],
    string|array $order= [],
    array $fields = [],
  ): ?array {
    return $this->dbTraitRselect($filter, $order, $fields);
  }

  public function select(
    string|array $filter = [],
    string|array $order= [],
    array $fields = [],
  ): ?\stdClass {
    return $this->dbTraitSelect($filter, $order, $fields);
  }

  public function selectOne(
    string $field,
    string|array $filter = [],
    string|array $order= [],
  ): mixed {
    return $this->dbTraitSelectOne($field, $filter, $order);
  }

  public function selectValues(
    string $field,
    array $filter = [],
    string|array $order= [],
    int $limit = 0,
    int $start = 0,
  ): array {
    return $this->dbTraitSelectValues($field, $filter, $order, $limit, $start);
  }

  public function selectAll(
    string|array $filter = [],
    string|array $order= [],
    $limit = 0,
    $start = 0,
    array $fields = [],
  ): array {
    return $this->dbTraitSelectAll($filter, $order, $limit, $start, $fields);
  }

  public function rselectAll(
    string|array $filter = [],
    string|array $order= [],
    $limit = 0,
    $start = 0,
    array $fields = [],
  ): array {
    return $this->dbTraitRselectAll($filter, $order, $limit, $start, $fields);
  }
}
