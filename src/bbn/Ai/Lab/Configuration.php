<?php

namespace bbn\Ai\Lab;


use bbn\X;

class Configuration extends Base
{

  protected static $default_class_cfg = [
    "table" => "bbn_ai_lab_configurations",
    "tables" => [
      "configurations" => "bbn_ai_lab_configurations",
    ],
    "arch" => [
      "configurations" => [
        "id" => "id",
        "model_id" => "model_id",
        "temperature" => "temperature",
        "top_p" => "top_p",
        "top_k" => "top_k",
        "max_tokens" => "max_tokens",
        "stop_sequences" => "stop_sequences",
        "seed" => "seed",
        "repeat_penalty" => "repeat_penalty",
        "presence_penalty" => "presence_penalty",
        "frequency_penalty" => "frequency_penalty",
        "logit_bias" => "logit_bias",
        "extra_params" => "extra_params",
        "created_at" => "created_at",
      ],
    ],
  ];

  public function findEquivalent(array $configData): ?array
  {
    // up to you how strict the comparison is, 
    // just an example that uses a subset:
    $criteria = [
      'model_id'    => $configData['model_id'] ?? null,
      'temperature' => $configData['temperature'] ?? null,
      'top_p'       => $configData['top_p'] ?? null,
      'max_tokens'  => $configData['max_tokens'] ?? null,
    ];

    return $this->dbTraitRselect($criteria);
  }

  public function findById(string $id): ?array
  {
    return $this->dbTraitRselect(['id' => $id]);
  }

  public function create(array $data): ?string
  {
    return $this->dbTraitInsert($data);
  }

  public function listByModel(string $modelId, int $limit = 50, int $start = 0): array
  {
    return $this->dbTraitRselectAll(['model_id' => $modelId], [], $limit, $start);
  }
}

