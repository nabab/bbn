<?php

use bbn\Api\Permissions\MolliePermissionManager;
use bbn\Api\Permissions\MolliePermissions;
use bbn\Api\Permissions\MollieTokensHandlerContract;

require '../vendor/autoload.php';

$cfg = [
  'dev' => [
    'clientId' => 'app_TnytsnEcEpUtdvpPP7QURRh3',
    'clientSecret' => 'vdcMt7r5hpUx3cbWpeKNJRGzm5MtQbQTUrbsAaKQ',
    'redirectUri'  => 'https://grinksadmin.thomas.lan/mollie',
  ],
];

/** @var $ctrl \bbn\Mvc\Controller */

$token_handler = new class implements MollieTokensHandlerContract {

  public function saveNewPermissionTokens(string $access_token, string $refresh_token, int $expires_in, string $account_name)
  {
    // TODO: Implement saveNewPermissionTokens() method.
  }

  public function updatePermissionTokens(string $access_token, string $refresh_token, int $expires_in, string $account_name)
  {
    // TODO: Implement updatePermissionTokens() method.
  }
};

$mollie_manager = new MolliePermissionManager($token_handler, new MolliePermissions($cfg['dev']));

// An example of a user accessing his saved existing account.

// The saved tokens in db
$tokens = [
  'access_token'  => '',
  'refresh_token' => ''
];
$access_token = $tokens['access_token'];

if ($tokens['expire'] <= time()) {
  $access_token = $mollie_manager->refreshAccessToken($tokens['refresh_token'], 'My mollie account 1');
}

// Using the access token, we may look up details about the resource owner.
$resourceOwner = $mollie_manager->getResourceOwner($access_token);

print_r($resourceOwner);