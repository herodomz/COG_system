<?php
// admin/dashboard.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'admin') {
    Session::setFlash('error', 'Please login as admin to access the dashboard.');
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$total_requests      = $db->query("SELECT COUNT(*) FROM cog_requests")->fetchColumn();
$pending_requests    = $db->query("SELECT COUNT(*) FROM cog_requests WHERE status = 'pending'")->fetchColumn();
$processing_requests = $db->query("SELECT COUNT(*) FROM cog_requests WHERE status = 'processing'")->fetchColumn();
$ready_requests      = $db->query("SELECT COUNT(*) FROM cog_requests WHERE status = 'ready'")->fetchColumn();
$released_requests   = $db->query("SELECT COUNT(*) FROM cog_requests WHERE status = 'released'")->fetchColumn();
$total_students      = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$today_requests      = $db->query("SELECT COUNT(*) FROM cog_requests WHERE DATE(request_date) = CURDATE()")->fetchColumn();
$pending_payments    = $db->query("SELECT COUNT(*) FROM cog_requests WHERE payment_status = 'unpaid'")->fetchColumn();

$requests = $db->query(
    "SELECT r.*, u.full_name, u.student_id, u.email
       FROM cog_requests r
       JOIN users u ON r.user_id = u.id
      ORDER BY r.request_date DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root { --primary: linear-gradient(135deg,#800000,#660000); }
        .sidebar { min-height:100vh; background:var(--primary); color:#fff; position:fixed; width:260px; top:0; left:0; overflow-y:auto; }
        .sidebar a { color:rgba(255,255,255,.9); text-decoration:none; padding:12px 20px; display:block; transition:all .3s; }
        .sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,.2); color:#fff; padding-left:30px; }
        .sidebar a.active { border-left:4px solid #fff; }
        .main-content { margin-left:260px; padding:30px; background:#f8f9fa; min-height:100vh; }
        .stat-card { background:#fff; border-radius:15px; padding:25px; box-shadow:0 5px 20px rgba(0,0,0,.08); transition:transform .3s; position:relative; overflow:hidden; }
        .stat-card:hover { transform:translateY(-5px); }
        .stat-card::after { content:''; position:absolute; top:0; right:0; width:80px; height:80px; background:linear-gradient(135deg,rgba(128,0,0,.1),rgba(102,0,0,.1)); border-radius:50%; transform:translate(20px,-20px); }
        .stat-icon { font-size:2.5rem; color:maroon; opacity:.2; position:absolute; right:20px; bottom:20px; }
        .status-badge { padding:6px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-pending    { background:#fff3cd; color:#856404; }
        .status-processing { background:#cce5ff; color:#004085; }
        .status-ready      { background:#d4edda; color:#155724; }
        .status-released   { background:#d1ecf1; color:#0c5460; }
        .timeout-bar { height:3px; background:#800000; position:fixed; top:0; left:260px; right:0; z-index:9999; transition:width 1s linear; }
    </style>
</head>
<body>

<div class="timeout-bar" id="timeoutBar" style="width:100%"></div>

<!-- ── Sidebar ── -->
<div class="sidebar">
    <div class="p-3">
        <h4 class="text-center mb-4">Admin Panel</h4>
        <nav>
            <a href="dashboard.php" class="active"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="requests.php"><i class="bi bi-list-check me-2"></i>All Requests</a>
            <a href="students.php"><i class="bi bi-people me-2"></i>Students</a>
            <a href="reports.php"><i class="bi bi-graph-up me-2"></i>Reports</a>
            <a href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
            <hr class="bg-white opacity-25">
            <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </nav>
    </div>
</div>

<!-- ── Main Content ── -->
<div class="main-content">

    <?php $s = Session::getFlash('success'); if ($s): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($s) ?>
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
        <h2 class="fw-bold">Admin Dashboard</h2>
        <span class="badge bg-light text-dark p-3">
            <i class="bi bi-calendar3 me-2"></i><?= date('F d, Y') ?>
        </span>
    </div>

    <!-- Stat Cards -->
    <div class="row mb-4 g-4">
        <?php $cards = [
            ['Total Requests',   $total_requests,      'bi-files',          'fw-bold'],
            ['Pending',          $pending_requests,    'bi-hourglass-split', 'fw-bold text-warning'],
            ['Processing',       $processing_requests, 'bi-gear',            'fw-bold text-info'],
            ['Ready',            $ready_requests,      'bi-check-circle',    'fw-bold text-success'],
            ['Released',         $released_requests,   'bi-check-all',       'fw-bold text-secondary'],
            ['Total Students',   $total_students,      'bi-people',          'fw-bold'],
            ["Today's Requests", $today_requests,      'bi-calendar-day',    'fw-bold'],
            ['Pending Payments', $pending_payments,    'bi-cash',            'fw-bold text-warning'],
        ]; ?>
        <?php foreach ($cards as [$label, $val, $icon, $cls]): ?>
        <div class="col-md-3">
            <div class="stat-card">
                <h6 class="text-muted mb-2"><?= $label ?></h6>
                <h3 class="<?= $cls ?> mb-0"><?= $val ?></h3>
                <i class="bi <?= $icon ?> stat-icon"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Requests Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">Recent Requests</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Request #</th><th>Student</th><th>Student ID</th>
                            <th>Date</th><th>Copies</th><th>Amount</th>
                            <th>Status</th><th>Payment</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($r['request_number']) ?></td>
                            <td><?= htmlspecialchars($r['full_name']) ?></td>
                            <td><?= htmlspecialchars($r['student_id']) ?></td>
                            <td><?= date('M d, Y', strtotime($r['request_date'])) ?></td>
                            <td><?= (int)$r['copies'] ?></td>
                            <td>₱<?= number_format($r['amount'], 2) ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($r['status']) ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $r['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($r['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_request.php?id=<?= (int)$r['id'] ?>"
                                   class="btn btn-sm" style="background:maroon;color:#fff;border:none;">
                                    <i class="bi bi-eye"></i> Manage
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
                                <h6 class="text-muted">No requests found</h6>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mt-2 g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-graph-up text-primary me-2"></i>Quick Stats</h6>
                    <?php
                    $week  = $db->query("SELECT COUNT(*) FROM cog_requests WHERE WEEK(request_date)  = WEEK(CURDATE())")->fetchColumn();
                    $month = $db->query("SELECT COUNT(*) FROM cog_requests WHERE MONTH(request_date) = MONTH(CURDATE())")->fetchColumn();
                    $year  = $db->query("SELECT COUNT(*) FROM cog_requests WHERE YEAR(request_date)  = YEAR(CURDATE())")->fetchColumn();
                    ?>
                    <div class="d-flex justify-content-between mb-2"><span>This Week:</span><span class="fw-bold"><?= $week ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>This Month:</span><span class="fw-bold"><?= $month ?></span></div>
                    <div class="d-flex justify-content-between"><span>This Year:</span><span class="fw-bold"><?= $year ?></span></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-cash-stack text-success me-2"></i>Revenue Overview</h6>
                    <?php
                    $revenue         = $db->query("SELECT COALESCE(SUM(amount),0) FROM cog_requests WHERE payment_status='paid'")->fetchColumn();
                    $pending_revenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM cog_requests WHERE payment_status='unpaid'")->fetchColumn();
                    $online_revenue  = $db->query("SELECT COALESCE(SUM(amount),0) FROM cog_requests WHERE payment_status='paid' AND admin_notes LIKE '%hitpay%'")->fetchColumn();
                    ?>
                    <div class="d-flex justify-content-between mb-2"><span>Total Collected:</span><span class="fw-bold">₱<?= number_format($revenue,2) ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Online (HitPay):</span><span class="fw-bold text-success">₱<?= number_format($online_revenue,2) ?></span></div>
                    <div class="d-flex justify-content-between"><span>Pending:</span><span class="fw-bold text-warning">₱<?= number_format($pending_revenue,2) ?></span></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history text-info me-2"></i>Processing Times</h6>
                    <div class="d-flex justify-content-between mb-2"><span>Avg (days):</span><span class="fw-bold">2.5 days</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>On-time:</span><span class="fw-bold text-success">95%</span></div>
                    <div class="d-flex justify-content-between"><span>Session Timeout:</span><span class="fw-bold">30 min</span></div>
                </div>
            </div>
        </div>
    </div>

<?php include '../includes/admin_layout_end.php'; ?>