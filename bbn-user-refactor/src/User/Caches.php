<?php

declare(strict_types=1);

namespace bbn\User;

use bbn\Cache;
use bbn\File\System;
use bbn\Mvc;
use bbn\X;

/** Per-user filesystem cache. */
final class Caches extends Component
{
  public function path(): ?string
  {
    if (!$this->state->id) {
      return null;
    }

    if (!$this->state->cachePath) {
      $this->state->cachePath = Mvc::getUserTmpPath($this->state->id) . 'cache/';
      $fs = new System();
      if (!$fs->isDir($this->state->cachePath)) {
        $fs->mkdir($this->state->cachePath);
      }
    }

    return $this->state->cachePath;
  }

  public function has(string $key): bool
  {
    return $this->get($key, true) !== null;
  }

  public function get(string $key, bool $raw = false): mixed
  {
    $path = $this->path();
    if (!$path || !($file = Cache::_file($key, $path))) {
      return null;
    }

    $fs = new System();
    if (!$fs->isFile($file) || !($content = $fs->getContents($file))) {
      return null;
    }

    $entry = json_decode($content, true);
    if (!is_array($entry)) {
      return null;
    }

    if (!empty($entry['ttl']) && !empty($entry['expire']) && $entry['expire'] <= time()) {
      $this->delete($key);
      return null;
    }

    return $raw ? $entry : ($entry['value'] ?? null);
  }

  public function set(string $key, mixed $value, int $ttl = 0): bool
  {
    $path = $this->path();
    if (!$path || !($file = Cache::_file($key, $path))) {
      return false;
    }

    $fs = new System();
    if (!$fs->createPath(X::dirname($file))) {
      return false;
    }

    $ttl = Cache::ttl($ttl);
    return (bool)$fs->putContents($file, json_encode([
      'timestamp' => microtime(true),
      'hash' => Cache::makeHash($value),
      'expire' => $ttl ? time() + $ttl : 0,
      'ttl' => $ttl,
      'value' => $value,
    ], JSON_PRETTY_PRINT));
  }

  public function delete(string $key): bool
  {
    $path = $this->path();
    return (bool)($path
      && ($file = Cache::_file($key, $path))
      && (new System())->delete($file));
  }

  public function clear(): bool
  {
    $path = $this->path();
    return (bool)($path && (new System())->delete($path, false));
  }
}
