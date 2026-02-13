<?php
/**
 * Simple Telegram Test Script
 * This tests if the bot can send messages
 */

require_once __DIR__ . '/../src/Core/App.php';

use App\Core\App;
use App\Core\Database;

header('Content-Type: application/json');

$app = new App();
$db = new Database();

$result = ['steps' => []];

// Step 1: Check telegram config
$config = $db->selectOne("SELECT * FROM telegram_configs WHERE is_active = 1 LIMIT 1");
if (!$config) {
    $result['error'] = 'No active telegram config found';
    $result['fix'] = 'INSERT INTO telegram_configs (company_id, bot_token, is_active) VALUES (1, "YOUR_BOT_TOKEN", 1)';
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$result['steps'][] = [
    'step' => 'Config found',
    'company_id' => $config['company_id'],
    'bot_token_prefix' => substr($config['bot_token'], 0, 15) . '...',
    'is_active' => $config['is_active'],
];

// Step 2: Check company exists
$company = $db->selectOne("SELECT * FROM companies WHERE id = ? AND is_active = 1", [$config['company_id']]);
if (!$company) {
    $result['error'] = 'Company not found or not active';
    $result['company_id'] = $config['company_id'];
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$result['steps'][] = [
    'step' => 'Company found',
    'company_name' => $company['name'],
];

// Step 3: Test bot API
$botToken = $config['bot_token'];
$testUrl = "https://api.telegram.org/bot{$botToken}/getMe";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    $result['error'] = 'CURL error: ' . $error;
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$botData = json_decode($response, true);
if (!$botData['ok']) {
    $result['error'] = 'Invalid bot token: ' . ($botData['description'] ?? 'unknown');
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

$result['steps'][] = [
    'step' => 'Bot verified',
    'bot_username' => '@' . $botData['result']['username'],
];

// Step 4: Get your chat_id (you need to message the bot first)
// Send ?chat_id=YOUR_CHAT_ID to test sending a message

if (isset($_GET['chat_id'])) {
    $chatId = (int) $_GET['chat_id'];

    $sendUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => "Test message from Support Bot!\n\nIf you see this, the bot is working correctly.\n\nTime: " . date('Y-m-d H:i:s'),
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
        $result['send_error'] = $sendError;
    } else {
        $result['send_result'] = json_decode($sendResponse, true);
    }
}

$result['success'] = true;
$result['message'] = 'All checks passed! Bot is ready.';
$result['next_step'] = 'To test sending a message, add ?chat_id=YOUR_CHAT_ID to this URL';
$result['get_chat_id'] = 'Send /start to your bot, then check getWebhookInfo for pending updates, or check server error logs';

echo json_encode($result, JSON_PRETTY_PRINT);
