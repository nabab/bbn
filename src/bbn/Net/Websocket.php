<?php
declare(strict_types=1);

namespace bbn\Net;

use RuntimeException;
use Throwable;
use JsonException;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server;

final class Websocket
{
  private Server $server;

  /**
   * @var array<string, callable>
   */
  private array $handlers = [];

  /**
   * userId => [fd => true]
   *
   * @var array<string, array<int, bool>>
   */
  private array $users = [];

  /**
   * fd => userId
   *
   * @var array<int, string>
   */
  private array $fdUsers = [];

  public function __construct(
    private string $host = '0.0.0.0',
    private int $port = 9000
  ) {
    $this->server = new Server(
      $this->host,
      $this->port
    );

    $this->server->set([
      'worker_num' => 2,
      'heartbeat_check_interval' => 30,
      'heartbeat_idle_time' => 90,
      'package_max_length' => 2 * 1024 * 1024,
    ]);

    $this->registerEvents();
  }

  /**
   * Add a handler for a specific message type.
   *
   * Callback receives:
   *
   *   $data   The "data" value from the JSON message
   *   $fd     Client connection ID
   *   $socket This WebSocketServer instance
   */
  public function on(
    string $type,
    callable $callback
  ): self {
    $this->handlers[$type] = $callback;

    return $this;
  }

  /**
   * Send a JSON message to one client.
   */
  public function send(
    int $fd,
    string $type,
    mixed $data = null
  ): bool {
    if (!$this->server->isEstablished($fd)) {
      return false;
    }

    try {
      $json = json_encode(
        [
          'type' => $type,
          'data' => $data,
        ],
        JSON_THROW_ON_ERROR
      );

      return $this->server->push(
        $fd,
        $json
      );
    } catch (Throwable $e) {
      $this->log(
        "Unable to send to {$fd}: {$e->getMessage()}"
      );

      return false;
    }
  }

  /**
   * Send a message to every connected WebSocket client.
   */
  public function broadcast(
    string $type,
    mixed $data = null,
    ?int $exceptFd = null
  ): void {
    foreach ($this->server->connections as $fd) {
      $fd = (int) $fd;

      if ($fd === $exceptFd) {
        continue;
      }

      if (!$this->server->isEstablished($fd)) {
        continue;
      }

      $this->send(
        $fd,
        $type,
        $data
      );
    }
  }

  public function start(): void
  {
    $this->server->start();
  }

  public function bindUser(
      int $fd,
      int|string $userId
  ): void {
      $userId = (string) $userId;

      // Remove previous association if there is one.
      $this->unbindUser($fd);

      $this->users[$userId][$fd] = true;
      $this->fdUsers[$fd] = $userId;

      $this->log(
          "Bound fd {$fd} to user {$userId}"
      );
  }


  public function unbindUser(int $fd): void
  {
      $userId = $this->fdUsers[$fd] ?? null;

      if ($userId === null) {
          return;
      }

      unset(
          $this->fdUsers[$fd],
          $this->users[$userId][$fd]
      );

      if (empty($this->users[$userId])) {
          unset($this->users[$userId]);
      }

      $this->log(
          "Unbound fd {$fd} from user {$userId}"
      );
  }


  public function sendToUser(
      int|string $userId,
      string $type,
      mixed $data = null
  ): void {
      $userId = (string) $userId;

      foreach ($this->users[$userId] ?? [] as $fd => $_) {
          $this->send(
              $fd,
              $type,
              $data
          );
      }
  }
  private function registerEvents(): void
  {
    $this->server->on(
      'Start',
      function (): void {
        $this->log(
          "WebSocket server listening on {$this->host}:{$this->port}"
        );
      }
    );

    $this->server->on(
      'Open',
      function (
        Server $server,
        Request $request
      ): void {
        $fd = $request->fd;

        var_dump("REQUEST ON OPEN", $request->getData(), $request->cookie);
        $this->log(
          "Client connected: {$fd}"
        );

        $this->send(
          $fd,
          'connected',
          [
            'fd' => $fd,
            'time' => time(),
          ]
        );
      }
    );

    $this->server->on(
      'Message',
      function (
        Server $server,
        Frame $frame
      ): void {
        $this->handleMessage($frame);
      }
    );

    $this->server->on(
      'Close',
      function (
        Server $server,
        int $fd
      ): void {
        $this->unbindUser($fd);
        $this->log("Client closed: {$fd}");
      }
    );

    $this->server->on(
      'Disconnect',
      function (
        Server $server,
        int $fd
      ): void {
        $this->unbindUser($fd);
        $this->log("Client disconnected: {$fd}");
      }
    );

    $this->server->on(
      'WorkerError',
      function (
        Server $server,
        int $workerId,
        int $workerPid,
        int $exitCode,
        int $signal
      ): void {
        $this->log(
          sprintf(
            'Worker error: worker=%d pid=%d exit=%d signal=%d',
            $workerId,
            $workerPid,
            $exitCode,
            $signal
          )
        );
      }
    );

    $this->server->on(
      'Shutdown',
      function (): void {
        $this->log(
          'WebSocket server stopped'
        );
      }
    );
  }

  private function handleMessage(Frame $frame): void
  {
    $fd = $frame->fd;

    try {
      $message = json_decode(
        $frame->data,
        true,
        512,
        JSON_THROW_ON_ERROR
      );

      if (!is_array($message)) {
        throw new RuntimeException(
          'Message must be a JSON object'
        );
      }

      if (!array_key_exists('type', $message)) {
        $this->send(
          $fd,
          'error',
          [
            'message' => 'Missing message type',
          ]
        );

        return;
      }

      if (!is_string($message['type'])) {
        $this->send(
          $fd,
          'error',
          [
            'message' => 'Message type must be a string',
          ]
        );

        return;
      }

      $type = $message['type'];
      $data = $message['data'] ?? null;

      $this->log(
        sprintf(
          'Message from %d: %s',
          $fd,
          $frame->data
        )
      );

      $handler = $this->handlers[$type] ?? null;

      if ($handler === null) {
        $this->send(
          $fd,
          'error',
          [
            'message' => 'Unknown message type',
            'type' => $type,
          ]
        );

        return;
      }

      $handler(
        $data,
        $fd,
        $this
      );
    } catch (JsonException $e) {
      $this->log(
        "Invalid JSON from {$fd}: {$e->getMessage()}"
      );

      $this->send(
        $fd,
        'error',
        [
          'message' => 'Invalid JSON',
        ]
      );
    } catch (Throwable $e) {
      $this->log(
        "Error processing message from {$fd}: {$e->getMessage()}"
      );

      $this->send(
        $fd,
        'error',
        [
          'message' => 'Internal server error',
        ]
      );
    }
  }

  private function log(string $message): void
  {
    echo sprintf(
      "[%s] %s\n",
      date('Y-m-d H:i:s'),
      $message
    );
  }
}

