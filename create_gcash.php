<?php
require __DIR__ . '/vendor/autoload.php';

use Xendit\Configuration;
use Xendit\PaymentRequest\PaymentRequestApi;
use Xendit\PaymentRequest\PaymentRequestParameters;
use GuzzleHttp\Client;

$config = Configuration::getDefaultConfiguration()
    ->setApiKey('xnd_development_w7l6ZRGMxUhtrOtZltH1mdIKXz86KsKDTwOMwtYoL3yFdZihVlVHtx2uALJVWenN');

// IMPORTANT: pass a Guzzle client into the API instance
$apiInstance = new PaymentRequestApi(new Client(), $config);

$paymentRequest = new PaymentRequestParameters([
    'amount' => 100.00,
    'currency' => 'PHP',
    'reference_id' => uniqid('gcash_', true),
    'payment_method' => [
        'type' => 'EWALLET',
        'ewallet' => [
            'channel_code' => 'GCASH',
        ],
    ],
    'success_redirect_url' => 'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_success.php',
    'failure_redirect_url' => 'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_fail.php',
    'callback_url' => 'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_callback.php',
]);

try {
    $result = $apiInstance->createPaymentRequest(null, null, null, $paymentRequest);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when creating payment request: ', $e->getMessage(), PHP_EOL;
}
