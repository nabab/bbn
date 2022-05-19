<?php

namespace bbn\Api;

use bbn\X;
use bbn\Str;

/**
 * GitLab API class
 * @category Api
 * @package Api
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Api/GitLab
 */
class GitLab
{


  use GitLab\User;
  use GitLab\Project;
  use GitLab\Branch;

  /** @var string The access token */
  protected $token;

  /** @var string The GitLab instance URL */
  protected $host;

  /** @var string The last error */
  protected $lastError = '';

  /**
   * Constructor.
   * @param array $cfg
   */
  public function __construct(array $cfg)
  {
    if (empty($cfg['token'])) {
      throw new \Error(_('The access token is mandatory'));
    }

    $this->token = $cfg['token'];
    $host        = !empty(\trim($cfg['host'])) ? \trim($cfg['host']) : 'localhost';
    $this->host  = $host . (\str_ends_with($host, '/') ? '' : '/') . 'api/v4/';
  }


  /**
   * Gets the last error
   * @return string
   */
  public function getLastError(): string
  {
    return $this->lastError;
  }


  /**
   * Returns true if an error has occurred
   * @return bool
   */
  public function hasError(): bool
  {
    return !empty($this->lastError);
  }


  /**
   * Make a request to the GitLab instance
   * @param string $url The part of the url related to the action to be performed
   * @return array
   */
  private function request($url): array
  {
    // Set the lastRequest property
    $this->lastRequest = $url . '?private_token=' . $this->token;
    // Make the curl request
    $response = X::curl($this->lastRequest, null, []);
    // Check if the response is a JSON string and convert it
    if (Str::isJson(($response))) {
      $response = \json_decode($response);
    }
    // Check if the request went in error
    $this->checkError($response);
    return $this->toArray($response);
  }

  /**
   * Checks if a request went in error
   * @param mixin $data A request response
   * @return bool
   */
  private function checkError($data): bool
  {
    // Reset lastError property
    $this->setLastError('', false);
    // Check if an error message is present
    if (\is_object($data) && !empty($data->message)) {
      // Set the error to lastError property and throw exception
      $this->setLastError($data->message);
      return true;
    }
    return false;
  }

  /**
   * Sets the lastError property with the last request error
   * @param string $message The error message
   * @param bool $exc True if you want throw the exception
   */
  private function setLastError(string $message, bool $exc = true)
  {
    // Set the error message to lastError property
    $this->lastError = $message;
    if ($exc) {
      // Throw exception
      throw new \Exception($message);
    }
  }

  /**
   * Transforms a request response to an array
   * @param mixin $response
   * @return array
   */
  private function toArray($response): array
  {
    if (\is_object($response)) {
      $response = X::toArray($response);
    }
    if (!\is_array($response)) {
      $response = [];
    }
    return $response;
  }

}