<?php

namespace bbn\Models\Tts;

use Exception;
use stdClass;
use bbn\X;

use function is_array;
use function is_string;
use function count;

trait DbSelection
{
  /**
   * Returns the IDs matching the given filter.
   *
   * Fast paths:
   * - if `$filter` is a string, it is assumed to already be the row ID
   * - if `$filter` is an array containing only the table ID field, that ID is returned directly
   *
   * @param string|array<string, mixed> $filter Filter configuration or a single row ID.
   * @return array<int, string> List of matching row IDs.
   */
  protected function dbTraitGetIds(string|array $filter = []): array
  {
    if (is_string($filter)) {
      return [$filter];
    }

    $cfg = $this->getClassCfg();
    $f = $cfg["arch"][$this->class_table_index];

    // If the filter is exactly "id = X", return it directly without a DB call.
    if (isset($filter[$f['id']]) && count($filter) === 1) {
      return [$filter[$f['id']]];
    }

    $filter = $this->dbTraitGetFilterCfg($filter);

    /** @var array<int, string> $res */
    $res = $this->db->getColumnValues(
      $this->class_table,
      $f['id'],
      $filter,
      [$f['id'] => "ASC"]
    );

    return $res;
  }

  /**
   * Checks whether at least one row exists for the given filter.
   *
   * If `$filter` is a string, it is treated as the row ID.
   *
   * Emits:
   * - `beforeselect`
   *
   * If the event prevents default, its response is cast to bool and returned.
   *
   * @param string|array<string, mixed> $filter Row ID or filter configuration.
   * @return bool True if at least one row exists, false otherwise.
   * @throws Exception If the table index is not defined.
   */
  protected function dbTraitExists(string|array $filter): bool
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $f = $this->class_cfg["arch"][$this->class_table_index];

    if (is_array($filter) && isset($filter[$f['id']]) && count($filter) === 1) {
      $filter = $filter[$f['id']];
    }

    $ev = $this->emit("beforeselect", $filter);
    if ($ev && $ev->isDefaultPrevented()) {
      return (bool)$ev->getResponse();
    }

    if (is_string($filter)) {
      $cfg = [$f['id'] => $filter];
    }
    elseif (is_array($filter)) {
      $cfg = $filter;
    }

    return !empty($cfg)
      && (bool)$this->db->count(
        $this->class_table,
        $this->dbTraitGetFilterCfg($cfg)
      );
  }

  /**
   * Selects a single field value from the first matching row.
   *
   * @param string                    $field  Field name to return.
   * @param string|array<string,mixed> $filter Row ID or filter configuration.
   * @param array<string, string>     $order  Order configuration.
   * @return mixed|null Field value, or null if no row matches.
   */
  protected function dbTraitSelectOne(
    string $field,
    string|array $filter = [],
    string|array $order= [],
  ) {
    if ($res = $this->dbTraitSingleSelection($filter, $order, "array", [$field])) {
      return $res[$field] ?? null;
    }

    return null;
  }

  /**
   * Selects the first matching row and returns it as an object.
   *
   * @param string|array<string, mixed> $filter Row ID or filter configuration.
   * @param array<string, string>       $order  Order configuration.
   * @param array<int, string>          $fields Optional list of fields to return.
   * @return stdClass|null Matching row as object, or null if not found.
   */
  protected function dbTraitSelect(
    string|array $filter = [],
    string|array $order= [],
    array $fields = [],
  ): ?stdClass {
    return $this->dbTraitSingleSelection($filter, $order, "object", $fields);
  }

  /**
   * Selects the first matching row and returns it as an array.
   *
   * @param string|array<string, mixed> $filter Row ID or filter configuration.
   * @param array<string, string>       $order  Order configuration.
   * @param array<int, string>          $fields Optional list of fields to return.
   * @return array<string, mixed>|null Matching row as array, or null if not found.
   */
  protected function dbTraitRselect(
    string|array $filter = [],
    string|array $order= [],
    array $fields = [],
  ): ?array {
    return $this->dbTraitSingleSelection($filter, $order, "array", $fields);
  }

  /**
   * Returns the values of a single field for all matching rows.
   *
   * @param string                $field  Field name to return.
   * @param array<string, mixed>  $filter Filter configuration.
   * @param array<string, string> $order  Order configuration.
   * @param int                   $limit  Maximum number of rows to return.
   * @param int                   $start  Result offset.
   * @return array<int, mixed>
   */
  protected function dbTraitSelectValues(
    string $field,
    array $filter = [],
    string|array $order= [],
    int $limit = 0,
    int $start = 0,
  ): array {
    return $this->dbTraitSelection($filter, $order, $limit, $start, "value", [$field]);
  }

  /**
   * Returns the number of rows matching the given filter.
   *
   * @param array<string, mixed> $filter Filter configuration.
   * @return int Number of matching rows.
   * @throws Exception If the table index is not defined.
   */
  protected function dbTraitCount(array $filter = []): int
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $req = $this->dbTraitGetRequestCfg($filter, [], 1, 0, [
      $this->fields["id"],
    ]);

    return $this->db->count($req);
  }

  /**
   * Returns all matching rows as objects.
   *
   * @param array<string, mixed>  $filter Filter configuration.
   * @param array<string, string> $order  Order configuration.
   * @param int                   $limit  Maximum number of rows to return.
   * @param int                   $start  Result offset.
   * @param array<int, string>    $fields Optional list of fields to return.
   * @return array<int, stdClass>
   */
  protected function dbTraitSelectAll(
    array $filter = [],
    string|array $order= [],
    int $limit = 0,
    int $start = 0,
    $fields = [],
  ): array {
    return $this->dbTraitSelection(
      $filter,
      $order,
      $limit,
      $start,
      "object",
      $fields,
    );
  }

  /**
   * Returns all matching rows as arrays.
   *
   * @param array<string, mixed>  $filter Filter configuration.
   * @param array<string, string> $order  Order configuration.
   * @param int                   $limit  Maximum number of rows to return.
   * @param int                   $start  Result offset.
   * @param array<int, string>    $fields Optional list of fields to return.
   * @return array<int, array<string, mixed>>
   */
  protected function dbTraitRselectAll(
    array $filter = [],
    string|array $order= [],
    int $limit = 0,
    int $start = 0,
    $fields = [],
  ): array {
    return $this->dbTraitSelection(
      $filter,
      $order,
      $limit,
      $start,
      "array",
      $fields,
    );
  }

  /**
   * Returns related rows references for a given row ID.
   *
   * The method inspects relations returned by `dbTraitGetTableRelations()`
   * and gathers the related row primary keys from each relation table.
   *
   * Result format:
   * [
   *   'table_name' => [
   *     'col' => 'foreign_key_column',
   *     'primary' => 'primary_key_column',
   *     'values' => [...]
   *   ]
   * ]
   *
   * @param string      $id    Source row ID.
   * @param string|null $table Optional relation table name to restrict the lookup.
   * @return array<string, array<string, mixed>>|null Related references, or null if the row does not exist.
   */
  protected function dbTraitGetRelations(string $id, ?string $table = null): ?array
  {
    if ($this->dbTraitExists($id)) {
      $db = &$this->db;
      $res = [];

      foreach ($this->dbTraitGetTableRelations($table) as $rel) {
        if (
          $all = $db->getColumnValues($rel["table"], $rel["primary"], [
            $rel["col"] => $id,
          ])
        ) {
          $res[$rel["table"]] = [
            "col" => $rel["col"],
            "primary" => $rel["primary"],
            "values" => $all,
          ];
        }
      }

      return $res;
    }

    return null;
  }

  /**
   * Executes a search and returns matching rows as arrays.
   *
   * If `$filter` is a string, it is converted through `dbTraitGetSearchFilter()`.
   * If `$filter` is already an array, it is assumed to be a full filter configuration.
   *
   * @param array<string, mixed>|string $filter Search term or full filter configuration.
   * @param array<int, string>          $cols   Search columns when `$filter` is scalar.
   * @param array<int, string>          $fields Fields to return.
   * @param array<string, string>       $order  Order configuration.
   * @param bool                        $strict Whether to use strict equality in generated search filters.
   * @param int                         $limit  Maximum number of rows to return.
   * @param int                         $start  Result offset.
   * @return array<int, array<string, mixed>>
   */
  protected function dbTraitSearch(
    array|string $filter,
    array $cols = [],
    array $fields = [],
    string|array $order= [],
    bool $strict = false,
    int $limit = 0,
    int $start = 0,
  ): array {
    if (is_array($filter)) {
      $finalFilter = $filter;
      if (empty($fields) && !empty($cols)) {
        $fields = $cols;
      }
    }
    else {
      $finalFilter = $this->dbTraitGetSearchFilter($filter, $cols, $strict);
    }

    return $this->dbTraitRselectAll(
      $finalFilter,
      $order,
      $limit,
      $start,
      $fields,
    );
  }

  /**
   * Returns an array of rows from the table for the given conditions.
   *
   * @param array $filter Filter conditions.
   * @param string|array $orderOrder by conditions.
   * @param int $limit Maximum number of rows to return.
   * @param int $start Offset of the first row to return.
   * @param string $mode The mode of result ('array', 'object', 'value').
   * @param array $fields Fields to select.
   *
   * @return array The result set.
   */
  protected function dbTraitSelection(
    array $filter,
    array $order,
    int $limit,
    int $start,
    string $mode = 'array',
    array $fields = []
  ): array
  {
    $returnObject = $mode === 'object';
    $go = true;
    if (isset($filter['id']) && (count($filter) === 1)) {
      $o = $this->emit('beforeselect', $filter);
      if ($res = $o->getResponse()) {
        if (!empty($fields)) {
          foreach ($res as $k => $v) {
            if (!in_array($k, $fields)) {
              unset($res[$k]);
            }
          }
        }

        $go = !$o->isDefaultPrevented();
        if ($returnObject) {
          $res = (object)$res;
        }
      }
    }

    if ($go) {
      $req = $this->dbTraitGetRequestCfg($filter, $order, $limit, $start, $fields);
      $method = $mode === 'object' ? 'selectAll' : ($mode === 'value' ? 'getColumnValues' : 'rselectAll');
      $res = $this->db->$method($req);
    }

    if ($res) {
      return $this->dbTraitTransformData($res);
    }

    return [];
  }

  /**
   * Selects the first matching row using `dbTraitSelection()`.
   *
   * @param string|array<string, mixed> $filter Row ID or filter configuration.
   * @param array<string, string>       $order  Order configuration.
   * @param string                      $mode   Selection mode, typically `array` or `object`.
   * @param array<int, string>          $fields Fields to return.
   * @return mixed First matching row, or null if none found.
   */
  protected function dbTraitSingleSelection(
    string|array $filter,
    array $order,
    string $mode = "array",
    array $fields = [],
  ): mixed {
    $f = $this->class_cfg["arch"][$this->class_table_index];

    if (is_string($filter)) {
      $cfg = [$f['id'] => $filter];
    }
    elseif (is_array($filter)) {
      $cfg = $filter;
    }

    if (
      isset($cfg) &&
      ($res = $this->dbTraitSelection($cfg, $order, 1, 0, $mode, $fields))
    ) {
      return $res[0];
    }

    return null;
  }
}
