<?php

namespace App\Models;

class SlaPolicy extends Model
{
    protected string $table = 'sla_policies';

    protected array $fillable = [
        'company_id',
        'name',
        'priority',
        'response_time_minutes',
        'resolution_time_minutes',
        'is_active',
        'business_hours_only',
    ];

    /**
     * Get active SLA policy for a company and priority
     */
    public function getByPriority(int $companyId, string $priority): ?array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE company_id = ? AND priority = ? AND is_active = 1
                LIMIT 1";

        return $this->db->selectOne($sql, [$companyId, $priority]);
    }

    /**
     * Get all active SLA policies for a company
     */
    public function getActiveByCompany(int $companyId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE company_id = ? AND is_active = 1
                ORDER BY
                    CASE priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END";

        return $this->db->select($sql, [$companyId]);
    }

    /**
     * Calculate SLA due dates based on ticket creation time
     *
     * @param string $createdAt - Ticket creation timestamp
     * @param int $responseMinutes - Response SLA in minutes
     * @param int $resolutionMinutes - Resolution SLA in minutes
     * @param bool $businessHoursOnly - Count only business hours (9-5 Mon-Fri)
     * @return array ['response_due' => timestamp, 'resolution_due' => timestamp]
     */
    public function calculateDueDates(
        string $createdAt,
        int $responseMinutes,
        int $resolutionMinutes,
        bool $businessHoursOnly = false
    ): array {
        $created = strtotime($createdAt);

        if (!$businessHoursOnly) {
            // Simple calculation: add minutes to creation time
            return [
                'response_due' => date('Y-m-d H:i:s', strtotime("+{$responseMinutes} minutes", $created)),
                'resolution_due' => date('Y-m-d H:i:s', strtotime("+{$resolutionMinutes} minutes", $created)),
            ];
        }

        // Business hours calculation (9 AM - 5 PM, Mon-Fri)
        return [
            'response_due' => $this->addBusinessMinutes($created, $responseMinutes),
            'resolution_due' => $this->addBusinessMinutes($created, $resolutionMinutes),
        ];
    }

    /**
     * Add minutes counting only business hours (9-5 Mon-Fri)
     */
    private function addBusinessMinutes(int $startTimestamp, int $minutes): string
    {
        $current = $startTimestamp;
        $remainingMinutes = $minutes;

        $businessStart = 9; // 9 AM
        $businessEnd = 17;  // 5 PM
        $businessMinutesPerDay = ($businessEnd - $businessStart) * 60; // 480 minutes

        while ($remainingMinutes > 0) {
            $dayOfWeek = (int)date('N', $current); // 1 (Mon) - 7 (Sun)
            $hour = (int)date('H', $current);
            $minute = (int)date('i', $current);

            // Skip weekends
            if ($dayOfWeek >= 6) { // Saturday or Sunday
                // Move to Monday 9 AM
                $daysToAdd = (8 - $dayOfWeek) % 7;
                if ($daysToAdd == 0) $daysToAdd = 1;
                $current = strtotime("+{$daysToAdd} days", mktime($businessStart, 0, 0, date('m', $current), date('d', $current), date('Y', $current)));
                continue;
            }

            // If before business hours, move to 9 AM
            if ($hour < $businessStart) {
                $current = mktime($businessStart, 0, 0, date('m', $current), date('d', $current), date('Y', $current));
                continue;
            }

            // If after business hours, move to next day 9 AM
            if ($hour >= $businessEnd) {
                $current = strtotime("+1 day", mktime($businessStart, 0, 0, date('m', $current), date('d', $current), date('Y', $current)));
                continue;
            }

            // Calculate remaining business minutes in current day
            $currentMinutes = ($hour * 60) + $minute;
            $businessEndMinutes = $businessEnd * 60;
            $remainingInDay = $businessEndMinutes - $currentMinutes;

            if ($remainingMinutes <= $remainingInDay) {
                // Can finish today
                $current = strtotime("+{$remainingMinutes} minutes", $current);
                $remainingMinutes = 0;
            } else {
                // Use up remaining day and move to next business day
                $remainingMinutes -= $remainingInDay;
                $current = strtotime("+1 day", mktime($businessStart, 0, 0, date('m', $current), date('d', $current), date('Y', $current)));
            }
        }

        return date('Y-m-d H:i:s', $current);
    }

    /**
     * Check if SLA is breached
     *
     * @param string|null $actualTime - When action was taken (or null if not yet)
     * @param string $dueTime - When it should have been done
     * @return bool
     */
    public function isBreached(?string $actualTime, string $dueTime): bool
    {
        if ($actualTime === null) {
            // Not done yet - check against current time
            return time() > strtotime($dueTime);
        }

        // Compare actual vs due
        return strtotime($actualTime) > strtotime($dueTime);
    }

    /**
     * Calculate actual time taken in minutes
     */
    public function calculateActualMinutes(string $startTime, string $endTime): int
    {
        return (int)((strtotime($endTime) - strtotime($startTime)) / 60);
    }

    /**
     * Get SLA compliance percentage for a company
     */
    public function getComplianceRate(int $companyId, string $period = '30days'): array
    {
        $interval = match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30
        };

        $sql = "SELECT
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN sla_response_breached = 0 THEN 1 ELSE 0 END) as response_met,
                    SUM(CASE WHEN sla_response_breached = 1 THEN 1 ELSE 0 END) as response_breached,
                    SUM(CASE WHEN sla_resolution_breached = 0 AND status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolution_met,
                    SUM(CASE WHEN sla_resolution_breached = 1 THEN 1 ELSE 0 END) as resolution_breached,
                    ROUND(AVG(response_time_minutes), 2) as avg_response_time,
                    ROUND(AVG(resolution_time_minutes), 2) as avg_resolution_time
                FROM tickets
                WHERE company_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND sla_response_due_at IS NOT NULL";

        $stats = $this->db->selectOne($sql, [$companyId, $interval]);

        if (!$stats || $stats['total_tickets'] == 0) {
            return [
                'total_tickets' => 0,
                'response_compliance' => 0,
                'resolution_compliance' => 0,
                'avg_response_time' => 0,
                'avg_resolution_time' => 0,
            ];
        }

        return [
            'total_tickets' => (int)$stats['total_tickets'],
            'response_compliance' => $stats['total_tickets'] > 0
                ? round(($stats['response_met'] / $stats['total_tickets']) * 100, 2)
                : 0,
            'resolution_compliance' => $stats['total_tickets'] > 0
                ? round(($stats['resolution_met'] / $stats['total_tickets']) * 100, 2)
                : 0,
            'avg_response_time' => (float)($stats['avg_response_time'] ?? 0),
            'avg_resolution_time' => (float)($stats['avg_resolution_time'] ?? 0),
            'response_met' => (int)$stats['response_met'],
            'response_breached' => (int)$stats['response_breached'],
            'resolution_met' => (int)$stats['resolution_met'],
            'resolution_breached' => (int)$stats['resolution_breached'],
        ];
    }
}
