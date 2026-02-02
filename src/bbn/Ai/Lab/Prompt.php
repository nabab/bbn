<?php

namespace bbn\Ai\Lab;

use bbn\Models\Tts\Note;

class Prompt extends Base
{

  use Note;

  protected static $default_class_cfg = [
    "table" => "bbn_ai_lab_prompts",
    "tables" => [
      "prompts" => "bbn_ai_lab_prompts",
    ],
    "arch" => [
      "prompts" => [
        "id" => "id",
        "name" => "name",
        "id_note" => "id_note"
      ],
    ],
    "type_note" => "ai_lab_prompt"
  ];

  public function findByName(string $name): ?array
  {
    if ($id = $this->dbTraitSelectOne('id', ['name' => $name])) {
      return $this->findById($id);
    }

    return null;
  }

  public function findById(string $id): ?array
  {
    if ($res = $this->dbTraitRselect(['id' => $id])) {
      $res = [...$this->noteGet($res['id_note']), ...$res];
    }

    return $res ?? null;
  }

  public function list(int $limit = 50, int $start = 0, array $filter = []): array
  {
    return $this->dbTraitRselectAll($filter, ['limit' => $limit, 'start' => $start]);
  }

  public function create(array $data): ?string
  {
    $cfg = $this->getClassCfg();
    if ($this->findByName($data['name'])) {
      return null;
    }
    if (($id_note = $this->noteInsert($data['name'], $data['content'] ?? '', $this->noteGetDefaultType())) &&
      ($id = $this->dbTraitInsert([
        $cfg['arch']['prompts']['id_note'] => $id_note,
        $cfg['arch']['prompts']['name'] => $data['name']
      ]))
    ) {
      return $id;
    }
    
    return null;
  }

  public function updateById(string $id, array $data): bool
  {
    $cfg = $this->getClassCfg();
    $res1 = $this->noteUpdate($data['id_note'], [
      'title' => $data['name'],
      'content' => $data['content'] ?? ''
    ]);
    $res2 = $this->dbTraitUpdate($id, [
      $cfg['arch']['prompts']['name'] => $data['name'],
    ]);
    return $res1 || $res2;
  }

  public function latestVersion($id): ?int
  {
    if ($idNote = $this->dbTraitSelectOne('id_note', ['id' => $id])) {
      return $this->noteLatestVersion($idNote);
    }

    return null;
  }
}
