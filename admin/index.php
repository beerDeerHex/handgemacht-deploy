<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/layout.php';

if (is_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if (is_rate_limited()) {
    $mins  = ceil(lockout_seconds_remaining() / 60);
    $error = "Zu viele Fehlversuche. Bitte warte $mins Minute(n) und versuche es dann erneut.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (login($password)) {
        header('Location: /admin/dashboard.php');
        exit;
    }
    $remaining = MAX_ATTEMPTS - ($_SESSION['login_attempts'] ?? 0);
    $error = is_rate_limited()
        ? 'Zu viele Fehlversuche. Bitte warte 5 Minuten.'
        : "Das Passwort war leider falsch. Noch $remaining Versuch(e) übrig.";
}

admin_html_head('Handgemacht – Anmelden', 'login-body');
?>
<div class="login-card">
    <h1>🧶 Handgemacht</h1>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="field">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" autofocus required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-xl" <?= is_rate_limited() ? 'disabled' : '' ?>>Anmelden</button>
    </form>
</div>
<?php
admin_html_foot();
