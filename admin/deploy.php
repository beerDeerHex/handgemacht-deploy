<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/dashboard.php');
    exit;
}
verify_csrf();

if (!admin_branch_exists()) {
    // Nothing to deploy — no pending branch at all.
    header('Location: /admin/dashboard.php?deploy=nothing');
    exit;
}

$ok = github_deploy();
header('Location: /admin/dashboard.php?deploy=' . ($ok ? 'ok' : 'fail'));
exit;
