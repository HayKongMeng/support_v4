<?php

/**
 * Email Processor CRON Job
 *
 * This script should be run periodically (e.g., every 5 minutes) to:
 * - Fetch new emails from IMAP
 * - Create tickets from new emails
 * - Add replies to existing tickets
 *
 * Setup: Add to crontab (runs every 5 minutes)
 * CRON: 0/5 * * * * php /path/to/support/cron/email_processor.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv->load();
}

// Initialize database
$config = require BASE_PATH . '/config/database.php';

$dsn = sprintf(
    '%s:host=%s;port=%s;dbname=%s;charset=%s',
    $config['driver'],
    $config['host'],
    $config['port'],
    $config['database'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    $db = new App\Core\Database($config);
} catch (PDOException $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting email processing...\n";

try {
    $processor = new App\Services\Email\EmailProcessor($db);
    $results = $processor->processAll();

    foreach ($results as $result) {
        if ($result['status'] === 'success') {
            echo "[" . date('Y-m-d H:i:s') . "] {$result['company']}: Processed {$result['processed']} emails\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] {$result['company']}: ERROR - {$result['error']}\n";
        }
    }

    $totalProcessed = array_sum(array_column(
        array_filter($results, fn($r) => $r['status'] === 'success'),
        'processed'
    ));

    echo "[" . date('Y-m-d H:i:s') . "] Completed. Total emails processed: {$totalProcessed}\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
