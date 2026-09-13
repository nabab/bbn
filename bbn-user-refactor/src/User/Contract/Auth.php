<?php

declare(strict_types=1);

namespace bbn\User\Contract;

interface Auth
{
  public function isAuth(): bool;
  public function isJustLogin(): bool;
  public function checkSession(): bool;
  public function logout(): void;
}
