<?php

namespace bbn\Ai;

use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Ai\Lab\Model;
use bbn\Ai\Lab\Prompt;
use bbn\Ai\Lab\PromptVersion;
use bbn\Ai\Lab\Dataset;
use bbn\Ai\Lab\DatasetItem;
use bbn\Ai\Lab\Experiment;
use bbn\Ai\Lab\Configuration;
use bbn\Ai\Lab\Run;
use bbn\Ai\Lab\Evaluation;

/**
 * Mother class for quick access to table classes + higher-level read workflows (Option A).
 * Keeps your constructor and per-table accessor style unchanged.
 */
class Lab extends DbCls
{
  protected Model $modelCls;
  protected Prompt $promptCls;
  protected PromptVersion $promptVersionCls;
  protected Dataset $datasetCls;
  protected DatasetItem $datasetItemCls;
  protected Experiment $experimentCls;
  protected Configuration $configurationCls;
  protected Run $runCls;
  protected Evaluation $evaluationCls;

  public function __construct(protected Db $db) {
    parent::__construct($db);
  }

  /* -------------------------- Lazy accessors (unchanged) -------------------------- */

  public function models(): Model
  {
    if (!isset($this->modelCls)) {
      $this->modelCls = new Model($this->db);
    }
    return $this->modelCls;
  }

  public function prompts(): Prompt
  {
    if (!isset($this->promptCls)) {
      $this->promptCls = new Prompt($this->db);
    }
    return $this->promptCls;
  }

  public function promptVersions(): PromptVersion
  {
    if (!isset($this->promptVersionCls)) {
      $this->promptVersionCls = new PromptVersion($this->db);
    }
    return $this->promptVersionCls;
  }

  public function datasets(): Dataset
  {
    if (!isset($this->datasetCls)) {
      $this->datasetCls = new Dataset($this->db);
    }
    return $this->datasetCls;
  }

  public function datasetItems(): DatasetItem
  {
    if (!isset($this->datasetItemCls)) {
      $this->datasetItemCls = new DatasetItem($this->db);
    }
    return $this->datasetItemCls;
  }

  public function experiments(): Experiment
  {
    if (!isset($this->experimentCls)) {
      $this->experimentCls = new Experiment($this->db);
    }
    return $this->experimentCls;
  }

  public function configurations(): Configuration
  {
    if (!isset($this->configurationCls)) {
      $this->configurationCls = new Configuration($this->db);
    }
    return $this->configurationCls;
  }

  public function runs(): Run
  {
    if (!isset($this->runCls)) {
      $this->runCls = new Run($this->db);
    }
    return $this->runCls;
  }

  public function evaluations(): Evaluation
  {
    if (!isset($this->evaluationCls)) {
      $this->evaluationCls = new Evaluation($this->db);
    }
    return $this->evaluationCls;
  }

  /* -------------------------- Variant helpers -------------------------- */

  /**
   * Computes a stable variant key for grouping.
   * Includes prompt_version_id by default so same config + different prompt version are distinct variants.
   */
  public function computeVariantKey(array $configSnapshot, ?string $promptVersionId = null): string
  {
    $basis = [
      'backend' => $configSnapshot['backend'] ?? null,
      'model' => $configSnapshot['model'] ?? null,
      'sampling' => $configSnapshot['sampling'] ?? [],
      'extra' => $configSnapshot['extra'] ?? [],
      'prompt_version_id' => $promptVersionId,
    ];

    $this->ksortRecursive($basis);

    $json = json_encode(
      $basis,
      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
    );

    return hash('sha256', $json ?: '');
  }

  public function formatVariantLabel(array $configSnapshot, ?array $promptVersion = null): string
  {
    $model = $configSnapshot['model'] ?? 'unknown-model';
    $s = $configSnapshot['sampling'] ?? [];

    $parts = [
      $model,
      't=' . ($s['temperature'] ?? ''),
      'p=' . ($s['top_p'] ?? ''),
      'k=' . ($s['top_k'] ?? ''),
      'rep=' . ($s['repeat_penalty'] ?? ''),
      'pres=' . ($s['presence_penalty'] ?? ''),
      'freq=' . ($s['frequency_penalty'] ?? ''),
    ];

    if (array_key_exists('seed', $s) && $s['seed'] !== null) {
      $parts[] = 'seed=' . $s['seed'];
    }

    if ($promptVersion && isset($promptVersion['version'])) {
      $parts[] = 'pv=' . $promptVersion['version'];
    }

    return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts, fn($v) => $v !== '' && $v !== 't=' && $v !== 'p=' && $v !== 'k='))));
  }

  private function ksortRecursive(array &$arr): void
  {
    ksort($arr);
    foreach ($arr as &$v) {
      if (is_array($v)) {
        $this->ksortRecursive($v);
      }
    }
  }

  /* -------------------------- Create workflow -------------------------- */

  /**
   * Create a run with config+prompt snapshot from raw data.
   * - Ensures variant_key is set (required for summaries & aggregations).
   * - Uses runs()->create() if present; falls back to insert().
   */
  public function createRunWithConfigAndPrompt(
    array $experiment,
    array $configuration,
    array $promptVersion,
    ?array $datasetItem,
    array $input,
    array $output,
    array $metrics
  ): array {

    $experimentId = $experiment['id'] ?? null;
    $configurationId = $configuration['id'] ?? null;

    if (!$configurationId) {
      throw new \InvalidArgumentException("Missing configuration['id']");
    }

    $promptVersionId = $promptVersion['id'] ?? null;
    $datasetItemId = $datasetItem['id'] ?? null;

    // Tolerate either "row + config_snapshot" or direct normalized config arrays.
    $configSnapshot = $configuration['config_snapshot'] ?? $configuration;
    $promptSnapshot = $promptVersion['prompt_snapshot'] ?? $promptVersion;

    $variantKey = $this->computeVariantKey($configSnapshot, $promptVersionId);

    $runData = [
      'experiment_id'       => $experimentId,
      'configuration_id'    => $configurationId,
      'prompt_version_id'   => $promptVersionId,
      'dataset_item_id'     => $datasetItemId,
      'variant_key'         => $variantKey,

      'input_text'          => $input['text'] ?? null,
      'input_metadata'      => $input['metadata'] ?? [],

      'config_snapshot'     => $configSnapshot,
      'prompt_snapshot'     => $promptSnapshot,

      'output_text'         => $output['text'] ?? null,
      'output_structured'   => $output['structured'] ?? null,
      'finish_reason'       => $output['finish_reason'] ?? null,

      'tokens_prompt'       => $metrics['tokens_prompt'] ?? null,
      'tokens_completion'   => $metrics['tokens_completion'] ?? null,
      'tokens_total'        => $metrics['tokens_total'] ?? null,
      'latency_ms'          => $metrics['latency_ms'] ?? null,
      'cost'                => $metrics['cost'] ?? null,
    ];

    $runId = $this->runs()->create($runData);

    return $runId ? ($this->runs()->findById($runId) ?? []) : [];
  }

  /* -------------------------- Option A pages support -------------------------- */

  public function listExperiments(int $limit = 50, int $start = 0): array
  {
    return $this->experiments()->list($limit, $start);
  }

  public function listExperimentHeader(string $experimentId): ?array
  {
    return $this->experiments()->findById($experimentId);
  }

  /**
   * Overview numbers + variant summaries + available metric names.
   */
  public function getExperimentOverview(string $experimentId): array
  {
    $totalRuns = method_exists($this->runs(), 'countByExperiment')
      ? $this->runs()->countByExperiment($experimentId)
      : count($this->runs()->findByExperiment($experimentId));

    $variantSummaries = $this->runs()->getVariantSummaries($experimentId);

    // Your Evaluation::listMetricsByExperiment currently returns rows like ['metric_name' => '...'].
    $metricRows = $this->evaluations()->listMetricsByExperiment($experimentId);
    $metrics = array_values(array_filter(array_map(
      fn($r) => is_array($r) ? ($r['metric_name'] ?? null) : null,
      $metricRows
    )));

    return [
      'experiment_id' => $experimentId,
      'total_runs' => $totalRuns,
      'variant_summaries' => $variantSummaries,
      'metrics' => $metrics,
    ];
  }

  /**
   * groups by variant (config+promptVersion), returns aggregates.
   *
   * Options:
   * - metric_name: if provided, merges Evaluation::aggregateByExperiment for that metric.
   */
  public function getExperimentVariantSummaries(string $experimentId, array $options = []): array
  {
    $runAgg = $this->runs()->getVariantSummaries($experimentId, $options);
    $metricName = $options['metric_name'] ?? null;

    if (!$metricName) {
      return $runAgg;
    }

    // Merge evaluation aggregates for the chosen metric
    $evalAgg = $this->evaluations()->aggregateByExperiment($experimentId, $metricName);
    $evalByVariant = [];
    foreach ($evalAgg as $e) {
      $evalByVariant[$e['variant_key']] = $e;
    }

    foreach ($runAgg as &$row) {
      $vk = $row['variant_key'] ?? null;
      $row['eval'] = ($vk && isset($evalByVariant[$vk])) ? $evalByVariant[$vk] : null;
    }

    return $runAgg;
  }

  /**
   * Explorer table: runs filtered inside an experiment.
   *
   * - $filters are run-column filters
   * - $order, $limit, $start control sorting & pagination
   */
  public function getExperimentRunsExplorer(
    string $experimentId,
    array $filters = [],
    array $order = ['created_at' => 'DESC'],
    int $limit = 50,
    int $start = 0
  ): array {
    $filter = array_merge(['experiment_id' => $experimentId], $filters);
    return $this->runs()->getRunsExplorer($filter, $order, $limit, $start);
  }

  /**
   * Run detail: run + evaluations (+ dataset item if available).
   */
  public function getRunDetail(string $runId): ?array
  {
    $run = method_exists($this->runs(), 'getByIdWithJoins')
      ? $this->runs()->getByIdWithJoins($runId)
      : $this->runs()->findById($runId);

    if (!$run) {
      return null;
    }

    $run['evaluations'] = $this->evaluations()->findByRun($runId);

    if (!empty($run['dataset_item_id'])) {
      $run['dataset_item'] = $this->datasetItems()->findById($run['dataset_item_id']);
    }

    return $run;
  }

  /**
   * Compare two runs: quick diffs for UI rendering.
   */
  public function getRunCompare(string $runIdA, string $runIdB): array
  {
    $a = $this->getRunDetail($runIdA);
    $b = $this->getRunDetail($runIdB);

    if (!$a || !$b) {
      return [
        'ok' => false,
        'error' => 'One or both runs not found',
        'a' => $runIdA,
        'b' => $runIdB,
      ];
    }

    return [
      'ok' => true,
      'a' => $a,
      'b' => $b,
      'diff' => [
        'config_snapshot' => $this->arrayDiff($a['config_snapshot'] ?? [], $b['config_snapshot'] ?? []),
        'prompt_snapshot' => $this->arrayDiff($a['prompt_snapshot'] ?? [], $b['prompt_snapshot'] ?? []),
        'output_text' => [
          'changed' => (string)($a['output_text'] ?? '') !== (string)($b['output_text'] ?? ''),
          'before' => (string)($a['output_text'] ?? ''),
          'after' => (string)($b['output_text'] ?? ''),
        ],
        'evaluations' => $this->evalDiff($a['evaluations'] ?? [], $b['evaluations'] ?? []),
      ],
    ];
  }

  /**
   * Example: get all runs + evaluations for an experiment.
   */
  public function getExperimentResults(string $experimentId): array
  {
    $runs = $this->runs()->findByExperiment($experimentId);

    foreach ($runs as &$run) {
      $run['evaluations'] = $this->evaluations()->findByRun($run['id']);
    }

    return $runs;
  }

  /* -------------------------- small internal helpers -------------------------- */

  private function arrayDiff(array $a, array $b): array
  {
    $out = ['changed' => [], 'removed' => [], 'added' => []];

    foreach ($a as $k => $v) {
      if (!array_key_exists($k, $b)) {
        $out['removed'][$k] = $v;
      }
      elseif ($b[$k] !== $v) {
        $out['changed'][$k] = ['from' => $v, 'to' => $b[$k]];
      }
    }

    foreach ($b as $k => $v) {
      if (!array_key_exists($k, $a)) {
        $out['added'][$k] = $v;
      }
    }

    return $out;
  }

  private function evalDiff(array $evalA, array $evalB): array
  {
    // Index by metric_name + evaluator for stable compare
    $idx = function(array $items): array {
      $out = [];
      foreach ($items as $e) {
        $k = ($e['metric_name'] ?? '') . '|' . ($e['evaluator'] ?? '');
        $out[$k] = $e;
      }
      ksort($out);
      return $out;
    };

    $a = $idx($evalA);
    $b = $idx($evalB);

    return $this->arrayDiff($a, $b);
  }
}
