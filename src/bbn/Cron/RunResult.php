<?php
declare(strict_types=1);

namespace bbn\Cron;

/**
 * Represents the outcome of a Runner execution.
 *
 * - code:   process exit code (0 = success, non-zero = error)
 * - message:optional message to print (e.g. old exit($message) behavior)
 */
final class RunResult
{
  public function __construct(
    public int $code = 0,
    public ?string $message = null
  ) {
  }

  /**
   * Convenience factory for a successful run.
   */
  public static function success(?string $message = null): self
  {
    return new self(0, $message);
  }

  /**
   * Convenience factory for a failed run.
   */
  public static function error(int $code = 1, ?string $message = null): self
  {
    if ($code === 0) {
      $code = 1;
    }

    return new self($code, $message);
  }

  public function hasMessage(): bool
  {
    return $this->message !== null && $this->message !== '';
  }

  public function isSuccess(): bool
  {
    return $this->code === 0;
  }

  public function isError(): bool
  {
    return $this->code !== 0;
  }
}
