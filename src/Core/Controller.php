<?php

namespace App\Core;

abstract class Controller
{
    protected App $app;
    protected Database $db;
    protected Auth $auth;
    protected Request $request;
    protected Response $response;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->db = $app->db();
        $this->auth = $app->auth();
        $this->request = new Request();
        $this->response = new Response();
    }

    protected function view(string $view, array $data = []): void
    {
        // Extract data to variables
        extract($data);

        // Add common variables
        $auth = $this->auth;
        $user = $this->auth->user();
        $company = $this->auth->company();
        $app = $this->app;

        // Flash messages
        $flashError = $_SESSION['flash_error'] ?? null;
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $oldInput = $_SESSION['old_input'] ?? [];

        // Clear flash data
        unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['old_input']);

        // Build view path
        $viewPath = $this->app->basePath("views/{$view}.php");

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        include $viewPath;
    }

    protected function json(array $data, int $status = 200): void
    {
        $this->response->json($data, $status);
    }

    protected function success(array $data = [], string $message = ''): void
    {
        if ($message === '') {
            $message = __('success');
        }
        $this->response->success($data, $message);
    }

    protected function error(string $message, int $status = 400, array $errors = []): void
    {
        $this->response->error($message, $status, $errors);
    }

    protected function redirect(string $url): void
    {
        $this->response->redirect($url);
    }

    protected function back(): void
    {
        $this->response->back();
    }

    protected function validate(array $rules): array
    {
        $errors = $this->request->validate($rules);

        if (!empty($errors)) {
            if ($this->request->isAjax() || $this->request->isJson()) {
                $this->error(__('validation_failed'), 422, $errors);
            }

            $this->response->withError(__('please_fix_errors_below'))->withInput();
            // Flatten errors for display
            $_SESSION['validation_errors'] = $errors;
            $this->back();
        }

        return $this->request->only(array_keys($rules));
    }

    protected function companyId(): int
    {
        return $this->auth->companyId();
    }

    protected function requireAuth(): void
    {
        if ($this->auth->guest()) {
            if ($this->request->isAjax() || $this->request->isJson()) {
                $this->error(__('unauthorized'), 401);
            }
            $this->redirect($this->app->url('login'));
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (!$this->auth->isAdmin()) {
            if ($this->request->isAjax() || $this->request->isJson()) {
                $this->error(__('forbidden'), 403);
            }
            $this->redirect($this->app->url('dashboard'));
        }
    }

    protected function requireAgent(): void
    {
        $this->requireAuth();
        if (!$this->auth->isAgent()) {
            if ($this->request->isAjax() || $this->request->isJson()) {
                $this->error(__('forbidden'), 403);
            }
            $this->redirect($this->app->url('customer/tickets'));
        }
    }
}
