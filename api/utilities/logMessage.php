<?php
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
?>
