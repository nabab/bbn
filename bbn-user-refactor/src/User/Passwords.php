<?php

declare(strict_types=1);

namespace bbn\User;

/** Password hashing, verification and persistence. */
final class Passwords extends Component
{
  public function hash(string $plain): string
  {
    $algorithm = $this->cfg['encryption'] ?? null;
    if (!is_string($algorithm) || !function_exists($algorithm)) {
      return hash('sha256', $plain);
    }

    return $algorithm($plain);
  }

  public function verify(string $plain, string $stored): bool
  {
    return hash_equals($stored, $this->hash($plain));
  }

  public function force(string $plain): bool
  {
    if (!$this->state->id) {
      return false;
    }

    $arch = $this->cfg['arch']['passwords'];
    return (bool)$this->db->insert($this->cfg['tables']['passwords'], [
      $arch['pass'] => $this->hash($plain),
      $arch['id_user'] => $this->state->id,
      $arch['added'] => date('Y-m-d H:i:s'),
    ]);
  }

  public function change(string $old, string $new): bool
  {
    if (!$this->state->auth || !$this->state->id) {
      return false;
    }

    $arch = $this->cfg['arch']['passwords'];
    $stored = $this->db->selectOne(
      $this->cfg['tables']['passwords'],
      $arch['pass'],
      [$arch['id_user'] => $this->state->id],
      [$arch['added'] => 'DESC'],
    );

    return is_string($stored)
      && $this->verify($old, $stored)
      && $this->force($new);
  }
}
