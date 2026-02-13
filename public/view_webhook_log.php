<?php
/**
 * View Webhook Test Log
 */

header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/../storage/logs/webhook_test.log';

echo "=== WEBHOOK TEST LOG ===\n";
echo "Log file: {$logFile}\n\n";

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if (empty($content)) {
        echo "Log file exists but is empty.\n";
        echo "No webhook calls have been received yet.\n";
    } else {
        echo $content;
    }
} else {
    echo "Log file does not exist yet.\n";
    echo "No webhook calls have been received.\n";
}

echo "\n\n=== HOW TO TEST ===\n";
echo "1. Set webhook to test endpoint:\n";
echo "   https://api.telegram.org/bot[TOKEN]/setWebhook?url=https://support.dpdcdev229.dpdatacenter.com/api/telegram/test_webhook.php\n\n";
echo "2. Send a message to your bot\n";
echo "3. Refresh this page to see if the webhook was called\n";
echo "4. After testing, restore the real webhook:\n";
echo "   https://api.telegram.org/bot[TOKEN]/setWebhook?url=https://support.dpdcdev229.dpdatacenter.com/api/telegram/webhook/1\n";
