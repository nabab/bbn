<?php

namespace bbn\User;

use bbn\User\Manager;

interface Implementor
{
  public function isReset(): bool;

  public function getSalt(): ?string;

  public function checkSalt(string $salt): bool;

  public function getCfg($attr = '');

  public function isJustLogin(): bool;

  public function getPassword(string $st): string;

  public function setSession($attr): self;

  public function unsetSession(): self;

  public function getSession($attr = null);

  public function getOsession($attr = null);

  public function setOsession(): self;

  public function hasSession($attr): bool;

  public function updateActivity(): self;

  public function saveSession(bool $force = false): self;

  public function closeSession($with_session = false): self;

  public function checkAttempts(): bool;

  public function saveCfg(): self;

  public function setCfg($attr): self;

  public function unsetCfg($attr): self;

  public function refreshInfo(): self;

  public function checkSession(): bool;

  public function isAdmin(): bool;

  public function isDev(): bool;

  public function getManager(): Manager;

  public function setPassword(string $old_pass, string $new_pass): bool;

  public function addToken(): ?string;

  public function getName($usr = null): ?string;

  public function setData($index, $data = null): self;

  public function updateInfo(array $d): bool;


}