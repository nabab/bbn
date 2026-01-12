<?php

namespace bbn\Ai\Lab;


use bbn\X;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;

class PromptVersion extends DbCls
{
  use DbActions;

  protected static $default_class_cfg = [
    "table" => "bbn_ai_lab_prompt_versions",
    "tables" => [
      "prompt_versions" => "bbn_ai_lab_prompt_versions",
    ],
    "arch" => [
      "prompt_versions" => [
        "id" => "id",
        "prompt_id" => "prompt_id",
        "version" => "version",
        "prompt_text" => "prompt_text",
        "messages" => "messages",
        "created_at" => "created_at",
      ],
    ],
  ];

  public function findLatestForPrompt(string $promptId): ?array
  {
    // Assuming trait has something like findAll + ordering
    return $this->dbTraitRselect(
      ['prompt_id' => $promptId],
      ['version' => 'DESC']
    );
  }

  public function createNewVersion(string $promptId, array $data): array
  {
    // e.g. get max(version)+1, then insert
    $latest = $this->findLatestForPrompt($promptId);
    $nextVersion = $latest ? ((int)$latest['version'] + 1) : 1;

    $data['prompt_id'] = $promptId;
    $data['version']   = $nextVersion;

    $id = $this->dbTraitInsert($data); // from trait
    return $this->dbTraitRselect($id);
  }

  public function findById(string $id): ?array
  {
    return $this->dbTraitRselect(['id' => $id]);
  }

  public function findByPrompt(string $promptId, int $limit = 50, int $start = 0): array
  {
    return $this->dbTraitRselectAll(
      ['prompt_id' => $promptId],
      ['limit' => $limit, 'start' => $start]
    );
  }

  public function findByPromptAndVersion(string $promptId, int $version): ?array
  {
    return $this->dbTraitRselect([
      'prompt_id' => $promptId,
      'version'   => $version
    ]);
  }

  /**
   * Return a structured diff between two prompt versions.
   *
   * Expected columns (typical):
   * - prompt_text (TEXT|null)
   * - messages (JSON|null)  (array of {role, content, ...})
   *
   * @param string $promptVersionIdA
   * @param string $promptVersionIdB
   * @return array
   */
  public function diff(string $promptVersionIdA, string $promptVersionIdB): array
  {
    $a = $this->findById($promptVersionIdA);
    $b = $this->findById($promptVersionIdB);

    if (!$a || !$b) {
      return [
        'ok' => false,
        'error' => 'One or both prompt versions not found.',
        'prompt_version_id_a' => $promptVersionIdA,
        'prompt_version_id_b' => $promptVersionIdB,
      ];
    }

    $promptTextA = $a['prompt_text'] ?? null;
    $promptTextB = $b['prompt_text'] ?? null;

    $messagesA = $a['messages'] ?? null;
    $messagesB = $b['messages'] ?? null;

    // Normalize messages: they might already be arrays (decoded JSON) depending on your trait.
    $messagesA = $this->normalizeJsonValue($messagesA);
    $messagesB = $this->normalizeJsonValue($messagesB);

    // Build diff strings (unified-ish)
    $promptTextDiff = $this->unifiedDiff(
      $promptTextA ?? '',
      $promptTextB ?? '',
      'prompt_text'
    );

    $messagesDiff = $this->unifiedDiff(
      $this->toPrettyJson($messagesA),
      $this->toPrettyJson($messagesB),
      'messages'
    );

    return [
      'ok' => true,
      'a' => [
        'id' => $a['id'] ?? $promptVersionIdA,
        'prompt_id' => $a['prompt_id'] ?? null,
        'version' => $a['version'] ?? null,
      ],
      'b' => [
        'id' => $b['id'] ?? $promptVersionIdB,
        'prompt_id' => $b['prompt_id'] ?? null,
        'version' => $b['version'] ?? null,
      ],
      'changes' => [
        'prompt_text' => [
          'changed' => ($promptTextA !== $promptTextB),
          'before' => $promptTextA,
          'after' => $promptTextB,
          'diff' => $promptTextDiff,
        ],
        'messages' => [
          'changed' => ($this->toPrettyJson($messagesA) !== $this->toPrettyJson($messagesB)),
          'before' => $messagesA,
          'after' => $messagesB,
          'diff' => $messagesDiff,
        ],
      ],
    ];
  }

  private function normalizeJsonValue(mixed $value): mixed
  {
    if ($value === null) {
      return null;
    }
    if (is_array($value)) {
      return $value;
    }
    if (is_string($value)) {
      $trim = trim($value);
      if ($trim === '') {
        return null;
      }
      $decoded = json_decode($value, true);
      return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }
    return $value;
  }

  private function toPrettyJson(mixed $value): string
  {
    if ($value === null) {
      return '';
    }
    if (is_string($value)) {
      // If it is already a string (and not valid JSON), keep it.
      $decoded = json_decode($value, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return $value;
      }
      $value = $decoded;
    }
    return (string)json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  /**
   * A small, dependency-free unified diff (line-based).
   * Produces a readable output for UI without pulling in libraries.
   */
  private function unifiedDiff(string $before, string $after, string $label = 'diff'): string
  {
    $a = preg_split("/\r\n|\n|\r/", $before);
    $b = preg_split("/\r\n|\n|\r/", $after);

    // Fast path
    if ($before === $after) {
      return "--- {$label}:before\n+++ {$label}:after\n@@ (no changes)\n";
    }

    $ops = $this->diffOps($a, $b);

    $out = [];
    $out[] = "--- {$label}:before";
    $out[] = "+++ {$label}:after";
    $out[] = "@@";

    foreach ($ops as $op) {
      [$tag, $line] = $op;
      if ($tag === ' ') {
        $out[] = "  " . $line;
      } elseif ($tag === '-') {
        $out[] = "- " . $line;
      } elseif ($tag === '+') {
        $out[] = "+ " . $line;
      }
    }

    return implode("\n", $out) . "\n";
  }

  /**
   * Compute a minimal-ish diff using LCS DP.
   * Returns operations: [' ', line] unchanged, ['-', line] removed, ['+', line] added.
   */
  private function diffOps(array $a, array $b): array
  {
    $n = count($a);
    $m = count($b);

    // DP table for LCS lengths
    $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

    for ($i = $n - 1; $i >= 0; $i--) {
      for ($j = $m - 1; $j >= 0; $j--) {
        if ($a[$i] === $b[$j]) {
          $dp[$i][$j] = $dp[$i + 1][$j + 1] + 1;
        } else {
          $dp[$i][$j] = max($dp[$i + 1][$j], $dp[$i][$j + 1]);
        }
      }
    }

    // Backtrack to ops
    $i = 0;
    $j = 0;
    $ops = [];

    while ($i < $n && $j < $m) {
      if ($a[$i] === $b[$j]) {
        $ops[] = [' ', $a[$i]];
        $i++;
        $j++;
        continue;
      }

      // Prefer the move that keeps longer LCS
      if ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
        $ops[] = ['-', $a[$i]];
        $i++;
      } else {
        $ops[] = ['+', $b[$j]];
        $j++;
      }
    }

    while ($i < $n) {
      $ops[] = ['-', $a[$i]];
      $i++;
    }
    while ($j < $m) {
      $ops[] = ['+', $b[$j]];
      $j++;
    }

    return $ops;
  }
}
