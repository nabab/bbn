<?php

declare(strict_types=1);

namespace bbn\User;

use bbn\X;

/** Authentication workflow coordinator. */
final class Auth extends Component
{
  public function __construct(
    \bbn\Db $db,
    State $state,
    array $cfg,
    array $fields,
    private Sessions $sessions,
    private Passwords $passwords,
    private Profile $profile,
  ) {
    parent::__construct($db, $state, $cfg, $fields);
  }

  /** @param array<string, mixed> $params */
  public function isLoginRequest(array $params): bool
  {
    $f = $this->cfg['fields'];
    return isset($params[$f['user']], $params[$f['pass']], $params[$f['salt']]);
  }

  /**
   * Checks credentials and updates shared state.
   * Error reporting deliberately remains in bbn\User for backward compatibility.
   *
   * @param array<string, mixed> $params
   * @param callable(int):void $error
   */
  public function login(array $params, callable $error): bool
  {
    $f = $this->cfg['fields'];
    if (!$this->sessions->checkSalt((string)($params[$f['salt']] ?? ''))) {
      $error(17);
      return false;
    }

    $arch = $this->cfg['arch'];
    $id = $this->db->selectOne(
      $this->cfg['tables']['users'],
      $arch['users']['id'],
      X::mergeArrays(
        $this->cfg['conditions'] ?? [],
        [$arch['users']['active'] => 1],
        [($arch['users']['login'] ?? $arch['users']['email']) => $params[$f['user']]],
      ),
    );

    if (!is_string($id)) {
      $error(6);
      return false;
    }

    $pass = $this->db->selectOne(
      $this->cfg['tables']['passwords'],
      $arch['passwords']['pass'],
      [$arch['passwords']['id_user'] => $id],
      [$arch['passwords']['added'] => 'DESC'],
    );

    if (!is_string($pass) || !$this->passwords->verify((string)$params[$f['pass']], $pass)) {
      $error(6);
      return false;
    }

    $this->state->id = $id;
    $this->state->auth = true;
    $this->state->justLogin = true;
    return true;
  }

  public function logout(): void
  {
    $this->sessions->destroy(true);
  }
}
