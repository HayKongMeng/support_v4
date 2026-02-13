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
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
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
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
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
     * Make API request
     */
    private function request(string $method, array $params = []): array
    {
        $url = $this->apiUrl . $this->botToken . '/' . $method;

        // Log outgoing request
        error_log("[Telegram Bot] Request: {$method} - " . json_encode($params));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log response
        error_log("[Telegram Bot] Response ({$httpCode}): " . ($response ?: 'empty'));

        if ($curlError) {
            error_log("[Telegram Bot] CURL Error: {$curlError}");
            throw new \Exception("Telegram API curl error: {$curlError}");
        }

        $data = json_decode($response, true);

        if (!$data || !$data['ok']) {
            $error = $data['description'] ?? 'Unknown error';
            error_log("[Telegram Bot] API Error: {$error}");
            throw new \Exception("Telegram API error: {$error}");
        }

        return $data['result'] ?? [];
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
}
