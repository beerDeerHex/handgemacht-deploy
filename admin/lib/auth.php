<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('CONFIG_LOADED', true);
$_localConfig = __DIR__ . '/../../config.local.php';
if (file_exists($_localConfig)) {
    require_once $_localConfig;
} else {
    require_once '/home/u237207940/domains/config.php';
}

function is_logged_in(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /admin/index.php');
        exit;
    }
}

const MAX_ATTEMPTS  = 5;
const LOCKOUT_SECS  = 15 * 60; // 15 minutes

function is_rate_limited(): bool {
    $until = $_SESSION['lockout_until'] ?? 0;
    if ($until && time() < $until) return true;
    if ($until && time() >= $until) {
        // Lockout expired — reset
        unset($_SESSION['lockout_until'], $_SESSION['login_attempts']);
    }
    return false;
}

function lockout_seconds_remaining(): int {
    return max(0, ($_SESSION['lockout_until'] ?? 0) - time());
}

function login(string $password): bool {
    if (is_rate_limited()) return false;
    if (!defined('ADMIN_PASSWORD_HASH')) return false;
    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        unset($_SESSION['login_attempts'], $_SESSION['lockout_until']);
        $_SESSION['admin_logged_in'] = true;
        return true;
    }
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
        $_SESSION['lockout_until'] = time() + LOCKOUT_SECS;
    }
    return false;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Ungültige Anfrage.');
    }
}
