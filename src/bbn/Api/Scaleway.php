<?php
namespace bbn\Api;

use bbn\Cache;
use bbn\X;
use bbn\Str;

/**
 * Scaleway API
 * @category API
 * @package Api
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license MIT
 * @link https://bbn.io/bbn-php/doc/class/Api/Scaleway
 */
class Scaleway
{
  use \bbn\Models\Tts\Cache;

  /** @var string The name reserved for the cache */
  const CACHE_NAME = 'bbn/api/scaleway';

  /** @var string The API url */
  const API_URL = 'https://api.online.net/api/v1/';

  /** @var string The API auth token */
  private $_token;

  /** @var \bbn\Cache cacher */
  private $_cacher;


  /**
   * Constructor.
   * @param string $token THe API auth token
   */
  public function __construct(string $token)
  {
    if (empty($token)) {
      throw new \Error(_('The API token is mandatory'));
    }

    self::cacheInit();
    $this->_token = $token;
    if (class_exists('\\bbn\\Cache')) {
      $this->_cacher = Cache::getEngine();
    }
  }


  /**
   * Get the servers list
   * @return array
   */
  public function getServers(): array
  {
    return $this->_callCommand('server');
  }


  /**
   * Makes the API url by the command
   * @param string $command The command
   * @return string
   */
  private function _makeApiUrl(string $command): string
  {
    return self::API_URL . $command;
  }


  /**
   * Makes the call to the API
   * @param string $command The command
   * @return array
   */
  private function _callCommand(string $command): array
  {
    $curl = X::curl(
      $this->_makeApiUrl($command),
      [],
      [
        'get' => 1,
        'HTTPHEADER' => [
          'Authorization: Bearer ' . $this->_token
        ]
      ]
    );
    if (Str::isJson($curl)) {
      $curl = json_decode($curl, true);
    }

    return $curl ?: [];
  }


}
