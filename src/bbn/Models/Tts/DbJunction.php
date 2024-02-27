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

  protected $DbJunctionFilterCfg = [];

  protected $rootFilterCfg = [];

  private $DbJunctionStructure = [];

  private $DbJunctionRelations = [];


  /**
   * @param array|string $id
   * @return bool
   */
  public function exists(array $filter): bool
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $f = $this->class_cfg['arch'][$this->class_table_index];
    if (!empty($filter) && $this->db->count(
      $this->class_table,
      $this->DbJunctionFilterCfg($filter)
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
  public function insert(array $data, bool $ignore = false): ?string
  {
    if ($data = $this->prepare($data)) {
      $ccfg = $this->getClassCfg();
      if (!empty($ccfg['arch'][$this->class_table_index]['cfg'])) {
        $col = $ccfg['arch'][$this->class_table_index]['cfg'];
        if (isset($data[$col])) {
          $data[$col] = json_encode($data[$col]);
        }
      }

      if ($this->db->{$ignore ? 'insertIgnore' : 'insert'}($ccfg['table'], $data)) {
        return $this->rselect($data);
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
  public function delete(array $filter, bool $cascade = false): bool
  {
    if ($this->exists($filter)) {
      $cfg = $this->getClassCfg();
      $f = $cfg['arch'][$this->class_table_index];

      return (bool)$this->db->delete($cfg['table'], $this->DbJunctionFilterCfg($filter));
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
  public function update(array $filter, array $data, bool $addCfg = false): bool
  {
    if (!$this->exists($filter)) {
      throw new Exception(X::_("Impossible to find the given row"));
    }

    $ccfg = $this->getClassCfg();
    $f = $ccfg['arch'][$this->class_table_index];
    if ($data = $this->prepare($data)) {
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
      
      return (bool)$this->db->update($ccfg['table'], $data, $this->DbJunctionFilterCfg($filter));
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
  public function selectOne(string $field, array $filter = [], array $order = [])
  {
    if ($res = $this->DbJunctionSingleSelection($filter, $order, 'array', [$field])) {
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
  public function select(array $filter = [], array $order = [], array $fields = []): ?stdClass
  {
    return $this->DbJunctionSingleSelection($filter, $order, 'object', $fields);
  }


  /**
   * Retrieves a row as an array from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return array|null
   */
  public function rselect(array $filter = [], array $order = [], array $fields = []): ?array
  {
    return $this->DbJunctionSingleSelection($filter, $order, 'array', $fields);
  }

  public function selectValues(string $field, array $filter = [], array $order = [], int $limit = 0, int $start = 0): array
  {
    return $this->DbJunctionSelection($filter, $order, $limit, $start, 'value', [$field]);
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
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

    $req = $this->DbJunctionGetRequestCfg($filter, [], 1, 0, [$this->fields['id']]);
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
  public function selectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->DbJunctionSelection($filter, $order, $limit, $start, 'object', $fields);
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
  public function rselectAll(array $filter = [], array $order = [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->DbJunctionSelection($filter, $order, $limit, $start, 'array', $fields);
  }

  protected function isInitClassCfg(): bool
  {
    return $this->_is_init_class_cfg;
  }

  protected function prepare(array $data)
  {
    if (!$this->isInitClassCfg($data)) {
      throw new Exception(X::_("Impossible to prepare an item if the class config has not been initialized"));
    }

    $ccfg = $this->getClassCfg();
    $table_index = array_flip($ccfg['tables'])[$ccfg['table']];
    if (!$table_index) {
      throw new Exception(X::_("The class config is not correct as the main table doesn't have an arch"));
    }

    $f = $ccfg['arch'][$table_index];
    $res = [];
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
    
    $structure = $this->DbJunctionGetStructure();
    foreach ($data as $k => $v) {
      if (in_array($k, $f)) {
        if (empty($v) && $structure['fields'][$k]['null']) {
          $v = null;
        }

        $res[$k] = $v;
      }
    }

    return $res;
  }


  protected function DbJunctionSetFilterCfg(array $cfg): void
  {
    $this->DbJunctionFilterCfg = $cfg;
  }

  protected function DbJunctionResetFilterCfg(): void
  {
    $this->DbJunctionFilterCfg = [];
  }

  protected function DbJunctionFilterCfg(array $cfg): array
  {
    $conditions = [];
    if (!empty($this->rootFilterCfg)) {
      $conditions[] = $this->rootFilterCfg;
    }

    if (!empty($this->DbJunctionFilterCfg)) {
      $conditions[] = $this->DbJunctionFilterCfg;
    }

    if (!empty($cfg)) {
      $conditions[] = $cfg;
    }

    if (empty($conditions)) {
      return [];
    }

    if (count($conditions) === 1) {
      return $conditions[0];
    }

    return array_map(function ($a) {
      return [
        'logic' => 'AND',
        'conditions' => $a
      ];
    }, $conditions);
  }


  protected function DbJunctionGetStructure(string $table = null): array
  {
    $cfg = $this->getClassCfg();
    if (!$table) {
      $table = $cfg['table'];
    }

    if (!$this->DbJunctionStructure[$table]) {
      $this->DbJunctionStructure[$table] = $this->db->modelize($table);
    }

    return $this->DbJunctionStructure[$table];
  }


  protected function DbJunctionGetRelations(string $table = null): array
  {
    $cfg = $this->getClassCfg();
    if (!$table) {
      $table = $cfg['table'];
    }
    $idx = array_flip($cfg['tables'])[$table];
    if ($idx && !isset($this->DbJunctionRelations[$table])) {
      $arc = &$cfg['arch'][$idx];
      $this->DbJunctionRelations[$table] = [];
      $refs = $this->db->findReferences($this->db->cfn($arc['id'], $table));
      foreach ($refs as $ref) {
        [$db, $table, $col] = X::split($ref, '.');
        $model = $this->db->modelize($table);
        $this->DbJunctionRelations[$table][] = [
          'db' => $db,
          'table' => $table,
          'primary' => isset($model['keys']['PRIMARY']) && (count($model['keys']['PRIMARY']['columns']) === 1) ? $model['keys']['PRIMARY']['columns'][0] : null,
          'col' => $col,
          'model' => $model
        ];
      }
    }

    return $this->DbJunctionRelations[$table];
  }

  /**
   * Returns an array of rows from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param array $limit
   * @param array $start
   *
   * @return array
   */
  private function DbJunctionSelection(
    array $filter,
    array $order,
    int $limit,
    int $start,
    string $mode = 'array',
    array $fields = [],

  ): array
  {
    $returnObject = $mode === 'object';
    $req = $this->DbJunctionGetRequestCfg($filter, $order, $limit, $start, $fields);
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


  private function DbJunctionGetRequestCfg(
    array $filter,
    array $order,
    int $limit,
    int $start,
    array $fields = [],

  ): array
  {
    if (!$this->class_table_index) {
      throw new Exception(X::_("The table index parameter should be defined"));
    }

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
          $properFields[$v['field']] = "JSON_UNQUOTE(JSON_EXTRACT(" 
              . $this->db->csn($cfgCol, true) . ", '\$." . $v['field']
              . "'))";
        }
      }
    }
    elseif (!isset($properFields)) {
      $properFields = array_values($fields);
    }

    $req = [
      'table' => $this->class_table,
      'fields' => $properFields,
      'where' => $this->DbJunctionFilterCfg($filter),
      'order' => $order
    ];

    if ($limit) {
      $req['limit'] = $limit;
      $req['start'] = $start;
    }

    return $req;
  }


  /**
   * Gets a single row and returns it
   *
   * @param [type] $filter
   * @param array $order
   * @param string $mode
   * @return mixed
   */
  private function DbJunctionSingleSelection(
    array $filter,
    array $order,
    string $mode = 'array',
    array $fields = []
  ): mixed
  {
    if ($res = $this->DbJunctionSelection($filter, $order, 1, 0, $mode, $fields)) {
      return $res[0];
    }

    return null;

  }
}

