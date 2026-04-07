<?php
// student/profile.php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/Email.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    Session::setFlash('error', 'Please login.'); header('Location: ../index.php'); exit();
}

$db      = (new Database())->getConnection();
$user_id = (int) Session::get('user_id');

$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();
if (!$user) { Session::destroy(); header('Location: ../index.php'); exit(); }

$nq = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND is_read=FALSE");
$nq->execute([':uid' => $user_id]);
$unread_count = (int)$nq->fetchColumn();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } elseif (isset($_POST['update_profile'])) {
        $full_name  = trim($_POST['full_name']  ?? '');
        $email      = trim($_POST['email']      ?? '');
        $course     = trim($_POST['course']     ?? '');
        $year_level = (int)($_POST['year_level'] ?? 0);

        if (strlen($full_name) < 2)           $error = 'Full name is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email format.';
        elseif ($year_level < 1 || $year_level > 5) $error = 'Invalid year level.';
        else {
            $chk = $db->prepare("SELECT id FROM users WHERE email=:e AND id!=:id");
            $chk->execute([':e' => $email, ':id' => $user_id]);
            if ($chk->rowCount() > 0) {
                $error = 'Email already used by another account.';
            } else {
                $db->prepare(
                    "UPDATE users SET full_name=:n, email=:e, course=:c, year_level=:y WHERE id=:id"
                )->execute([':n' => $full_name, ':e' => $email, ':c' => $course, ':y' => $year_level, ':id' => $user_id]);
                Session::set('user_name', $full_name);
                $success = 'Profile updated successfully!';
                $stmt->execute([':id' => $user_id]);
                $user = $stmt->fetch();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password']     ?? '';
        $con = $_POST['confirm_password'] ?? '';

        if (!password_verify($cur, $user['password']))        $error = 'Current password is incorrect.';
        elseif (strlen($new) < 8)                             $error = 'Password must be at least 8 characters.';
        elseif (!preg_match('/[A-Z]/', $new))                 $error = 'Password must contain an uppercase letter.';
        elseif (!preg_match('/[a-z]/', $new))                 $error = 'Password must contain a lowercase letter.';
        elseif (!preg_match('/[0-9]/', $new))                 $error = 'Password must contain a number.';
        elseif ($new !== $con)                                $error = 'New passwords do not match.';
        else {
            $db->prepare("UPDATE users SET password=:p WHERE id=:id")
               ->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user_id]);

            // ── Send password changed security alert ────────────────────────
            Email::sendPasswordChanged($user['email'], $user['full_name']);
            // ────────────────────────────────────────────────────────────────

            $success = 'Password changed successfully! A security alert has been sent to your email.';
        }
    }
}

$pageTitle  = 'Profile';
$activePage = 'profile';
include '../includes/student_layout.php';
?>

<h2 class="fw-bold mb-4">My Profile</h2>

<!-- Avatar card -->
<div class="card border-0 shadow-sm rounded-4 mb-4 text-center py-4">
    <div class="d-flex align-items-center justify-content-center rounded-circle mx-auto mb-3 text-white fw-bold"
         style="width:80px;height:80px;background:linear-gradient(135deg,#800000,#660000);font-size:2rem;">
        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
    </div>
    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h4>
    <p class="text-muted mb-0"><?= htmlspecialchars($user['student_id']) ?></p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success rounded-3"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger rounded-3"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabProfile">
            <i class="bi bi-person me-1"></i>Profile
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPassword">
            <i class="bi bi-key me-1"></i>Password
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Profile tab -->
    <div class="tab-pane fade show active" id="tabProfile">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= Session::generateCSRFToken() ?>">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Student ID</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['student_id']) ?>" disabled>
                            <div class="form-text">Student ID cannot be changed.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" class="form-control" name="full_name"
                                   value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" name="email"
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Course</label>
                            <select class="form-select" name="course" required>
                                <option value="">Select…</option>
                                <?php foreach (['BSIT'=>'BS Information Technology','BSCS'=>'BS Computer Science',
                                                'BSED'=>'BS Secondary Education','BEED'=>'BS Elementary Education',
                                                'BSBA'=>'BS Business Administration','BSHRM'=>'BS Hospitality Management'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $user['course'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Year Level</label>
                            <select class="form-select" name="year_level" required>
                                <option value="">Select…</option>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <option value="<?= $i ?>" <?= $user['year_level'] == $i ? 'selected' : '' ?>><?= $i ?>st Year</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-maroon">
                                <i class="bi bi-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password tab -->
    <div class="tab-pane fade" id="tabPassword">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <div class="alert alert-light border-start border-4 border-warning mb-3" style="font-size:13px;">
                    <i class="bi bi-shield-check me-1 text-warning"></i>
                    A security alert email will be sent to <strong><?= htmlspecialchars($user['email']) ?></strong> when you change your password.
                </div>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= Session::generateCSRFToken() ?>">
                    <input type="hidden" name="change_password" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="col-12"></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" class="form-control" name="new_password" id="newPw"
                                   required minlength="8" autocomplete="new-password">
                            <div class="form-text">Min 8 chars with uppercase, lowercase, and a number.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" id="conPw"
                                   required autocomplete="new-password">
                        </div>
                        <!-- Strength bar -->
                        <div class="col-12">
                            <div class="progress" style="height:5px">
                                <div class="progress-bar" id="strengthBar" style="width:0"></div>
                            </div>
                            <small class="text-muted" id="strengthText"></small>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-maroon">
                                <i class="bi bi-key me-1"></i>Change Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('newPw').addEventListener('input', function () {
        const pw = this.value;
        let score = 0;
        if (pw.length >= 8)          score++;
        if (/[A-Z]/.test(pw))        score++;
        if (/[a-z]/.test(pw))        score++;
        if (/[0-9]/.test(pw))        score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;

        const bar  = document.getElementById('strengthBar');
        const text = document.getElementById('strengthText');
        const pct  = (score / 5) * 100;
        bar.style.width = pct + '%';
        const levels = ['', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success', 'bg-success'];
        const labels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
        bar.className  = 'progress-bar ' + (levels[score] || '');
        text.textContent = labels[score] || '';
    });
</script>

<?php include '../includes/student_layout_end.php'; ?>