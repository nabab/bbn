<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:33
 */

namespace bbn\Appui\Vcs;


interface Server {

  function hasAdminAccessToken(string $id): bool;

  function getAdminAccessToken(string $id): ?string;

  function getUserAccessToken(string $id): string;

  function getConnection(string $id, bool $asAdmin = false): object;

  function getServer(string $id): object;

  function getCurrentUser(string $id): array;

  function getProjectsList(string $id, int $page = 1, int $perPage = 25): array;

  function getProject(string $idServer, string $idProject): ?array;

  function getProjectBranches(string $idServer, string $idProject): array;

  function getProjectTags(string $idServer, string $idProject): array;

  function getProjectUsers(string $idServer, string $idProject): array;

  function getProjectUsersRoles(): array;

  function getProjectUsersEvents(string $idServer, string $idProject): array;

  function getProjectEvents(string $idServer, string $idProject): array;

  function getProjectCommitsEvents(string $idServer, string $idProject): array;

  function getProjectLabels(string $idServer, string $idProject): array;

  function normalizeBranch(object $branch): array;

  function normalizeEvent(object $event): array;

  function normalizeUser(object $user): array;

  function normalizeMember(object $member): array;

  function normalizeLabel(object $label): array;

  function normalizeProject(object $project): array;

  function insertBranch(string $idServer, string $idProject, string $branch, string $fromBranch): array;

  function deleteBranch(string $idServer, string $idProject, string $branch): bool;

  function insertProjectUser(string $idServer, string $idProject, int $idUser, int $idRole): array;

  function removeProjectUser(string $idServer, string $idProject, int $idUser): bool;

  function getProjectIssues(string $idServer, string $idProject): array;

  function getUsers(string $idServer): array;

}