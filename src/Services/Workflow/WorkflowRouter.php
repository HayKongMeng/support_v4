<?php

namespace App\Services\Workflow;

use App\Core\Database;
use App\Services\Telegram\TelegramAgentNotifier;

class WorkflowRouter
{
    private Database $db;
    private int $companyId;
    private ?array $companySettings = null;

    public function __construct(Database $db, int $companyId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
    }

    /**
     * Resolve workflow + first assignee for a newly created ticket.
     */
    public function resolveForTicket(string $source, ?int $categoryId = null, ?int $requesterUserId = null): ?array
    {
        $template = $this->findTemplate($source, $categoryId);

        // Fallback routing rule: category auto-assignee.
        $categoryAssigneeId = $this->resolveCategoryAutoAssignee($categoryId);
        $incomingHandlerAssigneeId = $this->resolveIncomingHandlerAssignee($source, $requesterUserId);

        if (!$template) {
            $assigneeId = $categoryAssigneeId ?: $incomingHandlerAssigneeId;
            return $assigneeId
                ? [
                    'template' => null,
                    'step' => null,
                    'assignee_id' => $assigneeId,
                    'due_at' => null,
                    'requester_user_id' => $requesterUserId,
                    'route_type' => 'category',
                ]
                : null;
        }

        $firstStep = $this->findNextStep((int) $template['id'], 0);
        if (!$firstStep) {
            $assigneeId = $categoryAssigneeId ?: $incomingHandlerAssigneeId;
            return $assigneeId
                ? [
                    'template' => null,
                    'step' => null,
                    'assignee_id' => $assigneeId,
                    'due_at' => null,
                    'requester_user_id' => $requesterUserId,
                    'route_type' => 'category',
                ]
                : null;
        }

        $assigneeId = $this->resolveAssigneeId($firstStep, $requesterUserId);
        $dueAt = $this->buildDueAt((int) ($firstStep['sla_minutes'] ?? 120));

        return [
            'template' => $template,
            'step' => $firstStep,
            'assignee_id' => $assigneeId ?: $categoryAssigneeId ?: $incomingHandlerAssigneeId,
            'due_at' => $dueAt,
            'requester_user_id' => $requesterUserId,
            'route_type' => 'workflow',
        ];
    }

    /**
     * Store workflow state and initial workflow log for a ticket.
     */
    public function startTicketWorkflow(int $ticketId, ?int $actorUserId, array $resolved): void
    {
        $assigneeId = isset($resolved['assignee_id']) ? (int) $resolved['assignee_id'] : null;
        $ticket = $this->db->selectOne(
            "SELECT ticket_number
             FROM tickets
             WHERE id = ? AND company_id = ?
             LIMIT 1",
            [$ticketId, $this->companyId]
        );
        $ticketNumber = (string) ($ticket['ticket_number'] ?? '');

        if (empty($resolved['template']) || empty($resolved['step'])) {
            if ($assigneeId) {
                $this->notifyNextAssignee($ticketId, $ticketNumber, $assigneeId, 'Initial assignment');
            }
            return;
        }

        $template = $resolved['template'];
        $step = $resolved['step'];
        $dueAt = $resolved['due_at'] ?? null;

        $existing = $this->db->selectOne(
            "SELECT id FROM ticket_workflow_states WHERE ticket_id = ?",
            [$ticketId]
        );

        if ($existing) {
            return;
        }

        $this->db->insert('ticket_workflow_states', [
            'company_id' => $this->companyId,
            'ticket_id' => $ticketId,
            'workflow_id' => (int) $template['id'],
            'current_step_order' => (int) $step['step_order'],
            'current_assignee_id' => $assigneeId,
            'status' => 'in_progress',
            'due_at' => $dueAt,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $meta = [
            'step_name' => $step['step_name'] ?? null,
            'approver_type' => $step['approver_type'] ?? null,
        ];

        $this->db->insert('ticket_workflow_logs', [
            'company_id' => $this->companyId,
            'ticket_id' => $ticketId,
            'workflow_id' => (int) $template['id'],
            'step_order' => (int) $step['step_order'],
            'action' => 'started',
            'actor_user_id' => $actorUserId,
            'assignee_user_id' => $assigneeId,
            'note' => 'Workflow started',
            'meta' => json_encode($meta),
        ]);

        if ($assigneeId) {
            $stepName = (string) ($step['step_name'] ?? 'Initial workflow step');
            $this->notifyNextAssignee($ticketId, $ticketNumber, $assigneeId, $stepName);
        }
    }

    /**
     * Escalate due workflow states.
     */
    public function processDueEscalations(int $limit = 100): array
    {
        $stats = [
            'completed' => 0,
            'escalated' => 0,
            'skipped' => 0,
        ];

        $states = $this->db->select(
            "SELECT s.*, t.ticket_number, t.status AS ticket_status, t.requester_id
             FROM ticket_workflow_states s
             JOIN tickets t ON t.id = s.ticket_id
             WHERE s.company_id = ?
               AND s.status = 'in_progress'
               AND (
                    t.status IN ('resolved', 'closed')
                    OR (s.due_at IS NOT NULL AND s.due_at <= NOW())
               )
             ORDER BY s.due_at ASC, s.id ASC
             LIMIT ?",
            [$this->companyId, max(1, $limit)]
        );

        foreach ($states as $state) {
            $ticketId = (int) $state['ticket_id'];
            $workflowId = (int) $state['workflow_id'];
            $currentStepOrder = (int) $state['current_step_order'];
            $ticketStatus = (string) ($state['ticket_status'] ?? '');
            $requesterUserId = isset($state['requester_id']) ? (int) $state['requester_id'] : null;

            if (in_array($ticketStatus, ['resolved', 'closed'], true)) {
                $this->completeState((int) $state['id'], $ticketId, $workflowId, $currentStepOrder, 'Ticket already closed');
                $stats['completed']++;
                continue;
            }

            $nextStep = $this->findNextStep($workflowId, $currentStepOrder);
            if (!$nextStep) {
                $this->completeState((int) $state['id'], $ticketId, $workflowId, $currentStepOrder, 'Final step reached');
                $stats['completed']++;
                continue;
            }

            $nextAssigneeId = $this->resolveAssigneeId($nextStep, $requesterUserId);
            $nextDueAt = $this->buildDueAt((int) ($nextStep['sla_minutes'] ?? 120));

            $this->db->update('ticket_workflow_states', [
                'current_step_order' => (int) $nextStep['step_order'],
                'current_assignee_id' => $nextAssigneeId,
                'due_at' => $nextDueAt,
                'last_escalated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $state['id']]);

            if ($nextAssigneeId) {
                $this->db->update('tickets', [
                    'assigned_to' => $nextAssigneeId,
                ], 'id = ? AND company_id = ?', [$ticketId, $this->companyId]);
            }

            $meta = [
                'from_step_order' => $currentStepOrder,
                'to_step_order' => (int) $nextStep['step_order'],
                'step_name' => $nextStep['step_name'] ?? null,
                'approver_type' => $nextStep['approver_type'] ?? null,
            ];

            $this->db->insert('ticket_workflow_logs', [
                'company_id' => $this->companyId,
                'ticket_id' => $ticketId,
                'workflow_id' => $workflowId,
                'step_order' => (int) $nextStep['step_order'],
                'action' => 'escalated',
                'assignee_user_id' => $nextAssigneeId,
                'note' => 'Escalated to next workflow step',
                'meta' => json_encode($meta),
            ]);

            $this->notifyNextAssignee($ticketId, (string) ($state['ticket_number'] ?? ''), $nextAssigneeId, (string) ($nextStep['step_name'] ?? 'Next step'));
            $stats['escalated']++;
        }

        return $stats;
    }

    private function findTemplate(string $source, ?int $categoryId = null): ?array
    {
        $source = strtolower(trim($source));
        if ($source === '') {
            $source = 'all';
        }

        $sql = "SELECT *
                FROM workflow_templates
                WHERE company_id = ?
                  AND is_active = 1
                  AND (trigger_source = 'all' OR trigger_source = ?)";
        $params = [$this->companyId, $source];

        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND (trigger_category_id IS NULL OR trigger_category_id = ?)";
            $params[] = $categoryId;
            $sql .= " ORDER BY
                      CASE WHEN trigger_category_id = ? THEN 0 ELSE 1 END,
                      CASE WHEN trigger_source = ? THEN 0 ELSE 1 END,
                      id ASC
                      LIMIT 1";
            $params[] = $categoryId;
            $params[] = $source;
        } else {
            $sql .= " AND trigger_category_id IS NULL
                      ORDER BY
                      CASE WHEN trigger_source = ? THEN 0 ELSE 1 END,
                      id ASC
                      LIMIT 1";
            $params[] = $source;
        }

        return $this->db->selectOne($sql, $params);
    }

    private function findNextStep(int $workflowId, int $currentStepOrder): ?array
    {
        return $this->db->selectOne(
            "SELECT *
             FROM workflow_steps
             WHERE workflow_id = ?
               AND is_active = 1
               AND step_order > ?
             ORDER BY step_order ASC
             LIMIT 1",
            [$workflowId, $currentStepOrder]
        );
    }

    private function resolveAssigneeId(array $step, ?int $requesterUserId = null): ?int
    {
        $type = (string) ($step['approver_type'] ?? 'user');

        if ($type === 'user') {
            $userId = (int) ($step['approver_user_id'] ?? 0);
            if ($userId > 0 && $this->isActiveCompanyUser($userId)) {
                return $userId;
            }
        } elseif ($type === 'role') {
            $role = trim((string) ($step['approver_role'] ?? ''));
            if ($role !== '') {
                $user = $this->db->selectOne(
                    "SELECT id FROM users
                     WHERE company_id = ?
                       AND role = ?
                       AND is_active = 1
                     ORDER BY id ASC
                     LIMIT 1",
                    [$this->companyId, $role]
                );
                if ($user) {
                    return (int) $user['id'];
                }
            }
        } elseif ($type === 'department_manager') {
            $departmentId = (int) ($step['approver_department_id'] ?? 0);
            if ($departmentId > 0) {
                $manager = $this->db->selectOne(
                    "SELECT d.manager_user_id
                     FROM departments d
                     JOIN users u ON u.id = d.manager_user_id
                     WHERE d.id = ?
                       AND d.company_id = ?
                       AND d.is_active = 1
                       AND u.is_active = 1
                     LIMIT 1",
                    [$departmentId, $this->companyId]
                );
                if (!empty($manager['manager_user_id'])) {
                    return (int) $manager['manager_user_id'];
                }
            }
        } elseif ($type === 'customer_owner') {
            $ownerId = $this->resolveCustomerOwner($requesterUserId);
            if ($ownerId !== null) {
                return $ownerId;
            }
        } elseif ($type === 'customer_owner_supervisor') {
            $supervisorId = $this->resolveCustomerOwnerSupervisor($requesterUserId);
            if ($supervisorId !== null) {
                return $supervisorId;
            }
        }

        // Fallback to first active admin to avoid dead routes.
        $fallback = $this->db->selectOne(
            "SELECT id FROM users
             WHERE company_id = ?
               AND role IN ('super_admin', 'admin')
               AND is_active = 1
             ORDER BY id ASC
             LIMIT 1",
            [$this->companyId]
        );

        return $fallback ? (int) $fallback['id'] : null;
    }

    private function resolveCustomerOwner(?int $requesterUserId): ?int
    {
        if ($requesterUserId === null || $requesterUserId <= 0) {
            return null;
        }

        $owner = $this->db->selectOne(
            "SELECT ca.owner_user_id
             FROM customer_account_owners ca
             JOIN users u ON u.id = ca.owner_user_id
             WHERE ca.company_id = ?
               AND ca.customer_user_id = ?
               AND u.is_active = 1
             LIMIT 1",
            [$this->companyId, $requesterUserId]
        );

        if (empty($owner['owner_user_id'])) {
            return null;
        }

        return (int) $owner['owner_user_id'];
    }

    private function resolveCustomerOwnerSupervisor(?int $requesterUserId): ?int
    {
        $ownerId = $this->resolveCustomerOwner($requesterUserId);
        if ($ownerId === null) {
            return null;
        }

        $mapping = $this->db->selectOne(
            "SELECT ur.supervisor_user_id
             FROM user_reporting ur
             JOIN users u ON u.id = ur.supervisor_user_id
             WHERE ur.company_id = ?
               AND ur.user_id = ?
               AND u.is_active = 1
             LIMIT 1",
            [$this->companyId, $ownerId]
        );

        if (empty($mapping['supervisor_user_id'])) {
            return null;
        }

        return (int) $mapping['supervisor_user_id'];
    }

    private function isActiveCompanyUser(int $userId): bool
    {
        $user = $this->db->selectOne(
            "SELECT id
             FROM users
             WHERE id = ?
               AND company_id = ?
               AND is_active = 1",
            [$userId, $this->companyId]
        );

        return $user !== null;
    }

    private function resolveCategoryAutoAssignee(?int $categoryId): ?int
    {
        if ($categoryId === null || $categoryId <= 0) {
            return null;
        }

        $category = $this->db->selectOne(
            "SELECT c.auto_assign_to
             FROM categories c
             JOIN users u ON u.id = c.auto_assign_to
             WHERE c.id = ?
               AND c.company_id = ?
               AND c.is_active = 1
               AND c.auto_assign_to IS NOT NULL
               AND u.company_id = ?
               AND u.is_active = 1
             LIMIT 1",
            [$categoryId, $this->companyId, $this->companyId]
        );

        if (!$category || empty($category['auto_assign_to'])) {
            return null;
        }

        return (int) $category['auto_assign_to'];
    }

    private function resolveIncomingHandlerAssignee(string $source, ?int $requesterUserId): ?int
    {
        if (!$this->shouldUseIncomingHandlerFallback($source, $requesterUserId)) {
            return null;
        }

        $handlerUserIds = $this->getIncomingHandlerUserIds();
        if (empty($handlerUserIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($handlerUserIds), '?'));
        $params = array_merge([$this->companyId], $handlerUserIds);

        $handler = $this->db->selectOne(
            "SELECT u.id, COUNT(t.id) AS open_count,
                    COALESCE(MAX(t.updated_at), '1970-01-01 00:00:00') AS last_open_ticket_at
             FROM users u
             LEFT JOIN tickets t ON t.company_id = u.company_id
                AND t.assigned_to = u.id
                AND t.status IN ('open', 'pending', 'in_progress')
             WHERE u.company_id = ?
               AND u.is_active = 1
               AND u.role <> 'customer'
               AND u.id IN ($placeholders)
             GROUP BY u.id
             ORDER BY open_count ASC, last_open_ticket_at ASC, u.id ASC
             LIMIT 1",
            $params
        );

        if (!$handler || empty($handler['id'])) {
            return null;
        }

        return (int) $handler['id'];
    }

    private function shouldUseIncomingHandlerFallback(string $source, ?int $requesterUserId): bool
    {
        $source = strtolower(trim($source));
        if (!in_array($source, ['web', 'email', 'telegram'], true)) {
            return false;
        }

        return $this->isCustomerRequester($requesterUserId);
    }

    private function isCustomerRequester(?int $requesterUserId): bool
    {
        if ($requesterUserId === null || $requesterUserId <= 0) {
            return false;
        }

        $user = $this->db->selectOne(
            "SELECT role
             FROM users
             WHERE id = ?
               AND company_id = ?
               AND is_active = 1
             LIMIT 1",
            [$requesterUserId, $this->companyId]
        );

        return ($user['role'] ?? '') === 'customer';
    }

    private function getIncomingHandlerUserIds(): array
    {
        $settings = $this->getCompanySettings();
        $ids = $settings['incoming_handler_user_ids'] ?? [];
        if (!is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id > 0 && !isset($normalized[$id])) {
                $normalized[$id] = true;
            }
        }

        return array_map('intval', array_keys($normalized));
    }

    private function getCompanySettings(): array
    {
        if ($this->companySettings !== null) {
            return $this->companySettings;
        }

        $company = $this->db->selectOne(
            "SELECT settings
             FROM companies
             WHERE id = ?
             LIMIT 1",
            [$this->companyId]
        );

        if (!$company || empty($company['settings'])) {
            $this->companySettings = [];
            return $this->companySettings;
        }

        $decoded = json_decode((string) $company['settings'], true);
        $this->companySettings = is_array($decoded) ? $decoded : [];
        return $this->companySettings;
    }

    private function buildDueAt(int $slaMinutes): string
    {
        $slaMinutes = max(1, $slaMinutes);
        return date('Y-m-d H:i:s', time() + ($slaMinutes * 60));
    }

    private function completeState(int $stateId, int $ticketId, int $workflowId, int $stepOrder, string $note): void
    {
        $this->db->update('ticket_workflow_states', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'due_at' => null,
        ], 'id = ?', [$stateId]);

        $this->db->insert('ticket_workflow_logs', [
            'company_id' => $this->companyId,
            'ticket_id' => $ticketId,
            'workflow_id' => $workflowId,
            'step_order' => $stepOrder,
            'action' => 'completed',
            'note' => $note,
            'meta' => json_encode(['auto_completed' => true]),
        ]);
    }

    private function notifyNextAssignee(int $ticketId, string $ticketNumber, ?int $assigneeId, string $stepName): void
    {
        if (!$assigneeId) {
            return;
        }

        $title = $ticketNumber !== ''
            ? "Workflow escalated - #{$ticketNumber}"
            : 'Workflow escalated';

        $this->db->insert('notifications', [
            'user_id' => $assigneeId,
            'ticket_id' => $ticketId,
            'type' => 'workflow_escalated',
            'title' => $title,
            'message' => "Ticket requires your action at workflow step: {$stepName}",
            'data' => json_encode([
                'source' => 'workflow',
                'step_name' => $stepName,
            ]),
        ]);

        $ticket = $this->db->selectOne(
            "SELECT id, ticket_number, subject, status, priority, requester_name, requester_email
             FROM tickets
             WHERE id = ? AND company_id = ?
             LIMIT 1",
            [$ticketId, $this->companyId]
        );

        if (!$ticket) {
            return;
        }

        $ticket['assigned_to'] = $assigneeId;
        $subject = htmlspecialchars((string) ($ticket['subject'] ?? ''), ENT_QUOTES | ENT_HTML5);
        $requesterName = htmlspecialchars((string) ($ticket['requester_name'] ?? ''), ENT_QUOTES | ENT_HTML5);
        $requesterEmail = htmlspecialchars((string) ($ticket['requester_email'] ?? ''), ENT_QUOTES | ENT_HTML5);
        $status = htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($ticket['status'] ?? 'open'))), ENT_QUOTES | ENT_HTML5);
        $priority = htmlspecialchars(ucfirst((string) ($ticket['priority'] ?? 'medium')), ENT_QUOTES | ENT_HTML5);
        $stepSafe = htmlspecialchars($stepName, ENT_QUOTES | ENT_HTML5);
        $link = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        if ($link !== '') {
            $link .= "/tickets/{$ticketId}";
        }

        $text = "<b>Ticket Assignment</b>\n" .
            "<b>Ticket:</b> #{$ticket['ticket_number']}\n" .
            "<b>Subject:</b> {$subject}\n" .
            "<b>Status:</b> {$status}\n" .
            "<b>Priority:</b> {$priority}\n" .
            "<b>Requester:</b> {$requesterName} ({$requesterEmail})\n" .
            "<b>Step:</b> {$stepSafe}";
        if ($link !== '') {
            $text .= "\n<b>Link:</b> {$link}";
        }

        try {
            $notifier = new TelegramAgentNotifier($this->db, $this->companyId);
            $notifier->notifyAssignedAgent($ticket, $text);
        } catch (\Throwable $e) {
            error_log('[WorkflowRouter] Telegram notify failed: ' . $e->getMessage());
        }
    }
}
