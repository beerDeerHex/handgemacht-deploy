<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
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
            $success = "\"$delName\" wurde gelöscht.";
        } else {
            $error = "\"$delName\" konnte nicht gelöscht werden.";
        }
    } else {
        $error = 'Ungültige Anfrage.';
    }
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    verify_csrf();
    $files      = $_FILES['images'] ?? [];
    $uploaded   = 0;
    $failMsgs   = [];
    $phpErrMap  = [
        UPLOAD_ERR_INI_SIZE   => 'Datei überschreitet upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'Datei überschreitet MAX_FILE_SIZE im Formular',
        UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE    => 'Keine Datei ausgewählt',
        UPLOAD_ERR_NO_TMP_DIR => 'Kein temporäres Verzeichnis verfügbar',
        UPLOAD_ERR_CANT_WRITE => 'Schreiben auf Disk fehlgeschlagen',
    ];
    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        $origName = $files['name'][$i] ?? "Datei $i";
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $failMsgs[] = "$origName: " . ($phpErrMap[$files['error'][$i]] ?? "PHP-Fehler {$files['error'][$i]}");
            continue;
        }
        $tmpPath = $files['tmp_name'][$i];
        $ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $failMsgs[] = "$origName: Dateityp nicht erlaubt (nur JPG/PNG)";
            continue;
        }
        if ($files['size'][$i] > 20 * 1024 * 1024) {
            $size = round($files['size'][$i] / 1024 / 1024, 1);
            $failMsgs[] = "$origName: Datei zu groß ({$size} MB, max. 20 MB)";
            continue;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $failMsgs[] = "$origName: Ungültiger MIME-Typ ($mime)";
            continue;
        }
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $filename = $safeName . '.' . $ext;
        $binary   = file_get_contents($tmpPath);

        if (upload_image($repoDir, $filename, $binary)) {
            $uploaded++;
        } else {
            $failMsgs[] = "$origName: GitHub API Fehler (Token-Berechtigungen prüfen)";
        }
    }

    if ($uploaded > 0) $success = "$uploaded Bild(er) erfolgreich hochgeladen.";
    if (!empty($failMsgs)) $error = implode('<br>', array_map('htmlspecialchars', $failMsgs));
}

// Load existing images from GitHub
$existingImages = github_list_dir($repoDir);
$imageExts      = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$existingImages = array_values(array_filter($existingImages, function ($f) use ($imageExts) {
    return in_array(strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)), $imageExts, true);
}));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handgemacht – Fotos verwalten</title>
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
        main { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 1.5rem; margin-bottom: 1.5rem; }
        h2 { font-size: 1.05rem; margin-bottom: 1rem; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        /* Image grid */
        .img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 0.75rem; }
        .img-item { position: relative; border-radius: 6px; overflow: hidden; background: #f9fafb; border: 1px solid #e5e7eb; }
        .img-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; cursor: zoom-in; }
        .img-name { font-size: 0.7rem; color: #6b7280; padding: 0.3rem 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .img-del { position: absolute; top: 4px; right: 4px; background: rgba(220,38,38,0.85); color: white; border: none; border-radius: 4px; padding: 2px 6px; font-size: 0.75rem; cursor: pointer; line-height: 1.4; }
        .img-del:hover { background: #b91c1c; }
        .pending-dot { position: absolute; top: 4px; left: 4px; background: #f59e0b; color: white; font-size: 0.65rem; font-weight: 700; padding: 2px 5px; border-radius: 4px; line-height: 1.4; pointer-events: none; }
        /* Lightbox */
        .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 90vw; max-height: 90vh; object-fit: contain; border-radius: 6px; box-shadow: 0 8px 32px rgba(0,0,0,0.5); }
        .lightbox-name { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.6); color: white; font-size: 0.85rem; padding: 0.4rem 0.9rem; border-radius: 20px; white-space: nowrap; }
        .lightbox-pending { position: fixed; top: 1.5rem; left: 50%; transform: translateX(-50%); background: #f59e0b; color: white; font-size: 0.8rem; font-weight: 600; padding: 0.35rem 0.9rem; border-radius: 20px; }
        .lightbox-close { position: fixed; top: 1rem; right: 1rem; color: white; font-size: 2rem; cursor: pointer; line-height: 1; background: none; border: none; }
        .empty { color: #9ca3af; font-size: 0.9rem; padding: 1rem 0; }
        /* Upload zone */
        .drop-zone { border: 2px dashed #d1d5db; border-radius: 8px; padding: 1.5rem; text-align: center; color: #6b7280; font-size: 0.9rem; cursor: pointer; transition: border-color 0.2s; }
        .drop-zone:hover, .drop-zone.drag-over { border-color: #1f2937; color: #1f2937; }
        .drop-zone input[type=file] { display: none; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 0.5rem; margin-top: 0.75rem; }
        .preview-grid img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 4px; }
        .upload-actions { margin-top: 1rem; display: flex; gap: 0.75rem; }
        .hint { font-size: 0.78rem; color: #9ca3af; margin-top: 0.5rem; }
        .count-badge { font-size: 0.8rem; color: #6b7280; font-weight: normal; margin-left: 0.5rem; }
    </style>
</head>
<body>
<header>
    <h1>🧶 Handgemacht Admin</h1>
    <a href="/admin/dashboard.php" class="btn btn-secondary" style="font-size:0.85rem">← Zurück</a>
</header>
<main>
    <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Existing images -->
    <div class="card">
        <h2>
            Vorhandene Fotos – <?= htmlspecialchars($catName) ?>
            <span class="count-badge"><?= count($existingImages) ?> Bilder</span>
        </h2>
        <?php if (empty($existingImages)): ?>
            <p class="empty">Noch keine Bilder in dieser Kategorie.</p>
        <?php else: ?>
            <?php
                $activeBranch = admin_branch_exists() ? GITHUB_ADMIN_BRANCH : GITHUB_BRANCH;
                $pendingNames = github_pending_filenames($repoDir);
                $base         = 'https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/' . $activeBranch . '/';
            ?>
            <div class="img-grid">
            <?php foreach ($existingImages as $img): ?>
                <?php
                    $fullUrl   = $base . $img['path'];
                    $thumbUrl  = $base . thumb_path($img['path']);
                    $isPending = isset($pendingNames[$img['name']]);
                ?>
                <div class="img-item">
                    <img src="<?= htmlspecialchars($thumbUrl) ?>"
                         alt="<?= htmlspecialchars($img['name']) ?>"
                         loading="lazy"
                         onerror="this.src='<?= htmlspecialchars($fullUrl) ?>'"
                         onclick="openLightbox('<?= htmlspecialchars($fullUrl, ENT_QUOTES) ?>','<?= htmlspecialchars($img['name'], ENT_QUOTES) ?>',<?= $isPending ? 'true' : 'false' ?>)">
                    <?php if ($isPending): ?>
                        <span class="pending-dot" title="Noch nicht live">Ausstehend</span>
                    <?php endif; ?>
                    <div class="img-name" title="<?= htmlspecialchars($img['name']) ?>"><?= htmlspecialchars($img['name']) ?></div>
                    <form method="POST" onsubmit="return confirm('\"<?= htmlspecialchars(addslashes($img['name'])) ?>\" wirklich löschen?')">
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
                <p style="margin-top:0.75rem; font-size:0.8rem; color:#92400e">
                    <span style="background:#f59e0b; color:white; border-radius:3px; padding:1px 5px; font-weight:600">Ausstehend</span>
                    = hochgeladen, aber noch nicht live. Deploy starten um sie zu veröffentlichen.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Upload new images -->
    <div class="card">
        <h2>Neue Fotos hochladen</h2>
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
                <p>📷 Hier klicken oder Bilder hierher ziehen</p>
                <p class="hint">JPG, PNG · max. 20 MB pro Bild · mehrere gleichzeitig möglich</p>
            </div>
            <div class="preview-grid" id="previewGrid"></div>
            <div class="upload-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Hochladen</button>
            </div>
        </form>
    </div>
</main>
<script>
function showPreviews(files) {
    const grid = document.getElementById('previewGrid');
    const btn  = document.getElementById('submitBtn');
    grid.innerHTML = '';
    btn.disabled = files.length === 0;
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

// Lightbox
function openLightbox(url, name, isPending) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lbImg').src = url;
    document.getElementById('lbName').textContent = name;
    const badge = document.getElementById('lbPending');
    badge.style.display = isPending ? 'block' : 'none';
    lb.classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lbImg').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" onclick="if(event.target===this) closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">×</button>
    <div id="lbPending" class="lightbox-pending" style="display:none">⏳ Noch nicht live — Deploy ausstehend</div>
    <img id="lbImg" src="" alt="">
    <div class="lightbox-name" id="lbName"></div>
</div>
</body>
</html>
