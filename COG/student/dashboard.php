<?php
// student/dashboard.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    Session::setFlash('error', 'Please login to access the dashboard.');
    header('Location: ../index.php'); exit();
}

$db      = (new Database())->getConnection();
$user_id = (int) Session::get('user_id');

// User
$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();
if (!$user) { Session::destroy(); header('Location: ../index.php'); exit(); }

// Requests
$stmt = $db->prepare("SELECT * FROM cog_requests WHERE user_id = :uid ORDER BY request_date DESC");
$stmt->execute([':uid' => $user_id]);
$requests = $stmt->fetchAll();

// Unread notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid AND is_read = FALSE ORDER BY created_at DESC");
$stmt->execute([':uid' => $user_id]);
$notifications = $stmt->fetchAll();
$unread_count  = count($notifications);

// Mark single notification read
if (isset($_GET['mark_read']) && ctype_digit($_GET['mark_read'])) {
    $upd = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :uid");
    $upd->execute([':id' => (int)$_GET['mark_read'], ':uid' => $user_id]);
    header('Location: dashboard.php'); exit();
}

// Stats
$pending_count = $ready_count = $released_count = 0;
foreach ($requests as $r) {
    if ($r['status'] === 'pending')   $pending_count++;
    if ($r['status'] === 'ready')     $ready_count++;
    if ($r['status'] === 'released')  $released_count++;
}

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include '../includes/student_layout.php';
?>

<!-- Welcome -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>!</h2>
        <p class="text-muted mb-0">Here's what's happening with your COG requests.</p>
    </div>
    <span class="badge bg-light text-dark p-3 d-none d-md-inline">
        <i class="bi bi-calendar3 me-1"></i><?= date('F d, Y') ?>
    </span>
</div>

<!-- Stat cards -->
<div class="row g-4 mb-4">
    <?php foreach ([
        ['Total Requests',   count($requests),  'bi-files',          'fw-bold'],
        ['Pending',          $pending_count,    'bi-hourglass-split','fw-bold text-warning'],
        ['Ready for Pickup', $ready_count,      'bi-check-circle',   'fw-bold text-success'],
        ['Released',         $released_count,   'bi-check-all',      'fw-bold text-info'],
    ] as [$label, $val, $icon, $cls]): ?>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <h6 class="text-muted mb-2"><?= $label ?></h6>
            <h3 class="<?= $cls ?> mb-0"><?= $val ?></h3>
            <i class="bi <?= $icon ?> stat-icon"></i>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent requests table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold">Recent Requests</h5>
        <a href="request_cog.php" class="btn btn-maroon btn-sm">
            <i class="bi bi-plus-circle me-1"></i>New Request
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Request #</th><th>Date</th><th>Purpose</th>
                        <th>Copies</th><th>Amount</th><th>Status</th><th>Payment</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($requests, 0, 5) as $req): ?>
                    <tr>
                        <td class="fw-bold ps-3"><?= htmlspecialchars($req['request_number']) ?></td>
                        <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth($req['purpose'] ?? '', 0, 30, '…')) ?></td>
                        <td><?= (int)$req['copies'] ?></td>
                        <td>₱<?= number_format($req['amount'], 2) ?></td>
                        <td><span class="status-badge status-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></td>
                        <td>
                            <span class="badge bg-<?= $req['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                <?= ucfirst($req['payment_status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_request.php?id=<?= (int)$req['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($req['payment_status'] === 'unpaid'): ?>
                            <a href="process_payment.php?id=<?= (int)$req['id'] ?>" class="btn btn-sm btn-success">
                                <i class="bi bi-credit-card"></i> Pay
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-2">No requests yet</p>
                            <a href="request_cog.php" class="btn btn-maroon btn-sm">Create First Request</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Info + Notifications -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="info-card h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Request Information</h5>
            <div class="list-group list-group-flush">
                <?php foreach ([
                    ['bi-clock',      'Processing Time:', '2–3 working days'],
                    ['bi-cash',       'Fee:',             '₱50.00 per copy'],
                    ['bi-credit-card','Payment:',         'Online (GCash/Card) or Cash'],
                    ['bi-card-text',  'Requirements:',    'Valid ID + School ID'],
                ] as [$ico, $lbl, $val]): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                    <span><i class="bi <?= $ico ?> me-2 text-primary"></i><?= $lbl ?></span>
                    <span class="fw-semibold"><?= $val ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="info-card h-100">
            <h5 class="fw-bold mb-3">
                <i class="bi bi-bell text-primary me-2"></i>Recent Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $unread_count ?> new</span>
                <?php endif; ?>
            </h5>
            <?php if (!empty($notifications)): ?>
                <?php foreach (array_slice($notifications, 0, 3) as $n): ?>
                <div class="alert alert-info position-relative py-2 px-3 small mb-2">
                    <?= htmlspecialchars($n['message']) ?>
                    <small class="d-block text-muted mt-1">
                        <i class="bi bi-clock me-1"></i><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>
                    </small>
                    <?php if (!$n['is_read']): ?>
                        <a href="?mark_read=<?= (int)$n['id'] ?>" class="stretched-link" title="Mark read"></a>
                        <span class="position-absolute top-0 end-0 p-1">
                            <span class="badge bg-danger" style="font-size:9px;">New</span>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($notifications) > 3): ?>
                    <a href="notifications.php" class="btn btn-sm btn-outline-secondary w-100 mt-1">View All</a>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-3">
                    <i class="bi bi-bell-slash display-5 text-muted d-block mb-2"></i>
                    <p class="text-muted mb-0 small">No new notifications</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/student_layout_end.php'; ?>