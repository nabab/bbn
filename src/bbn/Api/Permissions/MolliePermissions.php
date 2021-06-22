<?php

namespace bbn\Api\Permissions;

use bbn\User\Session;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Mollie\OAuth2\Client\Provider\Mollie;
use phpDocumentor\Parser\Exception;

class MolliePermissions implements ApiPermissionsContract
{

  /**
   * @var Mollie
   */
  protected Mollie $provider;

  /**
   * @var array
   */
  protected $cfg;

  public function __construct(array $cfg)
  {
    $this->cfg      = $cfg;
    $this->provider = new Mollie($this->cfg);
  }

  public function getAuthorizationUrl(): string
  {
    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $this->provider->getAuthorizationUrl([
      // Optional, only use this if you want to ask for scopes the user previously denied.
      'approval_prompt' => 'force',
      // Optional, a list of scopes. Defaults to only 'organizations.read'.
      'scope' => [
        Mollie::SCOPE_PAYMENTS_READ,
        Mollie::SCOPE_PAYMENTS_WRITE,
        Mollie::SCOPE_REFUNDS_READ,
        Mollie::SCOPE_REFUNDS_WRITE,
        Mollie::SCOPE_CUSTOMERS_READ,
        Mollie::SCOPE_CUSTOMERS_WRITE,
        Mollie::SCOPE_MANDATES_READ,
        Mollie::SCOPE_MANDATES_WRITE,
        Mollie::SCOPE_SUBSCRIPTIONS_READ,
        Mollie::SCOPE_SUBSCRIPTIONS_WRITE,
        Mollie::SCOPE_PROFILES_READ,
        Mollie::SCOPE_PROFILES_WRITE,
        Mollie::SCOPE_INVOICES_READ,
        Mollie::SCOPE_SETTLEMENTS_READ,
        Mollie::SCOPE_ORDERS_READ,
        Mollie::SCOPE_ORDERS_WRITE,
        Mollie::SCOPE_SHIPMENTS_READ,
        Mollie::SCOPE_SHIPMENTS_WRITE,
        Mollie::SCOPE_ORGANIZATIONS_READ,
        Mollie::SCOPE_ORGANIZATIONS_WRITE,
        Mollie::SCOPE_ONBOARDING_READ,
        Mollie::SCOPE_ONBOARDING_WRITE,
      ],
    ]);

    return $authorizationUrl;
  }

  /**
   * @param string $authorization_code
   * @throws Exception
   */
  public function getTokens(string $authorization_code): array
  {
    try
    {
      // Try to get an access token using the authorization code grant.
      $tokens = $this->provider->getAccessToken('authorization_code', [
        'code' => $authorization_code
      ]);

      return [
        'access_token'  => $tokens->getToken(),
        'refresh_token' => $tokens->getRefreshToken(),
        'expires_in'    => $tokens->getExpires()
      ];
    }
    catch (IdentityProviderException $e)
    {
      // Failed to get the access token or user details.
      exit($e->getMessage());
    }
  }

  public function refreshAccessToken(string $refresh_token): string
  {
    try {
      return $this->provider->getAccessToken(new RefreshToken(), ['refresh_token' => $refresh_token]);

    } catch (IdentityProviderException $e) {
      exit($e->getMessage());
    }
  }

  public function getProvider()
  {
    return $this->provider;
  }
}