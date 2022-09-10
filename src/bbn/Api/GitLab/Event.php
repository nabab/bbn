<?php
namespace bbn\Api\GitLab;

trait Event
{

  /**
   * Gets the events of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getEvents($project): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->eventURL);
  }


  /**
   * Gets the users events of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getUsersEvents($project): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->eventURL, ['action' => 'joined']);
  }


  /**
   * Gets the commits events of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getCommitsEvents($project): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->eventURL, ['action' => 'pushed']);
  }


}