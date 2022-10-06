<?php
namespace bbn\Api\GitLab;

trait Note
{

  /**
   * Gets the notes list of a specific issue of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $issue The issue ID
   * @param string $sort The sorting direction 'asc' or 'desc'
   * @param string $order Order by 'creation' date or 'modification' date
   * @return array
   */
  public function getIssueNotes($project, int $issue, string $sort = 'asc', $order = 'creation'): array
  {
    if (($i = $this->getIssue($issue))
      && !empty($i['iid'])
    ) {
      return $this->request($this->projectURL . $project . '/' . $this->issueURL . $i['iid'] . '/' . $this->noteURL, [
        'sort' => $sort,
        'order_by' => $order === 'creation' ? 'created_at' : 'update_at'
      ]);
    }
    return [];
  }


  /**
   * Gets a note of a specific issue of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $issue The issue ID
   * @param int $note The note ID
   * @return array
   */
  public function getIssueNote($project, int $issue, int $note): array
  {
    if (($i = $this->getIssue($issue))
      && !empty($i['iid'])
    ) {
      return $this->request($this->projectURL . $project . '/' . $this->issueURL . $i['iid'] . '/' . $this->noteURL . $note);
    }
    return [];
  }


  /**
   * Create an issue note of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $issue The issue ID
   * @param string $content The note content
   * @param bool $internatl The internal flag
   * @param string $date The note date
   * @return null|array
   */
  public function createIssueNote($project, int $issue, string $content, bool $internal = false, string $date = ''): ?array
  {
    $params = [
      'body' => \urlencode($content),
      'internal' => empty($internal) ? 'false' : 'true'
    ];
    if (!empty($date)) {
      $params['created_at'] = \date('c', \strtotime($date));
    }
    if (($iss = $this->getIssue($issue))
      && !empty($iss['iid'])
    ) {
      return $this->post($this->projectURL . $project . '/' . $this->issueURL . $iss['iid'] . '/' . $this->noteURL, $params);
    }
    return null;
  }


  /**
   * Edit an issue note of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $issue The issue ID
   * @param int $note The note ID
   * @param string $content The note content
   * @return null|array
   */
  public function editIssueNote($project, int $issue, int $note, string $content, bool $internal = false): ?array
  {
    $params = [
      'body' => \urlencode($content),
      //'internal' => empty($internal) ? 'false' : 'true'
    ];
    if (($iss = $this->getIssue($issue))
      && !empty($iss['iid'])
    ) {
      return $this->put($this->projectURL . $project . '/' . $this->issueURL . $iss['iid'] . '/' . $this->noteURL . $note, $params);
    }
    return null;
  }


  /**
   * Delete an issue note of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $issue The issue ID
   * @param int $note The note ID
   * @return bool
   */
  public function deleteIssueNote($project, int $issue, int $note): bool
  {
    if (($iss = $this->getIssue($issue))
      && !empty($iss['iid'])
    ) {
      return $this->delete($this->projectURL . $project . '/' . $this->issueURL . $iss['iid'] . '/' . $this->noteURL . $note);
    }
    return false;
  }


}