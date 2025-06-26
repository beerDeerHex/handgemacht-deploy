<?php
// contact_request.php

// imports
require_once __DIR__ . '/utilities/logMessage.php';
require_once __DIR__ . '/utilities/getDatabaseConnection.php';

logMessage("", true);

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed']);
    logMessage("", false, true);
    exit;
}

logMessage("Valid request method: " . $_SERVER['REQUEST_METHOD']);

// Get and decode JSON input
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Validate JSON format
if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage('Invalid JSON received: ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    logMessage("", false, true);
    exit;
}

logMessage("Valid  JSON received");

// Google reCAPTCHA verification
if (empty($data['recaptchaToken'])) {
    logMessage('Missing reCAPTCHA token');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing reCAPTCHA token']);
    logMessage("", false, true);
    exit;
}
$recaptchaSecret = getenv('RECAPTCHA_SECRET');
$recaptchaToken = $data['recaptchaToken'];
$recaptchaResponse = file_get_contents(
    'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($recaptchaSecret) . '&response=' . urlencode($recaptchaToken)
);
$recaptchaResult = json_decode($recaptchaResponse, true);
if (empty($recaptchaResult['success']) || !$recaptchaResult['success']) {
    logMessage('reCAPTCHA verification failed: ' . json_encode($recaptchaResult));
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA verification failed']);
    logMessage("", false, true);
    exit;
}

logMessage("reCAPTCHA verification successful");

// Required fields and their types
$requiredFields = [
    'name' => 'string',
    'email' => 'string',
    'type' => 'string',
    'message' => 'string'
];

// Validate required fields
foreach ($requiredFields as $field => $type) {
    if (!isset($data[$field])) {
        logMessage("Missing required field: $field");
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Missing field: $field"]);
        logMessage("", false, true);
        exit;
    }
    if (gettype($data[$field]) !== $type || trim($data[$field]) === '') {
        logMessage("Invalid or empty value for field: $field");
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Invalid value for: $field"]);
        logMessage("", false, true);
        exit;
    }
}

logMessage("All required fields are present and valid");

// Validate email format
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    logMessage("Invalid email format: {$data['email']}");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
    logMessage("", false, true);
    exit;
}

logMessage("Valid email format");

try {
    $db = getDatabaseConnection();

    $stmt = $db->prepare("INSERT INTO contact_request (username, email, request_type, content) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }

    $stmt->bind_param(
        "ssss",
        $data['name'],
        $data['email'],
        $data['type'],
        $data['message']
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $stmt->close();
    logMessage("Contact request saved successfully for email: {$data['email']}");
    echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully']);
    logMessage("", false, true);

} catch (Exception $e) {
    logMessage('Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
    logMessage("", false, true);
    exit;
}
?>
