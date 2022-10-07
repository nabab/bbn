<?php

namespace bbn\Appui;

use bbn;
use bbn\Cache;
use bbn\Appui\Passwords;
use bbn\X;
use bbn\Appui\Option;
use bbn\Appui\Task;
use bbn\Appui\Note;
use bbn\User;
use bbn\Appui\Vcs\GitLab;
use bbn\Appui\Vcs\Svn;


/**
 * VCS class
 * @category Appui
 * @package Appui
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Appui/Vcs
 */
class Vcs
{
  use bbn\Models\Tts\Cache;
  use bbn\Models\Tts\Optional;
  use Vcs\Common;

  const CACHE_NAME = 'bbn/Appui/Vcs';

  const BBN_TASK_TABLE = 'bbn_tasks_vcs';

  /** @var string The cache name prefix */
  private $cacheNamePrefix;

  /** @var bbn\Appui\Vcs\Git The bbn\Appui\Vcs\Git class instance */
  private $git;

  /** @var bbn\Appui\Vcs\Svn The bbn\Appui\Vcs\Svn class instance */
  private $svn;


  /**
   * Constructor.
   * @param bbn\Db $db
   */
  public function __construct($db)
  {
    $this->db = $db;
    $this->opt = Option::getInstance();
    $this->pwd = new Passwords($this->db);
    $this->git = new GitLab($this->db);
    $this->svn = new Svn($this->db);
    $this->cacheInit();
    self::optionalInit();

    $this->cacheNamePrefix = '';
  }


  public function setAdminAccessToken(string $id, string $token): bool
  {
    if (!$this->pwd->store($token, $id)) {
      throw new \Exception(X::_('Error while storing the admin access token: ID: %s , Token: %s', $id, $token));
    }
    return true;
  }


  public function setUserAccessToken(string $id, string $token): bool
  {
    if (!($user = \bbn\User::getInstance())) {
      throw new \Exception(X::_('No User class instance found'));
    }
    if (!($pref = \bbn\User\Preferences::getInstance())) {
      throw new \Exception(X::_('No User\Preferences class instance found'));
    }
    if ($exPref = $pref->getByOption($id)) {
      $this->pwd->userDelete($exPref['id'], $user);
      $pref->delete($exPref['id']);
    }
    if (!($idPref = $pref->add($id, []))) {
      throw new \Exception(X::_('Error while adding the user preference: idUser %s - idOption %s', $user->getId(), $id));
    }
    if (!$this->pwd->userStore($token, $idPref, $user)) {
      throw new \Exception(X::_('Error while storing the user access token: ID: %s , Token: %s', $id, $token));
    }
    if (!($serverCls = $this->getServerInstance($id))) {
      throw new \Exception(X::_('Unable to connect with the following access token: ID: %s , Token: %s', $id, $token));
    }
    if (!($userInfo = $serverCls->getCurrentUser($id))) {
      throw new \Exception(X::_('Unable to find user information: ID: %s , Token: %s', $id, $token));
    }
    $pref->set($idPref, ['user' => $userInfo]);
    return true;
  }


  public function addServer(string $name, string $host, string $type, string $adminAccessToken, string $userAccessToken = ''): string
  {
    if (!($idParent = $this->getOptionId('list'))) {
      throw new \Exception(X::_('"list" option not found'));
    }
    $reg = '/(http[s]?:\/\/)?(?\'code\'[[:alpha:]\.]+(?!\/$)?)/m';
    preg_match_all($reg, $host, $matches);
    if (!empty($matches['code'])) {
      $host = $matches['code'][0];
    }
    $this->checkServerHost($host);
    $optFields = $this->opt->getFields();
    $o = [
      $optFields['id_parent'] => $idParent,
      $optFields['text'] => $name,
      $optFields['code'] => $host,
      'type' => $type
    ];
    if (!($idOpt = $this->opt->add($o))) {
      throw new \Exception(X::_('Error while inserting the option: %s', \json_encode($o)));
    }
    $this->setAdminAccessToken($idOpt, $adminAccessToken);
    if (!empty($userAccessToken)) {
      $this->setUserAccessToken($idOpt, $userAccessToken);
    }
    return $idOpt;
  }


  public function editServer(string $id, string $name, string $host, string $type): bool{
    $reg = '/(http[s]?:\/\/)?(?\'code\'[[:alpha:]\.]+(?!\/$)?)/m';
    preg_match_all($reg, $host, $matches);
    if (!empty($matches['code'])) {
      $host = $matches['code'][0];
    }
    $this->checkServerHost($host);
    $optFields = $this->opt->getFields();
    $o = [
      $optFields['text'] => $name,
      $optFields['code'] => $host,
      'type' => $type
    ];
    if (!$this->opt->set($id, $o)) {
      throw new \Exception(X::_('Error while updating the option with ID %s: %s', $id, \json_encode($o)));
    }
    return true;
  }


  public function getServersList(): array
  {
    $t = $this;
    return \array_map(function($o) use($t){
      return $t->normalizeServer($o);
    }, $this->opt->fullOptions($this->getOptionId('list')));
  }


  public function getProjectsList(string $id, int $page = 1, int $perPage = 25): array
  {
    $list = [];
    if ($serverCls = $this->getServerInstance($id)) {
      $list = $serverCls->getProjectsList($id, $page, $perPage);
    }
    return $list;
  }


  public function getProject(string $idServer, string $idProject): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProject($idServer, $idProject);
    }
    return null;
  }


  public function getProjectBranches(string $idServer, string $idProject): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectBranches($idServer, $idProject);
    }
    return [];
  }


  public function getProjectTags(string $idServer, string $idProject): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectTags($idServer, $idProject);
    }
    return [];
  }


  public function getProjectUsers(string $idServer, string $idProject): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectUsers($idServer, $idProject);
    }
    return [];
  }


  public function getProjectUsersRoles(string $idServer): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectUsersRoles($idServer);
    }
    return [];
  }


  public function getProjectUsersEvents(string $idServer, string $idProject): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectUsersEvents($idServer, $idProject);
    }
    return [];
  }


  public function getProjectEvents(string $idServer, string $idProject): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectEvents($idServer, $idProject);
    }
    return [];
  }


  public function getProjectCommitsEvents(string $idServer, string $idProject): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectCommitsEvents($idServer, $idProject);
    }
    return [];
  }


  public function getProjectLabels(string $idServer, string $idProject): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectLabels($idServer, $idProject);
    }
    return [];
  }


  public function getUsers(string $idServer): array
  {
    if (($serverCls = $this->getServerInstance($idServer))
      && $users = $serverCls->getUsers($idServer)
    ) {
      if ($appuiUsers = $this->getAppuiUsers($idServer)) {
        $users = \array_map(function($u) use($appuiUsers){
          $appui = X::getRow($appuiUsers, ['idVcs' => $u['id']]) ?: [];
          $u['idAppui'] = $appui['id'] ?? null;
          $u['originalInfo'] = $appui['info'] ?? null;
          return $u;
        }, $users);
      }
      return $users;
    }
    return [];
  }


  public function getAppuiUsers(string $idServer): array
  {
    if ($prefs = $this->db->rselectAll([
      'table' => 'bbn_users_options',
      'fields' => [
        'id' => 'id_user',
        'idVcs' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.user.id"))',
        'info' => 'JSON_EXTRACT(cfg, "$.user")'
      ],
      'where' => [
        'conditions' => [[
          'field' => 'id_option',
          'value' => $idServer
        ], [
          'field' => 'JSON_EXTRACT(cfg, "$.user")',
          'operator' => 'isnotnull'
        ]]
      ]
    ])) {
      return \array_map(function($p){
        $p['info'] = \json_decode($p['info'], true);
        return $p;
      }, $prefs);
    }
    return [];
  }


  public function insertBranch(string $idServer, string $idProject, string $branch, string $fromBranch): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->insertBranch($idServer, $idProject, $branch, $fromBranch);
    }
    return null;
  }


  public function deleteBranch(string $idServer, string $idProject, string $branch): bool
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->deleteBranch($idServer, $idProject, $branch);
    }
    return false;
  }


  public function insertProjectUser(string $idServer, string $idProject, int $idUser, string $idRole): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->insertProjectUser($idServer, $idProject, $idUser, $idRole);
    }
    return null;
  }


  public function removeProjectUser(string $idServer, string $idProject, int $idUser): bool
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->removeProjectUser($idServer, $idProject, $idUser);
    }
    return false;
  }


  public function createProjectIssue(
    string $idServer,
    string $idProject,
    string $title,
    string $description = '',
    array $labels = [],
    int $assigned = null,
    bool $private = false,
    string $date = ''
  ): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->createProjectIssue($idServer, $idProject, $title, $description, $labels, $assigned, $private, $date);
    }
    return [];
  }


  public function editProjectIssue(
    string $idServer,
    string $idProject,
    int $idIssue,
    string $title,
    string $description = '',
    array $labels = [],
    int $assigned = null,
    bool $private = false
  ): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->editProjectIssue($idServer, $idProject, $idIssue, $title, $description, $labels, $assigned, $private);
    }
    return [];
  }

  public function getProjectIssues(string $idServer, string $idProject): array
  {
    if (($serverCls = $this->getServerInstance($idServer))
      && ($issues = $serverCls->getProjectIssues($idServer, $idProject))
    ) {
      $t = $this;
      $issues = \array_map(function($i) use ($t, $idServer, $idProject){
        $i['idAppuiTask'] = $t->getAppuiTask($idServer, $idProject, $i['id']);
        return $i;
      }, $issues);
      return $issues;
    }
    return [];
  }


  public function getProjectIssue(string $idServer, string $idProject, int $idIssue): ?array
  {
    if (($serverCls = $this->getServerInstance($idServer))
      && ($issue = $serverCls->getProjectIssue($idServer, $idProject, $idIssue))
    ) {
      $t = $this;
      $issue['idAppuiTask'] = $this->getAppuiTask($idServer, $idProject, $issue['id']);
      return $issue;
    }
    return null;
  }


  public function closeProjectIssue(string $idServer, string $idProject, int $idIssue): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->closeProjectIssue($idServer, $idProject, $idIssue);
    }
    return null;
  }


  public function reopenProjectIssue(string $idServer, string $idProject, int $idIssue): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->reopenProjectIssue($idServer, $idProject, $idIssue);
    }
    return null;
  }


  public function assignProjectIssue(string $idServer, string $idProject, int $idIssue, int $idUser): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->assignProjectIssue($idServer, $idProject, $idIssue, $idUser);
    }
    return null;
  }


  public function getProjectIssueComments(string $idServer, string $idProject, int $idIssue): array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->getProjectIssueComments($idServer, $idProject, $idIssue);
    }
    return [];
  }


  public function insertProjectIssueComment(string $idServer, string $idProject, int $idIssue, string $content, bool $pvt = false, string $date = ''): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->insertProjectIssueComment($idServer, $idProject, $idIssue, $content, $pvt, $date);
    }
    return null;
  }


  public function editProjectIssueComment(string $idServer, string $idProject, int $idIssue, int $idComment, string $content, bool $pvt = false): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->editProjectIssueComment($idServer, $idProject, $idIssue, $idComment, $content, $pvt);
    }
    return null;
  }


  public function createProjectLabel(string $idServer, string $idProject, string $name, string $color): ?array
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->createProjectLabel($idServer, $idProject, $name, $color);
    }
    return null;
  }


  public function deleteProjectIssueComment(string $idServer, string $idProject, int $idIssue, int $idComment): bool
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->deleteProjectIssueComment($idServer, $idProject, $idIssue, $idComment);
    }
    return false;
  }


  public function addLabelToProjectIssue(string $idServer, string $idProject, int $idIssue, string $label): bool
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->addLabelToProjectIssue($idServer, $idProject, $idIssue, $label);
    }
    return false;
  }


  public function removeLabelFromProjectIssue(string $idServer, string $idProject, int $idIssue, string $label): bool
  {
    if ($serverCls = $this->getServerInstance($idServer)) {
      return $serverCls->removeLabelFromProjectIssue($idServer, $idProject, $idIssue, $label);
    }
    return false;
  }


  public function importIssueToTask(string $idServer, string $idProject, int $idIssue): ?string
  {
    if (!($idTask = $this->getAppuiTask($idServer, $idProject, $idIssue))) {
      if ($issue = $this->getProjectIssue($idServer, $idProject, $idIssue)) {
        $task = new Task($this->db);
        $notes = new Note($this->db);
        $notesCfg = $notes->getClassCfg();
        $notesFields = $notesCfg['arch']['notes'];
        $notesVersionsFields = $notesCfg['arch']['versions'];
        $idCatSupportTask = $this->opt->fromCode('support', 'cats', 'task', 'appui');
        // Use the external user's ID
        $idUser = BBN_EXTERNAL_USER_ID;
        // Check if the git user is an appui user
        if ($appuiUser = X::getRow($this->getAppuiUsers($idServer), ['idVcs' => $issue['author']['id']])) {
          $idUser = $appuiUser['id'];
        }
        if (!empty($idUser)) {
          // Set the task's user
          $task->setUser($idUser);
          // Set the task's date
          $task->setDate(date('Y-m-d H:i:s', strtotime($issue['created'])));
          // Create the task
          if (($idTask = $task->insert([
              'title' => $issue['title'],
              'type' => $idCatSupportTask,
              'state' => $this->opt->fromCode($issue['state'], 'states', 'task', 'appui'),
              'cfg' => \json_encode(['widgets' => ['notes' => 1]])
            ]))
            && $this->db->insert(self::BBN_TASK_TABLE, [
              'id_server' => $idServer,
              'id_project' => $idProject,
              'id_issue' => $idIssue,
              'id_task' => $idTask
            ])
          ) {
            $idParent = $this->db->lastId();
            // Comments
            if (!empty($issue['notes'])
              && ($issueNotes = $this->getProjectIssueComments($idServer, $idProject, $idIssue))
            ) {
              foreach ($issueNotes as $note) {
                // Check if the note already exists
                if (!$this->getAppuiTaskNote($idServer, $idProject, $note['id'])) {
                  // Use the external user's ID
                  $idUser = BBN_EXTERNAL_USER_ID;
                  // Check if the git user is an appui user
                  if ($appuiUser = X::getRow($this->getAppuiUsers($idServer), ['idVcs' => $note['author']['id']])) {
                    $idUser = $appuiUser['id'];
                  }
                  if (!empty($idUser)) {
                    // Set the task's user
                    $task->setUser($idUser);
                    // Set the task's date
                    $task->setDate(date('Y-m-d H:i:s', strtotime($note['updated'])));
                    // Add the note to the task
                    if (($idNote = $task->comment($idTask, [
                        'title' => '',
                        'text' => $note['content']
                      ]))
                      && $this->db->insert(self::BBN_TASK_TABLE, [
                        'id_parent' => $idParent,
                        'id_task' => $idTask,
                        'id_server' => $idServer,
                        'id_project' => $idProject,
                        'id_comment' => $note['id'],
                      ])
                    ) {
                      $this->db->update($notesCfg['table'], [
                        $notesFields['creator'] => $idUser
                      ], [
                        $notesFields['id'] => $idNote
                      ]);
                      $this->db->update($notesCfg['tables']['versions'], [
                        $notesVersionsFields['id_user'] => $idUser
                      ], [
                        $notesVersionsFields['id_note'] => $idNote
                      ]);
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    return $idTask;
  }


  private function getAppuiTask(string $idServer, string $idProject, int $idIssue): ?string
  {
    if ($this->db->tableExists(self::BBN_TASK_TABLE)) {
      return $this->db->selectOne(self::BBN_TASK_TABLE, 'id_task', [
        'id_server' => $idServer,
        'id_project' => $idProject,
        'id_issue' => $idIssue,
        'id_comment' => null,
        'id_parent' => null
      ]) ?: null;
    }
    return null;
  }

  private function getAppuiTaskNote(string $idServer, string $idProject, int $idComment): ?array
  {
    if ($this->db->tableExists(self::BBN_TASK_TABLE)) {
      $this->db->getColumnValues([
        'table' => self::BBN_TASK_TABLE,
        'fields' => ['id_note'],
        'where' => [
          'conditions' => [[
            'field' => 'id_parent',
            'operator' => 'isnotnull'
          ], [
            'field' => 'id_server',
            'value' => $idServer
          ], [
            'field' => 'id_project',
            'value' => $idProject
          ], [
            'field' => 'id_comment',
            'value' => $idComment
          ]]
        ]
      ]);
    }
    return null;
  }


  private function getServerInstance(string $id)
  {
    if ($server = $this->getServer($id)) {
      switch ($server->type) {
        case 'git':
          return $this->git;
        case 'svn':
          return $this->svn;
      }
    }
  }


}