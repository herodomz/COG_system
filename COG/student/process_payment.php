<?php
// student/process_payment.php  –  Redirects student to Xendit GCash checkout
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/Xendit.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    Session::setFlash('error', 'Please login.'); header('Location: ../index.php'); exit();
}
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: my_requests.php'); exit();
}

$db         = (new Database())->getConnection();
$user_id    = (int) Session::get('user_id');
$request_id = (int) $_GET['id'];

// Fetch unpaid request belonging to this student
$stmt = $db->prepare(
    "SELECT r.*, u.email, u.full_name
       FROM cog_requests r JOIN users u ON r.user_id = u.id
      WHERE r.id = :rid AND r.user_id = :uid AND r.payment_status = 'unpaid'"
);
$stmt->execute([':rid' => $request_id, ':uid' => $user_id]);
$request = $stmt->fetch();

if (!$request) {
    Session::setFlash('error', 'Request not found or already paid.');
    header('Location: my_requests.php'); exit();
}

$copies      = (int)$request['copies'];
$description = 'COG Request – ' . $request['request_number']
             . ' (' . $copies . ' cop' . ($copies > 1 ? 'ies' : 'y') . ')';

// Append ?ref= to cancel URL so cancel page knows which request to return to
$baseCancel = rtrim(env('XENDIT_CANCEL_URL', ''), '?');
putenv('XENDIT_CANCEL_URL=' . $baseCancel . '?ref=' . urlencode($request['request_number']));
$_ENV['XENDIT_CANCEL_URL'] = $baseCancel . '?ref=' . urlencode($request['request_number']);

$result = Xendit::createGCashPayment(
    (float)$request['amount'],
    $request['request_number'],
    $request['email'],
    $request['full_name'],
    '',
    $description
);

if (isset($result['error'])) {
    Session::setFlash('error', 'Payment gateway error: ' . $result['error']);
    header("Location: view_request.php?id={$request_id}"); exit();
}

// Store Xendit charge ID in admin_notes for reconciliation
$db->prepare(
    "UPDATE cog_requests
        SET admin_notes = CONCAT(IFNULL(admin_notes,''), ' [xendit_charge:', :cid, ']')
      WHERE id = :id"
)->execute([':cid' => $result['charge_id'], ':id' => $request_id]);

// Redirect student to Xendit GCash sandbox checkout
header('Location: ' . $result['checkout_url']);
exit();