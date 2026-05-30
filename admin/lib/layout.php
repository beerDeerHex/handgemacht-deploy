<?php
// Shared layout, styles and UI partials for the admin panel.
// Lives in lib/ (never served directly — see lib/.htaccess) and is require'd by each page.
// Goal: one consistent, large-and-friendly look across every page so the language and
// sizing never drift. Designed for a non-technical 60+ user: big text, big buttons,
// plain German, no developer jargon.

// Whom to contact when something technical goes wrong (shown in error messages).
if (!defined('ADMIN_SUPPORT_NAME')) {
    define('ADMIN_SUPPORT_NAME', 'Stefan');
}

function admin_styles(): string {
    return <<<'CSS'
*{ box-sizing:border-box; margin:0; padding:0; }
html{ font-size:18px; }
body{ font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; background:#f3f4f6; color:#1f2937; line-height:1.5; }
a{ color:#2563eb; }

/* Top bar */
header.admin{ background:#fff; border-bottom:1px solid #e5e7eb; padding:1rem 1.5rem; display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; position:sticky; top:0; z-index:20; }
header.admin .brand{ font-size:1.35rem; font-weight:700; color:#1f2937; text-decoration:none; }
main.admin{ max-width:880px; margin:1.5rem auto 4rem; padding:0 1rem; }

/* Buttons — large touch targets */
.btn{ display:inline-flex; align-items:center; justify-content:center; gap:.45rem; min-height:48px; padding:.7rem 1.3rem; border-radius:8px; font-size:1.05rem; font-weight:600; text-decoration:none; cursor:pointer; border:2px solid transparent; line-height:1.2; }
.btn:focus-visible{ outline:3px solid #93c5fd; outline-offset:2px; }
.btn-primary{ background:#1f2937; color:#fff; } .btn-primary:hover{ background:#374151; }
.btn-accent{ background:#2563eb; color:#fff; } .btn-accent:hover{ background:#1d4ed8; }
.btn-danger{ background:#dc2626; color:#fff; } .btn-danger:hover{ background:#b91c1c; }
.btn-secondary{ background:#fff; color:#1f2937; border-color:#d1d5db; } .btn-secondary:hover{ background:#f3f4f6; }
.btn-block{ width:100%; }
.btn-xl{ font-size:1.2rem; padding:1rem 1.7rem; min-height:58px; }
.btn:disabled{ opacity:.5; cursor:not-allowed; }

/* Cards & sections */
.card{ background:#fff; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:1.5rem; }
.stack > * + *{ margin-top:1.5rem; }
h1.page{ font-size:1.5rem; margin-bottom:.2rem; }
.section-title{ font-size:1.25rem; margin-bottom:.2rem; display:flex; align-items:center; gap:.5rem; }
.section-sub{ color:#6b7280; margin-bottom:1rem; font-size:1rem; }
.toolbar{ display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }

/* Alerts */
.alert{ border-radius:10px; padding:1rem 1.2rem; font-size:1.05rem; border:2px solid; margin-bottom:1.25rem; }
.alert strong{ font-weight:700; }
.alert-success{ background:#dcfce7; border-color:#86efac; color:#166534; }
.alert-error{ background:#fee2e2; border-color:#fca5a5; color:#991b1b; }
.alert-info{ background:#dbeafe; border-color:#93c5fd; color:#1e40af; }
.alert-warn{ background:#fffbeb; border-color:#fde68a; color:#92400e; }

/* Form fields */
.field{ margin-bottom:1.4rem; }
.field label{ display:block; font-size:1.05rem; font-weight:600; margin-bottom:.35rem; }
.hint{ font-size:.95rem; color:#6b7280; margin-bottom:.5rem; }
input[type=text],input[type=date],input[type=password],textarea{
    width:100%; padding:.8rem .9rem; border:2px solid #d1d5db; border-radius:8px; font-size:1.1rem; font-family:inherit; color:#1f2937; }
input:focus,textarea:focus{ outline:none; border-color:#2563eb; }
textarea{ resize:vertical; min-height:130px; line-height:1.5; }
.required{ color:#dc2626; }
.actions{ display:flex; gap:.75rem; margin-top:1.5rem; flex-wrap:wrap; }

/* Badges */
.badge{ display:inline-block; padding:.25rem .7rem; border-radius:999px; font-size:.9rem; font-weight:700; }
.badge-future{ background:#dcfce7; color:#15803d; }
.badge-past{ background:#f3f4f6; color:#6b7280; }

/* "Not yet on the website" reminder banner */
.banner{ border-radius:12px; padding:1.3rem 1.4rem; margin-bottom:1.5rem; border:2px solid; }
.banner-warn{ background:#fffbeb; border-color:#fcd34d; }
.banner-warn .banner-head{ font-size:1.2rem; font-weight:700; color:#92400e; display:flex; gap:.5rem; align-items:center; margin-bottom:.5rem; }
.banner-warn .banner-text{ color:#78350f; margin-bottom:1.1rem; font-size:1.05rem; }
.banner-ok{ background:#f0fdf4; border-color:#bbf7d0; color:#166534; font-size:1.08rem; font-weight:600; display:flex; align-items:center; gap:.5rem; }
.banner-list{ list-style:none; margin:.4rem 0 1rem; }
.banner-list li{ font-size:.98rem; color:#78350f; padding:.15rem 0; }

/* Live website status bar */
.statusbar{ display:flex; align-items:center; gap:.75rem; background:#fff; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:1rem 1.25rem; margin-bottom:1.5rem; font-size:1.05rem; }
.status-dot{ width:14px; height:14px; border-radius:50%; flex-shrink:0; }
.dot-none{ background:#d1d5db; } .dot-queued{ background:#f59e0b; } .dot-progress{ background:#3b82f6; animation:pulse 1.2s ease-in-out infinite; } .dot-success{ background:#22c55e; } .dot-failure{ background:#ef4444; }
@keyframes pulse{ 0%,100%{opacity:1} 50%{opacity:.35} }
.status-label{ font-weight:600; }
.status-time{ color:#9ca3af; margin-left:auto; font-size:.9rem; }
.status-help{ font-size:.9rem; }

/* Event list (cards, reflow nicely on phones/tablets) */
.event-list{ display:flex; flex-direction:column; gap:.8rem; }
.event-row{ background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:1rem 1.1rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
.event-thumb{ width:64px; height:64px; border-radius:8px; object-fit:cover; background:#f3f4f6; flex-shrink:0; }
.event-thumb-empty{ width:64px; height:64px; border-radius:8px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; font-size:1.6rem; flex-shrink:0; }
.event-main{ flex:1; min-width:170px; }
.event-name{ font-size:1.12rem; font-weight:700; }
.event-meta{ color:#6b7280; font-size:.98rem; margin-top:.2rem; display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; }
.event-actions{ display:flex; gap:.5rem; flex-wrap:wrap; }

/* Photo category tiles */
.photo-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:.75rem; }
.photo-btn{ display:flex; flex-direction:column; align-items:center; gap:.4rem; padding:1.2rem .6rem; border:2px solid #d1d5db; border-radius:10px; text-align:center; font-size:1.02rem; font-weight:600; text-decoration:none; color:#1f2937; background:#fff; }
.photo-btn:hover{ background:#f3f4f6; border-color:#2563eb; }
.photo-btn .ico{ font-size:1.7rem; }

/* Photo manage page */
.img-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.8rem; }
.img-item{ position:relative; border-radius:10px; overflow:hidden; background:#f9fafb; border:1px solid #e5e7eb; }
.img-item img{ width:100%; aspect-ratio:1; object-fit:cover; display:block; cursor:zoom-in; }
.img-name{ font-size:.82rem; color:#6b7280; padding:.35rem .45rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.img-del{ position:absolute; top:6px; right:6px; width:36px; height:36px; background:rgba(220,38,38,.9); color:#fff; border:none; border-radius:8px; font-size:1.05rem; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.img-del:hover{ background:#b91c1c; }
.pending-dot{ position:absolute; top:6px; left:6px; background:#f59e0b; color:#fff; font-size:.72rem; font-weight:700; padding:3px 7px; border-radius:6px; pointer-events:none; }
.drop-zone{ border:3px dashed #cbd5e1; border-radius:12px; padding:2rem 1rem; text-align:center; color:#6b7280; cursor:pointer; transition:border-color .2s; }
.drop-zone:hover,.drop-zone.drag-over{ border-color:#2563eb; color:#1f2937; background:#f8fafc; }
.drop-zone .big{ font-size:1.15rem; font-weight:600; color:#1f2937; }
.drop-zone input[type=file]{ display:none; }
.preview-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(90px,1fr)); gap:.5rem; margin-top:1rem; }
.preview-grid img{ width:100%; aspect-ratio:1; object-fit:cover; border-radius:6px; }
.count-badge{ font-size:.95rem; color:#6b7280; font-weight:400; margin-left:.5rem; }
.empty{ color:#9ca3af; padding:1rem 0; font-size:1.02rem; }
.image-preview{ margin-top:.6rem; max-height:170px; max-width:100%; border-radius:8px; display:none; }
.current-image{ font-size:.98rem; color:#6b7280; margin-top:.4rem; }

/* Lightbox */
.lightbox{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:1000; align-items:center; justify-content:center; padding:1rem; }
.lightbox.open{ display:flex; }
.lightbox img{ max-width:90vw; max-height:90vh; object-fit:contain; border-radius:8px; box-shadow:0 8px 32px rgba(0,0,0,.5); }
.lightbox-name{ position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%); background:rgba(0,0,0,.6); color:#fff; font-size:.95rem; padding:.45rem 1rem; border-radius:20px; white-space:nowrap; }
.lightbox-pending{ position:fixed; top:1.5rem; left:50%; transform:translateX(-50%); background:#f59e0b; color:#fff; font-weight:600; padding:.4rem 1rem; border-radius:20px; }
.lightbox-close{ position:fixed; top:1rem; right:1rem; color:#fff; font-size:2.6rem; background:none; border:none; cursor:pointer; line-height:1; }

/* Login */
.login-body{ display:flex; align-items:center; justify-content:center; min-height:100vh; padding:1rem; }
.login-card{ background:#fff; border-radius:14px; padding:2.2rem; width:100%; max-width:400px; box-shadow:0 4px 16px rgba(0,0,0,.1); }
.login-card h1{ font-size:1.5rem; margin-bottom:1.6rem; text-align:center; }
CSS;
}

// Opens an HTML document: doctype, head (with shared styles) and <body>.
// $bodyClass lets the login page opt into its centered layout.
function admin_html_head(string $title, string $bodyClass = ''): void {
    $styles = admin_styles();
    $title  = htmlspecialchars($title);
    $cls    = $bodyClass ? ' class="' . htmlspecialchars($bodyClass) . '"' : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>$title</title>
    <style>$styles</style>
</head>
<body$cls>
HTML;
}

// Renders the sticky top bar. On the dashboard pass ['logout' => true];
// on sub-pages pass ['back' => true] for a big "Zurück" button.
function admin_topbar(array $opts = []): void {
    echo '<header class="admin">';
    echo '<a class="brand" href="/admin/dashboard.php">🧶 Handgemacht</a>';
    echo '<div>';
    if (!empty($opts['back'])) {
        echo '<a href="/admin/dashboard.php" class="btn btn-secondary">← Zurück zur Übersicht</a>';
    } elseif (!empty($opts['logout'])) {
        $csrf = htmlspecialchars(csrf_token());
        echo '<form method="POST" style="display:inline">'
           . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
           . '<input type="hidden" name="action" value="logout">'
           . '<button class="btn btn-secondary" type="submit">Abmelden</button>'
           . '</form>';
    }
    echo '</div></header>';
}

function admin_html_foot(string $extraScript = ''): void {
    if ($extraScript) echo "<script>$extraScript</script>";
    echo "</body>\n</html>";
}

// The "you have changes that are not on the website yet" reminder.
// Shown on every page so the publish step can never be forgotten.
// $changes is the array from github_get_pending_changes().
function render_pending_banner(array $changes): void {
    $count = count($changes);
    if ($count === 0) {
        echo '<div class="banner banner-ok">✅ Alles ist auf der Website. Es gibt nichts zu veröffentlichen.</div>';
        return;
    }
    $csrf  = htmlspecialchars(csrf_token());
    $word  = $count === 1 ? 'Änderung' : 'Änderungen';
    $isAre = $count === 1 ? 'ist' : 'sind';
    echo '<div class="banner banner-warn">';
    echo   '<div class="banner-head">⚠️ ' . $count . ' ' . $word . ' ' . $isAre . ' noch NICHT auf der Website</div>';
    echo   '<div class="banner-text">Deine Änderungen sind sicher gespeichert, aber Besucher sehen sie noch nicht. '
         . 'Klicke auf den Knopf, um sie zu veröffentlichen. Danach dauert es ca. 5 Minuten, bis die Website aktualisiert ist.</div>';
    echo   '<ul class="banner-list">';
    foreach ($changes as $c) {
        echo '<li>• ' . htmlspecialchars($c['message']) . '</li>';
    }
    echo   '</ul>';
    echo   '<form method="POST" action="/admin/deploy.php" '
         . 'onsubmit="return confirm(\'Deine ' . $count . ' ' . $word . ' jetzt auf die Website stellen?\')">';
    echo     '<input type="hidden" name="csrf_token" value="' . $csrf . '">';
    echo     '<button class="btn btn-accent btn-xl btn-block" type="submit">🚀 Jetzt auf die Website stellen</button>';
    echo   '</form>';
    echo '</div>';
}
