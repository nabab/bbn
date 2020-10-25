<?php

namespace bbn\appui;

use bbn;
use bbn\util\jwt;
use bbn\x;


class api extends bbn\models\cls\basic
{

  public const REMOTE = 'https://server.thomas.lan/api/home';

  public $jwt;

  /**
   * Constructor
   *
   */
  public function __construct(bbn\user $user, int $ttl = 300)
  {
    if ($user->get_id()) {
      $this->jwt = new jwt();
      $this->jwt->prepare($user->get_id(), $user->get_osession('fingerprint'), $ttl);
    }
  }

  public function register(array $cfg, string $key): ?string
  {
    if ($this->jwt && x::has_prop($cfg, 'data', true)) {
      $jwt = $this->jwt->set_key($key)->set($cfg);
      return x::curl(self::REMOTE, ['action' => 'register', 'data' => $jwt]);
    }
    return null;
  }




}