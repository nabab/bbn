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


  /**
   * @param string $id
   * @param bool $asAdmin
   * @return object
   */
  public function getConnection(string $id, bool $asAdmin = false): object
  {
    $server = $this->getServer($id);
    $this->checkServerHost($server->host);
    return new bbn\Api\GitLab($asAdmin ? $this->getAdminAccessToken($id) : $server->userAccessToken, $server->host);
  }


  /**
   * @param string $id
   * @return array
   */
  public function getCurrentUser(string $id): array
  {
    return $this->getConnection($id)->getUser();
  }


  /**
   * @param string $id
   * @param int $page
   * @param int $perPage
   * @return array
   */
  public function getProjectsList(string $id, int $page = 1, int $perPage = 25): array
  {
    $list = $this->getConnection($id)->getProjectsList($page, $perPage) ?: [];
    $list['data'] = \array_map([$this, 'normalizeProject'], $list['data']);
    return $list;
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @return null|array
   */
  public function getProject(string $idServer, string $idProject): ?array
  {
    if ($proj = $this->getConnection($idServer, true)->getProject($idProject, true)) {
      return $this->normalizeProject((object)$proj);
    }
    return null;
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @return array
   */
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


  /**
   * @param string $idServer
   * @param string $idProject
   * @return array
   */
  public function getProjectTags(string $idServer, string $idProject): array
  {
    return $this->getConnection($idServer)->getTags($idProject) ?: [];
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @return array
   */
  public function getProjectUsers(string $idServer, string $idProject): array
  {
    return \array_map([$this, 'normalizeMember'], $this->getConnection($idServer, true)->getProjectUsers($idProject) ?: []);
  }


  /**
   * @return array
   */
  public function getProjectUsersRoles(): array
  {
    return bbn\Api\GitLab::$accessLevels;
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @return array
   */
  public function getProjectUsersEvents(string $idServer, string $idProject): array
  {
    return \array_map([$this, 'normalizeEvent'], $this->getConnection($idServer)->getUsersEvents($idProject) ?: []);
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @return array
   */
  public function getProjectEvents(string $idServer, string $idProject): array
  {
    return \array_map([$this, 'normalizeEvent'], $this->getConnection($idServer)->getEvents($idProject) ?: []);
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @return array
   */
  public function getProjectCommitsEvents(string $idServer, string $idProject): array
  {
    return \array_values(
      \array_filter(
        \array_map(
          [$this, 'normalizeEvent'],
          $this->getConnection($idServer)->getCommitsEvents($idProject) ?: []
        ),
        function($e){
          return $e['type'] === 'commit';
        }
      )
    );
  }


   /**
   * @param string $idServer
   * @param string $idProject
   * @return array
   */
  public function getProjectLabels(string $idServer, string $idProject): array
  {
    return \array_map(
      [$this, 'normalizeLabel'],
      $this->getConnection($idServer)->getProjectLabels($idProject) ?: []
    );
  }


  /**
   * @param string $idServer
   * @return array
   */
  public function getUsers(string $idServer): array
  {
    return \array_map([$this, 'normalizeUser'], $this->getConnection($idServer, true)->getUsers() ?: []);
  }


  /**
   * @param object $branch
   * @return array
   */
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


  /**
   * @param object $event
   * @return array
   */
  public function normalizeEvent(object $event): array
  {
    $data = [
      'id' => $event->id,
      'created' => $event->created_at,
      'author' => $this->normalizeUser($event->author),
      'type' => '',
      'title' => '',
      'text' => '',
      'original' => $event
    ];
    switch ($event->action_name) {
      case 'pushed to':
        $data = X::mergeArrays($data, [
          'type' => 'commit',
          'text' => $event->push_data->commit_title,
          'branch' => $event->push_data->ref
        ]);
        break;
      case 'pushed new':
        $data = X::mergeArrays($data, [
          'type' => 'branch',
          'text' => X::_('Branch created'),
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
      case 'joined':
        $data = X::mergeArrays($data, [
          'type' => 'user',
          'title' => X::_('Has been included among the users of the project')
        ]);
        break;
      case 'left':
        $data = X::mergeArrays($data, [
          'type' => 'user',
          'title' => X::_('Has been removed from project users')
        ]);
        break;
    }
    return $data;
  }


  /**
   * @param object $user
   * @return array
   */
  public function normalizeUser(object $user): array
  {
    return [
      'id' => $user->id,
      'name' => $user->name,
      'username' => $user->username,
      'email' => $user->email ?? '',
      'avatar' => $user->avatar_url,
      'url' => $user->web_url
    ];
  }


  /**
   * @param object $member
   * @return array
   */
  public function normalizeMember(object $member): array
  {
    return X::mergeArrays([
      'created' => $member->created_at,
      'author' => !empty($member->created_by) ? $this->normalizeUser($member->created_by) : [],
      'expire' => $member->expires_at,
      'role' => $this->getProjectUsersRoles()[$member->access_level]
    ], $this->normalizeUser($member));
  }


  /**
   * @param object $user
   * @return array
   */
  public function normalizeLabel(object $label): array
  {
    return [
      'id' => $label->id,
      'name' => $label->name,
      'description' => $label->description ?: '',
      'backgroundColor' => $label->color,
      'fontColor' => $label->text_color,
      'openedIssues' => $label->open_issues_count,
      'closedIssues' => $label->closed_issues_count
    ];
  }


  /**
   * @param object $project
   * @return array
   */
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


  /**
   * @param object $issue
   * @return array
   */
  public function normalizeIssue(object $issue): array
  {
    return [
      'id' => $issue->id,
      'iid' => $issue->iid,
      'title' => $issue->title,
      'description' => $issue->description ?: '',
      'url' => $issue->web_url,
      'author' => $this->normalizeUser($issue->author),
      'created' => $issue->created_at,
      'updated' => $issue->updated_at ?: $issue->created_at,
      'closed' => $issue->closed_at,
      'closedBy' => !empty($issue->closed_by) ? $this->normalizeUser($issue->closed_by) : [],
      'assigned' => !empty($issue->assignee) ? $this->normalizeUser($issue->assignee) : [],
      'private' => $issue->confidential,
      'labels' => $issue->labels,
      'state' => $issue->state,
      'notes' => $issue->user_notes_count,
      'tasks' => [
        'count' => $issue->task_completion_status->count,
        'completed' => $issue->task_completion_status->completed_count,
      ]
    ];
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @param string $branch
   * @param string $fromBranch
   * @return array
   */
  public function insertBranch(string $idServer, string $idProject, string $branch, string $fromBranch): array
  {
    return $this->getConnection($idServer)->insertBranch($idProject, $branch, $fromBranch);
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @param string $branch
   * @return bool
   */
  public function deleteBranch(string $idServer, string $idProject, string $branch): bool
  {
    return $this->getConnection($idServer)->deleteBranch($idProject, $branch);
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @param int $idUser
   * @param int $idRols
   * @return array
   */
  public function insertProjectUser(string $idServer, string $idProject, int $idUser, int $idRole): array
  {
    return $this->getConnection($idServer)->insertProjectUser($idProject, $idUser, $idRole);
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @param int $idUser
   * @return bool
   */
  public function removeProjectUser(string $idServer, string $idProject, int $idUser): bool
  {
    return $this->getConnection($idServer)->removeProjectUser($idProject, $idUser);
  }


  /**
   * @param string $idServer
   * @param string $idProject
   * @return bool
   */
  public function getProjectIssues(string $idServer, string $idProject): array
  {
    return \array_map(
      [$this, 'normalizeIssue'],
      $this->getConnection($idServer)->getIssues($idProject)
    );
  }


}