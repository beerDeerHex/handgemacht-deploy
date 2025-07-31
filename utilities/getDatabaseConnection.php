<?php
require_once __DIR__ . '/logMessage.php';
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
?>

DB_USER=your_db_user 
DB_PASS=your_db_pass /usr/bin/php /home/u237207940/domains/handgemacht-claudiawild.com/public_html/cronjobs/email_sender.php