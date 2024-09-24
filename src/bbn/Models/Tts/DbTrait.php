<?php

namespace bbn\Models\Tts;

use bbn\X;
use Exception;

/**
 * Trait DbTrait
 * Provides common database-related methods and properties for handling class configurations, data preparation, filtering, and relations.
 */
trait DbTrait
{
  use DbConfig;

  /**
   * @var array Configuration for filters applied to database queries.
   */
  protected $dbTraitFilterCfg = [];

  /**
   * @var array Configuration for the root filter.
   */
  protected $rootFilterCfg = [];

  /**
   * @var array Cached relations for the current table.
   */
  private $dbTraitRelations = [];

  /**
   * @var array Cached relations for the current table.
   */
  private $dbTraitStructure = [];

  /**
   * Prepares data before inserting or updating in the database.
   *
   * @param array $data The data to be prepared.
   *
   * @return array The prepared data.
   * @throws Exception If the class config has not been initialized or is incorrect.
   */
  protected function dbTraitPrepare(array $data): array
  {
    // Ensure that the class configuration is initialized
    if (!$this->isInitClassCfg()) {
      throw new Exception(X::_("Impossible to prepare an item if the class config has not been initialized"));
    }

    $ccfg = $this->getClassCfg();
    // Get the table index from the class configuration
    $table_index = array_flip($ccfg['tables'])[$ccfg['table']];
    if (!$table_index) {
      throw new Exception(X::_("The class config is not correct as the main table doesn't have an arch"));
    }

    $f = $ccfg['arch'][$table_index];
    $res = [];
    
    // Handle 'cfg' field if present in the table configuration
    if (!empty($f['cfg'])) {
      if (array_key_exists($f['cfg'], $data)) {
        $res[$f['cfg']] = is_string($data[$f['cfg']]) ? json_decode($data[$f['cfg']], true) : $data[$f['cfg']];
        unset($data[$f['cfg']]);
      }
      elseif (isset($ccfg['cfg'])) {
        $cfg = [];
        foreach ($ccfg['cfg'] as $k => $v) {
          if (array_key_exists($v['field'], $data)) {
            $cfg[$v['field']] = $data[$v['field']];
            unset($data[$v['field']]);
          }
        }
        if (!empty($cfg)) {
          $res[$f['cfg']] = $cfg;
        }
      }
    }
    
    $structure = $this->dbTraitGetStructure();
    foreach ($data as $k => $v) {
      if (in_array($k, $f)) {
        // Set the value to null if it's empty and the field allows null
        if (empty($v) && $structure['fields'][$k]['null']) {
          $v = null;
        }
        $res[$k] = $v;
      }
    }

    return $res;
  }

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
  protected function dbTraitFilterCfg(array $cfg): array
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
    return array_map(function ($a) {
      return [
        'logic' => 'AND',
        'conditions' => $a
      ];
    }, $conditions);
  }

  /**
   * Gets the structure of the specified table.
   *
   * @param string|null $table The table name (optional).
   *
   * @return array The structure of the table.
   */
  protected function dbTraitGetStructure(string $table = null): array
  {
    if (!$table) {
      $cfg = $this->getClassCfg();
      $table = $cfg['table'];
    }

    if (!isset($this->dbTraitStructure[$table])) {
      $this->dbTraitStructure[$table] = $this->db->modelize($table);
    }

    return $this->dbTraitStructure[$table];
  }

  /**
   * Retrieves the relations for a given table.
   *
   * @param string|null $table The table name (optional).
   *
   * @return array An array of relations.
   */
  protected function dbTraitGetTableRelations(string $table = null): array
  {
    $cfg = $this->getClassCfg();
    if (!$table) {
      $table = $cfg['table'];
    }
    $idx = array_flip($cfg['tables'])[$table];
    if ($idx && !isset($this->dbTraitRelations[$table])) {
      $arc = &$cfg['arch'][$idx];
      $this->dbTraitRelations[$table] = [];
      if (!empty($arc['id'])) {
        $refs = $this->db->findReferences($this->db->cfn($arc['id'], $table));
        foreach ($refs as $ref) {
          [$db, $table, $col] = X::split($ref, '.');
          $model = $this->db->modelize($table);
          $this->dbTraitRelations[$table][] = [
            'db' => $db,
            'table' => $table,
            'primary' => isset($model['keys']['PRIMARY']) && (count($model['keys']['PRIMARY']['columns']) === 1) ? $model['keys']['PRIMARY']['columns'][0] : null,
            'col' => $col,
            'model' => $model
          ];
        }
      }
    }

    return $this->dbTraitRelations[$table];
  }

  /**
   * Returns an array of rows from the table for the given conditions.
   *
   * @param array $filter Filter conditions.
   * @param array $order Order by conditions.
   * @param int $limit Maximum number of rows to return.
   * @param int $start Offset of the first row to return.
   * @param string $mode The mode of result ('array', 'object', 'value').
   * @param array $fields Fields to select.
   *
   * @return array The result set.
   */
  private function dbTraitSelection(
    array $filter,
    array $order,
    int $limit,
    int $start,
    string $mode = 'array',
    array $fields = []
  ): array
  {
    $returnObject = $mode === 'object';
    $req = $this->dbTraitGetRequestCfg($filter, $order, $limit, $start, $fields);
    $f = $this->class_cfg['arch'][$this->class_table_index];
    $method = $mode === 'object' ? 'selectAll' : ($mode === 'value' ? 'getColumnValues' : 'rselectAll');
    $res = $this->db->$method($req);
    if ($res) {
      if (!empty($f['cfg'])) {
        foreach ($res as &$r) {
          if ($returnObject && !empty($r->{$f['cfg']})) {
            $cfg = json_decode($r->{$f['cfg']});
            $r = X::mergeObjects($cfg, $r);
            unset($r->{$f['cfg']});
          }
          elseif (!$returnObject && !empty($r[$f['cfg']])) {
            $cfg = json_decode($r[$f['cfg']], true);
            $r = array_merge($cfg, $r);
            unset($r[$f['cfg']]);
          }
        }

        unset($r);
      }

      return $res;
    }

    return [];
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
  private function dbTraitGetRequestCfg(
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
      'where' => $this->dbTraitFilterCfg($filter),
      'order' => $order
    ];

    if ($limit) {
      $req['limit'] = $limit;
      $req['start'] = $start;
    }

    return $req;
  }
}
