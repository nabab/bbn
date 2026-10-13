<?php declare(strict_types=1);

namespace bbn\Net;

use bbn\Cache;
use bbn\Str;
use bbn\X;
use InvalidArgumentException;
use JsonException;
use Redis;
use RuntimeException;

/**
 * Redis publisher, NOT a WebSocket client.
 */
final class WebsocketClient
{
  private const MODES = [
    'user',
    'event',
    'broadcast',
    'handler',
  ];

  public function __construct(
    private string $stream = 'appui:websocket'
  ) {
    $this->stream = WebsocketProtocol::redisKey($stream);
  }

  /**
   * Send an application event to browser clients subscribed to that event.
   */
  public function event(string $event, mixed $data = null): string|false
  {
    return $this->dispatch('event', [
      'event' => $event,
      'data' => $data,
    ]);
  }

  /**
   * Send a message to all active connections of one user.
   */
  public function user(int|string $userId, string $type, mixed $data = null): string|false
  {
    return $this->dispatch('user', [
      'user_id' => $userId,
      'type' => $type,
      'data' => $data,
    ]);
  }

  /**
   * Send a message to all active browser clients.
   */
  public function broadcast(string $type, mixed $data = null): string|false
  {
    return $this->dispatch('broadcast', [
      'type' => $type,
      'data' => $data,
    ]);
  }

  /**
   * Trigger a server-side handler registered with Websocket::on().
   * This is NOT sent directly to browser clients.
   */
  public function trigger(string $type, mixed $data = null, ?int $fd = null): string|false
  {
    return $this->dispatch('handler', [
      'type' => $type,
      'data' => $data,
      'fd' => (int) ($fd ?? 0),
    ]);
  }

  /**
   * Low-level Redis dispatcher.
   *
   * If you do not need this publicly, make it private or protected.
   */
  public function dispatch(string $mode, array $payload): string|false
  {
    if (!in_array($mode, self::MODES, true)) {
      throw new InvalidArgumentException("Unknown WebSocket Redis delivery mode: {$mode}");
    }

    $message = WebsocketProtocol::redis([
      'type' => $mode,
      'data' => $payload,
    ]);

    try {
      $json = json_encode(
        $message,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
      );
    } catch (JsonException $e) {
      throw new RuntimeException('Redis WebSocket message is not JSON serializable.', 0, $e);
    }

    if (strlen($json) > 1_048_576) {
      $logFile = Str::genpwd();

      X::log("Redis WebSocket message is too large, check {$logFile}", '_IMPORTANT-too-large');
      X::log($json, $logFile);

      throw new RuntimeException('Redis WebSocket messages are limited to 1 MiB.');
    }

    $result = $this->redis()->xAdd(
      $this->stream,
      '*',
      ['json' => $json]
    );

    if ($result === false) {
      X::log(
        'Redis WebSocket xAdd failed for stream ' . $this->stream,
        str_replace('\\', '-', self::class)
      );
    }

    return $result;
  }

  private function redis(): Redis
  {
    $redis = Cache::getEngine()->getObj();

    if (!$redis instanceof Redis) {
      throw new RuntimeException('Cache engine did not return a Redis object.');
    }

    return $redis;
  }
}
