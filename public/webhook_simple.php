<?php
/**
 * Super Simple Webhook Test
 * No dependencies at all
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== SIMPLE WEBHOOK TEST ===\n\n";

echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "URI: " . $_SERVER['REQUEST_URI'] . "\n\n";

// Try to load the app
echo "=== TESTING APP BOOTSTRAP ===\n\n";

$basePath = dirname(__DIR__);
echo "Base path: {$basePath}\n";

$autoloadPath = $basePath . '/vendor/autoload.php';
echo "Autoload path: {$autoloadPath}\n";
echo "Autoload exists: " . (file_exists($autoloadPath) ? 'Yes' : 'No') . "\n\n";

if (!file_exists($autoloadPath)) {
    echo "ERROR: vendor/autoload.php not found!\n";
    echo "Run: composer install\n";
    exit;
}

echo "Loading autoload...\n";
require $autoloadPath;
echo "Autoload loaded OK\n\n";

echo "=== TESTING CLASSES ===\n\n";

$classes = [
    'App\\Core\\App',
    'App\\Core\\Database',
    'App\\Services\\Telegram\\TelegramWebhook',
    'App\\Services\\Telegram\\TelegramBot',
];

foreach ($classes as $class) {
    echo "{$class}: " . (class_exists($class) ? 'OK' : 'NOT FOUND') . "\n";
}

echo "\n=== TESTING DATABASE ===\n\n";

try {
    $db = new \App\Core\Database();
    echo "Database connection: OK\n";

    $config = $db->selectOne("SELECT * FROM telegram_configs WHERE is_active = 1 LIMIT 1");
    if ($config) {
        echo "Telegram config found for company: {$config['company_id']}\n";
    } else {
        echo "No active telegram config found\n";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING TELEGRAMWEBHOOK ===\n\n";

try {
    $db = new \App\Core\Database();
    $handler = new \App\Services\Telegram\TelegramWebhook($db, 1);
    echo "TelegramWebhook instantiated: OK\n";
} catch (Exception $e) {
    echo "TelegramWebhook error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== ALL TESTS COMPLETE ===\n";
