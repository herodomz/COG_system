<?php
// admin/view_request.php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/Email.php';

if (!Session::isLoggedIn() || Session::get('role') != 'admin') {
    Session::setFlash('error', 'Please login as admin.');
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    Session::setFlash('error', 'Invalid request ID.');
    header("Location: requests.php");
    exit();
}

$database   = new Database();
$db         = $database->getConnection();
$request_id = (int)$_GET['id'];

// Handle status / payment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF token');

    $new_status     = $_POST['status'];
    $payment_status = $_POST['payment_status'];
    $admin_notes    = trim($_POST['admin_notes']);

    $db->beginTransaction();
    try {
        // Get old status + student info for notification & email
        $old = $db->prepare(
            "SELECT r.status, r.user_id, r.payment_status AS old_payment,
                    u.email, u.full_name, r.request_number, r.amount
               FROM cog_requests r JOIN users u ON r.user_id = u.id
              WHERE r.id = :id"
        );
        $old->execute([':id' => $request_id]);
        $old_row = $old->fetch();

        // Update request
        $upd = $db->prepare(
            "UPDATE cog_requests
                SET status = :status,
                    payment_status = :payment_status,
                    admin_notes    = :notes,
                    payment_date   = CASE WHEN :ps2 = 'paid' AND payment_date IS NULL THEN NOW() ELSE payment_date END
              WHERE id = :id"
        );
        $upd->execute([
            ':status'         => $new_status,
            ':payment_status' => $payment_status,
            ':ps2'            => $payment_status,
            ':notes'          => $admin_notes,
            ':id'             => $request_id,
        ]);

        // Status history & student notification
        if ($old_row['status'] !== $new_status) {
            $hist = $db->prepare(
                "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by)
                 VALUES (:rid, :old, :new, :by)"
            );
            $hist->execute([
                ':rid' => $request_id,
                ':old' => $old_row['status'],
                ':new' => $new_status,
                ':by'  => Session::get('admin_id'),
            ]);

            $labels = [
                'processing' => 'is now being processed',
                'ready'      => 'is ready for pickup at the Registrar\'s Office',
                'released'   => 'has been released. Thank you!',
                'pending'    => 'has been moved back to pending',
            ];
            $label = $labels[$new_status] ?? "status changed to $new_status";
            $notif = $db->prepare(
                "INSERT INTO notifications (user_id, request_id, message)
                 VALUES (:uid, :rid, :msg)"
            );
            $notif->execute([
                ':uid' => $old_row['user_id'],
                ':rid' => $request_id,
                ':msg' => "Your COG request {$label}.",
            ]);

            // ── Send status update email to student ─────────────────────────
            Email::sendStatusUpdate(
                $old_row['email'],
                $old_row['full_name'],
                $old_row['request_number'],
                $new_status,
                $admin_notes
            );
            // ────────────────────────────────────────────────────────────────
        }

        // ── Send payment confirmation email if admin marks as paid ──────────
        if ($payment_status === 'paid' && $old_row['old_payment'] !== 'paid') {
            Email::sendPaymentConfirmation(
                $old_row['email'],
                $old_row['full_name'],
                $old_row['request_number'],
                (float)$old_row['amount'],
                'Cash (Registrar\'s Office)'
            );
        }
        // ────────────────────────────────────────────────────────────────────

        $db->commit();
        Session::setFlash('success', 'Request updated successfully! Student has been notified by email.');
    } catch (Exception $e) {
        $db->rollBack();
        error_log($e->getMessage());
        Session::setFlash('error', 'Failed to update request.');
    }
    header("Location: view_request.php?id=$request_id");
    exit();
}

// Fetch request details
$q    = $db->prepare(
    "SELECT r.*, u.full_name, u.student_id, u.email, u.course, u.year_level
       FROM cog_requests r
       JOIN users u ON r.user_id = u.id
      WHERE r.id = :id"
);
$q->execute([':id' => $request_id]);
$request = $q->fetch();

if (!$request) {
    Session::setFlash('error', 'Request not found.');
    header("Location: requests.php");
    exit();
}

// Fetch status history
$hist = $db->prepare(
    "SELECT h.*, a.full_name AS changed_by_name
       FROM request_status_history h
       LEFT JOIN admins a ON h.changed_by = a.id
      WHERE h.request_id = :rid
      ORDER BY h.created_at DESC"
);
$hist->execute([':rid' => $request_id]);
$history = $hist->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Request – COG System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar { min-height:100vh; background:linear-gradient(135deg,#800000,#660000); color:#fff; position:fixed; width:260px; }
        .sidebar a { color:rgba(255,255,255,.8); text-decoration:none; padding:12px 20px; display:block; transition:all .3s; }
        .sidebar a:hover,.sidebar a.active { background:rgba(255,255,255,.2); color:#fff; padding-left:30px; }
        .sidebar a.active { border-left:4px solid #fff; }
        .main-content { margin-left:260px; padding:30px; background:#f8f9fa; min-height:100vh; }
        .detail-card { background:#fff; border-radius:15px; padding:30px; box-shadow:0 5px 20px rgba(0,0,0,.08); margin-bottom:24px; }
        .status-badge { padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-pending    { background:#fff3cd; color:#856404; }
        .status-processing { background:#cce5ff; color:#004085; }
        .status-ready      { background:#d4edda; color:#155724; }
        .status-released   { background:#d1ecf1; color:#0c5460; }
        .info-label { font-size:11px; color:#6c757d; text-transform:uppercase; letter-spacing:.5px; }
        .info-value { font-size:15px; font-weight:500; }
        .timeline { padding-left:28px; }
        .timeline-item { position:relative; padding-bottom:20px; }
        .timeline-item::before { content:''; position:absolute; left:-20px; top:5px; width:10px; height:10px;
                                  border-radius:50%; background:#800000; }
        .timeline-item::after  { content:''; position:absolute; left:-16px; top:15px; width:2px;
                                  height:calc(100% - 10px); background:#e9ecef; }
        .timeline-item:last-child::after { display:none; }
        .btn-maroon { background:linear-gradient(135deg,#800000,#660000); color:#fff; border:none; }
        .btn-maroon:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(128,0,0,.4); color:#fff; }
        .email-notice { background:#e8f4fd; border-left:4px solid #0d6efd; border-radius:8px; padding:10px 14px; font-size:12px; color:#084298; margin-top:8px; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="p-3">
        <h4 class="text-center mb-4">Admin Panel</h4>
        <nav>
            <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="requests.php" class="active"><i class="bi bi-list-check me-2"></i>All Requests</a>
            <a href="students.php"><i class="bi bi-people me-2"></i>Students</a>
            <a href="reports.php"><i class="bi bi-graph-up me-2"></i>Reports</a>
            <a href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
            <hr class="bg-white opacity-25">
            <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </nav>
    </div>
</div>

<div class="main-content">
    <?php $s = Session::getFlash('success'); if ($s): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($s) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php $e = Session::getFlash('error'); if ($e): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($e) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Manage Request</h2>
        <a href="requests.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Requests
        </a>
    </div>

    <div class="row">
        <!-- LEFT: Details + Update Form -->
        <div class="col-lg-8">
            <!-- Request header -->
            <div class="detail-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="fw-bold mb-1">Request #<?= htmlspecialchars($request['request_number']) ?></h4>
                        <small class="text-muted">
                            Submitted <?= date('F d, Y \a\t h:i A', strtotime($request['request_date'])) ?>
                        </small>
                    </div>
                    <div>
                        <span class="status-badge status-<?= $request['status'] ?> me-2">
                            <?= ucfirst($request['status']) ?>
                        </span>
                        <span class="badge bg-<?= $request['payment_status'] === 'paid' ? 'success' : 'warning' ?> p-2">
                            <?= ucfirst($request['payment_status']) ?>
                        </span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3"><i class="bi bi-person me-2 text-primary"></i>Student Info</h6>
                        <table class="table table-borderless mb-0">
                            <tr><td class="info-label">Name</td><td class="info-value"><?= htmlspecialchars($request['full_name']) ?></td></tr>
                            <tr><td class="info-label">Student ID</td><td class="info-value"><?= htmlspecialchars($request['student_id']) ?></td></tr>
                            <tr><td class="info-label">Email</td><td class="info-value"><?= htmlspecialchars($request['email']) ?></td></tr>
                            <tr><td class="info-label">Course</td><td class="info-value"><?= htmlspecialchars($request['course'] ?? 'N/A') ?></td></tr>
                            <tr><td class="info-label">Year</td><td class="info-value"><?= (int)$request['year_level'] ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3"><i class="bi bi-file-text me-2 text-primary"></i>Request Details</h6>
                        <table class="table table-borderless mb-0">
                            <tr><td class="info-label">Purpose</td><td class="info-value"><?= htmlspecialchars($request['purpose']) ?></td></tr>
                            <tr><td class="info-label">Copies</td><td class="info-value"><?= (int)$request['copies'] ?></td></tr>
                            <tr><td class="info-label">Amount</td><td class="info-value text-success fw-bold">₱<?= number_format($request['amount'], 2) ?></td></tr>
                            <tr>
                                <td class="info-label">Payment</td>
                                <td class="info-value">
                                    <span class="badge bg-<?= $request['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($request['payment_status']) ?>
                                    </span>
                                    <?php if ($request['payment_date']): ?>
                                        <small class="text-muted d-block"><?= date('M d, Y', strtotime($request['payment_date'])) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if ($request['admin_notes']): ?>
                <div class="alert alert-light border mt-3 mb-0">
                    <small class="text-muted"><strong>Admin Notes:</strong><br>
                    <?= nl2br(htmlspecialchars(preg_replace('/\[xendit_\w+:[^\]]*\]/', '', $request['admin_notes']))) ?></small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Update Form -->
            <div class="detail-card">
                <h5 class="fw-bold mb-4"><i class="bi bi-pencil me-2 text-primary"></i>Update Request</h5>
                <div class="email-notice mb-3">
                    <i class="bi bi-envelope me-1"></i>
                    <strong>Email notifications are automatic.</strong> The student will be emailed whenever you change the status or mark payment as paid.
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= Session::generateCSRFToken() ?>">
                    <input type="hidden" name="update_request" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Request Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['pending','processing','ready','released'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $request['status'] === $st ? 'selected' : '' ?>>
                                        <?= ucfirst($st) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Payment Status</label>
                            <select name="payment_status" class="form-select">
                                <option value="unpaid" <?= $request['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                <option value="paid"   <?= $request['payment_status'] === 'paid'   ? 'selected' : '' ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Admin Notes <small class="text-muted">(included in student email)</small></label>
                            <textarea name="admin_notes" class="form-control" rows="3"
                                placeholder="Add notes about this request…"><?= htmlspecialchars(
                                    preg_replace('/\[xendit_\w+:[^\]]*\]/', '', $request['admin_notes'] ?? '')
                                ) ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-maroon px-4">
                                <i class="bi bi-save me-2"></i>Save Changes &amp; Notify Student
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- RIGHT: Timeline -->
        <div class="col-lg-4">
            <div class="detail-card">
                <h5 class="fw-bold mb-4"><i class="bi bi-clock-history me-2 text-primary"></i>Status History</h5>
                <?php if (!empty($history)): ?>
                    <div class="timeline">
                        <?php foreach ($history as $h): ?>
                            <div class="timeline-item">
                                <div class="fw-bold">
                                    <?= ucfirst($h['old_status']) ?> →
                                    <span class="text-success"><?= ucfirst($h['new_status']) ?></span>
                                </div>
                                <small class="text-muted">
                                    <?= date('M d, Y h:i A', strtotime($h['created_at'])) ?><br>
                                    <?php if ($h['changed_by_name']): ?>
                                        By: <?= htmlspecialchars($h['changed_by_name']) ?>
                                    <?php else: ?>
                                        System (webhook)
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="fw-bold">Request Created</div>
                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($request['request_date'])) ?></small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="detail-card">
                <h5 class="fw-bold mb-3"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h5>
                <div class="d-grid gap-2">
                    <a href="mailto:<?= htmlspecialchars($request['email']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-envelope me-2"></i>Email Student Directly
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-2"></i>Print Request
                    </button>
                    <a href="view_student.php?id=<?= (int)$request['user_id'] ?>" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-person me-2"></i>View Student Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php include '../includes/admin_layout_end.php'; ?>