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

/** @var bbn\Mvc\Controller $ctrl */

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

// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {
  $mollie_manager->authorize(); // This will redirect the user to get authorization
}
// Check given state against previously stored one to mitigate CSRF attack
elseif (empty($_GET['state']) || ($_GET['state'] !== $mollie_manager->getSessionState())) {
  $mollie_manager->unsetSessionState();
  exit('Invalid state');
}
else {
  // This account name could be collected from the user before taking the action to connect his account
  // This account will be saved in the db for the user along with tokens and expiry
  $access_token = $mollie_manager->getAccessToken($_GET['code'], 'My mollie account 1');

  // Using the access token, we may look up details about the resource owner.
  $resourceOwner = $mollie_manager->getResourceOwner($access_token);

  print_r($resourceOwner);
}