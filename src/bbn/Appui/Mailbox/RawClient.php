<?php
namespace bbn\Appui\Mailbox;

use bbn\Str;
use bbn\X;
use Exception;

/**
 * Class providing functionality for raw IMAP client operations.
 */
class RawClient
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
   * RawClient constructor.
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
    $this->disconnect();
  }

  /**
   * Connects to the IMAP server and performs login.
   * @return void
   * @throws Exception if connection or login fails
   */
  public function connect(): void
  {
    if (!empty($this->streamResource)) {
      return;
    }

    $context = [];
    $proto = 'tls';
    if ($this->encryption) {
      $proto = 'ssl';
      $context['ssl'] = [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ];
    }

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

    // Server greeting
    $greet = fgets($this->streamResource, 4096);
    if ($greet === false) {
      throw new Exception(X::_("No IMAP greeting"));
    }

    // LOGIN
    $this->sendCommand("LOGIN {$this->login} {$this->pass}");
  }

  /**
   * Disconnects from the IMAP server.
   * @return void
   */
  public function disconnect(): void
  {
    if (!empty($this->streamResource)) {
      // Try logout (best-effort)
      try {
        $this->sendCommand("LOGOUT", true, false);
      }
      catch (\Throwable $e) {}

      fclose($this->streamResource);
      $this->streamResource = null;
    }
  }

  /**
   * Checks if the connection is currently established and the stream resource is valid. Sends a NOOP command to the server to verify the connection is still alive. Returns true if the connection is valid, false otherwise.
   * @return bool True if the connection is established and valid, false otherwise
    * @throws Exception If there is an error response from the server when sending the NOOP
   */
  public function isConnected(): bool
  {
    if (!empty($this->getStreamResource())) {
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
   * Returns the current stream resource used for the IDLE connection.
   * @return resource|null The stream resource for the IDLE connection, or null if not connected
   */
  public function getStreamResource()
  {
    return $this->streamResource;
  }

  /**
   * Sends a command to the IMAP server through the stream resource. Increments the tag number for each command sent. Optionally waits for a response from the server and returns it as a string or an array of lines.
   * @param string $command The command to send to the IMAP server
   * @param bool $response Whether to wait for a response from the server after sending the command
   * @param bool $responseAsArray Whether to return the response as an array of lines instead of a single string (only applicable if $response is true)
   * @return string|array The response from the server as a string or an array of lines, depending on the $responseAsArray parameter. Returns an empty string or array if $response is false.
   * @throws Exception If there is an error response from the server or if the response is empty when a response is expected
   */
  public function sendCommand(
    string $command,
    bool $response = true,
    bool $responseAsArray = false
  ): string|array
  {
    $this->tag++;
    $this->lastCommand = $this->getCurrentTagPrefix() . $command;
    fwrite($this->streamResource, $this->lastCommand . "\r\n");
    $this->lastTime = time();

    if (!$response) {
      return $responseAsArray ? [] : '';
    }

    return $this->readCommandResponse($responseAsArray);
  }

  /**
   * Gets the capabilities of the IMAP server by sending the CAPABILITY command and parsing the response. Returns an array of capability strings supported by the server.
   * @return array An array of capability strings supported by the server
   * @throws Exception If there is an error response from the server when sending the CAPABILITY
   */
  public function getCapabilities(): array
  {
    $response = $this->sendCommand("CAPABILITY", true);
    if (preg_match('/^\*\s+CAPABILITY\s+(.+)$/i', $response, $m)) {
      $caps = preg_split('/\s+/', trim($m[1])) ?: [];
      return array_values(array_unique(array_map('strtoupper', $caps)));
    }

    return [];
  }

  /**
   * Checks if the IMAP server supports a specific capability by retrieving the server capabilities and checking if the specified capability is present in the list. Returns true if the capability is supported, false otherwise.
   * @param string $capability The capability to check for support (case-insensitive)
   * @return bool True if the capability is supported by the server, false otherwise
   * @throws Exception If there is an error response from the server when sending the CAPABILITY
   */
  public function hasCapability(string $capability): bool
  {
    $caps = $this->getCapabilities();
    return in_array(strtoupper($capability), $caps);
  }

  /**
   * Gets the current tag prefix for commands.
   * @return string The current tag prefix
   */
  protected function getCurrentTagPrefix(): string
  {
    return $this->tagPrefix . $this->tag . ' ';
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

          break;
        }
      }
    }
    catch(Exception $e) {
      throw $e;
    }

    $this->lastTime = time();
    if (!empty($asArray)) {
      return $response;
    }

    if (empty($response)) {
      throw new Exception(X::_('Empty response (command: %s)', $this->lastCommand), 2);
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
  protected function readCommandResponseLine(): string
  {
    stream_set_blocking($this->streamResource, false);
    $line = '';
    while (!in_array(Str::sub($line, -1), ["\n",  PHP_EOL])) {
      if (($this->lastTime + $this->timeout) < time()) {
        throw new Exception(X::_('Connection lost'), 3);
      }

      $read = [$this->streamResource];
      $write = $except = [];
      $n = @stream_select($read, $write, $except, 10);
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
    if ($line === '') {
      throw new Exception(X::_('Empty response (command: %s)', $this->lastCommand), 1);
    }

    return $line;
  }
}