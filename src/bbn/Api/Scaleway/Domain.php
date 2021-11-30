<?php
namespace bbn\Api\Scaleway;

/**
 * Scaleway Domain trait
 */
trait Domain
{


  /**
   * Gets a list of domains owned by the current user
   * @return array
   */
  public function getDomains(): array
  {
    return $this->_callCommand('domain/');
  }


  /**
   * Gets a domain info
   * @param string $name The domain name
   * @return array
   */
  public function getDomain(string $name): array
  {
    return $this->_callCommand("domain/$name");
  }


  /**
   * Returns a paginated list of zone version associated with the domain
   * @param string $name The domain name
   * @return array
   */
  public function getDomainVersion(string $name): array
  {
    return $this->_callCommand("domain/$name/version");
  }


  /**
   * Returns the currently active zone of the domain
   * @param string $name The domain name
   * @return array
   */
  public function getDomainZone(string $name): array
  {
    return $this->_callCommand("domain/$name/zone");
  }


}
