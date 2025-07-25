<?php

namespace bbn\Db\Internal;

trait Write 
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                 WRITE HELPERS WITH TRIGGERS                  *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Inserts row(s) in a table.
   *
   * <code>
   * $db->insert("table_users", [
   *    ["name" => "Ted"],
   *    ["surname" => "McLow"]
   *  ]);
   * </code>
   *
   * <code>
   * $db->insert("table_users", [
   *    ["name" => "July"],
   *    ["surname" => "O'neill"]
   *  ], [
   *    ["name" => "Peter"],
   *    ["surname" => "Griffin"]
   *  ], [
   *    ["name" => "Marge"],
   *    ["surname" => "Simpson"]
   *  ]);
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The values to insert.
   * @param bool $ignore If true, controls if the row is already existing and ignores it.
   *
   * @return int Number affected rows.
   */
  public function insert($table, array|null $values = null, bool $ignore = false): ?int
  {
    return $this->language->insert($table, $values, $ignore);
  }


  /**
   * If not exist inserts row(s) in a table, else update.
   *
   * <code>
   * $db->insertUpdate(
   *  "table_users",
   *  [
   *    'id' => '12',
   *    'name' => 'Frank'
   *  ]
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The values to insert.
   *
   * @return int The number of rows inserted or updated.
   */
  public function insertUpdate($table, array|null $values = null): ?int
  {
    return $this->language->insertUpdate($table, $values);
  }


  /**
   * Updates row(s) in a table.
   *
   * <code>
   * $db->update(
   *  "table_users",
   *  [
   *    ['name' => 'Frank'],
   *    ['surname' => 'Red']
   *  ],
   *  ['id' => '127']
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The new value(s).
   * @param array|null $where The "where" condition.
   * @param boolean $ignore If IGNORE should be added to the statement
   *
   * @return int The number of rows updated.
   */
  public function update($table, array|null $values = null, array|null $where = null, bool $ignore = false): ?int
  {
    return $this->language->update($table, $values, $where, $ignore);
  }


  /**
   * If exist updates row(s) in a table, else ignore.
   *
   * <code>
   * $db->updateIgnore(
   *   "table_users",
   *   [
   *     ['name' => 'Frank'],
   *     ['surname' => 'Red']
   *   ],
   *   ['id' => '20']
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values
   * @param array|null $where The "where" condition.
   *
   * @return int The number of rows deleted.
   */
  public function updateIgnore($table, array|null $values = null, array|null $where = null): ?int
  {
    return $this->update($table, $values, $where, true);
  }


  /**
   * Deletes row(s) in a table.
   *
   * <code>
   * $db->delete("table_users", ['id' => '32']);
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $where The "where" condition.
   * @param bool $ignore default: false.
   *
   * @return int The number of rows deleted.
   */
  public function delete($table, array|null $where = null, bool $ignore = false): ?int
  {
    return $this->language->delete($table, $where, $ignore);
  }


  /**
   * If exist deletes row(s) in a table, else ignore.
   *
   * <code>
   * $db->deleteIgnore(
   *  "table_users",
   *  ['id' => '20']
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $where The "where" condition.
   *
   * @return int The number of rows deleted.
   */
  public function deleteIgnore($table, array|null $where = null): ?int
  {
    return $this->delete(\is_array($table) ? array_merge($table, ['ignore' => true]) : $table, $where, true);
  }


  /**
   * If not exist inserts row(s) in a table, else ignore.
   *
   * <code>
   * $db->insertIgnore(
   *  "table_users",
   *  [
   *    ['id' => '19', 'name' => 'Frank'],
   *    ['id' => '20', 'name' => 'Ted'],
   *  ]
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The row(s) values.
   *
   * @return int The number of rows inserted.
   */
  public function insertIgnore($table, array|null $values = null): ?int
  {
    return $this->insert(\is_array($table) ? array_merge($table, ['ignore' => true]) : $table, $values, true);
  }


  /**
   * @param $table
   * @return int|null
   */
  public function truncate($table): ?int
  {
    return $this->delete($table, []);
  }

}
