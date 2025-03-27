<?php
namespace bbn\Api\GitLab;

trait Issue
{

  /**
   * Get the issues list
   * @return array
   */
  public function getAllIssues(): array
  {
    return $this->request($this->issueURL, ['scope' => 'all']);
  }


  /**
   * Gets the assigned issues list
   * @return array
   */
  public function getAssigendIssues(): array
  {
    return $this->request($this->issueURL, [
      'scope' => 'all',
      'assignee_id' => 'Any'
    ]);
  }


  /**
   * Gets the issues list of the current user
   * @return array
   */
  public function getMyIssues(): array
  {
    return $this->request($this->issueURL, ['scope' => 'all']);
  }


  /**
   * Gets the assigned issues list of the current user
   * @return array
   */
  public function getMyAssigendIssues(): array
  {
    return $this->request($this->issueURL, [
      'scope' => 'all',
      'assignee_id' => $this->getUserID()
    ]);
  }


  /**
   * Gets a specific issue (only administrator)
   * @param int $id The issue ID
   * @return array
   */
  public function getIssue(int $id): array
  {
    return $this->request($this->issueURL . $id);
  }


  /**
   * Gets a specific project issue
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @return array
   */
  public function getProjectIssue($project, int $iid): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->issueURL . $iid);
  }


  /**
   * Gets the issues list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getIssues($project): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->issueURL, [
      'scope' => 'all',
      'page' => 0,
      'per_page' => 5000
    ]);
  }


  /**
   * Gets the closed issues list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getClosedIssues($project): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->issueURL, [
      'scope' => 'all',
      'state' => 'closed'
    ]);
  }


  /**
   * Gets the opened issues list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getOpenedIssues($project): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->issueURL, [
      'scope' => 'all',
      'state' => 'opened'
    ]);
  }


  /**
   * Creates a new issue to the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $title The issue's title
   * @param string $description The issue's description
   * @param array $labels The labels
   * @param int $assigned The ID of the user to whom the issue is assigned
   * @param bool $private If the issue is confidential
   * @param string $date The issue's date
   * @return array|null
   */
  public function createIssue(
    $project,
    string $title,
    string $description = '',
    array $labels = [],
    ?int $assigned = null,
    bool $private = false,
    string $date = ''
  ): ?array
  {
    $params = [
      'title' => $title,
      'description' => $description,
      'labels' => \implode(',', $labels),
      'issue_type' => 'issue'
    ];
    if (!empty($private)) {
      $params['confidential'] = 'true';
    }
    if (!empty($assigned)) {
      $params['assignee_ids'] = $assigned;
    }
    if (!empty($date)) {
      $params['created_at'] = \date('c', \strtotime($date));
    }
    if ($issue = $this->post($this->projectURL . $project . '/' . $this->issueURL, $params)) {
      return $issue;
    }
    return null;
  }


  /**
   * Edites an issue on the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param string $title The issue's title
   * @param string $description The issue's description
   * @param array $labels The labels
   * @param int $assigned The ID of the user to whom the issue is assigned
   * @param bool $private If the issue is confidential
   * @return array
   */
  public function editIssue(
    $project,
    int $iid,
    string $title,
    string $description = '',
    array $labels = [],
    int $assigned = 0,
    bool $private = false
  ): array
  {
    $params = [
      'title' => $title,
      'description' => $description,
      'labels' => \implode(',', $labels),
      'confidential' => empty($private) ? 'false' : 'true',
      'assignee_ids' => $assigned
    ];
    return $this->put($this->projectURL . $project . '/' . $this->issueURL . $iid, $params);
  }


  /**
   * Closes an issue of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue interal ID
   * @return array
   */
  public function closeIssue($project, int $iid): array
  {
    return $this->put($this->projectURL . $project . '/' . $this->issueURL . $iid, [
      'state_event' => 'close'
    ]);
  }


  /**
   * Reopens an issue of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @return array
   */
  public function reopenIssue($project, int $iid): array
  {
    return $this->put($this->projectURL . $project . '/' . $this->issueURL . $iid, [
      'state_event' => 'reopen'
    ]);
  }


  /**
   * Assigns an issue of the given project to an user
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param int $user The user ID
   * @return array
   */
  public function assignIssue($project, int $iid, int $user): array
  {
    return $this->put($this->projectURL . $project . '/' . $this->issueURL . $iid, [
      'assignee_ids' => $user
    ]);
  }


}