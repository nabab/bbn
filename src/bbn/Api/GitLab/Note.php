<?php
namespace bbn\Api\GitLab;

trait Note
{

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
      && ($note = $this->post($this->projectURL . $project . '/' . $this->issueURL . $iss['iid'], $params))
    ) {
      return $note['id'];
    }
    return null;
  }


}