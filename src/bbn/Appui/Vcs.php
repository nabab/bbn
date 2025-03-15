<?php

namespace bbn\Appui;

use bbn;
use bbn\Cache;
use bbn\Appui\Passwords;
use bbn\X;
use bbn\Str;
use bbn\Appui\Option;
use bbn\Appui\Task;
use bbn\Appui\Note;
use bbn\User;
use bbn\User\Preferences;
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

  public static $engines = [
    'git' => [
      'gitlab' => [
        'name' => 'GitLab',
        'class' => '\bbn\Appui\Vcs\GitLab'
      ]
    ],
    'svn' => []
  ];

  /** @var string The cache name prefix */
  private $cacheNamePrefix;

  /** @var string|null The server ID */
  private $idServer = null;

  /** @var object|null The server class instance */
  private $server = null;

  /** @var string The DB table for tasks-vcs links */
  private static $taskTable = 'bbn_tasks_vcs';


  /**
   * Constructor.
   * @param bbn\Db $db
   */
  public function __construct(Db $db, string $idServer = '', string $idUser = '')
  {
    $this->db = $db;
    $this->opt = Option::getInstance();
    $this->pwd = new Passwords($this->db);
    $this->cacheInit();
    self::optionalInit();
    if (!empty($idServer)) {
      $this->changeServer($idServer);
      if (!empty($idUser)) {
        $this->$idUser = $idUser;
      }
    }

    $this->cacheNamePrefix = '';
  }


  public function changeServer(string $id): bbn\Appui\Vcs
  {
    if (($server = $this->getServer($id))
      && !empty(self::$engines[$server->type][$server->engine])
    ) {
      $this->idServer = $id;
      $this->server = new self::$engines[$server->type][$server->engine]['class']($this->db, $id);
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
    if (!($user = User::getInstance())) {
      throw new \Exception(X::_('No User class instance found'));
    }
    if (!($pref = Preferences::getInstance())) {
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

    $this->changeServer($id);
    if (empty($this->server)) {
      throw new \Exception(X::_('Unable to connect with the following access token: ID: %s , Token: %s', $id, $token));
    }
    if (!($userInfo = $this->server->getCurrentUser())) {
      throw new \Exception(X::_('Unable to find user information: ID: %s , Token: %s', $id, $token));
    }
    $pref->set($idPref, ['user' => $userInfo]);
    return true;
  }


  public function addServer(
    string $name,
    string $host,
    string $type,
    string $engine,
    string $adminAccessToken,
    string $userAccessToken = ''
  ): string
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
      'type' => $type,
      'engine' => $engine
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


  public function editServer(string $id, string $name, string $host, string $type, string $engine): bool{
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
      'type' => $type,
      'engine' => $engine
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
        $i['idAppuiTask'] = $t->getAppuiTaskId($idProject, $i['id']);
        return $i;
      }, $issues);
      return $issues;
    }
    return [];
  }


  public function getProjectIssue(string $idProject, int $idIssue): ?array
  {
    if ($issue = $this->server->getProjectIssue($idProject, $idIssue)) {
      $issue['idAppuiTask'] = $this->getAppuiTaskId($idProject, $issue['id']);
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


  public function getProjectIssueComment(string $idProject, int $idIssue, int $idComment): array
  {
    return $this->server->getProjectIssueComment($idProject, $idIssue, $idComment);
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
    $d = $this->server->analyzeWebhook($data);
    if ($d = $this->server->analyzeWebhook($data)) {
      if (!empty($d['type'])) {
        switch ($d['type']) {
          case 'comment':
            if (!empty($d['idProject'])
              && !empty($d['idIssue'])
              && $this->getAppuiTaskByIssue($d['idProject'], $d['idIssue'])
            ) {
              return $this->addToTasksQueue($d['idProject'], 'import', $d);
            }
            break;
        }
      }
    }
  }


  public function addToTasksQueue(int $idProject, string $type, $task, string $idServer = ''): bool
  {
    if (!($idServer = empty($idServer) ? $this->idServer : $idServer)) {
      return false;
    }
    return (bool)self::getDb()->insert('queue', [
      'id_server' => $idServer,
      'id_project' => $idProject,
      'type' => $type,
      'task' => !Str::isJson($task) ? \json_encode($task) : $task
    ]);
  }


  public function processTasksQueue(): array
  {
    $db = self::getDb();
    $res = [
      'todo' => 0,
      'processed' => 0,
      'failed' => 0
    ];
    if ($queue = $db->selectAll([
      'table' => 'queue',
      'fields' => [],
      'where' => [
        'conditions' => [[
          'field' => 'started',
          'operator' => 'isnull'
        ], [
          'field' => 'failed',
          'value' => 0
        ], [
          'field' => 'active',
          'value' => 1
        ]]
      ],
      'order' => [[
        'field' => 'created',
        'dir' => 'asc'
      ]]
    ])) {
      $res['todo'] = count($queue);
      foreach ($queue as $q) {
        $success = false;
        $db->update('queue', ['started' => date('Y-m-d H:i:s')], ['id' => $q->id]);
        try {
          if (!empty($q->id_server)
            && ($t = \json_decode($q->task))
          ) {
            $this->changeServer($q->id_server);
            if (!empty($t->type)) {
              switch ($t->type) {
                case 'comment':
                  $success = $this->processComment($q->id_project, $q->type, $t);
                  break;
              }
            }
          }
        }
        catch(\Exception $e){
          $success = false;
          \bbn\X::adump($e->getMessage());
        }
        $db->update('queue', [
          'ended' => date('Y-m-d H:i:s'),
          'failed' => empty($success) ? 1 : 0
        ], [
          'id' => $q->id
        ]);
        $res['processed']++;
        if (empty($success)) {
          $res['failed']++;
        }
      }
    }
    return $res;
  }


  public function importIssueToTask(string $idProject, int $idIssue): ?string
  {
    if (!($idTask = $this->getAppuiTaskId($idProject, $idIssue))) {
      if ($issue = $this->getProjectIssue($idProject, $idIssue)) {
        $task = new Task($this->db);
        $idCatSupportTask = $this->opt->fromCode('support', 'cats', 'task', 'appui');
        // Use the external user's ID
        $idUser = BBN_EXTERNAL_USER_ID;
        // Check if the vcs user is an appui user
        if ($appuiUser = $this->getAppuiUser($issue['author']['id'])){
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
            && ($idTaskLink = $this->addAppuiTaskLink($idTask, $idProject, $idIssue))
          ) {
            // Comments
            if (!empty($issue['notes'])
              && ($issueNotes = $this->getProjectIssueComments($idProject, $idIssue))
            ) {
              foreach ($issueNotes as $note) {
                // Check if the note already exists and if it's a real note
                if (empty($note['auto']) &&
                  !$this->getAppuiTaskNote($idProject, $idIssue, $note['id'])
                ) {
                  // Use the external user's ID
                  $idUser = BBN_EXTERNAL_USER_ID;
                  // Check if the git user is an appui user
                  if ($appuiUser = $this->getAppuiUser($note['author']['id'])) {
                    $idUser = $appuiUser['id'];
                  }
                  if (!empty($idUser)
                    && ($idNote = $this->addAppuiTaskNote($idProject, $idTask, $idUser, $note['content'], $note['updated']))
                  ) {
                    $this->addAppuiTaskNoteLink($idTaskLink, $idNote, $idProject, $note['id']);
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


  public function addAppuiTaskLink(string $idTask, int $idProject, int $idIssue): ?string
  {
    if (!empty($this->idServer)
      && $this->db->tableExists(self::$taskTable)
      && $this->db->insert(self::$taskTable, [
        'id_task' => $idTask,
        'id_server' => $this->idServer,
        'id_project' => $idProject,
        'id_issue' => $idIssue,
      ])
    ) {
      return $this->db->lastId();
    }
    return null;
  }


  public function addAppuiTaskNoteLink(string $idParent, string $idNote, int $idProject, int $idComment): string
  {
    if (!empty($this->idServer)
      && $this->db->tableExists(self::$taskTable)
      && $this->db->insert(self::$taskTable, [
        'id_parent' => $idParent,
        'id_note' => $idNote,
        'id_server' => $this->idServer,
        'id_project' => $idProject,
        'id_comment' => $idComment,
      ])
    ) {
      return $this->db->lastId();
    }
    return null;
  }


  public function removeAppuiTaskNoteLink(string $idParent, string $idNote): bool
  {
    return $this->db->tableExists(self::$taskTable)
      && (bool)$this->db->delete(self::$taskTable, [
        'id_parent' => $idParent,
        'id_note' => $idNote
      ]);
  }


  public function addAppuiTaskNote(int $idProject, string $idTask, string $idUser, string $content, string $date): ?string
  {
    if ($this->getAppuiTask($idProject, $idTask)) {
      $notes = new Note($this->db);
      $notesCfg = $notes->getClassCfg();
      $notesFields = $notesCfg['arch']['notes'];
      $notesVersionsFields = $notesCfg['arch']['versions'];
      $task = new Task($this->db);
      // Set the task's user
      $task->setUser($idUser);
      // Set the task's date
      $task->setDate(date('Y-m-d H:i:s', strtotime($date)));
      // Add the note to the task
      if ($idNote = $task->comment($idTask, [
        'title' => '',
        'text' => $content
      ])) {
        // Set the correct user ID
        $this->db->update($notesCfg['table'], [
          $notesFields['creator'] => $idUser
        ], [
          $notesFields['id'] => $idNote
        ]);
        $this->db->update($notesCfg['tables']['versions'], [
          $notesVersionsFields['id_user'] => $idUser
        ], [
          $notesVersionsFields['id_note'] => $idNote,
          $notesVersionsFields['latest'] => 1
        ]);
        return $idNote;
      }
    }
    return null;
  }


  public function editAppuiTaskNote(int $idProject, string $idTask, int $idComment, string $idUser, string $content, string $date = ''): bool
  {
    if (($task = $this->getAppuiTask($idProject, $idTask))
      && ($note = $this->getAppuiTaskNote($idProject, $task['id_issue'], $idComment))
      && ($idNote = $note['id_note'])
    ) {
      $notes = new Note($this->db);
      $notesCfg = $notes->getClassCfg();
      $notesVersionsFields = $notesCfg['arch']['versions'];
      if (($n = $notes->get($idNote))
        && $notes->update($idNote, '', $content, $n['private'], $n['locked'])
      ){
        // Set the correct user ID and date
        $this->db->update($notesCfg['tables']['versions'], [
          $notesVersionsFields['id_user'] => $idUser,
          $notesVersionsFields['creation'] => $date
        ], [
          $notesVersionsFields['id_note'] => $idNote,
          $notesVersionsFields['latest'] => 1
        ]);
        return true;
      }
    }
    return false;
  }


  public function removeAppuiTaskNote(int $idProject, string $idTask, int $idComment): bool
  {
    if (($task = $this->getAppuiTask($idProject, $idTask))
      && ($note = $this->getAppuiTaskNote($idProject, $task['id_issue'], $idComment))
    ) {
      return (bool)$this->db->delete(self::$taskTable, ['id' => $note['id']]);
    }
    return false;
  }


  public function getAppuiTaskNoteByNote(string $idNote): ?array
  {
    if ($this->db->tableExists(self::$taskTable)) {
      return $this->db->rselect(self::$taskTable, [], ['id_note' => $idNote]);
    }
    return null;
  }


  public function getAppuiTaskByTask(string $idTask): ?array
  {
    if ($this->db->tableExists(self::$taskTable)) {
      return $this->db->rselect(self::$taskTable, [], ['id_task' => $idTask]);
    }
    return null;
  }


  public function getAppuiTaskById(string $id): ?array
  {
    if ($this->db->tableExists(self::$taskTable)) {
      return $this->db->rselect(self::$taskTable, [], ['id' => $id]);
    }
    return null;
  }


  public function getAppuiUser(int $idVcs, string $idServer = ''): ?array
  {
    $idServer = empty($idServer) ? $this->idServer : $idServer;
    if (!empty($idServer)
      && $pref = $this->db->rselect([
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
          ], [
            'field' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.user.id"))',
            'value' => $idVcs
          ]]
        ]
      ])
    ) {
      $pref['info'] = \json_decode($pref['info'], true);
      return $pref;
    }
    return null;
  }


  public function getAppuiUsers(string $idServer = ''): array
  {
    $idServer = empty($idServer) ? $this->idServer : $idServer;
    if (!empty($idServer)
      && $prefs = $this->db->rselectAll([
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
      ])
    ) {
      return \array_map(function($p){
        $p['info'] = \json_decode($p['info'], true);
        return $p;
      }, $prefs);
    }
    return [];
  }


  public function getUserByAppuiUser(string $idAppuiUser, string $idServer = '')
  {
    $idServer = empty($idServer) ? $this->idServer : $idServer;
    if (!empty($idServer)) {
      return $this->db->selectOne([
        'table' => 'bbn_users_options',
        'fields' => ['JSON_UNQUOTE(JSON_EXTRACT(cfg, "$.user.id"))'],
        'where' => [
          'conditions' => [[
            'field' => 'id_option',
            'value' => $idServer
          ], [
            'field' => 'id_user',
            'value' => $idAppuiUser
          ], [
            'field' => 'JSON_EXTRACT(cfg, "$.user")',
            'operator' => 'isnotnull'
          ]]
        ]
      ]);
    }
    return null;
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


  private function getAppuiTaskId(string $idProject, int $idIssue, string $idServer = ''): ?string
  {
    $idServer = empty($idServer) ? $this->idServer : $idServer;
    if ($this->db->tableExists(self::$taskTable)
      && !empty($idServer)
    ) {
      return $this->db->selectOne(self::$taskTable, 'id_task', [
        'id_server' => $idServer,
        'id_project' => $idProject,
        'id_issue' => $idIssue,
        'id_comment' => null,
        'id_parent' => null,
        'id_note' => null
      ]) ?: null;
    }
    return null;
  }


  private function getAppuiTask(string $idProject, string $idTask, string $idServer = ''): ?array
  {
    $idServer = empty($idServer) ? $this->idServer : $idServer;
    if ($this->db->tableExists(self::$taskTable)
      && !empty($idServer)
    ) {
      return $this->db->rselect(self::$taskTable, [], [
        'id_server' => $idServer,
        'id_project' => $idProject,
        'id_task' => $idTask,
        'id_comment' => null,
        'id_parent' => null,
        'id_note' => null
      ]);
    }
    return null;
  }


  private function getAppuiTaskByIssue(string $idProject, int $idIssue, string $idServer = ''): ?array
  {
    $idServer = empty($idServer) ? $this->idServer : $idServer;
    if ($this->db->tableExists(self::$taskTable)
      && !empty($idServer)
    ) {
      return $this->db->rselect(self::$taskTable, [], [
        'id_server' => $idServer,
        'id_project' => $idProject,
        'id_issue' => $idIssue,
        'id_comment' => null,
        'id_parent' => null,
        'id_note' => null
      ]);
    }
    return null;
  }


  private function getAppuiTaskNote(string $idProject, int $idIssue, int $idComment, string $idServer = ''): ?array
  {
    $idServer = empty($idServer) ? $this->idServer : $idServer;
    if ($this->db->tableExists(self::$taskTable)
      && !empty($idServer)
    ) {
      return $this->db->rselect([
        'table' => self::$taskTable,
        'fields' => [],
        'join' => [[
          'table' => self::$taskTable,
          'alias' => 'parent',
          'on' => [
            'conditions' => [[
              'field' => 'parent.id',
              'exp' => $this->db->cfn('id_parent', self::$taskTable)
            ], [
              'field' => 'parent.id_server',
              'value' => $idServer
            ], [
              'field' => 'parent.id_project',
              'value' => $idProject
            ], [
              'field' => 'parent.id_issue',
              'value' => $idIssue
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $this->db->cfn('id_server', self::$taskTable),
            'value' => $idServer
          ], [
            'field' => $this->db->cfn('id_project', self::$taskTable),
            'value' => $idProject
          ], [
            'field' => $this->db->cfn('id_comment', self::$taskTable),
            'value' => $idComment
          ]]
        ]
      ]);
    }
    return null;
  }


  private function getAppuiTaskNotes(string $idProject, int $idIssue, string $idServer = ''): ?array
  {
    $idServer = empty($idServer) ? $this->idServer : $idServer;
    if ($this->db->tableExists(self::$taskTable)
      && !empty($idServer)
    ) {
      $this->db->getColumnValues([
        'table' => self::$taskTable,
        'fields' => ['id_note'],
        'join' => [[
          'table' => self::$taskTable,
          'alias' => 'parent',
          'on' => [
            'conditions' => [[
              'field' => 'parent.id',
              'exp' => $this->db->cfn('id_parent', self::$taskTable)
            ], [
              'field' => 'parent.id_server',
              'value' => $idServer
            ], [
              'field' => 'parent.id_project',
              'value' => $idProject
            ], [
              'field' => 'parent.id_issue',
              'value' => $idIssue
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $this->db->cfn('id_server', self::$taskTable),
            'value' => $idServer
          ], [
            'field' => $this->db->cfn('id_project', self::$taskTable),
            'value' => $idProject
          ]]
        ]
      ]);
    }
    return null;
  }


  private function processComment(int $idProject, string $type, object $task): bool
  {
    $success = false;
    if (!empty($task->idIssue)
      && ($appuiTask = $this->getAppuiTaskByIssue($idProject, $task->idIssue))
    ) {
      if ($type === 'import') {
        if (!empty($task->idComment)) {
          $idTask = $appuiTask['id_task'];
          $idUser = BBN_EXTERNAL_USER_ID;
          if ($u = $this->getAppuiUser($task->idUser)) {
            $idUser = $u['id'];
          }
          $currentNote = $this->getAppuiTaskNote($idProject, $task->idIssue, $task->idComment);
          switch ($task->action) {
            case 'insert':
              $success = empty($currentNote)
                && ($idNote = $this->addAppuiTaskNote($idProject, $idTask, $idUser, $task->text, $task->updated))
                && $this->addAppuiTaskNoteLink($appuiTask['id'], $idNote, $idProject, $task->idComment);
              break;
            case 'update':
              $notes = new Note($this->db);
              if (!empty($currentNote)
                && ($note = $notes->get($currentNote['id_note']))
                && ($task->updated > $note['creation'])
              ) {
                $success = $this->editAppuiTaskNote($idProject, $idTask, $task->idComment, $idUser, $task->text, $task->updated);
              }
              break;
            case 'delete':
              $success = !empty($currentNote)
                && $this->removeAppuiTaskNote($idProject, $idTask, $task->idComment);
              break;
          }
        }
      }
      else if ($type === 'export') {
        $vcsCls = $this;
        if (!empty($task->idUser)
          && $idUser = $this->getUserByAppuiUser($task->idUser)
        ) {
          $vcsCls = new \bbn\Appui\Vcs($this->db, $this->idServer, $idUser);
        }
        switch ($task->action) {
          case 'insert':
            if (empty($idUser)) {
              $task->text = User::getInstance()->getName($task->idUser) . ' ' . _('wrote:') . PHP_EOL . PHP_EOL . $task->text;
            }
            $success = ($n = $vcsCls->insertProjectIssueComment(
                $idProject,
                $task->idIssue,
                $task->text,
                !empty($task->locked),
                $task->updated
              ))
              && $vcsCls->addAppuiTaskNoteLink($appuiTask['id'], $task->idNote, $idProject, $n['id']);
            break;
          case 'update':
            if ($comment = $this->getProjectIssueComment($idProject, $task->idIssue, $task->idComment)) {
              if ($comment['updated'] > $task->updated) {
                return false;
              }
              if (empty($idUser)) {
                $task->text = User::getInstance()->getName($task->idUser) . ' ' . _('edited:') . PHP_EOL . PHP_EOL . $task->text;
              }
              $success = $vcsCls->editProjectIssueComment(
                $idProject,
                $task->idIssue,
                $task->idComment,
                $task->text,
                !empty($task->locked),
                $task->updated
              );
            }
            break;
          case 'delete':
            $success = $vcsCls->deleteProjectIssueComment($idProject, $task->idIssue, $task->idComment)
              && $vcsCls->removeAppuiTaskNoteLink($appuiTask['id'], $task->idNote);
            break;
        }
      }
    }
    return !empty($success);
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
          id INTEGER NOT NULL,
          id_server VARCHAR (16) NOT NULL,
          id_project INTEGER NOT NULL,
          type TEXT NOT NULL CHECK (type IN ('import', 'export')),
          created DATETIME NOT NULL DEFAULT (CURRENT_TIMESTAMP),
          task TEXT NOT NULL,
          started DATETIME DEFAULT NULL,
          ended DATETIME DEFAULT NULL,
          failed INTEGER (1) DEFAULT (0) NOT NULL CHECK (failed IN (0, 1)),
          active INTEGER (1) NOT NULL DEFAULT (1) CHECK (active IN (0, 1)),
          PRIMARY KEY (id)
        );");
      }
      return \is_file($path) ? $path : null;
    }
    return null;
  }


}