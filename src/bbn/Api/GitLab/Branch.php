<?php
namespace bbn\Api\GitLab;

trait Branch
{


  /** @var string */
  protected $branchURL = 'branches/';

  /**
   * Gets the branches of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getBranches($project): array
  {
    return $this->request($this->projectURL . $project . '/repository/' . $this->branchURL);
  }


  /**
   * Gets a specific branch of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $branch The name of the brach
   * @return array
   */
  public function getBranch($project, string $branch): array
  {
    return $this->request($this->projectURL . $project . '/repository/' . $this->branchURL . $branch);
  }


}