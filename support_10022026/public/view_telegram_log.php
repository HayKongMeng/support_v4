<?php
/**
 * View Telegram Webhook Log
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== TELEGRAM WEBHOOK LOG ===\n\n";

$logFile = dirname(__DIR__) . '/storage/logs/telegram_webhook.log';

echo "Log file path: {$logFile}\n";
echo "File exists: " . (file_exists($logFile) ? 'Yes' : 'No') . "\n\n";

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if (empty($content)) {
        echo "Log file is empty - no webhook calls received yet.\n";
    } else {
        echo "=== LOG CONTENTS ===\n\n";
        echo $content;
    }

    // Show file stats
    echo "\n\n=== FILE STATS ===\n";
    echo "Size: " . filesize($logFile) . " bytes\n";
    echo "Modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "\n";
} else {
    echo "Log file does not exist.\n";
    echo "This means the webhook endpoint has NOT been called.\n\n";

    echo "Possible reasons:\n";
    echo "1. Telegram cannot reach your webhook URL\n";
    echo "2. The URL routing is not working\n";
    echo "3. The .htaccess is blocking the request\n";
}

echo "\n\n=== TEST STEPS ===\n";
echo "1. Send a message to your bot (@dpdc_ticket_bot)\n";
echo "2. Refresh this page\n";
echo "3. If no log appears, the webhook is not being reached\n";

// Clear log option
if (isset($_GET['clear'])) {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
        echo "\n\nLog cleared!\n";
    }
}
echo "\n\nTo clear log: ?clear=1\n";
