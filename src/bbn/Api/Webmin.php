<?php
namespace bbn\Api;

use bbn\Cache;

/**
 * Webmin API class
 * @package Api
 * @author Mirko Argentino <mirko@bbn.solutions>
 */
class Webmin
{
  use \bbn\Models\Tts\Cache;

  const CACHE_NAME = 'bbn/api/webmin';

  /** @var string Username */
  private $_user;

  /** @var string Password */
  private $_pass;

  /** @var string Hostname */
  private $_hostname;

  /** @var \bbn\Cache Cache */
  private $_cacher;


  /**
   * Constructor.
   * @param array $cfg
   */
  public function __construct(array $cfg)
  {
    if (empty($cfg['user'])) {
      throw new \Error(_('The username is mandatory'));
    }

    if (empty($cfg['pass'])) {
      throw new \Error(_('The password is mandatory'));
    }

    self::cacheInit();
    $this->_user = $cfg['user'];
    $this->_pass = $cfg['pass'];
    $this->_hostname = isset($cfg['host']) ? $cfg['host'] : 'localhost';
    if (class_exists('\\bbn\\Cache')) {
      $this->_cacher = Cache::getEngine();
    }
  }


  public function callCommand(string $command)
  {
    return xmlrpc_decode(file_get_contents($this->_getUrl(), false, $this->_getContext($command)));
  }


  public function getHostname()
  {
    return $this->callCommand('webmin::get_system_hostname');
  }


  public function getSystemUptime()
  {
    return $this->callCommand('webmin::get_system_uptime');
  }


  public function getNotifications()
  {
    return $this->callCommand('webmin::get_webmin_notifications');
  }


  public function getOS()
  {
    return $this->callCommand('webmin::detect_operating_system');
  }


  public function getSmartDisksPartitions()
  {
    return $this->callCommand('smart-status::list_smart_disks_partitions');
  }


  private function _getCredentials(): string
  {
    return base64_encode($this->_user.':'.$this->_pass);
  }


  private function _getContext(string $command)
  {
    return stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => [
          'Content-Type: text/xml',
          'Authorization: Basic ' . $this->_getCredentials()
        ],
        'content' => xmlrpc_encode_request($command, null)
      ],
      'ssl' => [
        'verify_peer' => false
      ]
    ]);
  }


  private function _getUrl(): string
  {
    return (strpos($this->_hostname, 'https://') !== 0 ? 'https://' : '') . $this->_hostname . ':10000/xmlrpc.cgi';
  }


}