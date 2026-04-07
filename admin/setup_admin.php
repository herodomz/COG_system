<?php
// setup_admin.php - Run this once to setup database admin
require_once 'config/database.php';
require_once 'config/session.php';

echo "<h2>🔧 Admin Setup Tool</h2>";

$database = new Database();
$db = $database->getConnection();

// Create admins table if not exists
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($db->exec($sql)) {
    echo "✅ admins table ready<br>";
} else {
    echo "❌ Failed to create admins table<br>";
}

// Hard-coded admin credentials
$hardcoded_admin = [
    'username' => 'admin@olshco.edu.com',
    'full_name' => 'System Administrator',
    'email' => 'admin@olshco.edu.com',
    'password' => password_hash('admin123', PASSWORD_DEFAULT)
];

// Check if admin already exists
$check = $db->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
$check->execute([$hardcoded_admin['username'], $hardcoded_admin['email']]);

if ($check->rowCount() == 0) {
    // Insert admin
    $query = "INSERT INTO admins (username, full_name, email, password) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([
        $hardcoded_admin['username'],
        $hardcoded_admin['full_name'],
        $hardcoded_admin['email'],
        $hardcoded_admin['password']
    ])) {
        echo "✅ Admin account created in database!<br>";
    } else {
        echo "❌ Failed to create admin in database<br>";
    }
} else {
    echo "✅ Admin account already exists in database<br>";
}

// Display admin accounts
echo "<h3>📋 Current Admin Accounts:</h3>";
$admins = $db->query("SELECT id, username, full_name, email, created_at FROM admins")->fetchAll();

if (count($admins) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Created</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr>";
        echo "<td>" . $admin['id'] . "</td>";
        echo "<td>" . $admin['username'] . "</td>";
        echo "<td>" . $admin['full_name'] . "</td>";
        echo "<td>" . $admin['email'] . "</td>";
        echo "<td>" . $admin['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No admin accounts found in database.</p>";
}

echo "<h3>🔐 Login Credentials:</h3>";
echo "<ul>";
echo "<li><strong>Email:</strong> admin@olshco.edu.com</li>";
echo "<li><strong>Password:</strong> admin123</li>";
echo "</ul>";

echo "<p><a href='admin/login.php' target='_blank'>Go to Admin Login Page →</a></p>";
echo "<p><a href='admin/dashboard.php' target='_blank'>Go to Admin Dashboard (after login) →</a></p>";
?>