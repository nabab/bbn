<?php

namespace bbn\Appui\Vcs;

use bbn;
use bbn\Appui\Passwords;
use bbn\Appui\Option;
use bbn\Api\GitLab;

/**
 * VCS\Svn class
 * @category Appui
 * @package Appui\Vcs
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Appui/Vcs/Svn
 */
class Svn implements Server
{
  use Common;

  /** @var bbn\Db The bbn\Db class instance */
  private $db;

  /** @var bbn\Appui\Passwords The bbn\Appui\Passwords class instance */
  private $pwd;


  /**
   * Constructor.
   * @param bbn\Db $db
   */
  public function __construct($db)
  {
    $this->db = $db;
    $this->opt = Option::getInstance();
    $this->pwd = new Passwords($this->db);
  }


  public function getConnection(string $id)
  {
    if ($server = $this->getServer($id)) {
      $this->checkServerHost($server->host);
      return new \stdClass;
    }
  }


  public function getProjectsList(string $id, int $page = 1, int $perPage = 25): array
  {
    return [];
  }


  public function getProject(string $idServer, string $idProject): ?object
  {
    return null;
  }


  public function getProjectBranches(string $idServer, string $idProject): array
  {
    return [];
  }


  public function getProjectUsers(string $idServer, string $idProject): array
  {
    return [];
  }


  public function normalizeProject(object $project): object
  {
    return (object)[
      'id' => $project->id,
      'name' => $project->name,
      'fullname' => $project->name_with_namespace,
      'description' => $project->description,
      'path' => $project->path,
      'fullpath' => $project->path_with_namespace,
      'url' => $project->web_url,
      'urlGit' => $project->http_url_to_repo,
      'urlSsh' => $project->ssh_url_to_repo,
      'namespace' => [
        'id' => $project->namespace->id,
        'idParent' => $project->namespace->parent_id,
        'name' => $project->namespace->name,
        'path' => $project->namespace->path,
        'fullpath' => $project->namespace->full_path,
        'url' => $project->namespace->web_url
      ],
      'created' => $project->created_at,
      'creator' => $project->creator_id,
      'private' => !empty($project->owner),
      'visibility' => $project->visibility,
      'defaultBranch' => $project->default_branch,
      'archived' => $project->archived
    ];
  }

}