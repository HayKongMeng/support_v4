<?php
/**
 * Telegram Bot Debug Script
 * Run this to diagnose why the bot isn't responding
 *
 * Usage:
 * - Visit this page to see diagnostics
 * - Add ?poll=1 to enable long polling mode (for local testing without HTTPS)
 * - Add ?test_chat_id=YOUR_ID to send a test message
 */

require_once __DIR__ . '/../src/Core/App.php';

use App\Core\App;
use App\Core\Database;
use App\Services\Telegram\TelegramWebhook;

$app = new App();
$db = new Database();

// Handle long polling mode for local development
if (isset($_GET['poll'])) {
    header('Content-Type: text/plain');
    echo "Starting long polling mode...\n";
    echo "This bypasses the webhook requirement for local testing.\n";
    echo "Press Ctrl+C to stop.\n\n";

    $config = $db->selectOne("SELECT * FROM telegram_configs WHERE is_active = 1 LIMIT 1");
    if (!$config) {
        die("ERROR: No active Telegram config found in database.\n");
    }

    echo "Bot token found for company ID: {$config['company_id']}\n";
    echo "Polling for updates...\n\n";

    $offset = 0;
    $timeout = 30;

    while (true) {
        $url = "https://api.telegram.org/bot{$config['bot_token']}/getUpdates";
        $params = [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout + 5,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!empty($data['result'])) {
            foreach ($data['result'] as $update) {
                $offset = $update['update_id'] + 1;

                echo "[" . date('H:i:s') . "] Update received: " . json_encode($update) . "\n";

                // Process the update
                try {
                    $handler = new TelegramWebhook($db, (int) $config['company_id']);
                    $handler->handle($update);
                    echo "[" . date('H:i:s') . "] Update processed successfully.\n";
                } catch (Exception $e) {
                    echo "[" . date('H:i:s') . "] Error: " . $e->getMessage() . "\n";
                }
            }
        }

        // Flush output
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
    exit;
}

echo "<h1>Telegram Bot Debug</h1>\n";
echo "<pre>\n";

// 1. Check companies
echo "\n=== COMPANIES ===\n";
$companies = $db->select("SELECT id, name, slug, is_active FROM companies");
if (empty($companies)) {
    echo "ERROR: No companies found in database!\n";
} else {
    foreach ($companies as $c) {
        echo "ID: {$c['id']}, Name: {$c['name']}, Slug: {$c['slug']}, Active: {$c['is_active']}\n";
    }
}

// 2. Check telegram configs
echo "\n=== TELEGRAM CONFIGS ===\n";
$configs = $db->select("SELECT tc.*, c.name as company_name
                        FROM telegram_configs tc
                        LEFT JOIN companies c ON tc.company_id = c.id");
if (empty($configs)) {
    echo "ERROR: No telegram configs found!\n";
    echo "You need to add a row to telegram_configs table.\n";
} else {
    foreach ($configs as $config) {
        echo "Company: {$config['company_name']} (ID: {$config['company_id']})\n";
        echo "Bot Username: {$config['bot_username']}\n";
        echo "Is Active: {$config['is_active']}\n";
        echo "Bot Token: " . substr($config['bot_token'], 0, 10) . "...\n";
        echo "Webhook URL: {$config['webhook_url']}\n";
        echo "\n";

        // Test the bot
        if ($config['is_active'] && $config['bot_token']) {
            echo "Testing bot API...\n";

            $url = "https://api.telegram.org/bot{$config['bot_token']}/getMe";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                echo "CURL ERROR: {$error}\n";
            } else {
                $data = json_decode($response, true);
                echo "HTTP Code: {$httpCode}\n";
                echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";

                if ($data['ok'] ?? false) {
                    echo "\nBot is valid! Bot username: @" . ($data['result']['username'] ?? 'unknown') . "\n";

                    // Check webhook info
                    echo "\nChecking webhook info...\n";
                    $webhookUrl = "https://api.telegram.org/bot{$config['bot_token']}/getWebhookInfo";
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $webhookUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    $webhookResponse = curl_exec($ch);
                    curl_close($ch);

                    $webhookData = json_decode($webhookResponse, true);
                    echo "Webhook Info: " . json_encode($webhookData, JSON_PRETTY_PRINT) . "\n";

                    // Check if webhook URL is correctly set
                    if (!empty($webhookData['result']['url'])) {
                        echo "\nWebhook URL is set to: {$webhookData['result']['url']}\n";

                        // Check for pending updates
                        if (isset($webhookData['result']['pending_update_count'])) {
                            echo "Pending updates: {$webhookData['result']['pending_update_count']}\n";
                        }

                        // Check for errors
                        if (!empty($webhookData['result']['last_error_message'])) {
                            echo "LAST ERROR: {$webhookData['result']['last_error_message']}\n";
                            echo "Error Date: " . date('Y-m-d H:i:s', $webhookData['result']['last_error_date']) . "\n";
                        }
                    } else {
                        echo "WARNING: No webhook URL set!\n";
                        echo "Expected webhook URL: " . ($_ENV['APP_URL'] ?? 'http://localhost') . "/support/public/api/telegram/webhook/{$config['company_id']}\n";
                    }
                } else {
                    echo "ERROR: Bot token is invalid!\n";
                }
            }
        }
    }
}

// 3. Check if webhook endpoint is accessible
echo "\n=== WEBHOOK ENDPOINT CHECK ===\n";
$appUrl = $_ENV['APP_URL'] ?? 'http://localhost/support';
echo "APP_URL from .env: {$appUrl}\n";

// Check HTTPS requirement
if (strpos($appUrl, 'https://') !== 0) {
    echo "\n⚠️  WARNING: TELEGRAM REQUIRES HTTPS FOR WEBHOOKS! ⚠️\n";
    echo "Your APP_URL is using HTTP. Telegram will NOT send updates to HTTP URLs.\n\n";
    echo "SOLUTIONS:\n";
    echo "1. For local development, use long polling mode:\n";
    echo "   Visit: " . $_SERVER['REQUEST_URI'] . "?poll=1\n\n";
    echo "2. Use ngrok to expose your local server with HTTPS:\n";
    echo "   - Download ngrok from https://ngrok.com/\n";
    echo "   - Run: ngrok http 80\n";
    echo "   - Update .env APP_URL to the ngrok HTTPS URL\n";
    echo "   - Re-setup the webhook\n\n";
    echo "3. Deploy to a server with HTTPS\n";
}

// 4. Test sending a message (requires a chat_id)
echo "\n=== MANUAL TEST ===\n";
echo "To test sending a message manually, add ?test_chat_id=YOUR_CHAT_ID to this URL\n";
echo "You can get your chat_id by messaging the bot and checking the logs.\n";

if (isset($_GET['test_chat_id']) && !empty($configs)) {
    $chatId = (int) $_GET['test_chat_id'];
    $config = $configs[0];

    echo "\nSending test message to chat_id: {$chatId}\n";

    $url = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => 'Test message from debug script! ' . date('Y-m-d H:i:s'),
        'parse_mode' => 'HTML',
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "CURL ERROR: {$error}\n";
    } else {
        $data = json_decode($response, true);
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n</pre>";
