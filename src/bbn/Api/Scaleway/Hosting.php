<?php
namespace bbn\Api\Scaleway;

/**
 * Scaleway Hosting trait
 */
trait Hosting
{


  /**
   * Gets a list of links to the user's hostings
   * @return array
   */
  public function getHostings(): array
  {
    return $this->_callCommand('hosting');
  }


  /**
   * Gets information on a hosting
   * @param string $id The hosting ID
   * @return array
   */
  public function getHosting(string $id): array
  {
    return $this->_callCommand("hosting/$id");
  }


}
