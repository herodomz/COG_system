<?php
/**
 * includes/student_layout.php
 *
 * Shared sidebar + page scaffold for every student page.
 *
 * Usage at top of each student page (after auth + DB queries):
 *   $pageTitle       = 'My Requests';        // <title> suffix
 *   $activePage      = 'my_requests';        // matches $navItems key
 *   $extraHeadHtml   = '';                    // optional <link> / <style> tags
 *   include '../includes/student_layout.php';
 *   // … your page HTML …
 *   include '../includes/student_layout_end.php';
 *
 * Variables expected to be set before including:
 *   $user         – array from users table
 *   $unread_count – int
 *   $pageTitle    – string (optional, default 'Dashboard')
 *   $activePage   – string (optional, default 'dashboard')
 */

$pageTitle  = $pageTitle  ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';

$navItems = [
    'dashboard'    => ['href' => 'dashboard.php',    'icon' => 'bi-speedometer2',    'label' => 'Dashboard'],
    'request_cog'  => ['href' => 'request_cog.php',  'icon' => 'bi-file-earmark-text','label' => 'Request COG'],
    'my_requests'  => ['href' => 'my_requests.php',  'icon' => 'bi-list-check',       'label' => 'My Requests'],
    'notifications'=> ['href' => 'notifications.php','icon' => 'bi-bell',             'label' => 'Notifications'],
    'profile'      => ['href' => 'profile.php',      'icon' => 'bi-person',           'label' => 'Profile'],
];

$remaining = Session::getRemainingTime();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> – COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <?= $extraHeadHtml ?? '' ?>
    <style>
        :root { --maroon:#800000; --maroon-dark:#660000; --gradient:linear-gradient(135deg,#800000,#660000); }
        /* ── Layout ── */
        .cog-sidebar {
            min-height:100vh; background:var(--gradient); color:#fff;
            position:fixed; top:0; left:0; width:260px; z-index:1000;
            overflow-y:auto;
        }
        .cog-main { margin-left:260px; padding:30px; background:#f8f9fa; min-height:100vh; }
        /* ── Sidebar links ── */
        .cog-sidebar nav a {
            color:rgba(255,255,255,.82); text-decoration:none;
            padding:11px 18px; display:flex; align-items:center; gap:10px;
            border-radius:9px; margin:3px 10px; font-size:14px; font-weight:500;
            transition:background .2s, transform .2s;
        }
        .cog-sidebar nav a:hover { background:rgba(255,255,255,.16); transform:translateX(4px); color:#fff; }
        .cog-sidebar nav a.active { background:rgba(255,255,255,.22); color:#fff; border-left:4px solid #fff; font-weight:700; }
        /* ── Timeout bar ── */
        .timeout-bar { height:3px; position:fixed; top:0; left:260px; right:0; z-index:1100; transition:width 1s linear, background .5s; }
        /* ── Cards ── */
        .stat-card { background:#fff; border-radius:15px; padding:24px; box-shadow:0 4px 18px rgba(0,0,0,.08); position:relative; overflow:hidden; transition:transform .3s,box-shadow .3s; }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:var(--gradient); }
        .stat-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,.13); }
        .stat-icon { font-size:2.4rem; opacity:.13; position:absolute; right:18px; top:50%; transform:translateY(-50%); }
        /* ── Status badges ── */
        .status-badge { padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block; }
        .status-pending    { background:#fff3cd; color:#856404; }
        .status-processing { background:#cce5ff; color:#004085; }
        .status-ready      { background:#d4edda; color:#155724; }
        .status-released   { background:#d1ecf1; color:#0c5460; }
        /* ── Notif badge in sidebar ── */
        .notif-dot { background:#dc3545; color:#fff; border-radius:50%; padding:2px 6px; font-size:11px; }
        /* ── Buttons ── */
        .btn-maroon { background:var(--gradient); color:#fff; border:none; }
        .btn-maroon:hover { opacity:.88; color:#fff; transform:translateY(-1px); }
        /* ── Card header ── */
        .card-header { background:#fff; border-bottom:2px solid #f0f0f0; }
        .table th { color:#6c757d; font-size:12px; text-transform:uppercase; letter-spacing:.5px; font-weight:600; }
        /* ── Quick-action card ── */
        .info-card { background:#fff; border-radius:12px; padding:22px; border:1px solid #eee; }
        .info-card:hover { border-color:var(--maroon); }
        /* ── Responsive ── */
        @media(max-width:768px) {
            .cog-sidebar { width:100%; position:relative; min-height:auto; }
            .cog-main { margin-left:0; }
            .timeout-bar { left:0; }
        }
    </style>
</head>
<body>
<!-- Session timeout progress bar -->
<div class="timeout-bar" id="timeoutBar"></div>

<!-- Sidebar -->
<div class="cog-sidebar">
    <div class="p-4">
        <h4 class="text-center mb-3 fw-bold">COG System</h4>
        <!-- User card -->
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center
                        rounded-circle bg-white bg-opacity-25 p-3 mb-2"
                 style="width:72px;height:72px;">
                <i class="bi bi-person-circle" style="font-size:2.8rem;color:#fff;"></i>
            </div>
            <h6 class="mt-1 fw-bold mb-0"><?= htmlspecialchars($user['full_name']) ?></h6>
            <small class="text-white-50"><?= htmlspecialchars($user['student_id']) ?></small>
        </div>

        <nav>
            <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= $item['href'] ?>" class="<?= $activePage === $key ? 'active' : '' ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <?= $item['label'] ?>
                <?php if ($key === 'notifications' && ($unread_count ?? 0) > 0): ?>
                    <span class="notif-dot ms-auto"><?= (int)$unread_count ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>

            <hr class="border-white opacity-25 my-3">
            <a href="../logout.php">
                <i class="bi bi-box-arrow-right"></i>Logout
            </a>
        </nav>
    </div>
</div>

<!-- Main content wrapper (page content goes between here and student_layout_end.php) -->
<div class="cog-main">

    <?php /* Flash messages */ ?>
    <?php if ($s = Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($s) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($e = Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($e) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>