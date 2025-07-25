<?php

namespace bbn\Db\Internal;

trait Shortcuts
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                          SHORTCUTS                           *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return table's full name.
   * (similar to {@link table_full_name()})
   *
   * ```php
   * X::dump($db->tfn("table_users"));
   * // (string) work_db.table_users
   * X::dump($db->tfn("table_users", true));
   * // (string) `work_db`.`table_users`
   * ```
   *
   * @param string $table   The table's name
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function tfn(string $table, bool $escaped = false): ?string
  {
    return $this->tableFullName($table, $escaped);
  }


  /**
   * Return table's simple name.
   * (similar to {@link table_simple_name()})
   *
   * ```php
   * X::dump($db->tsn("work_db.table_users"));
   * // (string) table_users
   * X::dump($db->tsn("work_db.table_users", true));
   * // (string) `table_users`
   * ```
   *
   * @param string $table   The table's name
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function tsn(string $table, bool $escaped = false): ?string
  {
    return $this->tableSimpleName($table, $escaped);
  }


  /**
   * Return column's full name.
   * (similar to {@link col_full_name()})
   *
   * ```php
   * X::dump($db->cfn("name", "table_users"));
   * // (string)  table_users.name
   * X::dump($db->cfn("name", "table_users", true));
   * // (string) \`table_users\`.\`name\`
   * ```
   *
   * @param string $col     The column's name (escaped or not).
   * @param string|null $table   The table's name (escaped or not).
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function cfn(string $col, ?string $table = null, bool $escaped = false): ?string
  {
    return $this->colFullName($col, $table, $escaped);
  }


  /**
   * Return the column's simple name.
   * (similar to {@link col_simple_name()})
   *
   * ```php
   * X::dump($db->csn("table_users.name"));
   * // (string) name
   * X::dump($db->csn("table_users.name", true));
   * // (string) `name`
   * ```
   *
   * @param string $col     The column's complete name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function csn(string $col, bool $escaped = false): ?string
  {
    return $this->colSimpleName($col, $escaped);
  }

}

