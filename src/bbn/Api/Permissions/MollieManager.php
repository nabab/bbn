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
   * This will return the `customerId` and `mandateId` in the response.
   *
   * @param array $customer_data
   * @param array $payment_data
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function createPaymentFirstTime($customer_data, array $payment_data): array
  {
    if (\is_array($customer_data)) {
      $customer = $this->mollie->customers->create($customer_data);  
    }
    else {
      $customer = new \stdClass();
      $customer->id = $customer_data;
    }

    $payment_data = array_merge($payment_data, ['sequenceType' => 'first']);

    try {
      return $this->createPayment($payment_data, $customer->id);
    }
    catch (\Exception $e) {
      if (\is_array($customer_data)) {
        $this->mollie->customers->delete($customer->id);
      }
      throw new \Exception($e->getMessage());
    }
  }

  /**
   * Charging immediately on-demand.
   *
   * https://docs.mollie.com/payments/recurring#payments-recurring-charging-on-demand
   *
   * @param array $payment_data
   * @param string $customer_id
   * @param string $mandate_id
   * @return array|null
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function createOnDemandPayment(array $payment_data, string $customer_id, string $mandate_id): ?array
  {
    if (!$this->customerHasValidMandate(
      $customer_id,
      $mandate_id,
      array_key_exists('testmode', $payment_data) ? ['test_mode' => $payment_data['testmode']] : [])
    ) {
      // If the mandate is not valid then creates a new one by having
      // The customer performs a first payment: createPaymentFirstTime()
      // Then update the new customer and mandate id in database.
      return null;
    }

    $payment_data = array_merge($payment_data, ['sequenceType' => 'recurring']);

    return $this->createPayment($payment_data, $customer_id);
  }

  /**
   * Checks if the provided mandate is valid.
   *
   * @param string $customer_id
   * @param string $mandate_id
   * @param array $params
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  private function customerHasValidMandate(string $customer_id, string $mandate_id, array $params = []): bool
  {
    $mandate = $this->getMandate($customer_id, $mandate_id, $params);

    return $mandate['status'] === 'valid';
  }

  /**
   * Retrieve a mandate by its ID and its customer’s ID.
   *
   * https://docs.mollie.com/reference/v2/mandates-api/get-mandate
   *
   * @param string $customer_id
   * @param string $mandate_id
   * @param array $params
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getMandate(string $customer_id, string $mandate_id, array $params = []): array
  {
    $customer = $this->getCustomerFromId($customer_id, $params);

    $mandate = $customer->getMandate($mandate_id, $params);

    return X::toArray($mandate);
  }

  /**
   * Retrieve all mandates for the given customerId, ordered from newest to oldest.
   *
   * https://docs.mollie.com/reference/v2/mandates-api/list-mandates
   *
   * @param string $customer_id
   * @param array $params
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function listMandates(string $customer_id, array $params = []): array
  {
    $customer = $this->getCustomerFromId($customer_id, $params);

    $mandates = $customer->mandates();

    return X::toArray($mandates);
  }

  /**
   * Revoke a customer’s mandate.
   *
   * You will no longer be able to charge the consumer’s bank account or credit card
   * with this mandate and all connected subscriptions will be canceled.
   *
   * https://docs.mollie.com/reference/v2/mandates-api/revoke-mandate
   *
   * @param string $customer_id
   * @param string $mandate_id
   * @param array $params
   * @return void
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function revokeMandate(string $customer_id, string $mandate_id, array $params = []): void
  {
    $customer = $this->getCustomerFromId($customer_id, $params);

    $mandate = $customer->getMandate($mandate_id);

    $mandate->revoke();
  }

  /**
   * Retrieve a single customer by its ID.
   *
   * https://docs.mollie.com/reference/v2/customers-api/get-customer
   *
   * @param string $customer_id
   * @param array $params
   * @return array
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getCustomer(string $customer_id, array $params = []): array
  {
    return X::toArray(
      $this->getCustomerFromId($customer_id, $params)
    );
  }

  /**
   * Retrieve a single customer by its ID.
   *
   * https://docs.mollie.com/reference/v2/customers-api/get-customer
   *
   * @param string $customer_id
   * @param array $params
   * @return \Mollie\Api\Resources\Customer
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  private function getCustomerFromId(string $customer_id, array $params = []): \Mollie\Api\Resources\Customer
  {
    return $this->mollie->customers->get($customer_id, $params);
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