<?php

namespace bbn\User\ThirdPartiesManagers;

use Mollie\Api\MollieApiClient;

class MollieOnboardingManager
{

  protected $onboarding;

  /**
   * MollieOnboardingManager constructor.
   *
   * https://docs.mollie.com/connect/onboarding
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
}