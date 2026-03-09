<?php

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Str;

use function count;

trait DbFiltering
{
  /**
   * @var array Configuration for the root filter.
   */
  protected $rootFilterCfg = [];

  protected $dbTraitFilterCfg = [];

  /**
   * Sets the filter configuration for database queries.
   *
   * @param array $cfg The filter configuration.
   */
  protected function dbTraitSetFilterCfg(array $cfg): void
  {
    $this->dbTraitFilterCfg = $cfg;
  }

  /**
   * Resets the filter configuration for database queries.
   */
  protected function dbTraitResetFilterCfg(): void
  {
    $this->dbTraitFilterCfg = [];
  }

  /**
   * Combines multiple filter configurations into a single array.
   *
   * @param array $cfg Additional filter configuration.
   *
   * @return array The combined filter configuration.
   */
  protected function dbTraitGetFilterCfg(array $cfg): array
  {
    $conditions = [];
    if (!empty($this->rootFilterCfg)) {
      $conditions[] = $this->rootFilterCfg;
    }

    if (!empty($this->dbTraitFilterCfg)) {
      $conditions[] = $this->dbTraitFilterCfg;
    }

    if (!empty($cfg)) {
      $conditions[] = $cfg;
    }

    // Return empty array if no conditions exist
    if (empty($conditions)) {
      return [];
    }

    // Return single condition if only one exists
    if (count($conditions) === 1) {
      return $conditions[0];
    }

    // Combine all conditions with 'AND' logic
    return [
      'logic' => 'AND',
      'conditions' => $conditions
    ];
  }

  /**
   * Builds a search filter configuration from a simple scalar term.
   *
   * If `$cols` is empty, searchable columns are inferred from the table model:
   * - text/char-like columns are included
   * - integer-like columns are also included if the filter looks numeric
   *
   * The returned filter uses OR logic across all selected columns.
   *
   * @param string|int         $filter Search term.
   * @param array<int, string> $cols   Columns to search on. If empty, columns are inferred.
   * @param bool               $strict Whether to use `=` instead of `contains`.
   * @return array<string, mixed> Search filter configuration.
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
        }
        elseif ($isNumber && Str::pos($colCfg["type"], "int") !== false) {
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
   * Prepares the request configuration for a database query.
   *
   * @param array $filter Filter conditions.
   * @param array $order Order by conditions.
   * @param int $limit Maximum number of rows to return.
   * @param int $start Offset of the first row to return.
   * @param array $fields Fields to select.
   *
   * @return array The request configuration.
   * @throws Exception If the table index is not defined or a field does not exist.
   */
  protected function dbTraitGetRequestCfg(
    array $filter,
    array $order,
    int $limit,
    int $start,
    array $fields = []
  ): array
  {
    // Ensure table index is defined
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    // Validate fields
    if (!empty($fields)) {
      foreach (array_values($fields) as $f) {
        if (!in_array($f, $this->class_cfg['arch'][$this->class_table_index])) {
          throw new Exception(X::_("The field %s does not exist", $f));
        }
      }

      $properFields = $fields;
    }
    else {
      $fields = $this->class_cfg['arch'][$this->class_table_index];
    }

    $ccfg = $this->getClassCfg();
    if (isset($fields['cfg']) && !empty($ccfg['cfg'])) {
      $cfgCol = $fields['cfg'];
      unset($fields['cfg']);
      if (!isset($properFields)) {
        $properFields = array_values($fields);
      }

      foreach ($ccfg['cfg'] as $v) {
        if ($v['field'] && !in_array($v['field'], $properFields)) {
          $properFields[$v['field']] = "IF(JSON_EXTRACT("
              . $this->db->csn($cfgCol, true) . ", '\$." . $v['field']
              . "') = 'null', NULL, JSON_UNQUOTE(JSON_EXTRACT("
              . $this->db->csn($cfgCol, true) . ", '\$." . $v['field']
              ."')))";
        }
      }
    }
    elseif (!isset($properFields)) {
      $properFields = array_values($fields);
    }

    // Build the request configuration
    $req = [
      'table' => $this->class_table,
      'fields' => $properFields,
      'where' => $this->dbTraitGetFilterCfg($filter),
      'order' => $order
    ];

    if ($limit) {
      $req['limit'] = $limit;
      $req['start'] = $start;
    }

    return $req;
  }

}

