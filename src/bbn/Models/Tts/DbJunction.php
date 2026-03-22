<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use ReflectionProperty;
use stdClass;
use Exception;
use bbn\X;
use bbn\Str;
use bbn\Mvc;

trait DbJunction
{
  use DbConfig;
  use DbFiltering;
  use DbStructure;
  use DbData;
  use DbSelection;
  use DbWrite;

  private static array $_isInitJunction = [];
  protected $rootFilterCfg = [];

  private static array $dbJunctionCfg = [];

  /**
   * @param array $filter
   * @return bool
   */
  public function dbTraitExists(array $filter): bool
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
   * @return array|null
   */
  public function dbTraitInsert(array $data, bool $ignore = false): ?array
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
   * @return int
   */
  public function dbTraitDelete(array $filter, bool $cascade = false): int
  {
    if ($this->dbTraitExists($filter)) {
      $cfg = $this->getClassCfg();
      $f = $cfg['arch'][$this->class_table_index];

      return $this->db->delete($cfg['table'], $this->dbTraitGetFilterCfg($filter));
    }

    return 0;
  }


  /**
   * Updates a single row in the table through its id.
   *
   * @param string $id
   * @param array $data
   *
   * @return int
   */
  public function dbTraitUpdate(array $filter, array $data, bool $addCfg = false): int
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

      return $this->db->update($ccfg['table'], $data, $this->dbTraitGetFilterCfg($filter));
    }

    return 0;
  }


  /**
   * Retrieves a row as an object from the table through its id.
   *
   * @param string|array $filter
   * @param array $order
   *
   * @return mixed
   */
  public function dbTraitSelectOne(string $field, array $filter = [], string|array $order= [])
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
  public function dbTraitSelect(array $filter = [], string|array $order= [], array $fields = []): ?stdClass
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
  public function dbTraitRselect(array $filter = [], string|array $order= [], array $fields = []): ?array
  {
    return $this->dbTraitSingleSelection($filter, $order, 'array', $fields);
  }

  public function dbTraitSelectValues(string $field, array $filter = [], string|array $order= [], int $limit = 0, int $start = 0): array
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
   * @param int   $limit
   * @param int   $start
   * @param array $fields
   *
   * @return array
   */
  public function dbTraitSelectAll(array $filter = [], string|array $order= [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitSelection($filter, $order, $limit, $start, 'object', $fields);
  }


  /**
   * Returns an array of rows as arrays from the table for the given conditions.
   *
   * @param array $filter
   * @param array $order
   * @param int   $limit
   * @param int   $start
   * @param array $fields
   *
   * @return array
   */
  public function dbTraitRselectAll(array $filter = [], string|array $order= [], int $limit = 0, int $start = 0, $fields = []): array
  {
    return $this->dbTraitSelection($filter, $order, $limit, $start, 'array', $fields);
  }

  protected static function dbJunctionSetup(array $linkedClasses = [])
  {
    if (!static::isDbConfigInit()) {
      throw new Exception(X::_("The class %s should be configured before using it as a junction", static::class));
    }

    static::$dbJunctionCfg[static::class] = [];
    foreach ($linkedClasses as $table => $cls) {
      if (empty($table) || empty($cls)) {
        throw new Exception(X::_("Each junction defined in the class %s configuration should have a table and a class defined", static::class));
      }

      if (!class_exists($cls)) {
        throw new Exception(X::_("The class %s defined in the junction configuration of the class %s does not exist", $cls, static::class));
      }

      static::$dbJunctionCfg[static::class][$table] = $cls;
    } 


  }

  protected function dbJunctionInit()
  {
    if (isset(self::$_isInitJunction[static::class])) {
      return;
    }

    /*
    $keys = $this->db->getKeys($this->class_cfg['table']);
    $tcs = self::dbConfigGetTableClasses($this->db)['tables'];
    $linkedClasses = [];
    foreach ($keys["keys"] as $n => $v) {
      if (
        $n !== "PRIMARY" &&
        count($v["columns"]) === 1 &&
        !empty($v["ref_table"]) &&
        isset($tcs[$v["ref_table"]])
      ) {
        $cls = $tcs[$v["ref_table"]];
        $property = "default_class_cfg";
        if (
          property_exists($cls, $property) &&
          method_exists($cls, "initClassCfg")
        ) {
          $cfg = $cls::getDefaultClassCfg();
          if (!empty($cfg['junctions'])) {
            foreach ($cfg['junctions'] as $j) {
              if (isset($j['table']) && $j['table'] === $this->class_cfg['table']) {
                $linkedClasses[$this->db->tsn($v['ref_table'])] = $cls;
                break;
              }
            }
          }
        }
      }
    }
    
    self::dbJunctionSetup($linkedClasses);
    */
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
    array $filter,
    array $order,
    string $mode = 'array',
    array $fields = []
  ): mixed
  {
    if ($res = $this->dbTraitSelection($filter, $order, 1, 0, $mode, $fields)) {
      return $res[0];
    }

    return null;

  }
}

