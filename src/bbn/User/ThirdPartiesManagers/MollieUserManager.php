<?php

namespace bbn\User\ThirdPartiesManagers;

use bbn\Api\Permissions\ApiPermissionsContract;
use bbn\User;

class MollieUserManager
{
  /**
   * @var User
   */
  protected User $user;

  /**
   * @var ApiPermissionsContract
   */
  protected ApiPermissionsContract $apiPermission;

  /**
   * MollieUserManager constructor.
   *
   * @param User $user The user being authenticated.
   * @param ApiPermissionsContract $apiPermission
   */
  public function __construct(User $user, ApiPermissionsContract $apiPermission)
  {
    $this->user          = $user;
    $this->apiPermission = $apiPermission;
  }

  /**
   * Redirect the user to get authorization.
   */
  public function authorize(): void
  {
    $authorization_url = $this->apiPermission->getAuthorizationUrl();

    // Get the state generated for you and store it to the session.
    $this->user->setSession($this->apiPermission->getProvider()->getState(), 'oauth2state');

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorization_url);
    exit;
  }

  /**
   * Get access token from authorization code and save it in database for the chosen account name.
   *
   * @param string $authorization_code
   * @param string $account_name
   * @return mixed
   * @throws \phpDocumentor\Parser\Exception
   */
  public function getAccessToken(string $authorization_code, string $account_name)
  {
    $tokens =  $this->apiPermission->getTokens($authorization_code);

    $access_token  = $tokens['access_token'];
    $refresh_token = $tokens['refresh_token'];
    $expires_in    = $tokens['expires_in']; // The number of seconds left before the access token expires.

    $this->saveTokensInDb($access_token, $refresh_token, $expires_in, $account_name);

    return $access_token;
  }

  /**
   * Refresh access token from the given refresh token and update it in db for the given account name.
   *
   * @param string $refresh_token
   * @param string $account_name
   * @return string
   * @throws \phpDocumentor\Parser\Exception
   */
  public function refreshAccessToken(string $refresh_token, string $account_name): string
  {
    $tokens = $this->apiPermission->refreshAccessToken($refresh_token);

    $access_token  = $tokens['access_token'];
    $refresh_token = $tokens['refresh_token'];
    $expires_in    = $tokens['expires_in']; // The number of seconds left before the access token expires.

    $this->updateTokensInDb($access_token, $refresh_token, $expires_in, $account_name);

    return $access_token;
  }

  /**
   * @param $access_token
   * @return array
   */
  public function getResourceOwner($access_token)
  {
    // Using the access token, we may look up details about the resource owner.
    $resourceOwner = $this->apiPermission->getProvider()->getResourceOwner($access_token);

    return $resourceOwner->toArray();
  }

  /**
   * @return void
   */
  public function unsetSessionState()
  {
    $this->user->unsetSession('oauth2state');
  }

  /**
   * Saves tokens and expiry for the given account name.
   *
   * @param string $access_token
   * @param string $refresh_token
   * @param int $expires_in
   * @param string $account_name
   * @return bool
   * @throws \phpDocumentor\Parser\Exception
   */
  protected function saveTokensInDb(string $access_token, string $refresh_token, int $expires_in, string $account_name)
  {
    return $this->user->saveNewPermissionTokens($access_token, $refresh_token, $expires_in, $account_name);
  }

  /**
   * Updates tokens and expiry for the given account name.
   *
   * @param string $access_token
   * @param string $refresh_token
   * @param int $expires_in
   * @param string $account_name
   * @return false|int|null
   */
  public function updateTokensInDb(string $access_token, string $refresh_token, int $expires_in, string $account_name)
  {
    return $this->user->updatePermissionTokens($account_name, $access_token, $refresh_token, $expires_in);
  }
}