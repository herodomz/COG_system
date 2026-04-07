<?php
// config/xendit.php  –  Xendit sandbox credentials
//
// Sign up / get your sandbox keys at: https://dashboard.xendit.co
// Settings → API Keys → Generate secret key
//
// For sandbox testing, use the key prefixed with "xnd_development_"
// All values are read from .env (see config/env.php).
// You can also hard-code them here during local development if needed.

// The values below are only fallback defaults used when the .env key is absent.
// Set these in your .env file instead of editing this file directly.

defined('XENDIT_SECRET_KEY')    || define('XENDIT_SECRET_KEY',    env('XENDIT_SECRET_KEY',    'xnd_development_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'));
defined('XENDIT_WEBHOOK_TOKEN') || define('XENDIT_WEBHOOK_TOKEN', env('XENDIT_WEBHOOK_TOKEN', 'your_xendit_webhook_verification_token_here'));
defined('XENDIT_SUCCESS_URL')   || define('XENDIT_SUCCESS_URL',   env('XENDIT_SUCCESS_URL',   'https://your-ngrok-url.ngrok-free.app/student/payment_success.php'));
defined('XENDIT_CANCEL_URL')    || define('XENDIT_CANCEL_URL',    env('XENDIT_CANCEL_URL',    'https://your-ngrok-url.ngrok-free.app/student/payment_cancel.php'));
defined('XENDIT_CALLBACK_URL')  || define('XENDIT_CALLBACK_URL',  env('XENDIT_CALLBACK_URL',  'https://your-ngrok-url.ngrok-free.app/student/payment_callback.php'));