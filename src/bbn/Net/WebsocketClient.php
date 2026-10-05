<?php
declare(strict_types=1);

namespace bbn\Net;

use Redis;
use RuntimeException;
use Throwable;

/** Redis publisher, NOT a WebSocket client. */
final class WebsocketClient
{
  private Redis $redis;

  public function __construct(
    string $host = '127.0.0.1',
    int $port = 6379,
    private string $stream = 'bbn:websocket',
    ?string $password = null,
    int|string $database = 0,
    ?string $username = null
  ) {
    $this->redis = new Redis();
    try {
      if (!$this->redis->connect($host, $port, 2.0)) {
        throw new RuntimeException('Redis connection failed.');
      }
      $this->redis->setOption(Redis::OPT_READ_TIMEOUT, 5.0);
      if ($password !== null && !$this->redis->auth(
        $username === null ? $password : [$username, $password]
      )) {
        throw new RuntimeException('Redis authentication failed.');
      }
      $db = filter_var($database, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
      if ($db === false || !$this->redis->select($db)) {
        throw new RuntimeException('Invalid Redis database or SELECT failed.');
      }
    } catch (Throwable $e) {
      $this->close();
      throw $e;
    }
  }

  /** Returns a stream entry ID; this is NOT a browser-delivery receipt. */
  public function send(string $type, mixed $data = null): string|false
  {
    $message = WebsocketProtocol::redis(['type' => $type, 'data' => $data]);
    $json = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    if (strlen($json) > 1024 * 1024) {
      throw new RuntimeException('Redis WebSocket messages are limited to 1 MiB.');
    }
    // Deliberately no automatic trimming: retention is an operational policy.
    return $this->redis->xAdd($this->stream, '*', ['json' => $json]);
  }

  public function user(int|string $userId, string $type, mixed $data = null): string|false
  {
    return $this->send('user', ['user_id' => $userId, 'type' => $type, 'data' => $data]);
  }

  public function event(string $event, mixed $data = null): string|false
  {
    return $this->send('event', ['event' => $event, 'data' => $data]);
  }

  public function broadcast(string $type, mixed $data = null): string|false
  {
    return $this->send('broadcast', ['type' => $type, 'data' => $data]);
  }

  public function close(): void
  {
    try {
      $this->redis->close();
    } catch (Throwable) {
      // Closing a disconnected connection is harmless.
    }
  }
}
