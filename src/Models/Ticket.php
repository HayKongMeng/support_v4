<?php

namespace App\Models;

class Ticket extends Model
{
    protected string $table = 'tickets';

    protected array $fillable = [
        'company_id',
        'ticket_number',
        'subject',
        'description',
        'status',
        'priority',
        'source',
        'category_id',
        'assigned_to',
        'requester_id',
        'requester_email',
        'requester_name',
        'ai_suggested_category',
        'ai_suggested_priority',
        'ai_confidence_score',
        'ai_classification_data',
        'tags',
        'custom_fields',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'sla_response_due_at',
        'sla_resolution_due_at',
        'sla_response_breached',
        'sla_resolution_breached',
        'response_time_minutes',
        'resolution_time_minutes',
    ];

    public function generateTicketNumber(): string
    {
        $prefix = 'TKT';
        $lastTicket = $this->db->selectOne(
            "SELECT ticket_number FROM {$this->table}
             WHERE company_id = ? ORDER BY id DESC LIMIT 1",
            [$this->companyId]
        );

        if ($lastTicket) {
            $lastNumber = (int) substr($lastTicket['ticket_number'], strlen($prefix) + 1);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function findByTicketNumber(string $ticketNumber): ?array
    {
        return $this->db->selectOne(
            "SELECT t.*, c.name as category_name, c.color as category_color,
                    u.name as assigned_name, r.name as requester_name_full
             FROM {$this->table} t
             LEFT JOIN categories c ON t.category_id = c.id
             LEFT JOIN users u ON t.assigned_to = u.id
             LEFT JOIN users r ON t.requester_id = r.id
             WHERE t.ticket_number = ? AND t.company_id = ?",
            [$ticketNumber, $this->companyId]
        );
    }

    public function getWithRelations(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT t.*, c.name as category_name, c.color as category_color,
                    u.name as assigned_name, u.email as assigned_email,
                    r.name as requester_name_full, r.email as requester_email_full
             FROM {$this->table} t
             LEFT JOIN categories c ON t.category_id = c.id
             LEFT JOIN users u ON t.assigned_to = u.id
             LEFT JOIN users r ON t.requester_id = r.id
             WHERE t.id = ? AND t.company_id = ?",
            [$id, $this->companyId]
        );
    }

    public function getFiltered(array $filters = [], int $page = 1, int $perPage = 25, ?array $user = null): array
    {
        $sql = "SELECT t.*, c.name as category_name, c.color as category_color,
                       u.name as assigned_name, r.name as requester_name_full
                FROM {$this->table} t
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users r ON t.requester_id = r.id
                WHERE t.company_id = ?";
        $countSql = "SELECT COUNT(*) as count FROM {$this->table} t WHERE t.company_id = ?";
        $params = [$this->companyId];

        if ($user && in_array($user['role'] ?? '', ['agent', 'front_office_agent', 'back_office_agent'])) {
            $sql .= " AND (t.assigned_to = ? OR t.requester_id = ?)";
            $countSql .= " AND (t.assigned_to = ? OR t.requester_id = ?)";
            $params[] = $user['id'];
            $params[] = $user['id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $countSql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $countSql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND t.category_id = ?";
            $countSql .= " AND t.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['assigned_to'])) {
            if ($filters['assigned_to'] === 'unassigned') {
                $sql .= " AND t.assigned_to IS NULL";
                $countSql .= " AND t.assigned_to IS NULL";
            } else {
                $sql .= " AND t.assigned_to = ?";
                $countSql .= " AND t.assigned_to = ?";
                $params[] = $filters['assigned_to'];
            }
        }

        if (!empty($filters['requester_id'])) {
            $sql .= " AND t.requester_id = ?";
            $countSql .= " AND t.requester_id = ?";
            $params[] = $filters['requester_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (t.subject LIKE ? OR t.ticket_number LIKE ? OR t.requester_email LIKE ?)";
            $countSql .= " AND (t.subject LIKE ? OR t.ticket_number LIKE ? OR t.requester_email LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search]);
        }

        // Get total count
        $total = $this->db->selectOne($countSql, $params)['count'] ?? 0;

        // Add ordering and pagination
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDir = strtoupper($filters['order_dir'] ?? 'DESC');
        $orderDir = in_array($orderDir, ['ASC', 'DESC']) ? $orderDir : 'DESC';

        $sql .= " ORDER BY t.{$orderBy} {$orderDir}";
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;

        $items = $this->db->select($sql, $params);

        return [
            'items' => $items,
            'total' => (int) $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function getByRequester(int $requesterId, int $page = 1, int $perPage = 25, ?array $user = null): array
    {
        return $this->getFiltered(['requester_id' => $requesterId], $page, $perPage, $user);
    }

    public function getStats(?array $user = null): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN assigned_to IS NULL AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as unassigned
                FROM {$this->table}
                WHERE company_id = ?";

        $params = [$this->companyId];

        if ($user && in_array($user['role'] ?? '', ['agent', 'front_office_agent', 'back_office_agent'])) {
            $sql .= " AND (assigned_to = ? OR requester_id = ?)";
            $params[] = $user['id'];
            $params[] = $user['id'];
        }

        return $this->db->selectOne($sql, $params) ?? [];
    }

    public function getStatsByPeriod(string $period = 'day', int $days = 30, ?array $user = null): array
    {
        $format = $period === 'day' ? '%Y-%m-%d' : '%Y-%m';

        $sql = "SELECT
                    DATE_FORMAT(created_at, '{$format}') as period,
                    COUNT(*) as created,
                    SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved
                FROM {$this->table}
                WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";

        $params = [$this->companyId, $days];

        if ($user && in_array($user['role'] ?? '', ['agent', 'front_office_agent', 'back_office_agent'])) {
            $sql .= " AND (assigned_to = ? OR requester_id = ?)";
            $params[] = $user['id'];
            $params[] = $user['id'];
        }

        $sql .= " GROUP BY period ORDER BY period ASC";

        return $this->db->select($sql, $params);
    }

    public function updateStatus(int $id, string $status): int
    {
        $data = ['status' => $status];

        if ($status === 'resolved') {
            $data['resolved_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'closed') {
            $data['closed_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($id, $data);
    }

    public function assign(int $id, ?int $userId): int
    {
        return $this->update($id, ['assigned_to' => $userId]);
    }

    /**
     * Override create to automatically calculate SLA.
     */
    public function create(array $data): int
    {
        $ticketId = parent::create($data);

        $this->calculateAndSetSla($ticketId, $data['priority'] ?? 'medium');

        return $ticketId;
    }

    /**
     * Calculate and set SLA due dates for a ticket.
     */
    public function calculateAndSetSla(int $ticketId, string $priority): void
    {
        $slaModel = new SlaPolicy($this->db);
        $slaModel->setCompanyId($this->companyId);

        $slaPolicy = $slaModel->getByPriority($this->companyId, $priority);

        if (!$slaPolicy) {
            return;
        }

        $ticket = $this->find($ticketId);
        if (!$ticket) {
            return;
        }

        $dueDates = $slaModel->calculateDueDates(
            $ticket['created_at'],
            (int)$slaPolicy['response_time_minutes'],
            (int)$slaPolicy['resolution_time_minutes'],
            (bool)$slaPolicy['business_hours_only']
        );

        $this->update($ticketId, [
            'sla_response_due_at' => $dueDates['response_due'],
            'sla_resolution_due_at' => $dueDates['resolution_due'],
        ]);
    }

    /**
     * Update SLA tracking when first response is made.
     */
    public function updateFirstResponseSla(int $ticketId): void
    {
        $ticket = $this->find($ticketId);
        if (!$ticket || !$ticket['sla_response_due_at'] || !$ticket['first_response_at']) {
            return;
        }

        $slaModel = new SlaPolicy($this->db);

        $responseTime = $slaModel->calculateActualMinutes(
            $ticket['created_at'],
            $ticket['first_response_at']
        );

        $isBreached = $slaModel->isBreached(
            $ticket['first_response_at'],
            $ticket['sla_response_due_at']
        );

        $this->update($ticketId, [
            'response_time_minutes' => $responseTime,
            'sla_response_breached' => $isBreached ? 1 : 0,
        ]);
    }

    /**
     * Update SLA tracking when ticket is resolved.
     */
    public function updateResolutionSla(int $ticketId): void
    {
        $ticket = $this->find($ticketId);
        if (!$ticket || !$ticket['sla_resolution_due_at'] || !$ticket['resolved_at']) {
            return;
        }

        $slaModel = new SlaPolicy($this->db);

        $resolutionTime = $slaModel->calculateActualMinutes(
            $ticket['created_at'],
            $ticket['resolved_at']
        );

        $isBreached = $slaModel->isBreached(
            $ticket['resolved_at'],
            $ticket['sla_resolution_due_at']
        );

        $this->update($ticketId, [
            'resolution_time_minutes' => $resolutionTime,
            'sla_resolution_breached' => $isBreached ? 1 : 0,
        ]);
    }

    /**
     * Get tickets with SLA about to breach.
     */
    public function getSlaNearBreach(int $minutes = 60, ?array $user = null): array
    {
        $sql = "SELECT t.*, c.name as category_name, u.name as assigned_name
                FROM {$this->table} t
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.company_id = ?
                AND t.status NOT IN ('resolved', 'closed')
                AND (
                    (t.first_response_at IS NULL AND t.sla_response_due_at <= DATE_ADD(NOW(), INTERVAL ? MINUTE))
                    OR
                    (t.resolved_at IS NULL AND t.sla_resolution_due_at <= DATE_ADD(NOW(), INTERVAL ? MINUTE))
                )";

        $params = [$this->companyId, $minutes, $minutes];

        if ($user && in_array($user['role'] ?? '', ['agent', 'front_office_agent', 'back_office_agent'])) {
            $sql .= " AND (t.assigned_to = ? OR t.requester_id = ?)";
            $params[] = $user['id'];
            $params[] = $user['id'];
        }

        $sql .= " ORDER BY
                  CASE
                      WHEN t.first_response_at IS NULL THEN t.sla_response_due_at
                      ELSE t.sla_resolution_due_at
                  END ASC";

        return $this->db->select($sql, $params);
    }

    /**
     * Get SLA statistics for tickets.
     */
    public function getSlaStats(?array $user = null): array
    {
        $sql = "SELECT
                    COUNT(*) as total_with_sla,
                    SUM(CASE WHEN sla_response_breached = 1 THEN 1 ELSE 0 END) as response_breached,
                    SUM(CASE WHEN sla_response_breached = 0 AND first_response_at IS NOT NULL THEN 1 ELSE 0 END) as response_met,
                    SUM(CASE WHEN sla_resolution_breached = 1 THEN 1 ELSE 0 END) as resolution_breached,
                    SUM(CASE WHEN sla_resolution_breached = 0 AND resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolution_met,
                    SUM(CASE WHEN first_response_at IS NULL AND NOW() > sla_response_due_at THEN 1 ELSE 0 END) as response_overdue,
                    SUM(CASE WHEN resolved_at IS NULL AND status NOT IN ('resolved', 'closed') AND NOW() > sla_resolution_due_at THEN 1 ELSE 0 END) as resolution_overdue
                FROM {$this->table}
                WHERE company_id = ? AND sla_response_due_at IS NOT NULL";

        $params = [$this->companyId];

        if ($user && in_array($user['role'] ?? '', ['agent', 'front_office_agent', 'back_office_agent'])) {
            $sql .= " AND (assigned_to = ? OR requester_id = ?)";
            $params[] = $user['id'];
            $params[] = $user['id'];
        }

        return $this->db->selectOne($sql, $params) ?? [];
    }
}
