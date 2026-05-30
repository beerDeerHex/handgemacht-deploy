<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/layout.php';
require_login();

$categories = [
    'Taschen'         => 'Taschen',
    'Rucksaecke'      => 'Rucksäcke',
    'Decken'          => 'Decken',
    'Knoepfe'         => 'Knöpfe',
    'Verschiedenes'   => 'Verschiedenes',
    'Veranstaltungen' => 'Veranstaltungsbilder',
];

$cat     = $_GET['cat'] ?? '';
$catName = $categories[$cat] ?? '';

if (!$catName) {
    header('Location: /admin/dashboard.php');
    exit;
}

$isEvent = ($cat === 'Veranstaltungen');
$repoDir = $isEvent
    ? 'src/images/Veranstaltungen'
    : 'src/images/Produkte/' . $cat;

$error   = '';
$success = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $delPath = $_POST['file_path'] ?? '';
    $delSha  = $_POST['file_sha']  ?? '';
    $delName = basename($delPath);
    // Validate path stays within expected directory
    if ($delSha && strpos($delPath, $repoDir) === 0) {
        if (delete_image($delPath, $delSha, $delName)) {
            $success = '„' . $delName . '" wurde gelöscht.';
        } else {
            $error = '„' . $delName . '" konnte nicht gelöscht werden. Bitte versuche es erneut.';
        }
    } else {
        $error = 'Etwas ist schiefgelaufen. Bitte lade die Seite neu und versuche es erneut.';
    }
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    verify_csrf();
    $files      = $_FILES['images'] ?? [];
    $uploaded   = 0;
    $failMsgs   = [];
    $phpErrMap  = [
        UPLOAD_ERR_INI_SIZE   => 'Datei zu groß für den Server',
        UPLOAD_ERR_FORM_SIZE  => 'Datei zu groß',
        UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE    => 'Keine Datei ausgewählt',
        UPLOAD_ERR_NO_TMP_DIR => 'Server-Fehler (kein temporäres Verzeichnis)',
        UPLOAD_ERR_CANT_WRITE => 'Server-Fehler beim Speichern',
    ];
    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        $origName = $files['name'][$i] ?? "Datei $i";
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $failMsgs[] = "$origName: " . ($phpErrMap[$files['error'][$i]] ?? "Fehler {$files['error'][$i]}");
            continue;
        }
        $tmpPath = $files['tmp_name'][$i];
        $ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $failMsgs[] = "$origName: nur JPG- und PNG-Bilder erlaubt";
            continue;
        }
        if ($files['size'][$i] > 20 * 1024 * 1024) {
            $size = round($files['size'][$i] / 1024 / 1024, 1);
            $failMsgs[] = "$origName: zu groß ({$size} MB, höchstens 20 MB)";
            continue;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $failMsgs[] = "$origName: scheint kein gültiges Bild zu sein";
            continue;
        }
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $filename = $safeName . '.' . $ext;
        $binary   = file_get_contents($tmpPath);

        if (upload_image($repoDir, $filename, $binary)) {
            $uploaded++;
        } else {
            $failMsgs[] = "$origName: konnte nicht hochgeladen werden (bitte erneut versuchen)";
        }
    }

    if ($uploaded > 0) {
        $word    = $uploaded === 1 ? 'Foto wurde' : 'Fotos wurden';
        $success = "$uploaded $word hochgeladen.";
    }
    if (!empty($failMsgs)) $error = implode('<br>', array_map('htmlspecialchars', $failMsgs));
}

// Load existing images from GitHub
$existingImages = github_list_dir($repoDir);
$imageExts      = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$existingImages = array_values(array_filter($existingImages, function ($f) use ($imageExts) {
    return in_array(strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)), $imageExts, true);
}));

$pendingChanges = github_get_pending_changes();
$activeBranch   = admin_branch_exists() ? GITHUB_ADMIN_BRANCH : GITHUB_BRANCH;
$pendingNames   = github_pending_filenames($repoDir);
$rawBase        = 'https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/' . $activeBranch . '/';

admin_html_head('Handgemacht – Fotos: ' . $catName);
admin_topbar(['back' => true]);
?>
<main class="admin">
    <h1 class="page">📷 Fotos: <?= htmlspecialchars($catName) ?></h1>
    <p class="section-sub">Hier kannst du Fotos ansehen, neue hochladen oder vorhandene löschen.</p>

    <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php render_pending_banner($pendingChanges); ?>

    <!-- Existing images -->
    <div class="card">
        <h2 class="section-title">
            Vorhandene Fotos
            <span class="count-badge"><?= count($existingImages) ?> Bild(er)</span>
        </h2>
        <?php if (empty($existingImages)): ?>
            <p class="empty">Noch keine Fotos in dieser Kategorie. Lade unten welche hoch.</p>
        <?php else: ?>
            <div class="img-grid">
            <?php foreach ($existingImages as $img): ?>
                <?php
                    $fullUrl   = $rawBase . $img['path'];
                    $thumbUrl  = $rawBase . thumb_path($img['path']);
                    $isPending = isset($pendingNames[$img['name']]);
                ?>
                <div class="img-item">
                    <img src="<?= htmlspecialchars($thumbUrl) ?>"
                         alt="<?= htmlspecialchars($img['name']) ?>"
                         loading="lazy"
                         onerror="this.onerror=null; this.src='<?= htmlspecialchars($fullUrl, ENT_QUOTES) ?>'"
                         onclick="openLightbox('<?= htmlspecialchars($fullUrl, ENT_QUOTES) ?>','<?= htmlspecialchars($img['name'], ENT_QUOTES) ?>',<?= $isPending ? 'true' : 'false' ?>)">
                    <?php if ($isPending): ?>
                        <span class="pending-dot" title="Noch nicht auf der Website">Ausstehend</span>
                    <?php endif; ?>
                    <div class="img-name" title="<?= htmlspecialchars($img['name']) ?>"><?= htmlspecialchars($img['name']) ?></div>
                    <form method="POST" onsubmit="return confirm('„<?= htmlspecialchars(addslashes($img['name'])) ?>" wirklich löschen?')">
                        <input type="hidden" name="csrf_token"  value="<?= csrf_token() ?>">
                        <input type="hidden" name="action"      value="delete">
                        <input type="hidden" name="file_path"   value="<?= htmlspecialchars($img['path']) ?>">
                        <input type="hidden" name="file_sha"    value="<?= htmlspecialchars($img['sha']) ?>">
                        <button type="submit" class="img-del" title="Löschen">✕</button>
                    </form>
                </div>
            <?php endforeach; ?>
            </div>
            <?php if (!empty($pendingNames)): ?>
                <p style="margin-top:.9rem; font-size:.95rem; color:#92400e">
                    <span class="pending-dot" style="position:static; display:inline-block">Ausstehend</span>
                    bedeutet: hochgeladen, aber noch nicht auf der Website. Veröffentliche oben, damit alle es sehen.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Upload new images -->
    <div class="card" style="margin-top:1.5rem">
        <h2 class="section-title">Neue Fotos hochladen</h2>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="upload">
            <div class="drop-zone" onclick="document.getElementById('fileInput').click()"
                 ondragover="event.preventDefault(); this.classList.add('drag-over')"
                 ondragleave="this.classList.remove('drag-over')"
                 ondrop="handleDrop(event)">
                <input type="file" id="fileInput" name="images[]" multiple
                       accept="image/jpeg,image/png"
                       onchange="showPreviews(this.files)">
                <p class="big">📷 Hier klicken, um Fotos auszuwählen</p>
                <p class="hint">oder Bilder hierher ziehen · JPG oder PNG · mehrere gleichzeitig möglich</p>
            </div>
            <div class="preview-grid" id="previewGrid"></div>
            <div class="actions">
                <button type="submit" class="btn btn-primary btn-xl" id="submitBtn" disabled>Hochladen</button>
            </div>
        </form>
    </div>
</main>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" onclick="if(event.target===this) closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">×</button>
    <div id="lbPending" class="lightbox-pending" style="display:none">⏳ Noch nicht auf der Website</div>
    <img id="lbImg" src="" alt="">
    <div class="lightbox-name" id="lbName"></div>
</div>
<?php
admin_html_foot(<<<'JS'
function showPreviews(files) {
    const grid = document.getElementById('previewGrid');
    const btn  = document.getElementById('submitBtn');
    grid.innerHTML = '';
    btn.disabled = files.length === 0;
    btn.textContent = files.length > 1 ? (files.length + ' Fotos hochladen') : 'Hochladen';
    Array.from(files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            grid.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}
function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    const input = document.getElementById('fileInput');
    const dt    = new DataTransfer();
    Array.from(event.dataTransfer.files).forEach(f => dt.items.add(f));
    input.files = dt.files;
    showPreviews(input.files);
}
function openLightbox(url, name, isPending) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lbImg').src = url;
    document.getElementById('lbName').textContent = name;
    document.getElementById('lbPending').style.display = isPending ? 'block' : 'none';
    lb.classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lbImg').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
JS);
