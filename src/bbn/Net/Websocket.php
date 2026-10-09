<?php
declare(strict_types=1);

namespace bbn\Net;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;
use bbn\X;
use Swoole\Http\Request;
use Swoole\Process;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/** One Swoole worker, local user/subscription indexes, optional Redis bridge. */
final class Websocket
{
  public const MSG_SUBSCRIBE = 'subscribe';
  public const MSG_UNSUBSCRIBE = 'unsubscribe';
  public const EVT_CONNECTED = 'connected';
  public const EVT_DISCONNECTED = 'disconnected';
  public const EVT_SUBSCRIBED = 'subscribed';
  public const EVT_UNSUBSCRIBED = 'unsubscribed';
  public const EVT_ERROR = 'error';

  private Server $server;
  private bool $started = false;
  private bool $redisProcessRegistered = false;
  private array $handlers = [];
  private array $connectedCallbacks = [];
  private array $disconnectedCallbacks = [];
  private array $users = [];
  private array $fdUsers = [];
  private array $subscriptions = [];
  private array $eventSubscribers = [];
  private array $activeFds = [];
  private ?\Closure $authenticator = null;
  private ?\Closure $subscriptionAuthorizer = null;
  private ?array $origins = null;

  public function __construct(
    private string $host = '0.0.0.0',
    private int $port = 9000,
    int $workerNum = 1
  ) {
    if ($workerNum !== 1) {
      throw new InvalidArgumentException('This implementation requires workerNum=1.');
    }
    // BASE closes connections on worker restart: clients reconnect and restore state.
    $this->server = new Server($host, $port, SWOOLE_BASE);
    $this->server->set([
      'worker_num' => 1,
      'heartbeat_check_interval' => 30,
      'heartbeat_idle_time' => 90,
      'package_max_length' => 2 * 1024 * 1024,
    ]);
    $this->registerEvents();
  }

  public function on(string $type, callable $callback): self
  {
    $this->handlers[WebsocketProtocol::name($type, true)] = $callback;
    return $this;
  }

  public function onConnected(callable $callback): self
  {
    $this->connectedCallbacks[] = $callback;
    return $this;
  }

  public function onDisconnected(callable $callback): self
  {
    $this->disconnectedCallbacks[] = $callback;
    return $this;
  }

  /** Callback(Request $request, self $socket): int|string|null. Null rejects. */
  public function authenticateWith(callable $callback): self
  {
    $this->authenticator = \Closure::fromCallable($callback);
    return $this;
  }

  /** Exact browser Origin allowlist. Missing Origin is rejected when configured. */
  public function allowOrigins(array $origins): self
  {
    $this->origins = $origins;
    return $this;
  }

  /** Callback(int $fd, string $event, self $socket): bool. Client subscriptions default to DENY. */
  public function authorizeSubscriptions(callable $callback): self
  {
    $this->subscriptionAuthorizer = \Closure::fromCallable($callback);
    return $this;
  }

  public function send(int $fd, string $type, mixed $data = null): bool
  {
    try {
      WebsocketProtocol::name($type);
      if (!isset($this->activeFds[$fd])) {
        X::log("Send skipped: fd={$fd} type={$type}; no active application connection", str_replace('\\', '-', __CLASS__));
        return false;
      }
      if (!$this->server->isEstablished($fd)) {
        X::log("Send skipped: fd={$fd} type={$type}; WebSocket is not established", str_replace('\\', '-', __CLASS__));
        return false;
      }
      $json = json_encode(['type' => $type, 'data' => $data],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
      if (strlen($json) > 2 * 1024 * 1024) {
        throw new RuntimeException('Outgoing message is too large.');
      }

      $pushStarted = hrtime(true);
      $result = $this->server->push($fd, $json);

      if ($result === true) {
        return true;
      }

      // Capture errors BEFORE logging or making other server calls.
      $errorCode = $this->server->getLastError();

      $systemErrno = function_exists('swoole_errno')
        ? \swoole_errno()
        : null;

      $pushMs = (hrtime(true) - $pushStarted) / 1_000_000;

      $errorMessage = function_exists('swoole_strerror')
        ? \swoole_strerror($errorCode, 9)
        : null;

      // This is a snapshot AFTER the failed write.
      $info = $this->server->getClientInfo($fd);

      X::log(
        'WebSocket push diagnostic: ' . json_encode(
          [
            'time' => microtime(true),
            'container' => gethostname(),
            'pid' => getmypid(),
            'worker_id' => $this->server->worker_id,
            'fd' => $fd,
            'type' => $type,
            'bytes' => strlen($json),
            'return_type' => get_debug_type($result),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'last_system_errno' => $systemErrno,
            'push_ms' => round($pushMs, 3),
            'established_after' => $this->server->isEstablished($fd),
            'websocket_status' => is_array($info)
              ? ($info['websocket_status'] ?? null)
              : null,
            'socket_fd' => is_array($info)
              ? ($info['socket_fd'] ?? null)
              : null,
            'close_errno' => is_array($info)
              ? ($info['close_errno'] ?? null)
              : null,
          ],
          JSON_THROW_ON_ERROR
          | JSON_UNESCAPED_SLASHES
          | JSON_INVALID_UTF8_SUBSTITUTE
        ), str_replace('\\', '-', __CLASS__)
      );

      return false;

    }
    catch (Throwable $e) {
      X::log('Send failed for fd ' . $fd . ': ' . $e->getMessage(), str_replace('\\', '-', __CLASS__));
      return false;
    }
  }

  public function broadcast(string $type, mixed $data = null, ?int $exceptFd = null): void
  {
    WebsocketProtocol::name($type, true);
    // Only application-ready connections; no unauthenticated/handshaking clients.
    foreach (array_keys($this->activeFds) as $fd) {
      if ($fd !== $exceptFd) {
        $this->send($fd, $type, $data);
      }
    }
  }

  public function emit(string $event, mixed $data = null, ?int $exceptFd = null): int
  {
    WebsocketProtocol::name($event, true);
    $sent = 0;
    foreach (array_keys($this->eventSubscribers[$event] ?? []) as $fd) {
      if ($fd === $exceptFd) {
        continue;
      }
      if (!$this->server->isEstablished($fd)) {
        $this->handleDisconnect($fd, 'stale');
        continue;
      }
      if ($this->send($fd, $event, $data)) {
        $sent++;
      }
    }
    return $sent;
  }

  /** Trusted server-side subscription. Client requests additionally require authorization. */
  public function subscribe(int $fd, string|array $events): array
  {
    if (!isset($this->activeFds[$fd]) || !$this->server->isEstablished($fd)) {
      throw new RuntimeException('Connection is not established.');
    }
    $events = WebsocketProtocol::events($events);
    $combined = array_unique(array_merge(array_keys($this->subscriptions[$fd] ?? []), $events));
    if (count($combined) > 256) {
      throw new InvalidArgumentException('Too many subscriptions.');
    }
    foreach ($events as $event) {
      $this->subscriptions[$fd][$event] = true;
      $this->eventSubscribers[$event][$fd] = true;
    }
    return $events;
  }

  public function unsubscribe(int $fd, string|array|null $events = null): array
  {
    // Local cleanup must also work after the transport has closed.
    $events = $events === null || $events === '' || $events === []
      ? array_keys($this->subscriptions[$fd] ?? [])
      : WebsocketProtocol::events($events);
    $removed = [];
    foreach ($events as $event) {
      if ($this->removeSubscription($fd, $event)) {
        $removed[] = $event;
      }
    }
    return $removed;
  }

  public function isSubscribed(int $fd, string $event): bool
  {
    return isset($this->subscriptions[$fd][$event]);
  }

  public function getSubscriptions(int $fd): array
  {
    return array_keys($this->subscriptions[$fd] ?? []);
  }

  public function bindUser(int $fd, int|string $userId): void
  {
    if (!$this->server->isEstablished($fd)) {
      throw new RuntimeException('Cannot bind an unestablished connection.');
    }
    $id = WebsocketProtocol::userId($userId);
    $this->unbindUser($fd);
    $this->users[$id][$fd] = true;
    $this->fdUsers[$fd] = $id;
  }

  public function getUserId(int $fd): ?string
  {
    return $this->fdUsers[$fd] ?? null;
  }

  public function unbindUser(int $fd): void
  {
    $id = $this->fdUsers[$fd] ?? null;
    if ($id === null) {
      return;
    }
    unset($this->fdUsers[$fd], $this->users[$id][$fd]);
    if (!$this->users[$id]) {
      unset($this->users[$id]);
    }
  }

  public function sendToUser(int|string $userId, string $type, mixed $data = null): void
  {
    foreach (array_keys($this->users[(string) $userId] ?? []) as $fd) {
      $this->send($fd, $type, $data);
    }
  }

  public function disconnect(int $fd, int $code = 1000, string $reason = ''): bool
  {
    try {
      return (bool) $this->server->disconnect($fd, $code, $reason);
    } finally {
      $this->handleDisconnect($fd, $reason ?: 'disconnect');
    }
  }

  public function start(): void
  {
    if ($this->started) {
      throw new RuntimeException('Server has already been started.');
    }
    $this->started = true;
    if (!$this->server->start()) {
      throw new RuntimeException('Unable to start WebSocket server.');
    }
  }

  public function shutdown(): void
  {
    foreach ($this->server->connections as $fd) {
      try { $this->server->close((int) $fd); } catch (Throwable) {}
    }
    $this->server->shutdown();
  }

  public function useRedisStream(
    string $host = '127.0.0.1',
    int $port = 6379,
    string $stream = 'bbn:websocket',
    string $group = 'websocket',
    ?string $password = null,
    int|string $database = 0,
    ?string $username = null
  ): self {
    if ($this->started || $this->redisProcessRegistered) {
      throw new RuntimeException('Configure Redis exactly once, before start().');
    }
    $db = filter_var($database, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($db === false) {
      throw new InvalidArgumentException('Invalid Redis database.');
    }
    if (!class_exists(\Redis::class) || !method_exists(\Redis::class, 'xAutoClaim')) {
      throw new RuntimeException('phpredis with xAutoClaim() support is required.');
    }
    $config = compact('host', 'port', 'stream', 'group', 'password', 'username');
    $config['database'] = $db;
    $server = $this->server;
    $process = new Process(function () use ($config, $server): void {
      $bridge = new RedisStreamBridge(
        $config,
        static fn(string $json): bool => (bool) $server->sendMessage($json, 0),
        fn(string $message) => X::log($message, str_replace('\\', '-', __CLASS__))
      );
      $bridge->run();
    }, false, 2, false); // Blocking isolated process; no coroutine/event-loop dependency.
    $server->addProcess($process);
    $this->redisProcessRegistered = true;
    return $this;
  }

  /**
   * Register server events (back-end)
   */
  private function registerEvents(): void
  {
    $this->server->on('Start', function (): void {
      X::log("WebSocket server listening on {$this->host}:{$this->port}", str_replace('\\', '-', __CLASS__));
    });
    $this->server->on('Open', function (Server $server, Request $request): void {
      $fd = (int) $request->fd;
      X::log("WebSocket Open fd={$fd}", str_replace('\\', '-', __CLASS__));
      $this->cleanupFd($fd);
      unset($this->activeFds[$fd]);
      try {
        if ($this->origins !== null
          && !in_array($request->header['origin'] ?? '', $this->origins, true)
        ) {
          X::log("WebSocket origin rejected fd={$fd}", str_replace('\\', '-', __CLASS__));
          $server->disconnect($fd, 4003, 'Origin not allowed');
          return;
        }
        if ($this->authenticator !== null) {
          X::log("WebSocket authentication started fd={$fd}", str_replace('\\', '-', __CLASS__));
          $id = ($this->authenticator)($request, $this);
          if ($id === null) {
            X::log("WebSocket authentication rejected fd={$fd}", str_replace('\\', '-', __CLASS__));
            $server->disconnect($fd, 4001, 'Authentication required');
            return;
          }
          $this->bindUser($fd, WebsocketProtocol::userId($id));
          X::log("WebSocket authentication completed fd={$fd}", str_replace('\\', '-', __CLASS__));
        } else {
          X::log("WebSocket authentication not configured fd={$fd}", str_replace('\\', '-', __CLASS__));
        }
        if (!$server->isEstablished($fd)) {
          X::log("WebSocket setup aborted: fd={$fd} is not established", str_replace('\\', '-', __CLASS__));
          $this->cleanupFd($fd);
          return;
        }
        $this->activeFds[$fd] = true;
        foreach ($this->connectedCallbacks as $index => $callback) {
          X::log("WebSocket connected callback {$index} started fd={$fd}", str_replace('\\', '-', __CLASS__));
          $callback($fd, $request);
          X::log("WebSocket connected callback {$index} completed fd={$fd}", str_replace('\\', '-', __CLASS__));
          if (!isset($this->activeFds[$fd])) {
            X::log("WebSocket setup stopped by callback fd={$fd}", str_replace('\\', '-', __CLASS__));
            return;
          }
        }
        // This means application-ready, not merely HTTP upgrade completed.
        // A failed readiness frame must not leave a silent, unready connection.
        if (!$this->send($fd, self::EVT_CONNECTED, ['fd' => $fd, 'time' => time()])) {
          X::log("WebSocket connected envelope FAILED fd={$fd}", str_replace('\\', '-', __CLASS__));
          $this->disconnect($fd, 4000, 'Unable to send server readiness');
          return;
        }
        X::log("WebSocket connected envelope queued fd={$fd}", str_replace('\\', '-', __CLASS__));
      } catch (Throwable $e) {
        X::log('Connection setup failed: ' . $e->getMessage(), str_replace('\\', '-', __CLASS__));
        $this->disconnect($fd, 4001, 'Connection rejected');
      }
    });
    $this->server->on('Message', function (Server $server, Frame $frame): void {
      $this->handleMessage($frame);
    });
    $this->server->on('PipeMessage', function (Server $server, int $sourceWorkerId, mixed $raw): void {
      try {
        if (!is_string($raw)) {
          throw new InvalidArgumentException('Pipe payload must be JSON.');
        }
        $message = WebsocketProtocol::redis(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
        $data = $message['data'];
        switch ($message['type']) {
          case 'user':
            $this->sendToUser($data['user_id'], $data['type'], $data['data'] ?? null);
            break;
          case 'event':
            $this->emit($data['event'], $data['data'] ?? null);
            break;
          case 'broadcast':
            $this->broadcast($data['type'], $data['data'] ?? null);
            break;
        }
      } catch (Throwable $e) {
        X::log('Pipe dispatch failed: ' . $e->getMessage(), str_replace('\\', '-', __CLASS__));
      }
    });
    $this->server->on('Close', function (Server $server, int $fd): void {
      $this->handleDisconnect($fd, 'close');
    });
    $this->server->on('WorkerError', function (Server $server, int $id, int $pid, int $code, int $signal): void {
      X::log("Worker failure: id=$id pid=$pid exit=$code signal=$signal", str_replace('\\', '-', __CLASS__));
    });
    $this->server->on('Shutdown', function (): void { X::log('WebSocket server stopped', str_replace('\\', '-', __CLASS__)); });
  }

  private function handleMessage(Frame $frame): void
  {
    $fd = (int) $frame->fd;
    if (!isset($this->activeFds[$fd])) {
      return;
    }
    try {
      $message = json_decode($frame->data, true, 512, JSON_THROW_ON_ERROR);
      if (!is_array($message)) {
        throw new InvalidArgumentException('Message must be an object.');
      }
      $type = WebsocketProtocol::name($message['type'] ?? null);
      X::log("WebSocket Message fd={$fd} type={$type}", str_replace('\\', '-', __CLASS__));
      $data = $message['data'] ?? null;
      X::log($data, str_replace('\\', '-', __CLASS__));
      if ($type === self::MSG_SUBSCRIBE) {
        if (!is_string($data) && !is_array($data)) {
          throw new InvalidArgumentException('Subscription data must be a string or array.');
        }
        $events = WebsocketProtocol::events($data);
        foreach ($events as $event) {
          if ($this->subscriptionAuthorizer === null
            || !($this->subscriptionAuthorizer)($fd, $event, $this)
          ) {
            throw new InvalidArgumentException('Subscription not authorized.');
          }
        }
        $this->send($fd, self::EVT_SUBSCRIBED, ['fd' => $fd, 'events' => $this->subscribe($fd, $events)]);
        return;
      }
      if ($type === self::MSG_UNSUBSCRIBE) {
        if ($data !== null && !is_string($data) && !is_array($data)) {
          throw new InvalidArgumentException('Unsubscription data must be a string, array or null.');
        }
        $this->send($fd, self::EVT_UNSUBSCRIBED, ['fd' => $fd, 'events' => $this->unsubscribe($fd, $data)]);
        return;
      }
      WebsocketProtocol::name($type, true);
      if (!isset($this->handlers[$type])) {
        throw new InvalidArgumentException('Unknown message type: ' . $type);
      }
      ($this->handlers[$type])($data, $fd, $this);
    } catch (JsonException) {
      $this->send($fd, self::EVT_ERROR, ['message' => 'Invalid JSON']);
    } catch (InvalidArgumentException $e) {
      $this->send($fd, self::EVT_ERROR, ['message' => $e->getMessage()]);
    } catch (Throwable $e) {
      X::log('Message processing failed: ' . $e->getMessage(), str_replace('\\', '-', __CLASS__));
      $this->send($fd, self::EVT_ERROR, ['message' => 'Internal server error']);
    }
  }

  private function handleDisconnect(int $fd, string $reason): void
  {
    $wasActive = isset($this->activeFds[$fd]);
    unset($this->activeFds[$fd]);
    $this->cleanupFd($fd);
    if ($wasActive) {
      foreach ($this->disconnectedCallbacks as $callback) {
        try { $callback($fd, $reason); }
        catch (Throwable $e) { X::log('Disconnect callback failed: ' . $e->getMessage(), str_replace('\\', '-', __CLASS__)); }
      }
    }
  }

  private function cleanupFd(int $fd): void
  {
    $this->unbindUser($fd);
    $this->unsubscribe($fd);
  }

  private function removeSubscription(int $fd, string $event): bool
  {
    if (!isset($this->subscriptions[$fd][$event])) {
      return false;
    }
    unset($this->subscriptions[$fd][$event], $this->eventSubscribers[$event][$fd]);
    if (!$this->subscriptions[$fd]) {
      unset($this->subscriptions[$fd]);
    }
    if (empty($this->eventSubscribers[$event])) {
      unset($this->eventSubscribers[$event]);
    }
    return true;
  }
}
