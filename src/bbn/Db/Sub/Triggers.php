<?php

namespace bbn\Db\Sub;

use bbn\Db;
use bbn\Db\Models\Cls\Sub;
use bbn\Db\Models\Itf\Triggers as ItfTriggers;

class Triggers extends Sub implements ItfTriggers
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                          TRIGGERS                            *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Enable the triggers' functions
   *
   * ```php
   * X::adump($ctrl->db->enableTrigger()); // bbn\Db Object
   * ```
   * 
   * @return Db
   */
  public function enableTrigger(): Db
  {
    $this->language->enableTrigger();
    return $this->db;
  }


  /**
   * Disable the triggers' functions
   * 
   * ```php
   * X::adump($ctrl->db->disableTrigger());
   * ```
   * 
   * @return Db
   */
  public function disableTrigger(): Db
  {
    $this->language->disableTrigger();
    return $this->db;
  }

  /**
   * Checks if the triggers' functions are enable
   * 
   * ```php
   * X::adump($ctrl->db->isTriggerEnabled()); // true
   * ```
   *
   * @return boolean
   */
  public function isTriggerEnabled(): bool
  {
    return $this->language->isTriggerEnabled();
  }

  /**
   * Checks if the triggers' functions are disable
   * 
   * ```php
   * X::adump($ctrl->db->isTriggerEnabled()); // false
   * ```
   * 
   * @return boolean
   */
  public function isTriggerDisabled(): bool
  {
    return $this->language->isTriggerDisabled();
  }


  /**
   * Apply a function each time the methods $kind are used
   *
   * @param callable            $function
   * @param array|string|null   $kind     select|insert|update|delete
   * @param array|string|null   $moment   before|after
   * @param null|string|array   $tables   database's table(s) name(s)
   * @return Db
   */
  public function setTrigger(callable $function, $kind = null, $moment = null, $tables = '*' ): Db
  {
    $this->language->setTrigger($function, $kind, $moment, $tables);

    return $this->db;
  }


  /**
   * Returns an array 
   * 
   * ```php
   * X::adump($ctrl->db->getTriggers());
   * ```
   * @return array
   */
  public function getTriggers(): array
  {
    return $this->language->getTriggers();
  }
}
