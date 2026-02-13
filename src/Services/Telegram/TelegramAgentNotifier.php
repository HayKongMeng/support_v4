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
            "SELECT bot_token, notify_assigned_agents, alert_group_chat_id
             FROM telegram_configs
             WHERE company_id = ? AND is_active = 1",
            [$this->companyId]
        );

        if (!$config || empty($config['notify_assigned_agents']) || empty($config['bot_token'])) {
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

        if (!empty($config['alert_group_chat_id'])) {
            $chatIds[] = (int) $config['alert_group_chat_id'];
        }

        $chatIds = array_values(array_unique(array_filter($chatIds)));
        if (empty($chatIds)) {
            return;
        }

        $bot = new TelegramBot($this->db, $config['bot_token']);
        foreach ($chatIds as $chatId) {
            $bot->sendMessage($chatId, $message);
        }
    }
}
