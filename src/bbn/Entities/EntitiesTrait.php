<?php

namespace bbn\Entities;

use Exception;
use bbn\X;
use bbn\Appui\Option;

trait EntitiesTrait
{
  protected $id_entity;
  protected function people(): ?People
  {
    return $this->entities->people();
  }

  protected function address(): ?Address
  {
    return $this->entities->address();
  }
  
  protected function option(): ?Option
  {
    return $this->entities->option();
  }

  public function getEntity()
  {
    return $this->id_entity;
  }

  public function setEntity($id_entity)
  {
    if (!isset($this->fields['id_entity'])) {
      throw new Exception(X::_("There is no id_entity column"));
    }

    if (!$this->entities->exists($id_entity)) {
      throw new Exception(X::_("The entity does not exist"));
    }

    $this->id_entity = $id_entity;
    $this->DbActionsSetFilterCfg([$this->fields['id_entity'] => $id_entity]);
  }

  public function unsetEntity()
  {
    $this->id_entity = null;
    $this->DbActionsResetFilterCfg();
  }



}
