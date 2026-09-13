<?php
namespace bbn;

use bbn\Models\Cls\Table;

final class Note extends Table
{
  protected static $default_class_cfg = [
    'table' => 'bbn_notes',
    'arch' => [
      'id' => 'id',
      'id_parent' => 'id_parent',
      'id_alias' => 'id_alias',
      'id_type' => 'id_type',
      'id_option' => 'id_option',
      'mime' => 'mime',
      'lang' => 'lang',
      'private' => 'private',
      'locked' => 'locked',
      'pinned' => 'pinned',
      'important' => 'important',
      'creator' => 'creator',
      'active' => 'active',
    ]
  ];

  public function softDelete(string $id): int
  {
    $f = $this->class_cfg['arch'];
    return $this->db->update($this->class_cfg['table'], [$f['active'] => 0], [$f['id'] => $id]);
  }

  public function setType(string $id, string $id_type): int
  {
    $f = $this->class_cfg['arch'];
    return $this->db->update($this->class_cfg['table'], [$f['id_type'] => $id_type], [$f['id'] => $id]);
  }

  public function setOption(string $id, ?string $id_option): int
  {
    $f = $this->class_cfg['arch'];
    return $this->db->update($this->class_cfg['table'], [$f['id_option'] => $id_option], [$f['id'] => $id]);
  }

  public function pin(string $id): bool
  {
    $f = $this->class_cfg['arch'];
    return (bool)$this->db->update($this->class_cfg['table'], [$f['pinned'] => 1], [$f['id'] => $id]);
  }

  public function unpin(string $id): bool
  {
    $f = $this->class_cfg['arch'];
    return (bool)$this->db->update($this->class_cfg['table'], [$f['pinned'] => 0], [$f['id'] => $id]);
  }

  public function setImportant(string $id): bool
  {
    $f = $this->class_cfg['arch'];
    return (bool)$this->db->update($this->class_cfg['table'], [$f['important'] => 1], [$f['id'] => $id]);
  }

  public function unsetImportant(string $id): bool
  {
    $f = $this->class_cfg['arch'];
    return (bool)$this->db->update($this->class_cfg['table'], [$f['important'] => 0], [$f['id'] => $id]);
  }

  public function getAliases(string $id_note): array
  {
    $f = $this->class_cfg['arch'];
    return $this->db->getColumnValues($this->class_cfg['table'], $f['id'], [$f['id_alias'] => $id_note]);
  }

  public function getChildren(string $id_note): array
  {
    $f = $this->class_cfg['arch'];
    return $this->db->getColumnValues($this->class_cfg['table'], $f['id'], [$f['id_parent'] => $id_note]);
  }
}
