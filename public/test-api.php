<?php
/**
 * Test Mini App API without Telegram authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;

try {
    // Load environment
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
    
    // Create database connection
    $db = new Database([
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'],
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_DATABASE'],
        'username' => $_ENV['DB_USERNAME'],
        'password' => $_ENV['DB_PASSWORD'],
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ]);
    
    // Test: Get tickets for a test user
    $companyId = 1;
    
    // Find a test user with telegram_chat_id
    $user = $db->selectOne(
        "SELECT * FROM users WHERE company_id = ? AND telegram_chat_id IS NOT NULL LIMIT 1",
        [$companyId]
    );
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'No user found with Telegram linked. Please link your account first using /link command in Telegram bot.',
            'hint' => 'Open Telegram, send /start to your bot, then send /link and follow instructions'
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Get tickets for this user
    $tickets = $db->select(
        "SELECT * FROM tickets 
         WHERE requester_id = ? AND company_id = ? AND status NOT IN ('closed') 
         ORDER BY created_at DESC 
         LIMIT 10",
        [$user['id'], $companyId]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'API is working!',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'telegram_chat_id' => $user['telegram_chat_id']
        ],
        'tickets_count' => count($tickets),
        'tickets' => $tickets
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
