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
use bbn\Db;


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

  /** @var string|null The server ID */
  private $idServer = null;

  /** @var object|null The server class instance */
  private $server = null;


  /**
   * Constructor.
   * @param bbn\Db $db
   */
  public function __construct(Db $db, string $idServer = '')
  {
    $this->db = $db;
    $this->opt = Option::getInstance();
    $this->pwd = new Passwords($this->db);
    $this->cacheInit();
    self::optionalInit();
    if (!empty($idServer)) {
      $this->changeServer($idServer);
    }

    $this->cacheNamePrefix = '';
  }


  public function changeServer(string $id): bbn\Appui\Vcs
  {
    if ($server = $this->getServer($id)) {
      $this->idServer = $id;
      switch ($server->type) {
        case 'git':
          $this->server = new GitLab($this->db, $id);
          break;
        case 'svn':
          $this->server = new Svn($this->db, $id);
          break;
        default:
          $this->server = null;
          break;
      }
    }
    else {
      $this->idServer = null;
      $this->server = null;
    }
    return $this;
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
    $this->changeServer($id);
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
    if (empty($this->server)) {
      throw new \Exception(X::_('Unable to connect with the following access token: ID: %s , Token: %s', $id, $token));
    }
    if (!($userInfo = $this->server->getCurrentUser())) {
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


  public function getProjectsList(int $page = 1, int $perPage = 25): array
  {
    return $this->server->getProjectsList($page, $perPage);
  }


  public function getProject(string $idProject): ?array
  {
    return $this->server->getProject($idProject);
  }


  public function getProjectBranches(string $idProject): array
  {
    return $this->server->getProjectBranches($idProject);
  }


  public function getProjectTags(string $idProject): array
  {
    return $this->server->getProjectTags($idProject);
  }


  public function getProjectUsers(string $idProject): array
  {
    return $this->server->getProjectUsers($idProject);
  }


  public function getProjectUsersRoles(): array
  {
    return $this->server->getProjectUsersRoles();
  }


  public function getProjectUsersEvents(string $idProject): array
  {
    return $this->server->getProjectUsersEvents($idProject);
  }


  public function getProjectEvents(string $idProject): array
  {
    return $this->server->getProjectEvents($idProject);
  }


  public function getProjectCommitsEvents(string $idProject): array
  {
    return $this->server->getProjectCommitsEvents($idProject);
  }


  public function getProjectLabels(string $idProject): array
  {
    return $this->server->getProjectLabels($idProject);
  }


  public function getUsers(): array
  {
    if ($users = $this->server->getUsers()) {
      if ($appuiUsers = $this->getAppuiUsers($this->idServer)) {
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


  public function insertBranch(string $idProject, string $branch, string $fromBranch): array
  {
    return $this->server->insertBranch($idProject, $branch, $fromBranch);
  }


  public function deleteBranch(string $idProject, string $branch): bool
  {
    return $this->server->deleteBranch($idProject, $branch);
  }


  public function insertProjectUser(string $idProject, int $idUser, string $idRole): array
  {
    return $this->server->insertProjectUser($idProject, $idUser, $idRole);
  }


  public function removeProjectUser(string $idProject, int $idUser): bool
  {
    return $this->server->removeProjectUser($idProject, $idUser);
  }


  public function createProjectIssue(
    string $idProject,
    string $title,
    string $description = '',
    array $labels = [],
    int $assigned = null,
    bool $private = false,
    string $date = ''
  ): ?array
  {
    return $this->server->createProjectIssue($idProject, $title, $description, $labels, $assigned, $private, $date);
  }


  public function editProjectIssue(
    string $idProject,
    int $idIssue,
    string $title,
    string $description = '',
    array $labels = [],
    int $assigned = null,
    bool $private = false
  ): ?array
  {
    return $this->server->editProjectIssue($idProject, $idIssue, $title, $description, $labels, $assigned, $private);
  }

  public function getProjectIssues(string $idProject): array
  {
    if ($issues = $this->server->getProjectIssues($idProject)) {
      $t = $this;
      $issues = \array_map(function($i) use ($t, $idProject){
        $i['idAppuiTask'] = $t->getAppuiTaskId($this->idServer, $idProject, $i['id']);
        return $i;
      }, $issues);
      return $issues;
    }
    return [];
  }


  public function getProjectIssue(string $idProject, int $idIssue): ?array
  {
    if ($issue = $this->server->getProjectIssue($idProject, $idIssue)) {
      $issue['idAppuiTask'] = $this->getAppuiTaskId($this->idServer, $idProject, $issue['id']);
      return $issue;
    }
    return null;
  }


  public function closeProjectIssue(string $idProject, int $idIssue): ?array
  {
    return $this->server->closeProjectIssue($idProject, $idIssue);
  }


  public function reopenProjectIssue(string $idProject, int $idIssue): ?array
  {
    return $this->server->reopenProjectIssue($idProject, $idIssue);
  }


  public function assignProjectIssue(string $idProject, int $idIssue, int $idUser): ?array
  {
    return $this->server->assignProjectIssue($idProject, $idIssue, $idUser);
  }


  public function getProjectIssueComments(string $idProject, int $idIssue): array
  {
    return $this->server->getProjectIssueComments($idProject, $idIssue);
  }


  public function insertProjectIssueComment(string $idProject, int $idIssue, string $content, bool $pvt = false, string $date = ''): ?array
  {
    return $this->server->insertProjectIssueComment($idProject, $idIssue, $content, $pvt, $date);
  }


  public function editProjectIssueComment(string $idProject, int $idIssue, int $idComment, string $content, bool $pvt = false): ?array
  {
    return $this->server->editProjectIssueComment($idProject, $idIssue, $idComment, $content, $pvt);
  }


  public function deleteProjectIssueComment(string $idProject, int $idIssue, int $idComment): bool
  {
    return $this->server->deleteProjectIssueComment($idProject, $idIssue, $idComment);
  }


  public function createProjectLabel(string $idProject, string $name, string $color): ?array
  {
    return $this->server->createProjectLabel($idProject, $name, $color);
  }


  public function addLabelToProjectIssue(string $idProject, int $idIssue, string $label): bool
  {
    return $this->server->addLabelToProjectIssue($idProject, $idIssue, $label);
  }


  public function removeLabelFromProjectIssue(string $idProject, int $idIssue, string $label): bool
  {
    return $this->server->removeLabelFromProjectIssue($idProject, $idIssue, $label);
  }


  public function analyzeWebhook(array $data)
  {
    if ($d = $this->server->analyzeWebhook($data)) {
      if (!empty($d['type'])) {
        switch ($d['type']) {
          case 'comment':
            if (!empty($d['idProject'])
              && !empty($d['idIssue'])
              && ($task = $this->getAppuiTask($this->idServer, $d['idProject'], $d['idIssue']))
            ) {

            }
            break;
        }
      }
    }
  }


  public function importIssueToTask(string $idProject, int $idIssue): ?string
  {
    if (!($idTask = $this->getAppuiTaskId($this->idServer, $idProject, $idIssue))) {
      if ($issue = $this->getProjectIssue($idProject, $idIssue)) {
        $task = new Task($this->db);
        $notes = new Note($this->db);
        $notesCfg = $notes->getClassCfg();
        $notesFields = $notesCfg['arch']['notes'];
        $notesVersionsFields = $notesCfg['arch']['versions'];
        $idCatSupportTask = $this->opt->fromCode('support', 'cats', 'task', 'appui');
        $appuiUsers = $this->getAppuiUsers($this->idServer);
        // Use the external user's ID
        $idUser = BBN_EXTERNAL_USER_ID;
        // Check if the git user is an appui user
        if ($appuiUser = X::getRow($appuiUsers, ['idVcs' => $issue['author']['id']])) {
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
              'id_server' => $this->idServer,
              'id_project' => $idProject,
              'id_issue' => $idIssue,
              'id_task' => $idTask
            ])
          ) {
            $idParent = $this->db->lastId();
            // Comments
            if (!empty($issue['notes'])
              && ($issueNotes = $this->getProjectIssueComments($idProject, $idIssue))
            ) {
              foreach ($issueNotes as $note) {
                // Check if the note already exists and if it's a real note
                if (empty($note['auto']) && !$this->getAppuiTaskNote($this->idServer, $idProject, $idIssue, $note['id'])) {
                  // Use the external user's ID
                  $idUser = BBN_EXTERNAL_USER_ID;
                  // Check if the git user is an appui user
                  if ($appuiUser = X::getRow($appuiUsers, ['idVcs' => $note['author']['id']])) {
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
                        'id_server' => $this->idServer,
                        'id_project' => $idProject,
                        'id_comment' => $idNote,
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


  /**
   * Gets the data path of the appui-vcs plugin
   * @return string
   */
  public static function getMainDataPath(): string
  {
    return bbn\Mvc::getDataPath('appui-vcs');
  }

  /**
   * Returns an instance of bbn\Db of the tasks queue database
   * @return bbn\Db|null
   */
  public static function getDb(): ?Db
  {
    if ($dbPath = self::makeDb()) {
      return new Db([
        'engine' => 'sqlite',
        'db' => $dbPath
      ]);
    }
    return null;
  }


  private function getAppuiTaskId(string $idServer, string $idProject, int $idIssue): ?string
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


  private function getAppuiTask(string $idServer, string $idProject, int $idIssue): ?array
  {
    if ($this->db->tableExists(self::BBN_TASK_TABLE)) {
      return $this->db->rselect(self::BBN_TASK_TABLE, [], [
        'id_server' => $idServer,
        'id_project' => $idProject,
        'id_issue' => $idIssue,
        'id_comment' => null,
        'id_parent' => null
      ]);
    }
    return null;
  }


  private function getAppuiTaskNote(string $idServer, string $idProject, int $idIssue, int $idComment): ?array
  {
    if ($this->db->tableExists(self::BBN_TASK_TABLE)) {
      return $this->db->rselect([
        'table' => self::BBN_TASK_TABLE,
        'fields' => [],
        'join' => [[
          'table' => self::BBN_TASK_TABLE,
          'alias' => 'parent',
          'on' => [
            'conditions' => [[
              'field' => 'parent.id',
              'exp' => 'id_parent'
            ], [
              'field' => 'id_server',
              'value' => $idServer
            ], [
              'field' => 'id_project',
              'value' => $idProject
            ], [
              'field' => 'id_issue',
              'value' => $idIssue
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
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


  private function getAppuiTaskNotes(string $idServer, string $idProject, int $idIssue): ?array
  {
    if ($this->db->tableExists(self::BBN_TASK_TABLE)) {
      $this->db->getColumnValues([
        'table' => self::BBN_TASK_TABLE,
        'fields' => ['id_note'],
        'join' => [[
          'table' => self::BBN_TASK_TABLE,
          'alias' => 'parent',
          'on' => [
            'conditions' => [[
              'field' => 'parent.id',
              'exp' => 'id_parent'
            ], [
              'field' => 'id_server',
              'value' => $idServer
            ], [
              'field' => 'id_project',
              'value' => $idProject
            ], [
              'field' => 'id_issue',
              'value' => $idIssue
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => 'id_server',
            'value' => $idServer
          ], [
            'field' => 'id_project',
            'value' => $idProject
          ]]
        ]
      ]);
    }
    return null;
  }


  /**
   * Makes the SQLite database and returns its path
   * @return null|string
   */
  private static function makeDb(): ?string
  {
    if ($mainDataPath = self::getMainDataPath()) {
      $path = $mainDataPath . 'queue.sqlite';
      if (!\is_file($path)
        && bbn\File\Dir::createPath($mainDataPath)
      ) {
        $db = new \SQLite3($path);
        $db->exec("CREATE TABLE queue (
          id INTEGER PRIMARY KEY,
          server VARCHAR (150) NOT NULL,
          created DATETIME NOT NULL DEFAULT (CURRENT_TIMESTAMP),
          user VARCHAR (32),
          method VARCHAR (100) NOT NULL,
          args TEXT,
          hash TEXT NOT NULL,
          start DATETIME,
          [end] DATETIME,
          failed INTEGER (1) DEFAULT (0),
          error TEXT,
          active INTEGER (1) DEFAULT (1)
        );");
      }
      return \is_file($path) ? $path : null;
    }
    return null;
  }


}