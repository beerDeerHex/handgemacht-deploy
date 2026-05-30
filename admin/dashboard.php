<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    verify_csrf();
    logout();
    header('Location: /admin/index.php');
    exit;
}

$events         = read_events();
$pendingChanges = github_get_pending_changes();
usort($events, fn($a, $b) => strcmp($b['dateSort'], $a['dateSort']));

$now       = date('Y-m-d');
$deployMsg = $_GET['deploy'] ?? '';

// Base URL for thumbnails. Thumbnails are always JPEGs at a predictable path,
// so we can build the URL from the image name alone (no extension lookup needed).
$activeBranch = admin_branch_exists() ? GITHUB_ADMIN_BRANCH : GITHUB_BRANCH;
$rawBase      = 'https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/' . $activeBranch . '/';

$categories = [
    'Taschen'         => ['Taschen', '👜'],
    'Rucksaecke'      => ['Rucksäcke', '🎒'],
    'Decken'          => ['Decken', '🛏️'],
    'Knoepfe'         => ['Knöpfe', '🔘'],
    'Verschiedenes'   => ['Verschiedenes', '✨'],
    'Veranstaltungen' => ['Veranstaltungsbilder', '📷'],
];

admin_html_head('Handgemacht – Übersicht');
admin_topbar(['logout' => true]);
?>
<main class="admin">

    <h1 class="page">Willkommen! 👋</h1>
    <p class="section-sub">Hier kannst du Veranstaltungen und Fotos für deine Website verwalten.</p>

    <!-- Result of a publish click -->
    <?php if ($deployMsg === 'ok'): ?>
        <div class="alert alert-success">
            🚀 <strong>Super!</strong> Deine Änderungen werden jetzt veröffentlicht.
            In ca. 5 Minuten ist die Website aktualisiert.
        </div>
    <?php elseif ($deployMsg === 'fail'): ?>
        <div class="alert alert-error">
            Das hat leider nicht geklappt. Bitte versuche es in ein paar Minuten erneut.
            Wenn es weiterhin nicht funktioniert, melde dich bei <?= htmlspecialchars(ADMIN_SUPPORT_NAME) ?>.
        </div>
    <?php elseif ($deployMsg === 'nothing'): ?>
        <div class="alert alert-info">
            Es gab nichts zu veröffentlichen — alles ist bereits auf der Website.
        </div>
    <?php endif; ?>

    <!-- Live website status -->
    <div class="statusbar" id="statusbar">
        <div class="status-dot dot-none" id="pDot"></div>
        <span class="status-label" id="pLabel">Status wird geladen…</span>
        <span class="status-time" id="pTime"></span>
        <a class="status-help" id="pLink" href="#" target="_blank" rel="noopener" style="display:none">Details ansehen →</a>
    </div>

    <!-- Unpublished-changes reminder (always visible) -->
    <?php render_pending_banner($pendingChanges); ?>

    <!-- Events -->
    <section class="stack">
        <div class="toolbar">
            <h2 class="section-title">📅 Veranstaltungen</h2>
            <a href="/admin/event-form.php" class="btn btn-primary">+ Neue Veranstaltung</a>
        </div>

        <?php if (empty($events)): ?>
            <div class="card"><p class="empty">Noch keine Veranstaltungen. Klicke auf „+ Neue Veranstaltung", um die erste anzulegen.</p></div>
        <?php else: ?>
            <div class="event-list">
            <?php foreach ($events as $event): ?>
                <?php
                    $isPast   = ($event['dateSort'] < $now);
                    $thumbUrl = $event['image'] ? $rawBase . 'src/images/_thumbs/Veranstaltungen/' . rawurlencode($event['image']) . '.jpg' : '';
                ?>
                <div class="event-row">
                    <?php if ($thumbUrl): ?>
                        <img class="event-thumb" src="<?= htmlspecialchars($thumbUrl) ?>" alt="" loading="lazy"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="event-thumb-empty" style="display:none">🖼️</div>
                    <?php else: ?>
                        <div class="event-thumb-empty">📅</div>
                    <?php endif; ?>

                    <div class="event-main">
                        <div class="event-name"><?= htmlspecialchars($event['name']) ?></div>
                        <div class="event-meta">
                            <span><?= htmlspecialchars($event['date']) ?></span>
                            <span class="badge <?= $isPast ? 'badge-past' : 'badge-future' ?>">
                                <?= $isPast ? 'Vergangen' : 'Bevorstehend' ?>
                            </span>
                        </div>
                    </div>

                    <div class="event-actions">
                        <a href="/admin/event-form.php?id=<?= urlencode($event['id']) ?>" class="btn btn-secondary">Bearbeiten</a>
                        <a href="/admin/delete.php?id=<?= urlencode($event['id']) ?>" class="btn btn-danger">Löschen</a>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Photos -->
    <section class="stack" style="margin-top:2.5rem">
        <h2 class="section-title">📷 Fotos</h2>
        <p class="section-sub">Wähle eine Kategorie, um Fotos anzusehen, hochzuladen oder zu löschen.</p>
        <div class="photo-grid">
            <?php foreach ($categories as $key => [$label, $icon]): ?>
                <a href="/admin/upload-image.php?cat=<?= urlencode($key) ?>" class="photo-btn">
                    <span class="ico"><?= $icon ?></span>
                    <span><?= htmlspecialchars($label) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

</main>
<?php
admin_html_foot(<<<'JS'
const STATES = {
    none:        { dot: 'dot-none',     label: 'Noch keine Veröffentlichung' },
    queued:      { dot: 'dot-queued',   label: '⏳ Veröffentlichung wird vorbereitet…' },
    in_progress: { dot: 'dot-progress', label: '🔄 Die Website wird gerade aktualisiert…' },
    success:     { dot: 'dot-success',  label: '✅ Die Website ist auf dem neuesten Stand' },
    failure:     { dot: 'dot-failure',  label: '❌ Bei der letzten Veröffentlichung gab es ein Problem' },
    cancelled:   { dot: 'dot-none',     label: '⚠️ Veröffentlichung wurde abgebrochen' },
};
let pollTimer = null;
async function fetchStatus() {
    try {
        const res  = await fetch('/admin/status.php');
        const data = await res.json();
        const key   = data.status === 'completed' ? (data.conclusion ?? 'none') : (data.status ?? 'none');
        const state = STATES[key] ?? STATES.none;
        document.getElementById('pDot').className   = 'status-dot ' + state.dot;
        document.getElementById('pLabel').textContent = state.label;
        document.getElementById('pTime').textContent  = data.updated_at ? 'Zuletzt: ' + data.updated_at : '';
        const link = document.getElementById('pLink');
        // Only offer the technical link when something went wrong.
        if (key === 'failure' && data.run_url) {
            link.href = data.run_url;
            link.style.display = 'inline';
        } else {
            link.style.display = 'none';
        }
        clearTimeout(pollTimer);
        const active = (data.status === 'queued' || data.status === 'in_progress');
        pollTimer = setTimeout(fetchStatus, active ? 10000 : 60000);
    } catch (e) {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(fetchStatus, 30000);
    }
}
fetchStatus();
JS);
