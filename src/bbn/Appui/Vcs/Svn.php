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
    if ($server = $this->getServer($id)) {
      $this->checkServerHost($server->host);
      return new \stdClass;
    }
  }

  public function getCurrentUser(string $id): array
  {
    return [];
  }


  public function getProjectsList(string $id, int $page = 1, int $perPage = 25): array
  {
    return [];
  }


  public function getProject(string $idServer, string $idProject): ?array
  {
    return null;
  }


  public function getProjectBranches(string $idServer, string $idProject): array
  {
    return [];
  }


  public function getProjectTags(string $idServer, string $idProject): array
  {
    return [];
  }


  public function getProjectUsers(string $idServer, string $idProject): array
  {
    return [];
  }


  public function getProjectUsersRoles(): array
  {
    return [];
  }


  public function getProjectUsersEvents(string $idServer, string $idProject): array
  {
    return [];
  }


  public function getProjectEvents(string $idServer, string $idProject): array
  {
    return [];
  }


  public function getProjectCommitsEvents(string $idServer, string $idProject): array
  {
    return [];
  }


  public function getProjectLabels(string $idServer, string $idProject): array
  {
    return [];
  }


  public function normalizeBranch(object $branch): array
  {
    return [];
  }


  public function normalizeEvent(object $event): array
  {
    return $event;
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


  public function insertBranch(string $idServer, string $idProject, string $branch, string $fromBranch): array
  {
    return [];
  }


  public function deleteBranch(string $idServer, string $idProject, string $branch): bool
  {
    return false;
  }


  public function insertProjectUser(string $idServer, string $idProject, int $idUser, int $idRole): array
  {
    return [];
  }


  public function removeProjectUser(string $idServer, string $idProject, int $idUser): bool
  {
    return false;
  }


  public function getUsers(string $idServer): array
  {
    return [];
  }

  public function getProjectIssues(string $idServer, string $idProject): array
  {
    return [];
  }

}