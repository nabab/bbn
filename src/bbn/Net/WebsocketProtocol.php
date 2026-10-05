<?php
declare(strict_types=1);

namespace bbn\Net;

use InvalidArgumentException;

/** Shared validation for the WebSocket server and Redis publisher. */
final class WebsocketProtocol
{
  public const RESERVED = [
    'subscribe', 'unsubscribe', 'connected', 'disconnected',
    'subscribed', 'unsubscribed', 'error',
  ];

  public static function name(mixed $name, bool $application = false): string
  {
    if (!is_string($name)
      || !preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D', $name)
    ) {
      throw new InvalidArgumentException('Invalid message/event name.');
    }
    if ($application && in_array($name, self::RESERVED, true)) {
      throw new InvalidArgumentException('This message/event name is reserved.');
    }
    return $name;
  }

  /** @return list<string> */
  public static function events(string|array $raw): array
  {
    if (is_string($raw)) {
      $raw = [$raw];
    }
    if (array_key_exists('events', $raw)) {
      if (!is_array($raw['events'])) {
        throw new InvalidArgumentException('events must be an array.');
      }
      $raw = $raw['events'];
    }
    // In a name => value map, values are ignored, just as in the original API.
    if (!array_is_list($raw)) {
      $raw = array_keys($raw);
    }
    if (!$raw || count($raw) > 256) {
      throw new InvalidArgumentException('Provide between 1 and 256 events.');
    }
    $events = [];
    foreach ($raw as $event) {
      $events[] = self::name(is_string($event) ? trim($event) : $event, true);
    }
    return array_values(array_unique($events));
  }

  public static function userId(mixed $id): string
  {
    if ((!is_string($id) && !is_int($id)) || (string) $id === '') {
      throw new InvalidArgumentException('user_id must be a nonempty string or integer.');
    }
    return (string) $id;
  }

  public static function redis(mixed $message): array
  {
    if (!is_array($message) || !is_string($message['type'] ?? null)
      || !is_array($message['data'] ?? null)
    ) {
      throw new InvalidArgumentException('Invalid Redis WebSocket envelope.');
    }
    $data = $message['data'];
    switch ($message['type']) {
      case 'user':
        self::userId($data['user_id'] ?? null);
        self::name($data['type'] ?? null, true);
        break;
      case 'event':
        self::name($data['event'] ?? null, true);
        break;
      case 'broadcast':
        self::name($data['type'] ?? null, true);
        break;
      default:
        throw new InvalidArgumentException('Unknown Redis delivery mode.');
    }
    return $message;
  }
}
