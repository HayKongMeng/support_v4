<?php
/**
 * Telegram Webhook Setup & Test Helper
 * Shows status and helps configure the webhook
 */

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$debugLog = $logDir . '/telegram_webhook_setup.log';

function log_msg($msg) {
    global $debugLog;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($debugLog, "[{$timestamp}] {$msg}\n", FILE_APPEND);
}

log_msg("=== WEBHOOK SETUP PAGE LOADED ===");
log_msg("Method: " . $_SERVER['REQUEST_METHOD']);
log_msg("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Get production URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Remove /public from the path since it's not in the URL
$currentUrl = $protocol . $host . '/telegram_webhook_test.php';
$debugFileUrl = $protocol . $host . '/telegram_full_debug.php';

?><!DOCTYPE html>
<html>
<head>
    <title>Telegram Webhook Setup</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .box { background: #252526; border: 1px solid #3e3e42; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .title { color: #4ec9b0; font-weight: bold; font-size: 18px; }
        .success { color: #4ec9b0; }
        .error { color: #f14c4c; }
        .warning { color: #dcdcaa; }
        .code { background: #1e1e1e; padding: 10px; margin: 10px 0; border-left: 3px solid #569cd6; overflow-x: auto; }
        a { color: #569cd6; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <div class="title">🤖 Telegram Webhook Setup</div>
        <p>This page helps you configure and test the Telegram webhook.</p>
    </div>

    <div class="box">
        <div class="title">Step 1: Set Webhook URL</div>
        <p>Your debug webhook URL:</p>
        <div class="code"><?php echo htmlspecialchars($debugFileUrl); ?></div>
        
        <p>Set this as your webhook by making a POST request (replace BOT_TOKEN):</p>
        <div class="code">
curl -X POST "https://api.telegram.org/bot[BOT_TOKEN]/setWebhook" \<br>
&nbsp;&nbsp;-H "Content-Type: application/json" \<br>
&nbsp;&nbsp;-d '{"url":"<?php echo $protocol . $host; ?>/api/telegram/webhook/1"}'
        </div>

        <p><span class="warning">⚠️  Replace [BOT_TOKEN] with your actual bot token!</span></p>
    </div>

    <div class="box">
        <div class="title">Step 2: Send Test Message</div>
        <p>After setting the webhook, send <code>/start</code> to your bot in Telegram.</p>
        <p>This will make Telegram POST to the webhook URL and log everything.</p>
    </div>

    <div class="box">
        <div class="title">Step 3: Check Logs</div>
        <p>View the debug log file:</p>
        <div class="code"><?php echo htmlspecialchars($logDir . '/telegram_complete_debug.log'); ?></div>
        
        <p>Or this setup log:</p>
        <div class="code"><?php echo htmlspecialchars($debugLog); ?></div>
    </div>

    <div class="box">
        <div class="title">Database Status</div>
        <?php
        try {
            define('BASE_PATH', dirname(__DIR__));
            require BASE_PATH . '/vendor/autoload.php';
            $app = \App\Core\App::getInstance();
            $db = $app->db();
            
            // Check config
            $config = $db->selectOne("SELECT id, company_id, bot_token FROM telegram_configs WHERE company_id = 1 AND is_active = 1");
            
            if ($config) {
                echo '<span class="success">✓ Telegram config found for company 1</span><br>';
                echo 'Bot token prefix: ' . substr($config['bot_token'], 0, 20) . '...<br>';
            } else {
                echo '<span class="error">✗ No active Telegram config for company 1</span><br>';
                $any = $db->selectOne("SELECT * FROM telegram_configs LIMIT 1");
                if ($any) {
                    echo "Found config: company_id={$any['company_id']}, is_active={$any['is_active']}<br>";
                }
            }
            
            // Test API
            if ($config) {
                $testUrl = "https://api.telegram.org/bot{$config['bot_token']}/getMe";
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $testUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $data = json_decode($response, true);
                if ($data && $data['ok']) {
                    echo '<span class="success">✓ Bot API is working!</span><br>';
                    echo 'Bot: @' . $data['result']['username'] . '<br>';
                } else {
                    echo '<span class="error">✗ Bot API error: ' . ($data['description'] ?? 'unknown') . '</span><br>';
                }
            }
        } catch (\Throwable $e) {
            echo '<span class="error">✗ Error: ' . $e->getMessage() . '</span><br>';
            log_msg("Error: " . $e->getMessage());
        }
        ?>
    </div>

    <div class="box">
        <div class="title">Current Webhook Info</div>
        <?php
        try {
            $config = $db->selectOne("SELECT bot_token FROM telegram_configs WHERE company_id = 1 LIMIT 1");
            if ($config) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://api.telegram.org/bot{$config['bot_token']}/getWebhookInfo",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
                
                $data = json_decode($response, true);
                if ($data && $data['ok']) {
                    $webhook = $data['result'];
                    echo 'URL: ' . ($webhook['url'] ?: '<span class="error">Not set</span>') . '<br>';
                    echo 'Pending updates: ' . $webhook['pending_update_count'] . '<br>';
                    if (!empty($webhook['last_error_message'])) {
                        echo '<span class="error">Last error: ' . $webhook['last_error_message'] . '</span><br>';
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silent
        }
        ?>
    </div>

</body>
</html>
<?php
log_msg("=== PAGE RENDERED ===");
