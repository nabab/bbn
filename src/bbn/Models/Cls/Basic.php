<?php
/**
 * @package bbn
 */
namespace bbn\Models\Cls;

use bbn\Str;
use bbn\X;

/**
 * Base class providing error handling, debug state and logging helpers.
 *
 * This class is intended to be extended by most framework/service classes
 * needing a minimal shared API for:
 * - error registration
 * - error retrieval
 * - debug mode handling
 * - debug logging
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @since Apr 4, 2011
 */
abstract class Basic
{
  /**
   * List of registered errors.
   *
   * Each error entry contains:
   * - `time` (int): UNIX timestamp
   * - `msg` (string): error message
   * - `code` (mixed, optional): error code
   *
   * @var array<int, array<string, mixed>>
   */
  protected array $errors = [];

  /**
   * Timestamp of last error.
   *
   * @var float|null
   */
  protected ?float $errorTime = null;

  /**
   * Last error message.
   *
   * @var string|null
   */
  protected ?string $error = null;

  /**
   * Last error code.
   *
   * @var int|string|null
   */
  protected int|string|null $errorCode = null;

  /**
   * Reserved list of available error codes.
   *
   * @var array<int|string, mixed>
   */
  protected array $errorCodes = [];

  /**
   * Whether debug mode is enabled for the current instance.
   *
   * @var bool
   */
  protected bool $debug = false;

  /**
   * Internal log storage.
   *
   * @var array<int, mixed>
   */
  protected array $log = [];

  /**
   * Checks whether the current object is in a valid state.
   *
   * Returns `false` if at least one error has been set.
   *
   * @return bool
   */
  public function check(): bool
  {
    return !$this->error;
  }

  /**
   * Registers an error on the current object.
   *
   * The error is stored as the current error and appended to the error stack.
   *
   * @param string $err  Error message.
   * @param mixed  $code Optional error code.
   * @return static
   */
  protected function setError(string $err, $code = null): static
  {
    $this->error = $err;
    $this->errorCode = $code;
    $this->errorTime = microtime(true);

    $entry = [
      'time' => $this->errorTime,
      'msg' => $err,
      'code' => $code
    ];

    $this->errors[] = $entry;
    return $this;
  }

  /**
   * Returns the last error message.
   *
   * @return string|null
   */
  public function getError(): ?string
  {
    return $this->error;
  }

  /**
   * Returns the last error message.
   *
   * @return array<string, mixed>|null
   */
  public function getFullError(): ?array
  {
    return [
      'code' => $this->errorCode,
      'text' => $this->error,
      'time' => $this->errorTime,
    ];
  }

  /**
   * Returns the last error code.
   *
   * @return int|string|false|null
   */
  public function getErrorCode(): int|string|false|null
  {
    return $this->errorCode;
  }

  /**
   * Returns the full list of registered errors.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getErrors(): array
  {
    return $this->errors;
  }

  /**
   * Writes one or more values to the debug log when debug mode is enabled.
   *
   * The log filename is based on the current class name.
   *
   * @param mixed ...$args Values to log.
   * @return void
   */
  public function log(...$args): void
  {
    if ($this->isDebug()) {
      $cn = Str::encodeFilename(str_replace('\\', '_', static::class));
      foreach ($args as $a) {
        X::log($a, $cn);
      }
    }
  }

  /**
   * Indicates whether debug mode is enabled.
   *
   * Debug is enabled if either:
   * - the instance debug flag is set
   * - the `BBN_IS_DEV` constant is truthy
   *
   * @return bool
   */
  public function isDebug(): bool
  {
    return $this->debug || constant('BBN_IS_DEV');
  }

  /**
   * Sets the instance debug flag.
   *
   * @param bool $debug
   * @return void
   */
  public function setDebug(bool $debug): void
  {
    $this->debug = $debug;
  }
}
