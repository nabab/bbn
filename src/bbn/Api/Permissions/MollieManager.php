<?php

namespace bbn\Api\Permissions;

use bbn\X;
use Mollie\Api\MollieApiClient;

class MollieManager
{

  private MollieApiClient $mollie;

  /**
   * MollieManager constructor.
   *
   * @param string $access_token
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function __construct(string $access_token)
  {
    $this->mollie = new MollieApiClient();
    $this->mollie->setAccessToken($access_token);
  }


  /**
   * @return MollieApiClient
   */
  public function getProvider()
  {
    return $this->mollie;
  }

  /**
   * https://docs.mollie.com/reference/v2/onboarding-api/get-onboarding-status
   *
   * @param $access_token
   * @return \Mollie\Api\Resources\Onboarding
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  private function getOnboardingObject(): \Mollie\Api\Resources\Onboarding
  {
    return $this->mollie->onboarding->get();
  }

  /**
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function canReceivePayments(): bool
  {
    return $this->getOnboardingObject()->canReceivePayments;
  }

  /**
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function canReceiveSettlements(): bool
  {
    return $this->getOnboardingObject()->canReceiveSettlements;
  }

  /**
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function onboardingIsInReview(): bool
  {
    return $this->getOnboardingObject()->isInReview();
  }

  /**
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function onboardingIsCompleted(): bool
  {
    return $this->getOnboardingObject()->isCompleted();
  }

  /**
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function onboardingNeedsData(): bool
  {
    return $this->getOnboardingObject()->needsData();
  }

  /**
   * @return string
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getDashboardLink(): string
  {
    return $this->getOnboardingObject()->_links->dashboard->href;
  }

  /**
   * @return string
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getOnboardingStatus(): string
  {
    return $this->getOnboardingObject()->status;
  }


  /**
   * Submits onboarding data.
   *
   * https://docs.mollie.com/reference/v2/onboarding-api/submit-onboarding-data
   *
   * @param array $data
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function submitOnboardingData(array $data): void
  {
    $this->mollie->onboarding->submit($data);
  }

  /**
   * Once you have created a payment, you should redirect your customer to the URL in the $payment['_links']['checkout']['href']
   *
   * https://docs.mollie.com/reference/v2/payments-api/create-payment
   *
   * @param array $data
   * @param string|null $customer_id
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function createPayment(array $data, ?string $customer_id = null): array
  {
    if ($customer_id) {
      $data = array_merge($data, ['customerId' => $customer_id]);
    }

    $payment = $this->mollie->payments->create($data);

    return X::toArray($payment);
  }

  /**
   * Create a payment for the first time for the user.
   * Once you have created a payment, you should redirect your customer to the URL in the $payment['_links']['checkout']['href']
   *
   * This will return the customerId in the response.
   *
   * @param array $customer_data
   * @param array $payment_data
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function createPaymentFirstTime(array $customer_data, array $payment_data)
  {
    $customer = $this->mollie->customers->create($customer_data);

    try {
      return $this->createPayment($payment_data, $customer->id);
    }
    catch (\Exception $e) {
      $this->mollie->customers->delete($customer->id);
      throw new \Exception($e->getMessage());
    }
  }

  /**
   * Creates a Refund on the Payment. The refunded amount is credited to your customer.
   *
   * https://docs.mollie.com/reference/v2/refunds-api/create-refund
   *
   * @param string $payment_id
   * @param string $amount
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function refundPayment(string $payment_id, string $amount, array $params = []): array
  {
    $payment = $this->mollie->payments->get($payment_id, $params);

    $params = array_merge([
      "amount" => [
        "currency" => $payment->amount->currency,
        "value"    => $amount
      ]
    ], $params);

    $refund = $payment->refund($params);

    return X::toArray($refund);
  }

  /**
   * Return the currently authenticated organization object.
   *
   * https://docs.mollie.com/reference/v2/organizations-api/current-organization
   *
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getOrganization(): array
  {
    $organization =  $this->mollie->organizations->current();

    return X::toArray($organization);
  }

  /**
   * Return the currently authenticated organization id.
   *
   * @return string
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getOrganizationId() :string
  {
    return $this->mollie->organizations->current()->id;
  }

  /**
   * Create a profile in order to process payments.
   *
   * https://docs.mollie.com/reference/v2/profiles-api/create-profile
   * 
   * @param array $data
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function createProfile(array $data): array
  {
    $profile = $this->mollie->profiles->create($data);

    return X::toArray($profile);
  }


  /**
   * Retrieve all profiles available on the account.
   *
   * https://docs.mollie.com/reference/v2/profiles-api/list-profiles
   *
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getProfiles(): array
  {
    $profiles = $this->mollie->profiles->page();

    return X::toArray($profiles);
  }


  /**
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function hasProfiles(): bool
  {
    return (bool)$this->mollie->profiles->page()->count;
  }

  /**
   * Retrieve all active payment methods.
   *
   * https://docs.mollie.com/reference/v2/methods-api/list-methods
   *
   * @param string $profile_id
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getActivePaymentMethods(string $profile_id): array
  {
    $methods = $this->mollie->methods->allActive([
      'profileId' => $profile_id
    ]);

    return X::toArray($methods);
  }

  /**
   * Retrieve all enabled payment methods.
   *
   * https://docs.mollie.com/reference/v2/methods-api/list-methods
   *
   * @param string $profile_id
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getAllPaymentMethods(string $profile_id): array
  {
    $methods = $this->mollie->methods->allAvailable([
      'profileId' => $profile_id
    ]);

    return X::toArray($methods);
  }

  /**
   * @param string $profile_id
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function hasActivePaymentMethods(string $profile_id): bool
  {
    return (bool)$this->mollie->methods->allActive([
      'profileId' => $profile_id
    ])
      ->count;
  }

  /**
   * Enable a payment method on a specific or authenticated profile to use it with payments.
   *
   * https://docs.mollie.com/reference/v2/profiles-api/enable-method#
   *
   * List of payment methods: https://docs.mollie.com/reference/v2/methods-api/list-methods
   *
   * @param string $profile_id
   * @param string $payment_method_id
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function enablePaymentMethod(string $profile_id, string $payment_method_id): array
  {
    $profile = $this->mollie->profiles->get($profile_id);

    $method = $profile->enableMethod($payment_method_id);

    return X::toArray($method);
  }

  /**
   * Retrieve all payments created ordered from newest to oldest.
   * This will list all payments not only the paid ones
   * so when looping through results you can check if a payment is paid:
   *
   * ```php
   * $client_payments = $mollie_manager->listPayments([
   *  'testmode' => true,
   * ]);
   * foreach ($client_payments as $payment) {
   *  if (!empty($payment['paidAt'])) {
   *    echo "ID: $payment['id'] <br>";
   *    echo "Amount: {$payment['amount']['value']} {$payment['amount']['currency']} <br>";
   *    echo "Status: {$payment['status']} <br><br>";
   *  }
   * }
   * ```
   *
   * https://docs.mollie.com/reference/v2/payments-api/list-payments
   *
   * @param array $params
   * @param string|null $from Used for pagination. Offset the result set to the payment with this ID. The payment with this ID is included in the result set as well.
   * @param int $limit The number of payments to return (with a maximum of 250).
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function listPayments(array $params = [], string $from = null, int $limit = 250): array
  {
    $payments = $this->mollie->payments->page($from, $limit, $params);

    return X::toArray($payments);
  }

  /**
   * Retrieve a single payment object by it's id.
   *
   * https://docs.mollie.com/reference/v2/payments-api/get-payment
   *
   * @param string $payment_id
   * @param array $params
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getPayment(string $payment_id, array $params = []): array
  {
    $payment = $this->mollie->payments->get($payment_id, $params);

    return X::toArray($payment);
  }

  /**
   * Retrieve all main account payments created ordered from newest to oldest.
   * This will list all payments not only the paid ones
   * so when looping through results you can check if a payment is paid:
   *
   * ```php
   * $_payments = $mollie_manager->listMainAccountPayments();
   * foreach ($payments as $payment) {
   *  if (!empty($payment['paidAt'])) {
   *    echo "ID: $payment['id'] <br>";
   *    echo "Amount: {$payment['amount']['value']} {$payment['amount']['currency']} <br>";
   *    echo "Status: {$payment['status']} <br><br>";
   *  }
   * }
   * ```
   *
   * https://docs.mollie.com/reference/v2/payments-api/list-payments
   *
   * @param string $api_key
   * @param array $params
   * @param string|null $from Used for pagination. Offset the result set to the payment with this ID. The payment with this ID is included in the result set as well.
   * @param int $limit The number of payments to return (with a maximum of 250).
   * @return array return array of \Mollie\Api\Resources\Payment objects
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function listMainAccountPayments(
    string $api_key,
    array $params = [],
    string $from = null,
    int $limit = 250
  ): array
  {
    $mollie = new MollieApiClient();
    $mollie->setApiKey($api_key);

    $payments = $mollie->payments->page($from, $limit, $params);

    return X::toArray($payments);
  }

  /**
   * Retrieve a single payment object by it's id.
   *
   * https://docs.mollie.com/reference/v2/payments-api/get-payment
   *
   * @param string $api_key
   * @param string $payment_id
   * @param array $params
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   `*/
  public function getMainAccountPayment(string $api_key, string $payment_id, array $params = []): array
  {
    $mollie = new MollieApiClient();
    $mollie->setApiKey($api_key);

    $payment = $mollie->payments->get($payment_id, $params);

    return X::toArray($payment);
  }
}