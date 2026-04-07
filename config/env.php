<?php
ini_set('error_log', __DIR__ . '/../php_errors.log');
// config/env.php – Lightweight .env loader (no Composer required)

function loadEnv(string $filePath): void {
    if (!file_exists($filePath)) {
        error_log("[ENV] .env file not found at {$filePath}");
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip inline comments
        if (($pos = strpos($value, '#')) !== false) {
            $value = trim(substr($value, 0, $pos));
        }
        // Strip surrounding quotes
        if (strlen($value) >= 2) {
            $q = $value[0];
            if (($q === '"' || $q === "'") && substr($value, -1) === $q) {
                $value = substr($value, 1, -1);
            }
        }
        if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }
}

// Load from /workspaces/COG/COG/.env
loadEnv(dirname(__DIR__) . '/.env');

/**
 * env() – read an environment variable with an optional default.
 */
function env(string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') return $default;
    return match (strtolower((string)$val)) {
        'true',  '(true)'  => true,
        'false', '(false)' => false,
        'null',  '(null)'  => null,
        default             => $val,
    };
}
