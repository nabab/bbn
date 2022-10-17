<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:33
 */

namespace bbn\Appui\Vcs;


interface Server {

  function __construct(\bbn\Db $db, string $idServer);

  function hasAdminAccessToken(string $id): bool;

  function getAdminAccessToken(string $id): ?string;

  function getUserAccessToken(string $id): string;

  function getConnection(bool $asAdmin = false): object;

  function getServer(string $id): object;

  function getCurrentUser(): array;

  function getProjectsList(int $page = 1, int $perPage = 25): array;

  function getProject(string $idProject): ?array;

  function getProjectBranches(string $idProject): array;

  function getProjectTags(string $idProject): array;

  function getProjectUsers(string $idProject): array;

  function getProjectUsersRoles(): array;

  function getProjectUsersEvents(string $idProject): array;

  function getProjectEvents(string $idProject): array;

  function getProjectCommitsEvents(string $idProject): array;

  function getProjectLabels(string $idProject): array;

  function normalizeBranch(object $branch): array;

  function normalizeEvent(object $event): array;

  function normalizeUser(object $user): array;

  function normalizeMember(object $member): array;

  function normalizeLabel(object $label): array;

  function normalizeProject(object $project): array;

  function insertBranch(string $idProject, string $branch, string $fromBranch): array;

  function deleteBranch(string $idProject, string $branch): bool;

  function insertProjectUser(string $idProject, int $idUser, int $idRole): array;

  function removeProjectUser(string $idProject, int $idUser): bool;

  function getProjectIssues(string $idProject): array;

  function getProjectIssue(string $idProject, int $idIssue): array;

  function createProjectIssue(
    string $idProject,
    string $title,
    string $description = '',
    array $labels = [],
    int $assigned = null,
    bool $private = false,
    string $date = ''
  ): ?array;

  public function editProjectIssue(
    string $idProject,
    int $idIssue,
    string $title,
    string $description = '',
    array $labels = [],
    int $assigned = null,
    bool $private = false
  ): ?array;

  function closeProjectIssue(string $idProject, int $idIssue): ?array;

  function reopenProjectIssue(string $idProject, int $idIssue): ?array;

  function assignProjectIssue(string $idProject, int $idIssue, int $idUser): ?array;

  function getProjectIssueComments(string $idProject, int $idIssue): array;

  function insertProjectIssueComment(string $idProject, int $idIssue, string $content, bool $pvt = false, string $date = ''): ?array;

  function editProjectIssueComment(string $idProject, int $idIssue, int $idComment, string $content, bool $pvt = false): ?array;

  function deleteProjectIssueComment(string $idProject, int $idIssue, int $idComment): bool;

  function createProjectLabel(string $idProject, string $name, string $color): ?array;

  function addLabelToProjectIssue(string $idProject, int $idIssue, string $label): bool;

  function removeLabelFromProjectIssue(string $idProject, int $idIssue, string $label): bool;

  function getUsers(): array;

}