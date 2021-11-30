<?php

namespace bbn\Api\Scaleway;

/**
 * Scaleway Dedibox trait
 */
trait Dedibox
{


  /**
   * Gets Dedibox plans list
   * @return array
   */
  public function getPlans(): array
  {
    return $this->_callCommand('dedibox/plans');
  }


  /**
   * Gets the list of available options products
   * @param int $id The dedibox product ID
   * @return array
   */
  public function getDediboxOptions(int $id): array
  {
    return $this->_callCommand("dedibox/options/$id");
  }


  /**
   * Gets the list of available datacenters
   * @param int $id The dedibox product ID
   * @return array
   */
  public function getDediboxDatacenters(int $id): array
  {
    return $this->_callCommand("dedibox/availability/$id");
  }


}
