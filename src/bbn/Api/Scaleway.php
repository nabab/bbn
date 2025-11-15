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
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Api/Scaleway
 */
class Scaleway
{
  use \bbn\Models\Tts\Cache;
  use Scaleway\Server;
  use Scaleway\Dedibox;
  use Scaleway\Failover;
  use Scaleway\Domain;
  use Scaleway\Hosting;

  /** @var string The name reserved for the cache */
  const CACHE_NAME = 'bbn/api/scaleway';

  /** @var string The API url */
  const API_URL = 'https://api.online.net/';

  /** @var string The API url prefix */
  const API_URL_PREFIX = 'api/v1/';

  /** @var string The API auth token */
  private $_token;

  /** @var \bbn\Cache cacher */
  private $_cacher;

  /** @var bool testmode */
  private $_testmode;


  /**
   * Constructor.
   * @param string $token THe API auth token
   */
  public function __construct(string $token, bool $testmode = false)
  {
    if (empty($token)) {
      throw new \Error(_('The API token is mandatory'));
    }

    self::cacheInit();
    $this->_token = $token;
    $this->_testmode = $testmode;
    if (class_exists('\\bbn\\Cache')) {
      $this->_cacher = Cache::getEngine();
    }
  }


  /**
   * Makes the API url by the command
   * @param string $command The command
   * @return string
   */
  private function _makeApiUrl(string $command): string
  {
    if (Str::pos($command, '/') === 0) {
      $command = Str::sub($command, 1);
    }

    return self::API_URL .
      (Str::pos($command, self::API_URL_PREFIX) !== 0 ? self::API_URL_PREFIX : '') .
      $command;
  }


  /**
   * Makes the call to the API
   * @param string $command The command
   * @param array  $post    The data to send
   * @return array
   */
  private function _callCommand(string $command, array $post = []): array
  {
    $header = [
      'HTTPHEADER' => [
        'Authorization: Bearer ' . $this->_token
      ]
    ];
    if (empty($post)) {
      $header['get'] = 1;
    }
    else {
      $header['post'] = 1;
    }

    $curl = X::curl(
      $this->_makeApiUrl($command),
      $post,
      $header
    );
    if (Str::isJson($curl)) {
      $curl = json_decode($curl, true);
    }

    return $curl ?: [];
  }


}
