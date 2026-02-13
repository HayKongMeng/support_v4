<?php

/**
 * Run Database Migrations
 * 
 * Run from command line:
 *   php run-migrations.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;

$app = App::getInstance();
$db = $app->db();

echo "\n🗄️  Running Database Migrations...\n\n";

$migrations = [
    'create_push_subscriptions' => 'database/migrations/create_push_subscriptions.sql',
];

foreach ($migrations as $name => $filepath) {
    if (!file_exists($filepath)) {
        echo "⚠️  Skipped: $name (file not found)\n";
        continue;
    }

    try {
        $sql = file_get_contents($filepath);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
        );

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->connection()->exec($statement);
            }
        }

        echo "✅ Migrated: $name\n";
    } catch (\Exception $e) {
        echo "❌ Error: $name - " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Migrations complete!\n\n";
