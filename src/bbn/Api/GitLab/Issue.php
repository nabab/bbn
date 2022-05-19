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
  public function getIssues(): array
  {
    return $this->request($this->host . $this->issueURL);
  }


}