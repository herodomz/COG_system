<?php
// admin/students.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'admin') {
    Session::setFlash('error', 'Please login as admin.');
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if users table exists
try {
    $db->query("SELECT 1 FROM users LIMIT 1");
} catch (PDOException $e) {
    // Create users table if it doesn't exist
    $create_users = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(20) UNIQUE NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        course VARCHAR(50),
        year_level INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($create_users);
}

// Handle student deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !Session::verifyCSRFToken($_GET['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $student_id = (int)$_GET['delete'];
    
    try {
        $db->beginTransaction();
        
        // Delete related notifications first (if table exists)
        try {
            $delete_notif = "DELETE FROM notifications WHERE user_id = :id";
            $notif_stmt = $db->prepare($delete_notif);
            $notif_stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
            $notif_stmt->execute();
        } catch (PDOException $e) {
            // Notifications table might not exist, continue
        }
        
        // Delete related requests
        $delete_requests = "DELETE FROM cog_requests WHERE user_id = :id";
        $req_stmt = $db->prepare($delete_requests);
        $req_stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
        $req_stmt->execute();
        
        // Delete student
        $delete_query = "DELETE FROM users WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
        $delete_stmt->execute();
        
        $db->commit();
        Session::setFlash('success', 'Student deleted successfully!');
    } catch (Exception $e) {
        $db->rollBack();
        Session::setFlash('error', 'Failed to delete student: ' . $e->getMessage());
    }
    
    header("Location: students.php");
    exit();
}

// Pagination and search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Changed to 12 for better grid layout
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%$search%";

// Get total count with search
if (!empty($search)) {
    $count_query = "SELECT COUNT(*) FROM users WHERE full_name LIKE :search OR student_id LIKE :search OR email LIKE :search";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->bindParam(':search', $search_param);
} else {
    $count_query = "SELECT COUNT(*) FROM users";
    $count_stmt = $db->prepare($count_query);
}
$count_stmt->execute();
$total_students = $count_stmt->fetchColumn();
$total_pages = ceil($total_students / $limit);

// Get students with request counts
if (!empty($search)) {
    $query = "SELECT u.*, 
              COALESCE((SELECT COUNT(*) FROM cog_requests WHERE user_id = u.id), 0) as total_requests,
              COALESCE((SELECT COUNT(*) FROM cog_requests WHERE user_id = u.id AND status = 'pending'), 0) as pending_requests
              FROM users u 
              WHERE u.full_name LIKE :search OR u.student_id LIKE :search OR u.email LIKE :search
              ORDER BY u.created_at DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':search', $search_param);
} else {
    $query = "SELECT u.*, 
              COALESCE((SELECT COUNT(*) FROM cog_requests WHERE user_id = u.id), 0) as total_requests,
              COALESCE((SELECT COUNT(*) FROM cog_requests WHERE user_id = u.id AND status = 'pending'), 0) as pending_requests
              FROM users u 
              ORDER BY u.created_at DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }

        /* Sidebar Styles */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            position: fixed;
            width: 260px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar .p-3 {
            padding: 20px 15px !important;
        }

        .sidebar h4 {
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: all 0.3s;
            border-radius: 8px;
            margin: 4px 10px;
            font-weight: 500;
        }

        .sidebar a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }

        /* Header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            font-weight: 700;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h2 i {
            color: #800000;
            font-size: 32px;
        }

        .total-badge {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 5px 15px rgba(128,0,0,0.3);
        }

        /* Search Card */
        .search-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: none;
        }

        .search-card .form-control {
            height: 45px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 10px 15px;
            font-size: 15px;
        }

        .search-card .form-control:focus {
            border-color: #800000;
            box-shadow: 0 0 0 0.2rem rgba(128,0,0,0.1);
        }

        .search-card .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border: none;
            height: 45px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .search-card .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,0,0.4);
        }

        /* Student Card */
        .student-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            border: 1px solid #f0f0f0;
            position: relative;
            overflow: hidden;
        }

        .student-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
        }

        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            border-color: transparent;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-right: 15px;
            box-shadow: 0 5px 15px rgba(128,0,0,0.3);
        }

        .student-info h6 {
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .student-info small {
            color: #6c757d;
            display: block;
            font-size: 12px;
        }

        .student-details {
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
            color: #495057;
        }

        .detail-item i {
            width: 20px;
            color: #800000;
            margin-right: 8px;
        }

        .request-badges {
            display: flex;
            gap: 8px;
            margin: 15px 0;
        }

        .badge-total {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fff3e0;
            color: #f57c00;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }

        .btn-view {
            flex: 1;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            border: none;
            padding: 8px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,0,0.4);
            color: white;
        }

        .btn-delete {
            width: 38px;
            height: 38px;
            background: #fee;
            color: #c33;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background: #c33;
            color: white;
            transform: scale(1.1);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h5 {
            color: #495057;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6c757d;
        }

        /* Pagination */
        .pagination {
            gap: 5px;
        }

        .pagination .page-link {
            color: #667eea;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(128,0,0,0.3);
        }

        .pagination .page-link:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        /* Alerts */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3">
            <h4 class="text-center">
                <i class="bi bi-shield-shaded"></i> Admin Panel
            </h4>
            <nav>
                <a href="dashboard.php">
                    <i class="bi bi-speedometer2"></i>Dashboard
                </a>
                <a href="requests.php">
                    <i class="bi bi-list-check"></i>All Requests
                </a>
                <a href="students.php" class="active">
                    <i class="bi bi-people"></i>Students
                </a>
                <a href="reports.php">
                    <i class="bi bi-graph-up"></i>Reports
                </a>
                <a href="settings.php">
                    <i class="bi bi-gear"></i>Settings
                </a>
                <hr class="bg-white opacity-25">
                <a href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i>Logout
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
                <i class="bi bi-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php $error = Session::getFlash('error'); ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <h2>
                <i class="bi bi-people-fill"></i>
                Manage Students
            </h2>
            <div class="total-badge">
                <i class="bi bi-person"></i>
                Total: <?php echo $total_students; ?>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-card">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0">
                            <i class="bi bi-search text-primary"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Search by name, student ID, or email..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-2"></i>Search
                    </button>
                </div>
                <?php if (!empty($search)): ?>
                <div class="col-12">
                    <a href="students.php" class="text-primary text-decoration-none small">
                        <i class="bi bi-arrow-counterclockwise"></i> Clear search
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Students Grid -->
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h5>No Students Found</h5>
                <p class="text-muted">
                    <?php if (!empty($search)): ?>
                        No results match your search criteria. 
                        <a href="students.php" class="text-primary">Clear search</a>
                    <?php else: ?>
                        There are no registered students yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($students as $student): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="student-card">
                        <div class="d-flex align-items-center">
                            <div class="student-avatar">
                                <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                            </div>
                            <div class="student-info">
                                <h6><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                <small><?php echo htmlspecialchars($student['student_id']); ?></small>
                            </div>
                        </div>

                        <div class="student-details">
                            <div class="detail-item">
                                <i class="bi bi-envelope"></i>
                                <?php echo htmlspecialchars($student['email']); ?>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-book"></i>
                                <?php echo htmlspecialchars($student['course'] ?: 'N/A'); ?> - 
                                <?php echo $student['year_level'] ?: 'N/A'; ?>st Year
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-calendar"></i>
                                Registered: <?php echo date('M d, Y', strtotime($student['created_at'])); ?>
                            </div>
                        </div>

                        <div class="request-badges">
                            <span class="badge-total">
                                <i class="bi bi-file-text"></i>
                                Total: <?php echo $student['total_requests']; ?>
                            </span>
                            <?php if ($student['pending_requests'] > 0): ?>
                            <span class="badge-pending">
                                <i class="bi bi-hourglass"></i>
                                Pending: <?php echo $student['pending_requests']; ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions">
                            <a href="view_student.php?id=<?php echo $student['id']; ?>" 
                               class="btn-view">
                                <i class="bi bi-eye"></i>
                                View Details
                            </a>
                            <button class="btn-delete" 
                                    onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['full_name'])); ?>')"
                                    title="Delete Student">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php include '../includes/admin_layout_end.php'; ?>