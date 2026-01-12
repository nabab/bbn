<?php

namespace bbn\Ai\Lab;


use bbn\X;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;

class DatasetItem extends DbCls
{
  use DbActions;

  protected static $default_class_cfg = [
    "table" => "bbn_ai_lab_dataset_items",
    "tables" => [
      "dataset_items" => "bbn_ai_lab_dataset_items",
    ],
    "arch" => [
      "dataset_items" => [
        "id" => "id",
        "dataset_id" => "dataset_id",
        "input_ref" => "input_ref",
        "input_data" => "input_data",
        "created_at" => "created_at",
      ],
    ],
  ];

  public function findByDataset(string $datasetId): array
  {
    $ar = $this->dbTraitRselectAll(['dataset_id' => $datasetId]);
    if ($ar) {
      $ar['input_data'] = json_decode($ar['input_data'], true);
    }

    return $ar;
  }

  public function findById(string $id): ?array
  {
    $ar = $this->dbTraitRselect(['id' => $id]);
    if ($ar) {
      $ar['input_data'] = json_decode($ar['input_data'], true);
    }

    return $ar;
  }

  public function countByDataset(string $datasetId): int
  {
    return $this->dbTraitCount(['dataset_id' => $datasetId]);
  }

  public function searchInDataset(string $datasetId, string $query, int $limit = 50, int $start = 0): array
  {
    $where = [
      'dataset_id' => $datasetId,
      ['input_data', 'contains', $query]
    ];
    $res = array_map(function ($a) {
      $a['input_data'] = json_decode($a['input_data'], true);
      return $a;
    }, $this->dbTraitRselectAll($where, [], $limit, $start));
    return $res;
  }

  // use TableTrait;

  /**
   * Create a dataset item.
   *
   * @param string      $datasetId
   * @param array       $inputData   Typically something like: ['text' => '...', 'metadata' => [...]]
   * @param string|null $inputRef
   * @return array The created row (re-fetched)
   */
  public function create(string $datasetId, array $inputData, ?string $inputRef = null): array
  {
    $row = [
      'dataset_id' => $datasetId,
      'input_ref'  => $inputRef,
      'input_data' => json_encode($inputData),
    ];

    $id = $this->dbTraitInsert($row);       // provided by your trait
    $created = $this->findById($id); // provided by your trait or by your class

    // If your insert already returns full row, you can just return it.
    return $created ?? array_merge(['id' => $id], $row);
  }

  /**
   * Bulk create dataset items.
   *
   * Input format (recommended):
   *  [
   *    ['input_data' => [...], 'input_ref' => 'optional'],
   *    ['input_data' => [...]],
   *  ]
   *
   * @param string $datasetId
   * @param array  $items
   * @return int number of inserted rows
   */
  public function bulkCreate(string $datasetId, array $items): int
  {
    if ($items === []) {
      return 0;
    }

    try {
      $count = 0;

      foreach ($items as $item) {
        if (!is_array($item)) {
          continue; // or throw, depending on your style
        }

        $inputData = $item['input_data'] ?? $item['inputData'] ?? null;
        if (!is_array($inputData)) {
          // You can throw instead; I’m keeping it forgiving.
          continue;
        }

        $inputRef = $item['input_ref'] ?? $item['inputRef'] ?? null;

        $this->dbTraitInsert([
          'dataset_id' => $datasetId,
          'input_ref'  => $inputRef,
          'input_data' => json_encode($inputData),
        ]);

        $count++;
      }

      return $count;
    } catch (\Throwable $e) {
      throw $e;
    }
  }
}
