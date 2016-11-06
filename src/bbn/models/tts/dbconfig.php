<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\models\tts;

use bbn;


trait dbconfig
{

  protected
    /** @var array */
    $class_cfg,
    /** @var string */
    $class_table;

  /**
   * Sets the class configuration as defined in $this->_defaults
   * @param array $cfg
   * @return $this
   */
  private function _init_class_cfg(array $cfg = []){
    $this->class_cfg = bbn\x::merge_arrays(self::$_defaults, $cfg);
    if ( !empty($cfg['arch']) ){
      foreach ( $cfg['arch'] as $t => $a ){
        $this->class_cfg['arch'][$t] = $a;
      }
    }
    if ( empty($this->class_cfg['table']) ){
      die("You must define a main table for the class in ".get_class($this));
    }
    $this->class_table = $this->class_cfg['table'];
    /*
     * The selection comprises the defined fields of the users table
     * Plus a bunch of user-defined additional fields in the same table
     */
    $this->fields = $this->class_cfg['arch'][$this->class_table];
    return $this;
  }

  public function exists($id){
    return $this->db->count($this->class_table, [
      $this->class_cfg['arch'][$this->class_table]['id'] => $id
    ]) ? true : false;
  }


}