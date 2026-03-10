<?php
/**
 * Telegram Bot Test - Simulates incoming webhook locally
 * Visit this in browser or run: php telegram_bot_test.php
 */

define('BASE_PATH', dirname(__DIR__));

// Load app
require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Services\Telegram\TelegramWebhook;
use App\Services\Telegram\TelegramBot;

echo "<h1>Telegram Bot Test</h1>";
echo "<hr>";

try {
    // Initialize app
    echo "Loading app...<br>";
    $app = App::getInstance();
    $db = $app->db();
    echo "✓ App loaded<br><br>";

    // Check config
    echo "<strong>Checking Telegram Config:</strong><br>";
    $config = $db->selectOne(
        "SELECT * FROM telegram_configs WHERE company_id = 1 AND is_active = 1"
    );
    
    if (!$config) {
        echo "❌ No Telegram config found!<br>";
        exit;
    }
    
    echo "✓ Config found<br>";
    echo "Bot token: " . substr($config['bot_token'], 0, 20) . "...<br>";
    echo "Company ID: " . $config['company_id'] . "<br><br>";

    // Test bot API
    echo "<strong>Testing Bot API:</strong><br>";
    $bot = new TelegramBot($db, $config['bot_token']);
    
    try {
        $me = $bot->getMe();
        echo "✓ Bot API working!<br>";
        echo "Bot: @" . $me['username'] . "<br>";
        echo "Bot ID: " . $me['id'] . "<br><br>";
    } catch (\Exception $e) {
        echo "❌ Bot API error: " . $e->getMessage() . "<br><br>";
    }

    // Simulate webhook
    echo "<strong>Simulating Webhook:</strong><br>";
    
    $testUpdate = [
        'update_id' => rand(1000, 9999),
        'message' => [
            'message_id' => 1,
            'date' => time(),
            'chat' => [
                'id' => 768732984,
                'type' => 'private',
                'first_name' => 'Test',
            ],
            'from' => [
                'id' => 768732984,
                'is_bot' => false,
                'first_name' => 'Test',
                'username' => 'testuser',
            ],
            'text' => '/start',
        ]
    ];

    echo "Simulated update:<br>";
    echo "<pre>";
    echo json_encode($testUpdate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "</pre>";

    echo "Processing webhook...<br>";
    
    $handler = new TelegramWebhook($db, 1);
    
    // Capture output
    ob_start();
    try {
        $handler->handle($testUpdate);
        echo "✓ Webhook processed successfully!<br>";
    } catch (\Exception $e) {
        echo "❌ Webhook error: " . $e->getMessage() . "<br>";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
        echo "<pre>";
        echo $e->getTraceAsString();
        echo "</pre>";
    }
    ob_end_clean();

    echo "<br><strong>Check Telegram:</strong><br>";
    echo "If everything worked, you should have received a message from the bot!<br>";

} catch (\Throwable $e) {
    echo "❌ <strong>CRITICAL ERROR:</strong><br>";
    echo $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<pre>";
    echo $e->getTraceAsString();
    echo "</pre>";
}

echo "<hr>";
echo "Test completed!";
