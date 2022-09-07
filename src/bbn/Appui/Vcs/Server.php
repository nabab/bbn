<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:33
 */

namespace bbn\Appui\Vcs;


interface Server {

  function getConnection(string $id);

  function getProjectsList(string $id, int $page = 1, int $perPage = 25): array;

  function getProject(string $idServer, string $idProject): ?object;

  function getProjectBranches(string $idServer, string $idProject): array;

  function getProjectUsers(string $idServer, string $idProject): array;

  function normalizeProject(object $project): object;


}