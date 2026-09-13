<?php

namespace bbn\Db\Sub;

use bbn\Db\Models\Cls\Sub;
use bbn\Db\Models\Itf\Native as ItfNative;

class Native extends Sub implements ItfNative
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                      NATIVE FUNCTIONS                        *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return an indexed array with the first result of the query or false if there are no results.
   *
   * ```php
   * X::dump($db->fetch("SELECT name FROM users WHERE id = 10"));
   * /* (array)
   * [
   *  "name" => "john",
   *  0 => "john",
   * ]
   * ```
   *
   * @param string $query
   * @param mixed $additionalArgs
   * @return array|false
   */
  public function fetch(string $query, ...$additionalArgs)
  {
    return $this->language->fetch($query, ...$additionalArgs);
  }


  /**
   * Return an array of indexed array with all results of the query or false if there are no results.
   *
   * ```php
   * X::dump($db->fetchAll("SELECT 'surname', 'name', 'id' FROM users WHERE name = 'john'"));
   * /* (array)
   *  [
   *    [
   *    "surname" => "White",
   *    0 => "White",
   *    "name" => "Michael",
   *    1 => "Michael",
   *    "id"  => 1,
   *    2 => 1,
   *    ],
   *    [
   *    "surname" => "Smith",
   *    0 => "Smith",
   *    "name" => "John",
   *    1  =>  "John",
   *    "id" => 2,
   *    2 => 2,
   *    ],
   *  ]
   * ```
   *
   * @param string $query
   * @param mixed $additionalArgs
   * @return array|false
   */
  public function fetchAll(string $query, ...$additionalArgs)
  {
    return $this->language->fetchAll($query, ...$additionalArgs);
  }


  /**
   * Transposition of the original fetchColumn method, but with the query included. Return an array or false if no result
   * @todo confusion between result's index and this->query arguments(IMPORTANT). Missing the example because the function doesn't work
   *
   * @param $query
   * @param int   $num
   * @param mixed $additionalArgs
   * @return mixed
   */
  public function fetchColumn(string $query, int $num = 0, ...$additionalArgs)
  {
    return $this->language->fetchColumn($query, $num, ...$additionalArgs);
  }


  /**
   * Return stdClass object or false if no result.
   *
   * ```php
   * X::dump($db->fetchObject("SELECT * FROM table_users WHERE name = 'john'"));
   * // stdClass Object {
   *                    "id"  =>  1,
   *                    "name"  =>  "John",
   *                    "surname"  =>  "Smith",
   *                    }
   * ```
   *
   * @param string $query
   * @param mixed $additionalArgs
   * @return bool|\stdClass
   */
  public function fetchObject(string $query, ...$additionalArgs)
  {
    return $this->language->fetchObject($query, ...$additionalArgs);
  }


  /**
   * Executes a writing statement and return the number of affected rows or return a query object for the reading * statement
   * @todo far vedere a thomams perche non funziona in lettura
   *
   * ```php
   * X::dump($db->query("DELETE FROM table_users WHERE name LIKE '%lucy%'"));
   * // (int) 3
   * X::dump($db->query("SELECT * FROM table_users WHERE name = 'John"));
   * // (bbn\Db\Query) Object
   * ```
   *
   * @param array|string $statement
   * @param mixed $additionalArgs
   * @return false|int|Query
   */
  public function query(string $statement, ...$additionalArgs)
  {
    if ($this->db->check()) {
      return $this->language->query($statement, ...$additionalArgs);
    }

    return false;
  }


  public function executeStatement(string $statement)
  {
    if ($this->db->check()) {
      return $this->language->executeStatement($statement);
    }

    return null;
  }


  /**
   * @param string $st
   * @return string
   * @throws Exception
   */
  public function rawQuery(string $st)
  {
    return $this->language->rawQuery($st);
  }

}

