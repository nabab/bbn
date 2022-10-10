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
      'with_counts' => true,
      'page' => 0,
      'per_page' => 5000
    ]);
  }


  /**
   * Creates a label to the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $name The label namne
   * @param string $color The label color
   * @return array
   */
  public function createProjectLabel($project, string $name, string $color): array
  {
    return $this->post($this->projectURL . $project . '/' . $this->labelURL, [
      'name' => $name,
      'color' => $color
    ]);
  }


  /**
   * Adds an issue label to the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param string $label The label name
   * @return bool
   */
  public function addLabelToProjectIssue($project, int $iid, string $label): bool
  {
    return !!$this->put($this->projectURL . $project . '/' . $this->issueURL . $iid, [
      'add_labels' => $label
    ]);
  }


  /**
   * Removes an issue label from the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $iid The issue internal ID
   * @param string $label The label name
   * @return bool
   */
  public function removeLabelFromProjectIssue($project, int $iid, string $label): bool
  {
    return !!$this->put($this->projectURL . $project . '/' . $this->issueURL . $iid, [
      'remove_labels' => $label
    ]);
  }


}