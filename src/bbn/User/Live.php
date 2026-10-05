<?php

namespace bbn\User;

use Exception;
use RuntimeException;
use Redis;
use bbn\X;
use bbn\User;
class Live
{
  /**
   * Issue a ticket for an already-authenticated HTTP session.
   *
   * $sessionExpiresAt must reflect the session's effective expiry.
   * $origin must be a configured/validated public application origin.
   *
   * @return array{ticket: string, success: bool}
   */
  public static function issueSocketTicket(Redis $redis, User $user): array
  {
    if (!$user->checkSession()) {
      throw new Exception(X::_("The user is not loggged in"));
    }

    $now = time();
    $ttl = 30;
    $userId = $user->getId();
    $sessionRef = $user->getSessionDbId();
    if (!$userId || !$sessionRef) {
      throw new RuntimeException(
        'Cannot issue a ticket for an invalid session.'
      );
    }

    $ticket = bin2hex(random_bytes(32));
    $key = 'appui:ws:ticket:' . hash('sha256', $ticket);
    $payload = json_encode(
      [
        'audience' => 'appui-websocket',
        'user_id' => $userId,
        'session_ref' => $sessionRef,
        'origin' => 'http' . (constant('BBN_IS_SSL') ? 's' : '') . '://' . constant('BBN_SERVER_NAME'),
        'expire' => $now + $ttl,
      ],
      JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
    );

    if (!$redis->set($key, $payload, ['nx', 'ex' => $ttl])) {
      throw new RuntimeException(
        'Unable to create WebSocket ticket.'
      );
    }

    return [
      'ticket' => $ticket,
      'connected' => true
    ];
  }

  public static function userConnect(Redis $redis, User $user): void
  {
    if ($userId = $user->getId()) {
      $key = 'appui:users:online';
      $redis->zAdd($key, time(), $userId);
    }
  }

  public static function userDisconnect(Redis $redis, User $user): void
  {
    if ($userId = $user->getId()) {
      $key = 'appui:users:online';
      $redis->zRem($key, $userId);
    }
  }

  public static function userActivity(Redis $redis, User $user): void
  {
    if ($userId = $user->getId()) {
      $key = 'appui:users:online';
      $redis->zAdd($key, time(), $userId);
    }
  }
}