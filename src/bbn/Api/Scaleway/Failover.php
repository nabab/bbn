<?php
namespace bbn\Api\Scaleway;

use bbn\X;

/**
 * Scaleway Failover trait
 */
trait Failover
{


  /**
   * Gets the list of available failover ips
   * @return array
   */
  public function getAvailableFailoverIps(): array
  {
    return X::sortBy($this->_callCommand('failover/ips'), 'ip', 'asc');
  }


}
