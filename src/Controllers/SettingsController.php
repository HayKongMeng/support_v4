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

        $this->response->withSuccess('Settings updated successfully');
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

        $this->response->withSuccess('Email settings updated successfully');
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
            'bot_token', 'bot_username', 'welcome_message', 'is_active',
            'notify_assigned_agents', 'alert_group_chat_id'
        ]);

        $existing = $this->db->selectOne(
            "SELECT id FROM telegram_configs WHERE company_id = ?",
            [$this->companyId()]
        );

        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['notify_assigned_agents'] = isset($data['notify_assigned_agents']) ? 1 : 0;
        $data['alert_group_chat_id'] = $data['alert_group_chat_id'] ?? null;
        if ($data['alert_group_chat_id'] === '') {
            $data['alert_group_chat_id'] = null;
        }
        $data['webhook_secret'] = bin2hex(random_bytes(16));

        if ($existing) {
            $this->db->update('telegram_configs', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['company_id'] = $this->companyId();
            $this->db->insert('telegram_configs', $data);
        }

        $this->response->withSuccess('Telegram settings updated successfully');
        $this->redirect($this->app->url('settings/telegram'));
    }

    public function users(): void
    {
        $this->requireAdmin();

        $userModel = new User($this->db);
        $userModel->setCompanyId($this->companyId());

        $users = $userModel->all([], 'name ASC');

        $this->view('settings/users', [
            'users' => $users,
        ]);
    }

    public function storeUser(): void
    {
        $this->requireAdmin();

        $data = $this->request->only(['name', 'email', 'password', 'role']);

        // Validate
        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Name is required';
        if (empty($data['email'])) $errors['email'] = 'Email is required';
        if (empty($data['password'])) $errors['password'] = 'Password is required';
        if (!in_array($data['role'], ['admin', 'agent', 'front_office_agent', 'back_office_agent', 'customer'])) {
            $errors['role'] = 'Invalid role';
        }

        // Check email uniqueness
        $userModel = new User($this->db);
        $existing = $userModel->findByEmail($data['email'], $this->companyId());
        if ($existing) {
            $errors['email'] = 'Email already exists';
        }

        if (!empty($errors)) {
            if ($this->request->isAjax()) {
                $this->error('Validation failed', 422, $errors);
                return;
            }
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError('Please fix the errors')->withInput();
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

        if ($this->request->isAjax()) {
            $this->success(['id' => $userId], 'User created successfully');
            return;
        }

        $this->response->withSuccess('User created successfully');
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
            $this->error('User not found', 404);
            return;
        }

        $data = $this->request->only(['name', 'email', 'role', 'is_active']);

        $errors = [];
        if (empty($data['name'])) $errors['name'] = 'Name is required';
        if (empty($data['email'])) $errors['email'] = 'Email is required';
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }
        if (empty($data['role']) || !in_array($data['role'], ['admin', 'agent', 'front_office_agent', 'back_office_agent', 'customer'])) {
            $errors['role'] = 'Invalid role';
        }

        if (!empty($data['email'])) {
            $existing = $this->db->selectOne(
                "SELECT id FROM users WHERE email = ? AND company_id = ? AND id != ?",
                [$data['email'], $this->companyId(), $id]
            );
            if ($existing) {
                $errors['email'] = 'Email already exists';
            }
        }

        $isActive = array_key_exists('is_active', $data) ? 1 : 0;
        $willBeAdmin = in_array($data['role'] ?? $user['role'], ['admin', 'super_admin']);
        $isCurrentlyAdmin = in_array($user['role'], ['admin', 'super_admin']);

        if ($isCurrentlyAdmin && (!$willBeAdmin || !$isActive)) {
            $remainingAdmins = $this->countActiveAdmins((int) $id);
            if ($remainingAdmins < 1) {
                $errors['role'] = 'Cannot remove the last admin';
            }
        }

        if (!empty($errors)) {
            if ($this->request->isAjax() || $this->request->isJson()) {
                $this->error('Validation failed', 422, $errors);
                return;
            }
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError('Please fix the errors')->withInput();
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

        if ($this->request->isAjax() || $this->request->isJson()) {
            $this->success([], 'User updated successfully');
            return;
        }

        $this->response->withSuccess('User updated successfully');
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
            $this->error('User not found', 404);
            return;
        }

        if ((int) $user['id'] === (int) $this->auth->id()) {
            $this->error('You cannot delete your own account', 422);
            return;
        }

        $isAdmin = in_array($user['role'], ['admin', 'super_admin']);
        if ($isAdmin && $this->countActiveAdmins((int) $id) < 1) {
            $this->error('Cannot delete the last admin', 422);
            return;
        }

        $this->db->delete('users', 'id = ? AND company_id = ?', [(int) $id, $this->companyId()]);

        if ($this->request->isAjax() || $this->request->isJson()) {
            $this->success([], 'User deleted successfully');
            return;
        }

        $this->response->withSuccess('User deleted successfully');
        $this->redirect($this->app->url('settings/users'));
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
            $this->response->withError('Title and content are required');
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

        $this->response->withSuccess('Canned response created successfully');
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
            $this->error('Canned response not found', 404);
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

        $this->response->withSuccess('Canned response updated');
        $this->redirect($this->app->url('settings/canned-responses'));
    }

    public function deleteCannedResponse(string $id): void
    {
        $this->requireAdmin();

        $this->db->delete('canned_responses', 'id = ? AND company_id = ?', [$id, $this->companyId()]);

        if ($this->request->isAjax()) {
            $this->success([], 'Canned response deleted');
            return;
        }

        $this->response->withSuccess('Canned response deleted');
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
            $errors['response_time_minutes'] = 'Response time must be at least 1 minute';
        }
        if (empty($data['resolution_time_minutes']) || $data['resolution_time_minutes'] < 1) {
            $errors['resolution_time_minutes'] = 'Resolution time must be at least 1 minute';
        }
        if ((int)$data['response_time_minutes'] > (int)$data['resolution_time_minutes']) {
            $errors['resolution_time_minutes'] = 'Resolution time must be greater than response time';
        }

        if (!empty($errors)) {
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError('Please fix the validation errors');
            $this->redirect($this->app->url('settings/sla'));
            return;
        }

        $this->db->update('sla_policies', [
            'response_time_minutes' => (int)$data['response_time_minutes'],
            'resolution_time_minutes' => (int)$data['resolution_time_minutes'],
            'business_hours_only' => isset($data['business_hours_only']) ? (int)$data['business_hours_only'] : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND company_id = ?', [(int)$id, $this->companyId()]);

        $this->response->withSuccess('SLA policy updated successfully');
        $this->redirect($this->app->url('settings/sla'));
    }
}
