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

function login(string $password): bool {
    if (!defined('ADMIN_PASSWORD_HASH')) return false;
    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        return true;
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
