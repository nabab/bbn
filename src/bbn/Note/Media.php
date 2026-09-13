<?php
namespace bbn\Note;

use bbn\Models\Cls\Table;

final class Media extends Table
{
  protected static $default_class_cfg = [
    'table' => 'bbn_notes_medias',
    'arch' => [
      'id' => 'id',
      'id_note' => 'id_note',
      'version' => 'version',
      'id_media' => 'id_media',
      'id_user' => 'id_user',
      'comment' => 'comment',
      'creation' => 'creation',
      'default_media' => 'default_media'
    ]
  ];

  public function setDefaultForNote(string $id_note, string $id_media): void
  {
    $f = $this->class_cfg['arch'];
    $this->db->update($this->class_cfg['table'], [$f['default_media'] => 0], [$f['id_note'] => $id_note]);
    $this->db->update($this->class_cfg['table'], [$f['default_media'] => 1], [$f['id_note'] => $id_note, $f['id_media'] => $id_media]);
  }

  public function link(string $id_note, int $version, string $id_media, string $id_user, int $default = 0): int
  {
    $f = $this->class_cfg['arch'];
    return (int)$this->db->insertUpdate($this->class_cfg['table'], [
      $f['id_note'] => $id_note,
      $f['version'] => $version,
      $f['id_media'] => $id_media,
      $f['id_user'] => $id_user,
      $f['creation'] => date('Y-m-d H:i:s'),
      $f['default_media'] => $default
    ]);
  }

  public function unlink(string $id_note, string $id_media): int
  {
    $f = $this->class_cfg['arch'];
    return (int)$this->db->delete($this->class_cfg['table'], [$f['id_note'] => $id_note, $f['id_media'] => $id_media]);
  }

  public function unlinkAll(string $id_note): int
  {
    $f = $this->class_cfg['arch'];
    return (int)$this->db->delete($this->class_cfg['table'], [$f['id_note'] => $id_note]);
  }

  public function listMediaIds(string $id_note): array
  {
    $f = $this->class_cfg['arch'];
    return $this->db->getColumnValues($this->class_cfg['table'], $f['id_media'], [$f['id_note'] => $id_note]) ?: [];
  }

  public function has(string $id_note, ?string $id_media = null): bool
  {
    $f = $this->class_cfg['arch'];
    $where = [$f['id_note'] => $id_note];
    if ($id_media) {
      $where[$f['id_media']] = $id_media;
    }
    return (bool)$this->db->count($this->class_cfg['table'], $where);
  }
}

