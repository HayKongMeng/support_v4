<?php

namespace App\Models;

class Company extends Model
{
    protected string $table = 'companies';

    protected array $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'logo',
        'timezone',
        'settings',
        'is_active',
    ];

    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE slug = ? AND is_active = 1",
            [$slug]
        );
    }

    public function getSettings(int $id): array
    {
        $company = $this->find($id);
        if ($company && $company['settings']) {
            return json_decode($company['settings'], true) ?? [];
        }
        return [];
    }

    public function updateSettings(int $id, array $settings): int
    {
        return $this->db->update(
            $this->table,
            ['settings' => json_encode($settings)],
            'id = ?',
            [$id]
        );
    }

    public function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $originalSlug = $slug;
        $counter = 1;

        while ($this->findBySlug($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
