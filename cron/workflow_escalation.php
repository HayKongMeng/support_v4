<?php

/**
 * Workflow Escalation CRON Job
 *
 * Processes hierarchical workflow deadlines and escalates tickets
 * to the next configured workflow step.
 *
 * Example CRON:
 * *\/2 * * * * /opt/cpanel/ea-php82/root/usr/bin/php /path/to/project/cron/workflow_escalation.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv->load();
}

$config = require BASE_PATH . '/config/database.php';
$db = new App\Core\Database($config);

echo '[' . date('Y-m-d H:i:s') . "] Starting workflow escalation...\n";

try {
    $companies = $db->select(
        "SELECT DISTINCT company_id
         FROM ticket_workflow_states
         WHERE status = 'in_progress'"
    );

    if (empty($companies)) {
        echo '[' . date('Y-m-d H:i:s') . "] No active workflow states.\n";
        exit(0);
    }

    $totals = [
        'completed' => 0,
        'escalated' => 0,
        'skipped' => 0,
    ];

    foreach ($companies as $row) {
        $companyId = (int) ($row['company_id'] ?? 0);
        if ($companyId <= 0) {
            continue;
        }

        $router = new App\Services\Workflow\WorkflowRouter($db, $companyId);
        $stats = $router->processDueEscalations(200);

        $totals['completed'] += (int) ($stats['completed'] ?? 0);
        $totals['escalated'] += (int) ($stats['escalated'] ?? 0);
        $totals['skipped'] += (int) ($stats['skipped'] ?? 0);

        echo '[' . date('Y-m-d H:i:s') . "] Company {$companyId}: escalated={$stats['escalated']}, completed={$stats['completed']}\n";
    }

    echo '[' . date('Y-m-d H:i:s') . '] Done. escalated=' . $totals['escalated'] . ', completed=' . $totals['completed'] . ", skipped={$totals['skipped']}\n";
    exit(0);
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] Fatal: ' . $e->getMessage() . "\n";
    exit(1);
}
