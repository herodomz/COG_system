<?php
// student/view_request.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    Session::setFlash('error', 'Please login.'); header('Location: ../index.php'); exit();
}
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: my_requests.php'); exit();
}

$db         = (new Database())->getConnection();
$user_id    = (int) Session::get('user_id');
$request_id = (int) $_GET['id'];

$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();
if (!$user) { Session::destroy(); header('Location: ../index.php'); exit(); }

$nq = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND is_read=FALSE");
$nq->execute([':uid' => $user_id]);
$unread_count = (int)$nq->fetchColumn();

// Get request — must belong to this student
$rStmt = $db->prepare(
    "SELECT r.*, u.full_name, u.student_id AS sid, u.email, u.course, u.year_level
       FROM cog_requests r JOIN users u ON r.user_id = u.id
      WHERE r.id = :rid AND r.user_id = :uid"
);
$rStmt->execute([':rid' => $request_id, ':uid' => $user_id]);
$request = $rStmt->fetch();

if (!$request) {
    Session::setFlash('error', 'Request not found.');
    header('Location: my_requests.php'); exit();
}

// Status history
$hStmt = $db->prepare(
    "SELECT * FROM request_status_history WHERE request_id = :rid ORDER BY created_at ASC"
);
$hStmt->execute([':rid' => $request_id]);
$history = $hStmt->fetchAll();

$pageTitle  = 'Request Details';
$activePage = 'my_requests';
include '../includes/student_layout.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold">Request Details</h2>
    <a href="my_requests.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Requests
    </a>
</div>

<div class="row g-4">
    <!-- LEFT: Details -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Request #<?= htmlspecialchars($request['request_number']) ?></h4>
                        <small class="text-muted">Submitted <?= date('F d, Y \a\t h:i A', strtotime($request['request_date'])) ?></small>
                    </div>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <span class="status-badge status-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span>
                        <span class="badge bg-<?= $request['payment_status'] === 'paid' ? 'success' : 'warning' ?> p-2">
                            <?= ucfirst($request['payment_status']) ?>
                        </span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3 text-muted text-uppercase" style="font-size:12px;letter-spacing:1px;">Student Info</h6>
                        <table class="table table-borderless table-sm">
                            <tr><td class="text-muted" style="width:40%">Name</td><td><?= htmlspecialchars($request['full_name']) ?></td></tr>
                            <tr><td class="text-muted">Student ID</td><td><?= htmlspecialchars($request['sid']) ?></td></tr>
                            <tr><td class="text-muted">Email</td><td><?= htmlspecialchars($request['email']) ?></td></tr>
                            <tr><td class="text-muted">Course</td><td><?= htmlspecialchars($request['course'] ?? 'N/A') ?></td></tr>
                            <tr><td class="text-muted">Year</td><td><?= (int)$request['year_level'] ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3 text-muted text-uppercase" style="font-size:12px;letter-spacing:1px;">Request Details</h6>
                        <table class="table table-borderless table-sm">
                            <tr><td class="text-muted" style="width:40%">Purpose</td><td><?= htmlspecialchars($request['purpose']) ?></td></tr>
                            <tr><td class="text-muted">Copies</td><td><?= (int)$request['copies'] ?></td></tr>
                            <tr><td class="text-muted">Amount</td><td class="fw-bold text-success">₱<?= number_format($request['amount'], 2) ?></td></tr>
                            <tr>
                                <td class="text-muted">Payment</td>
                                <td>
                                    <span class="badge bg-<?= $request['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($request['payment_status']) ?>
                                    </span>
                                    <?php if ($request['payment_date']): ?>
                                        <br><small class="text-muted"><?= date('M d, Y', strtotime($request['payment_date'])) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if (!empty($request['admin_notes'])): ?>
                <?php $clean_notes = preg_replace('/\[hitpay_id:[^\]]*\]|\[paid_via_hitpay:[^\]]*\]/', '', $request['admin_notes']); ?>
                <?php if (trim($clean_notes)): ?>
                <div class="alert alert-light border mt-3 mb-0 rounded-3">
                    <small class="text-muted"><strong>Admin Notes:</strong><br><?= nl2br(htmlspecialchars($clean_notes)) ?></small>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Actions -->
                <div class="mt-4 pt-3 border-top d-flex gap-2 flex-wrap">
                    <?php if ($request['payment_status'] === 'unpaid'): ?>
                    <a href="process_payment.php?id=<?= $request_id ?>"
                       class="btn btn-success">
                        <i class="bi bi-credit-card me-2"></i>Pay Online (GCash)
                    </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="bi bi-printer me-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Timeline -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-clock-history me-2 text-primary"></i>Status Timeline</h5>
                <div style="padding-left:20px;">
                    <!-- Submitted -->
                    <div class="position-relative pb-4" style="border-left:2px solid #dee2e6; padding-left:16px;">
                        <span class="position-absolute bg-success rounded-circle d-block"
                              style="width:12px;height:12px;left:-7px;top:2px;"></span>
                        <div class="fw-semibold">Request Submitted</div>
                        <small class="text-muted"><?= date('M d, Y h:i A', strtotime($request['request_date'])) ?></small>
                    </div>

                    <?php if (!empty($history)): ?>
                        <?php foreach ($history as $h): ?>
                        <div class="position-relative pb-4" style="border-left:2px solid #dee2e6; padding-left:16px;">
                            <span class="position-absolute bg-primary rounded-circle d-block"
                                  style="width:12px;height:12px;left:-7px;top:2px;"></span>
                            <div class="fw-semibold">
                                <?= ucfirst($h['old_status']) ?> →
                                <span class="text-success"><?= ucfirst($h['new_status']) ?></span>
                            </div>
                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($h['created_at'])) ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php if (in_array($request['status'], ['processing','ready','released'])): ?>
                        <div class="position-relative pb-4" style="border-left:2px solid #dee2e6; padding-left:16px;">
                            <span class="position-absolute bg-info rounded-circle d-block"
                                  style="width:12px;height:12px;left:-7px;top:2px;"></span>
                            <div class="fw-semibold">Processing Started</div>
                        </div>
                        <?php endif; ?>
                        <?php if (in_array($request['status'], ['ready','released'])): ?>
                        <div class="position-relative pb-4" style="border-left:2px solid #dee2e6; padding-left:16px;">
                            <span class="position-absolute bg-success rounded-circle d-block"
                                  style="width:12px;height:12px;left:-7px;top:2px;"></span>
                            <div class="fw-semibold text-success">Ready for Pickup</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($request['status'] === 'released'): ?>
                        <div class="position-relative" style="padding-left:16px;">
                            <span class="position-absolute bg-secondary rounded-circle d-block"
                                  style="width:12px;height:12px;left:-7px;top:2px;"></span>
                            <div class="fw-semibold text-secondary">Released</div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/student_layout_end.php'; ?>