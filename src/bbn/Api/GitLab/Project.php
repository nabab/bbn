<?php
namespace bbn\Api\GitLab;

trait Project
{

  /**
   * Gets the list of projects to which you have access
   * @return array
   */
  public function getProjects(): array
  {
    return $this->request($this->projectURL, [
      'order_by' => 'name',
      'sort' => 'asc'
    ]);
  }


  /**
   * Gets the list of projects to which you have access (simple mode)
   * @return array
   */
  public function getProjectsSimple(): array
  {
    return $this->request($this->projectURL, [
      'simple' => true,
      'order_by' => 'name',
      'sort' => 'asc'
    ]);
  }


  /**
   * Gets the list of projects to which you have access
   * @param int $page
   * @param int $perPage
   * @return array
   */
  public function getProjectsList(int $page = 1, int $perPage = 25): array
  {
    $list = $this->request($this->projectURL, [
      'order_by' => 'name',
      'sort' => 'asc',
      'page' => $page,
      'per_page' => $perPage
    ]);
    $header = $this->getLastResponseHeader();
    return [
      'data' => $list,
      'total' => (int)$header['x-total'],
      'limit' => $perPage
    ];
  }


  /**
   * Gets a specific project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProject($project, bool $includeStats = false): array
  {
    $params = [];
    if ($includeStats) {
      $params['statistics'] = true;
    }
    return $this->request($this->projectURL . $project, $params);
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
    $params = [];
    if (!empty($filePath)) {
      $params['path'] = \urldecode($filePath);
    }
    if (!empty($branch)) {
      $params['branch'] = $branch;
    }
    if (!empty($since)) {
      $params['since'] = \date('c', \strtotime($since));
    }
    if (!empty($until)) {
      $params['until'] = \date('c', \strtotime($until));
    }
    return $this->request($this->projectURL . $project . '/repository/commits', $params);
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


}