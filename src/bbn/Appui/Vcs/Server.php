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

  function getCurrentUser(string $id): object;

  function getProjectsList(string $id, int $page = 1, int $perPage = 25): array;

  function getProject(string $idServer, string $idProject): ?object;

  function getProjectBranches(string $idServer, string $idProject): array;

  function getProjectTags(string $idServer, string $idProject): array;

  function getProjectUsers(string $idServer, string $idProject): array;

  function getProjectUsersEvents(string $idServer, string $idProject): array;

  function getProjectEvents(string $idServer, string $idProject): array;

  function getProjectCommitsEvents(string $idServer, string $idProject): array;

  function normalizeEvent(object $event): object;

  function normalizeUser(object $user): object;

  function normalizeProject(object $project): object;


}