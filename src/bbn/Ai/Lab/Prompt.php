<?php

namespace bbn\Ai\Lab;


use bbn\X;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;

class Prompt extends DbCls
{
  use DbActions;

  protected static $default_class_cfg = [
    "table" => "bbn_ai_lab_prompts",
    "tables" => [
      "prompts" => "bbn_ai_lab_prompts",
    ],
    "arch" => [
      "prompts" => [
        "id" => "id",
        "name" => "name",
        "description" => "description",
        "created_at" => "created_at",
      ],
    ],
  ];

  public function findByName(string $name): ?array
  {
    return $this->dbTraitRselect(['name' => $name]);
  }

  public function findById(string $id): ?array
  {
    return $this->dbTraitRselect(['id' => $id]);
  }

  public function list(int $limit = 50, int $start = 0, array $filter = []): array
  {
    return $this->dbTraitRselectAll($filter, ['limit' => $limit, 'start' => $start]);
  }

  public function create(array $data): ?string
  {
    return $this->dbTraitInsert($data);
  }

  public function updateById(string $id, array $data): bool
  {
    return $this->dbTraitUpdate($id, $data);
  }
}
