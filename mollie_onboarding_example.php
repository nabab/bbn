<?php

use bbn\User\ThirdPartiesManagers\MollieOnboardingManager;

require 'vendor/autoload.php';

/** @var $ctrl \bbn\Mvc\Controller */

if ($tokens = $ctrl->inc->user->getPermissionTokensFromAccountName('My mollie account 1')) {
  // The saved access token in db
  $access_token = $tokens['access_token'];

  $onboarding = new MollieOnboardingManager($access_token);

  /**
   * @var \Mollie\Api\Resources\Onboarding
   */
  $onboarding_status_object = $onboarding->getOnboardingObject();

  /**
   * Either "needs-data", "in-review" or "completed".
   * Indicates this current status of the organization’s onboarding process.
   *
   * @var string
   */
  $onboarding_status = $onboarding->getOnboardingStatus();


  if ($onboarding_status_object->needsData()) {
    // The onboarding is not completed and the merchant needs to provide (more) information

    /** @var  $link  https://www.mollie.com/dashboard/onboarding */
    $link = $onboarding_status_object->_links->dashboard->href;


    if ($onboarding_status_object->canReceivePayments) {
      // You can start receiving payments. Before Mollie can pay out to your bank,
      // please provide some additional information. <Link to onboarding URL> $link
    } else {
      // Before you can receive payments, Mollie needs more information. <Link to onboarding URL> $link
    }
  }
  elseif ($onboarding_status_object->isInReview()) {
    // The merchant provided all information and Mollie needs to check this

    if ($onboarding_status_object->canReceivePayments) {
      // You can start receiving payments. Mollie is verifying your details to enable settlements to your bank.
    } else {
      // Mollie has all the required information and is verifying your details.
    }
  }
  elseif ($onboarding_status_object->isCompleted()) {
    // The onboarding is completed
  }

  // Submit onboarding data using API rather than redirecting customer
  $onboarding->submitOnboardingData([
    "organization" => [
      "name" => "Mollie B.V.",
      "address" => [
        "streetAndNumber" => "Keizersgracht 126",
        "postalCode" => "1015 CW",
        "city" => "Amsterdam",
        "country" => "NL",
      ],
      "registrationNumber" => "30204462",
      "vatNumber" => "NL815839091B01",
    ],
    "profile" => [
      "name" => "Mollie",
      "url" => "https://www.mollie.com",
      "email" => "info@mollie.com",
      "phone" => "+31208202070",
      "categoryCode" => 6012,
    ],
  ]);
}
else {
  throw new Exception('Account not found');
}
