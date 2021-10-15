<?php

namespace bbn\Db;

interface SqlFormatters
{
  /**
   * Generates a string starting with SELECT ... FROM with corresponding parameters
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function getSelect(array $cfg): string;


  /**
   * Fetches the database and returns an array of objects
   *
   * @param array $cfg The configuration array
   * @return false|array
   */
  public function getInsert(array $cfg): string;


  /**
   * Fetches the database and returns an array of objects
   *
   * @param array $cfg The configuration array
   * @return false|array
   */
  public function getUpdate(array $cfg): string;


  /**
   * Returns the SQL code for a DELETE statement.
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function getDelete(array $cfg): string;


  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function getJoin(array $cfg): string;


  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function getWhere(array $cfg): string;


  /**
   * Returns a string with the GROUP BY part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function getGroupBy(array $cfg): string;


  /**
   * Returns a string with the HAVING part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function getHaving(array $cfg): string;


  /**
   * Get a string starting with ORDER BY with corresponding parameters to $order
   *
   * @param array $cfg
   * @return string
   */
  public function getOrder(array $cfg): string;


  /**
   * Get a string starting with LIMIT with corresponding parameters to $where
   *
   * @param array $cfg
   * @return string
   */
  public function getLimit(array $cfg): string;


  /**
   * Fetches the database and returns an array of objects
   *
   * @param string $table The table for which to create the statement
   * @param array|null $model
   * @return string
   */
  public function getCreate(string $table, array $model = null): string;

  /**
   * @param string $table
   * @param array|null $model
   * @return string
   */
  public function getCreateTable(string $table, array $model = null): string;

  /**
   * @param string $table
   * @param array|null $model
   * @return string
   */
  public function getCreateKeys(string $table, array $model = null): string;

  /**
   * @param string $table
   * @param array|null $model
   * @return string
   */
  public function getCreateConstraints(string $table, array $model = null): string;


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   */
  public function getAlterTable(string $table, array $cfg): string;


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   */
  public function getAlterColumn(string $table, array $cfg): string;


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   */
  public function getAlterKey(string $table, array $cfg): string;

  /**
   * @param array $cfg
   * @return array
   */
  public function getQueryValues(array $cfg): array;
}