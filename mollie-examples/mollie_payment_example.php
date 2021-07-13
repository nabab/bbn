<?php

use bbn\User\ThirdPartiesManagers\MollieManager;

require '../vendor/autoload.php';

/** @var $ctrl \bbn\Mvc\Controller */
if ($tokens = $ctrl->inc->user->getPermissionTokensFromAccountName('My mollie account 1')) {
  // The saved access token in db
  $access_token = $tokens['access_token'];

  $mollie_manager = new MollieManager($access_token);

  if (!$mollie_manager->canReceivePayments()) {
    // User cannot receive payment as Mollie needs more information.
    // This should be checked before allowing the user to start accept payments
    // see mollie_onboarding_example.php
    exit;
  }

  // This should be saved in database when first onboarded but for this example we will do boarding here
  $organization_id = $mollie_manager->getOrganizationId();

  if (!$mollie_manager->hasProfiles()) {
    // If the user has no profiles, can create profile here
    $profile = $mollie_manager->createProfile([
      'name' => 'foo2',
      'website' => 'http://foo.bar',
      'email'    => 'foo@bar.com',
      'phone'     => '1234567'
    ]);
    $profile_id = $profile['id'];
  }
  else {
    $profiles = $mollie_manager->getProfiles();
    $profile_id = $profiles[0]['id'];
  }

  if (!$mollie_manager->hasActivePaymentMethods($profile_id)) {
    $mollie_manager->enablePaymentMethod($profile_id, 'creditcard');
  }

  $amount = 10;
  $fees   = 3.5;

  $payment = $mollie_manager->createPayment([
    "amount" => [
      "currency" => "EUR",
      "value" => number_format($amount, 2, '.', '') // You must send the correct number of decimals, thus we enforce the use of strings
    ],
    "description" => "Order #12345",
    "redirectUrl" => "",
    "webhookUrl" => "",
    "profileId" => $profile_id,
    "metadata" => [
      "order_id" => "12345",
    ],
    "applicationFee" => [
      "amount" => [
        "currency" => "EUR",
        "value" => number_format( $amount * ($fees / 100), 2, '.', '')
      ],
      "description" => "Fees" // Required: The description of the application fee. This will appear on settlement reports to the merchant and to you.
    ]
  ]);

  // Redirect to the checkout page
  header("Location: " . $payment['_links']['checkout']['href']);

}
else {
  throw new Exception('Account not found');
}

