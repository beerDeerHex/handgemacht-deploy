<?php
// contact_request.php

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// Create a simple log entry
$logMessage = date('Y-m-d H:i:s') . " - Message received\n";

// Save to a log file
file_put_contents('message-log.txt', $logMessage, FILE_APPEND);

// Return a simple JSON response
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Message logged']);

/**
 * Example: Logging with error_log
 * This logs a message to the PHP error log.
 */
error_log("Custom log entry: Message received at " . date('Y-m-d H:i:s'));

// End of contact_request.php
?>