<?php
function logMessage($message = "", $startMessage = false, $endMessage = false) {
    $logFile = 'log.txt';
    $timestamp = date('Y-m-d H:i:s');
    if ($startMessage) {
            file_put_contents($logFile, "\n-----------------------------------------START OF NEW REQUEST-----------------------------------------\n", FILE_APPEND);
    } elseif ($endMessage) {
            file_put_contents($logFile, "------------------------------------------------------------------------------------------------------\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "$timestamp - $message\n", FILE_APPEND);
    }
}
?>
