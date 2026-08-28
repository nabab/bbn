<?php

declare(strict_types=1);

namespace bbn\User;

/**
 * Mutable runtime state shared by the small user components.
 *
 * Database configuration remains on bbn\User because DbOps expects it there;
 * this object only contains authentication/session runtime state.
 */
final class State
{
  public bool $auth = false;
  public bool $justLogin = false;
  public bool $passwordReset = false;
  public bool $fake = false;

  public ?string $id = null;
  public ?string $idGroup = null;
  public ?string $userAgent = null;
  public ?string $ipAddress = null;
  public ?string $acceptLanguage = null;
  public ?string $cachePath = null;
  public ?string $path = null;
  public ?string $tmpPath = null;
  public ?string $encryptionKey = null;

  /** @var array<string, mixed> */
  public array $data = [];

  /** @var array<string, mixed> */
  public array $cfg = [];

  /** @var array<string, mixed> */
  public array $sessionCfg = [];

  /** @var array<string, mixed>|null */
  public ?array $group = null;
}
