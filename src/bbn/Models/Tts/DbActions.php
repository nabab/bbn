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

trait DbActions
{
  use DbConfig;
  use DbTrait;

  /**
   * @param array|string $id
   * @return bool
   */
  protected function dbTraitExists($filter): bool
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $f = $this->class_cfg['arch'][$this->class_table_index];
    if (is_string($filter)) {
      $cfg = [$f['id'] => $filter];
    }
    elseif (is_array($filter)) {
      $cfg = $filter;
    }

    if (!empty($cfg) && $this->db->count(
      $this->class_table,
      $this->dbTraitFilterCfg($cfg)
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
  protected function dbTraitInsert(array $data, bool $ignore = false): ?string
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
        return $this->db->lastId();
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
  protected function dbTraitDelete(string|array $filter, bool $cascade = false): bool
  {
    if ($this->dbTraitExists($filter)) {
      $cfg = $this->getClassCfg();
      $f = $cfg['arch'][$this->class_table_index];

      if (!is_array($filter) && !empty($f['id'])) {
        $filter = [$f['id'] => $filter];
      }

      return (bool)$this->db->delete($cfg['table'], $this->dbTraitFilterCfg($filter));
    }

    return false;
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
  protected function dbTraitUpdate(array $data, string|array $filter): bool
  {
    if (!$this->dbTraitExists($filter)) {
      throw new Exception(X::_("Impossible to find the given row"));
    }

    $ccfg = $this->getClassCfg();
    $f = $ccfg['arch'][$this->class_table_index];
    if (!is_array($filter)) {
      $filter = [$f['id'] => $filter];
    }

    if ($data = $this->dbTraitPrepare($data)) {
      if (!empty($f['cfg'])) {
        $col = $f['cfg'];
        if (!empty($data[$col])) {
          $jsonUpdate = 'JSON_SET(' . $this->db->csn($col, true);
          foreach ($data[$col] as $k => $v) {
            $jsonUpdate .= ', "$.' . $k . '", "' . Str::escapeDquotes(is_iterable($v) ? json_encode($v) : $v) . '"';
          }

          $jsonUpdate .= ")";
          $data[$col] = [null, $jsonUpdate];
        }
      }
      
      return (bool)$this->db->update($ccfg['table'], $data, $this->dbTraitFilterCfg($filter));
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
  protected function dbTraitSelectOne(string $field, $filter = [], array $order = [])
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
  protected function dbTraitSelect($filter = [], array $order = [], array $fields = []): ?stdClass
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
  protected function dbTraitRselect($filter = [], array $order = [], array $fields = []): ?array
  {
    return $this->dbTraitSingleSelection($filter, $order, 'array', $fields);
  }

  protected function dbTraitSelectValues(string $field, array $filter = [], array $order = [], int $limit = 0, int $start = 0): array
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
  protected function dbTraitCount(array $filter = []): int
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $req = $this->dbTraitGetRequestCfg($filter, [], 1, 0, [$this->fields['id']]);
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
  protected function dbTraitSelectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
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
  protected function dbTraitRselectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitSelection($filter, $order, $limit, $start, 'array', $fields);
  }

  protected function dbTraitGetRelations(string $id, string $table = null): ?array
  {
    if ($this->dbTraitExists($id)) {
      $db =& $this->db;
      $res = [];
      foreach ($this->dbTraitGetTableRelations($table) as $rel) {
        if ($all = $db->getColumnValues($rel['table'], $rel['primary'], [$rel['col'] => $id])) {
          $res[$rel['table']] = $all;
        }
      }

      return $res;
    }

    return null;
  }

  /**
   * Gets a single row and returns it
   *
   * @param string|array $filter
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
    $f = $this->class_cfg['arch'][$this->class_table_index];
    if (is_string($filter)) {
      $cfg = [$f['id'] => $filter];
    }
    elseif (is_array($filter)) {
      $cfg = $filter;
    }

    if (isset($cfg)
        && ($res = $this->dbTraitSelection($cfg, $order, 1, 0, $mode, $fields))
    ) {
      return $res[0];
    }

    return null;

  }

}
