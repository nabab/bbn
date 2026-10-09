<?php

namespace bbn\User;

use Exception;
use RuntimeException;
use Redis;
use bbn\Cache;
use bbn\X;
use bbn\User;
use bbn\Net\Websocket;
use Swoole\Http\Request;

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
    $idUser = $user->getId();
    $sessionRef = $user->getSessionDbId();
    if (!$idUser || !$sessionRef) {
      throw new RuntimeException(
        'Cannot issue a ticket for an invalid session.'
      );
    }

    $ticket = bin2hex(random_bytes(32));
    $key = 'appui:ws:ticket:' . hash('sha256', $ticket);
    $payload = json_encode(
      [
        'audience' => 'appui-websocket',
        'id_user' => $idUser,
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

  public static function checkSocketTicket(Request $request, Websocket $socket): ?string
  {
    $ticket = $request->get['ticket'] ?? null;
    $origin = $request->header['origin'] ?? '';
    if (
      !is_string($ticket) ||
      !preg_match('/\A[a-f0-9]{64}\z/', $ticket) ||
      !is_string($origin)
    ) {
      return null;
    }

    $redis = self::getRedis();
    try {
      $json = $redis->getDel(
        'appui:ws:ticket:' . hash('sha256', $ticket)
      );
    } catch (\Exception $e) {
      return null;
    }

    // Missing, expired, or already consumed.
    if (!is_string($json)) {
      return null;
    }

    try {
      $identity = json_decode(
        $json,
        true,
        32,
        JSON_THROW_ON_ERROR
      );
    } catch (\JsonException) {
      return null;
    }

    if (
      !is_array($identity) ||
      ($identity['audience'] ?? null) !== 'appui-websocket' ||
      ($identity['origin'] ?? null) !== $origin ||
      !is_string($identity['id_user'] ?? null) ||
      $identity['id_user'] === '' ||
      !is_string($identity['session_ref'] ?? null) ||
      $identity['session_ref'] === '' ||
      !is_int($identity['expire'] ?? null) ||
      $identity['expire'] <= time()
    ) {
      return null;
    }

    if (!self::userIsConnected($identity['id_user'])) {
      return null;
    }

    // Your wrapper calls bindUser() with the returned identity.
    return $identity['id_user'];
  }

  public static function userConnect(Redis $redis, User $user): void
  {
    if (($idSess = $user->getSessionDbId()) && ($idUser = $user->getId())) {
      $t = time();
      $redis->set("appui:sessions:list:$idSess", $idUser);
      if (!$redis->sIsMember("appui:users:sessions:$idUser", $idSess)) {
        $redis->sAdd("appui:users:sessions:$idUser", $idSess);
      }

      $redis->zAdd('appui:users:online', $t, $idUser);
      $redis->zAdd('appui:sessions:online', $t, $idSess);
    }
  }

  public static function userDisconnect(Redis $redis, User $user): void
  {
    if (($idSess = $user->getSessionDbId()) && ($idUser = $user->getId())) {
      if ($redis->exists("appui:sessions:list:$idSess")) {
        $redis->del("appui:sessions:list:$idSess");
      }

      $redis->zRem('appui:sessions:online', $idSess);
      if ($redis->sIsMember("appui:users:sessions::$idUser", $idSess)) {
        $redis->sRem("appui:users:sessions:$idUser", $idSess);
        if (!$redis->sCard("appui:users:sessions:$idUser")) {
          $redis->zRem('appui:users:online', $idUser);
          $redis->del("appui:users:sessions:$idUser");
        }
      }
    }
  }

  public static function userActivity(Redis $redis, User $user): void
  {
    if (($idSess = $user->getSessionDbId()) && ($idUser = $user->getId())) {
      $t = time();
      $redis->zAdd('appui:users:online', $t, $idUser);
      $redis->zAdd('appui:users:activity', $t, $idUser);
      $redis->zAdd('appui:sessions:online', $t, $idSess);
      $redis->zAdd('appui:sessions:activity', $t, $idSess);
    }
  }

  public static function userIsConnected($idUser): bool
  {
    $redis = self::getRedis();
    if ($redis->zScore('appui:users:online', $idUser)) {
      return true;
    }

    return false;
  }

  public static function userIsActive($idUser, $timeout = 300): bool
  {
    $redis = self::getRedis();
    $activity = $redis->zScore('appui:users:activity', $idUser) ?: 0;
    if (time() - $activity > $timeout) {
      return false;
    }

    return true;
  }

  private static function getRedis(): Redis
  {
    $cache = Cache::getEngine();
    return $cache->getObj();
  }
}