<?php
namespace bbn\Api\GitLab;

trait Project
{


  /** @var string */
  protected $projectURL = 'projects/';

  /**
   * Gets the list of projects to which you have access
   * @return array
   */
  public function getProjects(): array
  {
    return $this->request($this->host . $this->projectURL);
  }


  /**
   * Gets a specific project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProject($project): array
  {
    return $this->request($this->host . $this->projectURL . $project);
  }


  /**
   * Gets the users list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProjectUsers($project): array
  {
    return $this->request($this->host . $this->projectURL . $project . '/users');
  }


  /**
   * Gets the groups list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProjectGroups($project): array
  {
    return $this->request($this->host . $this->projectURL . $project . '/groups');
  }


}