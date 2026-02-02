<?php

namespace bbn\Ai\Lab;


use bbn\X;
use bbn\Db;

class Model extends Base
{
  protected static $default_class_cfg = [
    "table" => "bbn_ai_lab_models",
    "tables" => [
      "models" => "bbn_ai_lab_models",
    ],
    "arch" => [
      "models" => [
        "id" => "id",
        "provider" => "provider",
        "name" => "name",
        "display_name" => "display_name",
        "created_at" => "created_at",
      ],
    ],
  ];

  public function findByName(string $provider, string $name): ?array
  {
    return $this->dbTraitRselect([
      'provider' => $provider,
      'name'     => $name,
    ]);
  }

  public function findById(string $id): ?array
  {
    return $this->dbTraitRselect([
      'id' => $id
    ]);
  }

  public function list(int $limit = 50, int $start = 0, array $filter = []): array
  {
    return $this->dbTraitRselectAll($filter, ['limit' => $limit, 'start' => $start]);
  }

  public function create(array $data): ?array
  {
    if ($id = $this->dbTraitInsertUpdate($data)) {
      return $this->findById($id);
    }

    return null;
  }

  public function updateById(string $id, array $data): bool
  {
    return $this->dbTraitUpdate($id, $data);
  }
}
