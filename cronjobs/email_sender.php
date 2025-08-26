<?php
// email_sender.php - cronjob to process and send contact requests

// Define constant before including config
define('CONFIG_LOADED', true);

// imports
require_once '/home/u237207940/domains/config.php';
require_once '/home/u237207940/domains/handgemacht-claudiawild.com/public_html/vendor/autoload.php';
require_once '/home/u237207940/domains/handgemacht-claudiawild.com/public_html/utilities/logMessage.php';
require_once '/home/u237207940/domains/handgemacht-claudiawild.com/public_html/utilities/getDatabaseConnection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

logMessage("", true);

date_default_timezone_set('UTC');

try {
    $db = getDatabaseConnection();
    // Fetch the oldest unsent contact request
    $result = $db->query("SELECT * FROM contact_request ORDER BY id ASC LIMIT 1");
    if (!$result) {
        logMessage('Failed to query contact_request: ' . $db->error);
        logMessage("", false, true);
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
    $to = getenv('CONTACT_REQUEST_RECEIVER');
    $subject = "Neue Kontaktanfrage von $username ($type)";
    $body = "Name: $username<br>Email: $email<br>Typ: $type<br>Nachricht:<br>" . nl2br($content);

    $mail = new PHPMailer(true);
    try {
        // SMTP settings (customize as needed)
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USER');
        $mail->Password = getenv('SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = getenv('SMTP_PORT');
        $mail->setFrom(getenv('SMTP_USER'), 'Kontaktformular');
        $mail->addAddress($to);
        $mail->addReplyTo($email, $username);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();
        // Insert into contact_request_sent
        $stmt = $db->prepare("INSERT INTO contact_request_sent (username, email, request_type, content, sent_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ssss", $username, $email, $type, $content);
            $stmt->execute();
            $stmt->close();
            // Delete from contact_request
            $db->query("DELETE FROM contact_request WHERE id = " . intval($id));
            logMessage("Contact request from $email sent and archived.");
            logMessage("", false, true);
        } else {
            logMessage('Failed to insert into contact_request_sent: ' . $db->error);
            logMessage("", false, true);
        }
    } catch (Exception $e) {
        logMessage("PHPMailer error for $email: " . $mail->ErrorInfo);
        logMessage("", false, true);
    }
} catch (Exception $e) {
    logMessage('Cronjob error: ' . $e->getMessage());
    logMessage("", false, true);
    exit;
}
?>