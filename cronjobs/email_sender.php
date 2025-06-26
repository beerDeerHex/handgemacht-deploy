<?php
// email_sender.php - cronjob to process and send contact requests

// imports
require_once __DIR__ . '../utilities';
require_once __DIR__ . '../utilities/getDatabaseConnection.php';

date_default_timezone_set('UTC');

try {
    $db = getDatabaseConnection();
    // Fetch the oldest unsent contact request
    $result = $db->query("SELECT * FROM contact_request ORDER BY id ASC LIMIT 1");
    if (!$result) {
        logMessage('Failed to query contact_request: ' . $db->error);
        exit;
    }
    if ($result->num_rows === 0) {
        // No new entries
        exit;
    }
    $row = $result->fetch_assoc();
    $id = $row['id'];
    $username = $row['username'];
    $email = $row['email'];
    $type = $row['request_type'];
    $content = $row['content'];

    // Prepare email
    $to = getenv('CONTACT_REQUEST_RECEIVER') ?: 'your@email.com';
    $subject = "Neue Kontaktanfrage von $username ($type)";
    $message = "Name: $username\nEmail: $email\nTyp: $type\nNachricht:\n$content";
    $headers = "From: noreply@" . $_SERVER['SERVER_NAME'];

    // Send email
    if (mail($to, $subject, $message, $headers)) {
        // Insert into contact_request_sent
        $stmt = $db->prepare("INSERT INTO contact_request_sent (username, email, request_type, content, sent_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ssss", $username, $email, $type, $content);
            $stmt->execute();
            $stmt->close();
            // Delete from contact_request
            $db->query("DELETE FROM contact_request WHERE id = " . intval($id));
            logMessage("Contact request from $email sent and archived.");
        } else {
            logMessage('Failed to insert into contact_request_sent: ' . $db->error);
        }
    } else {
        logMessage("Failed to send email for contact request from $email");
    }
} catch (Exception $e) {
    logMessage('Cronjob error: ' . $e->getMessage());
    exit;
}
?>