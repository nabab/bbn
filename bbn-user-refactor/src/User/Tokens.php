<?php

declare(strict_types=1);

namespace bbn\User;

use bbn\Str;
use bbn\X;

/** API, access and session token operations. */
final class Tokens extends Component
{
  public function add(?string $sessionId): ?string
  {
    if (!$this->state->auth || !$sessionId) {
      return null;
    }

    $token = Str::genpwd(32, 16);
    $arch = $this->cfg['arch']['tokens'];
    if ($this->db->insert($this->cfg['tables']['tokens'], [
      $arch['id_session'] => $sessionId,
      $arch['content'] => $token,
      $arch['creation'] => X::microtime(),
      $arch['last'] => X::microtime(),
    ])) {
      return $token;
    }

    return null;
  }

  public function userByDevice(string $token, string $device): ?string
  {
    if (empty($this->cfg['tables']['api_tokens'])) {
      return null;
    }

    $arch = $this->cfg['arch']['api_tokens'];
    $id = $this->db->selectOne(
      $this->cfg['tables']['api_tokens'],
      $arch['id_user'],
      [$arch['token'] => $token, $arch['device_uid'] => $device],
    );

    return is_string($id) ? $id : null;
  }
}
