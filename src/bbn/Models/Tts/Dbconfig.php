<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use bbn\X;
use stdClass;
use Exception;

trait Dbconfig
{

  /** @var bool */
  private $_is_init_class_cfg = false;

  /** @var array */
  protected $fields;

  protected $class_cfg;

  /** @var string */
  protected $class_table;

  /** @var string */
  protected $class_table_index;


  /**
   * @param array|string $id
   * @return bool
   */
  public function exists($filter): bool
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

    if (!empty($cfg) && $arr = $this->db->count(
      $this->class_table,
      $cfg
    )) {
      return true;
    }

    return false;
  }


  /**
   * Returns the class configuration.
   * 
   * @return mixed
   */
  public function getClassCfg()
  {
    return $this->class_cfg;
  }


  /**
   * Returns the fields of the main table.
   *
   * @return array
   */
  public function getFields()
  {
    return $this->fields;
  }


  /**
   * Inserts a new row in the table.
   *
   * @param array $data
   *
   * @return string|null
   */
  public function insert(array $data): ?string
  {
    if ($data = $this->prepare($data)) {
      $ccfg = $this->getClassCfg();
      if ($this->db->insert($ccfg['table'], $data)) {
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
  public function delete(string $id): bool
  {
    if ($data = $this->exists($id)) {
      $ccfg = $this->getClassCfg();
      $f = $ccfg['arch'][$this->class_table_index];
      return (bool)$this->db->delete($ccfg['table'], [$f['id'] => $id]);
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
  public function update(string $id, array $data, bool $addCfg = false): bool
  {
    if (!$this->exists($id)) {
      throw new Exception(X::_("Impossible to find the given row"));
    }

    if ($addCfg) {
      $data = array_merge($this->select($id), $data);
    }

    if ($data = $this->prepare($data)) {
      $ccfg = $this->getClassCfg();
      $f = $ccfg['arch'][$this->class_table_index];
      return (bool)$this->db->update($ccfg['table'], $data, [$f['id'] => $id]);
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
  public function selectOne(string $field, $filter, array $order = [])
  {
    if ($res = $this->dbConfigSingleSelection($filter, $order, false, [$field])) {
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
  public function select($filter, array $order = []): ?stdClass
  {
    return $this->dbConfigSingleSelection($filter, $order, true);
  }


  /**
   * Retrieves a row as an array from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return array|null
   */
  public function rselect($filter, array $order = []): ?array
  {
    return $this->dbConfigSingleSelection($filter, $order, false);
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

    return $this->db->count($this->class_table, $filter);
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
  public function selectAll(array $filter, array $order = [], int $limit = 0, int $start = 0): array
  {
    return $this->dbConfigSelection($filter, $order, $limit, $start, true);
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
  public function rselectAll(array $filter, array $order = [], int $limit = 0, int $start = 0): array
  {
    return $this->dbConfigSelection($filter, $order, $limit, $start, false);
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
    if (!$f['id']) {
      throw new Exception(X::_("The class config is not correct as the main table doesn't have an id column"));
    }

    $res = [];
    if ($hasCfg = !empty($f['cfg'])) {
      if (!empty($data[$f['cfg']])) {
        $cfg = is_string($data[$f['cfg']]) ? json_decode($data[$f['cfg']], true) : $data[$f['cfg']];
      }
      elseif (array_key_exists($f['cfg'], $data)) {
        $res['cfg'] = null;
        $cfg = null;

      }
      else {
        $cfg = [];
      }
    }
    

    foreach ($data as $k => $v) {
      if (in_array($k, $f)) {
        $res[$k] = $v;
      }
      elseif ($hasCfg && is_array($cfg)) {
        $cfg[$k] = $v;
      }
    }

    if (!empty($cfg)) {
      $res[$f['cfg']] = json_encode($cfg);
    }

    if (isset($res[$f['id']])) {
      unset($res[$f['id']]);
    }

    return $res;
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
  private function dbConfigSelection(
    array $filter,
    array $order,
    int $limit,
    int $start,
    bool $returnObject,
    array $fields = []
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
    }
    else {
      $fields = $this->class_cfg['arch'][$this->class_table_index];
    }
    $req = [
      'table' => $this->class_table,
      'fields' => $fields,
      'where' => $filter,
      'order' => $order
    ];

    if ($limit) {
      $req['limit'] = $limit;
      $req['start'] = $start;
    }

    $res = $returnObject ? $this->db->selectAll($req) : $this->db->rselectAll($req);
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
   * Gets a single row and returns it
   *
   * @param [type] $filter
   * @param array $order
   * @param boolean $asObject
   * @return mixed
   */
  private function dbConfigSingleSelection(
    $filter,
    array $order,
    bool $asObject,
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
        && ($res = $this->dbConfigSelection($cfg, $order, 1, 0, $asObject, $fields))
    ) {
      return $res[0];
    }

    return null;

  }


  /**
   * Sets the class configuration as defined in self::default_class_cfg
   * @param array $cfg
   * @return $this
   */
  private function _init_class_cfg(array $cfg = null)
  {
    if (isset(self::$default_class_cfg)) {
      $cfg = X::mergeArrays(self::$default_class_cfg, $cfg ?: []);
    }

    $table_index = array_flip($cfg['tables'])[$cfg['table']];
    if (!$table_index || !isset($cfg['tables'], $cfg['table'], $cfg['arch'], $cfg['arch'][$table_index])) {
      throw new Exception(X::_("The class %s is not configured properly to work with trait Dbconfig", get_class($this)));
    }

    $this->class_table = $cfg['table'];
    // We completely replace the table structure, no merge
    foreach ($cfg['arch'] as $t => $a){
      if (isset($cfg['tables'][$t]) && $cfg['tables'][$t] === $cfg['table']) {
        $this->class_table_index = $t;
      }
    }

    // The selection comprises the defined fields of the users table
    // Plus a bunch of user-defined additional fields in the same table
    $this->fields = $cfg['arch'][$this->class_table_index];
    $this->class_cfg = $cfg;
    $this->_is_init_class_cfg = true;

    return $this;
  }
}
