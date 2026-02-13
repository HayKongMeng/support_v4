<?php
header('Content-Type: text/plain; charset=utf-8');

$logFile = dirname(__DIR__) . '/storage/logs/telegram_direct.log';

echo "=== DIRECT WEBHOOK LOG ===\n\n";
echo "Log file: {$logFile}\n";
echo "Exists: " . (file_exists($logFile) ? 'Yes' : 'No') . "\n\n";

if (file_exists($logFile)) {
    echo "=== CONTENTS ===\n\n";
    echo file_get_contents($logFile);

    if (isset($_GET['clear'])) {
        file_put_contents($logFile, '');
        echo "\n\n[Log cleared]\n";
    }
} else {
    echo "No log file yet. Send a message to the bot first.\n";
}

echo "\n\nTo clear: ?clear=1\n";
