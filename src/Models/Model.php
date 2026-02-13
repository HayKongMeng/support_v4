<?php

namespace App\Models;

use App\Core\Database;

abstract class Model
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected ?int $companyId = null;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $params = [$id];

        if ($this->companyId && $this->hasCompanyId()) {
            $sql .= " AND company_id = ?";
            $params[] = $this->companyId;
        }

        return $this->db->selectOne($sql, $params);
    }

    public function all(array $conditions = [], string $orderBy = 'id DESC', int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        $where = [];
        if ($this->companyId && $this->hasCompanyId()) {
            $where[] = "company_id = ?";
            $params[] = $this->companyId;
        }

        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $where[] = "{$field} IN ({$placeholders})";
                $params = array_merge($params, $value);
            } else {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY {$orderBy}";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        return $this->db->select($sql, $params);
    }

    public function create(array $data): int
    {
        $data = $this->filterFillable($data);

        if ($this->companyId && $this->hasCompanyId()) {
            $data['company_id'] = $this->companyId;
        }

        return $this->db->insert($this->table, $data);
    }

    public function update(int $id, array $data): int
    {
        $data = $this->filterFillable($data);

        $where = "{$this->primaryKey} = ?";
        $params = [$id];

        if ($this->companyId && $this->hasCompanyId()) {
            $where .= " AND company_id = ?";
            $params[] = $this->companyId;
        }

        return $this->db->update($this->table, $data, $where, $params);
    }

    public function delete(int $id): int
    {
        $where = "{$this->primaryKey} = ?";
        $params = [$id];

        if ($this->companyId && $this->hasCompanyId()) {
            $where .= " AND company_id = ?";
            $params[] = $this->companyId;
        }

        return $this->db->delete($this->table, $where, $params);
    }

    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];

        $where = [];
        if ($this->companyId && $this->hasCompanyId()) {
            $where[] = "company_id = ?";
            $params[] = $this->companyId;
        }

        foreach ($conditions as $field => $value) {
            $where[] = "{$field} = ?";
            $params[] = $value;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $result = $this->db->selectOne($sql, $params);
        return (int) ($result['count'] ?? 0);
    }

    public function paginate(int $page = 1, int $perPage = 25, array $conditions = [], string $orderBy = 'id DESC'): array
    {
        $total = $this->count($conditions);
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $items = $this->all($conditions, $orderBy, $perPage, $offset);

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages,
        ];
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function hasCompanyId(): bool
    {
        return in_array('company_id', $this->fillable) || $this->table !== 'companies';
    }

    public function raw(string $sql, array $params = []): array
    {
        return $this->db->select($sql, $params);
    }

    public function rawOne(string $sql, array $params = []): ?array
    {
        return $this->db->selectOne($sql, $params);
    }
}
