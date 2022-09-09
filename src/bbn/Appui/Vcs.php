<?php

namespace bbn\Appui;

use bbn;
use bbn\Cache;
use bbn\Appui\Passwords;
use bbn\X;
use bbn\Appui\Option;
use bbn\Str;
use bbn\Api\GitLab;
use bbn\Appui\Vcs\Git;
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
    $this->git = new Git($this->db);
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
    $this->checkServerHost($host);
    if (!($idParent = $this->getOptionId('list'))) {
      throw new \Exception(X::_('"list" option not found'));
    }
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


  public function editServer(string $id, string $name, string $host, string $type){
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
    return false;
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


  public function getProject(string $idServer, string $idProject): ?object
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


  public function getAppuiUsers(string $idServer): array
  {
    if ($prefs = $this->db->rselectAll([
      'table' => 'bbn_users_options',
      'fields' => [
        'id' => 'id_user',
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