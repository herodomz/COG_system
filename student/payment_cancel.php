<?php
// student/payment_cancel.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    header('Location: ../index.php'); exit();
}

$ref     = trim($_GET['ref'] ?? '');
$user_id = (int) Session::get('user_id');
$request = null;

if ($ref) {
    $db   = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT * FROM cog_requests WHERE request_number = :ref AND user_id = :uid");
    $stmt->execute([':ref' => $ref, ':uid' => $user_id]);
    $request = $stmt->fetch();
}

$return_id = $request['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled – COG System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }

        .cancel-wrapper { width: 100%; max-width: 480px; }

        .cancel-card {
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

        .cancel-banner {
            background: linear-gradient(135deg, #6c757d, #495057);
            padding: 40px 30px 30px;
            text-align: center;
            color: #fff;
        }

        .x-circle {
            width: 90px;
            height: 90px;
            background: rgba(255,255,255,0.2);
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

        .cancel-banner h2 { font-weight: 700; font-size: 26px; margin-bottom: 6px; }
        .cancel-banner p  { opacity: 0.9; font-size: 14px; }

        .cancel-body { padding: 28px 30px 32px; }

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

        .notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #856404;
            margin-bottom: 24px;
        }

        .btn-retry {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #800000, #660000);
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

        .btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128,0,0,0.35);
            color: #fff;
        }

        .btn-back {
            display: block;
            width: 100%;
            background: transparent;
            color: #800000;
            border: 2px solid #800000;
            border-radius: 12px;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-back:hover { background: #800000; color: #fff; }

        .countdown-wrap { margin-top: 20px; text-align: center; }
        .countdown-text { font-size: 12px; color: #aaa; margin-bottom: 6px; }
        .countdown-bar-bg { height: 4px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .countdown-bar-fill { height: 100%; background: #6c757d; border-radius: 4px; width: 100%; transition: width 1s linear; }
    </style>
</head>
<body>
<div class="cancel-wrapper">
    <div class="cancel-card">

        <div class="cancel-banner">
            <div class="x-circle">
                <i class="bi bi-x-lg" style="font-size: 2.8rem;"></i>
            </div>
            <h2>Payment Cancelled</h2>
            <p>You cancelled the GCash payment.<br>Your request is still pending.</p>
        </div>

        <div class="cancel-body">

            <?php if ($request): ?>
            <div class="detail-box">
                <div class="detail-row">
                    <span class="label"><i class="bi bi-hash me-1"></i>Reference No.</span>
                    <span class="value"><?= htmlspecialchars($request['request_number']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="bi bi-cash me-1"></i>Amount Due</span>
                    <span class="value">₱<?= number_format($request['amount'], 2) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="bi bi-info-circle me-1"></i>Status</span>
                    <span class="value">
                        <span class="badge bg-warning text-dark">Unpaid</span>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <div class="notice">
                <i class="bi bi-exclamation-triangle me-1"></i>
                No charges were made. You can retry payment anytime from your request page.
            </div>

            <?php if ($return_id): ?>
            <a href="process_payment.php?id=<?= (int)$return_id ?>" class="btn-retry">
                <i class="bi bi-credit-card me-2"></i>Retry GCash Payment
            </a>
            <a href="view_request.php?id=<?= (int)$return_id ?>" class="btn-back">
                <i class="bi bi-arrow-left me-1"></i>Back to Request
            </a>
            <?php else: ?>
            <a href="my_requests.php" class="btn-retry">
                <i class="bi bi-list-check me-2"></i>Go to My Requests
            </a>
            <a href="dashboard.php" class="btn-back">
                <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
            </a>
            <?php endif; ?>

            <?php if ($return_id): ?>
            <div class="countdown-wrap">
                <div class="countdown-text">Returning to your request in <strong id="countdownNum">8</strong>s</div>
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
(function () {
    const target  = 'view_request.php?id=<?= (int)$return_id ?>';
    const total   = 8;
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