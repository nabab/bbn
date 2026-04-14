<?php

namespace bbn\Db\Models\Itf;

use bbn\Db;

interface Engine
{
  public function postCreation();

  public function change(string $db): Db;

  public function escape(string $item): string;

  public function tableFullName(string $table, bool $escaped = false): ?string;

  public function isTableFullName(string $table): bool;

  public function isColFullName(string $col): bool;

  public function tableSimpleName(string $table, bool $escaped = false): ?string;

  public function colFullName(string $col, ?string $table = null, bool $escaped = false): ?string;

  public function colSimpleName(string $col, bool $escaped = false): ?string;

  public function setTimezone(string $tz): Db;

  public function disableKeys(): Db;

  public function enableKeys(): Db;

  public function getDatabases(): ?array;

  public function getTables(string $database = ''): ?array;

  public function getColumns(string $table): ?array;

  public function getKeys(string $table): ?array;

  public function getConditions(array $conditions, array $cfg = [], bool $is_having = false, int $indent = 0): string;

  public function getSelect(array $cfg, bool $subCfg = false): string;

  public function getUnion(array $cfg): string;

  public function getInsert(array $cfg): string;

  public function getUpdate(array $cfg): string;

  public function getDelete(array $cfg): string;

  public function getJoin(array $cfg, array|null $join = null): string;

  public function getWhere(array $cfg): string;

  public function getGroupBy(array $cfg): string;

  public function getHaving(array $cfg): string;

  public function getOrder(array $cfg): string;

  public function getLimit(array $cfg): string;

  public function getCreate(string $table, array|null $model = null): string;

  public function getCreateTable(string $table, ?array $cfg = null): string;

  public function getCreateTableRaw(
    string $table,
    ?array $cfg = null,
    $createKeys = true,
    $createConstraints = true
  ): string;

  public function getCreateKeys(string $table, array|null $model = null): string;

  public function getCreateConstraints(string $table, array|null $model = null): string;

  public function createIndex(string $table, $column, bool $unique = false, ?int $length = null): bool;

  public function deleteIndex(string $table, string $key): bool;

  public function getAlterTable(string $table, array $cfg): string;

  public function getAlterColumn(string $table, array $cfg): string;

  public function getAlterKey(string $table, array $cfg): string;

  public function alter(string $table, array $cfg): int;

  public function moveColumn(string $table, string $column, array $cfg, string|null $after = null): int;

  public function createUser(string|null $user = null, string|null $pass = null, string|null $db = null): bool;

  public function deleteUser(string $user): bool;

  public function getUsers(string $user = '', string $host = ''): ?array;

  public function renameTable(string $table, string $newName): bool;

  public function getTableComment(string $table): string;

  public function dbSize(string $database = '', string $type = ''): int;

  public function tableSize(string $table, string $type = ''): int;

  public function status(string $table = '', string $database = '');

  public function getUid(): ?string;
}
