<?php
/**
 * Telegram Bot - Complete Debug & Test
 * This file logs everything about incoming Telegram webhooks and API responses
 * Use this as webhook endpoint: /api/telegram/webhook/debug/1
 */

// Set up immediate logging
$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$debugLog = $logDir . '/telegram_complete_debug.log';

function debugLog($msg) {
    global $debugLog;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($debugLog, "[{$timestamp}] {$msg}\n", FILE_APPEND);
}

debugLog("========== TELEGRAM DEBUG START ==========");
debugLog("Method: " . $_SERVER['REQUEST_METHOD']);
debugLog("URI: " . $_SERVER['REQUEST_URI']);
debugLog("Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Read input
$input = file_get_contents('php://input');
debugLog("Input size: " . strlen($input) . " bytes");
debugLog("Input preview: " . substr($input, 0, 200));

// Handle GET request (for browser testing)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    debugLog("GET request detected - this is a browser test, not a webhook");
    debugLog("To test the webhook, send a message to the Telegram bot");
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => 'Debug endpoint is ready. Send a message to the bot.']);
    exit;
}

// Handle POST request (actual webhook from Telegram)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Invalid request method: {$_SERVER['REQUEST_METHOD']}");
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Try to parse JSON
$update = json_decode($input, true);
if (!$update) {
    debugLog("ERROR: Could not parse JSON");
    debugLog("Raw input: {$input}");
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$chatId = $update['message']['chat']['id'] ?? null;
$text = $update['message']['text'] ?? null;
debugLog("Chat ID: {$chatId}");
debugLog("Text: {$text}");

// Load app
try {
    debugLog("Loading application...");
    define('BASE_PATH', dirname(__DIR__));
    require BASE_PATH . '/vendor/autoload.php';
    
    debugLog("App loaded successfully");
    
    $app = \App\Core\App::getInstance();
    debugLog("App instance created");
    
    $db = $app->db();
    debugLog("Database connected");
    
    // Check telegram config
    debugLog("Looking for Telegram config for company 1...");
    $config = $db->selectOne(
        "SELECT id, company_id, is_active, bot_token FROM telegram_configs WHERE company_id = 1 AND is_active = 1"
    );
    
    if ($config) {
        debugLog("Config found! Company: {$config['company_id']}, Bot token prefix: " . substr($config['bot_token'], 0, 15) . "...");
    } else {
        debugLog("ERROR: No Telegram config found!");
        // Check if any config exists
        $any = $db->selectOne("SELECT id, company_id, is_active FROM telegram_configs LIMIT 1");
        if ($any) {
            debugLog("Found config but: company_id={$any['company_id']}, is_active={$any['is_active']}");
        } else {
            debugLog("No telegram configs in database at all!");
        }
        http_response_code(200);
        echo json_encode(['ok' => true]);
        exit;
    }
    
    // Test bot token with direct curl
    debugLog("Testing bot token with Telegram API (getMe)...");
    $testUrl = "https://api.telegram.org/bot{$config['bot_token']}/getMe";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    debugLog("Making curl request to getMe...");
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    debugLog("HTTP Code: {$httpCode}");
    debugLog("Curl Error: " . ($curlError ?: 'none'));
    debugLog("Response length: " . strlen($response));
    debugLog("Response preview: " . substr($response, 0, 300));
    
    if (!$response) {
        debugLog("ERROR: Empty response from Telegram");
    } else {
        $data = json_decode($response, true);
        if ($data && $data['ok']) {
            debugLog("✓ SUCCESS: Bot API is working!");
            debugLog("Bot username: @{$data['result']['username']}");
        } else {
            debugLog("✗ ERROR: Telegram API returned error");
            debugLog("Response: " . json_encode($data));
        }
    }
    
    // Now try to send a message
    debugLog("Attempting to send test message to chat {$chatId}...");
    
    $sendUrl = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => '🤖 <b>Bot is working!</b>' . "\n\nDebug mode active.",
        'parse_mode' => 'HTML',
    ];
    
    debugLog("Send URL: {$sendUrl}");
    debugLog("Send chat_id: {$chatId}");
    debugLog("Send text length: " . strlen($params['text']));
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $sendUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    
    debugLog("Sending message...");
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    debugLog("Send HTTP Code: {$httpCode}");
    debugLog("Send Curl Error: " . ($curlError ?: 'none'));
    debugLog("Send Response length: " . strlen($response));
    debugLog("Send Response: " . substr($response, 0, 300));
    
    $data = json_decode($response, true);
    if ($data && $data['ok']) {
        debugLog("✓ SUCCESS: Message sent!");
        debugLog("Message ID: {$data['result']['message_id']}");
    } else {
        debugLog("✗ ERROR: Message send failed");
        debugLog("Telegram error: " . json_encode($data));
    }
    
} catch (\Throwable $e) {
    debugLog("✗ EXCEPTION THROWN!");
    debugLog("Message: " . $e->getMessage());
    debugLog("File: " . $e->getFile() . ":" . $e->getLine());
    debugLog("Class: " . get_class($e));
}

debugLog("========== TELEGRAM DEBUG END ==========\n");

// Always respond OK to Telegram
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
