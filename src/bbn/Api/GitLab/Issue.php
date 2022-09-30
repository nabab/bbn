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
   * Gets a specific issue
   * @param int $id The issue ID
   * @return array
   */
  public function getIssue(int $id): array
  {
    return $this->request($this->issueURL . $id);
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
   * @param string $date The issue's date
   */
  public function createIssue($project, string $title, string $date = ''): ?int
  {
    $params = [
      'title' => \urlencode($title)
    ];
    if (!empty($date)) {
      $params['created_at'] = \date('c', \strtotime($date));
    }
    if ($issue = $this->post($this->projectURL . $project . '/' . $this->issueURL, $params)) {
      return $issue['id'];
    }
    return null;
  }


  /**
   * Closes an issue of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int The issue ID
   * @return null|array
   */
  public function closeIssue($project, int $issue): ?array
  {
    if (($i = $this->getIssue($issue))
      && !empty($i['iid'])
    ) {
      return $this->put($this->projectURL . $project . '/' . $this->issueURL . $i['iid'], [
        'state_event' => 'close'
      ]);
    }
    return null;
  }


  /**
   * Reopens an issue of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int The issue ID
   * @return null|array
   */
  public function reopenIssue($project, int $issue): ?array
  {
    if (($i = $this->getIssue($issue))
      && !empty($i['iid'])
    ) {
      return $this->put($this->projectURL . $project . '/' . $this->issueURL . $i['iid'], [
        'state_event' => 'reopen'
      ]);
    }
    return null;
  }


  /**
   * Assigns an issue of the given project to an user
   * @param int|string $project ID or URL-encoded path of the project
   * @param int The issue ID
   * @param int The user ID
   * @return null|array
   */
  public function assignIssue($project, int $issue, int $user): ?array
  {
    if (($i = $this->getIssue($issue))
      && !empty($i['iid'])
    ) {
      return $this->put($this->projectURL . $project . '/' . $this->issueURL . $i['iid'], [
        'assignee_ids' => $user
      ]);
    }
    return null;
  }


}