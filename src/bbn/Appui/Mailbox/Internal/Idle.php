<?php

namespace bbn\Appui\Mailbox\Internal;

use Exception;
use bbn\X;
use bbn\Str;


/**
 * Trait providing functionality for IMAP IDLE.
 */
trait Idle
{

  /**
   * @var resource|false|null IMAP stream resource
   */
  protected $imapStreamResource;

  /**
   * @var float Last time an IDLE command was sent
   */
  protected $idleLastTime;

  /**
   * @var string The last IDLE command sent
   */
  protected $idleLastCommand;

  /**
   * @var string The IDLE tag
   */
  protected $idleTag;

  /**
   * @var string The IDLE tag prefix
   */
  protected $idleTagPrefix = 'BBN_';

  /**
   * @var bool Whether the IDLE is running
   */
  protected $idleRunning = false;
  /**
   * @var callable|null The callback function to call when a new message arrives
   */
  protected $idleCallback;

  /**
   * @var int The time of the last callback ping
   */
  protected $idleCallbackLastPing = 0;

  /**
   * @var int The timeout for the callback to stop the IDLE
   */
  protected $idleCallbackFrequency = 10;

  /**
   * @var int The timeout for the IDLE command
   */
  protected $idleTimeout = 300;


  public function idle(
    string $folder,
    callable $callback,
    ?int $timeout = null
  ): bool
  {
    set_time_limit(0);
    ignore_user_abort(true);
    $this->idleCallback = $callback;
    $this->idleRunning = false;
    $this->idleTag = 0;
    $context = [];
    if ($this->encryption) {
      $context['ssl'] = [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ];
    }

    if (!empty($timeout)) {
      $this->idleTimeout = $timeout;
    }

    // Establish the connection
    $this->imapStreamResource = stream_socket_client(
      ($this->encryption ? "ssl" : "tls") . "://{$this->host}:{$this->port}",
      $errno,
      $errstr,
      $this->idleTimeout,
      STREAM_CLIENT_CONNECT,
      stream_context_create($context)
    );
    if (!$this->imapStreamResource) {
      throw new Exception(X::_("Failed to connect: %s (%s)", $errstr, $errno));
    }

    try {
      $this->sendCommand("LOGIN {$this->login} {$this->pass}");
      $this->sendCommand("SELECT {$folder}");
      $this->sendCommand("CAPABILITY", false);
      $capabilityResponse = $this->readCommandResponseLine();
      $canIdle = !empty($capabilityResponse)
        && str_contains($capabilityResponse, "CAPABILITY")
        && in_array("IDLE", explode(" ", $capabilityResponse));
      if (empty($canIdle)) {
        $this->stopIdle();
        throw new Exception(X::_("IDLE not supported by the server"));
      }

      $this->sendCommand("IDLE", false);
      $this->idleRunning = true;
      $this->idleCallback(['start' => true]);
      while ($this->isIdleRunning()) {
        try {
          $response = $this->readCommandResponseLine();
        }
        catch (Exception $e) {
          $errCode = $e->getCode();
          if (($errCode === 1) && $this->isIdleConnected()) {
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
          // New message arrived, fetch it and call the callback with the message data
          if (($pos = Str::pos($response, "EXISTS")) !== false) {
            $msgn = (int)Str::sub($response, 2, $pos - 2);
            $this->idleLastTime = time();
            $this->selectFolder($folder);
            $this->idleCallback(['exists' => $this->getMsg($msgn)]);
          }
          // Message deleted
          elseif (($pos = Str::pos($response, "EXPUNGE")) !== false) {
            $msgn = (int)Str::sub($response, 2, $pos - 2);
            $this->idleLastTime = time();
            $this->idleCallback(['expunge' => $response]);
          }
          // Message flagged
          elseif ((($pos = Str::pos($response, "FETCH")) !== false)
            && str_contains($response, "FLAGS")
          ) {
            $msgn = (int)Str::sub($response, 2, $pos - 2);
            $this->idleLastTime = time();
            $this->idleCallback(['flags' => $response]);
          }
        }


        if (($this->idleCallbackLastPing + $this->idleCallbackFrequency) <= time()) {
          $this->pingIdleCallback();
        }

        // Check if the stream is still alive or should be considered stale
        if (!$this->_is_connected()
          || (($this->idleLastTime + $this->idleTimeout) < time())
        ) {
          // Stop current IDLE connection
          $this->stopIdle();
          // Close the main stream
          if (!empty($this->stream)) {
            imap_close($this->stream);
          }

          // Establish a new connection
          $this->connect();
          // Run IDLE again
          return $this->idle($folder, $callback, $this->idleTimeout);
        }
      }
    }
    catch (Exception $e) {
      $this->stopIdle();
      throw $e;
    }

    return true;
  }


  public function stopIdle()
  {
    $this->idleRunning = false;
    $this->idleCallback = null;
    if (!empty($this->imapStreamResource)) {
      fwrite($this->imapStreamResource, "DONE\r\n");
      fclose($this->imapStreamResource);
      $this->imapStreamResource = null;
    }
  }


  public function getImapStreamResource()
  {
    return $this->imapStreamResource;
  }


  public function isIdleRunning(): bool
  {
    return (bool)$this->idleRunning
      && !empty($this->idleCallback)
      && !empty($this->imapStreamResource);
  }


  protected function getCurrentIdleTagPrefix(): string
  {
    return $this->idleTagPrefix . $this->idleTag . ' ';
  }


  protected function sendCommand(
    string $command,
    bool $response = true,
    bool $responseAsArray = false
  ): string|array
  {
    $this->idleTag++;
    $this->idleLastCommand = $this->getCurrentIdleTagPrefix() . $command;
    fwrite($this->imapStreamResource, $this->idleLastCommand . "\r\n");
    $this->idleLastTime = time();
    return empty($response) ? (empty($responseAsArray) ? '' : []) : $this->readCommandResponse($responseAsArray);
  }


  protected function readCommandResponse(bool $asArray = false): string|array
  {
    $response = [];
    try {
      while ($line = trim($this->readCommandResponseLine())) {
        $response[] = $line;
        $prefix = $this->getCurrentIdleTagPrefix();
        if (str_starts_with($line, $prefix)) {
          if (str_starts_with($line, $prefix . 'BAD ')
            || str_starts_with($line, $prefix . 'NO ')
          ) {
            throw new Exception(X::_('Error response (command: %s): %s', $this->idleLastCommand, $line), 2);
          }

          if (empty($asArray)) {
            return $line;
          }
        }
      }
    }
    catch(Exception $e) {
      throw $e;
    }

    $this->idleLastTime = time();
    return !empty($asArray) ? $response : (!empty($response) ? $response[count($response) - 1] : '');
  }


  protected function readCommandResponseLine(): string
  {
    stream_set_blocking($this->imapStreamResource, false);
    $line = '';
    while (!in_array(Str::sub($line, -1), ["\n",  PHP_EOL])
      && $this->isIdleRunning()
    ) {
      if (($this->idleCallbackLastPing + $this->idleCallbackFrequency) <= time()) {
        $this->pingIdleCallback();
      }

      $this->idleCallback(['sync' => true]);

      if (!$this->_is_connected()
        || (($this->idleLastTime + $this->idleTimeout) < time())
      ) {
        throw new Exception(X::_('IDLE Connection lost'), 3);
      }

      $read = [$this->imapStreamResource];
      $write = $except = [];
      $n = @stream_select($read, $write, $except, $this->idleCallbackFrequency ?: 10);

      if (($n === 0) || ($n === false)) {
        continue;
      }

      $chunk = fgets($this->imapStreamResource, 1024);
      if ($chunk === false) {
        continue;
      }

      $line .= $chunk;
    }

    $this->idleLastTime = time();
    if ($this->isIdleRunning()
      && ($line === '')
    ) {
      throw new Exception(X::_('Empty response (command: %s)', $this->idleLastCommand), 1);
    }

    return $line;
  }


  protected function pingIdleCallback(): bool
  {
    $this->idleCallbackLastPing = time();
    if (empty($this->idleCallback)) {
      return false;
    }

    $ping = $this->idleCallback(['ping' => true]);
    if (empty($ping)) {
      throw new Exception(_("User connection lost"), 4);
    }

    return true;
  }


  protected function isIdleConnected(): bool
  {
    if (!empty($this->imapStreamResource)) {
      try {
        $this->sendCommand("NOOP");
        return true;
      }
      catch (Exception $e) {
        return false;
      }
    }

    return false;
  }
}