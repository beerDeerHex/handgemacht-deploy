<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/layout.php';
require_login();

$events   = read_events();
$deleteId = $_GET['id'] ?? '';
$found    = null;

foreach ($events as $e) {
    if ($e['id'] === $deleteId) { $found = $e; break; }
}

if (!$found) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $events = array_values(array_filter($events, fn($e) => $e['id'] !== $deleteId));
    if (write_events($events, "Veranstaltung gelöscht: {$found['name']}")) {
        header('Location: /admin/dashboard.php');
        exit;
    }
    $error = 'Löschen hat leider nicht geklappt. Bitte versuche es erneut.';
}

admin_html_head('Handgemacht – Veranstaltung löschen');
admin_topbar(['back' => true]);
?>
<main class="admin" style="max-width:560px">
    <div class="card" style="text-align:center">
        <div style="font-size:3.2rem; margin-bottom:.5rem">🗑️</div>
        <h1 class="page">Diese Veranstaltung löschen?</h1>
        <p style="color:#6b7280; margin:.6rem 0 1.5rem; font-size:1.05rem">
            <strong style="color:#1f2937; font-size:1.15rem"><?= htmlspecialchars($found['name']) ?></strong><br>
            <?= htmlspecialchars($found['date']) ?>
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p class="hint" style="margin-bottom:1.2rem">Das kann nicht rückgängig gemacht werden.</p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="actions" style="justify-content:center">
                <button type="submit" class="btn btn-danger btn-xl">Ja, endgültig löschen</button>
                <a href="/admin/dashboard.php" class="btn btn-secondary btn-xl">Abbrechen</a>
            </div>
        </form>
    </div>
</main>
<?php
admin_html_foot();
