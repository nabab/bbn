<?php

namespace bbn\Appui\Vcs;

use bbn;
use bbn\Appui\Passwords;
use bbn\Appui\Option;
use bbn\Api\GitLab;
use bbn\X;

/**
 * VCS\Git class
 * @category Appui
 * @package Appui\Vcs
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Appui/Vcs/Git
 */
class Git implements Server
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


  public function getConnection(string $id, bool $asAdmin = false): object
  {
    $server = $this->getServer($id);
    $this->checkServerHost($server->host);
    return new GitLab($asAdmin ? $this->getAdminAccessToken($id) : $server->userAccessToken, $server->host);
  }


  public function getProjectsList(string $id, int $page = 1, int $perPage = 25): array
  {
    $list = $this->getConnection($id)->getProjectsList($page, $perPage) ?: [];
    $list['data'] = \array_map([$this, 'normalizeProject'], $list['data']);
    return $list;
  }


  public function getProject(string $idServer, string $idProject): ?object
  {
    if ($proj = $this->getConnection($idServer, true)->getProject($idProject, true)) {
      return $this->normalizeProject((object)$proj);
    }
    return null;
  }


  public function getProjectBranches(string $idServer, string $idProject): array
  {
    return $this->getConnection($idServer)->getBranches($idProject) ?: [];
  }


  public function getProjectTags(string $idServer, string $idProject): array
  {
    return $this->getConnection($idServer)->getTags($idProject) ?: [];
  }


  public function getProjectUsers(string $idServer, string $idProject): array
  {
    return \array_map([$this, 'normalizeUser'], $this->getConnection($idServer)->getProjectUsers($idProject) ?: []);
  }


  public function getProjectUsersEvents(string $idServer, string $idProject): array
  {
    return \array_map([$this, 'normalizeEvent'], $this->getConnection($idServer)->getUsersEvents($idProject) ?: []);
  }


  public function getProjectEvents(string $idServer, string $idProject): array
  {
    return \array_map([$this, 'normalizeEvent'], $this->getConnection($idServer)->getEvents($idProject) ?: []);
  }


  public function getProjectCommitsEvents(string $idServer, string $idProject): array
  {
    return \array_map([$this, 'normalizeEvent'], $this->getConnection($idServer)->getCommitsEvents($idProject) ?: []);
  }


  public function normalizeEvent(object $event): object
  {
    $data = [
      'id' => $event->id,
      'created' => $event->created_at,
      'author' => $this->normalizeUser($event->author),
      'title' => ''
    ];
    if (isset($event->push_data)) {
      $data = X::mergeArrays($data, [
        'title' => $event->push_data->commit_title,
        'branch' => $event->push_data->ref,
      ]);
    }
    return (object)$data;
  }


  public function normalizeUser(object $user): object
  {
    return (object)[
      'id' => $user->id,
      'name' => $user->name,
      'username' => $user->username,
      'avatar' => $user->avatar_url,
      'url' => $user->web_url
    ];
  }


  public function normalizeProject(object $project): object
  {
    return (object)[
      'id' => $project->id,
      'type' => 'git',
      'name' => $project->name,
      'fullname' => $project->name_with_namespace,
      'description' => $project->description ?: '',
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
      'archived' => $project->archived,
      'avatar' => $project->avatar_url,
      'license' => [
        'name' => $project->license->name,
        'code' => $project->license->nickname
      ],
      'noCommits' => $project->statistics['commit_count'],
      'size' => $project->statistics['repository_size'],
      'noForks' => $project->forks_count,
      'noStars' => $project->star_count
    ];
  }

}