<?php

declare(strict_types=1);

namespace bbn\User;

use bbn\Str;

/** User identity and profile access. */
final class Profile extends Component
{
  public function id(): ?string
  {
    return $this->state->id;
  }

  public function groupId(): ?string
  {
    return $this->state->idGroup;
  }

  /** @return array<string, mixed>|null */
  public function group(?string $id = null): ?array
  {
    if (!$this->state->auth) {
      return null;
    }

    if (!$id && $this->state->group) {
      return $this->state->group;
    }

    $arch = $this->cfg['arch']['groups'];
    $group = $this->db->rselect(
      $this->cfg['tables']['groups'],
      $arch,
      [$arch['id'] => $id ?: $this->state->idGroup],
    ) ?: null;

    if (!$id) {
      $this->state->group = $group;
    }

    return $group;
  }

  /** @return array<string, mixed>|null */
  public function info(string $id): ?array
  {
    $arch = $this->cfg['arch']['users'];
    $row = $this->db->rselect(
      $this->cfg['tables']['users'],
      $arch,
      [$arch['id'] => $id],
    ) ?: null;

    if ($row) {
      $row['group'] = $this->group($row[$arch['id_group']] ?? null);
    }

    return $row;
  }
}
