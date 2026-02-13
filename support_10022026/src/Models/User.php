<?php

namespace App\Models;

class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = [
        'company_id',
        'email',
        'password_hash',
        'name',
        'phone',
        'avatar',
        'role',
        'telegram_chat_id',
        'telegram_username',
        'is_active',
        'email_verified_at',
        'last_login_at',
    ];

    public function findByEmail(string $email, int $companyId = null): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = ?";
        $params = [$email];

        if ($companyId) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        } elseif ($this->companyId) {
            $sql .= " AND company_id = ?";
            $params[] = $this->companyId;
        }

        return $this->db->selectOne($sql, $params);
    }

    public function findByTelegramChatId(int $chatId): ?array
    {
        $sql = "SELECT u.*, c.name as company_name, c.slug as company_slug
                FROM {$this->table} u
                JOIN companies c ON u.company_id = c.id
                WHERE u.telegram_chat_id = ? AND u.is_active = 1";

        return $this->db->selectOne($sql, [$chatId]);
    }

    public function getAgents(): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE company_id = ? AND role IN ('admin', 'agent', 'front_office_agent', 'back_office_agent') AND is_active = 1
                ORDER BY name ASC";

        return $this->db->select($sql, [$this->companyId]);
    }

    public function getCustomers(): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE company_id = ? AND role = 'customer' AND is_active = 1
                ORDER BY name ASC";

        return $this->db->select($sql, [$this->companyId]);
    }

    public function createCustomerIfNotExists(string $email, string $name, int $companyId): int
    {
        $existing = $this->findByEmail($email, $companyId);

        if ($existing) {
            return $existing['id'];
        }

        return $this->db->insert($this->table, [
            'company_id' => $companyId,
            'email' => $email,
            'name' => $name,
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'role' => 'customer',
            'is_active' => 1,
        ]);
    }

    public function linkTelegram(int $userId, int $chatId, string $username = null): int
    {
        return $this->db->update(
            $this->table,
            [
                'telegram_chat_id' => $chatId,
                'telegram_username' => $username,
            ],
            'id = ?',
            [$userId]
        );
    }

    public function search(string $query): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE company_id = ? AND is_active = 1
                AND (name LIKE ? OR email LIKE ?)
                ORDER BY name ASC
                LIMIT 20";

        $search = "%{$query}%";
        return $this->db->select($sql, [$this->companyId, $search, $search]);
    }
}
