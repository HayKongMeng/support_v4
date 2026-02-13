<?php

namespace App\Models;

class Category extends Model
{
    protected string $table = 'categories';

    protected array $fillable = [
        'company_id',
        'parent_id',
        'name',
        'description',
        'color',
        'icon',
        'auto_assign_to',
        'keywords',
        'sla_response_hours',
        'sla_resolve_hours',
        'sort_order',
        'is_active',
    ];

    public function getActive(): array
    {
        return $this->db->select(
            "SELECT c.*, u.name as auto_assign_name
             FROM {$this->table} c
             LEFT JOIN users u ON c.auto_assign_to = u.id
             WHERE c.company_id = ? AND c.is_active = 1
             ORDER BY c.sort_order ASC, c.name ASC",
            [$this->companyId]
        );
    }

    public function getWithKeywords(): array
    {
        return $this->db->select(
            "SELECT id, name, keywords FROM {$this->table}
             WHERE company_id = ? AND is_active = 1 AND keywords IS NOT NULL",
            [$this->companyId]
        );
    }

    public function getTree(): array
    {
        $categories = $this->getActive();
        return $this->buildTree($categories);
    }

    private function buildTree(array $items, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = $this->buildTree($items, $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }

    public function findByKeywords(string $text): ?array
    {
        $categories = $this->getWithKeywords();
        $text = strtolower($text);
        $bestMatch = null;
        $bestScore = 0;

        foreach ($categories as $category) {
            $keywords = json_decode($category['keywords'], true) ?? [];
            $score = 0;

            foreach ($keywords as $keyword) {
                if (stripos($text, strtolower($keyword)) !== false) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $category;
            }
        }

        return $bestMatch;
    }

    public function getTicketCounts(): array
    {
        $sql = "SELECT c.id, c.name, c.color,
                       COUNT(t.id) as ticket_count,
                       SUM(CASE WHEN t.status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as open_count
                FROM {$this->table} c
                LEFT JOIN tickets t ON c.id = t.category_id
                WHERE c.company_id = ? AND c.is_active = 1
                GROUP BY c.id
                ORDER BY ticket_count DESC";

        return $this->db->select($sql, [$this->companyId]);
    }
}
