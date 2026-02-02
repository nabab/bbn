<?php

namespace bbn\Ai\Lab;


use bbn\X;

class Evaluation extends Base
{

  protected static $default_class_cfg = [
    "table" => "bbn_ai_lab_evaluations",
    "tables" => [
      "evaluations" => "bbn_ai_lab_evaluations",
    ],
    "arch" => [
      "evaluations" => [
        "id" => "id",
        "run_id" => "run_id",
        "evaluator" => "evaluator",
        "metric_name" => "metric_name",
        "score_numeric" => "score_numeric",
        "score_text" => "score_text",
        "created_at" => "created_at",
      ],
    ],
  ];

  public function findByRun(string $runId): array
  {
    return $this->dbTraitRselectAll(['run_id' => $runId]);
  }

  public function findByMetric(string $metricName): array
  {
    return $this->dbTraitRselectAll(['metric_name' => $metricName]);
  }

  public function findById(string $id): ?array
  {
    return $this->dbTraitRselect(['id' => $id]);
  }

  public function add(string $runId, array $data): array
  {
    $data['run_id'] = $runId;
    $id = $this->dbTraitInsert($data);
    return $this->findById($id);
  }

  public function bulkAdd(string $runId, array $evaluations): int
  {
    $count = 0;
    foreach ($evaluations as $eval) {
      if ($this->add($runId, $eval)) {
        $count++;
      }
    }

    return $count;
  }

  public function listMetricsByExperiment(string $experimentId): array
  {
    $cfg = $this->getClassCfg();
    return $this->db->getColumnValues([
      'tables' => 
      ['e' => $cfg['table']],
      'fields' => [
        'DISTINCT e.metric_name AS metric_name',
      ],
      'join' => [
        [
          'table' => 'bbn_ai_lab_runs',
          'alias' => 'r',
          'on' => [
            [
              'field' => 'r.id',
              'exp' => 'e.run_id'
            ]
          ]
        ]
      ],
      'where' => [
        'r.experiment_id' => $experimentId,
      ],
      'order' => ['e.metric_name' => 'ASC'],
    ]);
  }

  /**
   * Aggregate a single metric across an experiment, grouped by variant_key.
   *
   * Returns rows like:
   * [
   *   [
   *     'variant_key' => '...',
   *     'n' => 120,
   *     'avg' => 0.82,
   *     'min' => 0.10,
   *     'max' => 1.00,
   *     'stddev' => 0.07
   *   ],
   *   ...
   * ]
   *
   * @param string $experimentId UUID (hex string) for runs.experiment_id
   * @param string $metricName   evaluations.metric_name
   * @return array
   */
  public function aggregateByExperiment(string $experimentId, string $metricName, ?string $evaluator = null): array
  {
    $cfg = $this->getClassCfg();
    $where = [
        'r.experiment_id' => $experimentId,
        'e.metric_name'   => $metricName,
       [ 'e.score_numeric', 'isnotnull'],
    ];
    if ($evaluator) {
      $where['e.evaluator'] = $evaluator;
    }

    return $this->db->rselectAll([
      'tables' => 
      ['e' => $cfg['table']],
      'fields' => [
        'variant_key' => 'r.variant_key',
        'n'           => 'COUNT(e.id)',
        'avg'         => 'AVG(e.score_numeric)',
        'min'         => 'MIN(e.score_numeric)',
        'max'         => 'MAX(e.score_numeric)',
        'stddev'      => 'STDDEV_SAMP(e.score_numeric)',
      ],
      'join' => [
        [
          'table' => 'bbn_ai_lab_runs',
          'alias' => 'r',
          'on' => [
            [
              'field' => 'r.id',
              'exp' => 'e.run_id'
            ]
          ]
        ]
      ],
      'where' => $where,
      'group_by' => ['r.variant_key'],
      'order' => ['avg' => 'DESC'],
    ]);
  }

  /**
   * Aggregate all metrics for one (experiment, variant_key).
   *
   * Returns rows like:
   * [
   *   [
   *     'metric_name' => 'accuracy',
   *     'n' => 120,
   *     'avg' => 0.82,
   *     'min' => 0.10,
   *     'max' => 1.00,
   *     'stddev' => 0.07
   *   ],
   *   ...
   * ]
   *
   * @param string $experimentId UUID (hex string)
   * @param string $variantKey
   * @return array
   */
  public function aggregateByVariant(string $experimentId, string $variantKey, ?string $evaluator = null): array
  {
    $where = [
        'r.experiment_id' => $experimentId,
        'r.variant_key'   => $variantKey,
       [ 'e.score_numeric', 'isnotnull'],
    ];
    if ($evaluator) {
      $where['e.evaluator'] = $evaluator;
    }

    return $this->db->rselectAll([
      'tables' => 
      ['e' => $this->getClassCfg()['table']],
      'fields' => [
        'metric_name' => 'e.metric_name',
        'n'           => 'COUNT(e.id)',
        'avg'         => 'AVG(e.score_numeric)',
        'min'         => 'MIN(e.score_numeric)',
        'max'         => 'MAX(e.score_numeric)',
        'stddev'      => 'STDDEV_SAMP(e.score_numeric)',
      ],
      'join' => [
        [
          'table' => 'bbn_ai_lab_runs',
          'alias' => 'r',
          'on' => [
            [
              'field' => 'r.id',
              'exp' => 'e.run_id'
            ]
          ]
        ]
      ],
      'where' => $where,
      'group_by' => ['e.metric_name'],
      'order' => ['e.metric_name' => 'ASC'],
    ]);
  }
}
