<?php

namespace bbn\Db;

interface SqlEngines
{
  /**
   * Creates an index
   *
   * @param string       $table
   * @param string|array $column
   * @param bool         $unique
   * @param null         $length
   * @return bool
   */
  public function createIndex(string $table, $column, bool $unique = false, $length = null): bool;

  /**
   * Deletes an index
   *
   * @param string $table
   * @param string $key
   * @return bool
   */
  public function deleteIndex(string $table, string $key): bool;

  /**
   * Creates a database user
   *
   * @param string $user
   * @param string $pass
   * @param string|null $db
   * @return bool
   */
  public function createUser(string $user, string $pass, string|null $db = null): bool;

  /**
   * Deletes a database user
   *
   * @param string $user
   * @return bool
   */
  public function deleteUser(string $user): bool;

  /**
   * Return an array including privileges of a specific db_user or all db_users.
   *
   * @param string $user
   * @param string $host
   * @return array
   */
  public function getUsers(string $user = '', string $host = ''): ?array;

  /**
   * Renames the given table to the new given name.
   *
   * @param string $table   The current table's name
   * @param string $newName The new name.
   * @return bool  True if it succeeded
   */
  public function renameTable(string $table, string $newName): bool;

  /**
   * Returns the comment (or an empty string if none) for a given table.
   *
   * @param string $table The table's name
   *
   * @return string The table's comment
   */
  public function getTableComment(string $table): string;

  /**
   * Creates the given column for the given table.
   *
   * @param string $table
   * @param string $column
   * @param array $col
   * @return bool
   */
  public function createColumn(string $table, string $column, array $col): bool;

  /**
   * Drops the given column for the given table.
   *
   * @param string $table
   * @param string $column
   * @return bool
   */
  public function dropColumn(string $table, string $column): bool;
}