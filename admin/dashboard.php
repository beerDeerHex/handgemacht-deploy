<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    verify_csrf();
    logout();
    header('Location: /admin/index.php');
    exit;
}

$events = read_events();
usort($events, fn($a, $b) => strcmp($b['dateSort'], $a['dateSort']));

$now = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handgemacht – Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f3f4f6; color: #1f2937; }
        header { background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 1.2rem; }
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem; cursor: pointer; border: none; }
        .btn-primary { background: #1f2937; color: white; }
        .btn-primary:hover { background: #374151; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
        main { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem; }
        table { width: 100%; background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border-collapse: collapse; overflow: hidden; }
        th { background: #f9fafb; text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
        td { padding: 0.75rem 1rem; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .badge-future { background: #dcfce7; color: #15803d; }
        .badge-past { background: #f3f4f6; color: #6b7280; }
        .actions { display: flex; gap: 0.5rem; }
        .notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #92400e; }
        .upload-section { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 1.5rem; margin-top: 2rem; }
        .upload-section h2 { font-size: 1rem; margin-bottom: 1rem; }
        .upload-section p { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.75rem; }
        .category-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.5rem; }
        .category-btn { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; text-align: center; font-size: 0.85rem; text-decoration: none; color: #374151; background: #f9fafb; }
        .category-btn:hover { background: #e5e7eb; }
    </style>
</head>
<body>
<header>
    <h1>🧶 Handgemacht Admin</h1>
    <form method="POST" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="logout">
        <button class="btn btn-secondary btn-sm" type="submit">Abmelden</button>
    </form>
</header>
<main>
    <div class="notice">
        Nach dem Speichern dauert es ca. <strong>1–2 Minuten</strong>, bis die Änderungen auf der Website sichtbar sind.
    </div>

    <div class="toolbar">
        <h2 style="font-size:1.1rem">Veranstaltungen</h2>
        <a href="/admin/event-form.php" class="btn btn-primary">+ Neue Veranstaltung</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Datum</th>
                <th>Status</th>
                <th>Bild</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <?php $isPast = ($event['dateSort'] < $now); ?>
            <tr>
                <td><strong><?= htmlspecialchars($event['name']) ?></strong></td>
                <td style="font-size:0.875rem; color:#6b7280"><?= htmlspecialchars($event['date']) ?></td>
                <td>
                    <?php if ($isPast): ?>
                        <span class="badge badge-past">Vergangen</span>
                    <?php else: ?>
                        <span class="badge badge-future">Bevorstehend</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.8rem; color:#6b7280">
                    <?= $event['image'] ? htmlspecialchars($event['image']) : '—' ?>
                </td>
                <td>
                    <div class="actions">
                        <a href="/admin/event-form.php?id=<?= urlencode($event['id']) ?>" class="btn btn-secondary btn-sm">Bearbeiten</a>
                        <a href="/admin/delete.php?id=<?= urlencode($event['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Veranstaltung wirklich löschen?')">Löschen</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="upload-section">
        <h2>Produktfotos hochladen</h2>
        <p>Wähle eine Kategorie, um neue Produktfotos hinzuzufügen. Die Fotos erscheinen automatisch auf der Website.</p>
        <div class="category-grid">
            <a href="/admin/upload-image.php?cat=Taschen" class="category-btn">Taschen</a>
            <a href="/admin/upload-image.php?cat=Rucksaecke" class="category-btn">Rucksäcke</a>
            <a href="/admin/upload-image.php?cat=Decken" class="category-btn">Decken</a>
            <a href="/admin/upload-image.php?cat=Knoepfe" class="category-btn">Knöpfe</a>
            <a href="/admin/upload-image.php?cat=Verschiedenes" class="category-btn">Verschiedenes</a>
            <a href="/admin/upload-image.php?cat=Veranstaltungen" class="category-btn">Veranstaltungsbilder</a>
        </div>
    </div>
</main>
</body>
</html>
