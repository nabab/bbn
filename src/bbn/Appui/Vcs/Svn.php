<?php

namespace bbn\Appui\Vcs;

use bbn;
use bbn\Appui\Passwords;
use bbn\Appui\Option;
use bbn\X;

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

  /** @var string The server ID */
  private $idServer;

  /** @var object The server info */
  private $server;

  /** @var object The SVN class instance for normal user */
  private $userConnection;

  /** @var object The SVN class instance for admin user */
  private $adminConnection;


  /**
   * Constructor.
   * @param bbn\Db $db
   */
  public function __construct(bbn\Db $db, string $idServer)
  {
    $this->db = $db;
    $this->opt = Option::getInstance();
    $this->pwd = new Passwords($this->db);
    $this->idServer = $idServer;
    $this->server = $this->getServer($this->id);
    $this->checkServerHost($this->server->host);
    $this->userConnection = new \stdClass();
    $this->adminConnection = new \stdClass();
  }


  public function getConnection(bool $asAdmin = false): object
  {
    return $asAdmin ? $this->adminConnection : $this->userConnection;
  }

  public function getCurrentUser(): array
  {
    return [];
  }


  public function getProjectsList(int $page = 1, int $perPage = 25): array
  {
    return [];
  }


  public function getProject(string $idProject): ?array
  {
    return null;
  }


  public function getProjectBranches(string $idProject): array
  {
    return [];
  }


  public function getProjectTags(string $idProject): array
  {
    return [];
  }


  public function getProjectUsers(string $idProject): array
  {
    return [];
  }


  public function getProjectUsersRoles(): array
  {
    return [];
  }


  public function getProjectUsersEvents(string $idProject): array
  {
    return [];
  }


  public function getProjectEvents(string $idProject): array
  {
    return [];
  }


  public function getProjectCommitsEvents(string $idProject): array
  {
    return [];
  }


  public function getProjectLabels(string $idProject): array
  {
    return [];
  }


  public function normalizeBranch(object $branch): array
  {
    return [];
  }


  public function normalizeEvent(object $event): array
  {
    return (array)$event;
  }


  public function normalizeUser(object $user): array
  {
    return [
      'id' => '',
      'name' => '',
      'username' => '',
      'avatar' => '',
      'url' => ''
    ];
  }


  public function normalizeMember(object $member): array
  {
    return X::mergeArrays([
      'created' => '',
      'author' => [],
      'expire' => '',
      'role' => ''
    ], $this->normalizeUser($member));
  }


  public function normalizeLabel(object $label): array
  {
    return [
      'id' => '',
      'name' => '',
      'description' => '',
      'backgroundColor' => '',
      'fontColor' => '',
      'openedIssues' => '',
      'closedIssues' => ''
    ];
  }


  public function normalizeProject(object $project): array
  {
    return [
      'id' => '',
      'type' => 'svn',
      'name' => '',
      'fullname' => '',
      'description' => '',
      'path' => '',
      'fullpath' => '',
      'url' => '',
      'urlGit' => '',
      'urlSsh' => '',
      'namespace' => [
        'id' => '',
        'idParent' => '',
        'name' => '',
        'path' => '',
        'fullpath' => '',
        'url' => ''
      ],
      'created' => '',
      'creator' => '',
      'private' => '',
      'visibility' => '',
      'defaultBranch' => '',
      'archived' => '',
      'avatar' => '',
      'license' => [
        'name' => '',
        'code' => ''
      ],
      'noCommits' => '',
      'size' => '',
      'noForks' => '',
      'noStars' => ''
    ];
  }


  public function insertBranch(string $idProject, string $branch, string $fromBranch): array
  {
    return [];
  }


  public function deleteBranch(string $idProject, string $branch): bool
  {
    return false;
  }


  public function insertProjectUser(string $idProject, int $idUser, int $idRole): array
  {
    return [];
  }


  public function removeProjectUser(string $idProject, int $idUser): bool
  {
    return false;
  }


  public function getUsers(): array
  {
    return [];
  }


  public function getProjectIssues(string $idProject): array
  {
    return [];
  }

  public function getProjectIssue(string $idProject, int $idIssue): array
  {
    return [];
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
    return null;
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
    return null;
  }


  public function closeProjectIssue(string $idProject, int $idIssue): ?array
  {
    return null;
  }


  public function reopenProjectIssue(string $idProject, int $idIssue): ?array
  {
    return null;
  }


  public function assignProjectIssue(string $idProject, int $idIssue, int $idUser): ?array
  {
    return null;
  }


  public function getProjectIssueComments(string $idProject, int $idIssue): array
  {
    return [];
  }


  public function insertProjectIssueComment(string $idProject, int $idIssue, string $content, bool $pvt = false, string $date = ''): ?array
  {
    return [];
  }


  public function editProjectIssueComment(string $idProject, int $idIssue, int $idComment, string $content, bool $pvt = false): ?array
  {
    return [];
  }


  public function deleteProjectIssueComment(string $idProject, int $idIssue, int $idComment): bool
  {
    return false;
  }


  public function createProjectLabel(string $idProject, string $name, string $color): ?array
  {
    return null;
  }


  public function addLabelToProjectIssue(string $idProject, int $idIssue, string $label): bool
  {
    return false;
  }


  public function removeLabelFromProjectIssue(string $idProject, int $idIssue, string $label): bool
  {
    return false;
  }

}