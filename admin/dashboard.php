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

$events          = read_events();
$pendingChanges  = github_get_pending_changes();
$hasPending      = count($pendingChanges) > 0;
usort($events, fn($a, $b) => strcmp($b['dateSort'], $a['dateSort']));

$now       = date('Y-m-d');
$deployMsg = $_GET['deploy'] ?? '';
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
        header { background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        header h1 { font-size: 1.2rem; }
        .header-right { display: flex; gap: 0.5rem; align-items: center; }
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem; cursor: pointer; border: none; }
        .btn-primary  { background: #1f2937; color: white; }
        .btn-primary:hover  { background: #374151; }
        .btn-deploy   { background: #2563eb; color: white; }
        .btn-deploy:hover   { background: #1d4ed8; }
        .btn-deploy:disabled { background: #93c5fd; cursor: not-allowed; }
        .btn-danger   { background: #dc2626; color: white; }
        .btn-danger:hover   { background: #b91c1c; }
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
        .badge-future  { background: #dcfce7; color: #15803d; }
        .badge-past    { background: #f3f4f6; color: #6b7280; }
        .actions { display: flex; gap: 0.5rem; }
        .alert { border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-info    { background: #dbeafe; border: 1px solid #93c5fd; color: #1e40af; }
        .alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
        .alert-warn    { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .alert-error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        /* Pending changes panel */
        .pending-panel { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; border-left: 4px solid #f59e0b; }
        .pending-panel h3 { font-size: 0.95rem; margin-bottom: 0.75rem; color: #92400e; }
        .pending-panel ul { list-style: none; padding: 0; }
        .pending-panel li { display: flex; justify-content: space-between; align-items: baseline; padding: 0.3rem 0; border-bottom: 1px solid #fef3c7; font-size: 0.875rem; gap: 1rem; }
        .pending-panel li:last-child { border-bottom: none; }
        .pending-panel .change-msg  { color: #1f2937; }
        .pending-panel .change-date { color: #9ca3af; font-size: 0.8rem; white-space: nowrap; }
        .pending-panel .deploy-row  { margin-top: 1rem; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .no-pending { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 0.9rem 1.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; color: #6b7280; display: flex; align-items: center; gap: 0.5rem; }
        /* Pipeline status bar */
        .pipeline { display: flex; align-items: center; gap: 0.75rem; background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 0.85rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .pipeline-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .dot-none      { background: #d1d5db; }
        .dot-queued    { background: #f59e0b; }
        .dot-progress  { background: #3b82f6; animation: pulse 1.2s ease-in-out infinite; }
        .dot-success   { background: #22c55e; }
        .dot-failure   { background: #ef4444; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.35} }
        .pipeline-label { font-weight: 600; color: #1f2937; }
        .pipeline-time  { color: #9ca3af; margin-left: auto; font-size: 0.8rem; }
        .pipeline a     { color: #2563eb; font-size: 0.8rem; text-decoration: none; }
        .pipeline a:hover { text-decoration: underline; }
        /* Photo section */
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
    <div class="header-right">
        <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="logout">
            <button class="btn btn-secondary btn-sm" type="submit">Abmelden</button>
        </form>
    </div>
</header>
<main>

    <!-- Live pipeline status -->
    <div class="pipeline" id="pipeline">
        <div class="pipeline-dot dot-none" id="pDot"></div>
        <span class="pipeline-label" id="pLabel">Pipeline-Status wird geladen…</span>
        <span class="pipeline-time"  id="pTime"></span>
        <a id="pLink" href="#" target="_blank" style="display:none">GitHub →</a>
    </div>

    <?php if ($deployMsg === 'ok'): ?>
        <div class="alert alert-info">
            🚀 <strong>Deploy gestartet!</strong> GitHub baut die Website neu — das dauert ca. <strong>5 Minuten</strong>.
        </div>
    <?php elseif ($deployMsg === 'fail'): ?>
        <div class="alert alert-error">
            Deploy fehlgeschlagen. Prüfe ob der GitHub-Token die Berechtigung <strong>Actions: Read and write</strong> und <strong>Contents: Read and write</strong> hat.
        </div>
    <?php elseif ($deployMsg === 'nothing'): ?>
        <div class="alert alert-warn">
            Keine ausstehenden Änderungen — nichts zu deployen.
        </div>
    <?php endif; ?>

    <?php if ($hasPending): ?>
        <div class="pending-panel">
            <h3>⏳ Ausstehende Änderungen (noch nicht live)</h3>
            <ul>
                <?php foreach ($pendingChanges as $change): ?>
                    <li>
                        <span class="change-msg"><?= htmlspecialchars($change['message']) ?></span>
                        <span class="change-date"><?= htmlspecialchars($change['date']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="deploy-row">
                <form method="POST" action="/admin/deploy.php"
                      onsubmit="return confirm('Alle <?= count($pendingChanges) ?> Änderung(en) jetzt live schalten?')">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button class="btn btn-deploy" type="submit">🚀 Jetzt deployen</button>
                </form>
                <span style="font-size:0.8rem; color:#92400e">Website wird nach dem Deploy in ca. 5 Min. aktualisiert.</span>
            </div>
        </div>
    <?php else: ?>
        <div class="no-pending">
            ✅ Alles deployed — keine ausstehenden Änderungen.
        </div>
    <?php endif; ?>

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
                    <span class="badge <?= $isPast ? 'badge-past' : 'badge-future' ?>">
                        <?= $isPast ? 'Vergangen' : 'Bevorstehend' ?>
                    </span>
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
        <h2>Fotos verwalten</h2>
        <p>Fotos hochladen oder löschen — wähle eine Kategorie.</p>
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
<script>
const STATES = {
    none:       { dot: 'dot-none',     label: 'Noch kein Deploy durchgeführt' },
    queued:     { dot: 'dot-queued',   label: '⏳ Deploy in der Warteschlange…' },
    in_progress:{ dot: 'dot-progress', label: '🔄 Wird gerade deployed…' },
    success:    { dot: 'dot-success',  label: '✅ Erfolgreich deployed' },
    failure:    { dot: 'dot-failure',  label: '❌ Deploy fehlgeschlagen' },
    cancelled:  { dot: 'dot-none',     label: '⚠️ Deploy abgebrochen' },
};

let pollTimer = null;

async function fetchStatus() {
    try {
        const res  = await fetch('/admin/status.php');
        const data = await res.json();

        const dot   = document.getElementById('pDot');
        const label = document.getElementById('pLabel');
        const time  = document.getElementById('pTime');
        const link  = document.getElementById('pLink');

        // Determine display state
        let key = data.status === 'completed' ? (data.conclusion ?? 'none') : (data.status ?? 'none');
        const state = STATES[key] ?? STATES.none;

        dot.className   = 'pipeline-dot ' + state.dot;
        label.textContent = state.label;
        time.textContent  = data.updated_at ? 'Zuletzt: ' + data.updated_at : '';

        if (data.run_url) {
            link.href         = data.run_url;
            link.style.display = 'inline';
        }

        // Keep polling while active; slow down once done
        clearTimeout(pollTimer);
        if (data.status === 'queued' || data.status === 'in_progress') {
            pollTimer = setTimeout(fetchStatus, 10000); // every 10s while running
        } else {
            pollTimer = setTimeout(fetchStatus, 60000); // every 60s when idle
        }
    } catch (e) {
        setTimeout(fetchStatus, 30000);
    }
}

fetchStatus();
</script>
</body>
</html>
