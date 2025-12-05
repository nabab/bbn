<?php
declare(strict_types=1);

namespace bbn\Cron;

/**
 * Represents the high-level cron runner type.
 *
 * - poll: the poller loop (bbn\Appui\Observer, user queues, etc.)
 * - cron: the cron task system / single task runner
 */
enum Type: string
{
  case Poll = 'poll';
  case Cron = 'cron';

  /**
   * Normalizes an arbitrary string into a Type.
   *
   * Fallback behavior:
   *  - "poll" (any case) => Type::Poll
   *  - anything else     => Type::Cron
   */
  public static function fromString(string $type): self
  {
    return match (strtolower($type)) {
      'poll' => self::Poll,
      default => self::Cron,
    };
  }

  /**
   * Convenience check.
   */
  public function isPoll(): bool
  {
    return $this === self::Poll;
  }

  /**
   * Convenience check.
   */
  public function isCron(): bool
  {
    return $this === self::Cron;
  }
}
