<?php
require __DIR__ . '/vendor/autoload.php';

use Xendit\Xendit;

Xendit::setApiKey('xnd_development_w7l6ZRGMxUhtrOtZltH1mdIKXz86KsKDTwOMwtYoL3yFdZihVlVHtx2uALJVWenN');

$params = [
    'external_id' => uniqid('gcash_', true),
    'amount' => 100.00, // PHP 100
    'currency' => 'PHP',
    'payment_method' => 'GCASH',
    'callback_url' => 'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_callback.php',
    'success_redirect_url' => 'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_success.php',
    'failure_redirect_url' => 'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_fail.php',
];

$ewalletCharge = \Xendit\EWallets::create($params);

print_r($ewalletCharge);
