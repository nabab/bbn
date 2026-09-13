<?php

declare(strict_types=1);

namespace bbn;

use AllowDynamicProperties;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbOps;
use bbn\Models\Tts\Retriever;
use bbn\User\Auth;
use bbn\User\Caches;
use bbn\User\Implementor;
use bbn\User\Locales;
use bbn\User\Passwords;
use bbn\User\Profile;
use bbn\User\Sessions;
use bbn\User\State;
use bbn\User\Tokens;
use Exception;

/**
 * Public facade for the user package.
 *
 * This class intentionally stays bbn\User. New implementation work belongs
 * in the short, focused classes under bbn\User\*.
 */
#[AllowDynamicProperties]
class User extends DbCls implements Implementor
{
  use Retriever;
  use DbOps;

  /** Keep the complete legacy configuration here unchanged. */
  protected static $default_class_cfg = [];

  private State $state;
  private Sessions $sessions;
  private Passwords $passwords;
  private Profile $profile;
  private Tokens $tokens;
  private Caches $caches;
  private Locales $locales;
  private Auth $authentication;

  public function __construct(Db $db, array $params = [])
  {
    $this->initClassCfg();
    parent::__construct($db);
    self::retrieverInit($this);

    $this->state = new State();
    $this->sessions = new Sessions($db, $this->state, $this->class_cfg, $this->fields);
    $this->passwords = new Passwords($db, $this->state, $this->class_cfg, $this->fields);
    $this->profile = new Profile($db, $this->state, $this->class_cfg, $this->fields);
    $this->tokens = new Tokens($db, $this->state, $this->class_cfg, $this->fields);
    $this->caches = new Caches($db, $this->state, $this->class_cfg, $this->fields);
    $this->locales = new Locales($db, $this->state, $this->class_cfg, $this->fields);
    $this->authentication = new Auth(
      $db,
      $this->state,
      $this->class_cfg,
      $this->fields,
      $this->sessions,
      $this->passwords,
      $this->profile,
    );

    $this->sessions->boot();
    $this->boot($params);
  }

  /** @param array<string, mixed> $params */
  private function boot(array $params): void
  {
    $this->state->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? (isset($_SERVER['argv'][1]) ? 'CLI' : 'Unknown');
    $this->state->ipAddress = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    $this->state->acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ($_SERVER['LANG'] ?? '');

    if ($this->authentication->isLoginRequest($params)) {
      $this->authentication->login($params, fn(int $code) => $this->setError((string)$code));
      return;
    }

    $id = $this->sessions->get('id');
    if (is_string($id)) {
      $this->state->id = $id;
      $this->state->auth = true;
    }
  }

  public function destruct(): void
  {
    $this->sessions->destruct();
    self::retrieverRemove($this);
  }

  public function isReset(): bool { return $this->state->passwordReset; }
  public function getSalt(): ?string { return $this->sessions->salt(); }
  public function checkSalt(string $salt): bool { return $this->sessions->checkSalt($salt); }
  public function isJustLogin(): bool { return $this->state->justLogin; }
  public function getPassword(string $st): string { return $this->passwords->hash($st); }

  public function setSession($attr): static
  {
    $args = func_get_args();
    $this->sessions->set($attr, $args[1] ?? null);
    return $this;
  }

  public function unsetSession(): static
  {
    $this->sessions->remove(...array_map('strval', func_get_args()));
    return $this;
  }

  public function getSession($attr = null): mixed { return $this->sessions->get($attr); }
  public function getOsession($attr = null): mixed { return $this->sessions->getMeta($attr); }

  public function setOsession(): static
  {
    $args = func_get_args();
    $this->sessions->setMeta($args[0], $args[1] ?? null);
    return $this;
  }

  public function hasSession($attr): bool { return $this->sessions->has((string)$attr); }
  public function updateActivity(): static { return $this; /* move legacy DB write into Sessions */ }
  public function saveSession(bool $force = false): static { return $this; /* move legacy DB write into Sessions */ }

  public function closeSession($with_session = false): static
  {
    $this->sessions->destroy((bool)$with_session);
    return $this;
  }

  public function checkAttempts(): bool
  {
    return empty($this->state->cfg['num_attempts'])
      || $this->state->cfg['num_attempts'] <= ($this->class_cfg['max_attempts'] ?? 10);
  }

  public function getCfg($attr = ''): mixed
  {
    return $attr === '' ? $this->state->cfg : ($this->state->cfg[$attr] ?? null);
  }

  public function saveCfg(): static { return $this; /* Profile persistence */ }

  public function setCfg($attr): static
  {
    $args = func_get_args();
    $values = is_array($attr) ? $attr : [$attr => ($args[1] ?? null)];
    foreach ($values as $key => $value) {
      if (is_string($key)) {
        $this->state->cfg[$key] = $value;
      }
    }
    $this->sessions->set('cfg', $this->state->cfg);
    return $this;
  }

  public function unsetCfg($attr): static
  {
    foreach ((array)$attr as $key) {
      unset($this->state->cfg[$key]);
    }
    $this->sessions->set('cfg', $this->state->cfg);
    return $this;
  }

  public function refreshInfo(): static { return $this; /* Profile hydration */ }
  public function checkSession(): bool { return $this->state->auth; }
  public function isAdmin(): bool { return (bool)$this->sessions->get('admin'); }
  public function isDev(): bool { return $this->isAdmin() || (bool)$this->sessions->get('dev'); }
  public function getManager() { return new User\Manager($this); }
  public function setPassword(string $old_pass, string $new_pass): bool { return $this->passwords->change($old_pass, $new_pass); }
  public function forcePassword($pass): bool { return $this->passwords->force((string)$pass); }
  public function addToken(): ?string { return $this->tokens->add($this->sessions->databaseId()); }

  public function getName($usr = null): ?string
  {
    $usr ??= $this->sessions->get();
    $field = $this->class_cfg['show'] ?? 'username';
    return is_array($usr) && isset($usr[$field]) ? (string)$usr[$field] : null;
  }

  public function getEmail($usr = null): ?string
  {
    $usr ??= $this->sessions->get();
    $field = $this->fields['email'] ?? 'email';
    return is_array($usr) && isset($usr[$field]) ? (string)$usr[$field] : null;
  }

  public function setData($index, $data = null): static
  {
    if (!$this->state->auth) {
      throw new Exception(X::_('Impossible to store data on an unauthenticated user'));
    }

    $values = is_array($index) ? $index : [$index => $data];
    foreach ($values as $key => $value) {
      if ($value === null) {
        unset($this->state->data[$key]);
      }
      else {
        $this->state->data[$key] = $value;
      }
    }
    return $this;
  }

  public function updateInfo(array $d): bool { return false; /* Profile update */ }
  public function isAuth(): bool { return $this->state->auth; }
  public function getId(): ?string { return $this->check() ? $this->state->id : null; }
  public function getIdGroup(): ?string { return $this->check() ? $this->state->idGroup : null; }
  public function logout(): void { $this->authentication->logout(); }

  public function getCachePath(): ?string { return $this->caches->path(); }
  public function hasCache(string $path): bool { return $this->caches->has($path); }
  public function getCache(string $key, bool $raw = false): mixed { return $this->caches->get($key, $raw); }
  public function setCache(string $key, mixed $val, int $ttl = 0): bool { return $this->caches->set($key, $val, $ttl); }
  public function deleteCache(string $key): bool { return $this->caches->delete($key); }
  public function deleteAllCache(): bool { return $this->caches->clear(); }
  public function getLocaleDatabase(?string $idUser = null, bool $createIfNotExists = true): ?Db
  {
    return $this->locales->database($idUser, $createIfNotExists);
  }

  protected function setError(string $err, $code = null): static
  {
    return parent::setError($err, $code);
  }
}
