<?php

namespace bbn\Db\Models\Itf;

interface Structure
{
  public function getFieldsList($tables): array;

  public function getForeignKeys(string $col, string $table, string|null $db = null): array;

  public function hasIdIncrement(string $table): bool;

  public function modelize($table = null, bool $force = false): ?array;

  public function convert(array $cfg, string $engine): array;

  public function getColMaxLength(string $column, string|null $table = null): ?int;

  public function fmodelize(string $table = '', bool $force = false): ?array;

  public function findReferences($column, string $db = ''): array;

  public function findRelations($column, string $db = ''): ?array;

  public function getPrimary(string $table): array;

  public function getSinglePrimary(string $table): ?string;

  public function getUniquePrimary(string $table): ?string;

  public function getUniqueKeys(string $table): array;

  public function setDatabaseCharset(string $database, string $charset, string $collation): bool;

  public function setTableCharset(string $table, string $charset, string $collation): bool;

  public function setColumnCharset(string $table, string $column, string $charset, string $collation): bool;

}
