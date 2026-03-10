<?php

namespace App\Core;

class Request
{
    private array $get;
    private array $post;
    private array $files;
    private array $server;
    private ?array $json = null;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isAjax(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        return stripos($contentType, 'application/json') !== false;
    }

    public function input(string $key, $default = null)
    {
        // Check JSON body first
        if ($this->isJson()) {
            $json = $this->json();
            if (isset($json[$key])) {
                return $json[$key];
            }
        }

        // Then POST
        if (isset($this->post[$key])) {
            return $this->post[$key];
        }

        // Then GET
        if (isset($this->get[$key])) {
            return $this->get[$key];
        }

        return $default;
    }

    public function all(): array
    {
        if ($this->isJson()) {
            return array_merge($this->get, $this->json());
        }
        return array_merge($this->get, $this->post);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function query(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    public function post(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function json(): array
    {
        if ($this->json === null) {
            $content = file_get_contents('php://input');
            $this->json = json_decode($content, true) ?? [];
        }
        return $this->json;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function ip(): string
    {
        // Only trust X-Forwarded-For if this server is behind a known proxy.
        // Set TRUSTED_PROXIES=true in .env if using a load balancer/CDN.
        $trustedProxy = ($_ENV['TRUSTED_PROXIES'] ?? 'false') === 'true';

        if ($trustedProxy && !empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain a chain; take the first (client) IP
            $ips = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if ($trustedProxy && !empty($this->server['HTTP_CLIENT_IP'])) {
            return $this->server['HTTP_CLIENT_IP'];
        }

        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function header(string $key, $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function validate(array $rules): array
    {
        $errors = [];
        $data = $this->all();

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $paramString] = explode(':', $rule, 2);
                    $params = explode(',', $paramString);
                }

                $error = $this->validateRule($field, $value, $rule, $params);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    private function validateRule(string $field, $value, string $rule, array $params): ?string
    {
        $label = $this->fieldLabel($field);

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    return __('validation_required', ['field' => $label]);
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return __('validation_email', ['field' => $label]);
                }
                break;

            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (strlen($value) < $min) {
                    return __('validation_min', ['field' => $label, 'min' => $min]);
                }
                break;

            case 'max':
                $max = (int) ($params[0] ?? 0);
                if (strlen($value) > $max) {
                    return __('validation_max', ['field' => $label, 'max' => $max]);
                }
                break;

            case 'numeric':
                if ($value && !is_numeric($value)) {
                    return __('validation_numeric', ['field' => $label]);
                }
                break;

            case 'in':
                if ($value && !in_array($value, $params)) {
                    return __('validation_in', ['field' => $label, 'values' => implode(', ', $params)]);
                }
                break;

            case 'confirmed':
                $all = $this->all();
                if ($value !== ($all["{$field}_confirmation"] ?? null)) {
                    return __('validation_confirmed', ['field' => $label]);
                }
                break;
        }

        return null;
    }

    private function fieldLabel(string $field): string
    {
        $key = 'field_' . $field;
        $translated = __($key);
        if ($translated !== $key) {
            return $translated;
        }

        return ucfirst(str_replace('_', ' ', $field));
    }
}
