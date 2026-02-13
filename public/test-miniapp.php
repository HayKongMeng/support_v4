<?php
/**
 * Diagnostic test for Mini App API
 */

header('Content-Type: application/json');

// Test 1: Check if file exists
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 2: Check BASE_PATH
define('BASE_PATH', dirname(__DIR__));
$results['tests']['base_path'] = [
    'status' => file_exists(BASE_PATH) ? 'OK' : 'FAIL',
    'path' => BASE_PATH
];

// Test 3: Check autoloader
$autoloadPath = BASE_PATH . '/vendor/autoload.php';
$results['tests']['autoloader'] = [
    'status' => file_exists($autoloadPath) ? 'OK' : 'FAIL',
    'path' => $autoloadPath
];

if (file_exists($autoloadPath)) {
    require $autoloadPath;
}

// Test 4: Check controller file
$controllerPath = BASE_PATH . '/src/Controllers/Api/TelegramMiniAppController.php';
$results['tests']['controller_file'] = [
    'status' => file_exists($controllerPath) ? 'OK' : 'FAIL',
    'path' => $controllerPath
];

// Test 5: Check if class can be loaded
try {
    if (class_exists('App\Controllers\Api\TelegramMiniAppController')) {
        $results['tests']['controller_class'] = ['status' => 'OK'];
    } else {
        $results['tests']['controller_class'] = ['status' => 'FAIL', 'message' => 'Class not found'];
    }
} catch (Exception $e) {
    $results['tests']['controller_class'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// Test 6: Check database connection
try {
    if (file_exists(BASE_PATH . '/.env')) {
        require BASE_PATH . '/vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
        $dotenv->load();
        
        $results['tests']['env_file'] = ['status' => 'OK'];
        $results['tests']['db_config'] = [
            'host' => $_ENV['DB_HOST'] ?? 'not set',
            'database' => $_ENV['DB_DATABASE'] ?? 'not set',
            'username' => $_ENV['DB_USERNAME'] ?? 'not set'
        ];
        
        // Try database connection
        try {
            $pdo = new PDO(
                "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD']
            );
            $results['tests']['database_connection'] = ['status' => 'OK'];
        } catch (Exception $e) {
            $results['tests']['database_connection'] = ['status' => 'FAIL', 'error' => $e->getMessage()];
        }
    } else {
        $results['tests']['env_file'] = ['status' => 'FAIL', 'message' => '.env file not found'];
    }
} catch (Exception $e) {
    $results['tests']['env_check'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

echo json_encode($results, JSON_PRETTY_PRINT);
