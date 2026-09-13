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

  public function setSession($attr): static;

  public function unsetSession(): static;

  public function getSession($attr = null);

  public function getOsession($attr = null);

  public function setOsession(): static;

  public function hasSession($attr): bool;

  public function updateActivity(): static;

  public function saveSession(bool $force = false): static;

  public function closeSession($with_session = false): static;

  public function checkAttempts(): bool;

  public function saveCfg(): static;

  public function setCfg($attr): static;

  public function unsetCfg($attr): static;

  public function refreshInfo(): static;

  public function checkSession(): bool;

  public function isAdmin(): bool;

  public function isDev(): bool;

  public function getManager();

  public function setPassword(string $old_pass, string $new_pass): bool;

  public function addToken(): ?string;

  public function getName($usr = null): ?string;

  public function setData($index, $data = null): static;

  public function updateInfo(array $d): bool;


}