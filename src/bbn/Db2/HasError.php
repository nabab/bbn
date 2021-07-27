<?php

namespace bbn\Db2;

use bbn\Db2\Enums\Errors;
use bbn\Str;
use bbn\X;

trait HasError
{
  /**
   * An elegant separator
   */
  protected static $LINE = '---------------------------------------------------------------------------------';
  
  /**
   * Error state of the current connection
   * @var bool
   */
  protected static $_has_error_all = false;

  /**
   * Error state of the current connection
   * @var bool $has_error
   */
  protected $_has_error = false;

  /**
   * @var string $last_error
   */
  protected $last_error = null;

  /**
   * @var string $on_error
   * Possible values:
   * *    stop: the script will go on but no further database query will be executed
   * *    die: the script will die with the error
   * *    continue: the script and further queries will be executed
   */
  protected $on_error = Errors::E_STOP_ALL;
  
  /**
   * Set an error and acts appropriately based oon the error mode
   *
   * @param $e
   * @return void
   * @throws \Exception
   */
  public function error($e): void
  {
    $this->_has_error = true;
    self::_set_has_error_all();
    $msg = [
      self::$LINE,
      self::getLogLine('ERROR DB!'),
      self::$LINE
    ];
    if (\is_string($e)) {
      $msg[] = self::getLogLine('USER MESSAGE');
      $msg[] = $e;
    }
    elseif (method_exists($e, 'getMessage')) {
      $msg[] = self::getLogLine('DB MESSAGE');
      $msg[] = $e->getMessage();
    }

    $this->last_error = end($msg);
    $msg[]            = self::getLogLine('QUERY');
    $msg[]            = $this->last();

    if (($last_real_params = $this->getRealLastParams()) && !empty($last_real_params['values'])) {
      $msg[] = self::getLogLine('VALUES');
      foreach ($last_real_params['values'] as $v){
        if ($v === null) {
          $msg[] = 'NULL';
        }
        elseif (\is_bool($v)) {
          $msg[] = $v ? 'TRUE' : 'FALSE';
        }
        elseif (\is_string($v)) {
          $msg[] = Str::isBuid($v) ? bin2hex($v) : Str::cut($v, 30);
        }
        else{
          $msg[] = $v;
        }
      }
    }

    $msg[] = self::getLogLine('BACKTRACE');
    $dbt   = array_reverse(debug_backtrace());
    array_walk(
      $dbt, function ($a, $i) use (&$msg) {
      $msg[] = str_repeat(' ', $i).($i ? '->' : '')."{$a['function']}  (".basename(dirname($a['file'])).'/'.basename($a['file']).":{$a['line']})";
    }
    );
    $this->log(implode(PHP_EOL, $msg));
    if ($this->on_error === Errors::E_DIE) {
      throw new \Exception(
        \defined('BBN_IS_DEV') && BBN_IS_DEV
          ? '<pre>'.PHP_EOL.implode(PHP_EOL, $msg).PHP_EOL.'</pre>'
          : 'Database error'
      );
    }
  }

  /**
   * Sets the has_error_all variable to true.
   *
   * @return void
   */
  private static function _set_has_error_all(): void
  {
    self::$_has_error_all = true;
  }

  /**
   * Returns a string with the given text in the middle of a "line" of logs.
   *
   * @param string $text The text to write
   * @return string
   */
  public static function getLogLine(string $text = '')
  {
    if ($text) {
      $text = ' '.$text.' ';
    }

    $tot  = \strlen(self::$LINE) - \strlen($text);
    $char = \substr(self::$LINE, 0, 1);
    return \str_repeat($char, floor($tot / 2)).$text.\str_repeat($char, ceil($tot / 2));
  }

  /**
   * Writes in data/logs/db.log.
   *
   * ```php
   * $db->$db->log('test');
   * ```
   * @param mixed $st
   * @return self
   */
  public function log($st): self
  {
    $args = \func_get_args();
    foreach ($args as $a){
      X::log($a, 'db');
    }

    return $this;
  }

  /**
   * Sets the error mode.
   *
   * ```php
   * $db->setErrorMode('continue'|'die'|'stop_all|'stop');
   * // (self)
   * ```
   *
   * @param string $mode The error mode: "continue", "die", "stop", "stop_all".
   * @return self
   */
  public function setErrorMode(string $mode)
  {
    $this->on_error = $mode;
    return $this;
  }

  /**
   * Gets the error mode.
   *
   * ```php
   * X::dump($db->getErrorMode());
   * // (string) stop_all
   * ```
   * @return string
   */
  public function getErrorMode(): string
  {
    return $this->on_error;
  }

  /**
   * Returns the last error.
   *
   * @return string|null
   */
  public function getLastError(): ?string
  {
    return $this->last_error;
  }

  public function check(): bool
  {
    if (!property_exists($this, 'current')) {
      throw new \Exception('Property current does not exist');
    }

    if ($this->current !== null) {
      // if $on_error is set to E_CONTINUE returns true
      if ($this->on_error === Errors::E_CONTINUE) {
        return true;
      }

      // If any connection has an error with mode E_STOP_ALL
      if (self::$_has_error_all && ($this->on_error === Errors::E_STOP_ALL)) {
        return false;
      }

      // If this connection has an error with mode E_STOP or E_STOP_ALL
      if ($this->_has_error && ($this->on_error === Errors::E_STOP || $this->on_error === Errors::E_STOP_ALL)) {
        return false;
      }

      return true;
    }

    return false;
  }
}