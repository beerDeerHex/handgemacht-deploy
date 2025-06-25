<?php
function logMessage($message = "", $startMessage = false, $endMessage = false) {
    $logFile = 'message-log.txt';
    $timestamp = date('Y-m-d H:i:s');
    if ($startMessage) {
            file_put_contents($logFile, "------------------------------START OF NEW REQUEST------------------------------", FILE_APPEND);
    } elseif ($endMessage) {
            file_put_contents($logFile, "------------------------------------------------------------------------------------------------------------------------------------------", FILE_APPEND);
    } else {
        file_put_contents($logFile, "$timestamp - $message\n", FILE_APPEND);
    }
}
?>
