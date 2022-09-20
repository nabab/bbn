<?php

namespace bbn\Appui\Vcs;

use bbn;
use bbn\Appui\Passwords;
use bbn\Appui\Option;
use bbn\X;

/**
 * VCS\Git class
 * @category Appui
 * @package Appui\Vcs
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Appui/Vcs/Git
 */
class GitLab implements Server
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
    return new bbn\Api\GitLab($asAdmin ? $this->getAdminAccessToken($id) : $server->userAccessToken, $server->host);
  }


  public function getCurrentUser(string $id): array
  {
    return $this->getConnection($id)->getUser();
  }


  public function getProjectsList(string $id, int $page = 1, int $perPage = 25): array
  {
    $list = $this->getConnection($id)->getProjectsList($page, $perPage) ?: [];
    $list['data'] = \array_map([$this, 'normalizeProject'], $list['data']);
    return $list;
  }


  public function getProject(string $idServer, string $idProject): ?array
  {
    if ($proj = $this->getConnection($idServer, true)->getProject($idProject, true)) {
      return $this->normalizeProject((object)$proj);
    }
    return null;
  }


  public function getProjectBranches(string $idServer, string $idProject): array
  {
    return X::sortBy(
      \array_map(
        [$this, 'normalizeBranch'],
        $this->getConnection($idServer)->getBranches($idProject) ?: []
      ),
      'created',
      'desc'
    );
  }


  public function getProjectTags(string $idServer, string $idProject): array
  {
    return $this->getConnection($idServer)->getTags($idProject) ?: [];
  }


  public function getProjectUsers(string $idServer, string $idProject): array
  {
    return \array_map([$this, 'normalizeMember'], $this->getConnection($idServer, true)->getProjectUsers($idProject) ?: []);
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


  public function normalizeBranch(object $branch): array
  {
    return [
      'id' => $branch->commit->id,
      'ref' => $branch->commit->short_id,
      'name' => $branch->name,
      'created' => $branch->commit->created_at,
      'default' => $branch->default,
      'author' => [
        'id' => '',
        'name' => $branch->commit->author_name,
        'username' => '',
        'email' => $branch->commit->author_email
      ],
      'url' => $branch->web_url
    ];
  }


  public function normalizeEvent(object $event): array
  {
    $data = [
      'id' => $event->id,
      'created' => $event->created_at,
      'author' => $this->normalizeUser($event->author),
      'type' => '',
      'title' => '',
      'text' => ''
    ];
    switch ($event->action_name) {
      case 'pushed to':
      case 'pushed new':
        $data = X::mergeArrays($data, [
          'type' => 'commit',
          'text' => $event->push_data->commit_title,
          'branch' => $event->push_data->ref
        ]);
        break;
      case 'imported':
        $data = X::mergeArrays($data, [
          'type' => 'import',
          'title' => X::_('Project imported')
        ]);
        break;
      case 'removed':
      case 'deleted':
        if (isset($event->push_data)) {
          $data = X::mergeArrays($data, [
            'type' => 'branch',
            'title' => X::_('Branch removed'),
            'branch' => $event->push_data->ref
          ]);
        }
        break;
      case 'accepted':
        if (isset($event->target_type) && ($event->target_type === 'MergeRequest')) {
          $data = X::mergeArrays($data, [
            'type' => 'merge',
            'title' => X::_('Merge request accepted'),
            'text' => $event->target_title ?: ''
          ]);
        }
        break;
      case 'opened':
        if (isset($event->target_type) && ($event->target_type === 'MergeRequest')) {
          $data = X::mergeArrays($data, [
            'type' => 'merge',
            'title' => X::_('Merge request created'),
            'text' => $event->target_title ?: ''
          ]);
        }
        break;
    }
    return $data;
  }


  public function normalizeUser(object $user): array
  {
    return [
      'id' => $user->id,
      'name' => $user->name,
      'username' => $user->username,
      'avatar' => $user->avatar_url,
      'url' => $user->web_url
    ];
  }


  public function normalizeMember(object $member): array
  {
    return X::mergeArrays([
      'created' => $member->created_at,
      'author' => !empty($member->created_by) ? $this->normalizeUser($member->created_by) : [],
      'expire' => $member->expires_at,
      'role' => bbn\Api\GitLab::$accessLevels[$member->access_level]
    ], $this->normalizeUser($member));
  }


  public function normalizeProject(object $project): array
  {
    return [
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
      'namespace' => (object)[
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
      'license' => (object)[
        'name' => $project->license->name,
        'code' => $project->license->nickname
      ],
      'noCommits' => $project->statistics['commit_count'],
      'size' => $project->statistics['repository_size'],
      'noForks' => $project->forks_count,
      'noStars' => $project->star_count
    ];
  }


  public function deleteBranch(string $idServer, string $idProject, string $branch): bool
  {
    return $this->getConnection($idServer)->deleteBranch($idProject, $branch);
  }

}