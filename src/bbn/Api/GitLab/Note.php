<?php
namespace bbn\Api\GitLab;

trait Note
{

  /**
   * Gets the notes list of a specific issue of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param string $sort The sorting direction 'asc' or 'desc'
   * @param string $order Order by 'creation' date or 'modification' date
   * @return array
   */
  public function getIssueNotes($project, int $iid, string $sort = 'asc', $order = 'creation'): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->issueURL . $iid . '/' . $this->noteURL, [
      'sort' => $sort,
      'order_by' => $order === 'creation' ? 'created_at' : 'update_at'
    ]);
  }


  /**
   * Gets a note of a specific issue of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param int $note The note ID
   * @return array
   */
  public function getIssueNote($project, int $iid, int $note): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->issueURL . $iid . '/' . $this->noteURL . $note);
  }


  /**
   * Create an issue note of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param string $content The note content
   * @param bool $internatl The internal flag
   * @param string $date The note date
   * @return array
   */
  public function createIssueNote($project, int $iid, string $content, bool $internal = false, string $date = ''): array
  {
    $params = [
      'body' => $content,
      'internal' => empty($internal) ? 'false' : 'true'
    ];
    if (!empty($date)) {
      $params['created_at'] = \date('c', \strtotime($date));
    }
    return $this->post($this->projectURL . $project . '/' . $this->issueURL . $iid . '/' . $this->noteURL, $params);
  }


  /**
   * Edit an issue note of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param int $note The note ID
   * @param string $content The note content
   * @return array
   */
  public function editIssueNote($project, int $iid, int $note, string $content, bool $internal = false): array
  {
    $params = [
      'body' => $content,
      //'internal' => empty($internal) ? 'false' : 'true'
    ];
    return $this->put($this->projectURL . $project . '/' . $this->issueURL . $iid . '/' . $this->noteURL . $note, $params);
  }


  /**
   * Delete an issue note of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param int $note The note ID
   * @return bool
   */
  public function deleteIssueNote($project, int $iid, int $note): bool
  {
    return $this->delete($this->projectURL . $project . '/' . $this->issueURL . $iid . '/' . $this->noteURL . $note);
  }


}