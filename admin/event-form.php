<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/layout.php';
require_login();

$events  = read_events();
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
        $error = 'Bitte fülle die Felder mit einem Sternchen (*) aus: Name, Datum und angezeigter Text.';
    } else {
        // Handle image upload
        if (!empty($_FILES['imageFile']['tmp_name'])) {
            $file     = $_FILES['imageFile'];
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed, true)) {
                $error = 'Bitte nur JPG- oder PNG-Bilder hochladen.';
            } elseif ($file['size'] > 20 * 1024 * 1024) {
                $error = 'Das Bild ist zu groß (höchstens 20 MB).';
            } else {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mime     = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                    $error = 'Diese Datei scheint kein gültiges Bild zu sein.';
                } else {
                    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                    $filename = $safeName . '.' . $ext;
                    $binary   = file_get_contents($file['tmp_name']);
                    if (upload_image('src/images/Veranstaltungen', $filename, $binary)) {
                        $image = $safeName; // stored without extension
                    } else {
                        $error = 'Das Bild konnte nicht hochgeladen werden. Bitte versuche es erneut.';
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
                    ? 'Die Veranstaltung wurde gespeichert.'
                    : 'Die neue Veranstaltung wurde angelegt.';
                $editing = true;
                $event   = $newEvent;
            } else {
                $error = 'Speichern hat leider nicht geklappt. Bitte versuche es erneut. '
                       . 'Wenn es weiterhin nicht funktioniert, melde dich bei ' . ADMIN_SUPPORT_NAME . '.';
            }
        }
    }
}

// Reflects the change we may have just saved.
$pendingChanges = github_get_pending_changes();

admin_html_head('Handgemacht – Veranstaltung ' . ($editing ? 'bearbeiten' : 'hinzufügen'));
admin_topbar(['back' => true]);
?>
<main class="admin">
    <h1 class="page"><?= $editing ? '✏️ Veranstaltung bearbeiten' : '➕ Neue Veranstaltung' ?></h1>
    <p class="section-sub">Felder mit einem Sternchen <span class="required">*</span> müssen ausgefüllt werden.</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php render_pending_banner($pendingChanges); ?>
    <?php endif; ?>

    <div class="card">
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
                <label for="dateSort">Wann findet die Veranstaltung statt? <span class="required">*</span></label>
                <p class="hint">Wähle das Datum im Kalender. Bei mehreren Tagen wähle den ersten Tag.</p>
                <input type="date" id="dateSort" name="dateSort" required
                       value="<?= htmlspecialchars($event['dateSort']) ?>"
                       onchange="suggestDisplayDate()">
            </div>

            <div class="field">
                <label for="date">Datum, wie es auf der Website steht <span class="required">*</span></label>
                <p class="hint">Wird automatisch vorgeschlagen — du kannst es frei anpassen,
                    z.B. „22. - 23. März 2026" oder „6. Dezember 2025, 14-19 Uhr".</p>
                <input type="text" id="date" name="date" required
                       value="<?= htmlspecialchars($event['date']) ?>"
                       placeholder="22. - 23. März 2026"
                       oninput="this.dataset.touched = '1'">
            </div>

            <div class="field">
                <label for="fullDescription">Beschreibung</label>
                <p class="hint">Optional. Dieser Text erscheint, wenn Besucher auf die Veranstaltung klicken.</p>
                <textarea id="fullDescription" name="fullDescription"
                          placeholder="Beschreibe die Veranstaltung…"><?= htmlspecialchars($event['fullDescription'] ?? '') ?></textarea>
            </div>

            <div class="field">
                <label for="imageFile">Bild</label>
                <p class="hint">JPG oder PNG, höchstens 20 MB. Leer lassen, um das bestehende Bild zu behalten.</p>
                <input type="file" id="imageFile" name="imageFile" accept="image/jpeg,image/png"
                       onchange="previewImage(this)">
                <img id="preview" class="image-preview" alt="Vorschau">
                <?php if ($event['image']): ?>
                    <p class="current-image">Aktuelles Bild: <strong><?= htmlspecialchars($event['image']) ?></strong></p>
                <?php endif; ?>
                <input type="hidden" name="image" value="<?= htmlspecialchars($event['image']) ?>">
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary btn-xl">💾 Speichern</button>
                <a href="/admin/dashboard.php" class="btn btn-secondary btn-xl">Abbrechen</a>
            </div>
        </form>
    </div>
</main>
<?php
admin_html_foot(<<<'JS'
const MONTHS = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
function suggestDisplayDate() {
    const picker = document.getElementById('dateSort');
    const text   = document.getElementById('date');
    if (!picker.value) return;
    // Don't overwrite text the user has typed or edited themselves.
    if (text.value.trim() !== '' && text.dataset.touched === '1') return;
    if (text.value.trim() !== '' && text.dataset.auto !== '1') return;
    const [y, m, d] = picker.value.split('-').map(Number);
    text.value = d + '. ' + MONTHS[m - 1] + ' ' + y;
    text.dataset.auto = '1';
}
function previewImage(input) {
    const preview = document.getElementById('preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}
JS);
