<?php

/**
 * Run Database Migrations
 *
 * Run from command line:
 *   php run-migrations.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\App;

$app = App::getInstance();
$db = $app->db();

echo "\nRunning Database Migrations...\n\n";

$migrations = [
    'create_telegram_core_tables' => 'database/migrations/create_telegram_core_tables.sql',
    'create_telegram_miniapp_profiles' => 'database/migrations/create_telegram_miniapp_profiles.sql',
    'create_push_subscriptions' => 'database/migrations/create_push_subscriptions.sql',
    'create_hierarchical_workflow' => 'database/migrations/create_hierarchical_workflow.sql',
    'extend_workflow_dynamic_reporting' => 'database/migrations/extend_workflow_dynamic_reporting.sql',
    'create_category_routing_rules' => 'database/migrations/create_category_routing_rules.sql',
];

foreach ($migrations as $name => $filepath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filepath);

    if (!file_exists($absolutePath)) {
        echo "Skipped: {$name} (file not found)\n";
        continue;
    }

    try {
        $sql = file_get_contents($absolutePath);

        // Remove SQL single-line comments before splitting statements.
        $lines = preg_split('/\R/', $sql ?: '');
        $cleanLines = [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if (str_starts_with($trim, '--')) {
                continue;
            }
            $cleanLines[] = $line;
        }
        $sql = implode("\n", $cleanLines);

        // Split by semicolon and execute each statement.
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt)
        );

        foreach ($statements as $statement) {
            if ($statement !== '') {
                $db->getPdo()->exec($statement);
            }
        }

        echo "Migrated: {$name}\n";
    } catch (\Throwable $e) {
        echo "Error: {$name} - " . $e->getMessage() . "\n";
    }
}

echo "\nMigrations complete!\n\n";
