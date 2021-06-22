<?php

use bbn\Api\Permissions\MolliePermissions;
use bbn\User\ThirdPartiesManagers\MollieUserManager;

require 'vendor/autoload.php';

$cfg = [
  'dev' => [
    'clientId' => 'app_TnytsnEcEpUtdvpPP7QURRh3',
    'clientSecret' => 'vdcMt7r5hpUx3cbWpeKNJRGzm5MtQbQTUrbsAaKQ',
    'redirectUri'  => 'https://grinksadmin.thomas.lan/mollie',
  ],
];

/** @var $ctrl \bbn\Mvc\Controller */

$mollie_manager = new MollieUserManager($ctrl->inc->user, new MolliePermissions($cfg['dev']));

// An example of a user accessing his saved existing account.

if ($tokens = $ctrl->inc->user->getPermissionTokensFromAccountName('My mollie account 1')) {
  // The saved access token in db
  $access_token = $tokens['access_token'];

  if ($tokens['expire'] <= time()) {
    $access_token = $mollie_manager->refreshAccessToken($tokens['refresh_token'], 'My mollie account 1');
  }

  // Using the access token, we may look up details about the resource owner.
  $resourceOwner = $mollie_manager->getResourceOwner($access_token);

  print_r($resourceOwner);
}
 else {
   throw new Exception('Account not found');
 }