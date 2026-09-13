<?php

namespace bbn\Db\Models\Itf;

interface Read
{
  public function select($table, $fields = [], array $where = [], string|array $order= [], int $start = 0): ?\stdClass;

  public function selectAll($table, $fields = [], array $where = [], string|array $order= [], int $limit = 0, int $start = 0): ?array;

  public function iselect($table, $fields = [], array $where = [], string|array $order= [], int $start = 0): ?array;

  public function iselectAll($table, $fields = [], array $where = [], string|array $order= [], int $limit = 0, int $start = 0): ?array;

  public function rselect($table, $fields = [], array $where = [], string|array $order= [], int $start = 0): ?array;

  public function rselectAll($table, $fields = [], array $where = [], string|array $order= [], $limit = 0, $start = 0): ?array;

  public function countUnion(array $union, array $where = []): ?int;

	public function selectUnion(array $union, $fields = [], array $where = [], string|array $order= [], $limit = 0, $start = 0): ?array;

	public function rselectUnion(array $union, $fields = [], array $where = [], string|array $order= [], $limit = 0, $start = 0): ?array;

	public function iselectUnion(array $union, $fields = [], array $where = [], string|array $order= [], $limit = 0, $start = 0): ?array;

  public function selectOne($table, $field = null, array $where = [], string|array $order= [], int $start = 0);

  public function count($table, array $where = []): ?int;

  public function selectAllByKeys($table, array $fields = [], array $where = [], string|array $order= [], int $limit = 0, int $start = 0): ?array;

  public function stat(string $table, string $column, array $where = [], string|array $order= []): ?array;

  public function getFieldValues($table, string|null $field = null, array $where = [], string|array $order= []): ?array;

  public function countFieldValues($table, string|null $field = null,  array $where = [], string|array $order= []): ?array;

  public function getColumnValues($table, string|null $field = null,  array $where = [], string|array $order= [], int $limit = 0, int $start = 0): ?array;

  public function getValuesCount($table, string|null $field = null, array $where = [], string|array $order= []): array;

}
