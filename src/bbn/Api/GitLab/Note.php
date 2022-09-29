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
   * Create an issue note of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $issue The issue ID
   * @param string $content The note content
   * @param string $date The note date
   * @return null|int
   */
  public function createIssueNote($project, int $issue, string $content, string $date = ''): ?int
  {
    $params = [
      'body' => \urlencode($content)
    ];
    if (!empty($date)) {
      $params['created_at'] = \date('c', \strtotime($date));
    }
    if (($iss = $this->getIssue($issue))
      && !empty($iss['iid'])
      && ($note = $this->post($this->projectURL . $project . '/' . $this->issueURL . $iss['iid'] . '/' . $this->noteURL, $params))
    ) {
      return $note['id'];
    }
    return null;
  }


}