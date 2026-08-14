<?php
namespace bbn\Appui\Mailbox;

use bbn\Str;
use bbn\X;
use Exception;
use bbn\Models\Cls\Basic;

/**
 * Class providing functionality for raw IMAP client operations.
 */
class Client extends Basic
{
  /**
   * @var string The host of the IMAP server
   */
  protected string $host;

  /**
   * @var int The port of the IMAP server
   */
  protected int $port;

  /**
   * @var bool Whether to use encryption for the connection
   */
  protected bool $encryption;

  /**
   * @var string The login of the mailbox
   */
  protected string $login;

  /**
   * @var string The password of the mailbox
   */
  protected string $pass;

  /**
   * @var resource|false|null IMAP stream resource
   */
  protected $streamResource = null;

  /**
   * @var int The timeout for the connection
   */
  protected int $timeout = 300;

  /**
   * @var int The current tag counter
   */
  protected int $tag = 0;

  /**
   * @var string The tag prefix
   */
  protected string $tagPrefix = 'BBN_';

  /**
   * @var float Last time an IDLE command was sent
   */
  protected float $lastTime = 0;

  /**
   * @var string The last IDLE command sent
   */
  protected ?string $lastCommand = null;

  /**
   * @var string The last tag used in a command
   */
  protected ?string $lastTag = null;

  /**
   * @var bool Whether the client is currently in the process of disconnecting
   */
  protected bool $disconnecting = false;

  /**
   * @var bool Whether the client is currently in the process of communicating with the server
   */
  protected bool $communicating = false;

  /**
   * @var array The capabilities of the IMAP server
   */
  protected array $capabilities = [];


  /**
   * Escapes a string for use in IMAP commands by adding quotes and escaping special characters.
   * @param string $str The string to escape
   * @return string The escaped string, enclosed in quotes and with special characters escaped
   */
  public static function escapeString(string $str): string
  {
    return '"' . addcslashes($str, '"\\') . '"';
  }

  /**
   * Client constructor.
   * @param string $host The host of the IMAP server
   * @param int $port The port of the IMAP server
   * @param bool $encryption Whether to use encryption for the connection
   * @param string $login The login of the mailbox
   * @param string $pass The password of the mailbox
   * @param int|null $timeout The timeout in seconds for the connection
   * @return void
   */
  public function __construct(
    string $host,
    int $port,
    bool $encryption,
    string $login,
    string $pass,
    ?int $timeout = null
  )
  {
    $this->host = $host;
    $this->port = $port;
    $this->encryption = $encryption;
    $this->login = $login;
    $this->pass = $pass;
    if (!empty($timeout)) {
      $this->timeout = $timeout;
    }
  }

  /**
   * Destructor to ensure the connection is closed
   * @return void
   */
  public function __destruct()
  {
    if (!$this->isDisconnecting()) {
      $this->disconnect();
    }
  }

  /**
   * Connects to the IMAP server and performs login.
   * @return static
   * @throws Exception if connection or login fails
   */
  public function connect(): static
  {
    if (!empty($this->streamResource)) {
      return $this;
    }

    $context = [];
    $proto = 'tcp';
    if ($this->encryption) {
      $proto = 'ssl';
      $context['ssl'] = [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ];
    }

    $this->resetProperties();
    $this->streamResource = stream_socket_client(
      $proto . "://{$this->host}:{$this->port}",
      $errno,
      $errstr,
      $this->timeout,
      STREAM_CLIENT_CONNECT,
      stream_context_create($context)
    );

    if (!$this->streamResource) {
      throw new Exception(X::_("Failed to connect: %s (%s)", $errstr, $errno));
    }

    stream_set_timeout($this->streamResource, $this->timeout);

    $greet = fgets($this->streamResource, 4096);
    if ($greet === false) {
      throw new Exception(X::_("No IMAP greeting"));
    }

    // LOGIN
    $this->sendCommand("LOGIN " . static::escapeString($this->login) . " " . static::escapeString($this->pass));
    return $this;
  }

  /**
   * Disconnects from the IMAP server.
   * @return static
   */
  public function disconnect(): static
  {
    if (!empty($this->streamResource) && !$this->isDisconnecting()) {
      $this->disconnecting = true;
      try {
        $this->sendCommand("LOGOUT");
      }
      catch (\Throwable $e) {}

      fclose($this->streamResource);
      $this->streamResource = null;
      $this->resetProperties();
    }

    return $this;
  }

  /**
   * Checks if the client is currently in the process of disconnecting. This can be used to prevent multiple simultaneous disconnect attempts or to check if a disconnect operation is already underway. Returns true if the client is currently disconnecting, false otherwise.
   * @return bool True if the client is currently disconnecting, false otherwise
   */
  public function isDisconnecting(): bool
  {
    return $this->disconnecting;
  }

  /**
   * Checks if the connection is currently established and the stream resource is valid. Sends a NOOP command to the server to verify the connection is still alive. Returns true if the connection is valid, false otherwise.
   * @return bool True if the connection is established and valid, false otherwise
    * @throws Exception If there is an error response from the server when sending the NOOP
   */
  public function isConnected(): bool
  {
    if (!empty($this->getStreamResource())) {
      if ($this->isCommunicating()) {
        return true;
      }

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

  /**
   * Checks if the client is currently in the process of communicating with the server (e.g., sending a command or waiting for a response). This can be used to prevent multiple simultaneous operations that may interfere with each other. Returns true if the client is currently communicating, false otherwise.
   * @return bool True if the client is currently communicating, false otherwise
   */
  public function isCommunicating(): bool
  {
    return $this->communicating;
  }

  /**
   * Sets the communicating state of the client to false
   */
  public function closeCommunication(): static
  {
    $this->communicating = false;
    return $this;
  }

  /**
   * Returns the current stream resource used for the connection.
   * @return resource|null The stream resource for the connection, or null if not connected
   */
  public function getStreamResource()
  {
    return $this->streamResource;
  }

  /**
   * Writes a string of data to the IMAP server through the stream resource. Appends a CRLF to the data if it does not already end with one. Returns true if the data was successfully written, false otherwise.
   * @param string $data The data to write to the server
   * @return bool True if the data was successfully written, false otherwise
   */
  public function write(
    string $data,
    bool $appendCRLF = true,
    bool $closeCommunication = false
  ): bool
  {
    if (($sr = $this->getStreamResource())
      && strlen($data)
    ) {
      $this->communicating = true;
      $data .= $appendCRLF && !str_ends_with($data, "\r\n")
        ? "\r\n"
        : '';
      $len = strlen($data);
      $written = 0;
      while ($written < $len) {
        $read = [];
        $write = [$sr];
        $except = [];
        $n = @stream_select($read, $write, $except, 10);
        if ($n === false) {
          throw new Exception('stream_select failed while writing');
        }

        if ($n === 0) {
          $meta = stream_get_meta_data($sr);
          if (!empty($meta['timed_out'])) {
            throw new Exception('Write failed (timed out)');
          }

          continue;
        }

        $chunk = fwrite($sr, substr($data, $written));
        if ($chunk === false) {
          throw new Exception('Write failed');
        }

        if ($chunk === 0) {
          continue;
        }

        $written += $chunk;
      }

      if ($closeCommunication) {
        $this->closeCommunication();
      }

      return true;
    }

    return false;
  }

  /**
   * Sends a command to the IMAP server through the stream resource. Increments the tag number for each command sent. Optionally waits for a response from the server and returns it as a string or an array of lines.
   * @param string $command The command to send to the IMAP server
   * @param bool $response Whether to wait for a response from the server after sending the command
   * @param bool $allResponse Whether to return the response as an array of lines instead of a single string (only applicable if $response is true)
   * @return string|array The response from the server as a string or an array of lines, depending on the $allResponse parameter. Returns an empty string or array if $response is false.
   * @throws Exception If there is an error response from the server or if the response is empty when a response is expected
   */
  public function sendCommand(
    string $command,
    bool $allResponse = false,
    bool $response = true,
    bool $closeCommunication = false
  ): string|array
  {
    $tag = $this->getNextTag();
    $this->lastTag = $tag;
    $this->lastCommand = $tag . $command;
    $this->write($this->lastCommand);
    $this->lastTime = time();

    if (!$response) {
      if ($closeCommunication) {
        $this->closeCommunication();
      }

      return $allResponse ? [] : '';
    }

    return $this->readCommandResponse($tag, $allResponse);
  }

  /**
   * Gets the capabilities of the IMAP server by sending the CAPABILITY command and parsing the response. Returns an array of capability strings supported by the server.
   * @return array An array of capability strings supported by the server
   * @throws Exception If there is an error response from the server when sending the CAPABILITY
   */
  public function getCapabilities(): array
  {
    if (!empty($this->capabilities)) {
      return $this->capabilities;
    }

    $response = $this->sendCommand("CAPABILITY");
    if (preg_match('/^\*\s+CAPABILITY\s+(.+)$/i', $response, $m)) {
      $caps = preg_split('/\s+/', trim($m[1])) ?: [];
      $this->capabilities = array_values(array_unique(array_map('strtoupper', $caps)));
    }

    return $this->capabilities;
  }

  /**
   * Checks if the IMAP server supports a specific capability by retrieving the server capabilities and checking if the specified capability is present in the list. Returns true if the capability is supported, false otherwise.
   * @param string $capability The capability to check for support (case-insensitive)
   * @return bool True if the capability is supported by the server, false otherwise
   * @throws Exception If there is an error response from the server when sending the CAPABILITY
   */
  public function hasCapability(string $capability): bool
  {
    return in_array(strtoupper($capability), $this->getCapabilities(), true);
  }

  /**
   * Reads the response from the IMAP server after sending a command. Collects lines of response until it detects the end of the response based on the command tag. Checks for error responses and throws exceptions if an error is detected. Returns the response as a string or an array of lines, depending on the $allResponse parameter.
   * @param bool $allResponse Whether to return the response as an array of lines instead of a single string
   * @return string|array The response from the server as a string or an array of lines, depending on the $allResponse parameter
   * @throws Exception If there is an error response from the server or if the response is empty when a response is expected
   */
  public function readCommandResponse(string $tag, bool $allResponse = false): string|array
  {
    $response = [];

    while (true) {
      $line = rtrim($this->readCommandResponseLine(), "\r\n");
      $response[] = $line;

      if (preg_match('/^\+\s/', $line)) {
        continue;
      }

      if (str_starts_with($line, $tag)) {
        if (str_starts_with($line, $tag . 'BAD ')
          || str_starts_with($line, $tag . 'NO ')
        ) {
          throw new Exception(X::_('Error response (command: %s): %s', $this->lastCommand, $line), 2);
        }

        $this->closeCommunication();
        break;
      }
    }

    $this->lastTime = time();

    if (empty($response)) {
      throw new Exception(X::_('Empty response (command: %s)', $this->lastCommand), 2);
    }

    if (!empty($allResponse)) {
      return $response;
    }

    $idx = count($response) - 2;
    if ($idx < 0) {
      $idx = 0;
    }

    return $response[$idx];
  }


  /**
   * Reads a line of response from the IMAP server. Handles non-blocking reads and checks for connection timeouts. Calls the ping callback if the callback frequency has been exceeded. Throws exceptions if the connection is lost or if an empty response is received when a response is expected.
   * @return string The line of response read from the server
   * @throws Exception If the connection is lost or if an empty response is received when a
   */
  public function readCommandResponseLine(): string
  {
    stream_set_blocking($this->streamResource, false);
    try {
      $line = '';
      while (!in_array(Str::sub($line, -1), ["\n", PHP_EOL], true)) {
        if (($this->lastTime + $this->timeout) < time()) {
          throw new Exception(X::_('Connection lost'), 3);
        }

        $read = [$this->streamResource];
        $write = [];
        $except = [];
        $n = @stream_select($read, $write, $except, 10);
        if (($n === 0) || ($n === false)) {
          continue;
        }

        $chunk = fgets($this->streamResource, 8192);
        if ($chunk === false) {
          continue;
        }

        $line .= $chunk;
      }

      $this->lastTime = time();
      if ($line === '') {
        throw new Exception(X::_('Empty response (command: %s)', $this->lastCommand), 1);
      }

      return $line;
    }
    catch (Exception $e) {
      throw $e;
    }
    finally {
      stream_set_blocking($this->streamResource, true);
    }
  }

  /**
   * Returns the last tag used in a command. This can be useful for tracking the most recent command sent to the server and correlating it with responses received. Returns the last tag as a string, or null if no commands have been sent yet.
   * @return string|null The last tag used in a command, or null if no commands have been sent yet
   */
  public function getLastTag(): ?string
  {
    return $this->lastTag;
  }

  /**
   * Generates the next unique tag for IMAP commands by incrementing the internal tag counter and concatenating it with the tag prefix. Returns the generated tag as a string.
   */
  protected function getNextTag(): string
  {
    $this->tag++;
    return $this->tagPrefix . $this->tag . ' ';
  }

  /**
   * Resets the internal properties of the client to their initial state. This is useful for ensuring a clean state when reconnecting or reinitializing the client. Resets the tag counter, last time, last command, last tag, disconnecting state, communicating state, and capabilities.
   */
  protected function resetProperties(): void
  {
    $this->tag = 0;
    $this->lastTime = 0;
    $this->lastCommand = null;
    $this->lastTag = null;
    $this->disconnecting = false;
    $this->communicating = false;
    $this->capabilities = [];
  }

}