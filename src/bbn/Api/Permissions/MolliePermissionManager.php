<?php

namespace bbn\Api\Permissions;

class MolliePermissionManager
{
  /**
   * @var MollieTokensHandlerContract
   */
  protected MollieTokensHandlerContract $tokensHandler;

  /**
   * @var ApiPermissionsContract
   */
  protected ApiPermissionsContract $apiPermission;

  /**
   * MollieUserManager constructor.
   *
   * @param MollieTokensHandlerContract $tokensHandler
   * @param ApiPermissionsContract $apiPermission
   */
  public function __construct(MollieTokensHandlerContract $tokensHandler, ApiPermissionsContract $apiPermission)
  {
    $this->tokensHandler = $tokensHandler;
    $this->apiPermission = $apiPermission;
  }

  /**
   * Redirect the user to get authorization.
   */
  public function authorize(): void
  {
    $authorization_url = $this->apiPermission->getAuthorizationUrl();

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $this->apiPermission->getProvider()->getState();

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
    if (isset($_SESSION['oauth2state'])) {
      unset($_SESSION['oauth2state']);
    }
  }

  public function getSessionState()
  {
    return $_SESSION['oauth2state'] ?? null;
  }

  /**
   * Saves tokens and expiry for the given account name.
   *
   * @param string $access_token
   * @param string $refresh_token
   * @param int $expires_in
   * @param string $account_name
   */
  protected function saveTokensInDb(string $access_token, string $refresh_token, int $expires_in, string $account_name)
  {
    return $this->tokensHandler->saveNewPermissionTokens($access_token, $refresh_token, $expires_in, $account_name);
  }

  /**
   * Updates tokens and expiry for the given account name.
   *
   * @param string $access_token
   * @param string $refresh_token
   * @param int $expires_in
   * @param string $account_name
   */
  public function updateTokensInDb(string $access_token, string $refresh_token, int $expires_in, string $account_name)
  {
    return $this->tokensHandler->updatePermissionTokens($account_name, $access_token, $refresh_token, $expires_in);
  }
}