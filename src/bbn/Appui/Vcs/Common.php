<?php

namespace bbn\Appui\Vcs;

use bbn;
use bbn\X;
use bbn\Str;

trait Common {

  /** @var bbn\Db The bbn\Db class instance */
  private $db;

  /** @var bbn\Appui\Option The bbn\Appui\Option class instance */
  private $opt;

  /** @var bbn\Appui\Passwords The bbn\Appui\Passwords class instance */
  private $pwd;


  public function hasAdminAccessToken(string $id): bool
  {
    return !!$this->getAdminAccessToken($id);
  }


  public function getAdminAccessToken(string $id): ?string
  {
    return $this->pwd->get($id);
  }


  public function getUserAccessToken(string $id): string
  {
    if (!($user = \bbn\User::getInstance())) {
      throw new \Exception(X::_('No User class instance found'));
    }
    if (!($pref = \bbn\User\Preferences::getInstance())) {
      throw new \Exception(X::_('No User\Preferences class instance found'));
    }
    if (!($userPref = $pref->getByOption($id))) {
      throw new \Exception(X::_('No user\'s preference found for the server %s', $id));
    }
    else {
      $idPref = $userPref[$pref->getFields()['id']];
    }
    if (!($token = $this->pwd->userGet($idPref, $user))) {
      throw new \Exception(X::_('No user\'s access token found for the server %s', $id));
    }
    return $token;
  }


  public function getServer(string $id): object
  {
    if (!($server = $this->opt->option($id))) {
      throw new \Exception(X::_('No server found with ID %s', $id));
    }
    return $this->normalizeServer($server);
  }


  private function normalizeServer(array $server): object
  {
    try {
      $ut = $this->getUserAccessToken($server['id']);
    }
    catch(\Exception $e) {
      $ut = false;
    }
    return (object)[
      'id' => $server['id'],
      'name' => $server['text'],
      'host' => $server['code'],
      'type' => $server['type'],
      'userAccessToken' => $ut,
      'hasAdminAccessToken' => $this->hasAdminAccessToken($server['id']),
      'hasUserAccessToken'=> !empty($ut)
    ];
  }


  private function checkServerHost(string $host)
  {
    if (!Str::isUrl($host)) {
      throw new \Exception(X::_('No valid host URL: %s', $host));
    }
  }
}