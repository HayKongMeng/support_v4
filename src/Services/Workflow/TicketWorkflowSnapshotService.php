<?php

namespace App\Services\Workflow;

use App\Core\Database;

class TicketWorkflowSnapshotService
{
    private Database $db;
    private int $companyId;

    public function __construct(Database $db, int $companyId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
    }

    public function getForTicket(int $ticketId): ?array
    {
        try {
            $state = $this->db->selectOne(
                "SELECT s.*,
                        wt.name AS workflow_name,
                        ws.step_name AS current_step_name,
                        ws.approver_type AS current_approver_type,
                        assignee.name AS current_assignee_name
                 FROM ticket_workflow_states s
                 LEFT JOIN workflow_templates wt ON wt.id = s.workflow_id
                 LEFT JOIN workflow_steps ws
                    ON ws.workflow_id = s.workflow_id
                   AND ws.step_order = s.current_step_order
                 LEFT JOIN users assignee ON assignee.id = s.current_assignee_id
                 WHERE s.company_id = ?
                   AND s.ticket_id = ?
                 LIMIT 1",
                [$this->companyId, $ticketId]
            );
        } catch (\Throwable $e) {
            error_log('Workflow snapshot query failed: ' . $e->getMessage());
            return null;
        }

        if (!$state) {
            return null;
        }

        $progress = $this->db->selectOne(
            "SELECT COUNT(*) AS total_steps,
                    SUM(CASE WHEN step_order <= ? THEN 1 ELSE 0 END) AS step_position
             FROM workflow_steps
             WHERE workflow_id = ?
               AND is_active = 1",
            [(int) $state['current_step_order'], (int) $state['workflow_id']]
        );

        $nextStep = $this->db->selectOne(
            "SELECT step_order, step_name, approver_type, sla_minutes
             FROM workflow_steps
             WHERE workflow_id = ?
               AND is_active = 1
               AND step_order > ?
             ORDER BY step_order ASC
             LIMIT 1",
            [(int) $state['workflow_id'], (int) $state['current_step_order']]
        );

        $remainingSeconds = null;
        $isOverdue = false;
        if (!empty($state['due_at'])) {
            $dueTs = strtotime((string) $state['due_at']);
            if ($dueTs !== false) {
                $remainingSeconds = $dueTs - time();
                $isOverdue = $remainingSeconds < 0;
            }
        }

        return [
            'workflow_name' => $state['workflow_name'] ?? null,
            'status' => (string) ($state['status'] ?? 'in_progress'),
            'current_step_order' => (int) ($state['current_step_order'] ?? 0),
            'current_step_name' => $state['current_step_name'] ?? null,
            'current_approver_type' => $state['current_approver_type'] ?? null,
            'current_assignee_name' => $state['current_assignee_name'] ?? null,
            'due_at' => $state['due_at'] ?? null,
            'remaining_seconds' => $remainingSeconds,
            'is_overdue' => $isOverdue,
            'next_step_order' => $nextStep ? (int) ($nextStep['step_order'] ?? 0) : null,
            'next_step_name' => $nextStep['step_name'] ?? null,
            'next_approver_type' => $nextStep['approver_type'] ?? null,
            'next_sla_minutes' => $nextStep ? (int) ($nextStep['sla_minutes'] ?? 0) : null,
            'step_position' => (int) ($progress['step_position'] ?? 0),
            'total_steps' => (int) ($progress['total_steps'] ?? 0),
        ];
    }
}
