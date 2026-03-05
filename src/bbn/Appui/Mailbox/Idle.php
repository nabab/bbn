<?php

namespace bbn\Appui\Mailbox;

use bbn\Appui\Mailbox\RawClient;
use Exception;
use bbn\X;
use bbn\Str;


/**
 * Class providing functionality for IMAP IDLE.
 */
class Idle extends RawClient
{
  /**
   * @var string The folder UID
   */
  protected $folder;

  /**
   * @var bool Whether the IDLE is running
   */
  protected $running = false;
  /**
   * @var callable|null The callback function to call when a new message arrives
   */
  protected $callback;

  /**
   * @var int The time of the last callback ping
   */
  protected $callbackLastPing = 0;

  /**
   * @var int The timeout for the callback to stop the IDLE
   */
  protected $callbackFrequency = 10;


  /**
   * Idle constructor.
   * @param string $host The host of the IMAP server
   * @param int $port The port of the IMAP server
   * @param bool $encryption Whether to use encryption for the connection
   * @param string $login The login of the mailbox
   * @param string $pass The password of the mailbox
   * @param string $folder The folder UID
   * @param callable $callback The callback function to call when a new message arrives
   * @param int|null $timeout The timeout in seconds for the IDLE connection
   */
  public function __construct(
    string $host,
    int $port,
    bool $encryption,
    string $login,
    string $pass,
    string $folder,
    callable $callback,
    ?int $timeout = null
  )
  {
    parent::__construct($host, $port, $encryption, $login, $pass, $timeout);
    $this->folder = $folder;
    $this->callback = $callback;
  }


  /**
   * Idle destructor. Ensures that the IDLE connection is stopped when the object is destroyed.
    * @return void
   */
  public function __destruct()
  {
    $this->stopIdle();
    parent::__destruct();
  }


  /**
   * Starts an IDLE connection to the mailbox for the specified folder. Listens for new messages and calls the callback function when a new message arrives.
   */
  public function idle(): bool
  {
    set_time_limit(0);
    ignore_user_abort(true);
    $this->running = false;
    $this->tag = 0;
    if (!$this->isConnected()) {
      $this->connect();
    }

    try {
      $this->sendCommand("SELECT {$this->folder}");
      if (!$this->hasCapability('IDLE')) {
        $this->stopIdle();
        throw new Exception(X::_("IDLE not supported by the server"));
      }

      $this->sendCommand("IDLE", false);
      $this->running = true;
      ($this->callback)(['action' => 'idleStarted']);
      while ($this->isRunning()) {
        try {
          $response = $this->readCommandResponseLine();
        }
        catch (Exception $e) {
          $errCode = $e->getCode();
          if (($errCode === 1) && $this->isConnected()) {
            continue;
          }

          if (($errCode === 4)
            || (!str_contains($e->getMessage(), "connection closed")
              && ($errCode !== 3))
          ) {
            throw $e;
          }
        }

        if (!empty($response)) {
          $action = null;
          $msgn = null;
          $re = '/^\*\s+(?<msgn>\d+)\s+(?<action>EXISTS|EXPUNGE|FETCH)(?:\s+\((?:.*?\s)?FLAGS\s+\((?<flags>[^)]*)\).*?\))?\s*$/i';
          if (preg_match($re, $response, $m)) {
            $msgn = (int)$m['msgn'];
            $action = match (strtolower($m['action'])) {
              'exists' => 'newMail',
              'expunge' => 'mailDeleted',
              'fetch' => 'mailFlagged',
              default => null
            };

            if (!empty($action) && !empty($msgn)) {
              $this->lastTime = time();
              $data = [
                'msgn' => $msgn,
              ];
              if ($action === 'mailFlagged') {
                if (isset($m['flags'])
                  && (trim($m['flags']) !== '')
                ) {
                  $data['flags'] = preg_split('/\s+/', trim($m['flags']));
                }
                else {
                  $data['flags'] = [];
                }
              }

              ($this->callback)([
                'action' => $action,
                'data' => $data
              ]);
            }
          }
        }


        if (($this->callbackLastPing + $this->callbackFrequency) <= time()) {
          $this->pingCallback();
        }

        // Check if the stream is still alive or should be considered stale
        if (($this->lastTime + $this->timeout) < time()) {
          // Stop current IDLE connection
          $this->stopIdle();
          // Run IDLE again
          return $this->idle();
        }
      }
    }
    catch (Exception $e) {
      $this->stopIdle();
      throw $e;
    }

    return true;
  }


  /**
   * Stops the IDLE connection to the mailbox. Closes the stream resource and sets the running flag to false.
   */
  public function stopIdle()
  {
    $this->running = false;
    if (!empty($this->streamResource)) {
      fwrite($this->streamResource, "DONE\r\n");
      $this->disconnect();
    }
  }


  /**
   * Checks if the IDLE connection is currently running.
   * @return bool True if the IDLE connection is running, false otherwise
   */
  public function isRunning(): bool
  {
    return (bool)$this->running;
  }


  /**
   * Reads a line of response from the IMAP server. Handles non-blocking reads and checks for connection timeouts. Calls the ping callback if the callback frequency has been exceeded. Throws exceptions if the connection is lost or if an empty response is received when a response is expected.
   * @return string The line of response read from the server
   * @throws Exception If the connection is lost or if an empty response is received when a
   */
  protected function readCommandResponseLine(): string
  {
    stream_set_blocking($this->streamResource, false);
    $line = '';
    while (!in_array(Str::sub($line, -1), ["\n",  PHP_EOL])) {
      if (($this->callbackLastPing + $this->callbackFrequency) <= time()) {
        $this->pingCallback();
      }

      if ($this->isRunning()
        && !empty($this->callback)
      ) {
        ($this->callback)(['action' => 'syncSubscribedFolders']);
      }

      if (($this->lastTime + $this->timeout) < time()) {
        throw new Exception(X::_('IDLE Connection lost'), 3);
      }

      $read = [$this->streamResource];
      $write = $except = [];
      $n = @stream_select($read, $write, $except, $this->callbackFrequency ?: 10);

      if (($n === 0) || ($n === false)) {
        continue;
      }

      $chunk = fgets($this->streamResource, 1024);
      if ($chunk === false) {
        continue;
      }

      $line .= $chunk;
    }

    $this->lastTime = time();
    if ($this->isRunning()
      && ($line === '')
    ) {
      throw new Exception(X::_('Empty response (command: %s)', $this->lastCommand), 1);
    }

    return $line;
  }


  /**
   * Sends a ping action to the callback function to check if the connection is still alive. Updates the last ping time and checks if the callback function returns a valid response. Throws an exception if the connection is lost according to the callback response.
   * @return bool True if the ping was successful and the connection is still alive, false otherwise
   * @throws Exception If the connection is lost according to the callback response
   */
  protected function pingCallback(): bool
  {
    $this->callbackLastPing = time();
    if (empty($this->callback)) {
      return false;
    }

    $ping = ($this->callback)(['action' => 'ping']);
    if (empty($ping)) {
      throw new Exception(_("User connection lost"), 4);
    }

    return true;
  }
}