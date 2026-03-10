<?php

namespace App\Services\Telegram;

use App\Core\Database;

class TelegramBot
{
    private Database $db;
    private string $botToken;
    private string $apiUrl = 'https://api.telegram.org/bot';

    public function __construct(Database $db, string $botToken)
    {
        $this->db = $db;
        $this->botToken = $botToken;
    }

    /**
     * Send a message
     */
    public function sendMessage(int $chatId, string $text, array $options = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);
        $params = $this->normalizePayload($params);

        return $this->request('sendMessage', $params);
    }

    /**
     * Send message with inline keyboard
     */
    public function sendMessageWithKeyboard(int $chatId, string $text, array $keyboard): array
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ]),
        ]);
    }

    /**
     * Send message with reply keyboard
     */
    public function sendMessageWithReplyKeyboard(int $chatId, string $text, array $buttons): array
    {
        $keyboard = array_map(function($button) {
            return [['text' => $button]];
        }, $buttons);

        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);
    }

    /**
     * Remove keyboard
     */
    public function removeKeyboard(int $chatId, string $text): array
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'remove_keyboard' => true,
            ]),
        ]);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ];
        $params = $this->normalizePayload($params);
        return $this->request('answerCallbackQuery', $params);
    }

    /**
     * Edit message text
     */
    public function editMessageText(int $chatId, int $messageId, string $text, array $options = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);
        $params = $this->normalizePayload($params);

        return $this->request('editMessageText', $params);
    }

    /**
     * Set webhook
     */
    public function setWebhook(string $url, string $secretToken = null): array
    {
        $params = ['url' => $url];

        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        return $this->request('setWebhook', $params);
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(array $params = []): array
    {
        return $this->request('deleteWebhook', $params);
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Get bot info
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Get file info from Telegram
     */
    public function getFile(string $fileId): ?array
    {
        try {
            $result = $this->request('getFile', ['file_id' => $fileId]);
            
            if (empty($result)) {
                error_log("[TelegramBot] getFile returned empty result for file_id: {$fileId}");
                return null;
            }
            
            error_log("[TelegramBot] getFile success: " . json_encode($result));
            return $result;
        } catch (\Exception $e) {
            error_log("[TelegramBot] getFile exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Make API request
     */
    private function request(string $method, array $params = []): array
    {
        $url = $this->apiUrl . $this->botToken . '/' . $method;
        $logFile = BASE_PATH . '/storage/logs/telegram_bot_api.log';
        $timestamp = date('Y-m-d H:i:s');

        // Ensure log directory exists
        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0755, true);
        }

        // Log to file
        @file_put_contents($logFile, "[{$timestamp}] ========== REQUEST ==========\n", FILE_APPEND);
        @file_put_contents($logFile, "[{$timestamp}] METHOD: {$method}\n", FILE_APPEND);
        @file_put_contents($logFile, "[{$timestamp}] URL: {$url}\n", FILE_APPEND);
        @file_put_contents($logFile, "[{$timestamp}] PARAMS: " . json_encode($params) . "\n", FILE_APPEND);

        // Log outgoing request
        error_log("[Telegram Bot] REQUEST: {$method}");
        error_log("[Telegram Bot] URL: {$url}");
        error_log("[Telegram Bot] PARAMS: " . json_encode($params));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        error_log("[Telegram Bot] Making curl request...");
        @file_put_contents($logFile, "[{$timestamp}] Making curl request...\n", FILE_APPEND);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log raw response
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$timestamp}] HTTP CODE: {$httpCode}\n", FILE_APPEND);
        @file_put_contents($logFile, "[{$timestamp}] RAW RESPONSE: " . (is_string($response) ? substr($response, 0, 500) : gettype($response)) . "\n", FILE_APPEND);
        @file_put_contents($logFile, "[{$timestamp}] CURL ERROR: " . ($curlError ?: 'none') . "\n", FILE_APPEND);

        error_log("[Telegram Bot] HTTP CODE: {$httpCode}");
        error_log("[Telegram Bot] RAW RESPONSE TYPE: " . gettype($response));
        error_log("[Telegram Bot] CURL ERROR: " . ($curlError ?: 'none'));

        if ($curlError) {
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$timestamp}] CURL ERROR DETAIL: {$curlError}\n==========\n", FILE_APPEND);
            error_log("[Telegram Bot] CURL ERROR DETAIL: {$curlError}");
            throw new \Exception("Telegram API curl error: {$curlError}");
        }

        if ($response === false || !$response) {
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$timestamp}] ERROR: Empty/false response from Telegram API\n==========\n", FILE_APPEND);
            error_log("[Telegram Bot] ERROR: Empty/false response from Telegram API");
            throw new \Exception("Telegram API returned empty response");
        }

        $data = json_decode($response, true);

        if ($data === null) {
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$timestamp}] ERROR: Invalid JSON response: {$response}\n==========\n", FILE_APPEND);
            error_log("[Telegram Bot] ERROR: Invalid JSON response: {$response}");
            throw new \Exception("Telegram API returned invalid JSON");
        }

        if (!($data['ok'] ?? false)) {
            $error = $data['description'] ?? json_encode($data);
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$timestamp}] API ERROR: {$error}\n==========\n", FILE_APPEND);
            error_log("[Telegram Bot] API ERROR: {$error}");
            throw new \Exception("Telegram API error: {$error}");
        }

        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$timestamp}] SUCCESS: {$method} completed\n==========\n", FILE_APPEND);
        error_log("[Telegram Bot] SUCCESS: {$method} completed");
        
        // Always return an array
        $result = $data['result'] ?? [];
        return is_array($result) ? $result : [];
    }

    /**
     * Format ticket for display
     */
    public function formatTicket(array $ticket): string
    {
        $status = ucfirst(str_replace('_', ' ', $ticket['status']));
        $priority = ucfirst($ticket['priority']);

        return "<b>Ticket #{$ticket['ticket_number']}</b>\n\n" .
            "<b>Subject:</b> {$ticket['subject']}\n" .
            "<b>Status:</b> {$status}\n" .
            "<b>Priority:</b> {$priority}\n" .
            "<b>Created:</b> " . date('M j, Y g:i A', strtotime($ticket['created_at'])) . "\n\n" .
            "<b>Description:</b>\n" . htmlspecialchars(substr($ticket['description'], 0, 500)) .
            (strlen($ticket['description']) > 500 ? '...' : '');
    }

    /**
     * Format ticket list
     */
    public function formatTicketList(array $tickets): string
    {
        if (empty($tickets)) {
            return "You don't have any tickets yet.";
        }

        $text = "<b>Your Tickets</b>\n\n";

        foreach ($tickets as $ticket) {
            $status = ucfirst(str_replace('_', ' ', $ticket['status']));
            $text .= "#{$ticket['ticket_number']} - {$status}\n";
            $text .= substr($ticket['subject'], 0, 50) . (strlen($ticket['subject']) > 50 ? '...' : '') . "\n\n";
        }

        return $text;
    }

    /**
     * Normalize outgoing payload values to recover common mojibake text.
     *
     * This fixes strings like "ÃƒÂ¡..." back to readable Khmer/emoji without
     * changing normal ASCII/UTF-8 text.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalizePayload($value)
    {
        if (is_string($value)) {
            return $this->normalizeOutgoingText($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizePayload($item);
            }
        }

        return $value;
    }

    private function normalizeOutgoingText(string $text): string
    {
        if ($text === '' || !preg_match('/[ÃÂâð]/u', $text)) {
            return $text;
        }

        $candidates = [$text];
        $current = $text;
        for ($i = 0; $i < 4; $i++) {
            if (!function_exists('mb_convert_encoding')) {
                break;
            }

            $next = @mb_convert_encoding($current, 'Windows-1252', 'UTF-8');
            if (!is_string($next) || $next === '' || $next === $current) {
                break;
            }

            $candidates[] = $next;
            $current = $next;
        }

        $best = $text;
        $bestScore = $this->scoreNormalizedText($text);
        foreach ($candidates as $candidate) {
            $score = $this->scoreNormalizedText($candidate);
            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function scoreNormalizedText(string $text): int
    {
        $bad = $this->countMatches('/[ÃÂâð]/u', $text);
        $khmer = $this->countMatches('/[\x{1780}-\x{17FF}]/u', $text);
        $emoji = $this->countMatches('/[\x{1F300}-\x{1FAFF}]/u', $text);
        $replacement = substr_count($text, '�');
        $questionRuns = $this->countMatches('/\?{2,}/', $text);

        return ($khmer * 10) + ($emoji * 3) - ($bad * 20) - ($replacement * 10) - ($questionRuns * 2);
    }

    private function countMatches(string $pattern, string $text): int
    {
        $count = @preg_match_all($pattern, $text, $matches);
        return is_int($count) ? $count : 0;
    }
}
