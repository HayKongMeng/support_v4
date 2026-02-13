<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Services\Telegram\TelegramWebhook;

class TelegramController extends Controller
{
    /**
     * Handle incoming Telegram webhook
     */
    public function webhook(string $companyId): void
    {
        // Get raw input
        $input = file_get_contents('php://input');

        // Write to file for debugging (works even if error_log doesn't)
        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents(
            $logDir . '/telegram_webhook.log',
            "[{$timestamp}] Company: {$companyId}\nInput: {$input}\n---\n",
            FILE_APPEND
        );

        // Log incoming webhook
        error_log("[Telegram Webhook] Company: {$companyId} - Raw input: {$input}");

        $update = json_decode($input, true);

        if (!$update) {
            error_log("[Telegram Webhook] Invalid JSON received");
            $this->error('Invalid request', 400);
            return;
        }

        error_log("[Telegram Webhook] Update ID: " . ($update['update_id'] ?? 'none'));

        // Verify company exists and has Telegram configured
        $config = $this->db->selectOne(
            "SELECT tc.*, c.is_active as company_active
             FROM telegram_configs tc
             JOIN companies c ON tc.company_id = c.id
             WHERE tc.company_id = ? AND tc.is_active = 1",
            [$companyId]
        );

        if (!$config || !$config['company_active']) {
            error_log("[Telegram Webhook] No active config for company {$companyId}");
            $this->success([], 'OK'); // Return OK to prevent Telegram retries
            return;
        }

        error_log("[Telegram Webhook] Config found, processing...");

        // Optional: Verify webhook secret
        $secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
        if ($config['webhook_secret'] && $secretHeader !== $config['webhook_secret']) {
            $this->success([], 'OK'); // Return OK but don't process
            return;
        }

        try {
            // Process the update
            $handler = new TelegramWebhook($this->db, (int) $companyId);
            $handler->handle($update);
        } catch (\Exception $e) {
            // Log error but return OK to prevent retries
            error_log("Telegram webhook error: " . $e->getMessage());
        }

        // Always return OK to Telegram
        $this->success([], 'OK');
    }

    /**
     * Setup webhook (admin endpoint)
     */
    public function setupWebhook(): void
    {
        $this->requireAdmin();

        $config = $this->db->selectOne(
            "SELECT * FROM telegram_configs WHERE company_id = ? AND is_active = 1",
            [$this->companyId()]
        );

        if (!$config) {
            $this->error('Telegram not configured', 400);
            return;
        }

        $webhookUrl = $this->app->config('app.url') . '/public/api/telegram/webhook/' . $this->companyId();

        try {
            $bot = new \App\Services\Telegram\TelegramBot($this->db, $config['bot_token']);
            $result = $bot->setWebhook($webhookUrl, $config['webhook_secret']);

            $this->success([
                'webhook_url' => $webhookUrl,
                'result' => $result,
            ], 'Webhook configured successfully');

        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Get webhook info (admin endpoint)
     */
    public function webhookInfo(): void
    {
        $this->requireAdmin();

        $config = $this->db->selectOne(
            "SELECT * FROM telegram_configs WHERE company_id = ? AND is_active = 1",
            [$this->companyId()]
        );

        if (!$config) {
            $this->error('Telegram not configured', 400);
            return;
        }

        try {
            $bot = new \App\Services\Telegram\TelegramBot($this->db, $config['bot_token']);
            $info = $bot->getWebhookInfo();
            $botInfo = $bot->getMe();

            $this->success([
                'bot' => $botInfo,
                'webhook' => $info,
            ]);

        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
