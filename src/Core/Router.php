<?php

namespace App\Core;

class Router
{
    private App $app;
    private array $routes = [];
    private array $middlewares = [];
    private string $prefix = '';
    private array $currentMiddlewares = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function group(array $options, callable $callback): self
    {
        $previousPrefix = $this->prefix;
        $previousMiddlewares = $this->currentMiddlewares;

        if (isset($options['prefix'])) {
            $this->prefix .= '/' . trim($options['prefix'], '/');
        }

        if (isset($options['middleware'])) {
            $middlewares = is_array($options['middleware']) ? $options['middleware'] : [$options['middleware']];
            $this->currentMiddlewares = array_merge($this->currentMiddlewares, $middlewares);
        }

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->currentMiddlewares = $previousMiddlewares;

        return $this;
    }

    public function get(string $uri, $action): self
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, $action): self
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function delete(string $uri, $action): self
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    private function addRoute(string $method, string $uri, $action): self
    {
        $uri = $this->prefix . '/' . trim($uri, '/');
        $uri = '/' . trim($uri, '/');

        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middlewares' => $this->currentMiddlewares,
        ];

        return $this;
    }

    public function middleware(string $name, callable $handler): self
    {
        $this->middlewares[$name] = $handler;
        return $this;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getUri();

        // Handle PUT/DELETE via POST with _method
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            $params = $this->matchRoute($route['uri'], $uri);

            if ($params !== false && $route['method'] === $method) {
                // Run middlewares
                foreach ($route['middlewares'] as $middleware) {
                    if (isset($this->middlewares[$middleware])) {
                        $result = call_user_func($this->middlewares[$middleware], $this->app);
                        if ($result === false) {
                            return;
                        }
                    }
                }

                $this->executeAction($route['action'], $params);
                return;
            }
        }

        // 404 Not Found
        http_response_code(404);
        $this->renderView('errors/404');
    }

    private function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'];

        // Remove query string first
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Get the script name to determine base path
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $basePath = dirname($scriptName);
        
        // Remove base path if present
        if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        return '/' . trim($uri, '/');
    }

    private function matchRoute(string $routeUri, string $requestUri): array|false
    {
        $routeUri = '/' . trim($routeUri, '/');
        $requestUri = '/' . trim($requestUri, '/');

        // Convert route params to regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestUri, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    private function executeAction($action, array $params): void
    {
        if (is_callable($action)) {
            call_user_func_array($action, $params);
            return;
        }

        if (is_string($action)) {
            [$controller, $method] = explode('@', $action);
            $controllerClass = "App\\Controllers\\{$controller}";

            if (class_exists($controllerClass)) {
                $instance = new $controllerClass($this->app);
                call_user_func_array([$instance, $method], $params);
                return;
            }
        }

        throw new \RuntimeException("Invalid route action");
    }

    private function renderView(string $view): void
    {
        $viewPath = $this->app->basePath("views/{$view}.php");
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View not found: {$view}";
        }
    }
}
