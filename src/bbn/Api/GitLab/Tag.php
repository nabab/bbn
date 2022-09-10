<?php
namespace bbn\Api\GitLab;

trait Tag
{

  /**
   * Gets the tags of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getTags($project): array
  {
    return $this->request($this->projectURL . $project . '/repository/' . $this->tagURL);
  }


  /**
   * Gets a specific tag of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param string $tag The ID of the tag
   * @return array
   */
  public function getTag($project, string $tag): array
  {
    return $this->request($this->projectURL . $project . '/repository/' . $this->tagURL . $tag);
  }


}