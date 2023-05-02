<?php

namespace bbn\Api;

use bbn\X;
use bbn\Str;
use bbn\User;
use bbn\User\Preferences;
use bbn\Appui\Option;
use bbn\Appui\Passwords;

/**
 * Google Drive API class
 * @category Api
 * @package Api
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Api/GoogleDrive
 */
class GoogleDrive
{

  private $db;

  private $pref;

  private $opt;

  private $credentialsOpt;

  private $tokensOpt;

  private $pass;

  private $driveName;

  private $client = null;

  private $connection = null;

  /**
   * Constructor.
   * @param array $cfg
   */
  public function __construct(string $driveName, ?\bbn\Db $db = null)
  {
    $this->db = !empty($db) ? $db : \bbn\Db::getInstance();
    $this->driveName = $driveName;
    $this->pref = Preferences::getInstance();
    if (empty($this->pref)) {
      throw new \Exception(X::_('No user preferences instance found'));
    }

    $this->opt = Option::getInstance();
    if (empty($this->opt)) {
      throw new \Exception(X::_('No appui option instance found'));
    }

    $this->credentialsOpt = $this->opt->fromCode('credentials', 'googledrive', 'finder', 'appui');
    if (empty($this->credentialsOpt)) {
      throw new \Exception(X::_('No credentials option found'));
    }

    $this->tokensOpt = $this->opt->fromCode('tokens', 'googledrive', 'finder', 'appui');
    if (empty($this->tokensOpt)) {
      throw new \Exception(X::_('No tokens option found'));
    }

    $this->pass = new Passwords($this->db);
  }

  public function getFilesList()
  {
    return $this->getConnection()->files->listFiles(['pageSize' => 10]);
  }

  public function setCredentials($credentials): bool
  {
    if (!($pId = $this->getCredentialsPref())) {
      $pId = $this->pref->add($this->credentialsOpt, ['text' => $this->driveName]);
    }

    if (!empty($pId)) {
      return $this->pass->userStore(
        !Str::isJson($credentials) ? \json_encode($credentials) : $credentials,
        $pId,
        User::getInstance()
      );
    }

    return false;
  }

  public function setToken($token): bool
  {
    if (!($pId = $this->getTokensPref())) {
      $pId = $this->pref->add($this->tokensOpt, ['text' => $this->driveName]);
    }

    if (!empty($pId)) {
      return $this->pass->userStore(
        !Str::isJson($token) ? \json_encode($token) : $token,
        $pId,
        User::getInstance()
      );
    }

    return false;
  }

  public function setTokenByCode(string $code): ?array
  {
    if ($this->getClient()
      && ($token = $this->fetchTokenCode($code))
    ) {
      $this->setToken($token);
      return $token;
    }

    return null;
  }

  private function getCredentials(): ?array
  {
    if (($pId = $this->getCredentialsPref())
      && ($c = $this->pass->userGet($pId, User::getInstance()))
    ) {
      return \json_decode($c, true);
    }
    return null;
  }

  private function getToken(): ?array
  {
    if (($pId = $this->getTokensPref())
      && ($t = $this->pass->userGet($pId, User::getInstance()))
    ) {
      return \json_decode($t, true);
    }
    return null;
  }

  private function deleteToken(): bool
  {
    if ($pId = $this->getTokensPref()) {
      return (bool)$this->pass->userDelete($pId, User::getInstance());
    }
    return false;
  }

  private function fetchTokenCode(string $code, bool $refresh = false): array
  {
    try {
      if ($refresh) {
        $token = $this->client->fetchAccessTokenWithRefreshToken($code);
      }
      else {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
      }
      if (empty($token) || !empty($token['error'])) {
        throw new \Exception(!empty($token['error']) ? \json_encode($token) : '');
      }
      return $token;
    }
    catch(\Exception $e) {
      throw new \Exception(X::_('Error during token code fetch: %s - Error: %s', $code, $e->getMessage()));
    }
  }

  private function getCredentialsPref(): ?string
  {
    if ($all = $this->pref->getAll($this->credentialsOpt, false)) {
      return X::getField($all, ['text' => $this->driveName], 'id');
    }
    return null;
  }

  private function getTokensPref(): ?string
  {
    if ($all = $this->pref->getAll($this->tokensOpt, false)) {
      return X::getField($all, ['text' => $this->driveName], 'id');
    }
    return null;
  }

  private function getClient(): ?\Google\Client
  {
    if (empty($this->client)
      && ($credentials = $this->getCredentials())
    ) {
      $this->client = new \Google\Client();
      $this->client->setAuthConfig($credentials);
      $this->client->setAccessType('offline');
      $this->client->addScope("https://www.googleapis.com/auth/drive");
    }

    return $this->client;
  }

  private function getConnection()
  {
    if (empty($this->connection)
      && $this->getClient()
    ) {
      if ($token = $this->getToken()) {
        $this->client->setAccessToken($token);
        if ($this->client->isAccessTokenExpired()) {
          if ($code = $this->client->getRefreshToken()) {
            $token = $this->fetchTokenCode($code, true);
            $this->setToken($token);
            $this->client->setAccessToken($token);
          }
          else {
            $this->deleteToken();
            return $this->getConnection();
          }
        }
        $this->connection = new \Google\Service\Drive($this->client);
      }
      else {
        return $this->client->createAuthUrl();
      }
    }

    return $this->connection;
  }

}
