<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use bbn\X;

trait Dbconfig
{

  /** @var bool */
  private $_is_init_class_cfg = false;

  /** @var array */
  protected $fields;

  /** @var string */
  protected $class_table;

  /** @var string */
  protected $class_table_index;


  /**
   * @param $id
   * @return bool
   */
  public function exists($id): bool
  {
    if (!$this->class_table_index) {
      throw new \Exception(X::_("The table index parameter should be defined"));
    }

    $res = $this->db->count(
      $this->class_table, [
      $this->class_cfg['arch'][$this->class_table_index]['id'] => $id
      ]
    );
    return (bool)$res;
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
   * @return void
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
      return !!$this->db->delete($ccfg['table'], [$f['id'] => $id]);
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
      throw new \Exception(X::_("Impossible to find the given row"));
    }

    if ($addCfg) {
      $data = array_merge($this->select($id), $data);
    }

    if ($data = $this->prepare($data)) {
      $ccfg = $this->getClassCfg();
      $f = $ccfg['arch'][$this->class_table_index];
      return !!$this->db->update($ccfg['table'], $data, [$f['id'] => $id]);
    }

    return false;
  }


  /**
   * Retrieves a row from the table through its id.
   *
   * @param string $id
   *
   * @return array|null
   */
  public function select(string $id): ?array
  {
    if ($this->exists($id)) {
      $f = $this->class_cfg['arch'][$this->class_table_index];
      if ($arr = $this->db->rselect(
        $this->class_table, array_values($f), [
          $f['id'] => $id
        ]
      )) {
        if (!empty($f['cfg']) && !empty($arr[$f['cfg']])) {
          $cfg = json_decode($arr[$f['cfg']], true);
          $arr = array_merge($cfg, $arr);
          unset($arr[$f['cfg']]);
        }

        return $arr;
      }
    }

    return null;

  }


  /**
   * Returns an array of rows from the table for the given conditions.
   *
   * @param array $cond
   *
   * @return array
   */
  public function selectAll(array $cond): array
  {
    if (!$this->class_table_index) {
      throw new \Exception(X::_("The table index parameter should be defined"));
    }

    $f = $this->class_cfg['arch'][$this->class_table_index];
    if ($arrs = $this->db->rselectAll($this->class_table, array_values($f), $cond)) {
      foreach ($arrs as &$arr) {
        if (!empty($f['cfg']) && !empty($arr[$f['cfg']])) {
          $cfg = json_decode($arr[$f['cfg']], true);
          $arr = array_merge($cfg, $arr);
          unset($arr[$f['cfg']]);
        }
      }
      unset($arr);

      return $arrs;
    }

    return [];
  }


  protected function isInitClassCfg(): bool
  {
    return $this->_is_init_class_cfg;
  }



  protected function prepare(array $data)
  {
    if (!$this->isInitClassCfg($data)) {
      throw new \Exception(X::_("Impossible to prepare an item if the class config has not been initialized"));
    }

    $ccfg = $this->getClassCfg();
    $table_index = array_flip($ccfg['tables'])[$ccfg['table']];
    if (!$table_index) {
      throw new \Exception(X::_("The class config is not correct as the main table doesn't have an arch"));
    }

    $f = $ccfg['arch'][$table_index];
    if (!$f['id']) {
      throw new \Exception(X::_("The class config is not correct as the main table doesn't have an id column"));
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
      throw new \Exception(X::_("The class %s is not configured properly to work with trait Dbconfig", get_class($this)));
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
