<?php
// admin/reports.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'admin') {
    Session::setFlash('error', 'Please login as admin.');
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get report data based on type
switch ($report_type) {
    case 'daily':
        $query = "SELECT DATE(request_date) as date, 
                  COUNT(*) as total_requests,
                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                  SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready,
                  SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
                  COALESCE(SUM(amount), 0) as total_amount,
                  COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount
                  FROM cog_requests 
                  WHERE DATE(request_date) BETWEEN :start_date AND :end_date
                  GROUP BY DATE(request_date)
                  ORDER BY date DESC";
        break;
        
    case 'monthly':
        $query = "SELECT DATE_FORMAT(request_date, '%Y-%m') as month,
                  COUNT(*) as total_requests,
                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                  SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready,
                  SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
                  COALESCE(SUM(amount), 0) as total_amount,
                  COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount
                  FROM cog_requests 
                  WHERE DATE(request_date) BETWEEN :start_date AND :end_date
                  GROUP BY DATE_FORMAT(request_date, '%Y-%m')
                  ORDER BY month DESC";
        break;
        
    case 'course':
        $query = "SELECT COALESCE(u.course, 'N/A') as course,
                  COUNT(r.id) as total_requests,
                  SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN r.status = 'processing' THEN 1 ELSE 0 END) as processing,
                  SUM(CASE WHEN r.status = 'ready' THEN 1 ELSE 0 END) as ready,
                  SUM(CASE WHEN r.status = 'released' THEN 1 ELSE 0 END) as released,
                  COALESCE(SUM(r.amount), 0) as total_amount,
                  COALESCE(SUM(CASE WHEN r.payment_status = 'paid' THEN r.amount ELSE 0 END), 0) as paid_amount
                  FROM cog_requests r
                  LEFT JOIN users u ON r.user_id = u.id
                  WHERE DATE(r.request_date) BETWEEN :start_date AND :end_date
                  GROUP BY u.course
                  ORDER BY total_requests DESC";
        break;
}

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summary_query = "SELECT 
                  COALESCE(COUNT(*), 0) as total_requests,
                  COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
                  COALESCE(SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END), 0) as processing,
                  COALESCE(SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END), 0) as ready,
                  COALESCE(SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END), 0) as released,
                  COALESCE(SUM(amount), 0) as total_amount,
                  COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount,
                  COALESCE(COUNT(DISTINCT user_id), 0) as unique_students
                  FROM cog_requests 
                  WHERE DATE(request_date) BETWEEN :start_date AND :end_date";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->bindParam(':start_date', $start_date);
$summary_stmt->bindParam(':end_date', $end_date);
$summary_stmt->execute();
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Ensure all values are numeric
$summary['total_requests'] = (int)$summary['total_requests'];
$summary['pending'] = (int)$summary['pending'];
$summary['processing'] = (int)$summary['processing'];
$summary['ready'] = (int)$summary['ready'];
$summary['released'] = (int)$summary['released'];
$summary['total_amount'] = (float)$summary['total_amount'];
$summary['paid_amount'] = (float)$summary['paid_amount'];
$summary['unique_students'] = (int)$summary['unique_students'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: maroon;
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
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .summary-card {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .summary-card::after {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .summary-card .number {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0 5px;
        }
        .summary-card .label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .no-data-message {
            text-align: center;
            padding: 50px;
            color: #6c757d;
        }
        .no-data-message i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border: none;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,0,0.4);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3">
            <h4 class="text-center mb-4">Admin Panel</h4>
            <nav>
                <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="requests.php"><i class="bi bi-list-check me-2"></i>All Requests</a>
                <a href="students.php"><i class="bi bi-people me-2"></i>Students</a>
                <a href="reports.php" class="active"><i class="bi bi-graph-up me-2"></i>Reports</a>
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
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Reports & Analytics</h2>
            <div>
                <span class="badge bg-light text-dark p-3">
                    <i class="bi bi-calendar-range me-2"></i>
                    <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
                </span>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Report Type</label>
                    <select name="type" class="form-select">
                        <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily Report</option>
                        <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                        <option value="course" <?php echo $report_type == 'course' ? 'selected' : ''; ?>>By Course</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-graph-up me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="label">Total Requests</div>
                    <div class="number"><?php echo number_format($summary['total_requests']); ?></div>
                    <small>Selected period</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <div class="label">Completed</div>
                    <div class="number"><?php echo number_format($summary['released']); ?></div>
                    <small>Released requests</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                    <div class="label">Pending</div>
                    <div class="number"><?php echo number_format($summary['pending']); ?></div>
                    <small>Awaiting processing</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card" style="background: linear-gradient(135deg, #17a2b8 0%, #6610f2 100%);">
                    <div class="label">Revenue</div>
                    <div class="number">₱<?php echo number_format($summary['paid_amount'], 2); ?></div>
                    <small>Total collected</small>
                </div>
            </div>
        </div>

        <!-- Additional Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="report-card">
                    <h6 class="fw-bold mb-3"><i class="bi bi-people text-primary me-2"></i>Student Statistics</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Unique Students:</span>
                        <span class="fw-bold"><?php echo $summary['unique_students']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Average per Student:</span>
                        <span class="fw-bold">
                            <?php 
                            $avg = $summary['unique_students'] > 0 ? $summary['total_requests'] / $summary['unique_students'] : 0;
                            echo number_format($avg, 1); 
                            ?> requests
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <h6 class="fw-bold mb-3"><i class="bi bi-cash-stack text-success me-2"></i>Payment Statistics</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Collection Rate:</span>
                        <span class="fw-bold text-success">
                            <?php 
                            $rate = $summary['total_amount'] > 0 ? ($summary['paid_amount'] / $summary['total_amount']) * 100 : 0;
                            echo number_format($rate, 1); ?>%
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Unpaid Amount:</span>
                        <span class="fw-bold text-warning">
                            ₱<?php echo number_format($summary['total_amount'] - $summary['paid_amount'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history text-info me-2"></i>Processing Status</h6>
                    <div class="progress mb-2" style="height: 20px;">
                        <?php 
                        $total = $summary['total_requests'] ?: 1;
                        $pending_pct = ($summary['pending'] / $total) * 100;
                        $processing_pct = ($summary['processing'] / $total) * 100;
                        $ready_pct = ($summary['ready'] / $total) * 100;
                        $released_pct = ($summary['released'] / $total) * 100;
                        ?>
                        <div class="progress-bar bg-warning" style="width: <?php echo $pending_pct; ?>%" 
                             title="Pending: <?php echo $summary['pending']; ?>">P</div>
                        <div class="progress-bar bg-info" style="width: <?php echo $processing_pct; ?>%" 
                             title="Processing: <?php echo $summary['processing']; ?>">R</div>
                        <div class="progress-bar bg-success" style="width: <?php echo $ready_pct; ?>%" 
                             title="Ready: <?php echo $summary['ready']; ?>">R</div>
                        <div class="progress-bar bg-secondary" style="width: <?php echo $released_pct; ?>%" 
                             title="Released: <?php echo $summary['released']; ?>">L</div>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-warning">Pending</span>
                        <span class="text-info">Processing</span>
                        <span class="text-success">Ready</span>
                        <span class="text-secondary">Released</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <?php if ($summary['total_requests'] > 0): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="report-card">
                    <h5 class="mb-3 fw-bold">Request Status Distribution</h5>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="report-card">
                    <h5 class="mb-3 fw-bold">Payment Status</h5>
                    <div class="chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="report-card text-center py-5">
            <i class="bi bi-bar-chart display-1 text-muted mb-3"></i>
            <h5 class="text-muted">No Data Available</h5>
            <p class="text-muted">There are no requests in the selected date range.</p>
            <a href="?type=daily&start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" 
               class="btn btn-outline-primary mt-3">
                <i class="bi bi-arrow-counterclockwise me-2"></i>Reset to Default Range
            </a>
        </div>
        <?php endif; ?>

        <!-- Report Table -->
        <?php if (!empty($report_data)): ?>
        <div class="report-card">
            <h5 class="mb-3 fw-bold">
                <?php 
                switch($report_type) {
                    case 'daily': echo "Daily Report Details"; break;
                    case 'monthly': echo "Monthly Report Details"; break;
                    case 'course': echo "Report by Course Details"; break;
                }
                ?>
            </h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?php echo $report_type == 'daily' ? 'Date' : ($report_type == 'monthly' ? 'Month' : 'Course'); ?></th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Pending</th>
                            <th class="text-center">Processing</th>
                            <th class="text-center">Ready</th>
                            <th class="text-center">Released</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-end">Paid Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        $grand_paid = 0;
                        foreach ($report_data as $row): 
                            $grand_total += $row['total_amount'];
                            $grand_paid += $row['paid_amount'];
                        ?>
                        <tr>
                            <td>
                                <strong>
                                <?php 
                                if ($report_type == 'daily') {
                                    echo date('M d, Y', strtotime($row['date']));
                                } elseif ($report_type == 'monthly') {
                                    echo date('F Y', strtotime($row['month'] . '-01'));
                                } else {
                                    echo htmlspecialchars($row['course'] ?: 'Not Specified');
                                }
                                ?>
                                </strong>
                            </td>
                            <td class="text-center"><?php echo $row['total_requests']; ?></td>
                            <td class="text-center"><span class="badge bg-warning"><?php echo $row['pending']; ?></span></td>
                            <td class="text-center"><span class="badge bg-info"><?php echo $row['processing']; ?></span></td>
                            <td class="text-center"><span class="badge bg-success"><?php echo $row['ready']; ?></span></td>
                            <td class="text-center"><span class="badge bg-secondary"><?php echo $row['released']; ?></span></td>
                            <td class="text-end">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td class="text-end text-success">₱<?php echo number_format($row['paid_amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Grand Total Row -->
                        <tr class="table-light fw-bold">
                            <td>TOTAL</td>
                            <td class="text-center"><?php echo array_sum(array_column($report_data, 'total_requests')); ?></td>
                            <td class="text-center"><?php echo array_sum(array_column($report_data, 'pending')); ?></td>
                            <td class="text-center"><?php echo array_sum(array_column($report_data, 'processing')); ?></td>
                            <td class="text-center"><?php echo array_sum(array_column($report_data, 'ready')); ?></td>
                            <td class="text-center"><?php echo array_sum(array_column($report_data, 'released')); ?></td>
                            <td class="text-end">₱<?php echo number_format($grand_total, 2); ?></td>
                            <td class="text-end text-success">₱<?php echo number_format($grand_paid, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php include '../includes/admin_layout_end.php'; ?>