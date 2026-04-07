<?php
// student/my_requests.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    Session::setFlash('error', 'Please login.'); header('Location: ../index.php'); exit();
}

$db      = (new Database())->getConnection();
$user_id = (int) Session::get('user_id');

$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();
if (!$user) { Session::destroy(); header('Location: ../index.php'); exit(); }

$nq = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = FALSE");
$nq->execute([':uid' => $user_id]);
$unread_count = (int)$nq->fetchColumn();

// ── Filters ──────────────────────────────────────────────
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where  = ["user_id = :user_id"];
$params = [':user_id' => $user_id];

if (!empty($_GET['search'])) {
    $where[]           = "(request_number LIKE :search OR purpose LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}
if (!empty($_GET['status'])) {
    $where[]           = "status = :status";
    $params[':status'] = $_GET['status'];
}
if (!empty($_GET['payment'])) {
    $where[]            = "payment_status = :payment";
    $params[':payment'] = $_GET['payment'];
}
if (!empty($_GET['date_from'])) {
    $where[]              = "DATE(request_date) >= :date_from";
    $params[':date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where[]            = "DATE(request_date) <= :date_to";
    $params[':date_to'] = $_GET['date_to'];
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// total
$cntStmt = $db->prepare("SELECT COUNT(*) FROM cog_requests $whereSQL");
foreach ($params as $k => $v) $cntStmt->bindValue($k, $v);
$cntStmt->execute();
$total_requests = (int)$cntStmt->fetchColumn();
$total_pages    = max(1, (int)ceil($total_requests / $limit));

// rows
$rowStmt = $db->prepare("SELECT * FROM cog_requests $whereSQL ORDER BY request_date DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) $rowStmt->bindValue($k, $v);
$rowStmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$rowStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$rowStmt->execute();
$requests = $rowStmt->fetchAll();

// stats
$sStmt = $db->prepare(
    "SELECT
       COUNT(*) as total,
       SUM(status='pending')        as pending,
       SUM(status='processing')     as processing,
       SUM(status='ready')          as ready,
       SUM(status='released')       as released,
       SUM(payment_status='paid')   as paid
     FROM cog_requests WHERE user_id = :uid"
);
$sStmt->execute([':uid' => $user_id]);
$stats = $sStmt->fetch();

$pageTitle  = 'My Requests';
$activePage = 'my_requests';
include '../includes/student_layout.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold">My Requests</h2>
        <p class="text-muted mb-0">Track all your COG requests</p>
    </div>
    <a href="request_cog.php" class="btn btn-maroon">
        <i class="bi bi-plus-circle me-1"></i>New Request
    </a>
</div>

<!-- Mini stats -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total',      $stats['total'],      'linear-gradient(135deg,#800000,#660000)'],
        ['Pending',    $stats['pending'],    'linear-gradient(135deg,#ffc107,#fd7e14)'],
        ['Processing', $stats['processing'], 'linear-gradient(135deg,#17a2b8,#6610f2)'],
        ['Ready',      $stats['ready'],      'linear-gradient(135deg,#28a745,#20c997)'],
        ['Released',   $stats['released'],   'linear-gradient(135deg,#6c757d,#343a40)'],
        ['Paid',       $stats['paid'],       'linear-gradient(135deg,#1e7e34,#28a745)'],
    ] as [$lbl, $val, $bg]): ?>
    <div class="col-6 col-md-2">
        <div class="rounded-3 p-3 text-white text-center" style="background:<?= $bg ?>">
            <div class="fw-bold fs-4"><?= (int)$val ?></div>
            <div style="font-size:12px;opacity:.9"><?= $lbl ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <form method="GET" id="filterForm" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search"
                       placeholder="Request # or purpose…"
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <?php foreach (['pending','processing','ready','released'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>>
                        <?= ucfirst($s) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment" class="form-select">
                    <option value="">All Payments</option>
                    <option value="unpaid" <?= ($_GET['payment'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="paid"   <?= ($_GET['payment'] ?? '') === 'paid'   ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from"
                       value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to"
                       value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-maroon">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="my_requests.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Request #</th><th>Date</th><th>Purpose</th>
                        <th>Copies</th><th>Amount</th><th>Status</th><th>Payment</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td class="fw-bold ps-3"><?= htmlspecialchars($req['request_number']) ?></td>
                        <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth($req['purpose'] ?? '', 0, 40, '…')) ?></td>
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
                                <i class="bi bi-eye"></i> View
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
                            <i class="bi bi-inbox display-3 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No requests found</p>
                            <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
                                <a href="my_requests.php" class="btn btn-sm btn-outline-secondary mt-2">Clear Filters</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white border-0 py-3">
        <nav>
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">«</a>
                </li>
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">»</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
    // Auto-submit on filter change
    document.querySelectorAll('#filterForm select').forEach(s =>
        s.addEventListener('change', () => document.getElementById('filterForm').submit())
    );
</script>

<?php include '../includes/student_layout_end.php'; ?>