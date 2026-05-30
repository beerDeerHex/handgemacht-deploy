<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_login();

header('Content-Type: application/json');

$result = github_api('GET', '/actions/runs?branch=' . urlencode(GITHUB_BRANCH) . '&per_page=1');
$run    = $result['workflow_runs'][0] ?? null;

if (!$run) {
    echo json_encode(['status' => 'none']);
    exit;
}

// Convert UTC timestamp to a human-readable local time string.
$updatedAt = $run['updated_at'] ?? '';
$ts        = $updatedAt ? strtotime($updatedAt) : 0;
$timeStr   = $ts ? date('d.m.Y H:i', $ts) . ' Uhr' : '';

echo json_encode([
    'status'     => $run['status'],        // queued | in_progress | completed
    'conclusion' => $run['conclusion'],    // success | failure | cancelled | null
    'updated_at' => $timeStr,
    'run_url'    => $run['html_url'] ?? '',
]);
