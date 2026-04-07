<?php
// admin/login.php
require_once '../config/database.php';
require_once '../config/session.php';

define('ADMIN_USERNAME', 'admin@olshco.edu.com');
define('ADMIN_PASSWORD', 'admin123');
define('ADMIN_NAME',     'System Administrator');
define('ADMIN_EMAIL',    'admin@olshco.edu.com');

if (Session::isLoggedIn()) {
    header("Location: " . (Session::get('role') == 'admin' ? 'dashboard.php' : '../student/dashboard.php'));
    exit();
}

$error   = '';
$timeout = isset($_GET['timeout']) && $_GET['timeout'] == '1';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        Session::set('admin_id',   999);
        Session::set('admin_name', ADMIN_NAME);
        Session::set('role',       'admin');
        Session::refreshActivity();
        Session::setFlash('success', 'Welcome back, ' . ADMIN_NAME . '!');
        header("Location: dashboard.php");
        exit();
    }

    try {
        $database = new Database();
        $db       = $database->getConnection();
        $stmt     = $db->prepare("SELECT * FROM admins WHERE username = :u OR email = :u");
        $stmt->execute([':u' => $username]);

        if ($stmt->rowCount() > 0) {
            $admin = $stmt->fetch();
            if (password_verify($password, $admin['password'])) {
                Session::set('admin_id',   $admin['id']);
                Session::set('admin_name', $admin['full_name']);
                Session::set('role',       'admin');
                Session::refreshActivity();
                Session::setFlash('success', 'Welcome back, ' . $admin['full_name'] . '!');
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "Admin account not found!";
        }
    } catch (PDOException $e) {
        $error = "Database error. Use hard-coded admin: " . ADMIN_USERNAME . " / " . ADMIN_PASSWORD;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:linear-gradient(135deg,#800000,#660000); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .login-card { background:#fff; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,.2); padding:40px; width:100%; max-width:400px; }
        .form-control:focus { border-color:maroon; box-shadow:0 0 0 .2rem rgba(128,0,0,.2); }
        .btn-login { background:maroon; border:none; color:#fff; padding:12px; border-radius:8px; width:100%; font-weight:600; }
        .btn-login:hover { opacity:.9; }
        .admin-info { background:#f0f7ff; border-left:4px solid maroon; padding:12px; border-radius:5px; font-size:13px; }
    </style>
</head>
<body>
<div class="login-card">
    <h3 class="fw-bold text-center mb-1">Admin Login</h3>
    <p class="text-center text-muted mb-4">OLSHCO – COG Management System</p>

    <?php if ($timeout): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-clock-history"></i>
            Session expired due to inactivity. Please log in again.
        </div>
    <?php endif; ?>

    <div class="admin-info mb-3">
        <strong>⚠️ Dev Mode</strong> – Default admin:<br>
        <strong>Email:</strong> admin@olshco.edu.com &nbsp;
        <strong>PW:</strong> admin123
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php $fe = Session::getFlash('error'); if ($fe): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($fe) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= Session::generateCSRFToken() ?>">
        <div class="mb-3">
            <label class="form-label">Username or Email</label>
            <input type="text" class="form-control" name="username" placeholder="Enter username or email" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn-login">Login</button>
        <div class="text-center mt-3">
            <a href="../index.php" style="color:maroon;font-size:14px;">← Back to Student Login</a>
        </div>
    </form>
</div>
</body>
</html>