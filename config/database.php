<?php
// config/database.php
require_once __DIR__ . '/env.php';

class Database {
    private string $host;
    private string $port;
    private string $db_name;
    private string $username;
    private string $password;
    private ?PDO   $conn = null;

    public function __construct() {
        $this->host     = env('DB_HOST', '127.0.0.1');
        $this->port     = env('DB_PORT', '3306');
        $this->db_name  = env('DB_NAME', 'cog_management_system');
        $this->username = env('DB_USER', 'cog_user');
        $this->password = env('DB_PASS', '');
    }

    public function getConnection(): PDO {
        if ($this->conn !== null) return $this->conn;
        try {
            $dsn = "mysql:host={$this->host};port={$this->port}"
                 . ";dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log("DB connection error: " . $e->getMessage());
            $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Database connection failed.']);
            } else {
                echo '<div style="font-family:sans-serif;padding:2rem;color:#800000">'
                   . '<h2>Database Error</h2>'
                   . '<p>Could not connect to database. Please check your .env file.</p></div>';
            }
            exit();
        }
        return $this->conn;
    }
}