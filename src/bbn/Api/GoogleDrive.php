<?php

namespace bbn\Api;

use bbn\X;
use bbn\File\Dir;

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

  private $credentials;

  private $token = null;

  private $client = null;

  private $connection = null;

  /**
   * Constructor.
   * @param array $cfg
   */
  public function __construct(array $credentials, ?array $token = null)
  {
    if (empty($credentials)) {
      throw new \Exception(X::_('Unable to connect without credentials'));
    }

    $this->credentials = $credentials;
    $this->token = $token;
  }

  public function getFilesList(): array
  {
    return X::toArray($this->getConnection()->files->listFiles([
      'includeItemsFromAllDrives' => true,
      'orderBy' => 'folder,name',
      'supportsAllDrives' => true
    ])->files);
  }

  public function getFile(string $id): array
  {
    return X::toArray($this->getConnection()->files->get($id, [
      'supportsAllDrives' => true
    ]));
  }

  public function downloadFile(string $id, string $destination)
  {
    return ($response = $this->getConnection()->files->get($id, [
        'supportsAllDrives' => true,
        'alt' => 'media'
      ]))
      && ($file = $response->getBody()->getContents())
      && Dir::createPath(\dirname($destination))
      && \file_put_contents($destination, $file);
  }

  public function uploadFile()
  {
    
  }

  public function isFile($idOrFile)
  {
    $f = $this->isFolder($idOrFile);
    if (\is_null($f)) {
      return null;
    }
    return $f === false;
  }

  public function isFolder($idOrFile): ?bool
  {
    if (\is_string($idOrFile)) {
      $idOrFile = $this->getFile($idOrFile);
    }

    if (\is_array($idOrFile)
      && isset($idOrFile['mimeType'])
    ) {
      return $idOrFile['mimeType'] === 'application/vnd.google-apps.folder';
    }

    return null;
  }

  public function getAccessTokenByCode(string $code): ?array
  {
    if ($this->getClient()) {
      return $this->fetchTokenCode($code);
    }
    return null;
  }

  public function getAccessToken(): ?array
  {
    if (!empty($this->token)
      && $this->getClient()
    ) {
      $this->client->setAccessToken($this->token);
      return $this->client->getAccessToken();
    }
    return null;
  }

  public function getRefreshToken(): ?string
  {
    if (!empty($this->token)
      && $this->getClient()
    ) {
      $this->client->setAccessToken($this->token);
      return $this->client->getRefreshToken();
    }
    return null;
  }

  public function createAuthUrl(): ?string
  {
    if ($this->getClient()) {
      return $this->client->createAuthUrl();
    }
    return null;
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

  private function isTokenExpired(): bool
  {
    if ($this->getClient()) {
      $this->client->setAccessToken($this->token);
      return $this->client->isAccessTokenExpired();
    }
    return false;
  }

  private function refreshExpiredToken(): ?array
  {
    if ($this->isTokenExpired()) {
      if ($code = $this->client->getRefreshToken()) {
        $token = $this->fetchTokenCode($code, true);
        $this->client->setAccessToken($token);
        return $this->client->getAccessToken();
      }
    }
    return null;
  }

  private function getClient(): ?\Google\Client
  {
    if (empty($this->client)) {
      $this->client = new \Google\Client();
      $this->client->setAuthConfig($this->credentials);
      $this->client->setAccessType('offline');
      $this->client->addScope("https://www.googleapis.com/auth/drive");
      $this->client->addScope("https://www.googleapis.com/auth/drive.metadata");
    }

    return $this->client;
  }

  private function getConnection()
  {
    if (empty($this->connection)
      && $this->getClient()
    ) {
      if (empty($this->token)) {
        return $this->createAuthUrl();
      }
      else {
        if ($this->isTokenExpired()) {
          if (!$this->refreshExpiredToken()) {
            return $this->createAuthUrl();
          }
        }

        $this->connection = new \Google\Service\Drive($this->client);
      }
    }

    return $this->connection;
  }

}
