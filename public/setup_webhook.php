<?php
/**
 * Setup Telegram Webhook
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Services\Telegram\TelegramBot;

echo "<h1>Setup Telegram Webhook</h1>";
echo "<hr>";

try {
    $app = App::getInstance();
    $db = $app->db();
    
    // Get config
    $config = $db->selectOne(
        "SELECT * FROM telegram_configs WHERE company_id = 1 AND is_active = 1"
    );
    
    if (!$config) {
        echo "❌ No Telegram config found!<br>";
        exit;
    }
    
    echo "✓ Config found<br>";
    echo "Bot token: " . substr($config['bot_token'], 0, 20) . "...<br><br>";
    
    // Setup webhook
    $bot = new TelegramBot($db, $config['bot_token']);
    
    $webhookUrl = $app->config('app.url') . '/api/telegram/webhook/1';
    
    echo "<strong>Setting webhook URL:</strong><br>";
    echo $webhookUrl . "<br><br>";
    
    try {
        $result = $bot->setWebhook($webhookUrl);
        echo "✓ Webhook set successfully!<br>";
        echo "<pre>";
        echo json_encode($result, JSON_PRETTY_PRINT);
        echo "</pre><br>";
    } catch (\Exception $e) {
        echo "❌ Failed to set webhook!<br>";
        echo "Error: " . $e->getMessage() . "<br><br>";
    }
    
    // Get webhook info
    echo "<strong>Current Webhook Info:</strong><br>";
    $info = $bot->getWebhookInfo();
    
    echo "<pre>";
    echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "</pre>";
    
    if (isset($info['pending_update_count']) && $info['pending_update_count'] > 0) {
        echo "<br><strong>⚠ Warning:</strong> There are " . $info['pending_update_count'] . " pending updates!<br>";
        echo "These are messages that failed to deliver. They will be retried.<br>";
    }
    
    if (isset($info['last_error_message'])) {
        echo "<br><strong>❌ Last Error:</strong><br>";
        echo $info['last_error_message'] . "<br>";
        echo "Error date: " . date('Y-m-d H:i:s', $info['last_error_date']) . "<br>";
    }
    
} catch (\Throwable $e) {
    echo "❌ <strong>ERROR:</strong><br>";
    echo $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

echo "<hr>";
echo "Done!";
