<?php
namespace bbn\Api\GitLab;

trait Issue
{


  /** @var string */
  protected $issueURL = 'issues/';

  /**
   * Get the issues list
   * @return array
   */
  public function getAllIssues(): array
  {
    return $this->request($this->issueURL . '?scope=all');
  }


  /**
   * Gets the assigned issues list
   * @return array
   */
  public function getAssigendIssues(): array
  {
    return $this->request($this->issueURL . '?scope=all&assignee_id=Any');
  }


  /**
   * Gets the issues list of the current user
   * @return array
   */
  public function getMyIssues(): array
  {
    return $this->request($this->issueURL . '?scope=all');
  }


  /**
   * Gets the assigned issues list of the current user
   * @return array
   */
  public function getMyAssigendIssues(): array
  {
    return $this->request($this->issueURL . '?scope=all&assignee_id=' . $this->getUserID());
  }


}