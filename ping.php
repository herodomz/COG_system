<?php
// ping.php  –  Called by session-timeout.js every 5 min to keep session alive
define('SKIP_TIMEOUT_CHECK', true);
require_once __DIR__ . '/config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'expired', 'remaining' => 0]);
    exit();
}

Session::refreshActivity();
echo json_encode([
    'status'    => 'ok',
    'remaining' => Session::getRemainingTime(),
]);