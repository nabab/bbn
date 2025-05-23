<?php


use bbn\Api\Permissions\MollieManager;

require '../vendor/autoload.php';

/** @var bbn\Mvc\Controller $ctrl */

// The saved access token in db
$access_token = '';

$mollie = new MollieManager($access_token);

/**
 * The organization id for the authenticated user.
 * Save it in database as it will be used to distribute payments.
 *
 * @var string
 */
$organization_id = $mollie->getOrganizationId();


/**
 * Either "needs-data", "in-review" or "completed".
 * Indicates this current status of the organization’s onboarding process.
 *
 * @var string
 */
$onboarding_status = $mollie->getOnboardingStatus();


if ($mollie->onboardingNeedsData()) {
  // The onboarding is not completed and the merchant needs to provide (more) information

  /** @var  $link  https://www.mollie.com/dashboard/onboarding */
  $link = $mollie->getDashboardLink();


  if ($mollie->canReceivePayments()) {
    // You can start receiving payments. Before Mollie can pay out to your bank,
    // please provide some additional information. <Link to onboarding URL> $link
  } else {
    // Before you can receive payments, Mollie needs more information. <Link to onboarding URL> $link
  }
}
elseif ($mollie->onboardingIsInReview()) {
  // The merchant provided all information and Mollie needs to check this

  if ($mollie->canReceivePayments()) {
    // You can start receiving payments. Mollie is verifying your details to enable settlements to your bank.
  } else {
    // Mollie has all the required information and is verifying your details.
  }
}
elseif ($mollie->onboardingIsCompleted()) {
  // The onboarding is completed
}

// Submit onboarding data using API rather than redirecting customer
$mollie->submitOnboardingData([
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
