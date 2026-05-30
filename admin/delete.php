<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_login();

$events  = read_events();
$deleteId = $_GET['id'] ?? '';
$found   = null;

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
    $error = 'Löschen fehlgeschlagen. Bitte versuche es erneut.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handgemacht – Veranstaltung löschen</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f3f4f6; color: #1f2937; }
        header { background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 1.2rem; }
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem; cursor: pointer; border: none; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        main { max-width: 500px; margin: 3rem auto; padding: 0 1rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 2rem; text-align: center; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h2 { font-size: 1.1rem; margin-bottom: 0.5rem; }
        p { color: #6b7280; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .actions { display: flex; gap: 0.75rem; justify-content: center; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
<header>
    <h1>🧶 Handgemacht Admin</h1>
    <a href="/admin/dashboard.php" class="btn btn-secondary" style="font-size:0.85rem">← Zurück</a>
</header>
<main>
    <div class="card">
        <div class="icon">🗑️</div>
        <h2>Veranstaltung löschen?</h2>
        <p><strong><?= htmlspecialchars($found['name']) ?></strong><br><?= htmlspecialchars($found['date']) ?></p>
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="actions">
                <button type="submit" class="btn btn-danger">Endgültig löschen</button>
                <a href="/admin/dashboard.php" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
