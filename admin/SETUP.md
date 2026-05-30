# Admin Panel – Einrichtung

## 1. GitHub Personal Access Token erstellen

1. Gehe zu GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens
2. Klicke „Generate new token"
3. Berechtigungen: **Contents: Read and write** (für das handgemacht Source-Repo)
4. Token kopieren

## 2. Einträge in `/home/u237207940/domains/config.php` hinzufügen

```php
// Admin Panel
define('ADMIN_PASSWORD_HASH', '');  // Schritt 3 – Hash eintragen
define('GITHUB_TOKEN',  'github_pat_xxx...');
define('GITHUB_OWNER',  'dein-github-benutzername');
define('GITHUB_REPO',   'handgemacht');  // Source-Repo-Name
define('GITHUB_BRANCH', 'main');
```

## 3. Admin-Passwort-Hash generieren

Auf dem Hostinger-Server (via SSH oder PHP-Datei):

```php
<?php echo password_hash('dein-wunschpasswort', PASSWORD_BCRYPT); ?>
```

Den ausgegebenen Hash in `ADMIN_PASSWORD_HASH` eintragen.

## 4. Admin-Panel aufrufen

Nach dem nächsten Deploy erreichbar unter:  
`https://handgemacht-claudiawild.com/admin/`

## Verwendung

- **Neue Veranstaltung**: Dashboard → „Neue Veranstaltung" → Formular ausfüllen → Speichern
- **Veranstaltung bearbeiten**: Dashboard → „Bearbeiten"
- **Veranstaltung löschen**: Dashboard → „Löschen"
- **Produktfotos hochladen**: Dashboard → Kategorie auswählen → Bilder hochladen

Nach jeder Änderung dauert es ca. **1–2 Minuten**, bis die Website aktualisiert ist
(GitHub Actions baut die Seite neu und deployt sie automatisch).
