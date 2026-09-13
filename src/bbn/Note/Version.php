<?php
namespace bbn\Note;

use bbn\Models\Cls\Table;

final class Version extends Table
{
  protected static $default_class_cfg = [
    'table' => 'bbn_notes_versions',
    'arch' => [
      'id_note' => 'id_note',
      'version' => 'version',
      'latest' => 'latest',
      'title' => 'title',
      'content' => 'content',
      'excerpt' => 'excerpt',
      'id_user' => 'id_user',
      'creation' => 'creation',
    ]
  ];

  public function latestVersion(string $id_note): ?int
  {
    $f = $this->class_cfg['arch'];
    $v = $this->db->selectOne($this->class_cfg['table'], 'MAX(' . $f['version'] . ')', [$f['id_note'] => $id_note]);
    return $v ? (int)$v : null;
  }

  public function getTitleLatest(string $id_note): ?string
  {
    $f = $this->class_cfg['arch'];
    return $this->db->selectOne($this->class_cfg['table'], $f['title'], [$f['id_note'] => $id_note, $f['latest'] => 1]);
  }

  public function setLatest(string $id_note, int $version): bool
  {
    $f = $this->class_cfg['arch'];
    $this->db->update($this->class_cfg['table'], [$f['latest'] => 0], [$f['id_note'] => $id_note, [$f['version'], '!=', $version]]);
    return (bool)$this->db->update($this->class_cfg['table'], [$f['latest'] => 1], [$f['id_note'] => $id_note, $f['version'] => $version]);
  }

  public function insertVersion(string $id_note, int $version, string $title, string $content, string $excerpt, string $id_user): bool
  {
    $f = $this->class_cfg['arch'];

    if (!$this->db->insert($this->class_cfg['table'], [
      $f['id_note'] => $id_note,
      $f['version'] => $version,
      $f['latest'] => 1,
      $f['title'] => $title,
      $f['content'] => $content,
      $f['excerpt'] => $excerpt,
      $f['id_user'] => $id_user,
      $f['creation'] => date('Y-m-d H:i:s'),
    ])) {
      return false;
    }

    $this->db->update(
      $this->class_cfg['table'],
      [$f['latest'] => 0],
      [$f['id_note'] => $id_note, [$f['version'], '!=', $version]]
    );

    return true;
  }

  public function removeVersion(string $id_note, int $version): bool
  {
    $f = $this->class_cfg['arch'];
    return (bool)$this->db->delete($this->class_cfg['table'], [$f['id_note'] => $id_note, $f['version'] => $version]);
  }

  public function listVersionsMeta(string $id_note): array
  {
    $f = $this->class_cfg['arch'];
    return $this->db->rselectAll([
      'table' => $this->class_cfg['table'],
      'fields' => [$f['version'], $f['id_user'], $f['creation']],
      'where' => ['conditions' => [[ 'field' => $f['id_note'], 'value' => $id_note ]]],
      'order' => [[ 'field' => $f['version'], 'dir' => 'DESC' ]]
    ]) ?: [];
  }
}
