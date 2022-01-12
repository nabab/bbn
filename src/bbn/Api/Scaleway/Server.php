<?php
namespace bbn\Api\Scaleway;

use bbn\Str;

/**
 * Scaleway Server trait
 */
trait Server
{


  /**
   * Gets the servers list
   * @return array
   */
  public function getServers(): array
  {
    return $this->_callCommand('server');
  }


  /**
   * Gets the server info
   * @param int|string $id The server ID
   * @return array
   */
  public function getServer(int|string $id): array
  {
    return $this->_callCommand(Str::isInteger($id) ? "server/$id" : $id);
  }


  /**
   * Gets the failover IPs list
   * @return array
   */
  public function getFailoverIps(): array
  {
    return $this->_callCommand('server/failover');
  }


  /**
   * Gets the IP info
   * @param string $ip The IP address
   * @return array
   */
  public function getIpInfo(string $ip): array
  {
    return $this->_callCommand("server/ip/$ip");
  }


  /**
   * Gets information on a server's disk
   * @param int|string $id The disk ID
   * @return array
   */
  public function getDiskInfo(int|string $id): array
  {
    return $this->_callCommand(Str::isInteger($id) ? "server/hardware/disk/$id" : $id);
  }


  /**
   * Gets information on a server's RAID controller
   * @param int|string $id The disk ID
   * @return array
   */
  public function getRaidInfo(int|string $id): array
  {
    return $this->_callCommand(Str::isInteger($id) ? "server/hardware/raidController/$id" : $id);
  }


  /**
   * Gets server product info
   * @param int|string $id The server ID
   * @return array
   */
  public function getProductInfo(int|string $id): array
  {
    return $this->_callCommand(Str::isInteger($id) ? "server/product/$id" : $id);
  }


}
