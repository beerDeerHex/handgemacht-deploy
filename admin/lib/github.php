<?php
// GitHub API helper — read/write files in the source repo via REST API.
// Requires GITHUB_TOKEN, GITHUB_OWNER, GITHUB_REPO defined in config.php.

function github_api(string $method, string $path, array $body = []): array {
    $url = 'https://api.github.com/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . $path;
    $ch = curl_init($url);
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

// Returns ['content' => decoded string, 'sha' => sha] or null on error.
function github_get_file(string $repo_path): ?array {
    $result = github_api('GET', '/contents/' . ltrim($repo_path, '/'));
    if (empty($result['content']) || empty($result['sha'])) return null;
    return [
        'content' => base64_decode(str_replace("\n", '', $result['content'])),
        'sha'     => $result['sha'],
    ];
}

// Creates or updates a file. $sha required for updates, null for new files.
function github_put_file(string $repo_path, string $content, string $message, ?string $sha = null): bool {
    $body = [
        'message' => $message,
        'content' => base64_encode($content),
        'branch'  => GITHUB_BRANCH,
    ];
    if ($sha !== null) $body['sha'] = $sha;
    $result = github_api('PUT', '/contents/' . ltrim($repo_path, '/'), $body);
    return isset($result['content']['sha']);
}

// Deletes a file. Returns true on success.
function github_delete_file(string $repo_path, string $sha, string $message): bool {
    $body = [
        'message' => $message,
        'sha'     => $sha,
        'branch'  => GITHUB_BRANCH,
    ];
    $result = github_api('DELETE', '/contents/' . ltrim($repo_path, '/'), $body);
    return ($result['_http_code'] === 200);
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

function upload_image(string $repo_dir, string $filename, string $binary_content): bool {
    $repo_path = rtrim($repo_dir, '/') . '/' . $filename;
    // Check if file already exists (need SHA for update)
    $existing = github_get_file($repo_path);
    $sha = $existing ? $existing['sha'] : null;
    return github_put_file($repo_path, $binary_content, "Bild hochgeladen: $filename", $sha);
}
