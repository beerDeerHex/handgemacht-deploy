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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $files = $_FILES['images'] ?? [];
    $uploaded = 0;
    $failed   = 0;

    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 0;
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) { $failed++; continue; }

        $tmpPath  = $files['tmp_name'][$i];
        $origName = $files['name'][$i];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) { $failed++; continue; }
        if ($files['size'][$i] > 20 * 1024 * 1024) { $failed++; continue; }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) { $failed++; continue; }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $filename = $safeName . '.' . $ext;
        $binary   = file_get_contents($tmpPath);

        if (upload_image($repoDir, $filename, $binary)) {
            $uploaded++;
        } else {
            $failed++;
        }
    }

    if ($uploaded > 0) {
        $success = "$uploaded Bild(er) erfolgreich hochgeladen. Die Website wird in ca. 1–2 Minuten aktualisiert.";
    }
    if ($failed > 0) {
        $error = "$failed Bild(er) konnten nicht hochgeladen werden.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handgemacht – Bilder hochladen</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f3f4f6; color: #1f2937; }
        header { background: white; border-bottom: 1px solid #e5e7eb; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 1.2rem; }
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem; cursor: pointer; border: none; }
        .btn-primary { background: #1f2937; color: white; }
        .btn-primary:hover { background: #374151; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        main { max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 1.5rem; }
        h2 { font-size: 1.1rem; margin-bottom: 0.5rem; }
        .sub { color: #6b7280; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .drop-zone { border: 2px dashed #d1d5db; border-radius: 8px; padding: 2rem; text-align: center; color: #6b7280; font-size: 0.9rem; cursor: pointer; transition: border-color 0.2s; }
        .drop-zone:hover, .drop-zone.drag-over { border-color: #1f2937; color: #1f2937; }
        .drop-zone input[type=file] { display: none; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.5rem; margin-top: 1rem; }
        .preview-grid img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 4px; }
        .actions { margin-top: 1.5rem; display: flex; gap: 0.75rem; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .hint { font-size: 0.8rem; color: #9ca3af; margin-top: 0.75rem; }
    </style>
</head>
<body>
<header>
    <h1>🧶 Handgemacht Admin</h1>
    <a href="/admin/dashboard.php" class="btn btn-secondary" style="font-size:0.85rem">← Zurück</a>
</header>
<main>
    <div class="card">
        <h2>Bilder hochladen – <?= htmlspecialchars($catName) ?></h2>
        <p class="sub">Mehrere Bilder gleichzeitig auswählen möglich. JPG oder PNG, max. 20 MB pro Bild.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="drop-zone" onclick="document.getElementById('fileInput').click()"
                 ondragover="event.preventDefault(); this.classList.add('drag-over')"
                 ondragleave="this.classList.remove('drag-over')"
                 ondrop="handleDrop(event)">
                <input type="file" id="fileInput" name="images[]" multiple
                       accept="image/jpeg,image/png"
                       onchange="showPreviews(this.files)">
                <p>📷 Hier klicken oder Bilder hierher ziehen</p>
                <p class="hint">JPG, PNG · max. 20 MB pro Bild</p>
            </div>
            <div class="preview-grid" id="previewGrid"></div>
            <div class="actions">
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Hochladen</button>
                <a href="/admin/dashboard.php" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</main>
<script>
function showPreviews(files) {
    const grid = document.getElementById('previewGrid');
    const btn  = document.getElementById('submitBtn');
    grid.innerHTML = '';
    if (files.length > 0) {
        btn.disabled = false;
        Array.from(files).forEach(file => {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement('img');
                img.src = e.target.result;
                grid.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    } else {
        btn.disabled = true;
    }
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
</script>
</body>
</html>
