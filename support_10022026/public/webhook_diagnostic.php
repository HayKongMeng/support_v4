<?php
/**
 * Telegram Webhook Diagnostic Tool
 * Tests the complete webhook processing flow
 */

require_once __DIR__ . '/../src/Core/App.php';

use App\Core\App;
use App\Core\Database;

$app = new App();
$db = new Database();

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Telegram Webhook Diagnostic</h1>";
echo "<pre style='background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px;'>";

$errors = [];
$warnings = [];

// Test 1: Check required tables
echo "\n<span style='color: #569cd6;'>═══ DATABASE TABLES CHECK ═══</span>\n\n";

$requiredTables = [
    'companies' => 'Multi-tenant companies',
    'users' => 'Users and customers',
    'telegram_configs' => 'Telegram bot configuration',
    'telegram_user_states' => 'Conversation state tracking',
    'tickets' => 'Support tickets',
    'ticket_messages' => 'Ticket messages',
    'categories' => 'Ticket categories',
    'activity_logs' => 'Activity logging',
];

foreach ($requiredTables as $table => $description) {
    try {
        $result = $db->select("SHOW TABLES LIKE ?", [$table]);
        if (!empty($result)) {
            echo "<span style='color: #4ec9b0;'>✓</span> {$table} - {$description}\n";
        } else {
            echo "<span style='color: #f14c4c;'>✗</span> {$table} - <span style='color: #f14c4c;'>TABLE MISSING!</span>\n";
            $errors[] = "Missing table: {$table}";
        }
    } catch (Exception $e) {
        echo "<span style='color: #f14c4c;'>✗</span> {$table} - Error: {$e->getMessage()}\n";
        $errors[] = "Error checking table {$table}: " . $e->getMessage();
    }
}

// Test 2: Check telegram_configs
echo "\n<span style='color: #569cd6;'>═══ TELEGRAM CONFIGURATION ═══</span>\n\n";

try {
    $config = $db->selectOne("SELECT * FROM telegram_configs WHERE is_active = 1 LIMIT 1");
    if ($config) {
        echo "<span style='color: #4ec9b0;'>✓</span> Active config found\n";
        echo "  Company ID: {$config['company_id']}\n";
        echo "  Bot Token: " . substr($config['bot_token'], 0, 15) . "...\n";
        echo "  Bot Username: " . ($config['bot_username'] ?: 'Not set') . "\n";
        echo "  Webhook Secret: " . ($config['webhook_secret'] ? 'Set' : 'Not set') . "\n";
        echo "  Default Category: " . ($config['default_category_id'] ?: 'Not set') . "\n";

        // Check if company exists and is active
        $company = $db->selectOne(
            "SELECT * FROM companies WHERE id = ? AND is_active = 1",
            [$config['company_id']]
        );

        if ($company) {
            echo "\n<span style='color: #4ec9b0;'>✓</span> Company found: {$company['name']}\n";
        } else {
            echo "\n<span style='color: #f14c4c;'>✗</span> Company ID {$config['company_id']} not found or inactive!\n";
            $errors[] = "Company not found or inactive";
        }

        // Test bot token
        echo "\n<span style='color: #569cd6;'>═══ BOT API TEST ═══</span>\n\n";

        $testUrl = "https://api.telegram.org/bot{$config['bot_token']}/getMe";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            echo "<span style='color: #f14c4c;'>✗</span> CURL Error: {$curlError}\n";
            $errors[] = "Cannot connect to Telegram API: {$curlError}";
        } else {
            $botData = json_decode($response, true);
            if ($botData['ok'] ?? false) {
                echo "<span style='color: #4ec9b0;'>✓</span> Bot API working\n";
                echo "  Bot Username: @{$botData['result']['username']}\n";
                echo "  Bot ID: {$botData['result']['id']}\n";
            } else {
                echo "<span style='color: #f14c4c;'>✗</span> Invalid bot token: " . ($botData['description'] ?? 'Unknown error') . "\n";
                $errors[] = "Invalid bot token";
            }
        }

        // Test webhook simulation
        echo "\n<span style='color: #569cd6;'>═══ WEBHOOK SIMULATION TEST ═══</span>\n\n";

        // Simulate a /start message
        $simulatedUpdate = [
            'update_id' => 999999999,
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => 123456789,
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                ],
                'chat' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/start',
            ],
        ];

        echo "Simulating /start message from chat_id: 123456789\n\n";

        // Test TelegramWebhook handler
        try {
            // Check if TelegramWebhook class exists
            if (!class_exists('App\Services\Telegram\TelegramWebhook')) {
                echo "<span style='color: #f14c4c;'>✗</span> TelegramWebhook class not found!\n";
                $errors[] = "TelegramWebhook class not found";
            } else {
                echo "<span style='color: #4ec9b0;'>✓</span> TelegramWebhook class exists\n";

                // Try to instantiate
                $handler = new \App\Services\Telegram\TelegramWebhook($db, (int) $config['company_id']);
                echo "<span style='color: #4ec9b0;'>✓</span> TelegramWebhook instantiated successfully\n";

                // Don't actually send message (it would go to fake chat_id)
                // But check that all required methods exist
                $requiredMethods = ['handle'];
                foreach ($requiredMethods as $method) {
                    if (method_exists($handler, $method)) {
                        echo "<span style='color: #4ec9b0;'>✓</span> Method '{$method}' exists\n";
                    } else {
                        echo "<span style='color: #f14c4c;'>✗</span> Method '{$method}' missing!\n";
                        $errors[] = "TelegramWebhook missing method: {$method}";
                    }
                }
            }
        } catch (Exception $e) {
            echo "<span style='color: #f14c4c;'>✗</span> Error: {$e->getMessage()}\n";
            echo "  File: {$e->getFile()}:{$e->getLine()}\n";
            $errors[] = "Exception during webhook simulation: " . $e->getMessage();
        }

    } else {
        echo "<span style='color: #f14c4c;'>✗</span> No active Telegram configuration found!\n";
        $errors[] = "No active Telegram configuration";

        // Check if any configs exist
        $allConfigs = $db->select("SELECT * FROM telegram_configs");
        if (!empty($allConfigs)) {
            echo "\nFound " . count($allConfigs) . " config(s) but none are active:\n";
            foreach ($allConfigs as $c) {
                echo "  - Company ID: {$c['company_id']}, is_active: {$c['is_active']}\n";
            }
        }
    }
} catch (Exception $e) {
    echo "<span style='color: #f14c4c;'>✗</span> Database error: {$e->getMessage()}\n";
    $errors[] = "Database error: " . $e->getMessage();
}

// Test 3: Check webhook URL configuration
echo "\n<span style='color: #569cd6;'>═══ WEBHOOK URL CHECK ═══</span>\n\n";

$appUrl = $_ENV['APP_URL'] ?? 'Not set';
echo "APP_URL from .env: {$appUrl}\n";

if (strpos($appUrl, 'https://') !== 0) {
    echo "<span style='color: #dcdcaa;'>⚠</span> Warning: Telegram requires HTTPS for webhooks\n";
    $warnings[] = "APP_URL is not HTTPS";
}

if (isset($config) && $config) {
    $expectedWebhookUrl = $appUrl . '/api/telegram/webhook/' . $config['company_id'];
    echo "Expected webhook URL: {$expectedWebhookUrl}\n";

    // Check current webhook
    $webhookInfoUrl = "https://api.telegram.org/bot{$config['bot_token']}/getWebhookInfo";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhookInfoUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $webhookResponse = curl_exec($ch);
    curl_close($ch);

    $webhookData = json_decode($webhookResponse, true);
    if ($webhookData['ok'] ?? false) {
        $currentUrl = $webhookData['result']['url'] ?? '';
        echo "\nCurrent webhook URL: " . ($currentUrl ?: 'Not set') . "\n";

        if ($currentUrl !== $expectedWebhookUrl) {
            echo "<span style='color: #dcdcaa;'>⚠</span> Webhook URL doesn't match expected!\n";
            $warnings[] = "Webhook URL mismatch";
        }

        if (!empty($webhookData['result']['last_error_message'])) {
            echo "<span style='color: #f14c4c;'>✗</span> Last error: {$webhookData['result']['last_error_message']}\n";
            echo "  Error date: " . date('Y-m-d H:i:s', $webhookData['result']['last_error_date']) . "\n";
            $errors[] = "Webhook error: " . $webhookData['result']['last_error_message'];
        }

        echo "Pending updates: " . ($webhookData['result']['pending_update_count'] ?? 0) . "\n";
    }
}

// Summary
echo "\n<span style='color: #569cd6;'>═══ SUMMARY ═══</span>\n\n";

if (empty($errors) && empty($warnings)) {
    echo "<span style='color: #4ec9b0;'>All checks passed! The webhook should be working.</span>\n\n";
    echo "If the bot still isn't responding:\n";
    echo "1. Check PHP error logs on the production server\n";
    echo "2. Make sure you've sent a message to the bot after setting up the webhook\n";
    echo "3. Try sending /start to the bot again\n";
} else {
    if (!empty($errors)) {
        echo "<span style='color: #f14c4c;'>ERRORS (" . count($errors) . "):</span>\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    if (!empty($warnings)) {
        echo "\n<span style='color: #dcdcaa;'>WARNINGS (" . count($warnings) . "):</span>\n";
        foreach ($warnings as $warning) {
            echo "  - {$warning}\n";
        }
    }
}

// Test with real chat_id if provided
if (isset($_GET['test_chat_id']) && isset($config) && $config) {
    echo "\n<span style='color: #569cd6;'>═══ SEND TEST MESSAGE ═══</span>\n\n";

    $chatId = (int) $_GET['test_chat_id'];
    echo "Sending test message to chat_id: {$chatId}\n";

    try {
        $bot = new \App\Services\Telegram\TelegramBot($db, $config['bot_token']);
        $result = $bot->sendMessage($chatId, "🔧 Diagnostic test message!\n\nTime: " . date('Y-m-d H:i:s') . "\nServer: " . gethostname());
        echo "<span style='color: #4ec9b0;'>✓</span> Message sent successfully!\n";
        echo "Message ID: {$result['message_id']}\n";
    } catch (Exception $e) {
        echo "<span style='color: #f14c4c;'>✗</span> Failed to send: {$e->getMessage()}\n";
    }
}

echo "\n<span style='color: #888;'>To send a test message, add ?test_chat_id=YOUR_CHAT_ID to this URL</span>\n";
echo "<span style='color: #888;'>Get your chat_id by messaging the bot and checking the error logs</span>\n";

echo "</pre>";
