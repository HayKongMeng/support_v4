<?php
/**
 * Debug Routing Issues
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== ROUTING DEBUG ===\n\n";

echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'not set') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'not set') . "\n";

echo "\n=== CHECKING .HTACCESS ===\n\n";

$htaccessPath = __DIR__ . '/.htaccess';
echo ".htaccess path: {$htaccessPath}\n";
echo "Exists: " . (file_exists($htaccessPath) ? 'Yes' : 'No') . "\n";

if (file_exists($htaccessPath)) {
    echo "\n.htaccess contents:\n";
    echo "---\n";
    echo file_get_contents($htaccessPath);
    echo "---\n";
}

echo "\n=== CHECKING MOD_REWRITE ===\n\n";

if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "mod_rewrite: " . (in_array('mod_rewrite', $modules) ? 'Enabled' : 'Not found') . "\n";
} else {
    echo "Cannot check Apache modules (not running as Apache module)\n";
}

echo "\n=== TESTING ROUTE MATCHING ===\n\n";

// Simulate what the Router does
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove query string
if (($pos = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $pos);
}

// Try to match the webhook route
$testUri = '/api/telegram/webhook/1';
$routePattern = '/api/telegram/webhook/{companyId}';
$pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePattern);
$pattern = '#^' . $pattern . '$#';

echo "Test URI: {$testUri}\n";
echo "Route pattern: {$routePattern}\n";
echo "Regex: {$pattern}\n";

if (preg_match($pattern, $testUri, $matches)) {
    echo "MATCH! Params: " . json_encode(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)) . "\n";
} else {
    echo "NO MATCH\n";
}

echo "\n=== DIRECTORY STRUCTURE ===\n\n";

echo "Current dir: " . __DIR__ . "\n";
echo "Files in current dir:\n";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file[0] !== '.') {
        echo "  - {$file}" . (is_dir(__DIR__ . '/' . $file) ? '/' : '') . "\n";
    }
}

echo "\n=== RECOMMENDATION ===\n\n";
echo "If .htaccess exists but routing doesn't work:\n";
echo "1. Check if AllowOverride is enabled in Apache config\n";
echo "2. Make sure mod_rewrite is enabled\n";
echo "3. Try adding 'RewriteBase /' to .htaccess\n";
