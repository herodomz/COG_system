<?php
// student/dashboard.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'student') {
    Session::setFlash('error', 'Please login to access the dashboard.');
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::get('user_id');

// Get user info with prepared statement
$user_query = "SELECT * FROM users WHERE id = :id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    Session::destroy();
    header("Location: ../index.php");
    exit();
}

// Get user's COG requests
$requests_query = "SELECT * FROM cog_requests WHERE user_id = :user_id ORDER BY request_date DESC";
$requests_stmt = $db->prepare($requests_query);
$requests_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$requests_stmt->execute();
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread notifications
$notif_query = "SELECT * FROM notifications WHERE user_id = :user_id AND is_read = FALSE ORDER BY created_at DESC";
$notif_stmt = $db->prepare($notif_query);
$notif_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$notif_stmt->execute();
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$unread_count = count($notifications);

// Mark notifications as read when viewed
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $mark_read = (int)$_GET['mark_read'];
    $update_query = "UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id', $mark_read, PDO::PARAM_INT);
    $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $update_stmt->execute();
    header("Location: dashboard.php");
    exit();
}

// Calculate statistics
$pending_count = 0;
$ready_count = 0;
$released_count = 0;

foreach ($requests as $request) {
    switch ($request['status']) {
        case 'pending':
            $pending_count++;
            break;
        case 'ready':
            $ready_count++;
            break;
        case 'released':
            $released_count++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #800000 0%, #660000 100%);
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--primary-gradient);
            color: white;
            position: fixed;
            width: 260px;
            transition: all 0.3s;
        }
        
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: all 0.3s;
            border-radius: 8px;
            margin: 4px 10px;
        }
        
        .sidebar a:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar a.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-left: 4px solid white;
            font-weight: 600;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: #667eea;
            opacity: 0.2;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        .text-primary, .btn-primary, .badge.bg-primary {
            color: maroon !important;
            background-color: white !important;
            border-color: maroon !important;
        }

        .quick-action-card:hover {
            border-color: maroon;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-released { background: #d1ecf1; color: #0c5460; }
        
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 3px 7px;
            font-size: 11px;
            margin-left: 5px;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 20px 25px;
        }
        
        .table th {
            border-top: none;
            color: #6c757d;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .quick-action-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            height: 100%;
            transition: all 0.3s;
            border: 1px solid #eee;
        }
        
        .quick-action-card:hover {
            border-color: maroon;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center mb-4 fw-bold">COG System</h4>
            <div class="text-center mb-4">
                <div class="bg-white bg-opacity-20 rounded-circle d-inline-block p-3 mb-2">
                    <i class="bi bi-person-circle" style="font-size: 3rem; color: white;"></i>
                </div>
                <h6 class="mt-2 fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                <small class="text-white-50"><?php echo htmlspecialchars($user['student_id']); ?></small>
            </div>
            
            <nav>
                <a href="dashboard.php" class="active">
                    <i class="bi bi-speedometer2 me-3"></i>Dashboard
                </a>
                <a href="request_cog.php">
                    <i class="bi bi-file-earmark-text me-3"></i>Request COG
                </a>
                <a href="my_requests.php">
                    <i class="bi bi-list-check me-3"></i>My Requests
                </a>
                <a href="notifications.php">
                    <i class="bi bi-bell me-3"></i>Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="profile.php">
                    <i class="bi bi-person me-3"></i>Profile
                </a>
                <hr class="bg-white opacity-25 my-3">
                <a href="../logout.php">
                    <i class="bi bi-box-arrow-right me-3"></i>Logout
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Flash Messages -->
        <?php $success = Session::getFlash('success'); ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php $error = Session::getFlash('error'); ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Welcome Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>!</h2>
                <p class="text-muted">Here's what's happening with your COG requests.</p>
            </div>
            <div class="d-none d-md-block">
                <span class="badge bg-light text-dark p-3">
                    <i class="bi bi-calendar3 me-2"></i><?php echo date('F d, Y'); ?>
                </span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4 g-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Total Requests</h6>
                    <h3 class="fw-bold mb-0"><?php echo count($requests); ?></h3>
                    <i class="bi bi-files stat-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Pending</h6>
                    <h3 class="fw-bold mb-0 text-warning"><?php echo $pending_count; ?></h3>
                    <i class="bi bi-hourglass-split stat-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Ready for Pickup</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $ready_count; ?></h3>
                    <i class="bi bi-check-circle stat-icon"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Released</h6>
                    <h3 class="fw-bold mb-0 text-info"><?php echo $released_count; ?></h3>
                    <i class="bi bi-check-all stat-icon"></i>
                </div>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Requests</h5>
                <a href="request_cog.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>New Request
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Date</th>
                                <th>Purpose</th>
                                <th>Copies</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($requests, 0, 5) as $request): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo htmlspecialchars($request['request_number']); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($request['purpose'], 0, 30) . '...'); ?></td>
                                <td><?php echo (int)$request['copies']; ?></td>
                                <td>₱<?php echo number_format($request['amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($request['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $request['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['payment_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_request.php?id=<?php echo (int)$request['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
                                    <h6 class="text-muted">No requests yet</h6>
                                    <a href="request_cog.php" class="btn btn-primary btn-sm mt-2">
                                        Create Your First Request
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions and Notifications -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="quick-action-card">
                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-info-circle text-primary me-2"></i>
                        Request Information
                    </h5>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-clock me-2 text-primary"></i>Processing Time:</span>
                            <span class="fw-bold">2-3 working days</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-cash me-2 text-primary"></i>Payment:</span>
                            <span class="fw-bold">₱50.00 per copy</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-building me-2 text-primary"></i>Mode of Payment:</span>
                            <span class="fw-bold">Cash (Registrar's Office)</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-card-text me-2 text-primary"></i>Requirements:</span>
                            <span class="fw-bold">Valid ID, School ID</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="quick-action-card">
                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-bell text-primary me-2"></i>
                        Recent Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $unread_count; ?> new</span>
                        <?php endif; ?>
                    </h5>
                    
                    <?php if (!empty($notifications)): ?>
                        <?php foreach (array_slice($notifications, 0, 3) as $notif): ?>
                            <div class="alert alert-info alert-dismissible fade show position-relative" role="alert">
                                <div class="pe-4">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                    <small class="d-block text-muted mt-1">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                                    </small>
                                </div>
                                <?php if (!$notif['is_read']): ?>
                                    <a href="?mark_read=<?php echo (int)$notif['id']; ?>" 
                                       class="stretched-link" 
                                       title="Mark as read"></a>
                                    <span class="position-absolute top-0 end-0 p-2">
                                        <span class="badge bg-danger rounded-pill">New</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($notifications) > 3): ?>
                            <div class="text-center mt-3">
                                <a href="notifications.php" class="btn btn-link">View All Notifications</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-bell-slash display-4 text-muted d-block mb-3"></i>
                            <p class="text-muted mb-0">No new notifications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>