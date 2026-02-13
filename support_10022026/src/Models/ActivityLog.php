<?php

namespace App\Models;

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    protected array $fillable = [
        'company_id',
        'ticket_id',
        'user_id',
        'action',
        'description',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
    ];

    public function log(
        int $companyId,
        ?int $ticketId,
        ?int $userId,
        string $action,
        string $description = null,
        array $oldValue = null,
        array $newValue = null
    ): int {
        return $this->create([
            'company_id' => $companyId,
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    public function getByTicket(int $ticketId, int $limit = 50): array
    {
        return $this->db->select(
            "SELECT a.*, u.name as user_name
             FROM {$this->table} a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.ticket_id = ?
             ORDER BY a.created_at DESC
             LIMIT ?",
            [$ticketId, $limit]
        );
    }

    public function getRecent(int $limit = 50): array
    {
        return $this->db->select(
            "SELECT a.*, u.name as user_name, t.ticket_number, t.subject
             FROM {$this->table} a
             LEFT JOIN users u ON a.user_id = u.id
             LEFT JOIN tickets t ON a.ticket_id = t.id
             WHERE a.company_id = ?
             ORDER BY a.created_at DESC
             LIMIT ?",
            [$this->companyId, $limit]
        );
    }
}
