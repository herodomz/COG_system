<?php
// admin/view_student.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'admin') {
    Session::setFlash('error', 'Please login as admin.');
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    Session::setFlash('error', 'Invalid student ID.');
    header("Location: students.php");
    exit();
}

$database   = new Database();
$db         = $database->getConnection();
$student_id = (int)$_GET['id'];

$s = $db->prepare("SELECT * FROM users WHERE id = :id");
$s->execute([':id' => $student_id]);
$student = $s->fetch();

if (!$student) {
    Session::setFlash('error', 'Student not found.');
    header("Location: students.php");
    exit();
}

// Fetch all COG requests for this student
$r = $db->prepare(
    "SELECT * FROM cog_requests WHERE user_id = :uid ORDER BY request_date DESC"
);
$r->execute([':uid' => $student_id]);
$requests = $r->fetchAll();

// Stats
$total   = count($requests);
$paid    = array_sum(array_map(fn($x) => $x['payment_status'] === 'paid' ? 1 : 0, $requests));
$revenue = array_sum(array_map(fn($x) => $x['payment_status'] === 'paid' ? $x['amount'] : 0, $requests));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile – COG System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar { min-height:100vh; background:linear-gradient(135deg,#800000,#660000); color:#fff; position:fixed; width:260px; }
        .sidebar a { color:rgba(255,255,255,.8); text-decoration:none; padding:12px 20px; display:block; transition:all .3s; }
        .sidebar a:hover,.sidebar a.active { background:rgba(255,255,255,.2); color:#fff; padding-left:30px; }
        .sidebar a.active { border-left:4px solid #fff; }
        .main-content { margin-left:260px; padding:30px; background:#f8f9fa; min-height:100vh; }
        .profile-card { background:#fff; border-radius:15px; padding:30px; box-shadow:0 5px 20px rgba(0,0,0,.08); margin-bottom:24px; }
        .avatar { width:80px; height:80px; background:linear-gradient(135deg,#800000,#660000);
                  border-radius:50%; display:flex; align-items:center; justify-content:center;
                  color:#fff; font-size:32px; font-weight:700; flex-shrink:0; }
        .stat-box { background:#f8f9fa; border-radius:10px; padding:16px; text-align:center; }
        .stat-box .num { font-size:26px; font-weight:700; }
        .status-badge { padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-pending    { background:#fff3cd; color:#856404; }
        .status-processing { background:#cce5ff; color:#004085; }
        .status-ready      { background:#d4edda; color:#155724; }
        .status-released   { background:#d1ecf1; color:#0c5460; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="p-3">
        <h4 class="text-center mb-4">Admin Panel</h4>
        <nav>
            <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="requests.php"><i class="bi bi-list-check me-2"></i>All Requests</a>
            <a href="students.php" class="active"><i class="bi bi-people me-2"></i>Students</a>
            <a href="reports.php"><i class="bi bi-graph-up me-2"></i>Reports</a>
            <a href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
            <hr class="bg-white opacity-25">
            <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </nav>
    </div>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Student Profile</h2>
        <a href="students.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back
        </a>
    </div>

    <!-- Profile header -->
    <div class="profile-card">
        <div class="d-flex align-items-center gap-4">
            <div class="avatar"><?= strtoupper(substr($student['full_name'], 0, 1)) ?></div>
            <div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($student['full_name']) ?></h4>
                <p class="text-muted mb-1"><?= htmlspecialchars($student['student_id']) ?> &bull; <?= htmlspecialchars($student['email']) ?></p>
                <p class="mb-0 text-muted"><?= htmlspecialchars($student['course'] ?? 'N/A') ?> – Year <?= (int)$student['year_level'] ?> &bull;
                    Registered <?= date('M d, Y', strtotime($student['created_at'])) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-box"><div class="num"><?= $total ?></div><div class="text-muted">Total Requests</div></div>
        </div>
        <div class="col-md-3">
            <div class="stat-box"><div class="num text-success"><?= $paid ?></div><div class="text-muted">Paid</div></div>
        </div>
        <div class="col-md-3">
            <div class="stat-box"><div class="num text-warning"><?= $total - $paid ?></div><div class="text-muted">Unpaid</div></div>
        </div>
        <div class="col-md-3">
            <div class="stat-box"><div class="num text-primary">₱<?= number_format($revenue, 0) ?></div><div class="text-muted">Revenue</div></div>
        </div>
    </div>

    <!-- Requests table -->
    <div class="profile-card">
        <h5 class="fw-bold mb-3">COG Requests</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Request #</th><th>Date</th><th>Purpose</th><th>Copies</th>
                        <th>Amount</th><th>Status</th><th>Payment</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($req['request_number']) ?></td>
                        <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth($req['purpose'], 0, 30, '…')) ?></td>
                        <td><?= (int)$req['copies'] ?></td>
                        <td>₱<?= number_format($req['amount'], 2) ?></td>
                        <td><span class="status-badge status-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></td>
                        <td><span class="badge bg-<?= $req['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= ucfirst($req['payment_status']) ?></span></td>
                        <td><a href="view_request.php?id=<?= (int)$req['id'] ?>" class="btn btn-sm btn-outline-primary">Manage</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($requests)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No requests yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php include '../includes/admin_layout_end.php'; ?>