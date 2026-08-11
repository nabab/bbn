<?php

namespace bbn\User;

use bbn\X;
use bbn\User;
use bbn\Cache;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbOps;


class Attempt extends DbCls
{
  use DbOps;

  protected Cache $cache;
  protected array $cfg;
  protected bool $checked = false;
  protected string $id_session_hash;

  const MAX_EMPTY_ATTEMPTS = 5;
  const IP_BAN_DURATION = 300; // 5 minutes
  const ACCOUNT_BAN_DURATION = 900; // 15 minutes
  const SESSION_BAN_DURATION = 1800; // 30 minutes

  protected static $default_class_cfg = [
    'table' => 'bbn_user_attempts',
    'tables' => [
      'attempts' => 'bbn_user_attempts'
    ],
    'arch' => [
      "attempts" => [
        "id" => "id",
        "id_user" => "id_user",
        "account_key" => "account_key",
        "attempted_at" => "attempted_at",
        "ip_address" => "ip_address",
        "user_agent" => "user_agent",
        "id_session" => "id_session",
        "id_session_hash" => "id_session_hash",
        "result" => "result",
      ],
    ]
  ];

  public function __construct(Db $db, array $cfg)
  {
    parent::__construct($db);
    $this->cache = Cache::getEngine();
    if (X::hasProps($cfg, ['account_key', 'ip_address', 'id_session'], true)) {
      $this->id_session_hash = md5($cfg['id_session']);
      $this->cfg = $cfg;
      if (!$this->isIpBanned() && !$this->isAccountBanned() && !$this->isSessionBanned()) {
        $this->checked = true;
        $this->initClassCfg();
      }
    }
  }

  private function isIpBanned(): bool
  {
    $sep = Cache::getSeparator();
    $name = X::join(['ban', 'ip_address', $this->cfg['ip_address']], $sep);
    $ipBanned = $this->cache->get($name);
    if ($ipBanned) {
      $this->cache->set($name, ++$ipBanned, self::IP_BAN_DURATION);
      return true;
    }
    return false;
  }

  private function isAccountBanned(): bool
  {
    $sep = Cache::getSeparator();
    $name = X::join(['ban', 'account_key', $this->cfg['account_key']], $sep);
    $accountBanned = $this->cache->get($name);
    if ($accountBanned) {
      $this->cache->set($name, ++$accountBanned, self::ACCOUNT_BAN_DURATION);
      return true;
    }
    return false;
  }

  private function isSessionBanned(): bool
  {
    $sep = Cache::getSeparator();
    $name = X::join(['ban', 'id_session', $this->cfg['id_session_hash']], $sep);
    $sessionBanned = $this->cache->get($name);
    if ($sessionBanned) {
      $this->cache->set($name, ++$sessionBanned, self::SESSION_BAN_DURATION);
      return true;
    }
    return false;
  }

  public function check(): bool
  {
    return $this->checked;
  }

  public function add(): bool
  {
    if ($this->checked) {
      return (bool)$this->dbTraitInsert([
        'id_user' => $this->cfg['id_user'] ?? null,
        'account_key' => $this->cfg['account_key'],
        'attempted_at' => date('Y-m-d H:i:s'),
        'ip_address' => inet_pton($this->cfg['ip_address']),
        'user_agent' => $this->cfg['user_agent'] ?? null,
        'id_session' => $this->cfg['id_session'],
        'id_session_hash' => $this->id_session_hash,
        'result' => $this->cfg['result'] ?? null
      ]);
    }

    return false;
  }


}