<?php

namespace bbn\Appui\Vcs;

use bbn;
use bbn\Appui\Passwords;
use bbn\Appui\Option;
use bbn\X;
use bbn\Date;

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

  /** @var string The server ID */
  private $idServer;

  /** @var object The server info */
  private $server;

  /** @var bbn\Api\GitLab The GitLab class instance for normal user */
  private $userConnection;

  /** @var bbn\Api\GitLab The GitLab class instance for admin user */
  private $adminConnection;


  /**
   * Constructor.
   * @param bbn\Db $db
   * @param string $idServer
   */
  public function __construct(bbn\Db $db, string $idServer)
  {
    $this->db = $db;
    $this->opt = Option::getInstance();
    $this->pwd = new Passwords($this->db);
    $this->idServer = $idServer;
    $this->server = $this->getServer();
    $this->checkServerHost($this->server->host);
    $this->userConnection = new bbn\Api\GitLab($this->server->userAccessToken, $this->server->host);
    $this->adminConnection = new bbn\Api\GitLab($this->getAdminAccessToken(), $this->server->host);
  }


  /**
   * @param bool $asAdmin
   * @return object
   */
  public function getConnection(bool $asAdmin = false): object
  {
    return $asAdmin ? $this->adminConnection : $this->userConnection;
  }


  /**
   * @return array
   */
  public function getCurrentUser(): array
  {
    return $this->getConnection()->getUser();
  }


  /**
   * @param int $page
   * @param int $perPage
   * @return array
   */
  public function getProjectsList(int $page = 1, int $perPage = 25): array
  {
    $list = $this->getConnection()->getProjectsList($page, $perPage) ?: [];
    $list['data'] = \array_map([$this, 'normalizeProject'], $list['data']);
    return $list;
  }


  /**
   * @param string $idProject
   * @return null|array
   */
  public function getProject(string $idProject): ?array
  {
    if ($proj = $this->getConnection(true)->getProject($idProject, true)) {
      return $this->normalizeProject((object)$proj);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @return array
   */
  public function getProjectBranches(string $idProject): array
  {
    return X::sortBy(
      \array_map(
        [$this, 'normalizeBranch'],
        $this->getConnection()->getBranches($idProject) ?: []
      ),
      'created',
      'desc'
    );
  }


  /**
   * @param string $idProject
   * @return array
   */
  public function getProjectTags(string $idProject): array
  {
    return $this->getConnection()->getTags($idProject) ?: [];
  }


  /**
   * @param string $idProject
   * @return array
   */
  public function getProjectUsers(string $idProject): array
  {
    return \array_map([$this, 'normalizeMember'], $this->getConnection(true)->getProjectUsers($idProject) ?: []);
  }


  /**
   * @return array
   */
  public function getProjectUsersRoles(): array
  {
    return bbn\Api\GitLab::$accessLevels;
  }


  /**
   * @param string $idProject
   * @return array
   */
  public function getProjectUsersEvents(string $idProject): array
  {
    return \array_map([$this, 'normalizeEvent'], $this->getConnection()->getUsersEvents($idProject) ?: []);
  }


  /**
   * @param string $idProject
   * @return array
   */
  public function getProjectEvents(string $idProject): array
  {
    return \array_map([$this, 'normalizeEvent'], $this->getConnection()->getEvents($idProject) ?: []);
  }


  /**
   * @param string $idProject
   * @return array
   */
  public function getProjectCommitsEvents(string $idProject): array
  {
    return \array_values(
      \array_filter(
        \array_map(
          [$this, 'normalizeEvent'],
          $this->getConnection()->getCommitsEvents($idProject) ?: []
        ),
        function($e){
          return $e['type'] === 'commit';
        }
      )
    );
  }


   /**
   * @param string $idProject
   * @return array
   */
  public function getProjectLabels(string $idProject): array
  {
    return X::sortBy(\array_map(
      [$this, 'normalizeLabel'],
      $this->getConnection()->getProjectLabels($idProject) ?: []
    ), 'name', 'asc');
  }


  /**
   * @return array
   */
  public function getUsers(): array
  {
    return \array_map([$this, 'normalizeUser'], $this->getConnection(true)->getUsers() ?: []);
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
      'created' => Date::format($branch->commit->created_at, 'dbdate'),
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
      'created' => Date::format($event->created_at, 'dbdate'),
      'author' => $this->normalizeUser($event->author),
      'type' => '',
      'title' => '',
      'text' => '',
      'originalEvent' => $event
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
      'created' => Date::format($member->created_at, 'dbdate'),
      'author' => !empty($member->created_by) ? $this->normalizeUser($member->created_by) : [],
      'expire' => !empty($member->expires_at) ? Date::format($member->expires_at, 'dbdate') : '',
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
    if (is_array($project->namespace)) {
      $project->namespace = (object)$project->namespace;
    }

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
      'created' => Date::format($project->created_at, 'dbdate'),
      'creator' => $project->creator_id,
      'private' => !empty($project->owner),
      'visibility' => $project->visibility,
      'defaultBranch' => $project->default_branch,
      'archived' => $project->archived,
      'avatar' => $project->avatar_url,
      'license' => empty($project->license) ? null : (object)[
        'name' => $project->license->name,
        'code' => $project->license->nickname
      ],
      'noCommits' => isset($project->statistics) ? $project->statistics['commit_count'] : null,
      'size' => isset($project->statistics) ? $project->statistics['repository_size'] : null,
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
      'author' => $this->normalizeUser((object)$issue->author),
      'created' => Date::format($issue->created_at, 'dbdate'),
      'updated' => !empty($issue->updated_at) ?
        Date::format($issue->updated_at, 'dbdate') :
        Date::format($issue->created_at, 'dbdate'),
      'closed' => !empty($issue->closed_at) ? Date::format($issue->closed_at, 'dbdate') : '',
      'closedBy' => !empty($issue->closed_by) ? $this->normalizeUser((object)$issue->closed_by) : [],
      'assigned' => !empty($issue->assignees) ? $this->normalizeUser((object)$issue->assignees[0]) : [],
      'private' => $issue->confidential,
      'labels' => $issue->labels,
      'state' => $issue->state,
      'notes' => $issue->user_notes_count,
      'tasks' => [
        'count' => $issue->task_completion_status->count ?: 0,
        'completed' => $issue->task_completion_status->completed_count ?: 0,
      ],
      'originalIssue' => $issue
    ];
  }


  /**
   * @param string $idProject
   * @param string $branch
   * @param string $fromBranch
   * @return array
   */
  public function insertBranch(string $idProject, string $branch, string $fromBranch): array
  {
    return $this->getConnection()->insertBranch($idProject, $branch, $fromBranch);
  }


  /**
   * @param string $idProject
   * @param string $branch
   * @return bool
   */
  public function deleteBranch(string $idProject, string $branch): bool
  {
    return $this->getConnection()->deleteBranch($idProject, $branch);
  }


  /**
   * @param string $idProject
   * @param int $idUser
   * @param int $idRols
   * @return array
   */
  public function insertProjectUser(string $idProject, int $idUser, int $idRole): array
  {
    return $this->getConnection()->insertProjectUser($idProject, $idUser, $idRole);
  }


  /**
   * @param string $idProject
   * @param int $idUser
   * @return bool
   */
  public function removeProjectUser(string $idProject, int $idUser): bool
  {
    return $this->getConnection()->removeProjectUser($idProject, $idUser);
  }


  /**
   * @param string $idProject
   * @return array
   */
  public function getProjectIssues(string $idProject): array
  {
    return \array_map(
      [$this, 'normalizeIssue'],
      $this->getConnection()->getIssues($idProject)
    );
  }


  /**
   * @param string $idProject
   * @return array
   */
  public function getProjectIssue(string $idProject, int $idIssue): array
  {
    return $this->normalizeIssue((object)$this->getConnection(true)->getIssue($idIssue));
  }


  /**
   * @param string $idProject
   * @param string $title
   * @param string $description
   * @param array $labels
   * @param int $assigned
   * @param bool $private
   * @param string $date
   * @return array|null
   */
  public function createProjectIssue(
    string $idProject,
    string $title,
    string $description = '',
    array $labels = [],
    ?int $assigned = null,
    bool $private = false,
    string $date = ''
  ): ?array
  {
    if ($issue = $this->getConnection()->createIssue($idProject, $title, $description, $labels, $assigned, $private, $date)) {
      return $this->normalizeIssue((object)$issue);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @param string $title
   * @param string $description
   * @param array $labels
   * @param int $assigned
   * @param bool $private
   * @return array|null
   */
  public function editProjectIssue(
    string $idProject,
    int $idIssue,
    string $title,
    string $description = '',
    array $labels = [],
    ?int $assigned = null,
    bool $private = false
  ): ?array
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
      && ($issue = $this->getConnection()->editIssue($idProject, $i['iid'], $title, $description, $labels, $assigned, $private))
    ) {
      return $this->normalizeIssue((object)$issue);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @return null|array
   */
  public function closeProjectIssue(string $idProject, int $idIssue): ?array
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
      && ($issue = $this->getConnection()->closeIssue($idProject, $i['iid']))
    ) {
      return $this->normalizeIssue((object)$issue);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @return null|array
   */
  public function reopenProjectIssue(string $idProject, int $idIssue): ?array
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
      && ($issue = $this->getConnection()->reopenIssue($idProject, $i['iid']))
    ) {
      return $this->normalizeIssue((object)$issue);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @param int $idUser
   * @return null|array
   */
  public function assignProjectIssue(string $idProject, int $idIssue, int $idUser): ?array
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
      && ($issue = $this->getConnection()->assignIssue($idProject, $i['iid'], $idUser))
    ) {
      return $this->normalizeIssue((object)$issue);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @return array
   */
  public function getProjectIssueComment(string $idProject, int $idIssue, int $idComment): array
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
      && ($comment = $this->getConnection()->getIssueNote($idProject, $i['iid'], $idComment))
    ){
      return $this->normalizeIssueComment((object)$comment);
    }
    return [];
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @return array
   */
  public function getProjectIssueComments(string $idProject, int $idIssue): array
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
    ){
      return \array_map(
        [$this, 'normalizeIssueComment'],
        $this->getConnection()->getIssueNotes($idProject, $i['iid'])
      );
    }
    return [];
  }


  /**
   * @param object $issue
   * @return array
   */
  public function normalizeIssueComment(object $comment): array
  {
    return [
      'id' => $comment->id,
      'author' => $this->normalizeUser((object)$comment->author),
      'created' => Date::format($comment->created_at, 'dbdate'),
      'updated' => !empty($comment->updated_at) ?
        Date::format($comment->updated_at, 'dbdate') :
        Date::format($comment->created_at, 'dbdate'),
      'content' => $comment->body,
      'auto' => $comment->system,
      'private' => $comment->internal,
      'attachment' => $comment->attachment,
      'originalComment' => $comment
    ];
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @param string $content
   * @param bool $pvt
   * @param string $date
   * @return null|array
   */
  public function insertProjectIssueComment(string $idProject, int $idIssue, string $content, bool $pvt = false, string $date = ''): ?array
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
      && ($comment = $this->getConnection()->createIssueNote($idProject, $i['iid'], $content, $pvt, $date))
    ) {
      return $this->normalizeIssueComment((object)$comment);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @param int $idComment
   * @param string $content
   * @param bool $pvt
   * @return null|array
   */
  public function editProjectIssueComment(string $idProject, int $idIssue, int $idComment, string $content, bool $pvt = false): ?array
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
      && ($comment = $this->getConnection()->editIssueNote($idProject, $i['iid'], $idComment, $content, $pvt))
    ) {
      return $this->normalizeIssueComment((object)$comment);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @param int $idComment
   * @return bool
   */
  public function deleteProjectIssueComment(string $idProject, int $idIssue, int $idComment): bool
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
    ){
      return $this->getConnection()->deleteIssueNote($idProject, $i['iid'], $idComment);
    }
    return false;
  }


  /**
   * @param string $idProject
   * @param string $name
   * @param string $color
   * @return null|array
  */
  public function createProjectLabel(string $idProject, string $name, string $color): ?array
  {
    if ($label = $this->getConnection()->createProjectLabel($idProject, $name, $color)) {
      return $this->normalizeLabel((object)$label);
    }
    return null;
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @param string $label
   * @return bool
   */
  public function addLabelToProjectIssue(string $idProject, int $idIssue, string $label): bool
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
    ){
      return $this->getConnection()->addLabelToProjectIssue($idProject, $i['iid'], $label);
    }
    return false;
  }


  /**
   * @param string $idProject
   * @param int $idIssue
   * @param string $label
   * @return bool
   */
  public function removeLabelFromProjectIssue(string $idProject, int $idIssue, string $label): bool
  {
    if (($i = $this->getConnection(true)->getIssue($idIssue))
      && !empty($i['iid'])
    ){
      return $this->getConnection()->removeLabelFromProjectIssue($idProject, $i['iid'], $label);
    }
    return false;
  }


  public function analyzeWebhook(array $data): array
  {
    $d = [
      'idProject' => $data['project_id'] ?? null,
      'idUser' => $data['user_id'] ?? (!empty($data['user']) ? $data['user']['id'] : null),
    ];
    switch ($data['event_type']) {
      case 'note':
        if (empty($data['object_attributes']['system'])) {
          $d = X::mergeArrays($d, [
            'type' => 'comment',
            'action' => 'insert',
            'idIssue' => $data['issue']['id'],
            'idComment' => $data['object_attributes']['id'],
            'text' => $data['object_attributes']['note'],
            'created' => Date::format($data['object_attributes']['created_at'], 'dbdate'),
            'updated' => !empty($data['object_attributes']['updated_at']) ?
              Date::format($data['object_attributes']['updated_at'], 'dbdate') :
              Date::format($data['object_attributes']['created_at'], 'dbdate')
          ]);
        }
        break;
    }
    return $d;
  }

}