<?php
// Check PHP PDO MySQL support
header('Content-Type: application/json');

$result = [
    'php_version' => phpversion(),
    'pdo_available' => extension_loaded('pdo'),
    'pdo_mysql_available' => extension_loaded('pdo_mysql'),
    'loaded_extensions' => get_loaded_extensions()
];

if ($result['pdo_mysql_available']) {
    $result['status'] = 'OK - PDO MySQL is enabled';
    $result['pdo_drivers'] = PDO::getAvailableDrivers();
} else {
    $result['status'] = 'ERROR - PDO MySQL is NOT enabled';
    $result['solution'] = 'Contact your hosting provider to enable PHP PDO MySQL extension';
}

echo json_encode($result, JSON_PRETTY_PRINT);
