<?php
/**
 * Telegram Webhook Test Receiver
 * Receives POST requests from Telegram and logs everything
 */

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/telegram_webhook_received.log';

function log_msg($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
}

log_msg("========== WEBHOOK RECEIVED ==========");
log_msg("Method: " . $_SERVER['REQUEST_METHOD']);
log_msg("URI: " . $_SERVER['REQUEST_URI']);
log_msg("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Get raw input
$input = file_get_contents('php://input');
log_msg("Input size: " . strlen($input) . " bytes");

if (!$input) {
    log_msg("ERROR: Empty input!");
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

log_msg("Raw input: " . substr($input, 0, 500));

// Parse JSON
$update = json_decode($input, true);
if (!$update) {
    log_msg("ERROR: Invalid JSON!");
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

log_msg("Update ID: " . ($update['update_id'] ?? 'unknown'));

if (isset($update['message'])) {
    $msg = $update['message'];
    log_msg("Message type: text");
    log_msg("Chat ID: " . $msg['chat']['id']);
    log_msg("User: " . $msg['from']['first_name'] . " " . ($msg['from']['last_name'] ?? ''));
    log_msg("Text: " . ($msg['text'] ?? '(no text)'));
    
    // Try to load and process
    try {
        log_msg("Loading application...");
        define('BASE_PATH', dirname(__DIR__));
        require BASE_PATH . '/vendor/autoload.php';
        
        $app = \App\Core\App::getInstance();
        $db = $app->db();
        log_msg("App loaded and database connected");
        
        // Get config
        $config = $db->selectOne(
            "SELECT * FROM telegram_configs WHERE company_id = 1 AND is_active = 1"
        );
        
        if (!$config) {
            log_msg("ERROR: No telegram config found!");
            http_response_code(200);
            echo json_encode(['ok' => true]);
            exit;
        }
        
        log_msg("Config found, bot token: " . substr($config['bot_token'], 0, 20) . "...");
        
        // Send reply using TelegramBot
        log_msg("Creating TelegramBot instance...");
        $bot = new \App\Services\Telegram\TelegramBot($db, $config['bot_token']);
        
        log_msg("Sending welcome message...");
        $response = $bot->sendMessage(
            $msg['chat']['id'],
            "✓ <b>Webhook is working!</b>\n\nYour message was received:\n<code>" . 
            htmlspecialchars(substr($msg['text'], 0, 100)) . 
            "</code>"
        );
        
        log_msg("Message sent successfully!");
        log_msg("Response: " . json_encode($response));
        
    } catch (\Throwable $e) {
        log_msg("EXCEPTION: " . $e->getMessage());
        log_msg("File: " . $e->getFile() . ":" . $e->getLine());
        log_msg("Stack: " . substr($e->getTraceAsString(), 0, 500));
    }
}

log_msg("========== WEBHOOK END ==========\n");

// Always respond OK
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
