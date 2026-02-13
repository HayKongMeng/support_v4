<?php
/**
 * Log Viewer - Check recent PHP error logs
 *
 * WARNING: Remove this file in production after debugging!
 */

require_once __DIR__ . '/../src/Core/App.php';

// Simple security - require a token
$secretToken = 'debug_telegram_2024';
if (($_GET['token'] ?? '') !== $secretToken) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied. Add ?token=' . $secretToken . ' to access.');
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Recent Telegram-related Log Entries</h1>";
echo "<pre style='background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; max-height: 800px; overflow: auto;'>";

// Try to find the error log file
$possibleLogFiles = [
    ini_get('error_log'),
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log',
    '/var/log/nginx/error.log',
    'C:/wamp64/logs/php_error.log',
    'C:/wamp64/logs/apache_error.log',
];

$logFile = null;
foreach ($possibleLogFiles as $file) {
    if ($file && file_exists($file) && is_readable($file)) {
        $logFile = $file;
        break;
    }
}

if ($logFile) {
    echo "Reading from: {$logFile}\n\n";

    // Read last 500 lines
    $lines = [];
    $fp = fopen($logFile, 'r');
    if ($fp) {
        // Go to end of file
        fseek($fp, -1, SEEK_END);
        $pos = ftell($fp);

        // Read backwards to get last 500 lines
        $lineCount = 0;
        $buffer = '';

        while ($pos > 0 && $lineCount < 500) {
            fseek($fp, $pos - 1);
            $char = fgetc($fp);
            if ($char === "\n") {
                if (!empty($buffer)) {
                    $lines[] = strrev($buffer);
                    $lineCount++;
                    $buffer = '';
                }
            } else {
                $buffer .= $char;
            }
            $pos--;
        }
        if (!empty($buffer)) {
            $lines[] = strrev($buffer);
        }
        fclose($fp);
    }

    $lines = array_reverse($lines);

    // Filter for Telegram-related entries
    $telegramLines = array_filter($lines, function($line) {
        return stripos($line, 'telegram') !== false ||
               stripos($line, 'TelegramWebhook') !== false ||
               stripos($line, 'TelegramBot') !== false;
    });

    if (empty($telegramLines)) {
        echo "<span style='color: #dcdcaa;'>No Telegram-related log entries found in the last 500 lines.</span>\n\n";
        echo "This could mean:\n";
        echo "1. The webhook is not being called at all\n";
        echo "2. Errors are being logged elsewhere\n";
        echo "3. No errors are occurring\n\n";

        echo "Last 20 general log entries:\n";
        echo str_repeat('-', 60) . "\n";
        $lastLines = array_slice($lines, -20);
        foreach ($lastLines as $line) {
            echo htmlspecialchars(trim($line)) . "\n";
        }
    } else {
        echo "Found " . count($telegramLines) . " Telegram-related entries:\n";
        echo str_repeat('-', 60) . "\n";

        foreach ($telegramLines as $line) {
            $line = trim($line);
            // Highlight different log levels
            if (stripos($line, 'error') !== false) {
                echo "<span style='color: #f14c4c;'>" . htmlspecialchars($line) . "</span>\n";
            } elseif (stripos($line, 'warning') !== false) {
                echo "<span style='color: #dcdcaa;'>" . htmlspecialchars($line) . "</span>\n";
            } else {
                echo htmlspecialchars($line) . "\n";
            }
        }
    }
} else {
    echo "<span style='color: #f14c4c;'>Could not find or read error log file.</span>\n\n";
    echo "Checked locations:\n";
    foreach ($possibleLogFiles as $file) {
        $exists = $file && file_exists($file);
        $readable = $exists && is_readable($file);
        $status = $exists ? ($readable ? '✓ Exists, readable' : '⚠ Exists, not readable') : '✗ Not found';
        echo "  {$file} - {$status}\n";
    }

    echo "\nPHP error_log setting: " . (ini_get('error_log') ?: 'Not set') . "\n";
    echo "log_errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "\n";
    echo "display_errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "\n";
}

echo "\n\n";
echo "<span style='color: #888;'>Refresh this page after sending a message to your bot to see new log entries.</span>\n";

echo "</pre>";

echo "<p><strong>Security Note:</strong> Remove this file after debugging!</p>";
