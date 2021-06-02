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

  protected
    /** @var array */
    $class_cfg,
    /** @var array */
    $fields,
    /** @var string */
    $class_table,
    /** @var string */
    $class_table_index;

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

    if (!isset($cfg['tables'], $cfg['table'], $cfg['arch'])) {
      throw new \Exception(X::_("The class %s is not configured properly to work with trait Dbconfig", get_class($this)));
    }

    $this->class_table = $cfg['table'];
    // We completely replace the table structure, no merge
    foreach ($cfg['arch'] as $t => $a){
      if ($cfg['tables'][$t] === $cfg['table']) {
        $this->class_table_index = $t;
      }
    }
    /*
     * The selection comprises the defined fields of the users table
     * Plus a bunch of user-defined additional fields in the same table
     */
    $this->fields = $cfg['arch'][$this->class_table_index];
    $this->class_cfg = $cfg;
    return $this;
  }

  /**
   * @param $id
   * @return bool
   */
  public function exists($id): bool
  {
    $res = $this->db->count(
      $this->class_table, [
      $this->class_cfg['arch'][$this->class_table_index]['id'] => $id
      ]
    );
    return (bool)$res;
  }

  /**
   * Return the
   * @return mixed
   */
  public function getClassCfg()
  {
    return $this->class_cfg;
  }

  public function getFields()
  {
    return $this->fields;
  }
}
