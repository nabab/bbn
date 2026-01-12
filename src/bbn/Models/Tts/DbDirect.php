<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use bbn\X;
use bbn\Str;
use stdClass;
use Exception;

trait DbDirect
{
  use DbActions;

  /**
   * @param array|string $id
   * @return bool
   */
  public function exists($filter): bool
  {
    return $this->dbTraitExists($filter);
  }

  /**
   * Inserts a new row in the table.
   *
   * @param array $data
   *
   * @return string|null
   */
  public function insert(array $data, bool $ignore = false): ?string
  {
    return $this->dbTraitInsert($data, $ignore);
  }


  /**
   * Deletes a single row from the table through its id.
   *
   * @param string $id
   *
   * @return bool
   */
  public function delete(string|array $filter, bool $cascade = false): bool
  {
    return $this->dbTraitDelete($filter, $cascade);
  }


  /**
   * Updates a single row in the table through its id.
   *
   * @param array $data
   * @param string|array $filter
   * @param bool $addCfg
   *
   * @return bool
   */
  public function update(string|array $filter, array $data): int
  {
    return $this->dbTraitUpdate($filter, $data);
  }


  /**
   * Retrieves a row as an object from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return mixed
   */
  public function selectOne(string $field, string|array $filter = [], array $order = [])
  {
    return $this->dbTraitSelectOne($field, $filter, $order);
  }


  /**
   * Retrieves a row as an object from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return stdClass|null
   */
  public function select(string|array $filter = [], array $order = [], array $fields = []): ?stdClass
  {
    return $this->dbTraitSelect($filter, $order, $fields);
  }


  /**
   * Retrieves a row as an array from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return array|null
   */
  public function rselect(string|array $filter = [], array $order = [], array $fields = []): ?array
  {
    return $this->dbTraitRselect($filter, $order, $fields);
  }

  public function selectValues(string $field, array $filter = [], array $order = [], int $limit = 0, int $start = 0): array
  {
    return $this->dbTraitSelectValues($field, $filter, $order, $limit, $start);
  }


  /**
   * Returns the number of rows from the table for the given conditions.
   *
   * @param array $filter
   *
   * @return int
   */
  public function count(array $filter = []): int
  {
    return $this->dbTraitCount($filter);
  }


  /**
   * Returns an array of rows as objects from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param int $limit
   * @param int $start
   *
   * @return array
   */
  public function selectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitSelectAll($filter, $order, $limit, $start, $fields);
  }


  /**
   * Returns an array of rows as arrays from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param int $limit
   * @param int $start
   *
   * @return array
   */
  public function rselectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitRselectAll($filter, $order, $limit, $start, $fields);
  }


  /**
   * Returns the eventual relations with the given id
   * @param string $id
   * @param string|null $table
   * @return array|null
   */
  public function getRelations(string $id, string|null $table = null): ?array
  {
    return $this->dbTraitGetRelations($id, $table);
  }


  public function getSearchFilter(string|int $filter, array $cols = [], bool $strict = false): array
  {
    return $this->dbTraitGetSearchFilter($filter, $cols, $strict);
  }


  public function search(array|string $filter, array $cols = [], array $fields = [], array $order = [], bool $strict = false, int $limit = 0, int $start = 0): array
  {
    return $this->dbTraitSearch($filter, $cols, $fields, $order, $strict, $limit, $start);
  }
}
