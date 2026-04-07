<?php
// student/request_cog.php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/Email.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    Session::setFlash('error', 'Please login to request COG.');
    header('Location: ../index.php'); exit();
}

$db      = (new Database())->getConnection();
$user_id = (int) Session::get('user_id');

// Fetch user for layout
$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();
if (!$user) { Session::destroy(); header('Location: ../index.php'); exit(); }

$nq = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = FALSE");
$nq->execute([':uid' => $user_id]);
$unread_count = (int)$nq->fetchColumn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $purpose       = trim($_POST['purpose'] ?? '');
        $other_purpose = trim($_POST['other_purpose'] ?? '');
        $copies        = (int)($_POST['copies'] ?? 1);

        if (empty($purpose)) {
            $error = 'Please select a purpose.';
        } elseif ($purpose === 'Other' && empty($other_purpose)) {
            $error = 'Please specify the purpose.';
        } elseif ($copies < 1 || $copies > 10) {
            $error = 'Number of copies must be between 1 and 10.';
        } else {
            $final_purpose = ($purpose === 'Other') ? $other_purpose : $purpose;
            $amount        = $copies * 50.00;

            // Generate unique request number with collision check
            do {
                $request_number = 'COG-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                $chk = $db->prepare("SELECT id FROM cog_requests WHERE request_number = :rn");
                $chk->execute([':rn' => $request_number]);
            } while ($chk->rowCount() > 0);

            $db->beginTransaction();
            try {
                $ins = $db->prepare(
                    "INSERT INTO cog_requests (user_id, request_number, purpose, copies, amount)
                     VALUES (:uid, :rn, :purpose, :copies, :amount)"
                );
                $ins->execute([
                    ':uid'    => $user_id,
                    ':rn'     => $request_number,
                    ':purpose'=> $final_purpose,
                    ':copies' => $copies,
                    ':amount' => $amount,
                ]);
                $request_id = (int)$db->lastInsertId();

                $msg = "Your COG request (Ref: {$request_number}) has been submitted. "
                     . "Please proceed to the Registrar's Office to pay or use the online payment option.";
                $notif = $db->prepare(
                    "INSERT INTO notifications (user_id, request_id, message) VALUES (:uid, :rid, :msg)"
                );
                $notif->execute([':uid' => $user_id, ':rid' => $request_id, ':msg' => $msg]);

                $db->commit();

                // ── Send confirmation email to student ──────────────────────
                Email::sendRequestConfirmation(
                    $user['email'],
                    $user['full_name'],
                    $request_number,
                    $final_purpose,
                    $copies,
                    $amount
                );

                // ── Notify admin(s) of new request ──────────────────────────
                try {
                    $db2 = (new Database())->getConnection();
                    $adminStmt = $db2->query("SELECT email, full_name FROM admins LIMIT 5");
                    while ($admin = $adminStmt->fetch()) {
                        Email::sendAdminNewRequest(
                            $admin['email'],
                            $admin['full_name'],
                            $user['full_name'],
                            $user['student_id'],
                            $request_number,
                            $final_purpose,
                            $copies,
                            $amount
                        );
                    }
                } catch (Exception $e) {
                    error_log("Admin notification email error: " . $e->getMessage());
                }
                // ────────────────────────────────────────────────────────────

                Session::setFlash('success', "Request submitted! Reference: {$request_number}. A confirmation email has been sent.");
                header('Location: my_requests.php'); exit();
            } catch (Exception $e) {
                $db->rollBack();
                error_log("COG request error: " . $e->getMessage());
                $error = 'Failed to submit request. Please try again.';
            }
        }
    }
}

$pageTitle  = 'Request COG';
$activePage = 'request_cog';
include '../includes/student_layout.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold">Request Certificate of Grades</h2>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-3"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" id="cogForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= Session::generateCSRFToken() ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Purpose of Request <span class="text-danger">*</span></label>
                        <select class="form-select" name="purpose" id="purposeSel" required>
                            <option value="">Select Purpose…</option>
                            <?php foreach (['Employment','School Transfer','Graduation Requirements','Scholarship Application','Board Exam','Other'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($_POST['purpose'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="otherDiv" style="display:none;">
                        <label class="form-label fw-semibold">Please specify <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="other_purpose"
                               id="otherInput" placeholder="Enter purpose"
                               value="<?= htmlspecialchars($_POST['other_purpose'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Number of Copies <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="copies" id="copiesInput"
                               min="1" max="10" value="<?= (int)($_POST['copies'] ?? 1) ?>" required>
                        <div class="form-text">Maximum 10 copies. ₱50.00 per copy.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Total Amount</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">₱</span>
                            <input type="text" class="form-control bg-light fw-bold" id="amountDisplay"
                                   value="50.00" readonly>
                        </div>
                    </div>

                    <div class="alert alert-light border-start border-4 border-danger rounded-3 mb-4">
                        <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1 text-danger"></i>Important Reminders</h6>
                        <ul class="mb-0 small">
                            <li>Processing time: 2–3 working days</li>
                            <li>Pay online (GCash/Card) or at the Registrar's Office</li>
                            <li>Bring valid ID + school ID when claiming</li>
                            <li>A confirmation email will be sent to your registered email</li>
                        </ul>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-maroon btn-lg">
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const purposeSel  = document.getElementById('purposeSel');
    const otherDiv    = document.getElementById('otherDiv');
    const otherInput  = document.getElementById('otherInput');
    const copiesInput = document.getElementById('copiesInput');
    const amountDisp  = document.getElementById('amountDisplay');

    function toggleOther() {
        const show = purposeSel.value === 'Other';
        otherDiv.style.display  = show ? 'block' : 'none';
        otherInput.required     = show;
        if (!show) otherInput.value = '';
    }
    function updateAmount() {
        const n = Math.min(Math.max(parseInt(copiesInput.value) || 1, 1), 10);
        amountDisp.value = (n * 50).toFixed(2);
    }

    purposeSel.addEventListener('change', toggleOther);
    copiesInput.addEventListener('input', updateAmount);
    toggleOther();
    updateAmount();
</script>

<?php include '../includes/student_layout_end.php'; ?>