<?php
/**
 * Check Webhook Status and Clear Pending Updates
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Services\Telegram\TelegramBot;

echo "<h1>Webhook Status & Clear Pending Updates</h1>";
echo "<hr>";

try {
    $app = App::getInstance();
    $db = $app->db();
    
    $config = $db->selectOne(
        "SELECT * FROM telegram_configs WHERE company_id = 1 AND is_active = 1"
    );
    
    if (!$config) {
        echo "❌ No config found!<br>";
        exit;
    }
    
    $botToken = $config['bot_token'];
    
    echo "<strong>Current Webhook Info:</strong><br>";
    
    // Direct curl to get webhook info
    $url = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $info = json_decode($response, true)['result'] ?? [];
    
    echo "<pre>";
    echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "</pre>";
    
    if (isset($_GET['delete'])) {
        echo "<strong>Deleting webhook to clear pending updates...</strong><br>";
        
        // Direct curl to delete
        $botToken = $config['bot_token'];
        $url = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['drop_pending_updates' => true]),
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        
        if ($result['ok']) {
            echo "✓ Webhook deleted and pending updates cleared!<br><br>";
            
            echo "<strong>Re-setting webhook...</strong><br>";
            
            // Use the correct URL without /public
            $baseUrl = $app->config('app.url');
            $baseUrl = rtrim(str_replace('/public', '', $baseUrl), '/');
            $webhookUrl = $baseUrl . '/api/telegram/webhook/1';
            
            $url = "https://api.telegram.org/bot{$botToken}/setWebhook";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['url' => $webhookUrl]),
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);
            
            if ($result['ok']) {
                echo "✓ Webhook re-set to: {$webhookUrl}<br>";
            } else {
                echo "❌ Failed to set webhook: " . ($result['description'] ?? 'Unknown error') . "<br>";
            }
            
            echo "<br><a href='check_webhook.php'>Check Status Again</a>";
        } else {
            echo "❌ Failed to delete webhook: " . ($result['description'] ?? 'Unknown error') . "<br>";
        }
        echo "<hr>";
    }
    
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
}
