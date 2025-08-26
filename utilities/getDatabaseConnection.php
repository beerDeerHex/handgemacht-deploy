<?php

// Define constant before including config
define('CONFIG_LOADED', true);

require_once '/home/u237207940/domains/handgemacht-claudiawild.com/config.php';
require_once '/home/u237207940/domains/handgemacht-claudiawild.com/public_html/utilities/logMessage.php';

function getDatabaseConnection(): mysqli {
    $host = DB_HOST;
    $db   = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASS;

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