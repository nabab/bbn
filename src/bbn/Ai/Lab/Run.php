<?php

namespace bbn\Ai\Lab;


class Run extends Base
{

  protected static $default_class_cfg = [
    "table" => "bbn_ai_lab_runs",
    "tables" => [
      "runs" => "bbn_ai_lab_runs",
    ],
    "arch" => [
      "runs" => [
        "id" => "id",
        "experiment_id" => "experiment_id",
        "configuration_id" => "configuration_id",
        "prompt_id" => "prompt_id",
        "prompt_version" => "prompt_version",
        "dataset_item_id" => "dataset_item_id",
        // IMPORTANT: you are using it in methods, so it must exist in arch
        "variant_key" => "variant_key",

        "input_text" => "input_text",
        "input_metadata" => "input_metadata",
        "config_snapshot" => "config_snapshot",
        "prompt_snapshot" => "prompt_snapshot",
        "output_text" => "output_text",
        "output_structured" => "output_structured",
        "finish_reason" => "finish_reason",
        "tokens_prompt" => "tokens_prompt",
        "tokens_completion" => "tokens_completion",
        "tokens_total" => "tokens_total",
        "latency_ms" => "latency_ms",
        "cost" => "cost",

        // If you store it as timestamp default current, keep as is
        "created_at" => "created_at",

        // Optional cache column for attachEvaluationSummary()
        // "evaluation_summary" => "evaluation_summary",
      ],
    ],
  ];

  public function findById(string $id): ?array
  {
    return $this->dbTraitRselect(['id' => $id]);
  }

  public function listRecent(int $limit = 50, int $start = 0): array
  {
    return $this->dbTraitRselectAll([], ['created_at' => 'DESC'], $limit, $start);
  }

  public function findByExperiment(string $experimentId, int $limit = 0, int $start = 0): array
  {
    return $this->dbTraitRselectAll(
      ['experiment_id' => $experimentId],
      ['created_at' => 'DESC'],
      $limit ?: null,
      $start ?: null
    );
  }

  public function findByConfiguration(string $configurationId, int $limit = 0, int $start = 0): array
  {
    return $this->dbTraitRselectAll(
      ['configuration_id' => $configurationId],
      ['created_at' => 'DESC'],
      $limit ?: null,
      $start ?: null
    );
  }

  public function countByExperiment(string $experimentId): int
  {
    return (int)$this->dbTraitCount(['experiment_id' => $experimentId]);
  }

  public function create(array $data): ?string
  {
    return $this->dbTraitInsert($data);
  }

  public function updateById(string $id, array $data): bool
  {
    return (bool)$this->dbTraitUpdate($id, $data);
  }

  public function getVariantSummaries(string $experimentId, array $options = []): array
  {
    return $this->db->rselectAll([
      'table' => $this->class_table,
      'fields' => [
        'variant_key',
        'run_count' => 'COUNT(*)',
        'avg_cost' => 'AVG(cost)',
        'avg_tokens_total' => 'AVG(tokens_total)',
        'avg_latency_ms' => 'AVG(latency_ms)',
      ],
      'where' => [
        'experiment_id' => $experimentId,
      ],
      'group_by' => 'variant_key',
      'order' => ['run_count' => 'DESC'],
      'limit' => $options['limit'] ?? 0,
      'start' => $options['start'] ?? 0,
    ]) ?: [];
  }

  public function getRunsExplorer(array $filter = [], array $order = [], int $limit = 50, int $start = 0): array
  {
    return $this->dbTraitRselectAll($filter, $order, $limit, $start);
  }

  /**
   * Find runs belonging to an experiment AND a specific variant.
   */
  public function findByExperimentAndVariant(
    string $experimentId,
    string $variantKey,
    ?int $limit = null,
    ?int $start = null
  ): array {
    return $this->dbTraitRselectAll(
      [
        'experiment_id' => $experimentId,
        'variant_key'   => $variantKey,
      ],
      [
        'created_at' => 'ASC',
      ],
      $limit,
      $start
    );
  }

  /**
   * Useful for explorer pages: fetch runs for a given input inside an experiment.
   */
  public function findByExperimentAndDatasetItem(string $experimentId, string $datasetItemId): array
  {
    return $this->dbTraitRselectAll(
      [
        'experiment_id' => $experimentId,
        'dataset_item_id' => $datasetItemId,
      ],
      ['created_at' => 'ASC']
    );
  }

  /**
   * Optional optimization: return run + denormalized context from snapshots
   * without joining other tables. (Mother can call this.)
   */
  public function getByIdWithJoins(string $runId): ?array
  {
    // Since you store snapshots, you can already show almost everything.
    // If later you want joins, you can extend this method.
    return $this->findById($runId);
  }

  public function attachEvaluationSummary(string $runId, array $summary): void
  {
    $candidateColumns = [
      'evaluation_summary',
      'evaluation_summary_json',
      'eval_summary',
    ];

    $column = $this->pickExistingColumn($candidateColumns);

    if (!$column) {
      throw new \RuntimeException(
        'No evaluation summary column found on runs table. Expected one of: ' . implode(', ', $candidateColumns)
      );
    }

    $this->updateById($runId, [
      $column => $summary,
    ]);
  }

  private function pickExistingColumn(array $candidates): ?string
  {
    // In BBN DbCls, the structure is in class_cfg arch
    $arch = $this->class_cfg['arch']['runs'] ?? [];

    foreach ($candidates as $col) {
      if (isset($arch[$col])) {
        return $col;
      }
    }

    return null;
  }
}
