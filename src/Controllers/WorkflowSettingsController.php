<?php

namespace App\Controllers;

use App\Core\Controller;

class WorkflowSettingsController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $companyId = $this->companyId();

        $users = $this->db->select(
            "SELECT id, name, email, role, is_active
             FROM users
             WHERE company_id = ?
             ORDER BY name ASC",
            [$companyId]
        );

        $activeUsers = array_values(array_filter($users, static fn(array $u): bool => (int)($u['is_active'] ?? 0) === 1));
        $customers = array_values(array_filter($activeUsers, static fn(array $u): bool => ($u['role'] ?? '') === 'customer'));
        $staffUsers = array_values(array_filter($activeUsers, static fn(array $u): bool => ($u['role'] ?? '') !== 'customer'));

        $incomingHandlerUserIds = $this->getIncomingHandlerUserIds();
        if (!empty($incomingHandlerUserIds)) {
            $validStaffIds = array_map(static fn(array $u): int => (int) ($u['id'] ?? 0), $staffUsers);
            $incomingHandlerUserIds = array_values(array_intersect($incomingHandlerUserIds, $validStaffIds));
        }

        $incomingHandlerUsers = array_values(array_filter(
            $staffUsers,
            static fn(array $u): bool => in_array((int) ($u['id'] ?? 0), $incomingHandlerUserIds, true)
        ));

        $departments = $this->db->select(
            "SELECT d.id, d.name, d.manager_user_id, u.name AS manager_name
             FROM departments d
             LEFT JOIN users u ON u.id = d.manager_user_id
             WHERE d.company_id = ? AND d.is_active = 1
             ORDER BY d.name ASC",
            [$companyId]
        );

        $categories = $this->db->select(
            "SELECT id, name
             FROM categories
             WHERE company_id = ? AND is_active = 1
             ORDER BY name ASC",
            [$companyId]
        );

        $customerOwners = $this->db->select(
            "SELECT co.id, co.customer_user_id, co.owner_user_id, co.updated_at,
                    cu.name AS customer_name, cu.email AS customer_email,
                    ou.name AS owner_name, ou.role AS owner_role
             FROM customer_account_owners co
             JOIN users cu ON cu.id = co.customer_user_id
             JOIN users ou ON ou.id = co.owner_user_id
             WHERE co.company_id = ?
             ORDER BY co.updated_at DESC, co.id DESC",
            [$companyId]
        );

        $reportingMappings = $this->db->select(
            "SELECT ur.id, ur.user_id, ur.supervisor_user_id, ur.updated_at,
                    u.name AS user_name, u.role AS user_role,
                    s.name AS supervisor_name, s.role AS supervisor_role
             FROM user_reporting ur
             JOIN users u ON u.id = ur.user_id
             JOIN users s ON s.id = ur.supervisor_user_id
             WHERE ur.company_id = ?
             ORDER BY ur.updated_at DESC, ur.id DESC",
            [$companyId]
        );

        $miniAppStaffProfiles = [];
        try {
            $miniAppStaffProfiles = $this->db->select(
                "SELECT p.id, p.telegram_user_id, p.role_type, p.linked_user_id, p.matched_name, p.updated_at,
                        u.name AS linked_user_name, u.role AS linked_user_role, u.email AS linked_user_email
                 FROM telegram_miniapp_profiles p
                 LEFT JOIN users u ON u.id = p.linked_user_id AND u.company_id = p.company_id
                 WHERE p.company_id = ?
                   AND p.role_type = 'staff'
                 ORDER BY p.updated_at DESC, p.id DESC",
                [$companyId]
            );
        } catch (\Throwable $e) {
            // Migration may not be installed yet; keep page usable.
            $miniAppStaffProfiles = [];
        }

        $categoryRoutingRules = [];
        try {
            $categoryRoutingRules = $this->db->select(
                "SELECT r.id, r.category_id, r.route_mode, r.department_id, r.queue_strategy, r.is_active, r.updated_at,
                        c.name AS category_name,
                        d.name AS department_name
                 FROM category_routing_rules r
                 JOIN categories c ON c.id = r.category_id
                 LEFT JOIN departments d ON d.id = r.department_id
                 WHERE r.company_id = ?
                 ORDER BY c.name ASC",
                [$companyId]
            );
        } catch (\Throwable $e) {
            $categoryRoutingRules = [];
        }

        $staffHierarchyRows = [];
        try {
            $staffHierarchyRows = $this->db->select(
                "SELECT u.id, u.name, u.email, u.role,
                        s.id AS supervisor_id,
                        s.name AS supervisor_name,
                        GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR ', ') AS department_names,
                        GROUP_CONCAT(DISTINCT dm.name ORDER BY dm.name SEPARATOR ', ') AS department_manager_names
                 FROM users u
                 LEFT JOIN user_reporting ur
                    ON ur.company_id = u.company_id
                   AND ur.user_id = u.id
                 LEFT JOIN users s
                    ON s.id = ur.supervisor_user_id
                   AND s.company_id = u.company_id
                   AND s.is_active = 1
                 LEFT JOIN department_users du
                    ON du.company_id = u.company_id
                   AND du.user_id = u.id
                 LEFT JOIN departments d
                    ON d.id = du.department_id
                   AND d.company_id = u.company_id
                   AND d.is_active = 1
                 LEFT JOIN users dm
                    ON dm.id = d.manager_user_id
                   AND dm.company_id = u.company_id
                   AND dm.is_active = 1
                 WHERE u.company_id = ?
                   AND u.is_active = 1
                   AND u.role <> 'customer'
                 GROUP BY u.id, u.name, u.email, u.role, s.id, s.name
                 ORDER BY u.name ASC",
                [$companyId]
            );
        } catch (\Throwable $e) {
            $staffHierarchyRows = [];
        }

        $templates = $this->db->select(
            "SELECT wt.*, c.name AS category_name, u.name AS created_by_name
             FROM workflow_templates wt
             LEFT JOIN categories c ON c.id = wt.trigger_category_id
             LEFT JOIN users u ON u.id = wt.created_by
             WHERE wt.company_id = ?
             ORDER BY wt.is_active DESC, wt.id DESC",
            [$companyId]
        );

        $steps = $this->db->select(
            "SELECT ws.*, wt.name AS workflow_name,
                    au.name AS approver_user_name,
                    d.name AS approver_department_name
             FROM workflow_steps ws
             JOIN workflow_templates wt ON wt.id = ws.workflow_id
             LEFT JOIN users au ON au.id = ws.approver_user_id
             LEFT JOIN departments d ON d.id = ws.approver_department_id
             WHERE wt.company_id = ?
             ORDER BY ws.workflow_id ASC, ws.step_order ASC",
            [$companyId]
        );

        $stepsByWorkflow = [];
        foreach ($steps as $step) {
            $workflowId = (int)($step['workflow_id'] ?? 0);
            if ($workflowId <= 0) {
                continue;
            }
            $stepsByWorkflow[$workflowId][] = $step;
        }

        $this->view('settings/workflow', [
            'users' => $users,
            'activeUsers' => $activeUsers,
            'customers' => $customers,
            'staffUsers' => $staffUsers,
            'incomingHandlerUserIds' => $incomingHandlerUserIds,
            'incomingHandlerUsers' => $incomingHandlerUsers,
            'departments' => $departments,
            'categories' => $categories,
            'customerOwners' => $customerOwners,
            'reportingMappings' => $reportingMappings,
            'miniAppStaffProfiles' => $miniAppStaffProfiles,
            'categoryRoutingRules' => $categoryRoutingRules,
            'staffHierarchyRows' => $staffHierarchyRows,
            'templates' => $templates,
            'stepsByWorkflow' => $stepsByWorkflow,
            'allowedCategoryRouteModes' => $this->allowedCategoryRouteModes(),
            'allowedQueueStrategies' => $this->allowedQueueStrategies(),
            'allowedSources' => $this->allowedSources(),
            'allowedApproverTypes' => $this->allowedApproverTypes(),
            'allowedRoles' => $this->allowedRoles(),
        ]);
    }

    public function saveIncomingHandlers(): void
    {
        $this->requireAdmin();

        $input = $this->request->input('incoming_handler_user_ids', []);
        if (!is_array($input)) {
            $input = [$input];
        }

        $ids = [];
        foreach ($input as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0 || isset($ids[$id])) {
                continue;
            }

            $user = $this->findCompanyUser($id);
            if (!$user || (($user['role'] ?? '') === 'customer')) {
                continue;
            }

            $ids[$id] = true;
        }

        $settings = $this->getCompanySettings();
        $settings['incoming_handler_user_ids'] = array_map('intval', array_keys($ids));
        $this->saveCompanySettings($settings);

        $this->withWorkflowSuccess(__('wf_incoming_handlers_updated'));
    }

    public function saveCustomerOwner(): void
    {
        $this->requireAdmin();

        $companyId = $this->companyId();
        $customerUserId = (int)$this->request->input('customer_user_id', 0);
        $ownerUserId = (int)$this->request->input('owner_user_id', 0);

        if ($customerUserId <= 0 || $ownerUserId <= 0) {
            $this->withWorkflowError(__('wf_customer_owner_required'));
            return;
        }

        $customer = $this->findCompanyUser($customerUserId);
        $owner = $this->findCompanyUser($ownerUserId);

        if (!$customer || ($customer['role'] ?? '') !== 'customer') {
            $this->withWorkflowError(__('wf_selected_customer_invalid'));
            return;
        }

        if (!$owner || ($owner['role'] ?? '') === 'customer') {
            $this->withWorkflowError(__('wf_selected_owner_must_staff'));
            return;
        }

        $existing = $this->db->selectOne(
            "SELECT id FROM customer_account_owners WHERE company_id = ? AND customer_user_id = ?",
            [$companyId, $customerUserId]
        );

        if ($existing) {
            $this->db->update(
                'customer_account_owners',
                [
                    'owner_user_id' => $ownerUserId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ?',
                [(int)$existing['id']]
            );
        } else {
            $this->db->insert('customer_account_owners', [
                'company_id' => $companyId,
                'customer_user_id' => $customerUserId,
                'owner_user_id' => $ownerUserId,
            ]);
        }

        $this->withWorkflowSuccess(__('wf_customer_owner_mapping_saved'));
    }

    public function deleteCustomerOwner(string $id): void
    {
        $this->requireAdmin();

        $mapping = $this->db->selectOne(
            "SELECT id FROM customer_account_owners WHERE id = ? AND company_id = ?",
            [(int)$id, $this->companyId()]
        );

        if (!$mapping) {
            $this->withWorkflowError(__('wf_mapping_not_found'));
            return;
        }

        $this->db->delete('customer_account_owners', 'id = ?', [(int)$id]);
        $this->withWorkflowSuccess(__('wf_customer_owner_mapping_deleted'));
    }

    public function saveUserReporting(): void
    {
        $this->requireAdmin();

        $companyId = $this->companyId();
        $userId = (int)$this->request->input('user_id', 0);
        $supervisorUserId = (int)$this->request->input('supervisor_user_id', 0);

        if ($userId <= 0 || $supervisorUserId <= 0) {
            $this->withWorkflowError(__('wf_user_supervisor_required'));
            return;
        }

        if ($userId === $supervisorUserId) {
            $this->withWorkflowError(__('wf_user_supervisor_same_error'));
            return;
        }

        $user = $this->findCompanyUser($userId);
        $supervisor = $this->findCompanyUser($supervisorUserId);

        if (!$user || ($user['role'] ?? '') === 'customer') {
            $this->withWorkflowError(__('wf_selected_user_must_staff'));
            return;
        }

        if (!$supervisor || ($supervisor['role'] ?? '') === 'customer') {
            $this->withWorkflowError(__('wf_selected_supervisor_must_staff'));
            return;
        }

        $existing = $this->db->selectOne(
            "SELECT id FROM user_reporting WHERE company_id = ? AND user_id = ?",
            [$companyId, $userId]
        );

        if ($existing) {
            $this->db->update(
                'user_reporting',
                [
                    'supervisor_user_id' => $supervisorUserId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = ?',
                [(int)$existing['id']]
            );
        } else {
            $this->db->insert('user_reporting', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'supervisor_user_id' => $supervisorUserId,
            ]);
        }

        $this->withWorkflowSuccess(__('wf_user_reporting_mapping_saved'));
    }

    public function deleteUserReporting(string $id): void
    {
        $this->requireAdmin();

        $mapping = $this->db->selectOne(
            "SELECT id FROM user_reporting WHERE id = ? AND company_id = ?",
            [(int)$id, $this->companyId()]
        );

        if (!$mapping) {
            $this->withWorkflowError(__('wf_reporting_mapping_not_found'));
            return;
        }

        $this->db->delete('user_reporting', 'id = ?', [(int)$id]);
        $this->withWorkflowSuccess(__('wf_user_reporting_mapping_deleted'));
    }

    public function saveMiniAppStaffMapping(): void
    {
        $this->requireAdmin();

        $companyId = $this->companyId();
        $profileId = (int)$this->request->input('profile_id', 0);
        $linkedUserIdInput = (int)$this->request->input('linked_user_id', 0);

        if ($profileId <= 0) {
            $this->withWorkflowError(__('wf_miniapp_profile_required'));
            return;
        }

        $profile = $this->db->selectOne(
            "SELECT id, role_type
             FROM telegram_miniapp_profiles
             WHERE id = ? AND company_id = ?",
            [$profileId, $companyId]
        );

        if (!$profile || ($profile['role_type'] ?? '') !== 'staff') {
            $this->withWorkflowError(__('wf_miniapp_staff_profile_invalid'));
            return;
        }

        $linkedUserId = null;
        if ($linkedUserIdInput > 0) {
            $linkedUser = $this->findCompanyUser($linkedUserIdInput);
            if (!$linkedUser || (($linkedUser['role'] ?? '') === 'customer')) {
                $this->withWorkflowError(__('wf_mapped_user_must_active_staff'));
                return;
            }
            $linkedUserId = $linkedUserIdInput;
        }

        $this->db->update(
            'telegram_miniapp_profiles',
            [
                'linked_user_id' => $linkedUserId,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$profileId]
        );

        $message = $linkedUserId
            ? __('wf_miniapp_staff_mapping_saved')
            : __('wf_miniapp_staff_mapping_cleared');
        $this->withWorkflowSuccess($message);
    }

    public function saveCategoryRoutingRule(): void
    {
        $this->requireAdmin();

        $companyId = $this->companyId();
        $categoryId = (int)$this->request->input('category_id', 0);
        $routeMode = trim((string)$this->request->input('route_mode', 'workflow_default'));
        $departmentId = (int)$this->request->input('department_id', 0);
        $queueStrategy = trim((string)$this->request->input('queue_strategy', 'least_open'));
        $isActive = $this->request->input('is_active') ? 1 : 0;

        if ($categoryId <= 0) {
            $this->withWorkflowError(__('wf_category_required'));
            return;
        }

        if (!in_array($routeMode, $this->allowedCategoryRouteModes(), true)) {
            $this->withWorkflowError(__('wf_invalid_routing_mode'));
            return;
        }

        if (!in_array($queueStrategy, $this->allowedQueueStrategies(), true)) {
            $this->withWorkflowError(__('wf_invalid_queue_strategy'));
            return;
        }

        $category = $this->db->selectOne(
            "SELECT id FROM categories WHERE id = ? AND company_id = ?",
            [$categoryId, $companyId]
        );
        if (!$category) {
            $this->withWorkflowError(__('wf_selected_category_invalid'));
            return;
        }

        $normalizedDepartmentId = null;
        if ($routeMode === 'department_queue') {
            if ($departmentId <= 0) {
                $this->withWorkflowError(__('wf_department_required_for_queue'));
                return;
            }
            $department = $this->db->selectOne(
                "SELECT id FROM departments WHERE id = ? AND company_id = ? AND is_active = 1",
                [$departmentId, $companyId]
            );
            if (!$department) {
                $this->withWorkflowError(__('wf_selected_department_invalid'));
                return;
            }
            $normalizedDepartmentId = $departmentId;
        }

        try {
            $existing = $this->db->selectOne(
                "SELECT id FROM category_routing_rules WHERE company_id = ? AND category_id = ?",
                [$companyId, $categoryId]
            );

            $payload = [
                'route_mode' => $routeMode,
                'department_id' => $normalizedDepartmentId,
                'queue_strategy' => $queueStrategy,
                'is_active' => $isActive,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                $this->db->update('category_routing_rules', $payload, 'id = ?', [(int)$existing['id']]);
            } else {
                $this->db->insert('category_routing_rules', [
                    'company_id' => $companyId,
                    'category_id' => $categoryId,
                ] + $payload);
            }
        } catch (\Throwable $e) {
            $this->withWorkflowError(__('wf_category_routing_table_unavailable'));
            return;
        }

        $this->withWorkflowSuccess(__('wf_category_routing_saved'));
    }

    public function deleteCategoryRoutingRule(string $id): void
    {
        $this->requireAdmin();

        try {
            $rule = $this->db->selectOne(
                "SELECT id FROM category_routing_rules WHERE id = ? AND company_id = ?",
                [(int)$id, $this->companyId()]
            );
        } catch (\Throwable $e) {
            $this->withWorkflowError(__('wf_category_routing_table_unavailable'));
            return;
        }

        if (!$rule) {
            $this->withWorkflowError(__('wf_category_routing_not_found'));
            return;
        }

        $this->db->delete('category_routing_rules', 'id = ?', [(int)$id]);
        $this->withWorkflowSuccess(__('wf_category_routing_deleted'));
    }

    public function storeTemplate(): void
    {
        $this->requireAdmin();

        $payload = $this->extractTemplatePayload();
        if ($payload === null) {
            return;
        }

        $payload['company_id'] = $this->companyId();
        $payload['created_by'] = $this->auth->id();
        $this->db->insert('workflow_templates', $payload);

        $this->withWorkflowSuccess(__('wf_template_created'));
    }

    public function updateTemplate(string $id): void
    {
        $this->requireAdmin();

        $template = $this->db->selectOne(
            "SELECT id FROM workflow_templates WHERE id = ? AND company_id = ?",
            [(int)$id, $this->companyId()]
        );

        if (!$template) {
            $this->withWorkflowError(__('wf_template_not_found'));
            return;
        }

        $payload = $this->extractTemplatePayload();
        if ($payload === null) {
            return;
        }

        $this->db->update('workflow_templates', $payload, 'id = ?', [(int)$id]);
        $this->withWorkflowSuccess(__('wf_template_updated'));
    }

    public function deleteTemplate(string $id): void
    {
        $this->requireAdmin();

        $template = $this->db->selectOne(
            "SELECT id FROM workflow_templates WHERE id = ? AND company_id = ?",
            [(int)$id, $this->companyId()]
        );

        if (!$template) {
            $this->withWorkflowError(__('wf_template_not_found'));
            return;
        }

        $activeStates = $this->db->selectOne(
            "SELECT COUNT(*) AS c FROM ticket_workflow_states WHERE workflow_id = ? AND status = 'in_progress'",
            [(int)$id]
        );

        if ((int)($activeStates['c'] ?? 0) > 0) {
            $this->withWorkflowError(__('wf_template_delete_active_blocked'));
            return;
        }

        $this->db->delete('workflow_templates', 'id = ?', [(int)$id]);
        $this->withWorkflowSuccess(__('wf_template_deleted'));
    }

    public function storeStep(): void
    {
        $this->requireAdmin();

        $payload = $this->extractStepPayload();
        if ($payload === null) {
            return;
        }

        try {
            $this->db->insert('workflow_steps', $payload);
            $this->withWorkflowSuccess(__('wf_step_created'));
        } catch (\Throwable $e) {
            $this->withWorkflowError(__('wf_step_create_failed'));
        }
    }

    public function updateStep(string $id): void
    {
        $this->requireAdmin();

        $step = $this->db->selectOne(
            "SELECT ws.id
             FROM workflow_steps ws
             JOIN workflow_templates wt ON wt.id = ws.workflow_id
             WHERE ws.id = ? AND wt.company_id = ?",
            [(int)$id, $this->companyId()]
        );

        if (!$step) {
            $this->withWorkflowError(__('wf_step_not_found'));
            return;
        }

        $payload = $this->extractStepPayload();
        if ($payload === null) {
            return;
        }

        try {
            $this->db->update('workflow_steps', $payload, 'id = ?', [(int)$id]);
            $this->withWorkflowSuccess(__('wf_step_updated'));
        } catch (\Throwable $e) {
            $this->withWorkflowError(__('wf_step_update_failed'));
        }
    }

    public function deleteStep(string $id): void
    {
        $this->requireAdmin();

        $step = $this->db->selectOne(
            "SELECT ws.id
             FROM workflow_steps ws
             JOIN workflow_templates wt ON wt.id = ws.workflow_id
             WHERE ws.id = ? AND wt.company_id = ?",
            [(int)$id, $this->companyId()]
        );

        if (!$step) {
            $this->withWorkflowError(__('wf_step_not_found'));
            return;
        }

        $this->db->delete('workflow_steps', 'id = ?', [(int)$id]);
        $this->withWorkflowSuccess(__('wf_step_deleted'));
    }

    private function extractTemplatePayload(): ?array
    {
        $name = trim((string)$this->request->input('name', ''));
        $triggerSource = trim((string)$this->request->input('trigger_source', 'all'));
        $triggerCategoryId = (int)$this->request->input('trigger_category_id', 0);
        $isActive = $this->request->input('is_active') ? 1 : 0;

        if ($name === '') {
            $this->withWorkflowError(__('wf_template_name_required'));
            return null;
        }

        if (!in_array($triggerSource, $this->allowedSources(), true)) {
            $this->withWorkflowError(__('wf_invalid_trigger_source'));
            return null;
        }

        if ($triggerCategoryId > 0) {
            $category = $this->db->selectOne(
                "SELECT id FROM categories WHERE id = ? AND company_id = ?",
                [$triggerCategoryId, $this->companyId()]
            );
            if (!$category) {
                $this->withWorkflowError(__('wf_invalid_trigger_category'));
                return null;
            }
        } else {
            $triggerCategoryId = null;
        }

        return [
            'name' => $name,
            'trigger_source' => $triggerSource,
            'trigger_category_id' => $triggerCategoryId,
            'is_active' => $isActive,
        ];
    }

    private function extractStepPayload(): ?array
    {
        $workflowId = (int)$this->request->input('workflow_id', 0);
        $stepOrder = (int)$this->request->input('step_order', 0);
        $stepName = trim((string)$this->request->input('step_name', ''));
        $approverType = trim((string)$this->request->input('approver_type', 'user'));
        $approverUserId = (int)$this->request->input('approver_user_id', 0);
        $approverRole = trim((string)$this->request->input('approver_role', ''));
        $approverDepartmentId = (int)$this->request->input('approver_department_id', 0);
        $slaMinutes = (int)$this->request->input('sla_minutes', 120);
        $isActive = $this->request->input('is_active') ? 1 : 0;

        if ($workflowId <= 0) {
            $this->withWorkflowError(__('wf_workflow_template_required'));
            return null;
        }

        $workflow = $this->db->selectOne(
            "SELECT id FROM workflow_templates WHERE id = ? AND company_id = ?",
            [$workflowId, $this->companyId()]
        );
        if (!$workflow) {
            $this->withWorkflowError(__('wf_selected_workflow_template_invalid'));
            return null;
        }

        if ($stepOrder <= 0 || $stepName === '') {
            $this->withWorkflowError(__('wf_step_order_name_required'));
            return null;
        }

        if ($slaMinutes < 1) {
            $this->withWorkflowError(__('wf_sla_minutes_min_1'));
            return null;
        }

        if (!in_array($approverType, $this->allowedApproverTypes(), true)) {
            $this->withWorkflowError(__('wf_invalid_approver_type'));
            return null;
        }

        $normalized = [
            'workflow_id' => $workflowId,
            'step_order' => $stepOrder,
            'step_name' => $stepName,
            'approver_type' => $approverType,
            'approver_user_id' => null,
            'approver_role' => null,
            'approver_department_id' => null,
            'sla_minutes' => $slaMinutes,
            'is_active' => $isActive,
        ];

        if ($approverType === 'user') {
            if ($approverUserId <= 0 || !$this->findCompanyUser($approverUserId)) {
                $this->withWorkflowError(__('wf_approver_user_required'));
                return null;
            }
            $normalized['approver_user_id'] = $approverUserId;
        } elseif ($approverType === 'role') {
            if ($approverRole === '' || !in_array($approverRole, $this->allowedRoles(), true)) {
                $this->withWorkflowError(__('wf_approver_role_required'));
                return null;
            }
            $normalized['approver_role'] = $approverRole;
        } elseif ($approverType === 'department_manager') {
            if ($approverDepartmentId <= 0) {
                $this->withWorkflowError(__('wf_department_required_for_approver'));
                return null;
            }
            $department = $this->db->selectOne(
                "SELECT id FROM departments WHERE id = ? AND company_id = ? AND is_active = 1",
                [$approverDepartmentId, $this->companyId()]
            );
            if (!$department) {
                $this->withWorkflowError(__('wf_selected_department_invalid'));
                return null;
            }
            $normalized['approver_department_id'] = $approverDepartmentId;
        }

        return $normalized;
    }

    private function findCompanyUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return $this->db->selectOne(
            "SELECT id, role
             FROM users
             WHERE id = ? AND company_id = ? AND is_active = 1",
            [$userId, $this->companyId()]
        );
    }

    private function getCompanySettings(): array
    {
        $company = $this->db->selectOne(
            "SELECT settings FROM companies WHERE id = ? LIMIT 1",
            [$this->companyId()]
        );

        if (!$company || empty($company['settings'])) {
            return [];
        }

        $settings = json_decode((string) $company['settings'], true);
        return is_array($settings) ? $settings : [];
    }

    private function saveCompanySettings(array $settings): void
    {
        $this->db->update(
            'companies',
            ['settings' => json_encode($settings)],
            'id = ?',
            [$this->companyId()]
        );
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

    private function withWorkflowSuccess(string $message): void
    {
        $this->response->withSuccess($message);
        $this->redirect($this->app->url('settings/workflow'));
    }

    private function withWorkflowError(string $message): void
    {
        $this->response->withError($message);
        $this->redirect($this->app->url('settings/workflow'));
    }

    private function allowedSources(): array
    {
        return ['telegram', 'web', 'email', 'all'];
    }

    private function allowedApproverTypes(): array
    {
        return ['user', 'role', 'department_manager', 'customer_owner', 'customer_owner_supervisor'];
    }

    private function allowedCategoryRouteModes(): array
    {
        return ['workflow_default', 'staff_supervisor', 'department_queue'];
    }

    private function allowedQueueStrategies(): array
    {
        return ['least_open', 'round_robin'];
    }

    private function allowedRoles(): array
    {
        return ['super_admin', 'admin', 'agent', 'front_office_agent', 'back_office_agent'];
    }
}
