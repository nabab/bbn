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

trait DbJunction
{
  use DbConfig;
  use DbTrait;

  protected $rootFilterCfg = [];

  private $dbJunctionStructure = [];

  private $dbTraitRelations = [];


  /**
   * @param array|string $id
   * @return bool
   */
  public function dbTraitExists(string|array $filter): bool
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $f = $this->class_cfg['arch'][$this->class_table_index];
    if (!empty($filter) && $this->db->count(
      $this->class_table,
      $this->dbTraitGetFilterCfg($filter)
    )) {
      return true;
    }

    return false;
  }

  /**
   * Inserts a new row in the table.
   *
   * @param array $data
   *
   * @return string|null
   */
  public function dbTraitInsert(array $data, bool $ignore = false): ?string
  {
    if ($data = $this->dbTraitPrepare($data)) {
      $ccfg = $this->getClassCfg();
      if (!empty($ccfg['arch'][$this->class_table_index]['cfg'])) {
        $col = $ccfg['arch'][$this->class_table_index]['cfg'];
        if (isset($data[$col])) {
          $data[$col] = json_encode($data[$col]);
        }
      }

      if ($this->db->{$ignore ? 'insertIgnore' : 'insert'}($ccfg['table'], $data)) {
        return $this->dbTraitRselect($data);
      }
    }

    return null;
  }


  /**
   * Deletes a single row from the table through its id.
   *
   * @param string $id
   *
   * @return bool
   */
  public function dbTraitDelete(array $filter, bool $cascade = false): bool
  {
    if ($this->dbTraitExists($filter)) {
      $cfg = $this->getClassCfg();
      $f = $cfg['arch'][$this->class_table_index];

      return (bool)$this->db->delete($cfg['table'], $this->dbTraitGetFilterCfg($filter));
    }

    return false;
  }


  /**
   * Updates a single row in the table through its id.
   *
   * @param string $id
   * @param array $data
   *
   * @return bool
   */
  public function dbTraitUpdate(string|array $filter, array $data, bool $addCfg = false): bool
  {
    if (!$this->dbTraitExists($filter)) {
      throw new Exception(X::_("Impossible to find the given row"));
    }

    $ccfg = $this->getClassCfg();
    $f = $ccfg['arch'][$this->class_table_index];
    if ($data = $this->dbTraitPrepare($data)) {
      if (!empty($f['cfg'])) {
        $col = $f['cfg'];
        if (!empty($data[$col])) {
          $jsonUpdate = 'JSON_SET(IFNULL(' . $this->db->csn($col, true) . ' ,"{}")';
          foreach ($data[$col] as $k => $v) {
            $jsonUpdate .= ', "$.' . $k . '", ' . (is_iterable($v) ? "JSON_EXTRACT('".Str::escapeSquotes(json_encode($v))."', '$')" : ('"'.Str::escapeDquotes($v).'"'));
          }

          $jsonUpdate .= ")";
          $data[$col] = [null, $jsonUpdate];
        }
      }

      return (bool)$this->db->update($ccfg['table'], $data, $this->dbTraitGetFilterCfg($filter));
    }

    return false;
  }


  /**
   * Retrieves a row as an object from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return mixed
   */
  public function dbTraitSelectOne(string $field, string|array $filter = [], array $order = [])
  {
    if ($res = $this->dbTraitSingleSelection($filter, $order, 'array', [$field])) {
      return $res[$field] ?? null;
    }

    return null;
  }


  /**
   * Retrieves a row as an object from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return stdClass|null
   */
  public function dbTraitSelect(string|array $filter = [], array $order = [], array $fields = []): ?stdClass
  {
    return $this->dbTraitSingleSelection($filter, $order, 'object', $fields);
  }


  /**
   * Retrieves a row as an array from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return array|null
   */
  public function dbTraitRselect(string|array $filter = [], array $order = [], array $fields = []): ?array
  {
    return $this->dbTraitSingleSelection($filter, $order, 'array', $fields);
  }

  public function dbTraitSelectValues(string $field, string|array $filter = [], array $order = [], int $limit = 0, int $start = 0): array
  {
    return $this->dbTraitSelection($filter, $order, $limit, $start, 'value', [$field]);
  }


  /**
   * Returns the number of rows from the table for the given conditions.
   *
   * @param array $filter
   *
   * @return int
   */
  public function dbTraitCount(array $filter = []): int
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $req = $this->dbTraitGetRequestCfg($filter, [], 1, 0, []);
    return $this->db->count($req);
  }


  /**
   * Returns an array of rows as objects from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param array $limit
   * @param array $start
   *
   * @return array
   */
  public function dbTraitSelectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitSelection($filter, $order, $limit, $start, 'object', $fields);
  }


  /**
   * Returns an array of rows as arrays from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param array $limit
   * @param array $start
   *
   * @return array
   */
  public function dbTraitRselectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitSelection($filter, $order, $limit, $start, 'array', $fields);
  }

  /**
   * Gets a single row and returns it
   *
   * @param [type] $filter
   * @param array $order
   * @param string $mode
   * @return mixed
   */
  private function dbTraitSingleSelection(
    string|array $filter,
    array $order,
    string $mode = 'array',
    array $fields = []
  ): mixed
  {
    if (is_string($filter) || is_int($filter)) {
      $filter = [$this->fields['id'] => $filter];
    }

    if ($res = $this->dbTraitSelection($filter, $order, 1, 0, $mode, $fields)) {
      return $res[0];
    }

    return null;

  }
}

