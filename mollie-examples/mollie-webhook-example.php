<?php

use Mollie\Api\MollieApiClient;

require '../vendor/autoload.php';

if (!isset($_POST["id"])) {
  http_response_code(404);
  exit();
}

try {
  $mollie = new MollieApiClient();
  $mollie->setApiKey('test_McfHU3tyyqs2CbCeMvuzd6BRyQV8SG');

  /*
   * Retrieve the payment's current state.
   */
  $payment = $mollie->payments->get($_POST["id"]);
  $orderId = $payment->metadata->order_id;

  if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
    /*
     * The payment is paid and isn't refunded or charged back.
     */
  } elseif ($payment->isOpen()) {
    /*
     * The payment is open.
     */
  } elseif ($payment->isPending()) {
    /*
     * The payment is pending.
     */
  } elseif ($payment->isFailed()) {
    /*
     * The payment has failed.
     */
  } elseif ($payment->isExpired()) {
    /*
     * The payment is expired.
     */
  } elseif ($payment->isCanceled()) {
    /*
     * The payment has been canceled.
     */
  } elseif ($payment->hasRefunds()) {
    /*
     * The payment has been (partially) refunded.
     * The status of the payment is still "paid"
     */
  } elseif ($payment->hasChargebacks()) {
    /*
     * The payment has been (partially) charged back.
     * The status of the payment is still "paid"
     */
  }

} catch (\Exception $e) {
  echo "API call failed: " . htmlspecialchars($e->getMessage());
}