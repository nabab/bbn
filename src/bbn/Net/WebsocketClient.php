<?php

declare(strict_types=1);

namespace bbn\Net;

use JsonException;
use Redis;
use RuntimeException;

final class WebsocketClient
{
  private Redis $redis;

  public function __construct(
    string $host = '127.0.0.1',
    int $port = 6379,
    private string $stream = 'bbn:websocket'
  ) {
    $this->redis = new Redis();

    if (!$this->redis->connect($host, $port)) {
      throw new RuntimeException(
        sprintf(
          'Unable to connect to Redis at %s:%d',
          $host,
          $port
        )
      );
    }
  }

  /**
   * Add a message to the WebSocket Redis Stream.
   */
  public function send(
    string $type,
    mixed $data = null
  ): string|false {
    $json = json_encode(
      [
        'type' => $type,
        'data' => $data,
      ],
      JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
    );

    return $this->redis->xAdd(
      $this->stream,
      '*',
      [
        'json' => $json,
      ]
    );
  }

  /**
   * Send a message to one user.
   */
  public function user(
    int|string $userId,
    string $type,
    mixed $data = null
  ): string|false {
    return $this->send(
      'user',
      [
        'user_id' => $userId,
        'type' => $type,
        'data' => $data,
      ]
    );
  }

  /**
   * Emit an event to subscribed clients.
   */
  public function event(
    string $event,
    mixed $data = null
  ): string|false {
    return $this->send(
      'event',
      [
        'event' => $event,
        'data' => $data,
      ]
    );
  }

  /**
   * Broadcast to every connected client.
   */
  public function broadcast(
    string $type,
    mixed $data = null
  ): string|false {
    return $this->send(
      'broadcast',
      [
        'type' => $type,
        'data' => $data,
      ]
    );
  }
}