<?php
// config/session.php
require_once __DIR__ . '/env.php';

$timeout = (int) env('SESSION_TIMEOUT', 1800);
define('SESSION_TIMEOUT', $timeout);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),  // true in production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

class Session {

    public static function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key): mixed {
        return $_SESSION[$key] ?? null;
    }

    public static function destroy(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
    }

    /**
     * Check inactivity timeout.
     * Returns TRUE and destroys the session if timed out.
     */
    public static function checkTimeout(): bool {
        if (!self::isLoggedIn()) return false;
        if (isset($_SESSION['last_activity'])) {
            if ((time() - $_SESSION['last_activity']) >= SESSION_TIMEOUT) {
                self::destroy();
                return true;
            }
        }
        $_SESSION['last_activity'] = time();
        return false;
    }

    public static function refreshActivity(): void {
        $_SESSION['last_activity'] = time();
    }

    /** Remaining seconds before session expires. */
    public static function getRemainingTime(): int {
        if (!isset($_SESSION['last_activity'])) return SESSION_TIMEOUT;
        return max(0, SESSION_TIMEOUT - (time() - $_SESSION['last_activity']));
    }

    public static function generateCSRFToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRFToken(string $token): bool {
        return !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function regenerateCSRFToken(): void {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function setFlash(string $key, string $message): void {
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string {
        if (!isset($_SESSION['flash'][$key])) return null;
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

// Auto-check timeout on every request (skip AJAX endpoints that define this)
if (!defined('SKIP_TIMEOUT_CHECK')) {
    if (Session::checkTimeout()) {
        $isAdmin = str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/');
        $dest    = $isAdmin ? '/admin/login.php' : '/index.php';
        header("Location: {$dest}?timeout=1");
        exit();
    }
    Session::refreshActivity();
}