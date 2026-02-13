<?php

namespace App\Models;

class TicketMessage extends Model
{
    protected string $table = 'ticket_messages';

    protected array $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'message_html',
        'is_internal',
        'source',
        'attachments',
        'email_message_id',
    ];

    public function getByTicket(int $ticketId, bool $includeInternal = true): array
    {
        $sql = "SELECT m.*, u.name as user_name, u.email as user_email,
                       u.role as user_role, u.avatar as user_avatar
                FROM {$this->table} m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.ticket_id = ?";

        if (!$includeInternal) {
            $sql .= " AND m.is_internal = 0";
        }

        $sql .= " ORDER BY m.created_at ASC";

        $messages = $this->db->select($sql, [$ticketId]);

        // Fetch attachments for each message
        foreach ($messages as &$message) {
            $message['attachment_files'] = $this->db->select(
                "SELECT * FROM attachments WHERE message_id = ? ORDER BY created_at ASC",
                [$message['id']]
            );
        }

        return $messages;
    }

    public function addReply(int $ticketId, int $userId, string $message, bool $isInternal = false, string $source = 'web', array $attachments = []): int
    {
        $messageId = $this->create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message' => $message,
            'message_html' => nl2br(htmlspecialchars($message)),
            'is_internal' => $isInternal ? 1 : 0,
            'source' => $source,
            'attachments' => !empty($attachments) ? json_encode($attachments) : null,
        ]);

        // Update ticket's first_response_at if this is from an agent
        $user = $this->db->selectOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($user && in_array($user['role'], ['admin', 'agent'])) {
            $ticket = $this->db->selectOne(
                "SELECT first_response_at FROM tickets WHERE id = ?",
                [$ticketId]
            );
            if ($ticket && !$ticket['first_response_at']) {
                $this->db->update(
                    'tickets',
                    ['first_response_at' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$ticketId]
                );
            }
        }

        // Update ticket updated_at
        $this->db->update('tickets', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);

        return $messageId;
    }

    public function findByEmailMessageId(string $messageId): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE email_message_id = ?",
            [$messageId]
        );
    }

    public function getLatestByTicket(int $ticketId): ?array
    {
        return $this->db->selectOne(
            "SELECT m.*, u.name as user_name
             FROM {$this->table} m
             LEFT JOIN users u ON m.user_id = u.id
             WHERE m.ticket_id = ? AND m.is_internal = 0
             ORDER BY m.created_at DESC
             LIMIT 1",
            [$ticketId]
        );
    }
}
