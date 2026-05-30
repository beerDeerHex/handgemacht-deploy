<?php
// GitHub API helper.
// All admin writes go to GITHUB_ADMIN_BRANCH (never touches main directly).
// The deploy button merges that branch into GITHUB_BRANCH (main), which triggers
// the existing GitHub Actions workflow automatically via the push event.

function github_api(string $method, string $path, array $body = []): array {
    $url = 'https://api.github.com/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . $path;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . GITHUB_TOKEN,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: handgemacht-admin/1.0',
            'Content-Type: application/json',
        ],
    ]);
    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true) ?? [];
    $data['_http_code'] = $httpCode;
    return $data;
}

// ---- Branch helpers --------------------------------------------------------

// Simple per-request cache so we don't hit the API multiple times per page load.
function _branch_cache(?bool $set = null): bool {
    static $value = null;
    if ($set !== null) $value = $set;
    return $value ?? false;
}
function _branch_checked(?bool $set = null): bool {
    static $checked = false;
    if ($set !== null) $checked = $set;
    return $checked;
}

function admin_branch_exists(): bool {
    if (_branch_checked()) return _branch_cache();
    _branch_checked(true);
    $ref    = github_api('GET', '/git/refs/heads/' . GITHUB_ADMIN_BRANCH);
    $exists = isset($ref['object']['sha']);
    _branch_cache($exists);
    return $exists;
}

// Ensures the admin working branch exists, creating it from main if needed.
function github_ensure_admin_branch(): bool {
    if (admin_branch_exists()) return true;
    $main = github_api('GET', '/git/refs/heads/' . GITHUB_BRANCH);
    if (!isset($main['object']['sha'])) return false;
    $result = github_api('POST', '/git/refs', [
        'ref' => 'refs/heads/' . GITHUB_ADMIN_BRANCH,
        'sha' => $main['object']['sha'],
    ]);
    if (isset($result['ref'])) {
        _branch_cache(true);
        return true;
    }
    return false;
}

// Returns list of commits on admin branch not yet in main.
// Each entry: ['message' => string, 'date' => 'YYYY-MM-DD']
function github_get_pending_changes(): array {
    if (!admin_branch_exists()) return [];
    $result = github_api('GET', '/compare/' . GITHUB_BRANCH . '...' . urlencode(GITHUB_ADMIN_BRANCH));
    if (empty($result['commits'])) return [];
    return array_map(fn($c) => [
        'message' => explode("\n", $c['commit']['message'])[0],
        'date'    => substr($c['commit']['author']['date'] ?? '', 0, 10),
    ], $result['commits']);
}

// ---- File helpers ----------------------------------------------------------

// Reads a file. Uses admin branch if it exists, otherwise falls back to main.
function github_get_file(string $repo_path): ?array {
    $branch = admin_branch_exists() ? GITHUB_ADMIN_BRANCH : GITHUB_BRANCH;
    $result = github_api('GET', '/contents/' . ltrim($repo_path, '/') . '?ref=' . urlencode($branch));
    if (empty($result['content']) || empty($result['sha'])) return null;
    return [
        'content' => base64_decode(str_replace("\n", '', $result['content'])),
        'sha'     => $result['sha'],
    ];
}

// Lists files in a directory. Uses admin branch if it exists, otherwise main.
// Pass $branch explicitly to target a specific branch.
function github_list_dir(string $repo_path, ?string $branch = null): array {
    $branch = $branch ?? (admin_branch_exists() ? GITHUB_ADMIN_BRANCH : GITHUB_BRANCH);
    $result = github_api('GET', '/contents/' . ltrim($repo_path, '/') . '?ref=' . urlencode($branch));
    if (!is_array($result) || isset($result['message'])) return [];
    return array_values(array_filter($result, fn($item) => isset($item['type']) && $item['type'] === 'file'));
}

// Returns a set of filenames that exist on the admin branch but not on main (or have a
// different SHA), i.e. images that have been uploaded but not yet deployed.
function github_pending_filenames(string $repo_path): array {
    if (!admin_branch_exists()) return [];
    $mainFiles    = github_list_dir($repo_path, GITHUB_BRANCH);
    $mainBySha    = array_column($mainFiles, 'sha', 'name');
    $pendingFiles = github_list_dir($repo_path, GITHUB_ADMIN_BRANCH);
    $pending      = [];
    foreach ($pendingFiles as $file) {
        if (!isset($mainBySha[$file['name']]) || $mainBySha[$file['name']] !== $file['sha']) {
            $pending[$file['name']] = true;
        }
    }
    return $pending;
}

// Creates or updates a file on the admin branch (creating the branch first if needed).
function github_put_file(string $repo_path, string $content, string $message, ?string $sha = null): bool {
    if (!github_ensure_admin_branch()) return false;
    $body = [
        'message' => $message,
        'content' => base64_encode($content),
        'branch'  => GITHUB_ADMIN_BRANCH,
    ];
    if ($sha !== null) $body['sha'] = $sha;
    $result = github_api('PUT', '/contents/' . ltrim($repo_path, '/'), $body);
    return isset($result['content']['sha']);
}

// Deletes a file on the admin branch.
function github_delete_file(string $repo_path, string $sha, string $message): bool {
    if (!github_ensure_admin_branch()) return false;
    $result = github_api('DELETE', '/contents/' . ltrim($repo_path, '/'), [
        'message' => $message,
        'sha'     => $sha,
        'branch'  => GITHUB_ADMIN_BRANCH,
    ]);
    return $result['_http_code'] === 200;
}

// ---- Deploy ----------------------------------------------------------------

// Merges admin branch into main (triggering CI/CD via the push event automatically).
// If there is nothing to merge, falls back to workflow_dispatch for a manual rebuild.
// Deletes the admin branch after success so the next write starts fresh from main.
function github_deploy(): bool {
    $merge = github_api('POST', '/merges', [
        'base'           => GITHUB_BRANCH,
        'head'           => GITHUB_ADMIN_BRANCH,
        'commit_message' => 'Admin: Änderungen deployen',
    ]);

    if ($merge['_http_code'] === 201) {
        // Merged — CI triggered automatically by the push to main.
        github_api('DELETE', '/git/refs/heads/' . GITHUB_ADMIN_BRANCH);
        return true;
    }

    if ($merge['_http_code'] === 204) {
        // Nothing to merge — branch is already in sync with main.
        // Trigger a manual redeploy via workflow_dispatch instead.
        github_api('DELETE', '/git/refs/heads/' . GITHUB_ADMIN_BRANCH);
        $dispatch = github_api('POST', '/actions/workflows/main.yml/dispatches', [
            'ref' => GITHUB_BRANCH,
        ]);
        return $dispatch['_http_code'] === 204;
    }

    return false;
}

// ---- Events helpers --------------------------------------------------------

const EVENTS_PATH = 'src/data/events.json';

function read_events(): array {
    $file = github_get_file(EVENTS_PATH);
    if (!$file) return [];
    return json_decode($file['content'], true) ?? [];
}

function write_events(array $events, string $commit_message): bool {
    $file = github_get_file(EVENTS_PATH);
    $sha  = $file ? $file['sha'] : null;
    $json = json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return github_put_file(EVENTS_PATH, $json, $commit_message, $sha);
}

// ---- Image helpers ---------------------------------------------------------

// Creates a JPEG thumbnail (max 300px on the longest side) from raw image binary.
// Returns the thumbnail binary, or null if GD is unavailable or the image is invalid.
function create_thumbnail(string $binary, int $maxSize = 300): ?string {
    if (!function_exists('imagecreatefromstring')) return null;
    $src = @imagecreatefromstring($binary);
    if (!$src) return null;

    $origW = imagesx($src);
    $origH = imagesy($src);
    $ratio = min($maxSize / $origW, $maxSize / $origH, 1.0); // never upscale
    $thumbW = max(1, (int)round($origW * $ratio));
    $thumbH = max(1, (int)round($origH * $ratio));

    $thumb = imagecreatetruecolor($thumbW, $thumbH);
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);
    imagedestroy($src);

    ob_start();
    imagejpeg($thumb, null, 75);
    $data = ob_get_clean();
    imagedestroy($thumb);

    return $data ?: null;
}

// Returns the thumbnail repo path for a given original repo path.
// e.g. src/images/Produkte/Taschen/bag.jpg -> src/images/_thumbs/Produkte/Taschen/bag.jpg
function thumb_path(string $repo_path): string {
    $path = preg_replace('#^src/images/#', 'src/images/_thumbs/', $repo_path);
    return preg_replace('/\.[^.]+$/', '.jpg', $path);
}

// Uploads an image and, if GD is available, also uploads a small thumbnail.
function upload_image(string $repo_dir, string $filename, string $binary_content): bool {
    $repo_path = rtrim($repo_dir, '/') . '/' . $filename;
    $existing  = github_get_file($repo_path);
    $sha       = $existing ? $existing['sha'] : null;

    if (!github_put_file($repo_path, $binary_content, "Bild hochgeladen: $filename", $sha)) {
        return false;
    }

    // Best-effort thumbnail — failure doesn't block the upload.
    $thumbData = create_thumbnail($binary_content);
    if ($thumbData) {
        $tp          = thumb_path($repo_path);
        $existingThumb = github_get_file($tp);
        $thumbSha    = $existingThumb ? $existingThumb['sha'] : null;
        github_put_file($tp, $thumbData, "Thumbnail: $filename", $thumbSha);
    }

    return true;
}

// Deletes an image and its thumbnail (if it exists).
function delete_image(string $repo_path, string $sha, string $filename): bool {
    $ok = github_delete_file($repo_path, $sha, "Bild gelöscht: $filename");

    // Best-effort thumbnail deletion.
    if ($ok) {
        $tp    = thumb_path($repo_path);
        $thumb = github_get_file($tp);
        if ($thumb) {
            github_delete_file($tp, $thumb['sha'], "Thumbnail gelöscht: $filename");
        }
    }

    return $ok;
}
