<?php

namespace App\Services\Telegram;

use App\Core\Database;

class TelegramAgentNotifier
{
    private Database $db;
    private int $companyId;

    public function __construct(Database $db, int $companyId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
    }

    public function notifyAssignedAgent(array $ticket, string $message): void
    {
        $config = $this->db->selectOne(
            "SELECT bot_token
             FROM telegram_configs
             WHERE company_id = ? AND is_active = 1",
            [$this->companyId]
        );

        if (!$config || empty($config['bot_token'])) {
            error_log('[TelegramAgentNotifier] Skip: Telegram bot is not configured/active.');
            return;
        }

        $chatIds = [];
        if (!empty($ticket['assigned_to'])) {
            $agent = $this->db->selectOne(
                "SELECT telegram_chat_id FROM users WHERE id = ? AND company_id = ? AND is_active = 1",
                [$ticket['assigned_to'], $this->companyId]
            );
            if (!empty($agent['telegram_chat_id'])) {
                $chatIds[] = (int) $agent['telegram_chat_id'];
            }
        }

        $chatIds = array_values(array_unique(array_filter($chatIds)));
        if (empty($chatIds)) {
            error_log('[TelegramAgentNotifier] Skip: No Telegram chat_id found for assigned user.');
            return;
        }

        $bot = new TelegramBot($this->db, $config['bot_token']);
        foreach ($chatIds as $chatId) {
            try {
                $bot->sendMessage($chatId, $message);
            } catch (\Throwable $e) {
                error_log('[TelegramAgentNotifier] Failed sending to chat_id ' . $chatId . ': ' . $e->getMessage());
            }
        }
    }
}
