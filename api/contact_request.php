<?php
// contact_request.php

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// Log the request for debugging
error_log("Message received at " . date('Y-m-d H:i:s'));

// Convert the json input to an associative array
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Usage example:
$mysqli = getDatabaseConnection();

// send a response to the client
http_response_code(400); // Bad Request
echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
exit;



function getDatabaseConnection(): mysqli {
    $host = 'localhost';
    $db   = 'u237207940_handgemacht';
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');

    // Create mysqli connection
    $mysqli = new mysqli($host, $db_user, $db_pass, $db);

    if ($mysqli->connect_error) {
        error_log("Database connection failed: " . $mysqli->connect_error);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }

    error_log("Database connection established using mysqli.");
    return $mysqli;
}

// End of contact_request.php
?>