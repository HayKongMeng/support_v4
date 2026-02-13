<?php
/**
 * Simple Telegram Diagnostic - No dependencies
 * Use this to diagnose basic issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Simple Telegram Diagnostic</h1>";
echo "<pre style='background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px;'>";

// Step 1: Check PHP version
echo "=== PHP INFO ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "CURL Enabled: " . (function_exists('curl_init') ? 'Yes' : 'No') . "\n";
echo "PDO Enabled: " . (class_exists('PDO') ? 'Yes' : 'No') . "\n";
echo "PDO MySQL: " . (in_array('mysql', PDO::getAvailableDrivers()) ? 'Yes' : 'No') . "\n\n";

// Step 2: Load .env manually
echo "=== LOADING .ENV ===\n";
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    echo "Found .env file\n";
    $envContent = file_get_contents($envFile);
    $lines = explode("\n", $envContent);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value, '"\'');
        }
    }
    echo "DB_HOST: " . ($env['DB_HOST'] ?? 'not set') . "\n";
    echo "DB_DATABASE: " . ($env['DB_DATABASE'] ?? 'not set') . "\n";
    echo "APP_URL: " . ($env['APP_URL'] ?? 'not set') . "\n\n";
} else {
    echo "ERROR: .env file not found at {$envFile}\n\n";
    $env = [];
}

// Step 3: Test database connection
echo "=== DATABASE CONNECTION ===\n";
try {
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $database = $env['DB_DATABASE'] ?? 'support_tickets';
    $username = $env['DB_USERNAME'] ?? 'root';
    $password = $env['DB_PASSWORD'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "Connected to database successfully!\n\n";

    // Check telegram_configs table
    echo "=== TELEGRAM CONFIG ===\n";
    $stmt = $pdo->query("SELECT * FROM telegram_configs WHERE is_active = 1 LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        echo "Config found:\n";
        echo "  Company ID: {$config['company_id']}\n";
        echo "  Bot Token: " . substr($config['bot_token'], 0, 15) . "...\n";
        echo "  Bot Username: " . ($config['bot_username'] ?? 'not set') . "\n";
        echo "  Is Active: {$config['is_active']}\n\n";

        // Test bot API
        echo "=== BOT API TEST ===\n";
        $testUrl = "https://api.telegram.org/bot{$config['bot_token']}/getMe";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            echo "CURL Error: {$curlError}\n";
        } else {
            echo "HTTP Code: {$httpCode}\n";
            $data = json_decode($response, true);
            if ($data['ok'] ?? false) {
                echo "Bot is VALID!\n";
                echo "Bot Username: @{$data['result']['username']}\n";
                echo "Bot ID: {$data['result']['id']}\n\n";

                // Check webhook
                echo "=== WEBHOOK INFO ===\n";
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
                if ($webhookData['ok'] ?? false) {
                    $result = $webhookData['result'];
                    echo "Webhook URL: " . ($result['url'] ?: 'NOT SET') . "\n";
                    echo "Pending Updates: " . ($result['pending_update_count'] ?? 0) . "\n";
                    if (!empty($result['last_error_message'])) {
                        echo "LAST ERROR: {$result['last_error_message']}\n";
                        echo "Error Date: " . date('Y-m-d H:i:s', $result['last_error_date']) . "\n";
                    } else {
                        echo "No recent errors!\n";
                    }
                }
            } else {
                echo "Bot token is INVALID!\n";
                echo "Error: " . ($data['description'] ?? 'Unknown') . "\n";
            }
        }

        // Test sending a message if chat_id provided
        if (isset($_GET['chat_id'])) {
            $chatId = (int) $_GET['chat_id'];
            echo "\n=== SENDING TEST MESSAGE ===\n";
            echo "Chat ID: {$chatId}\n";

            $sendUrl = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";
            $params = [
                'chat_id' => $chatId,
                'text' => "Test from simple_diagnostic.php\nTime: " . date('Y-m-d H:i:s'),
                'parse_mode' => 'HTML',
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $sendUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $params,
                CURLOPT_TIMEOUT => 10,
            ]);
            $sendResponse = curl_exec($ch);
            $sendError = curl_error($ch);
            curl_close($ch);

            if ($sendError) {
                echo "Send Error: {$sendError}\n";
            } else {
                $sendData = json_decode($sendResponse, true);
                if ($sendData['ok'] ?? false) {
                    echo "Message sent successfully!\n";
                } else {
                    echo "Failed to send: " . ($sendData['description'] ?? 'Unknown error') . "\n";
                }
            }
        }

    } else {
        echo "ERROR: No active telegram config found!\n";

        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'telegram_configs'");
        if ($stmt->rowCount() == 0) {
            echo "TABLE telegram_configs DOES NOT EXIST!\n";
        } else {
            echo "Table exists. Checking all configs...\n";
            $stmt = $pdo->query("SELECT id, company_id, is_active FROM telegram_configs");
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($configs)) {
                echo "No configs in table at all.\n";
            } else {
                foreach ($configs as $c) {
                    echo "  ID: {$c['id']}, Company: {$c['company_id']}, Active: {$c['is_active']}\n";
                }
            }
        }
    }

    // Check telegram_user_states table
    echo "\n=== REQUIRED TABLES ===\n";
    $tables = ['companies', 'users', 'telegram_configs', 'telegram_user_states', 'tickets', 'categories'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->rowCount() > 0;
        echo ($exists ? "[OK]" : "[MISSING]") . " {$table}\n";
    }

} catch (PDOException $e) {
    echo "Database Error: {$e->getMessage()}\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. If webhook has errors, re-setup webhook\n";
echo "2. Add ?chat_id=YOUR_ID to test sending messages\n";
echo "3. Get your chat_id by sending /start to the bot\n";

echo "</pre>";
