<?php

namespace App\Models;

class CannedResponse extends Model
{
    protected string $table = 'canned_responses';

    protected array $fillable = [
        'company_id',
        'category_id',
        'title',
        'content',
        'shortcut',
        'is_active',
        'usage_count',
        'created_by',
    ];

    public function getActive(?int $categoryId = null): array
    {
        $sql = "SELECT cr.*, c.name as category_name
                FROM {$this->table} cr
                LEFT JOIN categories c ON cr.category_id = c.id
                WHERE cr.company_id = ? AND cr.is_active = 1";
        $params = [$this->companyId];

        if ($categoryId) {
            $sql .= " AND (cr.category_id = ? OR cr.category_id IS NULL)";
            $params[] = $categoryId;
        }

        $sql .= " ORDER BY cr.usage_count DESC, cr.title ASC";

        return $this->db->select($sql, $params);
    }

    public function findByShortcut(string $shortcut): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table}
             WHERE company_id = ? AND shortcut = ? AND is_active = 1",
            [$this->companyId, $shortcut]
        );
    }

    public function incrementUsage(int $id): void
    {
        $this->db->query(
            "UPDATE {$this->table} SET usage_count = usage_count + 1 WHERE id = ?",
            [$id]
        );
    }

    public function search(string $query): array
    {
        return $this->db->select(
            "SELECT * FROM {$this->table}
             WHERE company_id = ? AND is_active = 1
             AND (title LIKE ? OR content LIKE ? OR shortcut LIKE ?)
             ORDER BY usage_count DESC
             LIMIT 10",
            [$this->companyId, "%{$query}%", "%{$query}%", "%{$query}%"]
        );
    }
}
