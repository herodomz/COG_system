<?php
// admin/requests.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'admin') {
    Session::setFlash('error', 'Please login as admin.');
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle status update
if (isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $request_id = (int)$_POST['request_id'];
    $new_status = $_POST['status'];
    $payment_status = $_POST['payment_status'];

    $db->beginTransaction();

    try {
        // Update request
        $update_query = "UPDATE cog_requests SET status = :status, payment_status = :payment_status WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':payment_status', $payment_status);
        $update_stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
        $update_stmt->execute();

        // Get user_id for notification
        $user_query = "SELECT user_id FROM cog_requests WHERE id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        // Create notification
        $notif_query = "INSERT INTO notifications (user_id, request_id, message) 
                        VALUES (:user_id, :request_id, :message)";
        $notif_stmt = $db->prepare($notif_query);
        $message = "Your request status has been updated to: " . ucfirst($new_status);
        $notif_stmt->bindParam(':user_id', $user['user_id'], PDO::PARAM_INT);
        $notif_stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
        $notif_stmt->bindParam(':message', $message);
        $notif_stmt->execute();

        $db->commit();
        Session::setFlash('success', 'Request updated successfully!');
    } catch (Exception $e) {
        $db->rollBack();
        Session::setFlash('error', 'Failed to update request.');
    }

    header("Location: requests.php");
    exit();
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !Session::verifyCSRFToken($_GET['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $request_id = (int)$_GET['delete'];
    
    $delete_query = "DELETE FROM cog_requests WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $request_id, PDO::PARAM_INT);
    
    if ($delete_stmt->execute()) {
        Session::setFlash('success', 'Request deleted successfully!');
    } else {
        Session::setFlash('error', 'Failed to delete request.');
    }
    
    header("Location: requests.php");
    exit();
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];

// Status filter
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "r.status = :status";
    $params[':status'] = $_GET['status'];
}

// Payment filter
if (isset($_GET['payment']) && !empty($_GET['payment'])) {
    $where_conditions[] = "r.payment_status = :payment";
    $params[':payment'] = $_GET['payment'];
}

// Search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $where_conditions[] = "(r.request_number LIKE :search OR u.full_name LIKE :search OR u.student_id LIKE :search)";
    $params[':search'] = $search;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) FROM cog_requests r 
                JOIN users u ON r.user_id = u.id 
                $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_requests = $count_stmt->fetchColumn();
$total_pages = ceil($total_requests / $limit);

// Get requests
$query = "SELECT r.*, u.full_name, u.student_id, u.email 
          FROM cog_requests r 
          JOIN users u ON r.user_id = u.id 
          $where_clause 
          ORDER BY r.request_date DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            position: fixed;
            width: 260px;
        }
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.2);
            color: white;
            padding-left: 30px;
        }
        .sidebar a.active {
            border-left: 4px solid white;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-released { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3">
            <h4 class="text-center mb-4">Admin Panel</h4>
            <nav>
                <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="requests.php" class="active"><i class="bi bi-list-check me-2"></i>All Requests</a>
                <a href="students.php"><i class="bi bi-people me-2"></i>Students</a>
                <a href="reports.php"><i class="bi bi-graph-up me-2"></i>Reports</a>
                <a href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
                <hr class="bg-white opacity-25">
                <a href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Flash Messages -->
        <?php $success = Session::getFlash('success'); ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php $error = Session::getFlash('error'); ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Manage Requests</h2>
            <span class="badge bg-primary p-3">Total: <?php echo $total_requests; ?></span>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo (isset($_GET['status']) && $_GET['status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                        <option value="ready" <?php echo (isset($_GET['status']) && $_GET['status'] == 'ready') ? 'selected' : ''; ?>>Ready</option>
                        <option value="released" <?php echo (isset($_GET['status']) && $_GET['status'] == 'released') ? 'selected' : ''; ?>>Released</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Payment</label>
                    <select name="payment" class="form-select">
                        <option value="">All Payments</option>
                        <option value="unpaid" <?php echo (isset($_GET['payment']) && $_GET['payment'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="paid" <?php echo (isset($_GET['payment']) && $_GET['payment'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Request #, Student name, or ID" 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Date</th>
                                <th>Copies</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo htmlspecialchars($request['request_number']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['student_id']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
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
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i> Manage
                                    </a>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="confirmDelete(<?php echo $request['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
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

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    <?php include '../includes/admin_layout_end.php'; ?>