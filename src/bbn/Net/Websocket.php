<?php
declare(strict_types=1);

namespace bbn\Net;

use JsonException;
use RuntimeException;
use bbn\X;
use Redis;
use RedisException;
use Swoole\Process;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Throwable;

/**
 * Swoole WebSocket server wrapper.
 *
 * It supports:
 * - JSON message routing by "type"
 * - client connect/disconnect callbacks
 * - client subscription/unsubscription to application events
 * - emitting application events only to subscribed clients
 */
final class Websocket
{
  /**
   * Client -> server message type used to subscribe to events.
   */
  public const MSG_SUBSCRIBE = 'subscribe';

  /**
   * Client -> server message type used to unsubscribe from events.
   */
  public const MSG_UNSUBSCRIBE = 'unsubscribe';

  /**
   * Server -> client event sent when a connection is opened.
   */
  public const EVT_CONNECTED = 'connected';

  /**
   * Reserved server-side event name for disconnection notifications.
   *
   * This is not automatically broadcast to other clients unless you emit it yourself.
   */
  public const EVT_DISCONNECTED = 'disconnected';

  /**
   * Server -> client acknowledgement sent after a successful subscription.
   */
  public const EVT_SUBSCRIBED = 'subscribed';

  /**
   * Server -> client acknowledgement sent after a successful unsubscription.
   */
  public const EVT_UNSUBSCRIBED = 'unsubscribed';

  /**
   * Server -> client error event.
   */
  public const EVT_ERROR = 'error';

  /**
   * Message/event names reserved by this class.
   *
   * You cannot register custom handlers for these with on(), and you cannot emit them with emit().
   */
  private const RESERVED_TYPES = [
    'subscribe',
    'unsubscribe',
    'connected',
    'disconnected',
    'subscribed',
    'unsubscribed',
    'error',
  ];

  /**
   * Redis internal message types.
   */
  private const REDIS_USER = 'user';
  private const REDIS_EVENT = 'event';
  private const REDIS_BROADCAST = 'broadcast';
  private bool $started = false;

  /**
   * @var ?array{host: string, port: int, stream: string, group: string, database: string, password: null|string} Redis Stream configuration.
   */
  private ?array $redisStream = null;

  /**
   * Prevent registering the Redis process more than once.
   */
  private bool $redisProcessRegistered = false;
  /**
   * Underlying Swoole WebSocket server.
   */
  private Server $server;

  /**
   * Custom application message handlers.
   *
   * @var array<string, callable(mixed $data, int $fd, self): void>
   */
  private array $handlers = [];

  /**
   * Callbacks invoked when a client connects.
   *
   * Expected signature:
   *   function (int $fd, Request $request): void
   *
   * @var list<callable(int $fd, Request $request): void>
   */
  private array $connectedCallbacks = [];

  /**
   * Callbacks invoked when a client disconnects.
   *
   * Expected signature:
   *   function (int $fd, string $reason): void
   *
   * @var list<callable(int $fd, string $reason): void>
   */
  private array $disconnectedCallbacks = [];

  /**
   * User to connection mapping.
   *
   * userId => [fd => true]
   *
   * @var array<string, array<int, bool>>
   */
  private array $users = [];

  /**
   * Connection to user mapping.
   *
   * fd => userId
   *
   * @var array<int, string>
   */
  private array $fdUsers = [];

  /**
   * Subscriptions per connection.
   *
   * fd => event => true
   *
   * @var array<int, array<string, bool>>
   */
  private array $subscriptions = [];

  /**
   * Reverse subscription index for fast emission.
   *
   * event => fd => true
   *
   * @var array<string, array<int, bool>>
   */
  private array $eventSubscribers = [];

  /**
   * Active connection ids in this worker process.
   *
   * Used to avoid duplicate disconnect handling if both "close" and "disconnect" fire.
   *
   * @var array<int, bool>
   */
  private array $activeFds = [];

  /**
   * Create a new WebSocket server wrapper.
   *
   * Note:
   * The subscription/user state is kept in process memory by default.
   * For that reason the default worker count is 1. If you use multiple workers,
   * each worker will have its own separate state unless you add shared storage.
   */
  public function __construct(
    private string $host = '0.0.0.0',
    private int $port = 9000,
    private int $workerNum = 1
  ) {
    $this->server = new Server(
      $this->host,
      $this->port
    );

    $this->server->set([
      'worker_num' => max(1, $this->workerNum),
      'heartbeat_check_interval' => 30,
      'heartbeat_idle_time' => 90,
      'package_max_length' => 2 * 1024 * 1024,
    ]);

    if (max(1, $this->workerNum) > 1) {
      $this->log(
        'Warning: Websocket subscription/user state is kept in each worker process. ' .
        'Use worker_num=1 unless you add shared storage such as Redis or Swoole Table.'
      );
    }

    $this->registerEvents();
  }

  /**
   * Register a handler for an application message type.
   *
   * The callback receives:
   * - mixed $data: the "data" value from the JSON message
   * - int $fd: client connection id
   * - self $socket: this Websocket instance
   *
   * Example:
   *   $ws->on('chat.send', function (mixed $data, int $fd, Websocket $ws): void {
   *       // ...
   *   });
   */
  public function on(string $type, callable $callback): self
  {
    if (in_array($type, self::RESERVED_TYPES, true)) {
      throw new RuntimeException(sprintf('Message type "%s" is reserved.', $type));
    }

    $this->handlers[$type] = $callback;

    return $this;
  }

  /**
   * Register a callback for client connection events.
   *
   * Callback signature:
   *   function (int $fd, Request $request): void
   */
  public function onConnected(callable $callback): self
  {
    $this->connectedCallbacks[] = $callback;

    return $this;
  }

  /**
   * Register a callback for client disconnection events.
   *
   * Callback signature:
   *   function (int $fd, string $reason): void
   *
   * The reason is usually "close" or "disconnect".
   */
  public function onDisconnected(callable $callback): self
  {
    $this->disconnectedCallbacks[] = $callback;

    return $this;
  }

  /**
   * Send a JSON message to one client.
   *
   * The payload sent to the client is:
   *   {
   *       "type": string,
   *       "data": mixed
   *   }
   */
  public function send(int $fd, string $type, mixed $data = null): bool
  {
    $this->log('send check ' . $type);
    if (!$this->server->isEstablished($fd)) {
      return false;
    }
    $this->log('send checked');

    try {
      $json = json_encode(
        [
          'type' => $type,
          'data' => $data,
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
      );

      return (bool) $this->server->push($fd, $json);
    } catch (Throwable $e) {
      $this->log("Unable to send to {$fd}: {$e->getMessage()}");

      return false;
    }
  }

  /**
   * Send a message to every connected WebSocket client.
   */
  public function broadcast(string $type, mixed $data = null, ?int $exceptFd = null): void
  {
    foreach ($this->server->connections as $fd) {
      $fd = (int) $fd;

      if ($fd === $exceptFd || !$this->server->isEstablished($fd)) {
        continue;
      }

      $this->send($fd, $type, $data);
    }
  }

  /**
   * Emit an application event to all clients subscribed to that event.
   *
   * Example:
   *   $ws->emit('chat.message', ['text' => 'Hello']);
   *
   * Subscribed clients will receive:
   *   {
   *       "type": "chat.message",
   *       "data": {"text": "Hello"}
   *   }
   */
  public function emit(string $event, mixed $data = null, ?int $exceptFd = null): int
  {
    if (in_array($event, self::RESERVED_TYPES, true)) {
      throw new RuntimeException(sprintf('Event name "%s" is reserved.', $event));
    }

    $sent = 0;

    foreach ($this->eventSubscribers[$event] ?? [] as $fd => $_) {
      $fd = (int) $fd;

      if ($exceptFd !== null && $fd === $exceptFd) {
        continue;
      }

      if (!$this->server->isEstablished($fd)) {
        unset($this->eventSubscribers[$event][$fd]);
        continue;
      }

      if ($this->send($fd, $event, $data)) {
        $sent++;
      }
    }

    if (isset($this->eventSubscribers[$event]) && empty($this->eventSubscribers[$event])) {
      unset($this->eventSubscribers[$event]);
    }

    return $sent;
  }

  /**
   * Subscribe a client to one or more events.
   *
   * @param string|array $events A single event name, an array of event names,
   *                             or an associative array where keys are event names.
   *
   * @return list<string> Normalized event names that were subscribed.
   */
  public function subscribe(int $fd, string|array $events): array
  {
    if (!$this->server->isEstablished($fd)) {
      throw new RuntimeException('Cannot subscribe an unestablished connection.');
    }

    $normalized = $this->normalizeEvents($events);

    foreach ($normalized as $event) {
      $this->subscriptions[$fd][$event] = true;
      $this->eventSubscribers[$event][$fd] = true;
    }

    return $normalized;
  }

  /**
   * Unsubscribe a client from one or more events.
   *
   * Pass null, an empty string, or an empty array to remove all subscriptions for the fd.
   *
   * @param string|array|null $events A single event name, an array of event names,
   *                                  or an associative array where keys are event names.
   *
   * @return list<string> Event names that were actually removed.
   */
  public function unsubscribe(int $fd, string|array|null $events = null): array
  {
    if (!$this->server->isEstablished($fd)) {
      throw new RuntimeException('Cannot unsubscribe an unestablished connection.');
    }

    if ($events === null || $events === '' || (is_array($events) && empty($events))) {
      $removed = array_keys($this->subscriptions[$fd] ?? []);

      foreach ($removed as $event) {
        $this->removeSubscription($fd, $event);
      }

      return $removed;
    }

    $normalized = $this->normalizeEvents($events);
    $removed = [];

    foreach ($normalized as $event) {
      if ($this->removeSubscription($fd, $event)) {
        $removed[] = $event;
      }
    }

    return $removed;
  }

  /**
   * Check whether a client is subscribed to an event.
   */
  public function isSubscribed(int $fd, string $event): bool
  {
    return isset($this->subscriptions[$fd][$event]);
  }

  /**
   * Get all events that a client is currently subscribed to.
   *
   * @return list<string>
   */
  public function getSubscriptions(int $fd): array
  {
    return array_keys($this->subscriptions[$fd] ?? []);
  }

  /**
   * Start the Swoole WebSocket server.
   *
   * This call blocks until the server is stopped.
   */
  public function start(): void
  {
    $this->started = true;
    $this->server->start();
  }

  public function shutdown(): void
  {
    /*
     * Optional: force-close established WebSocket connections first.
     *
     * With worker_num = 1 this is usually enough.
     * With multiple workers, each worker only sees its own local connections.
     */
    try {
      foreach (array_keys($this->server->connections) as $fd) {
        $this->server->close((int) $fd);
      }
    } catch (Throwable $e) {
      // Ignore; shutdown below will still stop the server.
    }

    $this->server->shutdown();
  }


  /**
   * Bind a user id to a connection.
   *
   * A single user may have multiple active connections.
   */
  public function bindUser(int $fd, int|string $userId): void
  {
    $userId = (string) $userId;

    // Remove previous association if there is one.
    $this->unbindUser($fd);

    $this->users[$userId][$fd] = true;
    $this->fdUsers[$fd] = $userId;

    $this->log("Bound fd {$fd} to user {$userId}");
  }

  /**
   * Remove the user association for a connection.
   */
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

    $this->log("Unbound fd {$fd} from user {$userId}");
  }

  /**
   * Send a message to every active connection belonging to a user.
   */
  public function sendToUser(int|string $userId, string $type, mixed $data = null): void
  {
    $userId = (string) $userId;

    foreach ($this->users[$userId] ?? [] as $fd => $_) {
      $this->send((int) $fd, $type, $data);
    }
  }


  /**
   * Consume messages from a Redis Stream.
   *
   * IMPORTANT:
   * If several independent WebSocket server instances must all receive
   * every event, each server instance must use its own consumer group.
   */
  public function useRedisStream(
    string $host = '127.0.0.1',
    int $port = 6379,
    string $stream = 'bbn:websocket',
    string $group = 'websocket',
    ?string $password = null,
    string $database = '0'
  ): self {
    if ($this->started) {
      throw new RuntimeException('Call useRedisStream() before start().');
    }

    if ($this->redisProcessRegistered) {
      throw new RuntimeException(
        'Redis Stream consumer has already been configured.'
      );
    }

    $this->redisStream = [
      'host' => $host,
      'port' => $port,
      'stream' => $stream,
      'group' => $group,
      'password' => $password,
      'database' => $database,
    ];

    $this->registerRedisProcess();
    $this->redisProcessRegistered = true;

    return $this;
  }

  /**
   * Register Swoole server events.
   */
  private function registerEvents(): void
  {
    $this->server->on(
      'Start',
      function (): void {
        $this->log("WebSocket server listening on {$this->host}:{$this->port}");
      }
    );

    $this->server->on(
      'Open',
      function (Server $server, Request $request): void {
        $fd = (int) $request->fd;

        // Clear any stale state if the fd is reused.
        $this->cleanupFd($fd);

        $this->activeFds[$fd] = true;

        $this->log("Client connected: {$fd}");

        $this->send(
          $fd,
          self::EVT_CONNECTED,
          [
            'fd' => $fd,
            'time' => time(),
          ]
        );

        $this->emitConnected($fd, $request);
      }
    );

    $this->server->on(
      'Message',
      function (Server $server, Frame $frame): void {
        $this->handleMessage($frame);
      }
    );

    $this->server->on(
      'PipeMessage',
      function (Server $server, int $fd, mixed $raw): void {
        $this->log("onPipeMessage");
        if (!is_string($raw)) {
          return;
        }

        try {
          $message = json_decode(
            $raw,
            true,
            512,
            JSON_THROW_ON_ERROR
          );

          $this->log($message);
          $this->handleRedisMessage(
            $message
          );
        } catch (Throwable $e) {
          $this->log(
            'Redis pipe message error: ' . $e->getMessage()
          );
        }
      }
    );
    $this->server->on(
      'Close',
      function (Server $server, int $fd): void {
        $this->handleDisconnect((int) $fd, 'close');
      }
    );

    // Some Swoole versions/builds emit "disconnect" instead of or in addition to "close".
    $this->server->on(
      'Disconnect',
      function (Server $server, int $fd): void {
        $this->handleDisconnect((int) $fd, 'disconnect');
      }
    );

    $this->server->on(
      'WorkerError',
      function (Server $server, int $workerId, int $workerPid, int $exitCode, int $signal): void {
        $this->log(sprintf(
          'Worker error: worker=%d pid=%d exit=%d signal=%d',
          $workerId,
          $workerPid,
          $exitCode,
          $signal
        ));
      }
    );

    $this->server->on(
      'Shutdown',
      function (): void {
        $this->log('WebSocket server stopped');
      }
    );
  }

  /**
   * Register the process which consumes the Redis Stream.
   */
  private function registerRedisProcess(): void
  {
    if (
      $this->redisStream === null ||
      !X::hasProps($this->redisStream, ['host', 'port', 'stream', 'group', 'database'])
    ) {
      return;
    }

    $rs = $this->redisStream;
    $server = $this->server;
    $workerNum = max(1, $this->workerNum);

    $log = function (string $message): void {
      X::log($message, 'websocket');
      echo sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    };

    $process = new Process(function () use ($rs, $server, $workerNum, $log): void {
      $redis = null;
      $running = true;

      if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function () use (&$running) {
          $running = false;
        });

        pcntl_signal(SIGINT, function () use (&$running) {
          $running = false;
        });
      }

      register_shutdown_function(function () use (&$redis): void {
        if ($redis instanceof Redis) {
          try {
            $redis->close();
          } catch (Throwable $e) {
            // ignore shutdown close errors
          }
        }
      });

      try {
        $redis = new Redis();

        // 2 second connect timeout so this process does not hang forever.
        $redis->connect($rs['host'], $rs['port'], 2);

        if (!empty($rs['password'])) {
          $redis->auth($rs['password']);
        }

        if (isset($rs['database']) && $rs['database'] !== '' && $rs['database'] !== '0') {
          $redis->select((int) $rs['database']);
        }

        try {
          // Must be larger than the xReadGroup block time below.
          $redis->setOption(Redis::OPT_READ_TIMEOUT, 10);
        } catch (Throwable $e) {
          // Older phpredis versions may not support this; ignore.
        }

        $consumer = sprintf('websocket-%d', getmypid());

        try {
          /*
           * Use '$' to consume only new messages from now on.
           *
           * If you want the first consumer to replay the whole stream,
           * use '0' instead.
           */
          $startId = '$';
          $redis->xGroup('CREATE', $rs['stream'], $rs['group'], $startId, true);
        } catch (RedisException $e) {
          /*
           * BUSYGROUP means it already exists,
           * which is perfectly fine.
           */
          if (!str_contains($e->getMessage(), 'BUSYGROUP')) {
            throw $e;
          }
        }

        while ($running) {
          if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
          }

          try {
            $messages = $redis->xReadGroup(
              $rs['group'],
              $consumer,
              [
                $rs['stream'] => '>',
              ],
              20,
              5000
            );

            if (!$messages) {
              continue;
            }

            foreach ($messages[$rs['stream']] ?? [] as $id => $fields) {
              try {
                $json = $fields['json'] ?? null;

                if (!is_string($json)) {
                  throw new RuntimeException(
                    'Redis message has no JSON payload.'
                  );
                }

                /*
                 * Make sure it is valid JSON before forwarding.
                 */
                $decoded = json_decode(
                  $json,
                  true,
                  512,
                  JSON_THROW_ON_ERROR
                );

                if (
                  !is_array($decoded)
                  || !isset($decoded['type'])
                  || !array_key_exists('data', $decoded)
                ) {
                  throw new RuntimeException(
                    'Invalid Redis message.'
                  );
                }

                /*
                 * Send the message to every WebSocket worker.
                 */
                for ($workerId = 0; $workerId < $workerNum; $workerId++) {
                  $server->sendMessage($json, $workerId);
                }

                /*
                 * Redis can now consider the message processed.
                 */
                $redis->xAck(
                  $rs['stream'],
                  $rs['group'],
                  [
                    $id,
                  ]
                );
              } catch (Throwable $e) {
                $log(sprintf('Redis message %s error: %s', $id, $e->getMessage()));
              }
            }
          } catch (Throwable $e) {
            if (!$running) {
              break;
            }

            $log('Redis Stream error: ' . $e->getMessage());
            sleep(1);
          }
        }
      } finally {
        if ($redis instanceof Redis) {
          try {
            $redis->close();
          } catch (Throwable $e) {
            // ignore shutdown close errors
          }
        }
      }
    });

    /*
     * Optional: makes the process easier to identify in ps.
     * Remove this line if your Swoole version does not support it.
     */
    // $process->name = 'websocket-redis';

    $server->addProcess($process);
  }


  /**
   * Handle an internal message received from Redis.
   */
  private function handleRedisMessage(array $message): void
  {
    $this->log('handleRedisMessage');
    $this->validateRedisMessage(
      $message
    );

    $type = $message['type'];
    $data = $message['data'];
    $this->log("handleRedisMessage $type");

    switch ($type) {
      case self::REDIS_USER:
        $this->sendToUser(
          $data['user_id'],
          $data['type'],
          $data['data'] ?? null
        );
        break;

      case self::REDIS_EVENT:
        $this->emit(
          $data['event'],
          $data['data'] ?? null
        );
        break;

      case self::REDIS_BROADCAST:
        $this->broadcast(
          $data['type'],
          $data['data'] ?? null
        );
        break;
    }
  }

  private function validateRedisMessage(mixed $message): void
  {
    if (
      !is_array($message)
      || !isset($message['type'])
      || !is_string($message['type'])
      || !array_key_exists('data', $message)
      || !is_array($message['data'])
    ) {
      throw new RuntimeException(
        'Invalid Redis WebSocket message.'
      );
    }

    switch ($message['type']) {
      case self::REDIS_USER:
        if (
          !array_key_exists('user_id', $message['data'])
          || !isset($message['data']['type'])
          || !is_string($message['data']['type'])
        ) {
          throw new RuntimeException(
            'Invalid Redis user message.'
          );
        }

        break;

      case self::REDIS_EVENT:
        if (
          !isset($message['data']['event'])
          || !is_string($message['data']['event'])
        ) {
          throw new RuntimeException(
            'Invalid Redis event message.'
          );
        }

        break;

      case self::REDIS_BROADCAST:
        if (
          !isset($message['data']['type'])
          || !is_string($message['data']['type'])
        ) {
          throw new RuntimeException(
            'Invalid Redis broadcast message.'
          );
        }

        break;

      default:
        throw new RuntimeException(
          sprintf(
            'Unknown Redis message type "%s".',
            $message['type']
          )
        );
    }
  }

  /**
   * Handle a client disconnection event.
   */
  private function handleDisconnect(int $fd, string $reason): void
  {
    if (!isset($this->activeFds[$fd])) {
      return;
    }

    unset($this->activeFds[$fd]);

    $this->cleanupFd($fd);

    $this->emitDisconnected($fd, $reason);

    $this->log("Client disconnected: {$fd} ({$reason})");
  }

  /**
   * Remove all local state associated with a connection.
   */
  private function cleanupFd(int $fd): void
  {
    $this->unbindUser($fd);

    foreach (array_keys($this->subscriptions[$fd] ?? []) as $event) {
      $this->removeSubscription($fd, $event);
    }

    unset($this->subscriptions[$fd]);
  }

  /**
   * Remove a single subscription for a connection.
   */
  private function removeSubscription(int $fd, string $event): bool
  {
    if (!isset($this->subscriptions[$fd][$event])) {
      return false;
    }

    unset(
      $this->subscriptions[$fd][$event],
      $this->eventSubscribers[$event][$fd]
    );

    if (empty($this->subscriptions[$fd])) {
      unset($this->subscriptions[$fd]);
    }

    if (empty($this->eventSubscribers[$event])) {
      unset($this->eventSubscribers[$event]);
    }

    return true;
  }

  /**
   * Invoke all registered connected callbacks.
   */
  private function emitConnected(int $fd, Request $request): void
  {
    foreach ($this->connectedCallbacks as $callback) {
      try {
        $callback($fd, $request);
      } catch (Throwable $e) {
        $this->log("Error in connected callback for fd {$fd}: {$e->getMessage()}");
      }
    }
  }

  /**
   * Invoke all registered disconnected callbacks.
   */
  private function emitDisconnected(int $fd, string $reason): void
  {
    foreach ($this->disconnectedCallbacks as $callback) {
      try {
        $callback($fd, $reason);
      } catch (Throwable $e) {
        $this->log("Error in disconnected callback for fd {$fd}: {$e->getMessage()}");
      }
    }
  }

  /**
   * Handle an incoming WebSocket frame.
   */
  private function handleMessage(Frame $frame): void
  {
    $fd = (int) $frame->fd;
    $this->log("handleMessage $fd");

    if (!isset($this->activeFds[$fd])) {
      return;
    }

    try {
      $message = json_decode(
        $frame->data,
        true,
        512,
        JSON_THROW_ON_ERROR
      );
    } catch (JsonException $e) {
      $this->log("Invalid JSON from {$fd}: {$e->getMessage()}");

      $this->send(
        $fd,
        self::EVT_ERROR,
        [
          'message' => 'Invalid JSON',
        ]
      );

      return;
    }

    if (!is_array($message)) {
      $this->send(
        $fd,
        self::EVT_ERROR,
        [
          'message' => 'Message must be a JSON object',
        ]
      );

      return;
    }

    // An empty array can come from either {} or []. Allow it and report missing type below.
    if ($message !== [] && $this->isList($message)) {
      $this->send(
        $fd,
        self::EVT_ERROR,
        [
          'message' => 'Message must be a JSON object',
        ]
      );

      return;
    }

    try {
      if (!array_key_exists('type', $message)) {
        $this->send(
          $fd,
          self::EVT_ERROR,
          [
            'message' => 'Missing message type',
          ]
        );

        return;
      }

      if (!is_string($message['type'])) {
        $this->send(
          $fd,
          self::EVT_ERROR,
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

      if ($type === self::MSG_SUBSCRIBE) {
        $this->handleSubscribe($fd, $data);

        return;
      }

      if ($type === self::MSG_UNSUBSCRIBE) {
        $this->handleUnsubscribe($fd, $data);

        return;
      }

      if (in_array($type, self::RESERVED_TYPES, true)) {
        $this->send(
          $fd,
          self::EVT_ERROR,
          [
            'message' => sprintf('Message type "%s" is reserved.', $type),
          ]
        );

        return;
      }

      $handler = $this->handlers[$type] ?? null;

      if ($handler === null) {
        $this->send(
          $fd,
          self::EVT_ERROR,
          [
            'message' => 'Unknown message type',
            'type' => $type,
          ]
        );

        return;
      }

      $handler($data, $fd, $this);
    } catch (Throwable $e) {
      $this->log("Error processing message from {$fd}: {$e->getMessage()}");

      $this->send(
        $fd,
        self::EVT_ERROR,
        [
          'message' => 'Internal server error',
        ]
      );
    }
  }

  /**
   * Handle a client subscription request.
   */
  private function handleSubscribe(int $fd, mixed $data): void
  {
    try {
      if (!is_string($data) && !is_array($data)) {
        throw new RuntimeException('Subscription events must be a string or an array.');
      }

      $events = $this->subscribe($fd, $data);

      $this->send(
        $fd,
        self::EVT_SUBSCRIBED,
        [
          'fd' => $fd,
          'events' => $events,
        ]
      );
    } catch (Throwable $e) {
      $this->log("Subscribe error for fd {$fd}: {$e->getMessage()}");

      $this->send(
        $fd,
        self::EVT_ERROR,
        [
          'message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * Handle a client unsubscription request.
   */
  private function handleUnsubscribe(int $fd, mixed $data): void
  {
    try {
      if ($data !== null && !is_string($data) && !is_array($data)) {
        throw new RuntimeException('Subscription events must be a string or an array.');
      }

      if ($data === null || $data === '' || (is_array($data) && empty($data))) {
        $events = null;
      } else {
        $events = $data;
      }

      $removed = $this->unsubscribe($fd, $events);

      $this->send(
        $fd,
        self::EVT_UNSUBSCRIBED,
        [
          'fd' => $fd,
          'events' => $removed,
        ]
      );
    } catch (Throwable $e) {
      $this->log("Unsubscribe error for fd {$fd}: {$e->getMessage()}");

      $this->send(
        $fd,
        self::EVT_ERROR,
        [
          'message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * Normalize subscription input into a list of event names.
   *
   * Supported formats:
   * - "event.name"
   * - ["event.one", "event.two"]
   * - {"events": ["event.one", "event.two"]}
   * - {"event.one": true, "event.two": false}
   *
   * @return list<string>
   */
  private function normalizeEvents(mixed $raw): array
  {
    if (is_string($raw)) {
      $raw = [$raw];
    }

    if (!is_array($raw)) {
      throw new RuntimeException('Subscription events must be a string or an array of strings.');
    }

    if (isset($raw['events']) && is_array($raw['events'])) {
      $raw = $raw['events'];
    }

    // Support associative arrays where the keys are event names, e.g. {"chat.message": true}.
    if (!$this->isList($raw)) {
      $events = [];

      foreach ($raw as $key => $_) {
        if (is_string($key) && trim($key) !== '') {
          $events[] = trim($key);
        }
      }

      $events = array_values(array_unique($events));

      if ($events === []) {
        throw new RuntimeException('No valid subscription events provided.');
      }

      return $events;
    }

    $events = [];

    foreach ($raw as $value) {
      if (!is_string($value)) {
        throw new RuntimeException('Each event must be a string.');
      }

      $value = trim($value);

      if ($value !== '') {
        $events[] = $value;
      }
    }

    $events = array_values(array_unique($events));

    if ($events === []) {
      throw new RuntimeException('No valid subscription events provided.');
    }

    return $events;
  }

  /**
   * Check whether an array is a list.
   */
  private function isList(array $array): bool
  {
    $index = 0;

    foreach ($array as $key => $_) {
      if ($key !== $index++) {
        return false;
      }
    }

    return true;
  }

  /**
   * Simple logger.
   */
  private function log($message): void
  {
    X::log($message, 'websocket');
    echo sprintf(
      "[%s] %s\n",
      date('Y-m-d H:i:s'),
      (string)$message
    );
  }
}
