<?php

namespace App\Models;

class Analytics extends Model
{
    protected string $table = 'analytics_snapshots';

    protected array $fillable = [
        'company_id',
        'snapshot_date',
        'metric_type',
        'tickets_created',
        'tickets_resolved',
        'tickets_closed',
        'sla_response_met',
        'sla_response_breached',
        'sla_resolution_met',
        'sla_resolution_breached',
        'avg_response_time_minutes',
        'avg_resolution_time_minutes',
        'avg_customer_satisfaction',
        'total_agents',
        'avg_tickets_per_agent',
    ];

    /**
     * Get analytics for a specific period
     *
     * @param int $companyId
     * @param string $startDate - Y-m-d format
     * @param string $endDate - Y-m-d format
     * @param string $metricType - 'daily', 'weekly', 'monthly'
     * @return array
     */
    public function getForPeriod(int $companyId, string $startDate, string $endDate, string $metricType = 'daily'): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE company_id = ?
                AND snapshot_date BETWEEN ? AND ?
                AND metric_type = ?
                ORDER BY snapshot_date ASC";

        return $this->db->select($sql, [$companyId, $startDate, $endDate, $metricType]);
    }

    /**
     * Generate daily snapshot for a company
     * This should be run by a CRON job daily
     */
    public function generateDailySnapshot(int $companyId, ?string $date = null): int
    {
        $date = $date ?? date('Y-m-d');

        // Get ticket volume metrics
        $volumeMetrics = $this->db->selectOne(
            "SELECT
                COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as tickets_created,
                COUNT(CASE WHEN DATE(resolved_at) = ? THEN 1 END) as tickets_resolved,
                COUNT(CASE WHEN DATE(closed_at) = ? THEN 1 END) as tickets_closed
            FROM tickets
            WHERE company_id = ?",
            [$date, $date, $date, $companyId]
        );

        // Get SLA metrics
        $slaMetrics = $this->db->selectOne(
            "SELECT
                SUM(CASE WHEN sla_response_breached = 0 AND first_response_at IS NOT NULL AND DATE(first_response_at) = ? THEN 1 ELSE 0 END) as sla_response_met,
                SUM(CASE WHEN sla_response_breached = 1 AND DATE(first_response_at) = ? THEN 1 ELSE 0 END) as sla_response_breached,
                SUM(CASE WHEN sla_resolution_breached = 0 AND resolved_at IS NOT NULL AND DATE(resolved_at) = ? THEN 1 ELSE 0 END) as sla_resolution_met,
                SUM(CASE WHEN sla_resolution_breached = 1 AND DATE(resolved_at) = ? THEN 1 ELSE 0 END) as sla_resolution_breached,
                AVG(CASE WHEN first_response_at IS NOT NULL AND DATE(first_response_at) = ? THEN response_time_minutes END) as avg_response_time,
                AVG(CASE WHEN resolved_at IS NOT NULL AND DATE(resolved_at) = ? THEN resolution_time_minutes END) as avg_resolution_time
            FROM tickets
            WHERE company_id = ?
            AND sla_response_due_at IS NOT NULL",
            [$date, $date, $date, $date, $date, $date, $companyId]
        );

        // Get customer satisfaction metrics
        $satisfactionMetrics = $this->db->selectOne(
            "SELECT AVG(rating) as avg_satisfaction
            FROM ticket_surveys
            WHERE company_id = ?
            AND DATE(rated_at) = ?
            AND rating IS NOT NULL",
            [$companyId, $date]
        );

        // Get agent metrics
        $agentMetrics = $this->db->selectOne(
            "SELECT
                COUNT(DISTINCT id) as total_agents,
                COUNT(CASE WHEN DATE(t.created_at) = ? THEN t.id END) / NULLIF(COUNT(DISTINCT u.id), 0) as avg_tickets_per_agent
            FROM users u
            LEFT JOIN tickets t ON u.id = t.assigned_to AND t.company_id = ?
            WHERE u.company_id = ?
            AND u.role IN ('admin', 'agent', 'front_office_agent', 'back_office_agent')
            AND u.is_active = 1",
            [$date, $companyId, $companyId]
        );

        // Check if snapshot already exists
        $existing = $this->db->selectOne(
            "SELECT id FROM {$this->table}
            WHERE company_id = ? AND snapshot_date = ? AND metric_type = 'daily'",
            [$companyId, $date]
        );

        $data = [
            'company_id' => $companyId,
            'snapshot_date' => $date,
            'metric_type' => 'daily',
            'tickets_created' => (int)($volumeMetrics['tickets_created'] ?? 0),
            'tickets_resolved' => (int)($volumeMetrics['tickets_resolved'] ?? 0),
            'tickets_closed' => (int)($volumeMetrics['tickets_closed'] ?? 0),
            'sla_response_met' => (int)($slaMetrics['sla_response_met'] ?? 0),
            'sla_response_breached' => (int)($slaMetrics['sla_response_breached'] ?? 0),
            'sla_resolution_met' => (int)($slaMetrics['sla_resolution_met'] ?? 0),
            'sla_resolution_breached' => (int)($slaMetrics['sla_resolution_breached'] ?? 0),
            'avg_response_time_minutes' => round((float)($slaMetrics['avg_response_time'] ?? 0), 2),
            'avg_resolution_time_minutes' => round((float)($slaMetrics['avg_resolution_time'] ?? 0), 2),
            'avg_customer_satisfaction' => round((float)($satisfactionMetrics['avg_satisfaction'] ?? 0), 2),
            'total_agents' => (int)($agentMetrics['total_agents'] ?? 0),
            'avg_tickets_per_agent' => round((float)($agentMetrics['avg_tickets_per_agent'] ?? 0), 2),
        ];

        if ($existing) {
            // Update existing snapshot
            return $this->update($existing['id'], $data);
        }

        // Create new snapshot
        return $this->create($data);
    }

    /**
     * Get KPI summary for dashboard
     */
    public function getKpiSummary(int $companyId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate = date('Y-m-d');

        $summary = $this->db->selectOne(
            "SELECT
                SUM(tickets_created) as total_created,
                SUM(tickets_resolved) as total_resolved,
                SUM(tickets_closed) as total_closed,
                SUM(sla_response_met) as total_response_met,
                SUM(sla_response_breached) as total_response_breached,
                SUM(sla_resolution_met) as total_resolution_met,
                SUM(sla_resolution_breached) as total_resolution_breached,
                AVG(avg_response_time_minutes) as overall_avg_response_time,
                AVG(avg_resolution_time_minutes) as overall_avg_resolution_time,
                AVG(avg_customer_satisfaction) as overall_avg_satisfaction,
                AVG(avg_tickets_per_agent) as overall_avg_tickets_per_agent
            FROM {$this->table}
            WHERE company_id = ?
            AND snapshot_date BETWEEN ? AND ?
            AND metric_type = 'daily'",
            [$companyId, $startDate, $endDate]
        );

        if (!$summary || $summary['total_created'] == 0) {
            return [
                'total_created' => 0,
                'total_resolved' => 0,
                'resolution_rate' => 0,
                'response_sla_compliance' => 0,
                'resolution_sla_compliance' => 0,
                'avg_response_time' => 0,
                'avg_resolution_time' => 0,
                'avg_satisfaction' => 0,
                'avg_tickets_per_agent' => 0,
            ];
        }

        $totalSlaTickets = $summary['total_response_met'] + $summary['total_response_breached'];
        $totalResolutionSla = $summary['total_resolution_met'] + $summary['total_resolution_breached'];

        return [
            'total_created' => (int)$summary['total_created'],
            'total_resolved' => (int)$summary['total_resolved'],
            'total_closed' => (int)$summary['total_closed'],
            'resolution_rate' => $summary['total_created'] > 0
                ? round(($summary['total_resolved'] / $summary['total_created']) * 100, 2)
                : 0,
            'response_sla_compliance' => $totalSlaTickets > 0
                ? round(($summary['total_response_met'] / $totalSlaTickets) * 100, 2)
                : 0,
            'resolution_sla_compliance' => $totalResolutionSla > 0
                ? round(($summary['total_resolution_met'] / $totalResolutionSla) * 100, 2)
                : 0,
            'avg_response_time' => round((float)$summary['overall_avg_response_time'], 2),
            'avg_resolution_time' => round((float)$summary['overall_avg_resolution_time'], 2),
            'avg_satisfaction' => round((float)$summary['overall_avg_satisfaction'], 2),
            'avg_tickets_per_agent' => round((float)$summary['overall_avg_tickets_per_agent'], 2),
        ];
    }

    /**
     * Get trend data for charts (ISO 20000 KPIs)
     */
    public function getTrendData(int $companyId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate = date('Y-m-d');

        $data = $this->db->select(
            "SELECT
                snapshot_date,
                tickets_created,
                tickets_resolved,
                sla_response_met,
                sla_response_breached,
                sla_resolution_met,
                sla_resolution_breached,
                avg_response_time_minutes,
                avg_resolution_time_minutes,
                avg_customer_satisfaction
            FROM {$this->table}
            WHERE company_id = ?
            AND snapshot_date BETWEEN ? AND ?
            AND metric_type = 'daily'
            ORDER BY snapshot_date ASC",
            [$companyId, $startDate, $endDate]
        );

        // Process data for charts
        $labels = [];
        $ticketsCreated = [];
        $ticketsResolved = [];
        $responseCompliance = [];
        $resolutionCompliance = [];
        $avgResponseTime = [];
        $avgResolutionTime = [];
        $satisfaction = [];

        foreach ($data as $row) {
            $labels[] = date('M j', strtotime($row['snapshot_date']));
            $ticketsCreated[] = (int)$row['tickets_created'];
            $ticketsResolved[] = (int)$row['tickets_resolved'];

            $totalResponse = $row['sla_response_met'] + $row['sla_response_breached'];
            $responseCompliance[] = $totalResponse > 0
                ? round(($row['sla_response_met'] / $totalResponse) * 100, 2)
                : 0;

            $totalResolution = $row['sla_resolution_met'] + $row['sla_resolution_breached'];
            $resolutionCompliance[] = $totalResolution > 0
                ? round(($row['sla_resolution_met'] / $totalResolution) * 100, 2)
                : 0;

            $avgResponseTime[] = round((float)$row['avg_response_time_minutes'], 2);
            $avgResolutionTime[] = round((float)$row['avg_resolution_time_minutes'], 2);
            $satisfaction[] = round((float)$row['avg_customer_satisfaction'], 2);
        }

        return [
            'labels' => $labels,
            'tickets_created' => $ticketsCreated,
            'tickets_resolved' => $ticketsResolved,
            'response_compliance' => $responseCompliance,
            'resolution_compliance' => $resolutionCompliance,
            'avg_response_time' => $avgResponseTime,
            'avg_resolution_time' => $avgResolutionTime,
            'satisfaction' => $satisfaction,
        ];
    }

    /**
     * Get category breakdown for analytics
     */
    public function getCategoryBreakdown(int $companyId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return $this->db->select(
            "SELECT
                c.name as category_name,
                c.color as category_color,
                COUNT(t.id) as ticket_count,
                AVG(t.response_time_minutes) as avg_response_time,
                AVG(t.resolution_time_minutes) as avg_resolution_time,
                SUM(CASE WHEN t.sla_response_breached = 0 AND t.first_response_at IS NOT NULL THEN 1 ELSE 0 END) as response_met,
                SUM(CASE WHEN t.sla_response_breached = 1 THEN 1 ELSE 0 END) as response_breached
            FROM tickets t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.company_id = ?
            AND t.created_at >= ?
            GROUP BY t.category_id, c.name, c.color
            ORDER BY ticket_count DESC",
            [$companyId, $startDate]
        );
    }

    /**
     * Get agent performance metrics
     */
    public function getAgentPerformance(int $companyId, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return $this->db->select(
            "SELECT
                u.name as agent_name,
                u.email as agent_email,
                COUNT(t.id) as tickets_handled,
                COUNT(CASE WHEN t.status IN ('resolved', 'closed') THEN 1 END) as tickets_resolved,
                AVG(t.response_time_minutes) as avg_response_time,
                AVG(t.resolution_time_minutes) as avg_resolution_time,
                SUM(CASE WHEN t.sla_response_breached = 0 AND t.first_response_at IS NOT NULL THEN 1 ELSE 0 END) as response_sla_met,
                SUM(CASE WHEN t.sla_response_breached = 1 THEN 1 ELSE 0 END) as response_sla_breached,
                AVG(ts.rating) as avg_satisfaction
            FROM users u
            LEFT JOIN tickets t ON u.id = t.assigned_to AND t.company_id = ?
            LEFT JOIN ticket_surveys ts ON t.id = ts.ticket_id
            WHERE u.company_id = ?
            AND u.role IN ('admin', 'agent', 'front_office_agent', 'back_office_agent')
            AND u.is_active = 1
            AND (t.created_at >= ? OR t.created_at IS NULL)
            GROUP BY u.id, u.name, u.email
            ORDER BY tickets_handled DESC",
            [$companyId, $companyId, $startDate]
        );
    }
}
