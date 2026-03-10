<?php
require 'vendor/autoload.php';
$app = \App\Core\App::getInstance();
$db = $app->db();

echo "=== PUSH SUBSCRIPTIONS REPORT ===\n\n";

$all = $db->select('SELECT * FROM push_subscriptions', []);
echo "Total subscriptions: " . count($all) . "\n\n";

if (empty($all)) {
    echo "NO SUBSCRIPTIONS FOUND IN DATABASE!\n";
    echo "Agents need to subscribe to web push notifications in their browser.\n";
} else {
    echo "Subscriptions by user:\n";
    $byUser = [];
    foreach ($all as $sub) {
        $uid = $sub['user_id'];
        if (!isset($byUser[$uid])) {
            $byUser[$uid] = [];
        }
        $byUser[$uid][] = $sub;
    }
    
    foreach ($byUser as $uid => $subs) {
        $user = $db->selectOne("SELECT id, name, email FROM users WHERE id = ?", [$uid]);
        echo "\n--- User ID $uid: " . ($user['name'] ?? 'UNKNOWN') . " (" . ($user['email'] ?? 'no email') . ") ---\n";
        echo "Subscriptions: " . count($subs) . "\n";
        foreach ($subs as $sub) {
            echo "  - Endpoint: " . substr($sub['endpoint'], 0, 50) . "...\n";
            echo "    Active: " . ($sub['is_active'] ? 'YES' : 'NO') . "\n";
            echo "    Created: " . $sub['created_at'] . "\n";
        }
    }
}

echo "\n\n=== SOLUTION ===\n";
echo "To receive push notifications, agent must:\n";
echo "1. Open the portal/dashboard\n";
echo "2. Allow browser notifications when prompted\n";
echo "3. Click 'Enable Push Notifications' button (if available)\n";
echo "4. Browser will send subscription to server and store in push_subscriptions table\n";
