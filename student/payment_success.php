<?php
// student/payment_success.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    header('Location: ../index.php'); exit();
}

$db      = (new Database())->getConnection();
$user_id = (int) Session::get('user_id');
$ref     = trim($_GET['ref'] ?? '');
$request = null;

if ($ref) {
    $stmt = $db->prepare("SELECT * FROM cog_requests WHERE request_number = :ref AND user_id = :uid");
    $stmt->execute([':ref' => $ref, ':uid' => $user_id]);
    $request = $stmt->fetch();
}

// The request ID to return to after success
$return_id = $request['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Submitted – COG System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #E5F6FF;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }

        .success-wrapper {
            width: 100%;
            max-width: 520px;
        }

        .success-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Top banner ── */
        .success-banner {
            background: linear-gradient(135deg, #0066CC, #0052A3);
            padding: 40px 30px 30px;
            text-align: center;
            color: #fff;
        }

        .checkmark-circle {
            width: 90px;
            height: 90px;
            background: rgba(0, 200, 81, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            animation: pop 0.4s ease 0.3s both;
        }

        @keyframes pop {
            0%   { transform: scale(0); }
            70%  { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        .success-banner h2 {
            font-weight: 700;
            font-size: 26px;
            margin-bottom: 6px;
        }

        .success-banner p {
            opacity: 0.9;
            font-size: 14px;
        }

        /* ── Body ── */
        .success-body {
            padding: 28px 30px 32px;
        }

        /* ── Status pill ── */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff3cd;
            color: #856404;
            border-radius: 50px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        /* ── Detail rows ── */
        .detail-box {
            background: #f8f9fa;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }

        .detail-row:last-child { border-bottom: none; }

        .detail-row .label { color: #6c757d; }
        .detail-row .value { font-weight: 600; color: #333; }
        .detail-row .value.green { color: #00C851; }

        /* ── Info notice ── */
        .notice {
            background: #E5F6FF;
            border-left: 4px solid #0066CC;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #0052A3;
            margin-bottom: 24px;
        }

        /* ── Buttons ── */
        .btn-view {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #0066CC, #0052A3);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            margin-bottom: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,102,204,0.35);
            color: #fff;
        }

        .btn-dashboard {
            display: block;
            width: 100%;
            background: transparent;
            color: #0066CC;
            border: 2px solid #0066CC;
            border-radius: 12px;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-dashboard:hover {
            background: #0066CC;
            color: #fff;
        }

        /* ── Countdown bar ── */
        .countdown-wrap {
            margin-top: 20px;
            text-align: center;
        }

        .countdown-text {
            font-size: 12px;
            color: #aaa;
            margin-bottom: 6px;
        }

        .countdown-bar-bg {
            height: 4px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .countdown-bar-fill {
            height: 100%;
            background: linear-gradient(135deg, #0066CC, #0052A3);
            border-radius: 4px;
            width: 100%;
            transition: width 1s linear;
        }
    </style>
</head>
<body>
<div class="success-wrapper">
    <div class="success-card">

        <!-- Banner -->
        <div class="success-banner">
            <div class="checkmark-circle">
                <i class="bi bi-check-lg" style="font-size: 2.8rem; color: #00C851;"></i>
            </div>
            <h2>Payment Submitted!</h2>
            <p>Your GCash payment has been received.<br>We'll update your status once verified.</p>
        </div>

        <!-- Body -->
        <div class="success-body">

            <?php if ($request): ?>
            <!-- Payment status pill -->
            <div class="text-center">
                <span class="status-pill">
                    <i class="bi bi-hourglass-split"></i>
                    <?= $request['payment_status'] === 'paid' ? 'Payment Confirmed' : 'Verification Pending' ?>
                </span>
            </div>

            <!-- Details -->
            <div class="detail-box">
                <div class="detail-row">
                    <span class="label"><i class="bi bi-hash me-1"></i>Reference No.</span>
                    <span class="value"><?= htmlspecialchars($request['request_number']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="bi bi-cash me-1"></i>Amount</span>
                    <span class="value green">₱<?= number_format($request['amount'], 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="bi bi-credit-card me-1"></i>Method</span>
                    <span class="value">GCash</span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="bi bi-calendar me-1"></i>Date</span>
                    <span class="value"><?= date('M d, Y h:i A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="bi bi-info-circle me-1"></i>Status</span>
                    <span class="value">
                        <span class="badge bg-<?= $request['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                            <?= ucfirst($request['payment_status']) ?>
                        </span>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notice -->
            <div class="notice">
                <i class="bi bi-info-circle me-1"></i>
                Payment verification may take a few seconds. Your COG will begin processing once confirmed.
            </div>

            <!-- Buttons -->
            <?php if ($return_id): ?>
            <a href="view_request.php?id=<?= (int)$return_id ?>" class="btn-view">
                <i class="bi bi-eye me-2"></i>View My Request
            </a>
            <?php else: ?>
            <a href="my_requests.php" class="btn-view">
                <i class="bi bi-list-check me-2"></i>View My Requests
            </a>
            <?php endif; ?>

            <a href="dashboard.php" class="btn-dashboard">
                <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
            </a>

            <!-- Auto-redirect countdown -->
            <?php if ($return_id): ?>
            <div class="countdown-wrap">
                <div class="countdown-text" id="countdownText">Redirecting to your request in <strong id="countdownNum">10</strong>s</div>
                <div class="countdown-bar-bg">
                    <div class="countdown-bar-fill" id="countdownBar"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
<?php if ($return_id): ?>
// Auto-redirect countdown
(function () {
    const target  = 'view_request.php?id=<?= (int)$return_id ?>';
    const total   = 20;
    let remaining = total;

    const numEl = document.getElementById('countdownNum');
    const barEl = document.getElementById('countdownBar');

    const iv = setInterval(function () {
        remaining--;
        if (numEl) numEl.textContent = remaining;
        if (barEl) barEl.style.width = ((remaining / total) * 100) + '%';
        if (remaining <= 0) {
            clearInterval(iv);
            window.location.href = target;
        }
    }, 1000);
})();
<?php endif; ?>
</script>
</body>
</html>