<?php
namespace bbn\Api\GitLab;

trait Label
{

  /**
   * Gets the labels list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProjectLabels($project): array
  {
    return $this->request($this->projectURL . $project . '/' . $this->labelURL, [
      'with_counts' => true
    ]);
  }


}