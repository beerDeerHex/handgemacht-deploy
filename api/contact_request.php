<?php
// contact_request.php

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// Start of new log entry
require_once __DIR__ . '/utilities/logMessage.php';
require_once __DIR__ . '/utilities/getDatabaseConnection.php';

logMessage("", true, true);

// Create a simple log entry
logMessage('Message received');

// Convert the json input to an associative array
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

try {

    // Connect to the database
    $db = getDatabaseConnection();

    // Write the data to the database
    $stmt = $db->prepare("INSERT INTO contact_request (username, email, request_type, content) VALUES (?, ?, ?, ?)");
    $stmt->bind_param(
        "ssss",
        $data['name'],
        $data['email'],
        $data['type'],
        $data['message']
    );
    $stmt->execute();
    $stmt->close();

} catch (Exception $e) {
    // Log the error message
    logMessage('Database error: ' . $e->getMessage());
    
    // Send a response to the client
    http_response_code(400); // Error
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    logMessage("", true);
    exit;
}

// End of contact_request.php
?>