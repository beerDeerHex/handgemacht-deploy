<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_login();

$events = read_events();
$editing = false;
$event = [
    'id'              => '',
    'name'            => '',
    'date'            => '',
    'dateSort'        => '',
    'image'           => '',
    'fullDescription' => '',
];

// Load event for editing
$editId = $_GET['id'] ?? '';
if ($editId) {
    foreach ($events as $e) {
        if ($e['id'] === $editId) {
            $event = $e;
            $editing = true;
            break;
        }
    }
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name        = trim($_POST['name'] ?? '');
    $date        = trim($_POST['date'] ?? '');
    $dateSort    = trim($_POST['dateSort'] ?? '');
    $image       = trim($_POST['image'] ?? '');
    $description = trim($_POST['fullDescription'] ?? '');
    $postId      = trim($_POST['event_id'] ?? '');

    if (!$name || !$date || !$dateSort) {
        $error = 'Bitte alle Pflichtfelder ausfüllen.';
    } else {
        // Handle image upload
        if (!empty($_FILES['imageFile']['tmp_name'])) {
            $file     = $_FILES['imageFile'];
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed, true)) {
                $error = 'Nur JPG und PNG Dateien sind erlaubt.';
            } elseif ($file['size'] > 20 * 1024 * 1024) {
                $error = 'Die Datei ist zu groß (max. 20 MB).';
            } else {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mime     = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                    $error = 'Ungültiger Dateityp.';
                } else {
                    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                    $filename = $safeName . '.' . $ext;
                    $binary   = file_get_contents($file['tmp_name']);
                    if (upload_image('src/images/Veranstaltungen', $filename, $binary)) {
                        $image = $safeName; // stored without extension
                    } else {
                        $error = 'Bild konnte nicht hochgeladen werden.';
                    }
                }
            }
        }

        if (!$error) {
            $newEvent = [
                'id'              => $postId ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . substr($dateSort, 0, 4),
                'name'            => $name,
                'date'            => $date,
                'dateSort'        => $dateSort,
                'image'           => $image,
                'fullDescription' => $description,
            ];

            if ($editing) {
                $newEvent['id'] = $postId; // keep original id
                foreach ($events as &$e) {
                    if ($e['id'] === $postId) { $e = $newEvent; break; }
                }
                unset($e);
                $commitMsg = "Veranstaltung aktualisiert: {$name}";
            } else {
                // Ensure unique id
                $ids = array_column($events, 'id');
                $base = $newEvent['id'];
                $i = 2;
                while (in_array($newEvent['id'], $ids, true)) {
                    $newEvent['id'] = $base . '-' . $i++;
                }
                $events[] = $newEvent;
                $commitMsg = "Neue Veranstaltung: {$name}";
            }

            if (write_events($events, $commitMsg)) {
                $success = $editing
                    ? 'Veranstaltung wurde gespeichert. Die Website wird in ca. 1–2 Minuten aktualisiert.'
                    : 'Neue Veranstaltung wurde hinzugefügt. Die Website wird in ca. 1–2 Minuten aktualisiert.';
                $editing = true;
                $event   = $newEvent;
            } else {
                $error = 'Speichern fehlgeschlagen. Bitte GitHub-Token und Einstellungen prüfen.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handgemacht – Veranstaltung <?= $editing ? 'bearbeiten' : 'hinzufügen' ?></title>
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
        h2 { font-size: 1.1rem; margin-bottom: 1.5rem; }
        .field { margin-bottom: 1.2rem; }
        label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.3rem; }
        .hint { font-size: 0.8rem; color: #6b7280; margin-bottom: 0.4rem; }
        input[type=text], input[type=date], textarea {
            width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;
        }
        textarea { resize: vertical; min-height: 120px; }
        input[type=file] { padding: 0.4rem 0; }
        .image-preview { margin-top: 0.5rem; max-height: 150px; max-width: 100%; border-radius: 4px; display: none; }
        .current-image { font-size: 0.85rem; color: #6b7280; margin-top: 0.3rem; }
        .actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .required { color: #dc2626; }
    </style>
</head>
<body>
<header>
    <h1>🧶 Handgemacht Admin</h1>
    <a href="/admin/dashboard.php" class="btn btn-secondary" style="font-size:0.85rem">← Zurück</a>
</header>
<main>
    <div class="card">
        <h2><?= $editing ? 'Veranstaltung bearbeiten' : 'Neue Veranstaltung' ?></h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['id']) ?>">

            <div class="field">
                <label for="name">Name der Veranstaltung <span class="required">*</span></label>
                <input type="text" id="name" name="name" required
                       value="<?= htmlspecialchars($event['name']) ?>"
                       placeholder="z.B. Kreativmarkt Garsten 2026">
            </div>

            <div class="field">
                <label for="date">Datum (Anzeigetext) <span class="required">*</span></label>
                <p class="hint">Freier Text, der auf der Website angezeigt wird. Z.B. "22. - 23. März 2026"</p>
                <input type="text" id="date" name="date" required
                       value="<?= htmlspecialchars($event['date']) ?>"
                       placeholder="22. - 23. März 2026">
            </div>

            <div class="field">
                <label for="dateSort">Datum (für Sortierung) <span class="required">*</span></label>
                <p class="hint">Wird für die Sortierung und Zukunft/Vergangenheit-Erkennung verwendet.</p>
                <input type="date" id="dateSort" name="dateSort" required
                       value="<?= htmlspecialchars($event['dateSort']) ?>">
            </div>

            <div class="field">
                <label for="fullDescription">Beschreibung</label>
                <textarea id="fullDescription" name="fullDescription"
                          placeholder="Beschreibe die Veranstaltung..."><?= htmlspecialchars($event['fullDescription'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label for="imageFile">Bild hochladen</label>
                <p class="hint">JPG oder PNG, max. 20 MB. Leer lassen, um das bestehende Bild beizubehalten.</p>
                <input type="file" id="imageFile" name="imageFile" accept="image/jpeg,image/png"
                       onchange="previewImage(this)">
                <img id="preview" class="image-preview" alt="Vorschau">
                <?php if ($event['image']): ?>
                    <p class="current-image">Aktuelles Bild: <strong><?= htmlspecialchars($event['image']) ?></strong></p>
                <?php endif; ?>
                <input type="hidden" name="image" value="<?= htmlspecialchars($event['image']) ?>">
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="/admin/dashboard.php" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</main>
<script>
function previewImage(input) {
    const preview = document.getElementById('preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
