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
use function array_key_exists;
use function is_string;
use function is_array;

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
      $this->dbTraitGetFilterCfg($cfg)
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

      return (bool)$this->db->delete($cfg['table'], $this->dbTraitGetFilterCfg($filter));
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
  protected function dbTraitUpdate(string|array $filter, array $data): int
  {
    $ccfg = $this->getClassCfg();
    $f = $ccfg['arch'][$this->class_table_index];
    if (!is_array($filter)) {
      $filter = [$f['id'] => $filter];
    }

    if (!$this->dbTraitExists($filter)) {
      throw new Exception(X::_("Impossible to find the given row"));
    }

    if ($data = $this->dbTraitPrepare($data)) {
      if (!empty($f['cfg'])) {
        $col = $f['cfg'];
        if (!empty($data[$col])) {
          if (is_string($data[$col])) {
            $data[$col] = json_decode($data[$col], true);
          }

          $jsonUpdate = 'JSON_SET(IFNULL(' . $this->db->csn($col, true) . ' ,"{}")';
          foreach ($data[$col] as $k => $v) {
            $jsonUpdate .= ', "$.' . $k . '", ' . (is_iterable($v) ? "JSON_EXTRACT('".Str::escapeSquotes(json_encode($v))."', '$')" : ('"'.Str::escapeDquotes($v).'"'));
          }

          $jsonUpdate .= ")";
          $data[$col] = [null, $jsonUpdate];
        }
      }

      return $this->db->update($ccfg['table'], $data, $this->dbTraitGetFilterCfg($filter));
    }

    return 0;
  }

  protected function dbTraitInsertUpdate(array $data): ?string
  {
    $cfg = $this->getClassCfg();
    $keys = $this->db->getUniqueKeys($this->class_cfg['table']);
    $update = false;
    if (!empty($keys)) {
      foreach ($keys as $key => $columns) {
        $checked = array_filter($columns, fn($col) => !array_key_exists($col, $data) || is_null($data[$col]));
        if (empty($checked)) {
          $update = $this->db->selectOne($cfg['table'], $cfg['arch'][$this->class_table_index]['id'], array_intersect_key($data, array_flip($columns)));
          break;
        }
      }
    }
    if ($update) {
      $this->dbTraitUpdate($update, $data);
      return $update;
    }
    else {
      return $this->dbTraitInsert($data);
    }
  }


  /**
   * Retrieves a row as an object from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return mixed
   */
  protected function dbTraitSelectOne(string $field, string|array $filter = [], array $order = [])
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
  protected function dbTraitSelect(string|array $filter = [], array $order = [], array $fields = []): ?stdClass
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
  protected function dbTraitRselect(string|array $filter = [], array $order = [], array $fields = []): ?array
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
   * @param int $limit
   * @param int $start
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
   * @param int $limit
   * @param int $start
   *
   * @return array
   */
  protected function dbTraitRselectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitSelection($filter, $order, $limit, $start, 'array', $fields);
  }

  protected function dbTraitGetRelations(string $id, string|null $table = null): ?array
  {
    if ($this->dbTraitExists($id)) {
      $db =& $this->db;
      $res = [];
      foreach ($this->dbTraitGetTableRelations($table) as $rel) {
        if ($all = $db->getColumnValues($rel['table'], $rel['primary'], [$rel['col'] => $id])) {
          $res[$rel['table']] = [
            'col' => $rel['col'],
            'primary' => $rel['primary'],
            'values' => $all
          ];
        }
      }

      return $res;
    }

    return null;
  }

  protected function dbTraitGetSearchFilter(string|int $filter, array $cols = [], bool $strict = false): array
  {
    $cfg = $this->getClassCfg();
    $isNumber = Str::isNumber($filter);
    $finalFilter = [
      'logic' => 'OR',
      'conditions' => []
    ];
    if (empty($cols)) {
      $tableCols = $this->db->modelize($cfg['table'])['fields'];
      foreach ($tableCols as $col => $colCfg) {
        if ((Str::pos($colCfg['type'], 'text') !== false) || (Str::pos($colCfg['type'], 'char') !== false)) {
          $cols[] = $col;
        }
        elseif ($isNumber && (Str::pos($colCfg['type'], 'int') !== false)) {
          $cols[] = $col;
        }
      }
    }

    foreach ($cols as $col) {
      $finalFilter['conditions'][] = [
        'field' => $this->db->cfn($col, $cfg['table']),
        'operator' => $strict ? '=' : 'contains',
        'value' => $filter
      ];
    }

    return $finalFilter;
  }

  protected function dbTraitSearch(array|string $filter, array $cols = [], array $fields = [], array $order = [], bool $strict = false, int $limit = 0, int $start = 0): array
  {
    if (is_array($filter)) {
      $finalFilter = $filter;
      if (empty($fields) && !empty($cols)) {
        $fields = $cols;
      }
    }
    else {
      $finalFilter = $this->dbTraitGetSearchFilter($filter, $cols);
    }

    return $this->dbTraitRselectAll($finalFilter, $order, $limit, $start, $fields);
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
