<?php

namespace bbn\Db;

interface EnginesApi
{
  /**
   * Returns the first row resulting from the query as an object.
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array|boolean $order The "order" condition
   * @param int $start The "start" condition, default: 0
   * @return null|\stdClass
   */
  public function select($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?\stdClass;

  /**
   * Fetches a given table and returns an array of a single row text-indexed
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return null|array
   */
  public function selectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array;

  /**
   * Return the first row resulting from the query as a numeric array.
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return array
   */

  public function iselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array;

  /**
   * Return the searched rows as an array of numeric arrays.
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|array $fields The fields's name
   * @param array $where  The "where" condition
   * @param array | boolean The "order" condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return array
   */
  public function iselectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array;

  /**
   * Fetches a given table and returns an array of a single row text-indexed
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array|boolean $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return false|array
   */
  public function rselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array;

  /**
   * Fetches a given table and returns an array of a single row text-indexed
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array | boolean $order condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return null|array
   */
  public function rselectAll($table, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array;

	/**
	 * Fetches a given array of tables and returns an array of text-indexed rows as objects
	 *
   * @param array $union An array of select configurations
   * @param string|array $fields The fields' names
   * @param array $where  The "where" condition
   * @param array | boolean $order condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
	 * @return null|array
	 */
	public function selectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array;

	/**
	 * Fetches a given array of tables and returns an array of text-indexed rows as arrays
	 *
   * @param array $union An array of select configurations
   * @param string|array $fields The fields' names
   * @param array $where  The "where" condition
   * @param array | boolean $order condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
	 * @return null|array
	 */
	public function rselectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array;

	/**
	 * Fetches a given array of tables and returns an array of text-indexed rows as arrays
	 *
   * @param array $union An array of select configurations
   * @param string|array $fields The fields' names
   * @param array $where  The "where" condition
   * @param array | boolean $order condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
	 * @return null|array
	 */
	public function iselectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array;

	/**
	 * Count the result against a given array of tables and returns the number
	 *
   * @param array $union An array of select configurations
   * @param array $where  The "where" condition
	 * @return int
	 */
	public function countUnion(array $union, array $where = []): ?int;

  /**
   * Fetches a given table and returns an array of a single row text-indexed
   *
   * @param string $table The table name.
   * @param string $field The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * @return mixed
   */
  public function selectOne($table, $field = null, array $where = [], array $order = [], int $start = 0);

  /**
   * Return the number of records in the table corresponding to the $where condition (non mandatory).
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $where The "where" condition
   * @return null|int
   */
  public function count($table, array $where = []): ?int;

  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   * Return the same value as "get_key_val".
   *
   * @param string|array $table The table's name or a configuration array
   * @param array $fields The fields's name
   * @param array $where The "where" condition
   * @param array|boolean $order The "order" condition
   * @param int $limit The $limit condition, default: 0
   * @param int $start The $limit condition, default: 0
   * @return null|array
   */
  public function selectAllByKeys($table, array $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array;

  /**
   * Return an array with the count of values corresponding to the where conditions.
   *
   * @param string|array $table The table's name or a configuration array.
   * @param string $column The field's name.
   * @param array $where The "where" condition.
   * @param array $order The "order" condition.
   * @return null|array
   */
  public function stat(string $table, string $column, array $where = [], array $order = []): ?array;

  /**
   * Inserts/Updates rows in the a given table
   *
   * @param $table
   * @param array|null $values
   * @param bool $ignore
   * @return int
   */
  public function insert($table, array|null $values = null, bool $ignore = false): ?int;

  /**
   * Inserts/Updates rows in the a given table
   *
   * @return int
   */
  public function insertUpdate($table, array|null $values = null): ?int;

  /**
   * Updates rows in the a given table
   *
   * @param array|string $table
   * @param array|null $values
   * @param array|null $where
   * @param bool $ignore
   * @return int
   */
  public function update($table, array|null $values = null, array|null $where = null, bool $ignore = false): ?int;

  /**
   * Deletes rows in the a given table
   *
   * @return int
   */
  public function delete($table, array $where, bool $ignore = false): ?int;
}