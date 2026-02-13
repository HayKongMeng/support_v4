<?php

/**
 * Global Helper Functions
 */

if (!function_exists('app')) {
    function app(): \App\Core\App
    {
        return \App\Core\App::getInstance();
    }
}

if (!function_exists('db')) {
    function db(): \App\Core\Database
    {
        return app()->db();
    }
}

if (!function_exists('auth')) {
    function auth(): \App\Core\Auth
    {
        return app()->auth();
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        return app()->config($key, $default);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return app()->url($path);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = ''): string
    {
        return htmlspecialchars($_SESSION['old_input'][$key] ?? $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'M j, Y'): string
    {
        if (!$date) return '';
        return date($format, strtotime($date));
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $date, string $format = 'M j, Y g:i A'): string
    {
        if (!$date) return '';
        return date($format, strtotime($date));
    }
}

if (!function_exists('time_ago')) {
    function time_ago(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return format_date($datetime);
        }
    }
}

if (!function_exists('truncate')) {
    function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . $suffix;
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        $_SESSION["flash_{$type}"] = $message;
    }
}

if (!function_exists('get_flash')) {
    function get_flash(string $type): ?string
    {
        $message = $_SESSION["flash_{$type}"] ?? null;
        unset($_SESSION["flash_{$type}"]);
        return $message;
    }
}
