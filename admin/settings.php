<?php
// admin/settings.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!Session::isLoggedIn() || Session::get('role') != 'admin') {
    Session::setFlash('error', 'Please login as admin.');
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Create settings table if it doesn't exist
try {
    $create_table = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($create_table);
    
    // Insert default settings if table is empty
    $check = $db->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($check == 0) {
        $default_settings = [
            'system_name' => 'COG Management System',
            'school_name' => 'OLSHCO',
            'price_per_copy' => '50.00',
            'contact_email' => 'admin@olshco.edu',
            'contact_phone' => '',
            'address' => ''
        ];
        
        $insert = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)";
        $stmt = $db->prepare($insert);
        foreach ($default_settings as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }
    }
} catch (PDOException $e) {
    error_log("Settings table error: " . $e->getMessage());
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    if (isset($_POST['update_general'])) {
        try {
            $db->beginTransaction();
            
            $settings = [
                'system_name' => trim($_POST['system_name']),
                'school_name' => trim($_POST['school_name']),
                'contact_email' => trim($_POST['contact_email']),
                'contact_phone' => trim($_POST['contact_phone']),
                'address' => trim($_POST['address'])
            ];
            
            // Validate email
            if (!empty($settings['contact_email']) && !filter_var($settings['contact_email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            // Update settings in database
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) 
                      ON DUPLICATE KEY UPDATE setting_value = :value";
            $stmt = $db->prepare($query);
            
            foreach ($settings as $key => $value) {
                $stmt->execute([':key' => $key, ':value' => $value]);
            }
            
            $db->commit();
            Session::setFlash('success', 'General settings updated successfully!');
            
        } catch (Exception $e) {
            $db->rollBack();
            Session::setFlash('error', 'Error updating settings: ' . $e->getMessage());
        }
        
        header("Location: settings.php");
        exit();
        
    } elseif (isset($_POST['update_fees'])) {
        try {
            $price_per_copy = (float)$_POST['price_per_copy'];
            
            if ($price_per_copy < 0) {
                throw new Exception("Price cannot be negative");
            }
            
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES ('price_per_copy', :value) 
                      ON DUPLICATE KEY UPDATE setting_value = :value";
            $stmt = $db->prepare($query);
            $stmt->execute([':value' => $price_per_copy]);
            
            Session::setFlash('success', 'Fee settings updated successfully!');
            
        } catch (Exception $e) {
            Session::setFlash('error', 'Error updating fees: ' . $e->getMessage());
        }
        
        header("Location: settings.php");
        exit();
        
    } elseif (isset($_POST['add_admin'])) {
        try {
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            
            // Validate inputs
            if (strlen($username) < 3) {
                throw new Exception("Username must be at least 3 characters");
            }
            
            if (strlen($full_name) < 2) {
                throw new Exception("Full name is required");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            if (strlen($password) < 8) {
                throw new Exception("Password must be at least 8 characters");
            }
            
            // Check if username exists
            $check_query = "SELECT id FROM admins WHERE username = :username OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([':username' => $username, ':email' => $email]);
            
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("Username or email already exists!");
            }
            
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO admins (username, full_name, email, password) 
                      VALUES (:username, :full_name, :email, :password)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':username' => $username,
                ':full_name' => $full_name,
                ':email' => $email,
                ':password' => $hashed_password
            ]);
            
            Session::setFlash('success', "New admin '$username' added successfully!");
            
        } catch (Exception $e) {
            Session::setFlash('error', $e->getMessage());
        }
        
        header("Location: settings.php");
        exit();
    }
}

// Get current settings
$settings = [];
try {
    $settings_query = "SELECT setting_key, setting_value FROM settings ORDER BY setting_key";
    $settings_stmt = $db->query($settings_query);
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Get admins list
$admins = [];
try {
    $admins_query = "SELECT id, username, full_name, email, created_at FROM admins ORDER BY created_at DESC";
    $admins = $db->query($admins_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching admins: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
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
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        .sidebar a.active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
            font-weight: 600;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .settings-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .settings-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .settings-header h5 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }
        .settings-header i {
            color: #667eea;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #800000;
            box-shadow: 0 0 0 0.2rem rgba(128,0,0,0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border: none;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,0,0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,0,0.4);
        }
        .table th {
            border-top: none;
            color: #6c757d;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td {
            vertical-align: middle;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #800000;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-box i {
            color: #800000;
            margin-right: 10px;
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
        <div class="p-3">
            <h4 class="text-center mb-4 fw-bold">Admin Panel</h4>
            <nav>
                <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="requests.php"><i class="bi bi-list-check me-2"></i>All Requests</a>
                <a href="students.php"><i class="bi bi-people me-2"></i>Students</a>
                <a href="reports.php"><i class="bi bi-graph-up me-2"></i>Reports</a>
                <a href="settings.php" class="active"><i class="bi bi-gear me-2"></i>Settings</a>
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

        <?php $error = Session::getFlash('error'); ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">System Settings</h2>
            <div>
                <span class="badge bg-light text-dark p-3">
                    <i class="bi bi-shield-check text-success me-2"></i>Admin: <?php echo htmlspecialchars(Session::get('admin_name')); ?>
                </span>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <i class="bi bi-info-circle-fill"></i>
            <strong>System Configuration:</strong> Manage your system settings, fees, and admin accounts here. Changes take effect immediately.
        </div>

        <!-- General Settings -->
        <div class="settings-card">
            <div class="settings-header">
                <h5><i class="bi bi-gear me-2"></i>General Settings</h5>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
                <input type="hidden" name="update_general" value="1">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">System Name</label>
                        <input type="text" class="form-control" name="system_name" 
                               value="<?php echo isset($settings['system_name']) ? htmlspecialchars($settings['system_name']) : 'COG Management System'; ?>" required>
                        <small class="text-muted">Name displayed throughout the system</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">School Name</label>
                        <input type="text" class="form-control" name="school_name" 
                               value="<?php echo isset($settings['school_name']) ? htmlspecialchars($settings['school_name']) : 'OLSHCO'; ?>" required>
                        <small class="text-muted">Institution name</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Contact Email</label>
                        <input type="email" class="form-control" name="contact_email" 
                               value="<?php echo isset($settings['contact_email']) ? htmlspecialchars($settings['contact_email']) : ''; ?>">
                        <small class="text-muted">For system notifications</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Contact Phone</label>
                        <input type="text" class="form-control" name="contact_phone" 
                               value="<?php echo isset($settings['contact_phone']) ? htmlspecialchars($settings['contact_phone']) : ''; ?>">
                        <small class="text-muted">Registrar's office contact</small>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <textarea class="form-control" name="address" rows="2"><?php echo isset($settings['address']) ? htmlspecialchars($settings['address']) : ''; ?></textarea>
                        <small class="text-muted">School address</small>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Save General Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Fee Settings -->
        <div class="settings-card">
            <div class="settings-header">
                <h5><i class="bi bi-cash me-2"></i>Fee Settings</h5>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
                <input type="hidden" name="update_fees" value="1">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Price per Copy (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" name="price_per_copy" step="0.01" min="0"
                                   value="<?php echo isset($settings['price_per_copy']) ? htmlspecialchars($settings['price_per_copy']) : '50.00'; ?>" required>
                        </div>
                        <small class="text-muted">Default price for each COG copy</small>
                    </div>
                    <div class="col-md-8 mb-3 d-flex align-items-end">
                        <div class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Current fee structure: ₱<?php echo isset($settings['price_per_copy']) ? number_format($settings['price_per_copy'], 2) : '50.00'; ?> per copy
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Save Fee Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Admin Management -->
        <div class="settings-card">
            <div class="settings-header">
                <h5><i class="bi bi-person-badge me-2"></i>Admin Management</h5>
                <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#addAdminForm">
                    <i class="bi bi-plus-circle"></i> Add New Admin
                </button>
            </div>
            
            <!-- Add Admin Form (Collapsible) -->
            <div class="collapse mb-4" id="addAdminForm">
                <div class="card card-body border-0 bg-light">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus text-success me-2"></i>New Admin Account</h6>
                    <form method="POST" onsubmit="return validateAdminForm()">
                        <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRFToken(); ?>">
                        <input type="hidden" name="add_admin" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <input type="text" class="form-control" name="username" id="username" required minlength="3">
                                <small class="text-muted">Minimum 3 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control" name="full_name" id="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" class="form-control" name="email" id="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Password</label>
                                <input type="password" class="form-control" name="password" id="password" required minlength="8">
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-person-plus"></i> Create Admin Account
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addAdminForm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Admins List -->
            <h6 class="fw-bold mb-3">Current Administrators</h6>
            <?php if (count($admins) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($admin['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                            <td>
                                <span class="badge-status badge-active">
                                    <i class="bi bi-check-circle"></i> Active
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-people display-4 text-muted d-block mb-3"></i>
                <p class="text-muted">No admin accounts found.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- System Information -->
        <div class="settings-card">
            <div class="settings-header">
                <h5><i class="bi bi-info-circle me-2"></i>System Information</h5>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted">PHP Version:</td>
                            <td class="fw-bold"><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Database:</td>
                            <td class="fw-bold">MySQL</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Server:</td>
                            <td class="fw-bold"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted">Total Admins:</td>
                            <td class="fw-bold"><?php echo count($admins); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Last Updated:</td>
                            <td class="fw-bold"><?php echo date('F d, Y h:i A'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">System Version:</td>
                            <td class="fw-bold">1.0.0</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    <?php include '../includes/admin_layout_end.php'; ?>