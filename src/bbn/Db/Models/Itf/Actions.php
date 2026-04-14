<?php

namespace bbn\Db\Models\Itf;

use bbn\Db;
use bbn\Db\Query;

interface Actions
{
  public function getRow(): ?array;

  public function getRows(): ?array;

  public function getIrow(): ?array;

  public function getIrows(): ?array;

  public function getByColumns(): ?array;

  public function getObj(): ?\stdClass;

  public function getObject(): ?\stdClass;

  public function getObjects(): ?array;

  public function charsets(): ?array;

  public function collations(): ?array;

  public function createDatabase(string $database): bool;

  public function dropDatabase(string $database): bool;

  public function renameDatabase(string $oldName, string $newName): bool;

  public function duplicateDatabase(string $source, string $target, bool $withData = true): bool;

  public function getDatabaseCharset(string $database): ?string;

  public function getDatabaseCollation(string $database): ?string;

  public function tableExists(string $table, string $database = ''): bool;

  public function createTable(
    string $table,
    ?array $cfg = null,
    bool $createKeys = true,
    bool $createConstraints = true
  ): bool;

  public function dropTable(string $table, string $database = ''): bool;

  public function duplicateTable(string $source, string $target, bool $withData = true): bool;

  public function copyTableTo(string $table, Db $target, bool $withData = true, string $newName = ''): bool;

  public function getTableCharset(string $table): ?string;

  public function getTableCollation(string $table): ?string;

  public function createColumn(string $table, string $col, array $cfg): bool;

  public function dropColumn(string $table, string $col): bool;

  public function alterColumn(string $table, string $col, array $cfg): bool;

  public function createConstraints(string $table, ?array $cfg = null): bool;

  public function dropConstraint(string $table, string $constraint): bool;

  public function createKeys(string $table, array $cfg): bool;

  public function dropKey(string $table, string $key): bool;

  public function enableLast();

  public function disableLast();

  public function getRealLastParams(): ?array;

  public function realLast(): ?string;

  public function getLastParams(): ?array;

  public function getLastValues(): ?array;

  public function getQuery(array $cfg): Query;

  public function getQueryValues(array $cfg): array;

  public function export4Option($table_name, $database = ''): array;

  public function parseQuery(string $query): ?array;

  public function analyzeDatabase(string $database): bool;

  public function analyzeTable(string $table): bool;
}
