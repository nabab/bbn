<?php

namespace bbn\Api;

use bbn\X;
use bbn\File;
use bbn\File\Dir;
use bbn\Str;
use \Google\Client;
use \Google\Service\Drive;
use \Google\Service\Drive\DriveFile;

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

  public function getFiles(?string $idParent = null, bool $includeDirs = false, ?string $detailed = null): array
  {
    return $this->getItems(empty($includeDirs) ? 'file' : 'both', $idParent, $detailed);
  }

  public function getDirs(?string $idParent = null, ?string $detailed = null): array
  {
    return $this->getItems('dir', $idParent, $detailed);
  }

  public function getItem(string $id): ?array
  {
    if ($this->getConnection()) {
      return X::toArray($this->connection->files->get($id, [
        'supportsAllDrives' => true,
        'fields' => '*'
      ]));
    }

    return null;
  }

  public function download(string $id)
  {
    if (($file = $this->getItem($id))
      && ($destination = \bbn\Mvc::getTmpPath() . $file['name'])
      && ($contents = $this->getContents($id))
      && Dir::createPath(\dirname($destination))
      && \file_put_contents($destination, $contents)
    ) {
      $f = new File($destination);
      $f->download();
     }
  }

  public function upload(array $files, ?string $destination = null): bool
  {
    if (empty($destination)) {
      $destination = $this->getRootID();
    }

    if (!empty($destination)
      && $this->getConnection()
    ) {
      foreach ($files as $f) {
        if (\is_file($f['tmp_name'])
          && ($content = \file_get_contents($f['tmp_name']))
        ) {
          $emptyFile = new DriveFile([
            'name' => $f['name'],
            'parents' => [$destination]
          ]);
          if (!$this->connection->files->create($emptyFile, [
            'data' => $content,
            'mimeType' => \mime_content_type($f['tmp_name']),
            'uploadType' => 'multipart',
            'fields' => 'id',
            'supportsAllDrives' => true
          ])) {
            return false;
          }
        }
      }

      return true;
    }

    return false;
  }

  public function mkdir(string $name, string $destination): ?string
  {
    if (empty($destination)) {
      $destination = $this->getRootID();
    }

    if (!empty($destination)
      && $this->getConnection()
    ) {
      $emptyFile = new DriveFile([
        'name' => $name,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents' => [$destination]
      ]);
      if ($item = $this->connection->files->create($emptyFile, [
        'fields' => 'id',
        'supportsAllDrives' => true
      ])) {
        return $item->id;
      }
    }

    return null;
  }

  public function delete(string $id): bool
  {
    if ($this->exists($id)
      && ($response = $this->connection->files->delete($id, [
        'supportsAllDrives' => true
      ]))
    ) {
      return empty($response->getBody()->getContents());
    }

    return false;
  }

  public function copy(string $id, string $destination = null): ?string
  {
    if ($item = $this->getItem($id)) {
      $emptyFile = new DriveFile();
      if (empty($destination)
        || \in_array($destination, $item['parents'])
      ) {
        $f = Str::fileExt($item['name'], true);
        $filename = $f[0] . date('_Y-m-d-H-i-s_') . (!empty($f[1]) ? '.' . $f[1] : '');
        $emptyFile->name = X::_("Copy of %s", $filename);
      }

      if (!empty($destination)) {
        $emptyFile->parents = [$destination];
      }

      if ($file = $this->connection->files->copy($id, $emptyFile, [
        'supportsAllDrives' => true
      ])) {
        return $file->id;
      }
    }

    return null;
  }

  public function move(string $id, string $destination): bool
  {
    if ($item = $this->getItem($id)) {
      $emptyFile = new DriveFile();
      if ($this->connection->files->update($id, $emptyFile, [
        'supportsAllDrives' => true,
        'addParents' => $destination,
        'removeParents' => \implode(',', $item['parents']),
        'fields' => 'id, parents'
      ])) {
        return true;
      }
    }

    return false;
  }

  public function rename(string $id, string $newName): bool
  {
    if ($this->exists($id)) {
      $emptyFile = new DriveFile();
      $emptyFile->name = $newName;
      $emptyFile->modifiedTime = date('c');
      if ($file = $this->connection->files->update($id, $emptyFile, [
        'supportsAllDrives' => true,
        'fields' => 'name'
      ])) {
        return $file->name === $newName;
      }
    }

    return false;
  }

  public function exists(string $id): bool
  {
    return !empty($this->getItem($id));
  }

  public function getContents(string $id)
  {
    if ($this->getConnection()
      && ($response = $this->connection->files->get($id, [
        'supportsAllDrives' => true,
        'alt' => 'media'
      ]))
    ) {
      return $response->getBody()->getContents();
    }

    return null;
  }

  public function getSize(string $id)
  {
    if ($item = $this->getItem($id)) {
      return $item['size'];
    }

    return null;
  }

  public function getMtime(string $id)
  {
    if ($item = $this->getItem($id)) {
      return $item['modifiedTime'];
    }

    return null;
  }

  public function isFile($idOrFile)
  {
    $f = $this->isDir($idOrFile);
    if (\is_null($f)) {
      return null;
    }
    return $f === false;
  }

  public function isDir($idOrFile): ?bool
  {
    if (\is_string($idOrFile)) {
      $idOrFile = $this->getItem($idOrFile);
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

  private function getRootID(): ?string
  {
    if (($root = $this->getItem('root'))
      && !empty($root['id'])
    ) {
      return $root['id'];
    }
    return null;
  }

  private function getItems(string $type = 'both', ?string $idParent = null, ?string $detailed = null): array
  {
    $params = [
      'includeItemsFromAllDrives' => true,
      'orderBy' => 'folder,name',
      'supportsAllDrives' => true,
      'fields' => 'files(*)'
    ];
    switch ($type) {
      case 'file':
        $params['q'] = "mimeType != 'application/vnd.google-apps.folder'";
        break;
      case 'dir':
        $params['q'] = "mimeType = 'application/vnd.google-apps.folder'";
        break;
    }
    if (empty($idParent)) {
      $params['q'] = (isset($params['q']) ? $params['q'] . ' AND ' : '') . 'sharedWithMe = true';
      if ($idRoot = $this->getRootID()) {
        $params['q'] .= " OR '$idRoot' in parents";
      }
    }
    else {
      $params['q'] = (isset($params['q']) ? $params['q'] . ' AND ' : '') . "'$idParent' in parents";
    }
    $ret = [];
    if ($this->getConnection()
      && ($items = $this->connection->files->listFiles($params)->files)
    ) {
      foreach ($items as $item) {
        $isDir = $this->isDir(X::toArray($item));
        $it = [
          'path' => $item->id,
          'dir' => !!$isDir,
          'file' => !$isDir,
          'name' => $item->name
        ];
        if (!empty($detailed)) {
          if (str_contains($detailed, 's')) {
            $it['size'] = $item->size;
          }
          if (str_contains($detailed, 'm')) {
            $it['mtime'] = date('Y-m-d H:i:s', strtotime($item->modifiedTime));
          }
        }
        $ret[] = $it;
      }
    }
    return $ret;
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
      if (($code = $this->client->getRefreshToken())
        && ($token = $this->fetchTokenCode($code, true))
      ) {
        $this->client->setAccessToken($token);
        return $this->client->getAccessToken();
      }
    }
    return null;
  }

  private function getClient(): ?Client
  {
    if (empty($this->client)) {
      $this->client = new Client();
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
        throw new \Exception(X::_('No valid token. Create a new one via the createAuthUrl method'));
      }
      else {
        if ($this->isTokenExpired()) {
          if (!$this->refreshExpiredToken()) {
            throw new \Exception(X::_('Unable to refresh expired token. Create a new one via the createAuthUrl method'));
          }
        }

        $this->connection = new Drive($this->client);
      }
    }

    return $this->connection;
  }

}
