<?php

namespace bbn\Appui;

use bbn;
use bbn\Cache;
use bbn\Appui\Passwords;
use bbn\X;
use bbn\Appui\Option;
use bbn\Str;
use bbn\Api\GitLab;

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

  const CACHE_NAME = 'bbn/Appui/Vcs';

  /** @var bbn\Appui\Option The bbn\Appui\Option class instance */
  private $opt;

  /** @var string The cache name prefix */
  private $cacheNamePrefix;

  /** @var bbn\Db The bbm\Db class instance */
  private $db;

  /** @var bbn\Appui\Passwords The bbm\Appui\Passwords class instance */
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


  public function hasAdminAccessToken(string $id): bool
  {
    return !!$this->pwd->get($id);
  }


  public function setUserAccessToken(string $id, string $token): bool
  {
    if (!($user = \bbn\User::getInstance())) {
      throw new \Exception(X::_('No User class instance found'));
    }
    if (!($pref = \bbn\User\Preferences::getInstance())) {
      throw new \Exception(X::_('No User\Preferences class instance found'));
    }
    if (!($idPref = $pref->add($id, []))) {
      throw new \Exception(X::_('Error while adding the user preference: idUser %s - idOption %s', $user->getId(), $id));
    }
    if (!$this->pwd->userStore($token, $idPref, $user)) {
      throw new \Exception(X::_('Error while storing the user access token: ID: %s , Token: %s', $id, $token));
    }
    return true;
  }


  public function getUserAccessToken(string $id): string
  {
    if (!($user = \bbn\User::getInstance())) {
      throw new \Exception(X::_('No User class instance found'));
    }
    if (!($pref = \bbn\User\Preferences::getInstance())) {
      throw new \Exception(X::_('No User\Preferences class instance found'));
    }
    if (!($idPref = $pref->getByOption($id))) {
      throw new \Exception(X::_('No user\'s preference found for the server %s', $id));
    }
    if (!($token = $this->pwd->userGet($idPref, $user))) {
      throw new \Exception(X::_('No user\'s access token found for the server %s', $id));
    }
    return $token;
  }


  public function addServer(string $name, string $url, string $type, string $adminAccessToken, string $userAccessToken = ''): string
  {
    if (!Str::isUrl($url)) {
      throw new \Exception(X::_('No valid URL: %s', $url));
    }
    if (!($idParent = $this->getOptionId('list'))) {
      throw new \Exception(X::_('"list" option not found'));
    }
    $o = [
      'id_parent' => $idParent,
      'text' => $name,
      'code' => $url,
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


  public function editServer(string $id, string $name, string $url, string $type){
    if (!Str::isUrl($url)) {
      throw new \Exception(X::_('No valid URL: %s', $url));
    }
    $o = [
      'text' => $name,
      'code' => $url,
      'type' => $type
    ];
    if (!$this->opt->set($id, $o)) {
      throw new \Exception(X::_('Error while updating the option with ID %s: %s', $id, \json_encode($o)));
    }
    return false;
  }


  public function getServer(string $id): array
  {
    if (!($server = $this->opt->option($id))) {
      throw new \Exception(X::_('No server found with ID %s', $id));
    }
    return $this->normalizeServer($server);
  }


  public function getServersList(): array
  {
    $t = $this;
    return \array_map(function($o) use($t){
      return $t->normalizeServer($o);
    }, $this->opt->fullOptions($this->getOptionId('list')));
  }


  public function getProjectsList(string $id): array
  {
    if (($accessToken = $this->getUserAccessToken($id))
      && ($server = $this->opt->option($id))
      && !empty($server['code'])
    ) {
      $gitlab = new \bbn\Appui\Api\GitLab($accessToken, $server['code']);
      return $gitlab->getProjects();
    }
    return [];
  }


  private function normalizeServer(array $obj): array
  {
    try {
      $ut = !!$this->getUserAccessToken($obj['id']);
    }
    catch(\Exception $e) {
      $ut = false;
    }
    return [
      'id' => $obj['id'],
      'name' => $obj['text'],
      'url' => $obj['code'],
      'type' => $obj['type'],
      'hasAdminAccessToken' => $this->hasAdminAccessToken($obj['id']),
      'hasUserAccessToken'=> $ut
    ];
  }


}