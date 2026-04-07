<?php
// includes/Xendit.php  –  Xendit GCash e-wallet payment helper
require_once __DIR__ . '/../config/env.php';

class Xendit {

    private static function apiKey(): string {
        return env('XENDIT_SECRET_KEY', '');
    }

    private static function baseUrl(): string {
        return 'https://api.xendit.co';
    }

    /**
     * Create a Xendit GCash e-wallet charge.
     * Returns ['checkout_url' => ..., 'charge_id' => ...] on success,
     * or ['error' => 'message'] on failure.
     */
    public static function createGCashPayment(
        float  $amount,
        string $referenceNumber,
        string $email,
        string $name,
        string $phone       = '',
        string $description = 'Certificate of Grades'
    ): array {
        $apiKey = self::apiKey();
        if (empty($apiKey) || $apiKey === 'your_xendit_secret_key_here') {
            return ['error' => 'Xendit API key is not configured. Please update your .env file.'];
        }

        $successUrl = env('XENDIT_SUCCESS_URL', '') . '?ref=' . urlencode($referenceNumber);
        $cancelUrl  = env('XENDIT_CANCEL_URL', '');

        // Keep channel_properties minimal — mobile_number only if provided
        $channelProps = [
            'success_redirect_url' => $successUrl,
            'cancel_redirect_url'  => $cancelUrl,
            'failure_redirect_url' => $cancelUrl,
        ];
        if (!empty($phone)) {
            $channelProps['mobile_number'] = $phone;
        }

        $payload = [
            'reference_id'       => $referenceNumber,
            'currency'           => 'PHP',
            'amount'             => (int) round($amount),
            'checkout_method'    => 'ONE_TIME_PAYMENT',
            'channel_code'       => 'PH_GCASH',
            'channel_properties' => $channelProps,
            'metadata'           => [
                'student_name'  => $name,
                'student_email' => $email,
                'description'   => $description,
            ],
        ];

        $jsonPayload = json_encode($payload);
        error_log("Xendit request payload: {$jsonPayload}");

        $ch = curl_init(self::baseUrl() . '/ewallets/charges');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_USERPWD        => $apiKey . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Xendit cURL error: $curlError");
            return ['error' => 'Payment gateway connection failed. Please try again.'];
        }

        error_log("Xendit response ({$httpCode}): {$response}");

        $data = json_decode($response, true);

        if (($httpCode === 201 || $httpCode === 202) && !empty($data['actions']['mobile_web_checkout_url'])) {
            return [
                'checkout_url' => $data['actions']['mobile_web_checkout_url'],
                'charge_id'    => $data['id'] ?? '',
                'status'       => $data['status'] ?? '',
            ];
        }

        // Surface full error detail during development
        $errMsg = $data['message'] ?? ($data['error_code'] ?? 'Payment request failed.');
        if (!empty($data['errors'])) {
            $errMsg .= ' — ' . json_encode($data['errors']);
        }
        return ['error' => $errMsg];
    }

    /**
     * Verify the Xendit webhook callback token.
     * Skips verification in dev/sandbox when token is not configured.
     */
    public static function verifyWebhook(string $callbackToken): bool {
        $expected = env('XENDIT_WEBHOOK_TOKEN', '');

        if (empty($expected) || $expected === 'your_xendit_webhook_verification_token_here') {
            error_log("Xendit: XENDIT_WEBHOOK_TOKEN not set — skipping verification (dev mode).");
            return true;
        }

        return hash_equals($expected, $callbackToken);
    }

    /**
     * Fetch a charge by ID to verify its status directly from Xendit.
     */
    public static function getCharge(string $chargeId): array {
        $ch = curl_init(self::baseUrl() . '/ewallets/charges/' . $chargeId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => self::apiKey() . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
}