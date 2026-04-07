<?php
// student/payment_callback.php  –  Xendit e-wallet webhook endpoint (POST only)
define('SKIP_TIMEOUT_CHECK', true);
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/Xendit.php';
require_once '../includes/Email.php';

// Accept POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo 'Method Not Allowed'; exit();
}

$callbackToken = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
if (empty($callbackToken)) {
    http_response_code(400); echo 'Missing callback token'; exit();
}

if (!Xendit::verifyWebhook($callbackToken)) {
    http_response_code(401); echo 'Invalid callback token'; exit();
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    http_response_code(400); echo 'Invalid JSON body'; exit();
}

$event    = $body['event']             ?? '';
$data     = $body['data']              ?? [];
$status   = strtoupper($data['status'] ?? '');
$ref      = trim($data['reference_id'] ?? '');
$amount   = $data['charge_amount']     ?? ($data['captured_amount'] ?? 0);
$chargeId = $data['id']                ?? '';

if ($status !== 'SUCCEEDED') {
    http_response_code(200); echo 'Acknowledged non-success event'; exit();
}
if (empty($ref)) {
    http_response_code(200); echo 'No reference_id'; exit();
}

$db = (new Database())->getConnection();

$stmt = $db->prepare(
    "SELECT r.*, u.id AS uid, u.email, u.full_name
       FROM cog_requests r JOIN users u ON r.user_id = u.id
      WHERE r.request_number = :ref AND r.payment_status = 'unpaid'"
);
$stmt->execute([':ref' => $ref]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(200); echo 'Already processed or not found'; exit();
}

$db->beginTransaction();
try {
    $db->prepare(
        "UPDATE cog_requests
            SET payment_status = 'paid',
                payment_date   = NOW(),
                status         = CASE WHEN status = 'pending' THEN 'processing' ELSE status END,
                admin_notes    = CONCAT(IFNULL(admin_notes,''), ' [xendit_paid:', :cid, ']')
          WHERE id = :id"
    )->execute([':cid' => $chargeId, ':id' => $request['id']]);

    $db->prepare(
        "INSERT INTO notifications (user_id, request_id, message) VALUES (:uid, :rid, :msg)"
    )->execute([
        ':uid' => $request['uid'],
        ':rid' => $request['id'],
        ':msg' => "✅ GCash payment of ₱{$amount} confirmed for request {$ref}. Your COG is now being processed.",
    ]);

    $db->prepare(
        "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by)
         VALUES (:rid, :old, 'processing', NULL)"
    )->execute([':rid' => $request['id'], ':old' => $request['status']]);

    $db->commit();

    // ── Send payment confirmation email to student ──────────────────────────
    Email::sendPaymentConfirmation(
        $request['email'],
        $request['full_name'],
        $ref,
        (float)$amount,
        'GCash'
    );

    // ── Send status update email (now processing) ───────────────────────────
    Email::sendStatusUpdate(
        $request['email'],
        $request['full_name'],
        $ref,
        'processing'
    );
    // ────────────────────────────────────────────────────────────────────────

    http_response_code(200); echo 'OK';
} catch (Exception $e) {
    $db->rollBack();
    error_log("Xendit webhook DB error: " . $e->getMessage());
    http_response_code(500); echo 'Internal error';
}