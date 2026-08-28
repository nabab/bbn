<?php

declare(strict_types=1);

namespace bbn\User;

use bbn\Db;
use bbn\X;
use Exception;

/**
 * Owns access to PHP session data and persistence in bbn_users_sessions.
 * The low-level bbn\User\Session class remains unchanged and reusable.
 */
final class Sessions extends Component
{
  public const USER_INDEX = 'bbn_user';
  public const SESSION_INDEX = 'bbn_session';

  private ?Session $session = null;

  /** @param array<string, mixed> $defaults */
  public function boot(array $defaults = []): void
  {
    $this->session = Session::getInstance();
    if (!$this->session) {
      $class = defined('BBN_SESSION')
        && is_string(constant('BBN_SESSION'))
        && class_exists(constant('BBN_SESSION'))
          ? constant('BBN_SESSION')
          : Session::class;
      $this->session = new $class($defaults);
    }
  }

  public function instance(): Session
  {
    if (!$this->session) {
      throw new Exception('The user session has not been booted');
    }

    return $this->session;
  }

  public function get(?string $key = null): mixed
  {
    $session = $this->instance();
    if (!$session->has(self::USER_INDEX)) {
      return null;
    }

    return $key === null
      ? $session->get(self::USER_INDEX)
      : $session->get(self::USER_INDEX, $key);
  }

  public function set(array|string $key, mixed $value = null): static
  {
    $session = $this->instance();
    if (!$session->has(self::USER_INDEX)) {
      return $this;
    }

    $values = is_array($key) ? $key : [$key => $value];
    foreach ($values as $name => $item) {
      if (is_string($name)) {
        $session->set($item, self::USER_INDEX, $name);
      }
    }

    return $this;
  }

  public function remove(string ...$keys): static
  {
    $args = [self::USER_INDEX, ...$keys];
    if ($this->instance()->has(...$args)) {
      $this->instance()->uset(...$args);
    }

    return $this;
  }

  public function getMeta(?string $key = null): mixed
  {
    $session = $this->instance();
    if (!$session->has(self::SESSION_INDEX)) {
      return null;
    }

    return $key === null
      ? $session->get(self::SESSION_INDEX)
      : $session->get(self::SESSION_INDEX, $key);
  }

  public function setMeta(array|string $key, mixed $value = null): static
  {
    $values = is_array($key) ? $key : [$key => $value];
    foreach ($values as $name => $item) {
      if (is_string($name)) {
        $this->instance()->set($item, self::SESSION_INDEX, $name);
      }
    }

    return $this;
  }

  public function has(string $key): bool
  {
    return $this->instance()->has(self::USER_INDEX, $key);
  }

  public function salt(): ?string
  {
    $salt = $this->getMeta('salt');
    return is_string($salt) ? $salt : null;
  }

  public function checkSalt(string $salt): bool
  {
    return hash_equals((string)$this->salt(), $salt);
  }

  public function databaseId(): ?string
  {
    $id = $this->getMeta('id_session');
    return is_string($id) ? $id : null;
  }

  public function destroy(bool $all = false): void
  {
    if ($all) {
      $this->instance()->set([]);
    }
    else {
      $this->instance()->set([], self::USER_INDEX);
    }

    $this->state->auth = false;
    $this->state->id = null;
    $this->state->sessionCfg = [];
  }

  public function destruct(): void
  {
    $this->session?->destruct();
    $this->session = null;
  }
}
