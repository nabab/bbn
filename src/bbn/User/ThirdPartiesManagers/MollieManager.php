<?php

namespace bbn\User\ThirdPartiesManagers;

use Mollie\Api\MollieApiClient;

class MollieManager
{
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
  public function getOnboardingObject(): \Mollie\Api\Resources\Onboarding
  {
    return $this->mollie->onboarding->get();
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
  public function submitOnboardingData(array $data)
  {
    $this->mollie->onboarding->submit($data);
  }

  /**
   * Once you have created a payment, you should redirect your customer to the URL in the $payment->getCheckoutUrl()
   *
   * https://docs.mollie.com/reference/v2/payments-api/create-payment
   *
   * @param array $data
   * @return \Mollie\Api\Resources\Payment
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function createPayment(array $data): \Mollie\Api\Resources\Payment
  {
    return $this->mollie->payments->create($data);
  }

  /**
   * Return the currently authenticated organization object.
   *
   * https://docs.mollie.com/reference/v2/organizations-api/current-organization
   *
   * @return \Mollie\Api\Resources\Organization
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getOrganization(): \Mollie\Api\Resources\Organization
  {
    return $this->mollie->organizations->current();
  }

  /**
   * Return the currently authenticated organization id.
   *
   * @return string
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getOrganizationId() :string
  {
    return $this->getOrganization()->id;
  }

  /**
   * Create a profile in order to process payments.
   *
   * https://docs.mollie.com/reference/v2/profiles-api/create-profile
   * 
   * @param array $data
   * @return \Mollie\Api\Resources\BaseResource|\Mollie\Api\Resources\Profile
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function createProfile(array $data): \Mollie\Api\Resources\Profile
  {
    return $this->mollie->profiles->create($data);
  }


  /**
   * Retrieve all profiles available on the account.
   *
   * https://docs.mollie.com/reference/v2/profiles-api/list-profiles
   *
   * @return \Mollie\Api\Resources\ProfileCollection
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getProfiles(): \Mollie\Api\Resources\ProfileCollection
  {
    return $this->mollie->profiles->page();
  }


  /**
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function hasProfiles(): bool
  {
    return (bool)$this->getProfiles()->count;
  }

  /**
   * Retrieve all active payment methods.
   *
   * https://docs.mollie.com/reference/v2/methods-api/list-methods
   *
   * @param string $profile_id
   * @return \Mollie\Api\Resources\MethodCollection
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getActivePaymentMethods(string $profile_id): \Mollie\Api\Resources\MethodCollection
  {
    return $this->mollie->methods->allActive([
      'profileId' => $profile_id
    ]);
  }

  /**
   * Retrieve all enabled payment methods.
   *
   * https://docs.mollie.com/reference/v2/methods-api/list-methods
   *
   * @param string $profile_id
   * @return \Mollie\Api\Resources\MethodCollection
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getAllPaymentMethods(string $profile_id): \Mollie\Api\Resources\MethodCollection
  {
    return $this->mollie->methods->allAvailable([
      'profileId' => $profile_id
    ]);
  }

  /**
   * @param string $profile_id
   * @return bool
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function hasActivePaymentMethods(string $profile_id): bool
  {
    return (bool)$this->getActivePaymentMethods($profile_id)->count;
  }

  /**
   * Enable a payment method on a specific or authenticated profile to use it with payments.
   *
   * https://docs.mollie.com/reference/v2/profiles-api/enable-method#
   *
   * List of payment methods: https://docs.mollie.com/reference/v2/methods-api/list-methods
   *
   * @param string $profile_id
   * @param string $payment_method
   * @return \Mollie\Api\Resources\Method
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function enablePaymentMethod(string $profile_id, string $payment_method_id): \Mollie\Api\Resources\Method
  {
    $profile = $this->mollie->profiles->get($profile_id);

    return $profile->enableMethod($payment_method_id);
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
   *  if ($payment->isPaid()) {
   *    echo "ID: $payment->id <br>";
   *    echo "Amount: {$payment->amount->value} {$payment->amount->currency} <br>";
   *    echo "Status: {$payment->status} <br><br>";
   *  }
   * }
   * ```
   *
   * https://docs.mollie.com/reference/v2/payments-api/list-payments
   *
   * @param array $params
   * @param string|null $from Used for pagination. Offset the result set to the payment with this ID. The payment with this ID is included in the result set as well.
   * @param int $limit The number of payments to return (with a maximum of 250).
   * @return array return array of \Mollie\Api\Resources\Payment objects
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function listPayments(array $params = [], string $from = null, int $limit = 250): array
  {
    return (array)$this->mollie->payments->page($from, $limit, $params);
  }

  /**
   * Retrieve a single payment object by it's id.
   *
   * https://docs.mollie.com/reference/v2/payments-api/get-payment
   *
   * @param string $payment_id
   * @return \Mollie\Api\Resources\Payment
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getPayment(string $payment_id): \Mollie\Api\Resources\Payment
  {
    return $this->mollie->payments->get($payment_id);
  }

  /**
   * Retrieve all main account payments created ordered from newest to oldest.
   * This will list all payments not only the paid ones
   * so when looping through results you can check if a payment is paid:
   *
   * ```php
   * $_payments = $mollie_manager->listMainAccountPayments();
   * foreach ($payments as $payment) {
   *  if ($payment->isPaid()) {
   *    echo "ID: $payment->id <br>";
   *    echo "Amount: {$payment->amount->value} {$payment->amount->currency} <br>";
   *    echo "Status: {$payment->status} <br><br>";
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

    return (array)$mollie->payments->page($from, $limit, $params);
  }

  /**
   * Retrieve a single payment object by it's id.
   *
   * https://docs.mollie.com/reference/v2/payments-api/get-payment
   *
   * @param string $api_key
   * @param string $payment_id
   * @return \Mollie\Api\Resources\Payment
   * @throws \Mollie\Api\Exceptions\ApiException
   */
  public function getMainAccountPayment(string $api_key, string $payment_id): \Mollie\Api\Resources\Payment
  {
    $mollie = new MollieApiClient();
    $mollie->setApiKey($api_key);

    return $mollie->payments->get($payment_id);
  }
}