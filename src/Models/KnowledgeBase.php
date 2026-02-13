<?php

namespace App\Models;

class KnowledgeBase extends Model
{
    protected string $table = 'knowledge_base';

    protected array $fillable = [
        'company_id',
        'author_id',
        'category_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'tags',
        'views',
        'is_published',
        'is_featured',
        'published_at',
    ];

    /**
     * Generate a unique slug from title
     */
    public function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE slug = ? AND company_id = ?";
        $params = [$slug, $this->companyId];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        return $this->db->selectOne($sql, $params) !== null;
    }

    /**
     * Get published articles with author info
     */
    public function getPublished(int $limit = 20, int $offset = 0, ?string $search = null, ?int $categoryId = null): array
    {
        $sql = "SELECT kb.*, u.name as author_name, c.name as category_name, c.color as category_color
                FROM {$this->table} kb
                LEFT JOIN users u ON kb.author_id = u.id
                LEFT JOIN categories c ON kb.category_id = c.id
                WHERE kb.company_id = ? AND kb.is_published = 1";
        $params = [$this->companyId];

        if ($search) {
            $sql .= " AND (kb.title LIKE ? OR kb.content LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($categoryId) {
            $sql .= " AND kb.category_id = ?";
            $params[] = $categoryId;
        }

        $sql .= " ORDER BY kb.is_featured DESC, kb.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->select($sql, $params);
    }

    /**
     * Get all articles with author info (for admin)
     */
    public function getAllWithAuthor(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT kb.*, u.name as author_name, c.name as category_name, c.color as category_color
                FROM {$this->table} kb
                LEFT JOIN users u ON kb.author_id = u.id
                LEFT JOIN categories c ON kb.category_id = c.id
                WHERE kb.company_id = ?
                ORDER BY kb.created_at DESC LIMIT ? OFFSET ?";

        return $this->db->select($sql, [$this->companyId, $limit, $offset]);
    }

    /**
     * Get article by slug
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT kb.*, u.name as author_name, c.name as category_name, c.color as category_color
                FROM {$this->table} kb
                LEFT JOIN users u ON kb.author_id = u.id
                LEFT JOIN categories c ON kb.category_id = c.id
                WHERE kb.slug = ? AND kb.company_id = ?";

        return $this->db->selectOne($sql, [$slug, $this->companyId]);
    }

    /**
     * Get article by ID with author
     */
    public function findWithAuthor(int $id): ?array
    {
        $sql = "SELECT kb.*, u.name as author_name, c.name as category_name, c.color as category_color
                FROM {$this->table} kb
                LEFT JOIN users u ON kb.author_id = u.id
                LEFT JOIN categories c ON kb.category_id = c.id
                WHERE kb.id = ? AND kb.company_id = ?";

        return $this->db->selectOne($sql, [$id, $this->companyId]);
    }

    /**
     * Increment view count
     */
    public function incrementViews(int $id): void
    {
        $this->db->query(
            "UPDATE {$this->table} SET views = views + 1 WHERE id = ? AND company_id = ?",
            [$id, $this->companyId]
        );
    }

    /**
     * Get featured articles
     */
    public function getFeatured(int $limit = 5): array
    {
        $sql = "SELECT kb.*, u.name as author_name
                FROM {$this->table} kb
                LEFT JOIN users u ON kb.author_id = u.id
                WHERE kb.company_id = ? AND kb.is_published = 1 AND kb.is_featured = 1
                ORDER BY kb.created_at DESC LIMIT ?";

        return $this->db->select($sql, [$this->companyId, $limit]);
    }

    /**
     * Get popular articles by views
     */
    public function getPopular(int $limit = 5): array
    {
        $sql = "SELECT kb.*, u.name as author_name
                FROM {$this->table} kb
                LEFT JOIN users u ON kb.author_id = u.id
                WHERE kb.company_id = ? AND kb.is_published = 1
                ORDER BY kb.views DESC LIMIT ?";

        return $this->db->select($sql, [$this->companyId, $limit]);
    }

    /**
     * Count published articles
     */
    public function countPublished(?string $search = null, ?int $categoryId = null): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE company_id = ? AND is_published = 1";
        $params = [$this->companyId];

        if ($search) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($categoryId) {
            $sql .= " AND category_id = ?";
            $params[] = $categoryId;
        }

        $result = $this->db->selectOne($sql, $params);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get articles by author
     */
    public function getByAuthor(int $authorId, int $limit = 20): array
    {
        $sql = "SELECT kb.*, c.name as category_name, c.color as category_color
                FROM {$this->table} kb
                LEFT JOIN categories c ON kb.category_id = c.id
                WHERE kb.author_id = ? AND kb.company_id = ?
                ORDER BY kb.created_at DESC LIMIT ?";

        return $this->db->select($sql, [$authorId, $this->companyId, $limit]);
    }
}
