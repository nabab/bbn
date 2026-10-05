<?php
declare(strict_types=1);

namespace bbn\Net;

use Redis;
use RuntimeException;
use Throwable;

/**
 * Blocking Redis consumer: run ONLY in the dedicated Swoole user process.
 * Requires Redis >= 6.2 and phpredis with xAutoClaim().
 * Exactly one bridge process must own each consumer group.
 */
final class RedisStreamBridge
{
  private bool $running = true;

  public function __construct(
    private array $config,
    private \Closure $handoff,
    private \Closure $log
  ) {}

  public function run(): void
  {
    if (!method_exists(Redis::class, 'xAutoClaim')) {
      throw new RuntimeException('Upgrade phpredis: xAutoClaim() is required.');
    }
    // pcntl is appropriate here: this process uses blocking I/O, not an event loop.
    // Without pcntl, leave the default SIGTERM handling intact.
    if (function_exists('pcntl_async_signals')) {
      pcntl_async_signals(true);
      pcntl_signal(SIGTERM, function (): void { $this->running = false; });
      pcntl_signal(SIGINT, function (): void { $this->running = false; });
    }
    $consumer = 'bridge'; // Stable across restarts; one process per group only.
    $delay = 1;
    while ($this->running) {
      $redis = null;
      try {
        $redis = $this->connect();
        $this->ensureGroup($redis);
        ($this->log)('Redis consumer ready: stream=' . $this->config['stream'] . ' group=' . $this->config['group']);
        $delay = 1;
        $cursor = '0-0';
        $nextClaim = 0.0;
        while ($this->running) {
          // Replay this consumer's unacknowledged entries before requesting new ones.
          $pending = $this->read($redis, $consumer, '0');
          $this->deliver($redis, $pending);
          if (!$this->running) {
            break;
          }
          // Also recover entries belonging to old PID-named consumers.
          if (microtime(true) >= $nextClaim) {
            $redis->clearLastError();
            $claim = $redis->xAutoClaim(
              $this->config['stream'], $this->config['group'], $consumer,
              60000, $cursor, 20
            );
            if ($claim === false) {
              throw new RuntimeException($redis->getLastError() ?: 'XAUTOCLAIM failed.');
            }
            $cursor = (string) $claim[0];
            $this->deliver($redis, $claim[1] ?? []);
            $nextClaim = microtime(true) + ($cursor === '0-0' ? 5 : 0);
          }
          if (!$this->running) {
            break;
          }
          $this->deliver($redis, $this->read($redis, $consumer, '>'));
        }
      } catch (Throwable $e) {
        if ($this->running) {
          ($this->log)('Redis bridge: ' . $e->getMessage());
        }
      } finally {
        if ($redis instanceof Redis) {
          try { $redis->close(); } catch (Throwable) {}
        }
      }
      if ($this->running) {
        sleep($delay);
        $delay = min(10, $delay * 2);
      }
    }
  }

  private function connect(): Redis
  {
    $c = $this->config;
    $redis = new Redis();
    if (!$redis->connect($c['host'], $c['port'], 2.0)) {
      throw new RuntimeException('Redis connection failed.');
    }
    // XREADGROUP blocks for at most 1 s; socket read timeout is longer.
    $redis->setOption(Redis::OPT_READ_TIMEOUT, 5.0);
    if ($c['password'] !== null && !$redis->auth(
      $c['username'] === null ? $c['password'] : [$c['username'], $c['password']]
    )) {
      throw new RuntimeException('Redis authentication failed.');
    }
    if (!$redis->select($c['database'])) {
      throw new RuntimeException('Redis SELECT failed.');
    }
    return $redis;
  }

  private function ensureGroup(Redis $redis): void
  {
    $redis->clearLastError();
    try {
      $ok = $redis->xGroup(
        'CREATE', $this->config['stream'], $this->config['group'], '$', true
      );
      if ($ok === false) {
        throw new RuntimeException($redis->getLastError() ?: 'XGROUP CREATE failed.');
      }
    } catch (Throwable $e) {
      if (!str_contains($e->getMessage(), 'BUSYGROUP')) {
        throw $e;
      }
    }
    $redis->clearLastError();
  }

  private function read(Redis $redis, string $consumer, string $id): array
  {
    $redis->clearLastError();
    // Do not specify BLOCK for pending-history reads.
    $args = [$this->config['group'], $consumer, [$this->config['stream'] => $id], 20];
    if ($id === '>') {
      $args[] = 1000;
    }
    $result = $redis->xReadGroup(...$args);
    if ($result === false && $redis->getLastError()) {
      throw new RuntimeException($redis->getLastError());
    }
    return $result[$this->config['stream']] ?? [];
  }

  private function deliver(Redis $redis, array $entries): void
  {
    foreach ($entries as $id => $fields) {
      if (!$this->running) {
        return;
      }
      try {
        $json = is_array($fields) ? ($fields['json'] ?? null) : null;
        if (!is_string($json) || strlen($json) > 1024 * 1024) {
          throw new RuntimeException('Missing or oversized JSON payload.');
        }
        WebsocketProtocol::redis(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
      } catch (Throwable $e) {
        // Poison messages should not block the group forever. Preserve then ACK.
        $deadId = $redis->xAdd($this->config['stream'] . ':dead', '*', [
          'source_id' => (string) $id,
          'group' => $this->config['group'],
          'reason' => $e->getMessage(),
          'json' => is_string($json ?? null) ? $json : '',
        ]);
        if ($deadId === false) {
          throw new RuntimeException('Could not write dead-letter entry.');
        }
        ($this->log)('Dead-lettered Redis message ' . $id);
        $this->ack($redis, (string) $id);
        continue;
      }
      if (!(($this->handoff)($json))) {
        throw new RuntimeException('Worker IPC handoff failed; entry remains pending.');
      }
      // This ACK means IPC accepted the message, NOT that a browser received it.
      $this->ack($redis, (string) $id);
    }
  }

  private function ack(Redis $redis, string $id): void
  {
    if ($redis->xAck($this->config['stream'], $this->config['group'], [$id]) === false) {
      throw new RuntimeException('XACK failed.');
    }
  }
}
