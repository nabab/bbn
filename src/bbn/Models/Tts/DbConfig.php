<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use bbn\X;
use Exception;

trait DbConfig
{

  /** @var bool */
  private $_isInitClassCfg = false;

  /** @var array */
  protected $fields;

  protected $class_cfg;

  /** @var string */
  protected $class_table;

  /** @var string */
  protected $class_table_index;


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
   * Sets the class configuration as defined in self::default_class_cfg
   * @param array $cfg
   * @return $this
   */
  protected function initClassCfg(array $cfg = null)
  {
$arr = [];
    if (isset(self::$default_class_cfg)) {
      $arr[] = self::$default_class_cfg;
    }

    if (isset(static::$default_class_cfg)) {
      $arr[] = static::$default_class_cfg;
    }
    
    if ($cfg) {
      $arr[] = $cfg;
    }

    if (!count($arr)) {
      throw new Exception(X::_("The class %s is not configured properly to work with trait DbActions", get_class($this)));
    }

    $cfg = count($arr) > 1 ? X::mergeArrays(...$arr) : $arr[0];

    $table_index = array_flip($cfg['tables'])[$cfg['table']];
    if (!$table_index || !isset($cfg['tables'], $cfg['table'], $cfg['arch'], $cfg['arch'][$table_index])) {
      throw new Exception(X::_("The class %s is not configured properly to work with trait DbActions", get_class($this)));
    }

    $this->class_table = $cfg['table'];
    // We completely replace the table structure, no merge
    $props = [];
    foreach ($cfg['arch'] as $t => &$fields){
      if (!$this->class_table_index && isset($cfg['tables'][$t]) && ($cfg['tables'][$t] === $cfg['table']))  {
        $this->class_table_index = $t;
      }
    foreach ($fields as $f => $it) {
        if (is_array($it)) {
          $props[$t][$f] = $it;
          $fields[$f] = $it['name'] ?? $f;
        }
      }
    }
    unset($fields);
    if (!empty($props)) {
      $cfg['props'] = $props;
    }



    // The selection comprises the defined fields of the users table
    // Plus a bunch of user-defined additional fields in the same table
    $this->fields = $cfg['arch'][$this->class_table_index];

    $this->class_cfg = $cfg;
    $this->_isInitClassCfg = true;

    return $this;
  }


  protected function isInitClassCfg(): bool
  {
    return $this->_isInitClassCfg;
  }

}

