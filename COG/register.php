<?php
// register.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/Email.php';

if (Session::isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $student_id = $_POST['student_id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $course = $_POST['course'];
    $year_level = $_POST['year_level'];
    
    // Check if student_id or email already exists
    $check_query = "SELECT * FROM users WHERE student_id = :student_id OR email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':student_id', $student_id);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $error = "Student ID or Email already exists!";
    } else {
        $query = "INSERT INTO users (student_id, full_name, email, password, course, year_level) 
                  VALUES (:student_id, :full_name, :email, :password, :course, :year_level)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':course', $course);
        $stmt->bindParam(':year_level', $year_level);
        
        if ($stmt->execute()) {
            // ── Send welcome email ──────────────────────────────────────────
            Email::sendWelcome($email, $full_name, $student_id);
            // ───────────────────────────────────────────────────────────────

            $success = "Registration successful! A welcome email has been sent. You can now login.";
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - COG Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h3 {
            color: #333;
            font-weight: 600;
        }
        .btn-register {
            background: linear-gradient(135deg, #800000 0%, #660000 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            color: white;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .login-link a {
            color: maroon;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="register-header">
            <h3>Student Registration</h3>
            <p>OLSHCO - Certificate of Grades</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="student_id" class="form-label">Student ID</label>
                <input type="text" class="form-control" id="student_id" name="student_id" required 
                       pattern="[0-9]{4}-[0-9]{4}" placeholder="YYYY-####">
                <small class="text-muted">Format: YYYY-#### (e.g., 2024-0001)</small>
            </div>

            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="full_name" name="full_name" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required 
                       minlength="6">
                <small class="text-muted">Minimum 6 characters</small>
            </div>

            <div class="mb-3">
                <label for="course" class="form-label">Course</label>
                <select class="form-select" id="course" name="course" required>
                    <option value="">Select Course</option>
                    <option value="BSIT">Bachelor of Science in Information Technology</option>
                    <option value="BSCS">Bachelor of Science in Computer Science</option>
                    <option value="BSED">Bachelor of Secondary Education</option>
                    <option value="BEED">Bachelor of Elementary Education</option>
                    <option value="BSBA">Bachelor of Science in Business Administration</option>
                    <option value="BSHRM">Bachelor of Science in Hospitality Management</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="year_level" class="form-label">Year Level</label>
                <select class="form-select" id="year_level" name="year_level" required>
                    <option value="">Select Year Level</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>

            <button type="submit" class="btn btn-register">Register</button>

            <div class="login-link">
                Already have an account? <a href="index.php">Login here</a>
            </div>
        </form>
    </div>
</body>
</html>