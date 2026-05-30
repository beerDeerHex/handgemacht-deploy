<?php
require_once __DIR__ . '/lib/auth.php';

if (is_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if (is_rate_limited()) {
    $mins = ceil(lockout_seconds_remaining() / 60);
    $error = "Zu viele Fehlversuche. Bitte warte $mins Minute(n).";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (login($password)) {
        header('Location: /admin/dashboard.php');
        exit;
    }
    $remaining = MAX_ATTEMPTS - ($_SESSION['login_attempts'] ?? 0);
    $error = is_rate_limited()
        ? 'Zu viele Fehlversuche. Bitte warte 15 Minuten.'
        : "Falsches Passwort. Noch $remaining Versuch(e) übrig.";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handgemacht – Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: white; border-radius: 8px; padding: 2rem; width: 100%; max-width: 360px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { font-size: 1.4rem; margin-bottom: 1.5rem; color: #1f2937; text-align: center; }
        label { display: block; font-size: 0.9rem; color: #374151; margin-bottom: 0.3rem; }
        input[type=password] { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; margin-bottom: 1rem; }
        button { width: 100%; padding: 0.7rem; background: #1f2937; color: white; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        button:hover { background: #374151; }
        .error { color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>🧶 Handgemacht Admin</h1>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST">
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" autofocus required>
        <button type="submit" <?= is_rate_limited() ? 'disabled' : '' ?>>Anmelden</button>
    </form>
</div>
</body>
</html>
