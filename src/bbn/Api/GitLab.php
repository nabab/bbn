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
  use GitLab\Tag;
  use GitLab\Event;
  use GitLab\Issue;
  use GitLab\Note;
  use GitLab\Label;

  /** @var array The access levels */
  public static $accessLevels = [
    //0 => 'No access',
    5 => 'Minimal access',
    10 => 'Guest',
    20 => 'Reporter',
    30 => 'Developer',
    40 => 'Maintainer',
    50 => 'Owner'
  ];

  /** @var string The access token */
  protected $token;

  /** @var string The GitLab instance URL */
  protected $host;

  /** @var string The last error */
  protected $lastError = '';

  /** @var string The last request made */
  protected $lastRequest = '';

  /** @var array The last response header */
  protected $lastResponseHeader = [];

  /** @var string */
  protected $userURL = 'users/';

  /** @var string */
  protected $projectURL = 'projects/';

  /** @var string */
  protected $branchURL = 'branches/';

  /** @var string */
  protected $tagURL = 'tags/';

  /** @var string */
  protected $eventURL = 'events/';

  /** @var string */
  protected $issueURL = 'issues/';

  /** @var string */
  protected $noteURL = 'notes/';

  /** @var string */
  protected $labelURL = 'labels/';


  /**
   * Constructor.
   * @param array $cfg
   */
  public function __construct(string $accessToken, string $host = '')
  {
    if (empty($accessToken)) {
      throw new \Error(_('The access token is mandatory'));
    }

    $this->token = $accessToken;
    $host        = !empty(\trim($host)) ? \trim($host) : 'localhost';
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
   * Returns the last request response header
   * @return array
   */
  public function getLastResponseHeader(): array
  {
    return $this->lastResponseHeader;
  }


  /**
   * Make a request to the GitLab instance
   * @param string $url The part of the url related to the action to be performed
   * @param bool $isPost True if you want make a POST request
   * @return array
   */
  private function request(string $url, array $params = [], bool $isPost = false, bool $isDelete = false): array
  {
    // Set the lastRequest property
    $this->lastRequest = $this->host . $url . '?private_token=' . $this->token;
    foreach ($params as $k => $v) {
      $this->lastRequest .= '&' . $k . '=' . $v;
    }
    //die(var_dump($this->lastRequest));
    $options = [];
    if (!empty($isPost)) {
      $options['post'] = 1;
    }
    else if (!empty($isDelete)) {
      $options['delete'] = 1;
    }
    else {
      $options['header'] = 1;
    }
    // Make the curl request
    $response = X::curl($this->lastRequest, null, $options);
    if (empty($isPost) && empty($isDelete)) {
      $headerSize = X::lastCurlInfo()['header_size'];
      $header = explode("\r\n", substr($response, 0, $headerSize));
      $this->lastResponseHeader = [];
      foreach ($header as $v) {
        $tmp = \explode(':', $v);
        if (\count($tmp)) {
          $this->lastResponseHeader[\trim($tmp[0])] = \trim($tmp[1]);
        }
      }
      $response = substr($response, $headerSize);
    }
    // Check if the response is a JSON string and convert it
    if (Str::isJson(($response))) {
      $response = \json_decode($response);
    }
    // Check if the request went in error
    $this->checkError($response);
    return $this->toArray($response);
  }


  /**
   * Makes a POST request to the GitLab instance
   * @param string $url The part of the url related to the action to be performed
   * @param array $params The request params
   * @return array
   */
  private function post(string $url, array $params = []): array
  {
    return $this->request($url, $params, true);
  }


  /**
   * Makes a DELETE request to the GitLab instance
   * @param string $url The part of the url related to the action to be performed
   * @param array $params The request params
   * @return array
   */
  private function delete(string $url, array $params = []): bool
  {
    return empty($this->request($url, $params, false, true));
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
    // Check if an error is present
    if ((X::lastCurlCode() !== 200)
      && (X::lastCurlCode() !== 201)
      && (X::lastCurlCode() !== 204)
    ) {
      // Set the error to lastError property and throw exception
      $err = \is_object($data) ? (!empty($data->error) ? $data->error : (!empty($data->message) ? $data->message : $data)) : $data;
      $this->setLastError($err);
      return true;
    }
    return false;
  }

  /**
   * Sets the lastError property with the last request error
   * @param string $error The error message
   * @param bool $exc True if you want throw the exception
   */
  private function setLastError($error, bool $exc = true)
  {
    // Set the error message to lastError property
    $this->lastError = $error;
    if ($exc) {
      // Throw exception
      throw new \Exception(\is_object($error) ? json_encode($error) : $error);
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