<?php

namespace bbn\Db\Internal;

trait Query 
{
  
  /****************************************************************
   *                                                              *
   *                                                              *
   *                       QUERY HELPERS                          *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Executes the given query with given vars, and extracts the first cell's result.
   *
   * ```php
   * X::dump($db->getOne("SELECT name FROM table_users WHERE id>?", 138));
   * // (string) John
   * ```
   *
   * @param string query
   * @param mixed values
   * @return mixed
   */
  public function getOne()
  {
   return $this->language->getOne(...\func_get_args());
  }


  /**
   * Execute the given query with given vars, and extract the first cell's result.
   * (similar to {@link get_one()})
   *
   * ```php
   * X::dump($db->getVar("SELECT telephone FROM table_users WHERE id>?", 1));
   * // (int) 123554154
   * ```
   *
   * @param string query
   * @param mixed values
   * @return mixed
   */
  public function getVar()
  {
    return $this->getOne(...\func_get_args());
  }


  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   *
   * ```php
   * X::dump($db->getKeyVal("SELECT name,id_group FROM table_users"));
   * /*
   * (array)[
   *      "John" => 1,
   *      "Michael" => 1,
   *      "Barbara" => 1
   *        ]
   *
   * X::dump($db->getKeyVal("SELECT name, surname, id FROM table_users WHERE id > 2 "));
   * /*
   * (array)[
   *         "John" => [
   *          "surname" => "Brown",
   *          "id" => 3
   *         ],
   *         "Michael" => [
   *          "surname" => "Smith",
   *          "id" => 4
   *         ]
   *        ]
   * ```
   *
   * @param string query
   * @param mixed values
   * @return null|array
   */
  public function getKeyVal(): ?array
  {
    return $this->language->getKeyVal(...\func_get_args());
  }


  /**
   * Return an array with the values of single field resulting from the query.
   *
   * ```php
   * X::dump($db->getColArray("SELECT id FROM table_users"));
   * /*
   * (array)[1, 2, 3, 4]
   * ```
   *
   * @param string query
   * @param mixed values
   * @return array
   */
  public function getColArray(): array
  {
    return $this->language->getColArray(...\func_get_args());
  }

}