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
    return $this->request($this->projectURL);
  }


  /**
   * Gets a specific project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProject($project): array
  {
    return $this->request($this->projectURL . $project);
  }


  /**
   * Gets the users list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProjectUsers($project): array
  {
    return $this->request($this->projectURL . $project . '/users');
  }


  /**
   * Gets the groups list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProjectGroups($project): array
  {
    return $this->request($this->projectURL . $project . '/groups');
  }


  /**
   * Gets the commits list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $branch The name of a repository branch, tag or revision range
   * @param string $filePath The file path
   * @param string $since Only commits after or on this date are returned
   * @param string $until Only commits before or on this date are returned
   * @return array
   */
  public function getCommits($project, string $branch = '', string $filePath = '', string $since = '', string $until = ''): array
  {
    $url = $this->projectURL . $project . '/repository/commits';
    $params = [];
    if (!empty($filePath)) {
      $params[] = 'path=' . \urldecode($filePath);
    }
    if (!empty($branch)) {
      $params[] = 'branch=' . $branch;
    }
    if (!empty($since)) {
      $params[] = 'since=' . \date('c', \strtotime($since));
    }
    if (!empty($until)) {
      $params[] = 'until=' . \date('c', \strtotime($until));
    }
    foreach ($params as $i => $p) {
      if ($i === 0) {
        $url .= '?';
      }
      $url .= $p . (!empty($params[$i + 1]) ? '&' : '');
    }
    return $this->request($url);
  }


  /**
   * Gets a specific commit of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $id The commit hash or name of a repository branch or tag
   */
  public function getCommit($project, string $id): array
  {
    return $this->request($this->projectURL . $project . '/repository/commits/' . $id);
  }


  /**
   * Gets the diff of a commit of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $id The commit hash or name of a repository branch or tag
   * @param string $filePath The file path
   */
  public function getDiff($project, string $id, string $filePath = ''): array
  {
    $diff = $this->request($this->projectURL . $project . '/repository/commits/' . $id . '/diff');
    if (!empty($filePath)) {
      if (!\is_null($i = \bbn\X::find($diff, function($d) use($filePath){
        return ($d->old_path === $filePath) || ($d->new_path === $filePath);
      }))) {
        return \bbn\X::toArray($diff[$i]);
      }
      return [];
    }
    return $diff;
  }


  /**
   * Gets the issues list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getIssues($project): array
  {
    return $this->request($this->projectURL . $project . '/issues');
  }


  /**
   * Gets the closed issues list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getClosedIssues($project): array
  {
    return $this->request($this->projectURL . $project . '/issues?state=closed');
  }


  /**
   * Gets the opened issues list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getOpenedIssues($project): array
  {
    return $this->request($this->projectURL . $project . '/issues?state=opened');
  }


}