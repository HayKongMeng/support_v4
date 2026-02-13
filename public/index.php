<?php

/**
 * Support Desk - Modern AI Ticketing System
 * Main Entry Point
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Autoloader
require BASE_PATH . '/vendor/autoload.php';

// Bootstrap application
use App\Core\App;

$app = App::getInstance();

// Register middlewares
$router = $app->router();

$router->middleware('auth', function($app) {
    if ($app->auth()->guest()) {
        header('Location: ' . $app->url('login'));
        return false;
    }
    return true;
});

$router->middleware('guest', function($app) {
    if ($app->auth()->check()) {
        header('Location: ' . $app->url('dashboard'));
        return false;
    }
    return true;
});

$router->middleware('admin', function($app) {
    if (!$app->auth()->isAdmin()) {
        header('Location: ' . $app->url('dashboard'));
        return false;
    }
    return true;
});

$router->middleware('agent', function($app) {
    if (!$app->auth()->isAgent()) {
        header('Location: ' . $app->url('customer/tickets'));
        return false;
    }
    return true;
});

// =====================
// Public Routes
// =====================
$router->get('/', function() use ($app) {
    if ($app->auth()->check()) {
        if ($app->auth()->isAgent()) {
            header('Location: ' . $app->url('dashboard'));
        } else {
            header('Location: ' . $app->url('customer/tickets'));
        }
    } else {
        header('Location: ' . $app->url('login'));
    }
});

// Auth routes
$router->group(['prefix' => '', 'middleware' => ['guest']], function($router) {
    $router->get('/login', 'AuthController@showLogin');
    $router->post('/login', 'AuthController@login');
    $router->get('/register', 'AuthController@showRegister');
    $router->post('/register', 'AuthController@register');
});

$router->get('/logout', 'AuthController@logout');

// =====================
// Agent/Admin Routes
// =====================
$router->group(['prefix' => '', 'middleware' => ['auth', 'agent']], function($router) {
    // Dashboard
    $router->get('/dashboard', 'DashboardController@index');

    // Analytics
    $router->get('/analytics', 'AnalyticsController@index');
    $router->get('/analytics/export', 'AnalyticsController@export');
    $router->get('/analytics/sla-status', 'AnalyticsController@slaStatus');

    // Tickets
    $router->get('/tickets', 'TicketController@index');
    $router->get('/tickets/create', 'TicketController@create');
    $router->post('/tickets', 'TicketController@store');
    $router->get('/tickets/{id}', 'TicketController@show');
    $router->post('/tickets/{id}/reply', 'TicketController@reply');
    $router->post('/tickets/{id}/status', 'TicketController@updateStatus');
    $router->post('/tickets/{id}/assign', 'TicketController@assign');
    $router->post('/tickets/{id}/priority', 'TicketController@updatePriority');
    $router->post('/tickets/{id}/category', 'TicketController@updateCategory');
});

// =====================
// Admin Routes
// =====================
$router->group(['prefix' => 'settings', 'middleware' => ['auth', 'admin']], function($router) {
    $router->get('/general', 'SettingsController@general');
    $router->post('/general', 'SettingsController@updateGeneral');

    $router->get('/email', 'SettingsController@email');
    $router->post('/email', 'SettingsController@updateEmail');

    $router->get('/telegram', 'SettingsController@telegram');
    $router->post('/telegram', 'SettingsController@updateTelegram');

    $router->get('/categories', 'CategoryController@index');
    $router->post('/categories', 'CategoryController@store');
    $router->post('/categories/{id}', 'CategoryController@update');
    $router->delete('/categories/{id}', 'CategoryController@delete');

    $router->get('/users', 'SettingsController@users');
    $router->post('/users', 'SettingsController@storeUser');
    $router->post('/users/{id}', 'SettingsController@updateUser');
    $router->delete('/users/{id}', 'SettingsController@deleteUser');

    $router->get('/canned-responses', 'SettingsController@cannedResponses');
    $router->post('/canned-responses', 'SettingsController@storeCannedResponse');
    $router->post('/canned-responses/{id}', 'SettingsController@updateCannedResponse');
    $router->delete('/canned-responses/{id}', 'SettingsController@deleteCannedResponse');

    $router->get('/sla', 'SettingsController@sla');
    $router->post('/sla/{id}', 'SettingsController@updateSla');
});

// =====================
// Knowledge Base Routes (All authenticated users)
// =====================
$router->group(['prefix' => 'kb', 'middleware' => ['auth']], function($router) {
    $router->get('/', 'KnowledgeBaseController@index');
    $router->get('/create', 'KnowledgeBaseController@create');
    $router->post('/', 'KnowledgeBaseController@store');
    $router->get('/my-articles', 'KnowledgeBaseController@myArticles');
    $router->get('/{slug}', 'KnowledgeBaseController@show');
    $router->get('/{id}/edit', 'KnowledgeBaseController@edit');
    $router->post('/{id}', 'KnowledgeBaseController@update');
    $router->delete('/{id}', 'KnowledgeBaseController@delete');
});

// =====================
// Customer Routes
// =====================
$router->group(['prefix' => 'customer', 'middleware' => ['auth']], function($router) {
    $router->get('/tickets', 'CustomerController@tickets');
    $router->get('/tickets/create', 'CustomerController@createTicket');
    $router->post('/tickets', 'CustomerController@storeTicket');
    $router->get('/tickets/{id}', 'CustomerController@showTicket');
    $router->post('/tickets/{id}/reply', 'CustomerController@replyTicket');

    $router->get('/profile', 'CustomerController@profile');
    $router->post('/profile', 'CustomerController@updateProfile');
});

// =====================
// API Routes
// =====================
$router->group(['prefix' => 'api'], function($router) {
    // Auth
    $router->post('/auth/login', 'AuthController@apiLogin');

    // Telegram webhook (POST for actual webhook, GET for testing)
    $router->post('/telegram/webhook/{companyId}', 'Api\\TelegramController@webhook');
    $router->get('/telegram/webhook/{companyId}', function($companyId) {
        echo json_encode(['status' => 'ok', 'message' => 'Telegram webhook endpoint is working', 'company_id' => $companyId]);
    });
    
    // Telegram Mini App API
    $router->post('/telegram/miniapp/create-ticket', 'Api\\TelegramMiniAppController@createTicket');
    $router->get('/telegram/miniapp/my-tickets', 'Api\\TelegramMiniAppController@myTickets');
    $router->get('/telegram/miniapp/ticket/{id}', 'Api\\TelegramMiniAppController@getTicket');
    $router->post('/telegram/miniapp/reply', 'Api\\TelegramMiniAppController@sendReply');
    $router->post('/telegram/miniapp/upload-file', 'Api\\TelegramMiniAppController@uploadFile');
    $router->post('/telegram/miniapp/link-email', 'Api\\TelegramMiniAppController@linkEmail');

    // Categories API
    $router->get('/categories', 'CategoryController@apiList');

    // Dashboard API
    $router->get('/dashboard/stats', 'DashboardController@getStats');
    $router->get('/dashboard/chart', 'DashboardController@getChartData');
});

// Authenticated API routes
$router->group(['prefix' => 'api', 'middleware' => ['auth']], function($router) {
    // Notifications
    $router->get('/notifications', 'Api\\NotificationController@index');
    $router->post('/notifications/{id}/read', 'Api\\NotificationController@markRead');
    $router->post('/notifications/read-all', 'Api\\NotificationController@markAllRead');

    // Push Notifications
    $router->get('/push/public-key', 'Api\\PushController@publicKey');
    $router->post('/push/subscribe', 'Api\\PushController@subscribe');
    $router->post('/push/unsubscribe', 'Api\\PushController@unsubscribe');
    $router->post('/push/test', 'Api\\PushController@testNotification');
});

// Admin API routes
$router->group(['prefix' => 'api', 'middleware' => ['auth', 'admin']], function($router) {
    $router->put('/users/{id}', 'SettingsController@updateUser');
    $router->delete('/users/{id}', 'SettingsController@deleteUser');
});

// Run application
$app->run();
