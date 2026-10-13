<?php declare(strict_types=1);

namespace bbn\Net;

use bbn\Cache;
use bbn\X;
use Closure;
use Redis;
use RuntimeException;
use Throwable;

/**
 * Blocking Redis consumer: run ONLY in the dedicated Swoole user process.
 * Uses Cache::getEngine()->getObj() for the Redis connection.
 */
final class RedisStreamBridge
{
  private bool $running = true;

  public function __construct(
    private string $stream,
    private string $group,
    private Closure $handoff
  ) {}

  public function run(): void
  {
    if (!class_exists(Redis::class)) {
      throw new RuntimeException('phpredis is required for the Redis stream bridge.');
    }

    $canAutoClaim = method_exists(Redis::class, 'xAutoClaim');

    if (!$canAutoClaim) {
      X::log(
        'XAUTOCLAIM is not available in this phpredis version; stale consumer recovery is disabled.',
        'socket-core'
      );
    }

    // pcntl is appropriate here: this process uses blocking I/O, not an event loop.
    if (function_exists('pcntl_async_signals')) {
      pcntl_async_signals(true);
      pcntl_signal(SIGTERM, function (): void {
        $this->running = false;
      });
      pcntl_signal(SIGINT, function (): void {
        $this->running = false;
      });
    }

    // Stable across restarts. For a single server this is enough.
    $consumer = 'bridge';
    $delay = 1;

    while ($this->running) {
      $redis = null;

      try {
        $redis = Cache::getEngine()->getObj();

        if (!$redis instanceof Redis) {
          throw new RuntimeException('Cache engine did not return a Redis object.');
        }

        $this->ensureGroup($redis);

        X::log(
          'Redis consumer ready: stream=' . $this->stream . ' group=' . $this->group,
          'socket-core'
        );

        $delay = 1;
        $cursor = '0-0';
        $nextClaim = 0.0;

        while ($this->running) {
          // 1) Re-deliver this consumer's pending entries first.
          $pending = $this->read($redis, $consumer, '0');
          X::log(['pending', $pending], 'socket-core');
          if (!$this->deliver($redis, $pending)) {
            // Back off before retrying pending entries.
            sleep(1);
            continue;
          }

          // 2) Read new messages even if XAUTOCLAIM later fails.
          $new = $this->read($redis, $consumer, '>');
          $this->deliver($redis, $new);

          if (!$this->running) {
            break;
          }

          // 3) Recover stale entries from old consumers, but never let this block normal reads.
          if ($canAutoClaim && microtime(true) >= $nextClaim) {
            try {
              $redis->clearLastError();

              $claim = $redis->xAutoClaim(
                $this->stream,
                $this->group,
                $consumer,
                1_000,
                $cursor,
                20
              );

              $error = (string) $redis->getLastError();

              if ($error !== '') {
                X::log(sprintf(
                  'XAUTOCLAIM debug: stream=%s group=%s consumer=%s cursor=%s claim_type=%s last_error=%s',
                  $this->stream,
                  $this->group,
                  $consumer,
                  $cursor,
                  get_debug_type($claim),
                  $error === '' ? 'none' : $error
                ), 'socket-core');
              }

              $unsupported = $error !== '' && str_contains($error, 'Unknown subcommand');

              if ($unsupported) {
                $canAutoClaim = false;
                X::log(
                  'XAUTOCLAIM is not supported by this Redis server; disabling auto-claim.',
                  'socket-core'
                );
              }

              // Only throw for a real Redis error.
              if ($claim === false && $error !== '' && !$unsupported) {
                throw new RuntimeException($error);
              }

              if (is_array($claim)) {
                $cursor = (string) ($claim[0] ?? '0-0');
                $claimed = is_array($claim[1] ?? null) ? $claim[1] : [];

                if ($claimed !== []) {
                  $this->deliver($redis, $claimed);
                }

                $nextClaim = microtime(true) + (($cursor === '0-0') ? 5.0 : 0.0);
              } else {
                // No Redis error: treat as "nothing claimed" and back off a little.
                $nextClaim = microtime(true) + 5.0;
              }
            } catch (Throwable $claimError) {
              X::log($claimError->getMessage(), 'socket-core');
              $nextClaim = microtime(true) + 5.0;
            }
          }
        }
      } catch (Throwable $e) {
        if ($this->running) {
          X::log(
            sprintf(
              'Redis bridge error: %s: %s',
              get_class($e),
              substr($e->getMessage(), 0, 512)
            ),
            'socket-core'
          );

          // Optional: log a truncated trace if you need it.
          X::log(substr($e->getTraceAsString(), 0, 4096), 'socket-core');
        }
      } finally {
        if ($redis instanceof Redis) {
          try {
            $redis->close();
          } catch (Throwable) {
          }
        }
      }

      if ($this->running) {
        sleep($delay);
        $delay = min(10, $delay * 2);
      }
    }
  }

  private function ensureGroup(Redis $redis): void
  {
    $redis->clearLastError();

    try {
      $result = $redis->xGroup(
        'CREATE',
        $this->stream,
        $this->group,
        '0',  // You asked for 0: replay existing entries on first group creation.
        true
      );
    } catch (Throwable $e) {
      if (!str_contains($e->getMessage(), 'BUSYGROUP')) {
        throw $e;
      }

      return;
    }

    $error = $redis->getLastError();

    if ($result === false || $error) {
      if (!$error || !str_contains((string) $error, 'BUSYGROUP')) {
        throw new RuntimeException((string) ($error ?: 'XGROUP CREATE failed.'));
      }
    }

    $redis->clearLastError();
  }

  private function read(Redis $redis, string $consumer, string $id): array
  {
    $redis->clearLastError();

    // Do not specify BLOCK for pending-history reads.
    $args = [
      $this->group,
      $consumer,
      [$this->stream => $id],
      20,
    ];

    if ($id === '>') {
      $args[] = 1_000;
    }

    $result = $redis->xReadGroup(...$args);
    if ($result === false || $result === null) {
      $error = $redis->getLastError();

      if ($error) {
        throw new RuntimeException((string) $error);
      }

      return [];
    }

    if (!is_array($result)) {
      return [];
    }

    $entries = $result[$this->stream] ?? null;

    if (!empty($entries)) {
      X::log($entries, 'socket-core');
    }

    return is_array($entries) ? $entries : [];
  }

  private function deliver(Redis $redis, array $entries): bool
  {
    foreach ($entries as $id => $fields) {
      if (!$this->running) {
        return false;
      }

      $json = null;

      try {
        $json = is_array($fields) ? ($fields['json'] ?? null) : null;

        if (!is_string($json) || strlen($json) > 1_048_576) {
          throw new RuntimeException('Missing or oversized JSON payload.');
        }

        WebsocketProtocol::redis(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
      } catch (Throwable $e) {
        // Poison message: preserve and ACK.
        try {
          $deadId = $redis->xAdd(
            $this->stream . ':dead',
            '*',
            [
              'source_id' => (string) $id,
              'group' => $this->group,
              'reason' => substr($e->getMessage(), 0, 512),
              'json' => is_string($json) ? substr($json, 0, 64 * 1024) : '',
            ]
          );

          if ($deadId === false) {
            throw new RuntimeException('Could not write dead-letter entry.');
          }

          $this->ack($redis, (string) $id);
        } catch (Throwable $deadError) {
          X::log(
            'Dead-letter failed for stream entry ' . $id . ': ' . substr($deadError->getMessage(), 0, 256),
            'socket-core'
          );

          // If we cannot dead-letter or ACK, stop this batch.
          return false;
        }

        continue;
      }

      try {
        if (!(($this->handoff)($json))) {
          X::log("Worker IPC handoff failed for entry {$id}; will retry later.", 'socket-core');
          return false;  // do not ACK, stop this batch
        }

        $this->ack($redis, (string) $id);
      } catch (Throwable $e) {
        X::log(
          "Worker IPC handoff exception for entry {$id}: " . substr($e->getMessage(), 0, 256),
          'socket-core'
        );

        return false;  // do not ACK, stop this batch
      }
    }

    return true;
  }

  private function ack(Redis $redis, string $id): void
  {
    if ($redis->xAck($this->stream, $this->group, [$id]) === false) {
      throw new RuntimeException('XACK failed.');
    }
  }
}
