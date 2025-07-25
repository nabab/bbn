<?php

namespace bbn\Db\Internal;

trait Triggers
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
   * @return self
   */
  public function enableTrigger(): self
  {
    $this->language->enableTrigger();
    return $this;
  }


  /**
   * Disable the triggers' functions
   * 
   * ```php
   * X::adump($ctrl->db->disableTrigger());
   * ```
   * 
   * @return self
   */
  public function disableTrigger(): self
  {
    $this->language->disableTrigger();
    return $this;
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
   * @return self
   */
  public function setTrigger(callable $function, $kind = null, $moment = null, $tables = '*' ): self
  {
    $this->language->setTrigger($function, $kind, $moment, $tables);

    return $this;
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
