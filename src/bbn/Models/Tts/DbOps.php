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
use bbn\Db;
use bbn\Mvc;
use bbn\Mvc\Controller;
use bbn\Mvc\Model;
use stdClass;
use Exception;
use ReflectionProperty;
use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Database actions trait for "regular" (non-junction) tables.
 *
 * Provides CRUD helpers built on top of DbTrait
 */
trait DbOps
{
  use DbTrait;
  /**
   * Returns the matching ids for the given filter/order.
   *
   * Fast paths:
   * - If $filter is a string, it's assumed to be the id.
   * - If $filter is an array containing only the id column, returns that id.
   *
   * @param string|array $filter Filter configuration or a single id.
   * @param array        $order Order configuration.
   * @return array The list of ids matching the query.
   */
  protected function dbTraitGetIds(string|array $filter = []): array
  {
    if (is_string($filter)) {
      return [$filter];
    }

    $cfg = $this->getClassCfg();
    $f = $cfg["arch"][$this->class_table_index];
    // If the filter is exactly "id = X", return it directly without a DB call.
    if (isset($filter[$f["id"]]) && count($filter) === 1) {
      return [$filter[$f["id"]]];
    }

    $filter = $this->dbTraitGetFilterCfg($filter);
    /** @var array $res */
    $res = $this->db->getColumnValues($this->class_table, $f["id"], $filter, [
      $f["id"] => "ASC",
    ]);

    return $res;
  }

  /**
   * Checks whether at least one row exists for the given filter.
   *
   * If $filter is a string, it's treated as the row id.
   *
   * @param string|array $filter Row id or filter configuration.
   * @return bool True if at least one row exists, false otherwise.
   */
  protected function dbTraitExists(string|array $filter): bool
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $f = $this->class_cfg["arch"][$this->class_table_index];
    if (isset($filter["id"]) && count($filter) === 1) {
      $filter = $filter["id"];
    }

    $ev = $this->emit("beforeselect", $filter);
    if ($ev && $ev->isDefaultPrevented()) {
      return (bool) $ev->response();
    }
    if (is_string($filter)) {
      $cfg = [$f["id"] => $filter];
    } elseif (is_array($filter)) {
      $cfg = $filter;
    }

    return !empty($cfg) &&
      (bool) $this->db->count(
        $this->class_table,
        $this->dbTraitGetFilterCfg($cfg),
      );
  }

  /**
   * Inserts a new row into the table.
   *
   * Handles JSON cfg column if configured in the table arch.
   *
   * @param array $data   The row data.
   * @param bool  $ignore Whether to ignore insert errors (insertIgnore).
   * @return string|null The inserted row id, or null on failure.
   */
  protected function dbTraitInsert(array $data, bool $ignore = false): ?string
  {
    if ($data = $this->dbTraitPrepare($data)) {
      $ccfg = $this->getClassCfg();

      // Encode "cfg" column as JSON if configured.
      if (!empty($ccfg["arch"][$this->class_table_index]["cfg"])) {
        $col = $ccfg["arch"][$this->class_table_index]["cfg"];
        if (isset($data[$col])) {
          $data[$col] = json_encode($data[$col]);
        }
      }

      $o = $this->emit("beforeinsert", $data);
      if (
        !$o->isDefaultPrevented() &&
        $this->db->{$ignore ? "insertIgnore" : "insert"}(
          $this->class_table,
          $data,
        )
      ) {
        $id = $this->db->lastId();
        $this->emit("afterinsert", $id);
        return $id;
      }
    }

    return null;
  }

  /**
   * Deletes row(s) from the table.
   *
   * Accepts a string id or a filter array.
   * When caching is enabled:
   *
   * Optionally supports cascade deletion on related tables.
   *
   * @param string|array $filter  Row id or filter configuration.
   * @param bool         $cascade Whether to cascade delete relations.
   * @return int Number of deleted rows.
   */
  protected function dbTraitDelete(
    string|array $filter,
    bool $cascade = false,
  ): int {
    if ($this->dbTraitExists($filter)) {
      $cfg = $this->getClassCfg();
      $f = $cfg["arch"][$this->class_table_index];

      if (!is_array($filter) && !empty($f["id"])) {
        $filter = [$f["id"] => $filter];
      }

      $o = $this->emit("beforedelete", [$filter, $cascade]);
      if (
        !$o->isDefaultPrevented() &&
        ($res = $this->db->delete(
          $this->class_table,
          $this->dbTraitGetFilterCfg($filter),
        ))
      ) {
        if ($cascade) {
          foreach ($this->dbTraitGetTableRelations() as $rel) {
            $this->db->delete($rel["table"], [
              $rel["col"] => is_array($filter) ? $filter[$f["id"]] : $filter,
            ]);
          }
        }

        $o = $this->emit("afterdelete", [$filter, $cascade]);
        return $o->response() ?: $res;
      }
    }

    return 0;
  }

  /**
   * Updates row(s) in the table.
   *
   * Accepts a string id or a filter array.
   * Handles JSON cfg partial updates if configured in the table arch.
   *
   * @param string|array $filter Row id or filter configuration.
   * @param array        $data   The data to update.
   * @return int Number of updated rows.
   */
  protected function dbTraitUpdate(string|array $filter, array $data): int
  {
    $ccfg = $this->getClassCfg();
    $f = $ccfg["arch"][$this->class_table_index];

    if (!is_array($filter)) {
      $filter = [$f["id"] => $filter];
    }

    if (!$this->dbTraitExists($filter)) {
      throw new Exception(X::_("Impossible to find the given row"));
    }

    if ($data = $this->dbTraitPrepare($data)) {
      // JSON cfg partial update support
      if (!empty($f["cfg"])) {
        $col = $f["cfg"];
        if (!empty($data[$col])) {
          if (is_string($data[$col])) {
            $data[$col] = json_decode($data[$col], true);
          }

          $jsonUpdate =
            "JSON_SET(IFNULL(" . $this->db->csn($col, true) . ' ,"{}")';
          foreach ($data[$col] as $k => $v) {
            $jsonUpdate .=
              ', "$.' .
              $k .
              '", ' .
              (is_iterable($v)
                ? "JSON_EXTRACT('" .
                  Str::escapeSquotes(json_encode($v)) .
                  "', '$')"
                : '"' . Str::escapeDquotes($v) . '"');
          }

          $jsonUpdate .= ")";
          $data[$col] = [null, $jsonUpdate];
        }
      }

      $f = $this->dbTraitGetFilterCfg($filter);
      $o = $this->emit("beforeupdate", [$f, $data]);

      if (
        !$o->isDefaultPrevented() &&
        ($res = $this->db->update(
          $this->class_table,
          $data,
          $f,
        ))
      ) {
        $o = $this->emit("afterupdate", [$f, $data]);
        return $o->response() ?: $res;
      }
    }

    return 0;
  }

  /**
   * Inserts or updates a row based on unique keys.
   *
   * Checks the table unique keys; if all columns of a key are provided,
   * tries to find an existing row id and updates it; otherwise inserts.
   *
   * @param array $data The row data.
   * @return string|null The row id (existing or newly inserted), or null on failure.
   */
  protected function dbTraitInsertUpdate(array $data): ?string
  {
    $cfg = $this->getClassCfg();
    $keys = $this->db->getUniqueKeys($this->class_table);
    $update = false;

    if (!empty($keys)) {
      foreach ($keys as $columns) {
        $checked = array_filter(
          $columns,
          fn($col) => !array_key_exists($col, $data) || is_null($data[$col]),
        );

        if (empty($checked)) {
          $update = $this->db->selectOne(
            $this->class_table,
            $cfg["arch"][$this->class_table_index]["id"],
            array_intersect_key($data, array_flip($columns)),
          );
          break;
        }
      }
    }

    if ($update) {
      $this->dbTraitUpdate($update, $data);
      return $update;
    }

    return $this->dbTraitInsert($data);
  }

  /**
   * Selects a single value from the first matching row.
   *
   * @param string       $field  The field to return.
   * @param string|array $filter Row id or filter configuration.
   * @param array        $order  Order configuration.
   * @return mixed The field value, or null if not found.
   */
  protected function dbTraitSelectOne(
    string $field,
    string|array $filter = [],
    array $order = [],
  ) {
    if (
      $res = $this->dbTraitSingleSelection($filter, $order, "array", [$field])
    ) {
      return $res[$field] ?? null;
    }

    return null;
  }

  /**
   * Selects a single row and returns it as an object.
   *
   * @param string|array $filter Row id or filter configuration.
   * @param array        $order  Order configuration.
   * @param array        $fields Optional list of fields to return.
   * @return stdClass|null The row as an object, or null if not found.
   */
  protected function dbTraitSelect(
    string|array $filter = [],
    array $order = [],
    array $fields = [],
  ): ?stdClass {
    return $this->dbTraitSingleSelection($filter, $order, "object", $fields);
  }

  /**
   * Selects a single row and returns it as an array.
   *
   * @param string|array $filter Row id or filter configuration.
   * @param array        $order  Order configuration.
   * @param array        $fields Optional list of fields to return.
   * @return array|null The row as an array, or null if not found.
   */
  protected function dbTraitRselect(
    string|array $filter = [],
    array $order = [],
    array $fields = [],
  ): ?array {
    return $this->dbTraitSingleSelection($filter, $order, "array", $fields);
  }

  /**
   * Returns an array of values for a single field, matching the given conditions.
   *
   * @param string $field The field to return.
   * @param array  $filter Filter configuration.
   * @param array  $order Order configuration.
   * @param int    $limit Max number of rows.
   * @param int    $start Offset.
   * @return array
   */
  protected function dbTraitSelectValues(
    string $field,
    array $filter = [],
    array $order = [],
    int $limit = 0,
    int $start = 0,
  ): array {
    return $this->dbTraitSelection($filter, $order, $limit, $start, "value", [
      $field,
    ]);
  }

  /**
   * Returns the number of rows matching the given conditions.
   *
   * @param array $filter Filter configuration.
   * @return int
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
   * Returns an array of rows as objects matching the given conditions.
   *
   * @param array $filter Filter configuration.
   * @param array $order Order configuration.
   * @param int   $limit Max number of rows.
   * @param int   $start Offset.
   * @param array $fields Optional list of fields to return.
   * @return array
   */
  protected function dbTraitSelectAll(
    array $filter = [],
    array $order = [],
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
   * Returns an array of rows as arrays matching the given conditions.
   *
   * @param array $filter Filter configuration.
   * @param array $order Order configuration.
   * @param int   $limit Max number of rows.
   * @param int   $start Offset.
   * @param array $fields Optional list of fields to return
   * @return array
   */
  protected function dbTraitRselectAll(
    array $filter = [],
    array $order = [],
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
   * Returns relations for a given row id.
   *
   * @param string      $id    The row id.
   * @param string|null $table Optional related table name to restrict results.
   * @return array|null
   */
  protected function dbTraitGetRelations(
    string $id,
    string|null $table = null,
  ): ?array {
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
   * Builds a filter configuration for a simple search string.
   *
   * If $cols is empty, it will scan the table model and select:
   * - text/char columns
   * - int columns if the filter looks numeric
   *
   * @param string|int $filter Search term.
   * @param array      $cols   Columns to search on.
   * @param bool       $strict If true, uses '='; otherwise uses 'contains'.
   * @return array The filter configuration.
   */
  protected function dbTraitGetSearchFilter(
    string|int $filter,
    array $cols = [],
    bool $strict = false,
  ): array {
    $cfg = $this->getClassCfg();
    $isNumber = Str::isNumber($filter);

    $finalFilter = [
      "logic" => "OR",
      "conditions" => [],
    ];

    if (empty($cols)) {
      $tableCols = $this->db->modelize($cfg["table"])["fields"];
      foreach ($tableCols as $col => $colCfg) {
        if (
          Str::pos($colCfg["type"], "text") !== false ||
          Str::pos($colCfg["type"], "char") !== false
        ) {
          $cols[] = $col;
        } elseif ($isNumber && Str::pos($colCfg["type"], "int") !== false) {
          $cols[] = $col;
        }
      }
    }

    foreach ($cols as $col) {
      $finalFilter["conditions"][] = [
        "field" => $this->db->cfn($col, $cfg["table"]),
        "operator" => $strict ? "=" : "contains",
        "value" => $filter,
      ];
    }

    return $finalFilter;
  }

  /**
   * Executes a search on the table.
   *
   * @param array|string $filter Search string or a full filter configuration.
   * @param array        $cols   Columns to search on (used only if $filter is a string).
   * @param array        $fields Fields to return.
   * @param array        $order  Order configuration.
   * @param bool         $strict If true, uses '='; otherwise uses 'contains'.
   * @param int          $limit  Max rows.
   * @param int          $start  Offset.
   * @return array Array of rows as arrays.
   */
  protected function dbTraitSearch(
    array|string $filter,
    array $cols = [],
    array $fields = [],
    array $order = [],
    bool $strict = false,
    int $limit = 0,
    int $start = 0,
  ): array {
    if (is_array($filter)) {
      $finalFilter = $filter;
      if (empty($fields) && !empty($cols)) {
        $fields = $cols;
      }
    } else {
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
   * Gets a single row and returns it, using dbTraitSelection().
   *
   * @param string|array $filter Row id or filter configuration.
   * @param array        $order  Order configuration.
   * @param string       $mode   'array'|'object' (passed through to dbTraitSelection()).
   * @param array        $fields Fields to return.
   * @return mixed The first matching row, or null.
   */
  private function dbTraitSingleSelection(
    string|array $filter,
    array $order,
    string $mode = "array",
    array $fields = [],
  ): mixed {
    $f = $this->class_cfg["arch"][$this->class_table_index];

    if (is_string($filter)) {
      $cfg = [$f["id"] => $filter];
    } elseif (is_array($filter)) {
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
