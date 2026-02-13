<?php

return [
    'name' => $_ENV['APP_NAME'] ?? 'Support Desk',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost/support',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),

    'timezone' => 'UTC',

    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'change-this-secret-key',
    'jwt_expiry' => 86400, // 24 hours

    'upload_max_size' => 10 * 1024 * 1024, // 10MB
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],

    'tickets' => [
        'statuses' => ['open', 'pending', 'in_progress', 'resolved', 'closed'],
        'priorities' => ['low', 'medium', 'high', 'urgent'],
        'sources' => ['web', 'email', 'telegram', 'api'],
    ],

    'pagination' => [
        'per_page' => 25,
    ],

    'ai' => [
        'enabled' => true,
        'confidence_threshold' => 0.7, // Auto-assign if confidence > 70%
        'provider' => 'openai', // 'openai' or 'keywords'
    ],
];
