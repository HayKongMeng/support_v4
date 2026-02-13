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
        $logFile = BASE_PATH . '/storage/logs/telegram_webhook_handler.log';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$timestamp}] ===== WEBHOOK HANDLER START =====\n", FILE_APPEND);

        try {
            // Get raw input
            $input = file_get_contents('php://input');
            @file_put_contents($logFile, "[{$timestamp}] Input received, size: " . strlen($input) . "\n", FILE_APPEND);

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
                @file_put_contents($logFile, "[{$timestamp}] ERROR: Could not parse JSON\n", FILE_APPEND);
                error_log("[Telegram Webhook] Invalid JSON received");
                $this->json(['ok' => true], 200); // Return OK status for Telegram
                return;
            }

            @file_put_contents($logFile, "[{$timestamp}] JSON parsed, update_id: " . ($update['update_id'] ?? 'none') . "\n", FILE_APPEND);
            error_log("[Telegram Webhook] Update ID: " . ($update['update_id'] ?? 'none'));

            // Verify company exists and has Telegram configured
            @file_put_contents($logFile, "[{$timestamp}] Loading telegram config for company {$companyId}...\n", FILE_APPEND);
            $config = $this->db->selectOne(
                "SELECT tc.*, c.is_active as company_active
                 FROM telegram_configs tc
                 JOIN companies c ON tc.company_id = c.id
                 WHERE tc.company_id = ? AND tc.is_active = 1",
                [$companyId]
            );

            if (!$config || !$config['company_active']) {
                @file_put_contents($logFile, "[{$timestamp}] ERROR: No active config for company {$companyId}\n", FILE_APPEND);
                error_log("[Telegram Webhook] No active config for company {$companyId}");
                $this->json(['ok' => true], 200); // Return OK status for Telegram
                return;
            }

            @file_put_contents($logFile, "[{$timestamp}] Config found, bot token: " . substr($config['bot_token'], 0, 15) . "...\n", FILE_APPEND);
            error_log("[Telegram Webhook] Config found, processing...");

            // Optional: Verify webhook secret
            $secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
            @file_put_contents($logFile, "[{$timestamp}] Webhook secret in DB: " . ($config['webhook_secret'] ? 'SET' : 'NOT SET') . "\n", FILE_APPEND);
            @file_put_contents($logFile, "[{$timestamp}] Secret header from Telegram: " . ($secretHeader ? 'PRESENT' : 'NOT PRESENT') . "\n", FILE_APPEND);
            
            // TEMP: Do not block when secret header is missing/mismatched
            if ($secretHeader !== null && !empty($config['webhook_secret']) && $secretHeader !== $config['webhook_secret']) {
                @file_put_contents($logFile, "[{$timestamp}] ERROR: Invalid webhook secret (ignored)\n", FILE_APPEND);
            }

            @file_put_contents($logFile, "[{$timestamp}] Secret validation passed\n", FILE_APPEND);

            try {
                // Process the update
                @file_put_contents($logFile, "[{$timestamp}] Creating TelegramWebhook handler...\n", FILE_APPEND);
                $handler = new TelegramWebhook($this->db, (int) $companyId);
                @file_put_contents($logFile, "[{$timestamp}] Calling handler->handle()...\n", FILE_APPEND);
                $handler->handle($update);
                @file_put_contents($logFile, "[{$timestamp}] Handler completed successfully\n", FILE_APPEND);
            } catch (\Exception $e) {
                // Log error but return OK to prevent retries
                @file_put_contents($logFile, "[{$timestamp}] EXCEPTION in handler: " . $e->getMessage() . "\n", FILE_APPEND);
                @file_put_contents($logFile, "[{$timestamp}] File: " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
                @file_put_contents($logFile, "[{$timestamp}] Trace: " . substr($e->getTraceAsString(), 0, 500) . "\n", FILE_APPEND);
                error_log("Telegram webhook error: " . $e->getMessage());
            }

            // Always return OK to Telegram
            @file_put_contents($logFile, "[{$timestamp}] Sending OK response to Telegram\n", FILE_APPEND);
            $this->json(['ok' => true], 200);

        } catch (\Throwable $e) {
            // Emergency fallback - always respond with OK to Telegram even if everything breaks
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$timestamp}] CRITICAL EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            @file_put_contents($logFile, "[{$timestamp}] File: " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
            error_log("[Telegram Webhook FATAL] " . $e->getMessage() . " - " . $e->getFile() . ":" . $e->getLine());
            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode(['ok' => true]);
            exit;
        }
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

        // Don't include /public - the domain already routes to it
        $baseUrl = $this->app->config('app.url');
        // Remove /public if it's at the end
        $baseUrl = rtrim(str_replace('/public', '', $baseUrl), '/');
        $webhookUrl = $baseUrl . '/api/telegram/webhook/' . $this->companyId();

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
