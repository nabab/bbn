<?php

namespace bbn\Api;

use Error;
use stdClass;

/**
 * Webmin API class
 * @category Api
 * @package Api
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Api/Webmin
 */
class Webmin
{
  use \bbn\Models\Tts\Cache;

  const CACHE_NAME = 'bbn/Api/Webmin';

  /** @var string Username */
  private $user;

  /** @var string Password */
  private $pass;

  /** @var string Hostname */
  private $hostname;


  /**
   * Constructor.
   * @param array $cfg
   */
  public function __construct(array $cfg)
  {
    if (empty($cfg['user'])) {
      throw Error(_('The username is mandatory'));
    }

    if (empty($cfg['pass'])) {
      throw Error(_('The password is mandatory'));
    }

    $this->cacheInit();
    $this->user     = $cfg['user'];
    $this->pass     = $cfg['pass'];
    $this->hostname = isset($cfg['host']) ? $cfg['host'] : 'localhost';
  }


  /**
   * Call a command
   * @param string $command The command
   * @param array $args The command arguments
   * @return mixed
   */
  public function callCommand(string $command, array $args = [])
  {
    return \xmlrpc_decode(
      \file_get_contents(
        $this->getUrl(),
        false,
        $this->getContext($command, $args)
      )
    );
  }


  /**
   * Gets the hostname
   * @return string|null
   */
  public function getHostname(): ?string
  {
    return $this->callCommand('webmin::get_system_hostname');
  }


  /**
   * Gets the system uptime
   * @return string
   */
  public function getSystemUptime(): ?string
  {
    return $this->callCommand('webmin::get_system_uptime');
  }


  /**
   * Gets notifications
   * @return object
   */
  public function getNotifications(): stdClass // There was object bringing an error in doc parsing
  {
    return $this->callCommand('webmin::get_webmin_notifications');
  }


  /**
   * Gets the operating system
   * @return string
   */
  public function getOS(): string
  {
    return $this->callCommand('webmin::detect_operating_system');
  }


  /**
   * Gets disks partitions
   * @return array
   */
  public function getSmartDisksPartitions(): array
  {
    return $this->callCommand('smart-status::list_smart_disks_partitions');
  }


  /**
   * Start a service
   * @param string $service The name of the service
   * @return bool
   */
  public function startService(string $service): bool
  {
    $res = $this->callCommand('init::start_action', [$service]);
    return \is_array($res) && !empty($res[0]);
  }


  /**
   * Stop a service
   * @param string $service The name of the service
   * @return bool
   */
  public function stopService(string $service): bool
  {
    $res = $this->callCommand('init::stop_action', [$service]);
    return \is_array($res) && !empty($res[0]);
  }


  /**
   * Restart a service
   * @param string $service The name of the service
   * @return bool
   */
  public function restartService(string $service): bool
  {
    $res = $this->callCommand('init::restart_action', [$service]);
    return \is_array($res) && !empty($res[0]);
  }


  /**
   * Encodes and returns the credentials
   * @return string
   */
  private function getCredentials(): string
  {
    return base64_encode($this->user.':' . $this->pass);
  }


  /**
   * Undocumented function
   * @param string $command The command
   * @param array $args The command arguments
   * @return resource
   */
  private function getContext(string $command, $args = [])
  {
    return stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => [
          'Content-Type: text/xml',
          'Authorization: Basic ' . $this->getCredentials()
        ],
        'content' => xmlrpc_encode_request($command, $args)
      ],
      'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
      ]
    ]);
  }


  /**
   * Gets the call URL
   * @return string
   */
  private function getUrl(): string
  {
    return (strpos($this->hostname, 'https://') !== 0 ? 'https://' : '')
      . $this->hostname . ':10000/xmlrpc.cgi';
  }
}
