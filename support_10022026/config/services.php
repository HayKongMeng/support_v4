<?php

return [
    'openai' => [
        'api_key' => $_ENV['OPENAI_API_KEY'] ?? '',
        'model' => 'gpt-3.5-turbo',
        'max_tokens' => 500,
    ],

    'smtp' => [
        'host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
        'port' => $_ENV['SMTP_PORT'] ?? 587,
        'username' => $_ENV['SMTP_USERNAME'] ?? '',
        'password' => $_ENV['SMTP_PASSWORD'] ?? '',
        'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls',
        'from_name' => $_ENV['APP_NAME'] ?? 'Support Desk',
    ],

    'telegram' => [
        'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? '',
        'webhook_url' => ($_ENV['APP_URL'] ?? '') . '/api/telegram/webhook',
    ],
];
