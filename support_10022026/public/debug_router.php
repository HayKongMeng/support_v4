<?php
/**
 * Debug Router - See exactly what the router processes
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;

header('Content-Type: text/plain; charset=utf-8');

echo "=== ROUTER DEBUG ===\n\n";

// What URI would be processed
$uri = $_SERVER['REQUEST_URI'];
echo "Original REQUEST_URI: {$uri}\n";

// Simulate getUri()
$basePath = '/support/public';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
    echo "After removing base path: {$uri}\n";
} else {
    echo "Base path '{$basePath}' not found in URI\n";
}

if (($pos = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $pos);
}

$uri = '/' . trim($uri, '/');
echo "Final processed URI: {$uri}\n\n";

// Now let's see what routes are registered
echo "=== REGISTERED ROUTES ===\n\n";

$app = App::getInstance();
$router = $app->router();

// Use reflection to access private routes array
$reflection = new ReflectionClass($router);
$routesProperty = $reflection->getProperty('routes');
$routesProperty->setAccessible(true);
$routes = $routesProperty->getValue($router);

foreach ($routes as $route) {
    echo "{$route['method']} {$route['uri']}";
    if (!empty($route['middlewares'])) {
        echo " [" . implode(', ', $route['middlewares']) . "]";
    }
    echo "\n";
}

echo "\n=== TESTING MATCH FOR /api/telegram/webhook/1 ===\n\n";

$testUri = '/api/telegram/webhook/1';
$matched = false;

foreach ($routes as $route) {
    $routeUri = '/' . trim($route['uri'], '/');
    $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routeUri);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $testUri, $matches)) {
        echo "MATCHED: {$route['method']} {$route['uri']}\n";
        echo "Pattern: {$pattern}\n";
        echo "Params: " . json_encode(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)) . "\n";
        $matched = true;
    }
}

if (!$matched) {
    echo "NO ROUTE MATCHED!\n";
}

echo "\n=== SIMULATE REQUEST TO /api/telegram/webhook/1 ===\n";
echo "To test, visit: " . str_replace('debug_router.php', 'api/telegram/webhook/1', $_SERVER['REQUEST_URI']) . "\n";
