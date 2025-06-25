<?php
// contact_request.php

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// Start of new log entry
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


function logMessage($message = "", $seperator = false, $space = false) {
    $logFile = 'message-log.txt';
    $timestamp = date('Y-m-d H:i:s');
    if ($seperator) {
        if ($space) {
            file_put_contents($logFile, "\n|-----------------------------------------------------------------------------------------------------------------|\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, "\n|-----------------------------------------------------------------------------------------------------------------|\n", FILE_APPEND);
        }
    } else {
        file_put_contents($logFile, "$timestamp - $message\n", FILE_APPEND);
    }
}

function getDatabaseConnection(): mysqli {
    $host = 'localhost';
    $db   = 'u237207940_handgemacht';
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');

    // Create mysqli connection
    $mysqli = new mysqli($host, $db_user, $db_pass, $db);

    // Check connection
    if ($mysqli->connect_error) {
        logMessage("Database connection failed: " . $mysqli->connect_error);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        logMessage("", true);
        exit;
    }

    logMessage("Database connection established using mysqli.");
    return $mysqli;
}

// End of contact_request.php
?>