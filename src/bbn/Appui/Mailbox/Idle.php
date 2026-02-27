<?php

namespace bbn\Appui\Mailbox;

use bbn\Appui\Mailbox;
use Exception;
use bbn\X;
use bbn\Str;


/**
 * Trait providing functionality for IMAP IDLE.
 */
class Idle
{
  /**
   * @var Mailbox The mailbox instance
   */
  protected $mailbox;

  /**
   * @var string The login of the mailbox
   */
  protected $login;

  /**
   * @var string The password of the mailbox
   */
  protected $pass;

  /**
   * @var string The folder UID
   */
  protected $folder;

  /**
   * @var resource|false|null IMAP stream resource
   */
  protected $streamResource;

  /**
   * @var float Last time an IDLE command was sent
   */
  protected $lastTime = 0;

  /**
   * @var string The last IDLE command sent
   */
  protected $lastCommand;

  /**
   * @var string The IDLE tag
   */
  protected $tag;

  /**
   * @var string The IDLE tag prefix
   */
  protected $tagPrefix = 'BBN_';

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
   * @var int The timeout for the IDLE command
   */
  protected $timeout = 300;


  /**
   * Idle constructor.
   * @param Mailbox $mailbox The mailbox instance
   * @param string $login The login of the mailbox
   * @param string $pass The password of the mailbox
   * @param string $folder The folder UID
   * @param callable $callback The callback function to call when a new message arrives
   * @param int|null $timeout The timeout in seconds for the IDLE connection
   */
  public function __construct(
    Mailbox $mailbox,
    string $login,
    string $pass,
    string $folder,
    callable $callback,
    ?int $timeout = null
  )
  {
    $this->mailbox = $mailbox;
    $this->login = $login;
    $this->pass = $pass;
    $this->folder = $folder;
    $this->callback = $callback;
    if (!empty($timeout)) {
      $this->timeout = $timeout;
    }
  }


  /**
   * Idle destructor. Ensures that the IDLE connection is stopped when the object is destroyed.
    * @return void
   */
  public function __destruct()
  {
    $this->stopIdle();
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
    $this->connect();

    try {
      $this->sendCommand("LOGIN {$this->login} {$this->pass}");
      $this->sendCommand("SELECT {$this->folder}");
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
              if (($action === 'mailFlagged')
                && isset($m['flags'])
                && (trim($m['flags']) !== '')
              ) {
                $data['flags'] = preg_split('/\s+/', trim($m['flags']));
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
        if (!$this->mailbox->check()
          || (($this->lastTime + $this->timeout) < time())
        ) {
          // Stop current IDLE connection
          $this->stopIdle();
          // Close the main stream
          if (!empty($this->mailbox->getStream())) {
            imap_close($this->mailbox->getStream());
          }

          // Establish a new connection
          $this->mailbox->connect();
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
      fclose($this->streamResource);
      $this->streamResource = null;
    }
  }


  /**
   * Returns the current stream resource used for the IDLE connection.
   * @return resource|null The stream resource for the IDLE connection, or null if not connected
   */
  public function getStreamResource()
  {
    return $this->streamResource;
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
   * Establishes a connection to the IMAP server using the mailbox credentials. Sets up the stream resource for the IDLE connection. Throws an exception if the connection fails.
    * @return void
   */
  protected function connect()
  {
    $context = [];
    if ($this->mailbox->getEncription()) {
      $context['ssl'] = [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ];
    }

    // Establish the connection
    $this->streamResource = stream_socket_client(
      ($this->mailbox->getEncription() ? "ssl" : "tls") . "://{$this->mailbox->getHost()}:{$this->mailbox->getPort()}",
      $errno,
      $errstr,
      $this->timeout,
      STREAM_CLIENT_CONNECT,
      stream_context_create($context)
    );
    if (!$this->streamResource) {
      throw new Exception(X::_("Failed to connect: %s (%s)", $errstr, $errno));
    }
  }


  /**
   * Generates the current IDLE command tag based on the tag prefix and the current tag number.
   * @return string The current IDLE command tag
   */
  protected function getCurrentTagPrefix(): string
  {
    return $this->tagPrefix . $this->tag . ' ';
  }


  /**
   * Sends a command to the IMAP server through the stream resource. Increments the tag number for each command sent. Optionally waits for a response from the server and returns it as a string or an array of lines.
   * @param string $command The command to send to the IMAP server
   * @param bool $response Whether to wait for a response from the server after sending the command
   * @param bool $responseAsArray Whether to return the response as an array of lines instead of a single string (only applicable if $response is true)
   * @return string|array The response from the server as a string or an array of lines, depending on the $responseAsArray parameter. Returns an empty string or array if $response is false.
   * @throws Exception If there is an error response from the server or if the response is empty when a response is expected
   */
  protected function sendCommand(
    string $command,
    bool $response = true,
    bool $responseAsArray = false
  ): string|array
  {
    $this->tag++;
    $this->lastCommand = $this->getCurrentTagPrefix() . $command;
    fwrite($this->streamResource, $this->lastCommand . "\r\n");
    $this->lastTime = time();
    return empty($response) ? (empty($responseAsArray) ? '' : []) : $this->readCommandResponse($responseAsArray);
  }


  /**
   * Reads the response from the IMAP server after sending a command. Collects lines of response until it detects the end of the response based on the command tag. Checks for error responses and throws exceptions if an error is detected. Returns the response as a string or an array of lines, depending on the $asArray parameter.
   * @param bool $asArray Whether to return the response as an array of lines instead of a single string
   * @return string|array The response from the server as a string or an array of lines, depending on the $asArray parameter
   * @throws Exception If there is an error response from the server or if the response is empty when a response is expected
   */
  protected function readCommandResponse(bool $asArray = false): string|array
  {
    $response = [];
    try {
      while ($line = trim($this->readCommandResponseLine())) {
        $response[] = $line;
        $prefix = $this->getCurrentTagPrefix();
        if (str_starts_with($line, $prefix)) {
          if (str_starts_with($line, $prefix . 'BAD ')
            || str_starts_with($line, $prefix . 'NO ')
          ) {
            throw new Exception(X::_('Error response (command: %s): %s', $this->lastCommand, $line), 2);
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

    $this->lastTime = time();
    return !empty($asArray) ? $response : (!empty($response) ? $response[count($response) - 1] : '');
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

      if (!$this->mailbox->check()
        || (($this->lastTime + $this->timeout) < time())
      ) {
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


  /**
   * Checks if the IDLE connection is currently established and the stream resource is valid. Sends a NOOP command to the server to verify the connection is still alive. Returns true if the connection is valid, false otherwise.
   * @return bool True if the IDLE connection is established and valid, false otherwise
    * @throws Exception If there is an error response from the server when sending the NOOP
   */
  protected function isConnected(): bool
  {
    if (!empty($this->streamResource)) {
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