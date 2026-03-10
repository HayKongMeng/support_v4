<?php

namespace App\Core;

class Response
{
    public function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function success(array $data = [], string $message = ''): void
    {
        if ($message === '') {
            $message = __('success');
        }

        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function error(string $message, int $status = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->json($response, $status);
    }

    public function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    public function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    public function withError(string $message): self
    {
        $_SESSION['flash_error'] = $message;
        return $this;
    }

    public function withSuccess(string $message): self
    {
        $_SESSION['flash_success'] = $message;
        return $this;
    }

    public function withInput(): self
    {
        $_SESSION['old_input'] = $_POST;
        return $this;
    }

    public function download(string $path, string $filename = null): void
    {
        if (!file_exists($path)) {
            http_response_code(404);
            echo __('file_not_found');
            exit;
        }

        $filename = $filename ?? basename($path);
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));

        readfile($path);
        exit;
    }
}
