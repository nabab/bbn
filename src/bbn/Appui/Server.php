<?php

namespace bbn\Appui;

use bbn;
use bbn\Cache;
use bbn\Api\Virtualmin;
use bbn\Api\Cloudmin;
use bbn\Api\Webmin;
use bbn\Appui\Passwords;
use bbn\X;
use bbn\Appui\Option;
use bbn\Db;
use SQLite3;

/**
 * Server class
 * @category Appui
 * @package Appui
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Appui/Server
 */
class Server
{
  use bbn\Models\Tts\Cache;
  use bbn\Models\Tts\Optional;

  const CACHE_NAME = 'bbn/Appui/Server';

  /** @var string Username */
  private $user;

  /** @var string Password */
  private $pass;

  /** @var string Hostname */
  private $hostname;

  private $opt;

  /** @var bbn\Api\Virtualmin Virtualmin instance */
  private $virtualmin;

  /** @var bbn\Api\Cloudmin|null Cloudmin instance */
  private $cloudmin = null;

  /** @var bbn\Api\Webmin Webmin instance */
  private $webmin;

  /** @var string The server cache name prefix */
  private $cacheNamePrefix;

  /** @var string The data path of the appui-server plugin */
  private $mainDataPath;

  /** @var string The data path of the server inside the appui-server plugin */
  private $dataPath;

  /** @var string The internal SQLite database */
  private $db;

  /** @var string The last error */
  private $lastError = '';


  /**
   * Constructor.
   * @param array $cfg
   */
  public function __construct($cfg)
  {
    $this->cacheInit();
    self::optionalInit();
    if (\is_string($cfg)) {
      $opt = self::getOption($cfg, 'servers');
      $psw = new Passwords(Db::getInstance());
      $cfg = [
        'user' => $opt['user'] ?? null,
        'pass' => !empty($opt['id']) ? $psw->get($opt['id']) : null,
        'host' => $opt['code'] ?? null
      ];
    }

    if (empty($cfg['user'])) {
      throw new \Exception(_('The username is mandatory'));
    }

    if (empty($cfg['pass'])) {
      throw new \Exception(_('The password is mandatory'));
    }

    $this->opt             = Option::getInstance();
    $this->user            = $cfg['user'];
    $this->pass            = $cfg['pass'];
    $this->hostname        = isset($cfg['host']) ? $cfg['host'] : 'localhost';
    $this->cacheNamePrefix = $this->hostname . '/';
    $this->mainDataPath    = self::getMainDataPath();
    $this->dataPath        = $this->mainDataPath . $this->hostname . '/';
    $this->db         = self::getDb();
    $this->virtualmin = new Virtualmin([
      'user' => $this->user,
      'pass' => $this->pass,
      'host' => $this->hostname,
      'mode' => 'virtualmin'
    ]);
    $this->webmin     = new Webmin([
      'user' => $this->user,
      'pass' => $this->pass,
      'host' => $this->hostname
    ]);
    if (!empty($cfg['cloudmin'])) {
      $this->cloudmin   = new Cloudmin([
        'user' => $this->user,
        'pass' => $this->pass,
        'host' => $this->hostname
      ]);
    }
    if (!$this->virtualmin->testConnection()) {
      throw new \Exception(sprintf(_('Connection with server "%s" failed'), $this->hostname));
    }
  }


    /**
   * Makes the cache for this server
   *
   * @param string $mode
   * @param string $domain
   * @return void
   */
  public function makeCache(string $mode = '', string $domain = '')
  {
    if (empty($mode) || ($mode === 'info') || $this->virtualmin->isInfoProp($mode)) {
      $this->cacheInfo($this->virtualmin->isInfoProp($mode) ? ['search' => $mode] : []);
    }

    if (empty($mode) || ($mode === 'uptime')) {
      $this->cacheUptime();
    }

    if (empty($mode) || ($mode === 'domains')) {
      $this->cacheListDomains($domain);
    }

    if (empty($mode) || ($mode === 'subdomains')) {
      $this->cacheListSubDomains($domain);
    }

    if (empty($mode) || ($mode === 'admins')) {
      $this->cacheListAdmins($domain);
    }

    if (empty($mode) || ($mode === 'users')) {
      $this->cacheListUsers($domain);
    }

    if (empty($mode) || ($mode === 'dns')) {
      $this->cacheListDns($domain);
    }

    if (empty($mode) || ($mode === 'backups')) {
      $this->cacheListBackups($domain);
    }

    if (empty($mode) || ($mode === 'features_template')) {
      $this->cacheListFeaturesTemplate($domain);
    }
  }


  /**
   * Checks if the cache file exists
   * @param string $path
   * @param string $domain
   * @return bool
   */
  public function hasCache(string $path, string $domain = ''): bool
  {
    return $this->getCache($path, false, $domain) !== false;
  }


  /**
   * Gets data from the cache
   * @param string $path
   * @param bool $force
   * @param string $domain
   * @return mixed
   */
  public function getCache(string $path, bool $force = false, string $domain = '')
  {
    $c = $this->cacheNamePrefix . (empty($domain) ? '' : "domains/$domain/") . $path;
    if ($force || ($this->cacheGet($c) === false)) {
      $this->makeCache($path, $domain);
    }
    return $this->cacheGet($c);
  }


  /**
   * Gets Virtualmin instance
   * @return bbn\Api\Virtualmin
   */
  public function getVirtualmin(): bbn\Api\Virtualmin
  {
    return $this->virtualmin;
  }


  /**
   * Gets Cloudmin instance
   * @return bbn\Api\Cloudmin|null
   */
  public function getCloudmin(): ?bbn\Api\Cloudmin
  {
    return $this->cloudmin;
  }


  /**
   * Gets Webmin instance
   * @return bbn\Api\Webmin
   */
  public function getWebmin(): bbn\Api\Webmin
  {
    return $this->webmin;
  }


  /**
   * Start a service
   * @param string $service The name of the service
   * @return bool
   */
  public function startService(string $service): bool
  {
    if ($this->webmin && $this->webmin->startService($service)) {
      return $this->collectInfo();
    }
    return false;
  }


  /**
   * Stop a service
   * @param string $service The name of the service
   * @return bool
   */
  public function stopService(string $service): bool
  {
    if ($this->webmin && $this->webmin->stopService($service)) {
      return $this->collectInfo();
    }
    return false;
  }


  /**
   * Restart a service
   * @param string $service The name of the service
   * @return bool
   */
  public function restartService(string $service): bool
  {
    if ($this->webmin && $this->webmin->restartService($service)) {
      return $this->collectInfo();
    }
    return false;
  }


  /**
   * Re-collect server info
   * @return bool
   */
  public function collectInfo(): bool
  {
    return $this->virtualmin ? (bool)$this->virtualmin->collectInfo() : false;
  }


  /**
   * Enables/Disables a domain
   *
   * @param string $domain
   * @param bool $state
   * @return bool
   */
  public function setDomainState(string $domain, bool $state = true): bool
  {
    if (
        (!empty($state)
          && $this->virtualmin->enable_domain(['domain' => $domain]))
        || (empty($state)
          && $this->virtualmin->disable_domain(['domain' => $domain])
        )
    ) {
      $domains = $this->getCache('domains');
      $this->makeCache('', $domain);
      if (
          !\is_null($idx = X::find($domains, ['name' => $domain]))
          && !empty($domains[$idx]['parent_domain'])
      ) {
        $this->makeCache('subdomains', $domains[$idx]['parent_domain']);
      }
      self::makeGlobalDomainsCache();
      return true;
    }
    return false;
  }


  /**
   * Creates a new domain
   * @param array $domainData
   * @return bool
   */
  public function createDomain(array $domainData): bool
  {
    if (empty($domainData['name'])) {
      throw new \Error(_('The "name" property is mandatory'));
    }
    if (empty($domainData['type'])) {
      throw new \Error(_('The "type" property is mandatory'));
    }
    $args = [
      'domain' => $domainData['name']
    ];
    if (!empty($domainData['description'])) {
      $args['desc'] = $domainData['description'];
    }

    switch ($domainData['type']) {
      case 'top':
        if (empty($domainData['password'])) {
          throw new \Error(_('The "password" property is mandatory'));
        }
        $args['pass'] = $domainData['password'];
        $features = $this->getCache('features_template');
        break;
      case 'sub':
        if (empty($domainData['parent'])) {
          throw new \Error(_('The "parent" property is mandatory'));
        }
        $args['parent'] = $domainData['parent'];
        $features       = $this->getCache('features_template', false, $domainData['parent']);
        break;
      case 'alias':
        if (empty($domainData['parent'])) {
          throw new \Error(_('The "parent" property is mandatory'));
        }
        $args['alias'] = $domainData['parent'];
        $features      = $this->getCache('features_template_alias', false, $domainData['parent']);
        break;
    }

    if (!empty($features)) {
      foreach ($features as $f) {
        if (
            (strtolower($f['automatic']) === 'yes')
            && (strtolower($f['enabled']) === 'yes')
            && (strtolower($f['default']) === 'yes')
        ) {
          $args[$f['name']] = 1;
        }
      }

      if (!empty($domainData['features'])) {
        $args = X::mergeArrays(
            $args,
            \array_filter($domainData['features'], function ($v, $k) use ($features) {
              $f = X::getRow($features, ['name' => $k]);
              return !empty($v)
                && !empty($f)
                && (strtolower($f['enabled']) === 'yes');
            }, ARRAY_FILTER_USE_BOTH)
        );
      }
    }

    if ($this->virtualmin->create_domain($args)) {
      $this->makeCache('', $domainData['name']);
      if (!empty($domainData['parent'])) {
        $this->makeCache('subdomains', $domainData['parent']);
      }
      self::makeGlobalDomainsCache();
      return true;
    }
    return false;
  }


  /**
   * Edites a domain
   * @param array $domainData
   * @return bool
   */
  public function editDomain(array $domainData): bool
  {
    if (empty($domainData['name'])) {
      throw new \Error(_('The "name" property is mandatory'));
    }

    if (empty($domainData['type'])) {
      throw new \Error(_('The "type" property is mandatory'));
    }

    if (!($domains = $this->getCache('domains'))) {
      throw new \Error(_('No domains cache found'));
    }

    if (!($oldDomain = X::getRow($domains, ['name' => $domainData['name']]))) {
      throw new \Error(_('Domain not found into cache'));
    }

    $args = ['domain' => $domainData['name']];
    if (
        isset($domainData['description'])
        && ($domainData['description'] !== $oldDomain['description'])
    ) {
      $args['desc'] = $domainData['description'];
    }

    switch ($domainData['type']) {
      case 'top':
        if (!empty($domainData['password'])) {
          $args['pass'] = $domainData['password'];
        }
        $features = $this->getCache('features_template');
        break;
      case 'sub':
        $features = $this->getCache('features_template', false, $domainData['parent']);
        break;
      case 'alias':
        $features = $this->getCache('features_template_alias', false, $domainData['parent']);
        break;
    }

    if (
        isset($domainData['serverQuota'])
        && ($domainData['serverQuota'] !== $oldDomain['server_block_quota'])
        && !(($domainData['serverQuota'] === 0)
          && ($oldDomain['server_block_quota'] === 'Unlimited'))
    ) {
      $args['quota'] = $domainData['serverQuota'] === 0 ? 'UNLIMITED' : ($domainData['serverQuota'] / 1024);
    }

    if (
        isset($domainData['userQuota'])
        && ($domainData['userQuota'] !== $oldDomain['user_block_quota'])
        && !(($domainData['userQuota'] === 0)
          && ($oldDomain['user_block_quota'] === 'Unlimited'))
    ) {
      $args['uquota'] = $domainData['userQuota'] === 0 ? 'UNLIMITED' : ($domainData['userQuota'] / 1024);
    }

    if (
        (count($args) > 1)
        && !$this->virtualmin->modify_domain($args)
    ) {
      $this->lastError = $this->virtualmin->error;
      return false;
    }

    // Features
    if (!empty($features) && !empty($domainData['features'])) {
      $args              = ['domain' => $domainData['name']];
      $oldFeatures       = explode(' ', $oldDomain['features']);
      $featuresToEnable  = \array_filter($domainData['features'], function ($v, $k) use ($features, $oldFeatures) {
        $f = X::getRow($features, ['name' => $k]);
        return !empty($v)
          && !empty($f)
          && (strtolower($f['enabled']) === 'yes')
          && !\in_array($k, $oldFeatures);
      }, ARRAY_FILTER_USE_BOTH);
      $featuresToDisable = \array_map(function ($f) {
        return 1;
      }, \array_filter($domainData['features'], function ($v, $k) use ($features, $oldFeatures) {
        $f = X::getRow($features, ['name' => $k]);
        return empty($v)
          && !empty($f)
          && (strtolower($f['enabled']) === 'yes')
          && \in_array($k, $oldFeatures);
      }, ARRAY_FILTER_USE_BOTH));

      if (
          !empty($featuresToEnable)
          && !$this->virtualmin->enable_feature(X::mergeArrays($featuresToEnable, $args))
      ) {
        $this->lastError = $this->virtualmin->error;
        return false;
      }

      if (
          !empty($featuresToDisable)
          && !$this->virtualmin->disable_feature(X::mergeArrays($featuresToDisable, $args))
      ) {
        $this->lastError = $this->virtualmin->error;
        return false;
      }
    }
    $this->makeCache('', $domainData['name']);
    if (!empty($oldDomain['parent_domain'])) {
      $this->makeCache('subdomains', $oldDomain['parent_domain']);
    }
    self::makeGlobalDomainsCache();
    return true;
  }

  /**
   * Deletes a domain
   * @param string $domain
   * @return bool
   */
  public function deleteDomain(string $domain): bool
  {
    if (
        ($domains = $this->getCache('domains'))
        && $this->virtualmin->delete_domain(['domain' => $domain])
    ) {
      if (!\is_null($idx = X::find($domains, ['name' => $domain]))) {
        if (!empty($domains[$idx]['parent_domain'])) {
          $this->makeCache('subdomains', $domains[$idx]['parent_domain']);
        }
        array_splice($domains, $idx, 1);
        $this->cacheSet(
            $this->cacheNamePrefix . 'domains',
            '',
            $domains,
            0
        );
        $this->cacheDelete($this->cacheNamePrefix . "domains/$domain");
        self::makeGlobalDomainsCache();
      }
      return true;
    }
    else {
      $this->lastError = $this->virtualmin->error;
    }
    return false;
  }


  /**
   * Renames a domain
   *
   * @param string $domain
   * @param string $newDomain
   * @return bool
   */
  public function renameDomain(string $domain, string $newDomain): bool
  {
    if (
        $this->virtualmin->unsetJson()->rename_domain([
          'domain' => $domain,
          'new-domain' => $newDomain,
          'auto-user' => 1,
          'auto-home' => 1,
          'auto-prefix' => 1
        ])
    ) {
      $this->virtualmin->setJson();
      $this->makeCache('', $newDomain);
      $domains = $this->getCache('domains');
      if (!\is_null($idx = X::find($domains, ['name' => $domain]))) {
        if (!empty($domains[$idx]['parent_domain'])) {
          $this->makeCache('subdomains', $domains[$idx]['parent_domain']);
        }
        array_splice($domains, $idx, 1);
        $this->cacheSet(
            $this->cacheNamePrefix . 'domains',
            '',
            $domains,
            0
        );
        $this->cacheDelete($this->cacheNamePrefix . "domains/$domain");
      }
      self::makeGlobalDomainsCache();
      return true;
    }
    else {
      $this->lastError = $this->virtualmin->error;
    }
    return false;
  }


  /**
   * Clones a domain
   *
   * @param string $domain
   * @param string $newDomain
   * @return bool
   */
  public function cloneDomain(string $domain, string $newDomain): bool
  {
    if (
        $this->virtualmin->clone_domain([
          'domain' => $domain,
          'newdomain' => $newDomain
        ])
    ) {
      $this->makeCache('', $newDomain);
      $domains = $this->getCache('domains');
      if (
          !\is_null($idx = X::find($domains, ['name' => $domain]))
          && !empty($domains[$idx]['parent_domain'])
      ) {
        $this->makeCache('subdomains', $domains[$idx]['parent_domain']);
      }
      self::makeGlobalDomainsCache();
      return true;
    }
    else {
      $this->lastError = $this->virtualmin->error;
    }
    return false;
  }


  /**
   * Adds the given action to the queue
   * @param string $method
   * @param array $args
   * @return int!false
   */
  public function addToTasksQueue(string $method, array $args = [])
  {
    $user = bbn\User::getInstance();
    if (
        !$this->checkQueueTaskHash($method, $args)
        && $this->db->insert('queue', [
          'server' => $this->hostname,
          'hash' => $this->getQueueTaskHash($method, $args),
          'method' => $method,
          'args' => \json_encode($args),
          'user' => $user->getId() ?: null
        ])
    ) {
      return $this->db->lastId();
    }
    return false;
  }


  /**
   * Removes a taks from the queue
   * @param int $id The task ID
   * @return bool
   */
  public function removeFromTasksQueue(int $id): bool
  {
    return (bool)$this->db->update('queue', ['active' => 0], ['id' => $id]);
  }


  /**
   * Sets the start field of a task element on the queue
   * @param int $id The task ID
   * @param string $date The date to set
   * @return bool
   */
  public function setQueueTaskStart(int $id, ?string $date = null): bool
  {
    return (bool)$this->db->update('queue', ['start' => $date ?: date('Y-m-d H:i:s')], ['id' => $id]);
  }


  /**
   * Sets the end field of a task element on the queue
   * @param int $id The task ID
   * @param string $date The date to set
   * @return bool
   */
  public function setQueueTaskEnd(int $id, ?string $date = null): bool
  {
    return (bool)$this->db->update('queue', ['end' => $date ?: date('Y-m-d H:i:s')], ['id' => $id]);
  }


  /**
   * Sets a task element on the queue as failed
   * @param int $id The task ID
   * @param string $error The error message
   * @return bool
   */
  public function setQueueTaskFailed(int $id, string $error = ''): bool
  {
    return (bool)$this->db->update('queue', [
      'failed' => 1
    ], [
      'id' => $id,
      'error' => $error
    ]);
  }


  /**
   * Gets the value of the 'lastError' property
   * @return string
   */
  public function getLastError(): string
  {
    return $this->lastError;
  }


  /**
   * Makes global domains cache
   *
   * @return array
   */
  public static function makeGlobalDomainsCache(): array
  {
    $cache   = Cache::getEngine();
    $domains = [];
    $servers = self::getOptions('servers');
    if (!empty($servers)) {
      foreach ($servers as $server) {
        if (
            !empty($server['code'])
            && ($serverDomains = $cache->get(self::CACHE_NAME . '/' . $server['code'] . '/domains'))
        ) {
          foreach ($serverDomains as $sd) {
            $backups              = $cache->get(self::CACHE_NAME . '/' . $server['code'] . '/domains/' . $sd['name'] . '/backups') ?: [];
            $domains[$sd['name']] = X::mergeArrays($sd, [
              'hostname' => $server['code'],
              'admins' => $cache->get(self::CACHE_NAME . '/' . $server['code'] . '/domains/' . $sd['name'] . '/admins') ?: [],
              'users' => $cache->get(self::CACHE_NAME . '/' . $server['code'] . '/domains/' . $sd['name'] . '/users') ?: [],
              'dns' => $cache->get(self::CACHE_NAME . '/' . $server['code'] . '/domains/' . $sd['name'] . '/dns') ?: [],
              'backups_succeeded' =>  !empty($backups) ? $backups['succeeded'] : [],
              'backups_failed' =>  !empty($backups) ? $backups['failed'] : []
            ]);
          }
        }
      }
      ksort($domains);
      $domains = \array_values($domains);
    }
    $cache->set(self::CACHE_NAME . '/domains', $domains);
    return $domains;
  }


  /**
   * Gets the data path of the appui-server plugin
   * @return string
   */
  public static function getMainDataPath(): string
  {
    return bbn\Mvc::getDataPath('appui-server');
  }

  /**
   * Returns an instance of bbn\Db of the tasks queue database
   * @return bbn\Db|null
   */
  public static function getDb(): ?bbn\Db
  {
    if ($dbPath = self::makeDb()) {
      return new bbn\Db([
        'engine' => 'sqlite',
        'db' => $dbPath
      ]);
    }
    return null;
  }


  /**
   * Process the tasks queue
   * @return void
   */
  public static function processTasksQueue()
  {
    $appPath = bbn\Mvc::getAppPath();
    $db = self::getDb();
    if (!empty($db) && is_dir($appPath)) {
      if ($queue = self::getCurrentTasksQueue(true)) {
        foreach ($queue as $q) {
          if (!empty($q['server'])) {
            $running = self::getRunningTasks();
            if (empty($running)
              || (bbn\X::find($running, ['server' => $q['server']]) === null)
            ) {
              exec(sprintf('php -f %srouter.php %s "%s"',
                $appPath,
                bbn\Mvc::getPluginUrl('appui-server') . '/action',
                bbn\Str::escapeDquotes(json_encode($q))
              ));
            }
          }
        }
      }
    }
  }


  /**
   * Gets the current tasks queueu
   * @return array
   */
  public static function getCurrentTasksQueue(bool $group = false): array
  {
    if ($db = self::getDb()) {
      return $db->rselectAll([
        'table' => 'queue',
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'failed',
            'value' => 0
          ], [
            'field' => 'start',
            'operator' => 'isnull'
          ], [
            'field' => 'end',
            'operator' => 'isnull'
          ]]
        ],
        'group_by' => !empty($group) ? ['server'] : [],
        'order' => ['id' => 'asc']
      ]);
    }
    return [];
  }


  /**
   * Gets the running tasks
   * @return array
   */
  public static function getRunningTasks(): array
  {
    if ($db = self::getDb()) {
      return $db->rselectAll([
        'table' => 'queue',
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'failed',
            'value' => 0
          ], [
            'field' => 'start',
            'operator' => 'isnotnull'
          ], [
            'field' => 'end',
            'operator' => 'isnull'
          ]]
        ],
        'order' => ['id' => 'asc']
      ]);
    }
    return [];
  }


  /**
   * Checks if the hash is already present on the queue
   * @param string $method
   * @param array $args
   * @return bool
   */
  private function checkQueueTaskHash(string $method, array $args = [])
  {
    $hash = $this->getQueueTaskHash($method, $args);
    return (bool)$this->db->selectOne([
      'table' => 'queue',
      'fields' => ['id'],
      'where' => [
        'conditions' => [[
          'field' => 'server',
          'value' => $this->hostname
        ], [
          'field' => 'hash',
          'value' => $hash
        ], [
          'field' => 'active',
          'value' => 1
        ], [
          'field' => 'end',
          'operator' => 'isnull'
        ]]
      ]
    ]);
  }


  /**
   * Gets the queue hash
   * @param string $method
   * @param array $args
   * @return string
   */
  private function getQueueTaskHash(string $method, array $args = []): string
  {
    return \md5($method . \json_encode($args));
  }


  /**
   * Normalizes the data structure
   * @param array $data
   * @return array
   */
  private function normalizeData(array $data): array
  {
    return \array_map(function ($f) {
      return X::mergeArrays(
          ['name' => $f['name']],
          \array_map(function ($v) {
            if (\is_array($v) && (count($v) === 1)) {
              $v = $v[0];
            }
            return $v;
          }, $f['values'])
      );
    }, \array_filter($data, function ($f) {
      return !empty($f['values']);
    }));
  }


  /**
   * Cache the info
   * @param array $search
   * @return void
   */
  private function cacheInfo(array $search = [])
  {
    if ($info = $this->virtualmin->info($search)) {
      foreach ($info as $k => $v) {
        $this->cacheSet($this->cacheNamePrefix . $k, '', $v, 0);
      }
    }
  }


  /**
   * Cache the server uptime
   * @return void
   */
  private function cacheUptime()
  {
    if ($uptime = $this->webmin->getSystemUptime()) {
      $this->cacheSet($this->cacheNamePrefix . 'uptime', '', $uptime, 0);
    }
  }


  /**
   * Cache the list of domains
   * @param string $domain
   * @return void
   */
  private function cacheListDomains(string $domain = '')
  {
    $domains = $this->virtualmin->list_domains(!empty($domain) ? ['domain' => $domain] : []) ?: [];
    $domains = $this->normalizeData($domains);
    if (!empty($domain)) {
      $dom     = !empty($domains) ? $domains[0] : [];
      $domains = $this->getCache('domains');
      if (!\is_null($idx = X::find($domains, ['name' => $domain]))) {
        $domains[$idx] = $dom;
      }
      else {
        $domains[] = $dom;
        $domains   = X::sortBy($domains, 'name', 'asc');
      }
    }
    $this->cacheSet(
        $this->cacheNamePrefix . 'domains',
        '',
        $domains ?: [],
        0
    );
  }


  /**
   * @param string $name
   * @param callable $func
   * @param string $domain
   */
  private function cacheList(string $name, callable $func, string $domain = '')
  {
    if (!empty($domain)) {
      $this->cacheSet(
          $this->cacheNamePrefix . "domains/$domain/$name",
          '',
          $func($domain),
          0
      );
    }
    else {
      if ($domains = $this->getCache('domains')) {
        foreach ($domains as $d) {
          $this->cacheSet(
              $this->cacheNamePrefix . "domains/$d[name]/$name",
              '',
              $func($d['name']),
              0
          );
        }
      }
    }
  }


  /**
   * Cache the list of sub domains
   * @param string $domain
   * @return void
   */
  private function cacheListSubDomains(string $domain = '')
  {
    $allDomains = $this->getCache('domains') ?: [];
    $domains    = [];
    foreach ($allDomains as $d) {
      if (!empty($d['parent_domain'])) {
        if (!isset($domains[$d['parent_domain']])) {
          $domains[$d['parent_domain']] = [];
        }

        $domains[$d['parent_domain']][] = $d;
      }
    }

    $getDomains = function ($d) use ($domains) {
      return $domains[$d] ?? [];
    };
    $this->cacheList('subdomains', $getDomains, $domain);
  }


  /**
   * Cache the list of admins
   * @param string $domain
   * @return void
   */
  private function cacheListAdmins(string $domain = '')
  {
    $vm        = $this->virtualmin;
    $getAdmins = function ($d) use ($vm) {
      return $vm->list_admins(['domain' => $d]) ?: [];
    };
    $this->cacheList('admins', $getAdmins, $domain);
  }


  /**
   * Cache the list of users
   * @param string $domain
   * @return void
   */
  private function cacheListUsers(string $domain = '')
  {
    $users = $this->normalizeData($this->virtualmin->list_users([
      'domain' => '',
      'all-domains' => 1,
      'domain-user' => '',
      'include-owner' => 1
    ]) ?: []);
    $getUsers = function ($d) use ($users) {
      $ret = [];
      foreach ($users as $user) {
        if (
            (\is_array($user['domain']) && \in_array($d, $user['domain'], true))
            || ($user['domain'] === $d)
        ) {
          $ret[] = $user;
        }
      }
      return $ret;
    };
    $this->cacheList('users', $getUsers, $domain);
  }


  /**
   * Cache the list of DNS
   * @param string $domain
   * @return void
   */
  private function cacheListDns(string $domain = '')
  {
    $domains   = $this->getCache('domains') ?: [];
    $t         = $this;
    $filterDns = function ($dom) use ($domains, $t) {
      $rexp = '/^([^\.]*\.{1}|^)[^\.]*' . str_replace('.', '\.{1}', $dom) . '\.{1}/m';
      $dns  = $t->normalizeData($t->virtualmin->get_dns(['domain' => $dom]) ?: []);
      foreach ($dns as $i => $d) {
        \preg_match_all($rexp, $d['name'], $found);
        if (
            (empty($found) || empty($found[0]))
            || (($d['name'] !== $dom . '.')
                && (\substr_count($d['name'], '.') > 2)
                && (!\is_null(X::find($domains, ['name' => substr($d['name'], 0, -1)]))))
        ) {
          unset($dns[$i]);
        }
      }
      return \array_values($dns);
    };
    $this->cacheList('dns', $filterDns, $domain);
  }


  /**
   * Cache the list of backups
   * @param string $domain
   * @return void
   */
  private function cacheListBackups(string $domain = '')
  {
    $t             = $this;
    $filterBackups = function ($dom) use ($t) {
      $backups = $t->normalizeData($t->virtualmin->list_backup_logs(['domain' => $dom]) ?: []);
      $ok      = [];
      $failed  = [];
      foreach ($backups as $backup) {
        $d = \date('Y-m-d H:i:s', \strtotime(\str_replace('/', ' ', $backup['started'])));
        if (\strtolower($backup['final_status']) === 'ok') {
          $ok[$d] = $backup;
        }
        else {
          $failed[$d] = $backup;
        }
      }
      \rsort($ok);
      \rsort($failed);
      $ok     = \array_values($ok);
      $failed = \array_values($failed);
      return [
        'succeeded' => \array_splice($ok, 0, 5),
        'failed' => \array_splice($failed, 0, 5)
      ];
    };
    $this->cacheList('backups', $filterBackups, $domain);
  }


  /**
   * Cache the list of features
   * @param string $domain
   * @return void
   */
  private function cacheListFeaturesTemplate(string $domain = '')
  {
    $t            = $this;
    $listFeatures = function ($dom = '') use ($t) {
      $args = [];
      if (!empty($dom)) {
        $args  = ['alias' => $dom];
        $alias = $t->normalizeData($t->virtualmin->list_features($args) ?: []);
        $t->cacheSet(
            $t->cacheNamePrefix . "domains/$dom/features_template_alias",
            '',
            $alias,
            0
        );
        $args = ['parent' => $dom];
      }
      return $t->normalizeData($t->virtualmin->list_features($args) ?: []);
    };
    $this->cacheList('features_template', $listFeatures, $domain);
    if (empty($domain)) {
      $this->cacheSet(
          $this->cacheNamePrefix . 'features_template',
          '',
          $listFeatures(),
          0
      );
    }
  }


  /**
   * Makes the SQLite database and returns its path
   * @return null|string
   */
  private static function makeDb(): ?string
  {
    if ($mainDataPath = self::getMainDataPath()) {
      $path = $mainDataPath . 'servers.sqlite';
      if (!\is_file($path) && bbn\File\Dir::createPath($mainDataPath)) {
        $db = new SQLite3($path);
        $db->exec("CREATE TABLE queue (
          id INTEGER PRIMARY KEY,
          server VARCHAR (150) NOT NULL,
          created DATETIME NOT NULL DEFAULT (CURRENT_TIMESTAMP),
          user VARCHAR (32),
          method VARCHAR (100) NOT NULL,
          args TEXT,
          hash TEXT NOT NULL,
          start DATETIME,
          [end] DATETIME,
          failed INTEGER (1) DEFAULT (0),
          error TEXT,
          active INTEGER (1) DEFAULT (1)
        );");
      }
      return \is_file($path) ? $path : null;
    }
    return null;
  }


}
