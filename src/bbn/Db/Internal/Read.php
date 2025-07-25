<?php

namespace bbn\Db\Internal;

trait Read
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                 READ HELPERS WITH TRIGGERS                   *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Returns the first row resulting from the query as an object.
   *
   * ```php
   * X::dump($db->select('table_users', ['name', 'surname'], [['id','>','2']]));
   * /*
   * (object){
   *   "name": "John",
   *   "surname": "Smith",
   * }
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $start  The "start" condition, default: 0
   * @return null|\stdClass
   */
  public function select($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?\stdClass
  {
    return $this->language->select($table, $fields, $where, $order, $start);
  }


  /**
   * Return table's rows resulting from the query as an array of objects.
   *
   * ```php
   * X::dump($db->selectAll("tab_users", ["id", "name", "surname"],[["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *        Object stdClass: df {
   *          "id" => 2,
   *          "name" => "John",
   *          "surname" => "Smith",
   *          },
   *        Object stdClass: df {
   *          "id" => 3,
   *          "name" => "Thomas",
   *          "surname" => "Jones",
   *         }
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $limit  The "limit" condition, default: 0
   * @param int             $start  The "start" condition, default: 0
   * @return null|array
   */
  public function selectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    return $this->language->selectAll($table, $fields, $where, $order, $limit, $start);
  }


  /**
   * Return the first row resulting from the query as a numeric array.
   *
   * ```php
   * X::dump($db->iselect("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *          4,
   *         "Jack",
   *          "Stewart"
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $start  The "start" condition, default: 0
   * @return array
   */
  public function iselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array
  {
    return $this->language->iselect($table, $fields, $where, $order, $start);
  }


  /**
   * Return the searched rows as an array of numeric arrays.
   *
   * ```php
   * X::dump($db->iselectAll("tab_users", ["id", "name", "surname"], [["id", ">", 1]],["id" => "ASC"],2));
   * /*
   * (array)[
   *          [
   *            2,
   *            "John",
   *            "Smith",
   *          ],
   *          [
   *            3,
   *            "Thomas",
   *            "Jones",
   *          ]
   *        ]
   * ```
   *
   * @param string|array  $table  The table's name or a configuration array
   * @param string|array  $fields The fields's name
   * @param array         $where  The "where" condition
   * @param array|boolean $order The "order" condition, default: false
   * @param int           $limit  The "limit" condition, default: 0
   * @param int           $start  The "start" condition, default: 0
   * @return array
   */
  public function iselectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    return $this->language->iselectAll($table, $fields, $where, $order, $limit, $start);
  }


  /**
   * Return the first row resulting from the query as an indexed array.
   *
   * ```php
   * X::dump($db->rselect("tab_users", ["id", "name", "surname"], ["id", ">", 1], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          "id" => 4,
   *          "name" => "John",
   *          "surname" => "Smith"
   *         ]
   * ```
   *
   * @param string|array  $table  The table's name or a configuration array
   * @param string|array  $fields The fields' name
   * @param array         $where  The "where" condition
   * @param array|boolean $order  The "order" condition, default: false
   * @param int           $start  The "start" condition, default: 0
   * @return null|array
   */
  public function rselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array
  {
    return $this->language->rselect($table, $fields, $where, $order, $start);
  }


  /**
   * Return table's rows as an array of indexed arrays.
   *
   * ```php
   * X::dump($db->rselectAll("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          [
   *          "id" => 2,
   *          "name" => "John",
   *          "surname" => "Smith",
   *          ],
   *          [
   *          "id" => 3,
   *          "name" => "Thomas",
   *          "surname" => "Jones",
   *          ]
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  condition, default: false
   * @param int             $limit  The "limit" condition, default: 0
   * @param int             $start  The "start" condition, default: 0
   * @return null|array
   */
  public function rselectAll($table, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    return $this->language->rselectAll($table, $fields, $where, $order, $limit, $start);
  }

  public function countUnion(array $union, array $where = []): ?int
  {
    return $this->language->countUnion($union, $where);
  }
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
	public function selectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    return $this->language->selectUnion($union, $fields, $where, $order, $limit, $start);
  }

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
	public function rselectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    return $this->language->rselectUnion($union, $fields, $where, $order, $limit, $start);
  }

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
	public function iselectUnion(array $union, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    return $this->language->iselectUnion($union, $fields, $where, $order, $limit, $start);
  }


  /**
   * Return a single value
   *
   * ```php
   * X::dump($db->selectOne("tab_users", "name", [["id", ">", 1]], ["id" => "DESC"], 2));
   *  (string) 'Michael'
   * ```
   *
   * @param string|array    $table The table's name or a configuration array
   * @param string          $field The field's name
   * @param array           $where The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int             $start The "start" condition, default: 0
   * @return mixed
   */
  public function selectOne($table, $field = null, array $where = [], array $order = [], int $start = 0)
  {
    return $this->language->selectOne($table, $field, $where, $order, $start);
  }


  /**
   * Return the number of records in the table corresponding to the $where condition (non mandatory).
   *
   * ```php
   * X::dump($db->count('table_users', ['name' => 'John']));
   * // (int) 2
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array        $where The "where" condition
   * @return int
   */
  public function count($table, array $where = []): ?int
  {
    return $this->language->count($table, $where);
  }


  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   * Return the same value as "get_key_val".
   *
   * ```php
   * X::dump($db->selectAllByKeys("table_users", ["name","id","surname"], [["id", ">", "1"]], ["id" => "ASC"]);
   * /*
   * (array)[
   *        "John" => [
   *          "surname" => "Brown",
   *          "id" => 3
   *          ],
   *        "Michael" => [
   *          "surname" => "Smith",
   *          "id" => 4
   *        ]
   *      ]
   * ```
   *
   * @param string|array  $table  The table's name or a configuration array
   * @param array         $fields The fields's name
   * @param array         $where  The "where" condition
   * @param array|boolean $order  The "order" condition
   * @param int           $limit  The $limit condition, default: 0
   * @param int           $start  The $limit condition, default: 0
   * @return array|false
   */
  public function selectAllByKeys($table, array $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    return $this->language->selectAllByKeys($table, $fields, $where, $order, $limit, $start);
  }


  /**
   * Return an array with the count of values corresponding to the where conditions.
   *
   * ```php
   * X::dump($db->stat('table_user', 'name', ['name' => '%n']));
   * /* (array)
   * [
   *  [
   *      "num" => 1,
   *      "name" => "alan",
   *  ], [
   *      "num" => 1,
   *      "name" => "karen",
   *  ],
   * ]
   * ```
   *
   * @param string|array $table  The table's name or a configuration array.
   * @param string       $column The field's name.
   * @param array        $where  The "where" condition.
   * @param array        $order  The "order" condition.
   * @return array
   */
  public function stat(string $table, string $column, array $where = [], array $order = []): ?array
  {
    return $this->language->stat($table, $column, $where, $order);
  }


  /**
   * Return the unique values of a column of a table as a numeric indexed array.
   *
   * ```php
   * X::dump($db->getFieldValues("table_users", "surname", [['id', '>', '2']]));
   * // (array) ["Smiths", "White"]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|null  $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array | false
   */
  public function getFieldValues($table, string|null $field = null, array $where = [], array $order = []): ?array
  {
    return $this->getColumnValues($table, $field, $where, $order);
  }


  /**
   * Return a count of identical values in a field as array, Reporting a structure type 'num' - 'val'.
   *
   * ```php
   * X::dump($db->countFieldValues('table_users','surname',[['name','=','John']]));
   * // (array) ["num" => 2, "val" => "John"]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param null|string  $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array|null
   */
  public function countFieldValues($table, string|null $field = null,  array $where = [], array $order = []): ?array
  {
    return $this->language->countFieldValues($table, $field, $where, $order);
  }


  /**
   * Return a numeric indexed array with the values of the unique column ($field) from the selected $table
   *
   * ```php
   * X::dump($db->getColumnValues('table_users','surname',['id','>',1]));
   * /*
   * array [
   *    "Smith",
   *    "Jones",
   *    "Williams",
   *    "Taylor"
   * ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|null $field The field's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition
   * @param int $limit
   * @param int $start
   * @return array
   */
  public function getColumnValues($table, string|null $field = null,  array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    return $this->language->getColumnValues($table, $field, $where, $order, $limit, $start);
  }


  /**
   * Return a string with the sql query to count equal values in a field of the table.
   *
   * ```php
   * X::dump($db->getValuesCount('table_users','name',['surname','=','smith']));
   * /*
   * (string)
   *   SELECT COUNT(*) AS num, `name` AS val FROM `db_example`.`table_users`
   *     GROUP BY `name`
   *     ORDER BY `name`
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|null $field The field's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition
   * @return array
   * // TODO-testing: this method stated that it will return string but actually it returns an array!
   */
  public function getValuesCount($table, string|null $field = null, array $where = [], array $order = []): array
  {
    return $this->countFieldValues($table, $field, $where, $order);
  }

}
