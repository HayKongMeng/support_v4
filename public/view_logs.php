<?php
/**
 * View Log Files
 */

define('BASE_PATH', dirname(__DIR__));

$logDir = BASE_PATH . '/storage/logs/';
$requestedFile = $_GET['file'] ?? '';

echo "<h1>Log Viewer</h1>";
echo "<hr>";

// List available logs
echo "<strong>Available Logs:</strong><br>";
if (is_dir($logDir)) {
    $files = scandir($logDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $size = filesize($logDir . $file);
        echo "<a href='?file={$file}'>{$file}</a> (" . number_format($size) . " bytes)<br>";
    }
} else {
    echo "Log directory not found!<br>";
}

echo "<hr>";

// Show requested file
if ($requestedFile) {
    $filePath = $logDir . basename($requestedFile);
    
    echo "<h2>File: {$requestedFile}</h2>";
    
    if (isset($_GET['clear'])) {
        file_put_contents($filePath, '');
        echo "✓ Log cleared!<br><br>";
        echo "<a href='?file={$requestedFile}'>Refresh</a><br><hr>";
    }
    
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        echo "Size: " . number_format(strlen($content)) . " bytes<br>";
        echo "<a href='?file={$requestedFile}&clear=1'>Clear this log</a><br><br>";
        
        echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 600px;'>";
        echo htmlspecialchars($content);
        echo "</pre>";
    } else {
        echo "❌ File not found: {$filePath}";
    }
}
