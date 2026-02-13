<?php

namespace App\Core;

use Dotenv\Dotenv;

class App
{
    private static ?App $instance = null;
    private array $config = [];
    private ?Database $db = null;
    private ?Router $router = null;
    private ?Auth $auth = null;

    private function __construct()
    {
        $this->loadEnvironment();
        $this->loadConfig();
        $this->initDatabase();
        $this->initSession();
    }

    public static function getInstance(): App
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnvironment(): void
    {
        $envPath = dirname(__DIR__, 2);
        if (file_exists($envPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($envPath);
            $dotenv->load();
        }
    }

    private function loadConfig(): void
    {
        $configPath = dirname(__DIR__, 2) . '/config';
        $this->config['app'] = require $configPath . '/app.php';
        $this->config['database'] = require $configPath . '/database.php';
        $this->config['services'] = require $configPath . '/services.php';
    }

    private function initDatabase(): void
    {
        $this->db = new Database($this->config['database']);
    }

    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function config(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function db(): Database
    {
        return $this->db;
    }

    public function auth(): Auth
    {
        if ($this->auth === null) {
            $this->auth = new Auth($this->db);
        }
        return $this->auth;
    }

    public function router(): Router
    {
        if ($this->router === null) {
            $this->router = new Router($this);
        }
        return $this->router;
    }

    public function run(): void
    {
        try {
            $this->router()->dispatch();
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    private function handleException(\Exception $e): void
    {
        if ($this->config('app.debug')) {
            echo '<h1>Error</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        } else {
            http_response_code(500);
            echo 'An error occurred. Please try again later.';
        }
    }

    public function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path ? '/' . ltrim($path, '/') : '');
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public' . ($path ? '/' . ltrim($path, '/') : ''));
    }

    public function url(string $path = ''): string
    {
        $baseUrl = rtrim($this->config('app.url', ''), '/');
        return $baseUrl . '/public/' . ltrim($path, '/');
    }
}

// Helper function
function app(): App
{
    return App::getInstance();
}
