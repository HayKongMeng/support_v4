<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Company;
use App\Models\User;

class SettingsController extends Controller
{
    public function general(): void
    {
        $this->requireAdmin();

        $company = $this->auth->company();
        $settings = json_decode($company['settings'] ?? '{}', true);

        $this->view('settings/general', [
            'company' => $company,
            'settings' => $settings,
        ]);
    }

    public function updateGeneral(): void
    {
        $this->requireAdmin();

        $data = $this->request->only(['name', 'email', 'phone', 'timezone']);
        $settings = $this->request->only([
            'ticket_prefix', 'ai_categorization_enabled',
            'customer_portal_enabled', 'auto_assign_enabled'
        ]);

        // Update company
        $this->db->update('companies', [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'timezone' => $data['timezone'] ?? 'UTC',
        ], 'id = ?', [$this->companyId()]);

        // Update settings
        $companyModel = new Company($this->db);
        $currentSettings = $companyModel->getSettings($this->companyId());
        $newSettings = array_merge($currentSettings, [
            'ticket_prefix' => $settings['ticket_prefix'] ?? 'TKT',
            'ai_categorization_enabled' => isset($settings['ai_categorization_enabled']),
            'customer_portal_enabled' => isset($settings['customer_portal_enabled']),
            'auto_assign_enabled' => isset($settings['auto_assign_enabled']),
        ]);
        $companyModel->updateSettings($this->companyId(), $newSettings);

        $this->response->withSuccess(__('settings_updated_success'));
        $this->redirect($this->app->url('settings/general'));
    }

    public function email(): void
    {
        $this->requireAdmin();

        $emailConfig = $this->db->selectOne(
            "SELECT * FROM email_configs WHERE company_id = ?",
            [$this->companyId()]
        );

        $this->view('settings/email', [
            'emailConfig' => $emailConfig,
        ]);
    }

    public function updateEmail(): void
    {
        $this->requireAdmin();

        $data = $this->request->only([
            'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password',
            'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
            'from_email', 'from_name', 'is_active'
        ]);

        $existing = $this->db->selectOne(
            "SELECT id FROM email_configs WHERE company_id = ?",
            [$this->companyId()]
        );

        $data['is_active'] = isset($data['is_active']) ? 1 : 0;

        if ($existing) {
            // Don't update password if empty
            if (empty($data['imap_password'])) {
                unset($data['imap_password']);
            }
            if (empty($data['smtp_password'])) {
                unset($data['smtp_password']);
            }

            $this->db->update('email_configs', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['company_id'] = $this->companyId();
            $this->db->insert('email_configs', $data);
        }

        $this->response->withSuccess(__('email_settings_updated_success'));
        $this->redirect($this->app->url('settings/email'));
    }

    public function telegram(): void
    {
        $this->requireAdmin();

        $telegramConfig = $this->db->selectOne(
            "SELECT * FROM telegram_configs WHERE company_id = ?",
            [$this->companyId()]
        );

        $webhookUrl = $this->app->config('app.url') . '/public/api/telegram/webhook/' . $this->companyId();

        $this->view('settings/telegram', [
            'telegramConfig' => $telegramConfig,
            'webhookUrl' => $webhookUrl,
        ]);
    }

    public function updateTelegram(): void
    {
        $this->requireAdmin();

        $data = $this->request->only([
            'bot_token', 'bot_username', 'welcome_message', 'is_active'
        ]);

        $existing = $this->db->selectOne(
            "SELECT id FROM telegram_configs WHERE company_id = ?",
            [$this->companyId()]
        );

        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['webhook_secret'] = bin2hex(random_bytes(16));

        if ($existing) {
            $this->db->update('telegram_configs', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['company_id'] = $this->companyId();
            $this->db->insert('telegram_configs', $data);
        }

        $this->response->withSuccess(__('telegram_settings_updated_success'));
        $this->redirect($this->app->url('settings/telegram'));
    }

    public function users(): void
    {
        $this->requireAdmin();

        $allUsers = $this->getUsersWithDepartment();
        $departments = $this->getActiveDepartments();
        $userFilters = $this->buildUsersFilters($departments);
        $users = $this->filterUsers($allUsers, $userFilters);
        $staffUsers = array_values(array_filter(
            $allUsers,
            static fn(array $u): bool => ($u['role'] ?? '') !== 'customer' && (int)($u['is_active'] ?? 0) === 1
        ));
        $telegramConfig = $this->db->selectOne(
            "SELECT bot_username, bot_token, webhook_secret, is_active
             FROM telegram_configs
             WHERE company_id = ?
             LIMIT 1",
            [$this->companyId()]
        );
        $telegramBotUsername = ltrim(trim((string) ($telegramConfig['bot_username'] ?? '')), '@');
        $telegramLinkEnabled = false;
        $telegramStartLinks = [];

        if (!empty($telegramConfig['is_active']) && $telegramBotUsername !== '' && !empty($telegramConfig['bot_token'])) {
            $secret = (string) ($telegramConfig['webhook_secret'] ?? '');
            if ($secret === '') {
                $secret = (string) ($telegramConfig['bot_token'] ?? '');
            }

            if ($secret !== '') {
                $telegramLinkEnabled = true;
                foreach ($staffUsers as $staffUser) {
                    $staffUserId = (int) ($staffUser['id'] ?? 0);
                    if ($staffUserId <= 0) {
                        continue;
                    }
                    $telegramStartLinks[$staffUserId] = $this->buildTelegramStartLink(
                        $telegramBotUsername,
                        $staffUserId,
                        $secret
                    );
                }
            }
        }

        $this->view('settings/users', [
            'users' => $users,
            'departments' => $departments,
            'staffUsers' => $staffUsers,
            'userFilters' => $userFilters,
            'activeTab' => $userFilters['tab'],
            'totalUsersCount' => count($allUsers),
            'filteredUsersCount' => count($users),
            'departmentFeatureEnabled' => $this->hasDepartmentTables(),
            'telegramBotUsername' => $telegramBotUsername,
            'telegramLinkEnabled' => $telegramLinkEnabled,
            'telegramStartLinks' => $telegramStartLinks,
        ]);
    }

    public function storeUser(): void
    {
        $this->requireAdmin();

        $data = $this->request->only(['name', 'email', 'password', 'role', 'department_id']);

        // Validate
        $errors = [];
        if (empty($data['name'])) $errors['name'] = __('name_required');
        if (empty($data['email'])) $errors['email'] = __('email_required');
        if (empty($data['password'])) $errors['password'] = __('password_required');
        if (!in_array($data['role'], ['admin', 'agent', 'front_office_agent', 'back_office_agent', 'customer'])) {
            $errors['role'] = __('invalid_role');
        }
        $departmentId = (int) ($data['department_id'] ?? 0);
        if ($departmentId > 0 && !$this->isValidDepartment($departmentId)) {
            $errors['department_id'] = __('users_invalid_department_selected');
        }

        // Check email uniqueness
        $userModel = new User($this->db);
        $existing = $userModel->findByEmail($data['email'], $this->companyId());
        if ($existing) {
            $errors['email'] = __('email_already_exists');
        }

        if (!empty($errors)) {
            if ($this->request->isAjax()) {
                $this->error(__('validation_failed'), 422, $errors);
                return;
            }
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError(__('please_fix_errors'))->withInput();
            $this->redirect($this->app->url('settings/users'));
            return;
        }

        $userId = $this->db->insert('users', [
            'company_id' => $this->companyId(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $this->auth->hashPassword($data['password']),
            'role' => $data['role'],
            'is_active' => 1,
        ]);

        $this->syncUserDepartment((int) $userId, $departmentId > 0 ? $departmentId : null);

        if ($this->request->isAjax()) {
            $this->success(['id' => $userId], __('user_created_success'));
            return;
        }

        $this->response->withSuccess(__('user_created_success'));
        $this->redirect($this->app->url('settings/users'));
    }

    public function updateUser(string $id): void
    {
        $this->requireAdmin();

        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE id = ? AND company_id = ?",
            [$id, $this->companyId()]
        );

        if (!$user) {
            $this->error(__('user_not_found'), 404);
            return;
        }

        $data = $this->request->only(['name', 'email', 'role', 'is_active', 'department_id']);

        $errors = [];
        if (empty($data['name'])) $errors['name'] = __('name_required');
        if (empty($data['email'])) $errors['email'] = __('email_required');
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('invalid_email_address');
        }
        if (empty($data['role']) || !in_array($data['role'], ['admin', 'agent', 'front_office_agent', 'back_office_agent', 'customer'])) {
            $errors['role'] = __('invalid_role');
        }
        $departmentId = (int) ($data['department_id'] ?? 0);
        if ($departmentId > 0 && !$this->isValidDepartment($departmentId)) {
            $errors['department_id'] = __('users_invalid_department_selected');
        }

        if (!empty($data['email'])) {
            $existing = $this->db->selectOne(
                "SELECT id FROM users WHERE email = ? AND company_id = ? AND id != ?",
                [$data['email'], $this->companyId(), $id]
            );
            if ($existing) {
                $errors['email'] = __('email_already_exists');
            }
        }

        $isActive = array_key_exists('is_active', $data) ? 1 : 0;
        $willBeAdmin = in_array($data['role'] ?? $user['role'], ['admin', 'super_admin']);
        $isCurrentlyAdmin = in_array($user['role'], ['admin', 'super_admin']);

        if ($isCurrentlyAdmin && (!$willBeAdmin || !$isActive)) {
            $remainingAdmins = $this->countActiveAdmins((int) $id);
            if ($remainingAdmins < 1) {
                $errors['role'] = __('cannot_remove_last_admin');
            }
        }

        if (!empty($errors)) {
            if ($this->request->isAjax() || $this->request->isJson()) {
                $this->error(__('validation_failed'), 422, $errors);
                return;
            }
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError(__('please_fix_errors'))->withInput();
            $this->redirect($this->app->url('settings/users'));
            return;
        }

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $isActive,
        ];

        // Handle password change
        $password = $this->request->input('password');
        if (!empty($password)) {
            $updates['password_hash'] = $this->auth->hashPassword($password);
        }

        $this->db->update('users', $updates, 'id = ?', [$id]);
        $this->syncUserDepartment((int) $id, $departmentId > 0 ? $departmentId : null);

        if ($this->request->isAjax() || $this->request->isJson()) {
            $this->success([], __('user_updated_success'));
            return;
        }

        $this->response->withSuccess(__('user_updated_success'));
        $this->redirect($this->app->url('settings/users'));
    }

    public function deleteUser(string $id): void
    {
        $this->requireAdmin();

        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE id = ? AND company_id = ?",
            [$id, $this->companyId()]
        );

        if (!$user) {
            $this->error(__('user_not_found'), 404);
            return;
        }

        if ((int) $user['id'] === (int) $this->auth->id()) {
            $this->error(__('cannot_delete_own_account'), 422);
            return;
        }

        $isAdmin = in_array($user['role'], ['admin', 'super_admin']);
        if ($isAdmin && $this->countActiveAdmins((int) $id) < 1) {
            $this->error(__('cannot_delete_last_admin'), 422);
            return;
        }

        $this->db->delete('users', 'id = ? AND company_id = ?', [(int) $id, $this->companyId()]);

        if ($this->request->isAjax() || $this->request->isJson()) {
            $this->success([], __('user_deleted_success'));
            return;
        }

        $this->response->withSuccess(__('user_deleted_success'));
        $this->redirect($this->app->url('settings/users'));
    }

    public function storeDepartment(): void
    {
        $this->requireAdmin();
        $redirectUrl = $this->app->url('settings/users?tab=departments');

        if (!$this->hasDepartmentTables()) {
            $this->response->withError(__('users_department_feature_unavailable'));
            $this->redirect($redirectUrl);
            return;
        }

        $name = trim((string)$this->request->input('department_name', ''));
        $managerUserId = (int)$this->request->input('manager_user_id', 0);

        if ($name === '') {
            $this->response->withError(__('users_department_name_required'));
            $this->redirect($redirectUrl);
            return;
        }

        if ($managerUserId > 0 && !$this->isValidDepartmentManager($managerUserId)) {
            $this->response->withError(__('users_invalid_department_manager_selected'));
            $this->redirect($redirectUrl);
            return;
        }

        try {
            $this->db->insert('departments', [
                'company_id' => $this->companyId(),
                'name' => $name,
                'manager_user_id' => $managerUserId > 0 ? $managerUserId : null,
                'is_active' => 1,
            ]);
            $this->response->withSuccess(__('users_department_created_successfully'));
        } catch (\Throwable $e) {
            $this->response->withError(__('users_unable_to_create_department'));
        }

        $this->redirect($redirectUrl);
    }

    public function deleteDepartment(string $id): void
    {
        $this->requireAdmin();
        $redirectUrl = $this->app->url('settings/users?tab=departments');

        if (!$this->hasDepartmentTables()) {
            $this->response->withError(__('users_department_feature_unavailable'));
            $this->redirect($redirectUrl);
            return;
        }

        $department = $this->db->selectOne(
            "SELECT id
             FROM departments
             WHERE id = ? AND company_id = ? AND is_active = 1",
            [(int)$id, $this->companyId()]
        );

        if (!$department) {
            $this->response->withError(__('users_department_not_found'));
            $this->redirect($redirectUrl);
            return;
        }

        $this->db->update('departments', [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND company_id = ?', [(int)$id, $this->companyId()]);

        $this->response->withSuccess(__('users_department_removed_successfully'));
        $this->redirect($redirectUrl);
    }

    private function countActiveAdmins(int $excludeId = 0): int
    {
        $sql = "SELECT COUNT(*) as count FROM users WHERE company_id = ? AND role IN ('admin', 'super_admin') AND is_active = 1";
        $params = [$this->companyId()];

        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->selectOne($sql, $params);
        return (int) ($result['count'] ?? 0);
    }

    private function buildUsersFilters(array $departments): array
    {
        $tab = trim((string) $this->request->query('tab', 'users'));
        if (!in_array($tab, ['users', 'departments'], true)) {
            $tab = 'users';
        }

        $search = trim((string) $this->request->query('search', ''));
        $role = trim((string) $this->request->query('role', ''));
        $status = trim((string) $this->request->query('status', ''));
        $departmentId = (int) $this->request->query('department_id', 0);

        $allowedRoles = ['super_admin', 'admin', 'agent', 'front_office_agent', 'back_office_agent', 'customer'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = '';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }

        $allowedDepartmentIds = array_map(static fn(array $d): int => (int) ($d['id'] ?? 0), $departments);
        if ($departmentId > 0 && !in_array($departmentId, $allowedDepartmentIds, true)) {
            $departmentId = 0;
        }

        return [
            'tab' => $tab,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'department_id' => $departmentId,
        ];
    }

    private function filterUsers(array $users, array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $searchNeedle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
        $role = (string) ($filters['role'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $departmentId = (int) ($filters['department_id'] ?? 0);

        return array_values(array_filter($users, static function (array $user) use ($searchNeedle, $role, $status, $departmentId): bool {
            if ($searchNeedle !== '') {
                $haystack = implode(' ', [
                    (string) ($user['name'] ?? ''),
                    (string) ($user['email'] ?? ''),
                    (string) ($user['role'] ?? ''),
                    (string) ($user['department_name'] ?? ''),
                ]);
                $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
                if (strpos($haystack, $searchNeedle) === false) {
                    return false;
                }
            }

            if ($role !== '' && (string) ($user['role'] ?? '') !== $role) {
                return false;
            }

            if ($status === 'active' && (int) ($user['is_active'] ?? 0) !== 1) {
                return false;
            }

            if ($status === 'inactive' && (int) ($user['is_active'] ?? 0) === 1) {
                return false;
            }

            if ($departmentId > 0 && (int) ($user['department_id'] ?? 0) !== $departmentId) {
                return false;
            }

            return true;
        }));
    }

    private function buildTelegramStartLink(string $botUsername, int $userId, string $secret): string
    {
        $botUsername = ltrim(trim($botUsername), '@');
        $expiresAt = time() + (14 * 24 * 60 * 60);
        $payload = $userId . ':' . $expiresAt;
        $rawSignature = hash_hmac('sha256', $payload, $secret, true);
        $signature = rtrim(strtr(base64_encode(substr($rawSignature, 0, 16)), '+/', '-_'), '=');
        $startParam = 'bind_' . $userId . '_' . $expiresAt . '_' . $signature;

        return 'https://t.me/' . rawurlencode($botUsername) . '?start=' . rawurlencode($startParam);
    }

    private function hasDepartmentTables(): bool
    {
        static $hasTables = null;
        if ($hasTables !== null) {
            return $hasTables;
        }

        try {
            $this->db->selectOne("SELECT 1 FROM departments LIMIT 1");
            $this->db->selectOne("SELECT 1 FROM department_users LIMIT 1");
            $hasTables = true;
        } catch (\Throwable $e) {
            $hasTables = false;
        }

        return $hasTables;
    }

    private function getActiveDepartments(): array
    {
        if (!$this->hasDepartmentTables()) {
            return [];
        }

        return $this->db->select(
            "SELECT d.id, d.name, d.manager_user_id,
                    u.name AS manager_name,
                    (
                        SELECT COUNT(*)
                        FROM department_users du
                        WHERE du.company_id = d.company_id
                          AND du.department_id = d.id
                    ) AS member_count
             FROM departments d
             LEFT JOIN users u ON u.id = d.manager_user_id
             WHERE d.company_id = ? AND d.is_active = 1
             ORDER BY d.name ASC",
            [$this->companyId()]
        );
    }

    private function getUsersWithDepartment(): array
    {
        if (!$this->hasDepartmentTables()) {
            $userModel = new User($this->db);
            $userModel->setCompanyId($this->companyId());
            return $userModel->all([], 'name ASC');
        }

        return $this->db->select(
            "SELECT u.*,
                    (
                        SELECT du.department_id
                        FROM department_users du
                        JOIN departments d ON d.id = du.department_id
                            AND d.company_id = du.company_id
                            AND d.is_active = 1
                        WHERE du.company_id = u.company_id
                          AND du.user_id = u.id
                        ORDER BY du.is_primary DESC, du.id ASC
                        LIMIT 1
                    ) AS department_id,
                    (
                        SELECT d.name
                        FROM department_users du
                        JOIN departments d ON d.id = du.department_id
                            AND d.company_id = du.company_id
                            AND d.is_active = 1
                        WHERE du.company_id = u.company_id
                          AND du.user_id = u.id
                        ORDER BY du.is_primary DESC, du.id ASC
                        LIMIT 1
                    ) AS department_name
             FROM users u
             WHERE u.company_id = ?
             ORDER BY u.name ASC",
            [$this->companyId()]
        );
    }

    private function isValidDepartment(int $departmentId): bool
    {
        if ($departmentId <= 0 || !$this->hasDepartmentTables()) {
            return false;
        }

        $department = $this->db->selectOne(
            "SELECT id FROM departments WHERE id = ? AND company_id = ? AND is_active = 1",
            [$departmentId, $this->companyId()]
        );

        return $department !== null;
    }

    private function isValidDepartmentManager(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = $this->db->selectOne(
            "SELECT id
             FROM users
             WHERE id = ?
               AND company_id = ?
               AND is_active = 1
               AND role IN ('super_admin', 'admin', 'agent', 'front_office_agent', 'back_office_agent')",
            [$userId, $this->companyId()]
        );

        return $user !== null;
    }

    private function syncUserDepartment(int $userId, ?int $departmentId): void
    {
        if (!$this->hasDepartmentTables()) {
            return;
        }

        $this->db->delete(
            'department_users',
            'company_id = ? AND user_id = ?',
            [$this->companyId(), $userId]
        );

        if ($departmentId === null || $departmentId <= 0) {
            return;
        }

        if (!$this->isValidDepartment($departmentId)) {
            return;
        }

        $this->db->insert('department_users', [
            'company_id' => $this->companyId(),
            'department_id' => $departmentId,
            'user_id' => $userId,
            'is_primary' => 1,
        ]);
    }

    public function cannedResponses(): void
    {
        $this->requireAdmin();

        $responses = $this->db->select(
            "SELECT cr.*, c.name as category_name
             FROM canned_responses cr
             LEFT JOIN categories c ON cr.category_id = c.id
             WHERE cr.company_id = ?
             ORDER BY cr.title ASC",
            [$this->companyId()]
        );

        $categories = $this->db->select(
            "SELECT * FROM categories WHERE company_id = ? AND is_active = 1",
            [$this->companyId()]
        );

        $this->view('settings/canned_responses', [
            'responses' => $responses,
            'categories' => $categories,
        ]);
    }

    public function storeCannedResponse(): void
    {
        $this->requireAdmin();

        $data = $this->request->only(['title', 'content', 'shortcut', 'category_id']);

        if (empty($data['title']) || empty($data['content'])) {
            $this->response->withError(__('title_and_content_required'));
            $this->redirect($this->app->url('settings/canned-responses'));
            return;
        }

        $this->db->insert('canned_responses', [
            'company_id' => $this->companyId(),
            'title' => $data['title'],
            'content' => $data['content'],
            'shortcut' => $data['shortcut'] ?: null,
            'category_id' => $data['category_id'] ?: null,
            'created_by' => $this->auth->id(),
            'is_active' => 1,
        ]);

        $this->response->withSuccess(__('canned_response_created_success'));
        $this->redirect($this->app->url('settings/canned-responses'));
    }

    public function updateCannedResponse(string $id): void
    {
        $this->requireAdmin();

        $response = $this->db->selectOne(
            "SELECT * FROM canned_responses WHERE id = ? AND company_id = ?",
            [$id, $this->companyId()]
        );

        if (!$response) {
            $this->error(__('canned_response_not_found'), 404);
            return;
        }

        $data = $this->request->only(['title', 'content', 'shortcut', 'category_id', 'is_active']);

        $this->db->update('canned_responses', [
            'title' => $data['title'],
            'content' => $data['content'],
            'shortcut' => $data['shortcut'] ?: null,
            'category_id' => $data['category_id'] ?: null,
            'is_active' => isset($data['is_active']) ? 1 : 0,
        ], 'id = ?', [$id]);

        $this->response->withSuccess(__('canned_response_updated'));
        $this->redirect($this->app->url('settings/canned-responses'));
    }

    public function deleteCannedResponse(string $id): void
    {
        $this->requireAdmin();

        $this->db->delete('canned_responses', 'id = ? AND company_id = ?', [$id, $this->companyId()]);

        if ($this->request->isAjax()) {
            $this->success([], __('canned_response_deleted'));
            return;
        }

        $this->response->withSuccess(__('canned_response_deleted'));
        $this->redirect($this->app->url('settings/canned-responses'));
    }

    public function sla(): void
    {
        $this->requireAdmin();

        $slaModel = new \App\Models\SlaPolicy($this->db);
        $slaModel->setCompanyId($this->companyId());

        $slaPolicies = $slaModel->getActiveByCompany($this->companyId());

        $this->view('settings/sla', [
            'slaPolicies' => $slaPolicies,
        ]);
    }

    public function updateSla(string $id): void
    {
        $this->requireAdmin();

        $data = $this->request->only([
            'response_time_minutes',
            'resolution_time_minutes',
            'business_hours_only'
        ]);

        $errors = [];
        if (empty($data['response_time_minutes']) || $data['response_time_minutes'] < 1) {
            $errors['response_time_minutes'] = __('response_time_min_1');
        }
        if (empty($data['resolution_time_minutes']) || $data['resolution_time_minutes'] < 1) {
            $errors['resolution_time_minutes'] = __('resolution_time_min_1');
        }
        if ((int)$data['response_time_minutes'] > (int)$data['resolution_time_minutes']) {
            $errors['resolution_time_minutes'] = __('resolution_time_gt_response_time');
        }

        if (!empty($errors)) {
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError(__('please_fix_validation_errors'));
            $this->redirect($this->app->url('settings/sla'));
            return;
        }

        $this->db->update('sla_policies', [
            'response_time_minutes' => (int)$data['response_time_minutes'],
            'resolution_time_minutes' => (int)$data['resolution_time_minutes'],
            'business_hours_only' => isset($data['business_hours_only']) ? (int)$data['business_hours_only'] : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND company_id = ?', [(int)$id, $this->companyId()]);

        $this->response->withSuccess(__('sla_policy_updated_success'));
        $this->redirect($this->app->url('settings/sla'));
    }
}
