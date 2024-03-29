<?php
namespace bbn\Api\GitLab;

trait Branch
{

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


  /**
   * Creates a new branch into the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $branch The name of the new branch
   * @param string The branch name to create branch from
   * @return array
   */
  public function insertBranch($project, string $branch, string $ref): array
  {
    return $this->post($this->projectURL . $project . '/repository/' . $this->branchURL, [
      'branch' => $branch,
      'ref' => $ref
    ]);
  }


  /**
   * Deletes a specific branch of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $branch The name of the brach
   * @return bool
   */
  public function deleteBranch($project, string $branch): bool
  {
    return $this->delete($this->projectURL . $project . '/repository/' . $this->branchURL . $branch);
  }


}