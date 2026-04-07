<?php
// student/notifications.php
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

// Mark single notification read
if (isset($_GET['mark_read']) && ctype_digit($_GET['mark_read'])) {
    $db->prepare("UPDATE notifications SET is_read=TRUE WHERE id=:id AND user_id=:uid")
       ->execute([':id' => (int)$_GET['mark_read'], ':uid' => $user_id]);
    header('Location: notifications.php'); exit();
}

// Mark all read
if (isset($_GET['mark_all_read'])) {
    $db->prepare("UPDATE notifications SET is_read=TRUE WHERE user_id=:uid")
       ->execute([':uid' => $user_id]);
    Session::setFlash('success', 'All notifications marked as read!');
    header('Location: notifications.php'); exit();
}

// Delete single
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $db->prepare("DELETE FROM notifications WHERE id=:id AND user_id=:uid")
       ->execute([':id' => (int)$_GET['delete'], ':uid' => $user_id]);
    Session::setFlash('success', 'Notification deleted.');
    header('Location: notifications.php'); exit();
}

$nq = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND is_read=FALSE");
$nq->execute([':uid' => $user_id]);
$unread_count = (int)$nq->fetchColumn();

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$total = (int)$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:uid")
                  ->execute([':uid' => $user_id]) ?: 0;
$cStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:uid");
$cStmt->execute([':uid' => $user_id]);
$total       = (int)$cStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $limit));

$nStmt = $db->prepare(
    "SELECT n.*, r.request_number FROM notifications n
       LEFT JOIN cog_requests r ON n.request_id = r.id
      WHERE n.user_id = :uid
      ORDER BY n.created_at DESC LIMIT :lim OFFSET :off"
);
$nStmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
$nStmt->bindValue(':lim', $limit,   PDO::PARAM_INT);
$nStmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$nStmt->execute();
$notifications = $nStmt->fetchAll();

// Group by relative date
$grouped = [];
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
foreach ($notifications as $n) {
    $d = date('Y-m-d', strtotime($n['created_at']));
    $grouped[$d === $today ? 'Today' : ($d === $yesterday ? 'Yesterday' : 'Earlier')][] = $n;
}

$pageTitle  = 'Notifications';
$activePage = 'notifications';
include '../includes/student_layout.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold">Notifications</h2>
        <p class="text-muted mb-0">
            <?= $unread_count > 0 ? "<strong>{$unread_count}</strong> unread" : 'All caught up!' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($total > 0): ?>
        <a href="?mark_all_read=1" class="btn btn-outline-primary btn-sm"
           onclick="return confirm('Mark all as read?')">
            <i class="bi bi-check-all me-1"></i>Mark All Read
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($notifications)): ?>
<div class="text-center py-5 card border-0 shadow-sm rounded-4">
    <i class="bi bi-bell-slash display-3 text-muted d-block mb-3"></i>
    <h5 class="text-muted">No Notifications</h5>
    <p class="text-muted small">When there are updates to your requests, they'll appear here.</p>
</div>
<?php else: ?>
    <?php foreach ($grouped as $group => $items): ?>
    <div class="mb-4">
        <p class="text-muted fw-semibold mb-2" style="font-size:12px;text-transform:uppercase;letter-spacing:1px;">
            <?= $group ?> <span class="badge bg-light text-dark"><?= count($items) ?></span>
        </p>

        <?php foreach ($items as $n): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-2 <?= !$n['is_read'] ? 'border-start border-3 border-danger' : '' ?>"
             style="<?= !$n['is_read'] ? 'background:#fff9f9;' : '' ?>">
            <div class="card-body py-3 px-4 d-flex align-items-start gap-3">
                <div class="flex-shrink-0 mt-1">
                    <i class="bi <?= $n['is_read'] ? 'bi-envelope-open text-muted' : 'bi-envelope-fill text-danger' ?> fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <p class="mb-1"><?= htmlspecialchars($n['message']) ?></p>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i>
                            <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>
                        </small>
                        <?php if ($n['request_number']): ?>
                        <small class="badge bg-light text-dark border">
                            <?= htmlspecialchars($n['request_number']) ?>
                        </small>
                        <?php endif; ?>
                        <?php if (!$n['is_read']): ?>
                        <span class="badge bg-danger">New</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-shrink-0 d-flex gap-1">
                    <?php if (!$n['is_read']): ?>
                    <a href="?mark_read=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-success" title="Mark read">
                        <i class="bi bi-check-lg"></i>
                    </a>
                    <?php endif; ?>
                    <a href="?delete=<?= (int)$n['id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       title="Delete"
                       onclick="return confirm('Delete this notification?')">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>">«</a>
            </li>
            <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>">»</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>

<?php include '../includes/student_layout_end.php'; ?>