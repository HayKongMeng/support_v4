<?php
/**
 * Direct Webhook Delete - Bypass TelegramBot class
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;

echo "<h1>Direct Webhook Delete</h1>";
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
    
    echo "<strong>Step 1: Delete webhook...</strong><br>";
    
    // Direct curl call to delete webhook with drop_pending_updates
    $url = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
    $params = ['drop_pending_updates' => true];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP {$httpCode}<br>";
    $result = json_decode($response, true);
    
    if ($result['ok']) {
        echo "✓ Webhook deleted and pending updates cleared!<br><br>";
    } else {
        echo "❌ Failed: " . ($result['description'] ?? 'Unknown error') . "<br>";
        exit;
    }
    
    echo "<strong>Step 2: Re-set webhook...</strong><br>";
    
    // Use the correct URL without /public/
    $webhookUrl = 'https://support.dpdc512.dpdatacenter.com/api/telegram/webhook/1';
    
    // Direct curl call to set webhook
    $url = "https://api.telegram.org/bot{$botToken}/setWebhook";
    $params = ['url' => $webhookUrl];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP {$httpCode}<br>";
    $result = json_decode($response, true);
    
    if ($result['ok']) {
        echo "✓ Webhook re-set to:<br>";
        echo "{$webhookUrl}<br><br>";
    } else {
        echo "❌ Failed: " . ($result['description'] ?? 'Unknown error') . "<br>";
        exit;
    }
    
    echo "<strong>Done!</strong><br>";
    echo "<a href='check_webhook.php'>Check webhook status</a>";
    
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
    echo $e->getFile() . ":" . $e->getLine();
}
