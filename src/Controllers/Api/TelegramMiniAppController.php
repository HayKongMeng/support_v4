<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\AI\TicketClassifier;
use App\Helpers\FileUploader;
use App\Services\Telegram\TelegramAgentNotifier;
use App\Services\Workflow\WorkflowRouter;

class TelegramMiniAppController extends Controller
{
    private int $companyId = 1; // Default company ID

    private function resolveLang(?string $lang): string
    {
        return strtolower(trim((string) $lang)) === 'en' ? 'en' : 'km';
    }

    private function msg(string $lang, string $key): string
    {
        static $messages = [
            'km' => [
                'invalid_telegram_data' => 'ទិន្នន័យ Telegram មិនត្រឹមត្រូវ',
                'link_before_use' => 'សូមភ្ជាប់គណនីរបស់អ្នកដំបូង ដោយប្រើ /link',
                'subject_min' => 'ប្រធានបទត្រូវមានយ៉ាងហោចណាស់ 5 តួអក្សរ',
                'description_min' => 'ពិពណ៌នាត្រូវមានយ៉ាងហោចណាស់ 10 តួអក្សរ',
                'invalid_email' => 'សូមបញ្ចូលអ៊ីមែលត្រឹមត្រូវ',
                'invalid_telegram_user' => 'អ្នកប្រើ Telegram មិនត្រឹមត្រូវ',
                'email_already_linked' => 'អ៊ីមែលនេះត្រូវបានភ្ជាប់ជាមួយ Telegram ផ្សេងហើយ',
                'link_success' => 'គណនីរបស់អ្នកបានតភ្ជាប់ដោយជោគជ័យ!',
                'user_not_found' => 'រកមិនឃើញអ្នកប្រើ',
                'ticket_not_found' => 'រកមិនឃើញសំណើ',
                'message_required' => 'សារត្រូវបានទាមទារ',
                'invalid_ticket_or_message' => 'លេខសំណើ ឬសារ មិនត្រឹមត្រូវ',
                'message_not_found' => 'រកមិនឃើញសារ',
                'no_file_provided' => 'មិនមានឯកសារផ្ញើមក',
                'attachment_added' => 'បានភ្ជាប់ឯកសារ។',
            ],
            'en' => [
                'invalid_telegram_data' => 'Invalid Telegram data',
                'link_before_use' => 'Please link your account first using /link',
                'subject_min' => 'Subject must be at least 5 characters',
                'description_min' => 'Description must be at least 10 characters',
                'category_required' => 'Please select a category',
                'invalid_category' => 'Invalid category selected',
                'invalid_email' => 'Please enter a valid email',
                'invalid_telegram_user' => 'Invalid Telegram user',
                'email_already_linked' => 'This email is already linked to another Telegram account',
                'link_success' => 'Your account has been linked successfully!',
                'onboarding_required' => 'Please select account type first (Customer or Staff).',
                'invalid_role_type' => 'Invalid account type selected.',
                'profile_saved' => 'Account type saved.',
                'profile_locked' => 'Account type already selected.',
                'staff_match_failed' => 'Staff profile is pending admin mapping.',
                'staff_hierarchy_mapped' => 'Staff profile saved and mapped to hierarchy.',
                'staff_mapping_pending_admin' => 'Staff profile saved. Admin can map your staff account in Workflow settings.',
                'user_not_found' => 'User not found',
                'ticket_not_found' => 'Ticket not found',
                'message_required' => 'Message is required',
                'invalid_ticket_or_message' => 'Invalid ticket or message',
                'message_not_found' => 'Message not found',
                'no_file_provided' => 'No file provided',
                'attachment_added' => 'Attachment added.',
            ],
        ];

        return $messages[$lang][$key] ?? $messages['en'][$key] ?? $key;
    }

    private function miniAppLog(string $message): void
    {
        $debugFile = dirname(__DIR__, 3) . '/storage/logs/miniapp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($debugFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }

    private function normalizeInitData(string $initData): string
    {
        $normalized = trim($initData);
        if ($normalized === '') {
            return '';
        }

        if (strpos($normalized, '&') === false && strpos($normalized, '%26') !== false) {
            $decoded = urldecode($normalized);
            if ($decoded !== '' && strpos($decoded, '=') !== false) {
                $normalized = $decoded;
            }
        }

        return $normalized;
    }

    private function resolveBotToken(): ?string
    {
        try {
            $config = $this->db->selectOne(
                "SELECT bot_token FROM telegram_configs WHERE company_id = ? AND is_active = 1",
                [$this->companyId]
            );

            if (!empty($config['bot_token'])) {
                $botToken = trim((string)$config['bot_token']);
                if ($botToken !== '') {
                    $this->miniAppLog('Bot token loaded from telegram_configs');
                    return $botToken;
                }
            }
        } catch (\Throwable $e) {
            $this->miniAppLog('telegram_configs query failed: ' . $e->getMessage());
        }

        $fallback = trim((string)$this->app->config('services.telegram.bot_token', ''));
        if ($fallback !== '') {
            $this->miniAppLog('Using fallback TELEGRAM_BOT_TOKEN from environment');
            return $fallback;
        }

        $this->miniAppLog('Bot token is missing in both telegram_configs and TELEGRAM_BOT_TOKEN');
        return null;
    }

    /**
     * Validate Telegram WebApp initData
     */
    private function validateInitData(string $initData): ?array
    {
        $normalizedInitData = $this->normalizeInitData($initData);
        $this->miniAppLog('validateInitData called, initData length: ' . strlen($normalizedInitData));

        if ($normalizedInitData === '') {
            $this->miniAppLog('Empty initData');
            return null;
        }

        $botToken = $this->resolveBotToken();
        if ($botToken === null) {
            return null;
        }

        parse_str($normalizedInitData, $data);
        $this->miniAppLog('initData parsed, keys: ' . json_encode(array_keys($data)));

        if (!isset($data['hash'])) {
            $this->miniAppLog('No hash in initData after parse');
            return null;
        }

        $hash = (string)$data['hash'];
        unset($data['hash']);

        ksort($data);
        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $this->miniAppLog(
            'Hash check - received: ' . substr($hash, 0, 10) . '..., calculated: ' . substr($calculatedHash, 0, 10) . '...'
        );

        if (!hash_equals($calculatedHash, $hash)) {
            $this->miniAppLog('Hash validation FAILED');
            return null;
        }

        $this->miniAppLog('Hash validation PASSED');

        if (!isset($data['user'])) {
            $this->miniAppLog('No user data in initData');
            return null;
        }

        $telegramUser = json_decode((string)$data['user'], true);
        if (!is_array($telegramUser) || empty($telegramUser['id'])) {
            $this->miniAppLog('User data decode failed or missing id');
            return null;
        }

        $this->miniAppLog('User data decoded, user ID: ' . $telegramUser['id']);
        return $telegramUser;
    }

    private function telegramDisplayName(array $telegramUser): string
    {
        $displayName = trim((string) (($telegramUser['first_name'] ?? '') . ' ' . ($telegramUser['last_name'] ?? '')));
        if ($displayName === '' && !empty($telegramUser['username'])) {
            $displayName = trim((string) $telegramUser['username']);
        }
        return $displayName !== '' ? $displayName : 'Telegram User';
    }

    private function findLinkedUserByTelegramId(int $telegramId): ?array
    {
        if ($telegramId <= 0) {
            return null;
        }

        return $this->db->selectOne(
            "SELECT *
             FROM users
             WHERE telegram_chat_id = ?
               AND company_id = ?
               AND is_active = 1
             LIMIT 1",
            [$telegramId, $this->companyId]
        );
    }

    private function findActiveCompanyUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return $this->db->selectOne(
            "SELECT *
             FROM users
             WHERE id = ?
               AND company_id = ?
               AND is_active = 1
             LIMIT 1",
            [$userId, $this->companyId]
        );
    }

    private function getMiniAppProfileByTelegramId(int $telegramId): ?array
    {
        if ($telegramId <= 0) {
            return null;
        }

        return $this->db->selectOne(
            "SELECT *
             FROM telegram_miniapp_profiles
             WHERE company_id = ?
               AND telegram_user_id = ?
             LIMIT 1",
            [$this->companyId, $telegramId]
        );
    }

    private function saveMiniAppProfileRecord(int $telegramId, string $roleType, ?int $linkedUserId, ?string $matchedName): ?array
    {
        $existing = $this->getMiniAppProfileByTelegramId($telegramId);
        $payload = [
            'role_type' => $roleType,
            'linked_user_id' => $linkedUserId,
            'matched_name' => $matchedName !== null ? trim($matchedName) : null,
        ];

        if ($existing) {
            $this->db->update(
                'telegram_miniapp_profiles',
                $payload,
                'id = ?',
                [(int) $existing['id']]
            );
        } else {
            $this->db->insert('telegram_miniapp_profiles', [
                'company_id' => $this->companyId,
                'telegram_user_id' => $telegramId,
            ] + $payload);
        }

        return $this->getMiniAppProfileByTelegramId($telegramId);
    }

    private function linkTelegramIdentity(array $user, int $telegramId, ?string $username = null): ?array
    {
        if (empty($user['id']) || $telegramId <= 0) {
            return null;
        }

        if (!empty($user['telegram_chat_id']) && (string) $user['telegram_chat_id'] !== (string) $telegramId) {
            return null;
        }

        $updates = [];
        if ((string) ($user['telegram_chat_id'] ?? '') !== (string) $telegramId) {
            $updates['telegram_chat_id'] = $telegramId;
        }

        $username = trim((string) ($username ?? ''));
        if ($username !== '' && strtolower((string) ($user['telegram_username'] ?? '')) !== strtolower($username)) {
            $updates['telegram_username'] = $username;
        }

        if (!empty($updates)) {
            $this->db->update('users', $updates, 'id = ?', [(int) $user['id']]);
        }

        return $this->findActiveCompanyUserById((int) $user['id']);
    }

    private function ensureUniqueTelegramEmail(string $seedLocalPart, int $telegramId): string
    {
        $seedLocalPart = strtolower(trim($seedLocalPart));
        $seedLocalPart = preg_replace('/[^a-z0-9._-]+/', '', $seedLocalPart) ?? '';
        if ($seedLocalPart === '') {
            $seedLocalPart = 'tg' . $telegramId;
        }

        $attempt = 0;
        while ($attempt < 100) {
            $suffix = $attempt === 0 ? '' : '+' . $attempt;
            $email = $seedLocalPart . $suffix . '@telegram.local';

            $existing = $this->db->selectOne(
                "SELECT id, telegram_chat_id
                 FROM users
                 WHERE company_id = ?
                   AND email = ?
                 LIMIT 1",
                [$this->companyId, $email]
            );

            if (!$existing) {
                return $email;
            }

            if (!empty($existing['telegram_chat_id']) && (string) $existing['telegram_chat_id'] === (string) $telegramId) {
                return $email;
            }

            $attempt++;
        }

        return 'tg' . $telegramId . '.' . time() . '@telegram.local';
    }

    private function ensureCustomerUser(array $telegramUser): ?array
    {
        $telegramId = (int) ($telegramUser['id'] ?? 0);
        if ($telegramId <= 0) {
            return null;
        }

        $linked = $this->findLinkedUserByTelegramId($telegramId);
        if ($linked) {
            return $linked;
        }

        $displayName = $this->telegramDisplayName($telegramUser);
        $seed = (string) ($telegramUser['username'] ?? ('tg' . $telegramId));
        $email = $this->ensureUniqueTelegramEmail($seed, $telegramId);

        $userId = $this->db->insert('users', [
            'company_id' => $this->companyId,
            'email' => $email,
            'name' => $displayName,
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'role' => 'customer',
            'telegram_chat_id' => $telegramId,
            'telegram_username' => $telegramUser['username'] ?? null,
            'is_active' => 1,
        ]);

        return $this->findActiveCompanyUserById($userId);
    }

    private function findStaffCandidateUser(array $telegramUser): ?array
    {
        $telegramId = (int) ($telegramUser['id'] ?? 0);
        if ($telegramId <= 0) {
            return null;
        }

        $linked = $this->findLinkedUserByTelegramId($telegramId);
        if ($linked && ($linked['role'] ?? '') !== 'customer') {
            return $linked;
        }

        $username = trim((string) ($telegramUser['username'] ?? ''));
        if ($username !== '') {
            $byUsername = $this->db->selectOne(
                "SELECT *
                 FROM users
                 WHERE company_id = ?
                   AND is_active = 1
                   AND role <> 'customer'
                   AND LOWER(COALESCE(telegram_username, '')) = LOWER(?)
                 ORDER BY id ASC
                 LIMIT 1",
                [$this->companyId, $username]
            );
            if ($byUsername) {
                return $byUsername;
            }
        }

        $displayName = trim($this->telegramDisplayName($telegramUser));
        if ($displayName !== '' && strtolower($displayName) !== 'telegram user') {
            $byExactName = $this->db->selectOne(
                "SELECT *
                 FROM users
                 WHERE company_id = ?
                   AND is_active = 1
                   AND role <> 'customer'
                   AND LOWER(TRIM(name)) = LOWER(TRIM(?))
                 ORDER BY id ASC
                 LIMIT 1",
                [$this->companyId, $displayName]
            );
            if ($byExactName) {
                return $byExactName;
            }
        }

        $firstName = trim((string) ($telegramUser['first_name'] ?? ''));
        if ($firstName !== '') {
            $matches = $this->db->select(
                "SELECT *
                 FROM users
                 WHERE company_id = ?
                   AND is_active = 1
                   AND role <> 'customer'
                   AND LOWER(name) LIKE LOWER(?)
                 ORDER BY id ASC
                 LIMIT 2",
                [$this->companyId, $firstName . '%']
            );
            if (count($matches) === 1) {
                return $matches[0];
            }
        }

        return null;
    }

    private function findMappedStaffUser(?array $profile, ?array $runtimeUser = null): ?array
    {
        if (!$profile || (($profile['role_type'] ?? '') !== 'staff')) {
            return null;
        }

        $linkedUserId = (int) ($profile['linked_user_id'] ?? 0);
        if ($linkedUserId <= 0) {
            if ($runtimeUser && (($runtimeUser['role'] ?? '') !== 'customer')) {
                return $runtimeUser;
            }
            return null;
        }

        if (
            $runtimeUser
            && (int) ($runtimeUser['id'] ?? 0) === $linkedUserId
            && (($runtimeUser['role'] ?? '') !== 'customer')
        ) {
            return $runtimeUser;
        }

        $mappedUser = $this->findActiveCompanyUserById($linkedUserId);
        if (!$mappedUser || (($mappedUser['role'] ?? '') === 'customer')) {
            return null;
        }

        return $mappedUser;
    }

    private function resolveHierarchyForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'supervisor' => null,
                'department' => null,
                'department_manager' => null,
            ];
        }

        $supervisor = $this->db->selectOne(
            "SELECT s.id, s.name, s.role
             FROM user_reporting ur
             JOIN users s ON s.id = ur.supervisor_user_id
             WHERE ur.company_id = ?
               AND ur.user_id = ?
               AND s.company_id = ?
               AND s.is_active = 1
             LIMIT 1",
            [$this->companyId, $userId, $this->companyId]
        );

        $department = $this->db->selectOne(
            "SELECT d.id AS department_id, d.name AS department_name,
                    d.manager_user_id,
                    m.name AS manager_name,
                    m.role AS manager_role
             FROM department_users du
             JOIN departments d ON d.id = du.department_id
                AND d.company_id = du.company_id
                AND d.is_active = 1
             LEFT JOIN users m ON m.id = d.manager_user_id
                AND m.company_id = d.company_id
                AND m.is_active = 1
             WHERE du.company_id = ?
               AND du.user_id = ?
             ORDER BY du.id ASC
             LIMIT 1",
            [$this->companyId, $userId]
        );

        return [
            'supervisor' => $supervisor ? [
                'id' => (int) ($supervisor['id'] ?? 0),
                'name' => (string) ($supervisor['name'] ?? ''),
                'role' => (string) ($supervisor['role'] ?? ''),
            ] : null,
            'department' => $department ? [
                'id' => (int) ($department['department_id'] ?? 0),
                'name' => (string) ($department['department_name'] ?? ''),
            ] : null,
            'department_manager' => $department && !empty($department['manager_user_id']) ? [
                'id' => (int) $department['manager_user_id'],
                'name' => (string) ($department['manager_name'] ?? ''),
                'role' => (string) ($department['manager_role'] ?? ''),
            ] : null,
        ];
    }

    private function resolveHierarchyFallbackAssignee(int $requesterUserId): ?int
    {
        if ($requesterUserId <= 0) {
            return null;
        }

        $supervisorId = $this->resolveDirectSupervisorAssignee($requesterUserId);
        if ($supervisorId) {
            return $supervisorId;
        }

        $departmentManager = $this->db->selectOne(
            "SELECT d.manager_user_id
             FROM department_users du
             JOIN departments d ON d.id = du.department_id
             JOIN users u ON u.id = d.manager_user_id
             WHERE du.company_id = ?
               AND du.user_id = ?
               AND d.company_id = ?
               AND d.is_active = 1
               AND d.manager_user_id IS NOT NULL
               AND u.is_active = 1
             ORDER BY du.id ASC
             LIMIT 1",
            [$this->companyId, $requesterUserId, $this->companyId]
        );

        if (!empty($departmentManager['manager_user_id'])) {
            return (int) $departmentManager['manager_user_id'];
        }

        return null;
    }

    private function resolveDirectSupervisorAssignee(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $supervisor = $this->db->selectOne(
            "SELECT ur.supervisor_user_id
             FROM user_reporting ur
             JOIN users u ON u.id = ur.supervisor_user_id
             WHERE ur.company_id = ?
               AND ur.user_id = ?
               AND u.company_id = ?
               AND u.is_active = 1
             LIMIT 1",
            [$this->companyId, $userId, $this->companyId]
        );

        if (empty($supervisor['supervisor_user_id'])) {
            return null;
        }

        return (int) $supervisor['supervisor_user_id'];
    }

    private function resolveStaffRoutingUserId(array $profile, ?array $resolvedUser): ?int
    {
        if (($profile['role_type'] ?? '') !== 'staff') {
            return null;
        }

        $mappedStaffUser = $this->findMappedStaffUser($profile, $resolvedUser);
        if (!$mappedStaffUser) {
            return null;
        }

        return (int) ($mappedStaffUser['id'] ?? 0);
    }

    private function getCategoryRoutingRule(int $categoryId): ?array
    {
        if ($categoryId <= 0) {
            return null;
        }

        try {
            return $this->db->selectOne(
                "SELECT id, category_id, route_mode, department_id, queue_strategy
                 FROM category_routing_rules
                 WHERE company_id = ?
                   AND category_id = ?
                   AND is_active = 1
                 LIMIT 1",
                [$this->companyId, $categoryId]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveDepartmentQueueAssignee(int $departmentId, string $queueStrategy = 'least_open'): ?int
    {
        if ($departmentId <= 0) {
            return null;
        }

        $queueStrategy = strtolower(trim($queueStrategy));
        if ($queueStrategy === 'round_robin') {
            $row = $this->db->selectOne(
                "SELECT u.id, COALESCE(MAX(t.created_at), '1970-01-01 00:00:00') AS last_assigned_at
                 FROM department_users du
                 JOIN users u ON u.id = du.user_id
                    AND u.company_id = du.company_id
                    AND u.is_active = 1
                    AND u.role <> 'customer'
                 LEFT JOIN tickets t ON t.company_id = du.company_id
                    AND t.assigned_to = u.id
                 WHERE du.company_id = ?
                   AND du.department_id = ?
                 GROUP BY u.id
                 ORDER BY last_assigned_at ASC, u.id ASC
                 LIMIT 1",
                [$this->companyId, $departmentId]
            );

            return $row ? (int) ($row['id'] ?? 0) : null;
        }

        $row = $this->db->selectOne(
            "SELECT u.id, COUNT(t.id) AS open_count,
                    COALESCE(MAX(t.updated_at), '1970-01-01 00:00:00') AS last_open_ticket_at
             FROM department_users du
             JOIN users u ON u.id = du.user_id
                AND u.company_id = du.company_id
                AND u.is_active = 1
                AND u.role <> 'customer'
             LEFT JOIN tickets t ON t.company_id = du.company_id
                AND t.assigned_to = u.id
                AND t.status IN ('open', 'pending', 'in_progress')
             WHERE du.company_id = ?
               AND du.department_id = ?
             GROUP BY u.id
             ORDER BY open_count ASC, last_open_ticket_at ASC, u.id ASC
             LIMIT 1",
            [$this->companyId, $departmentId]
        );

        return $row ? (int) ($row['id'] ?? 0) : null;
    }

    private function resolveCategoryRoutingAssignee(array $profile, ?array $resolvedUser, int $categoryId): ?int
    {
        $rule = $this->getCategoryRoutingRule($categoryId);
        if (!$rule) {
            return null;
        }

        $routeMode = (string) ($rule['route_mode'] ?? 'workflow_default');
        if ($routeMode === 'staff_supervisor') {
            $staffUserId = $this->resolveStaffRoutingUserId($profile, $resolvedUser);
            if ($staffUserId) {
                $supervisorId = $this->resolveDirectSupervisorAssignee($staffUserId);
                return $supervisorId ?: $staffUserId;
            }
            return null;
        }

        if ($routeMode === 'department_queue') {
            $departmentId = (int) ($rule['department_id'] ?? 0);
            $queueStrategy = (string) ($rule['queue_strategy'] ?? 'least_open');
            return $this->resolveDepartmentQueueAssignee($departmentId, $queueStrategy);
        }

        return null;
    }

    private function buildProfileState(?array $profile, ?array $linkedUser): array
    {
        $roleType = $profile['role_type'] ?? null;
        $isStaff = $roleType === 'staff';
        $mappedStaffUser = $this->findMappedStaffUser($profile, $linkedUser);
        $exposedLinkedUser = $isStaff ? $mappedStaffUser : $linkedUser;
        $hasLinked = $exposedLinkedUser !== null;
        $needsStaffMatch = $isStaff && ($mappedStaffUser === null);
        $hierarchy = ($isStaff && $mappedStaffUser)
            ? $this->resolveHierarchyForUser((int) $mappedStaffUser['id'])
            : [
                'supervisor' => null,
                'department' => null,
                'department_manager' => null,
            ];

        return [
            'onboarding_required' => empty($roleType),
            'role_type' => $roleType,
            'needs_staff_match' => $needsStaffMatch,
            'staff_mapping_required' => $needsStaffMatch,
            'linked_user' => $hasLinked ? [
                'id' => (int) ($exposedLinkedUser['id'] ?? 0),
                'name' => (string) ($exposedLinkedUser['name'] ?? ''),
                'email' => (string) ($exposedLinkedUser['email'] ?? ''),
                'role' => (string) ($exposedLinkedUser['role'] ?? ''),
            ] : null,
            'hierarchy' => $hierarchy,
        ];
    }

    private function resolveMiniAppUserByProfile(array $telegramUser, ?array &$profile = null): ?array
    {
        $telegramId = (int) ($telegramUser['id'] ?? 0);
        if ($telegramId <= 0) {
            return null;
        }

        $profile = $profile ?: $this->getMiniAppProfileByTelegramId($telegramId);
        if (!$profile || empty($profile['role_type'])) {
            return null;
        }

        $roleType = (string) $profile['role_type'];
        $runtimeLinked = $this->findLinkedUserByTelegramId($telegramId);

        if ($roleType === 'customer') {
            if (!$runtimeLinked) {
                $runtimeLinked = $this->ensureCustomerUser($telegramUser);
            }

            if ($runtimeLinked) {
                $profile = $this->saveMiniAppProfileRecord(
                    $telegramId,
                    $roleType,
                    (int) $runtimeLinked['id'],
                    $this->telegramDisplayName($telegramUser)
                );
            }

            return $runtimeLinked;
        }

        $mappedStaffUser = $this->findMappedStaffUser($profile);
        $runtimeUser = $runtimeLinked ?: $this->ensureCustomerUser($telegramUser);
        if (!$runtimeUser) {
            return null;
        }

        $profile = $this->saveMiniAppProfileRecord(
            $telegramId,
            'staff',
            $mappedStaffUser ? (int) $mappedStaffUser['id'] : null,
            $this->telegramDisplayName($telegramUser)
        );

        return $runtimeUser;
    }

    public function profile(): void
    {
        $lang = $this->resolveLang((string) $this->request->query('lang', ''));
        $initData = (string) $this->request->query('initData', '');

        $telegramUser = $this->validateInitData($initData);
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }

        $telegramId = (int) ($telegramUser['id'] ?? 0);
        $profile = $this->getMiniAppProfileByTelegramId($telegramId);
        $linkedUser = null;

        if ($profile && !empty($profile['role_type'])) {
            $resolved = $this->resolveMiniAppUserByProfile($telegramUser, $profile);
            $linkedUser = (($profile['role_type'] ?? '') === 'staff')
                ? $this->findMappedStaffUser($profile, $resolved)
                : $resolved;
        }

        $this->json([
            'success' => true,
            'message' => '',
        ] + $this->buildProfileState($profile, $linkedUser));
    }

    public function saveProfile(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $input = is_array($input) ? $input : [];
        $lang = $this->resolveLang($input['lang'] ?? null);

        $initData = (string) ($input['initData'] ?? '');
        $roleType = strtolower(trim((string) ($input['role_type'] ?? '')));

        if (!in_array($roleType, ['customer', 'staff'], true)) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_role_type')], 422);
            return;
        }

        $telegramUser = $this->validateInitData($initData);
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }

        $telegramId = (int) ($telegramUser['id'] ?? 0);
        if ($telegramId <= 0) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_user')], 422);
            return;
        }

        $existingProfile = $this->getMiniAppProfileByTelegramId($telegramId);
        if ($existingProfile && !empty($existingProfile['role_type'])) {
            $resolvedUser = $this->resolveMiniAppUserByProfile($telegramUser, $existingProfile);
            $linkedUser = (($existingProfile['role_type'] ?? '') === 'staff')
                ? $this->findMappedStaffUser($existingProfile, $resolvedUser)
                : $resolvedUser;
            $this->json([
                'success' => true,
                'message' => $this->msg($lang, 'profile_locked'),
            ] + $this->buildProfileState($existingProfile, $linkedUser));
            return;
        }

        $runtimeUser = null;
        $profileLinkedUser = null;
        if ($roleType === 'customer') {
            $runtimeUser = $this->ensureCustomerUser($telegramUser);
            $profileLinkedUser = $runtimeUser;
        } else {
            $runtimeUser = $this->ensureCustomerUser($telegramUser);
        }

        $profile = $this->saveMiniAppProfileRecord(
            $telegramId,
            $roleType,
            $profileLinkedUser ? (int) $profileLinkedUser['id'] : null,
            $this->telegramDisplayName($telegramUser)
        );

        $linkedUser = ($roleType === 'staff')
            ? $this->findMappedStaffUser($profile, $runtimeUser)
            : $runtimeUser;

        $message = ($roleType === 'staff')
            ? $this->msg($lang, 'staff_mapping_pending_admin')
            : $this->msg($lang, 'profile_saved');

        $this->json([
            'success' => true,
            'message' => $message,
        ] + $this->buildProfileState($profile, $linkedUser));
    }
    
    /**
     * List active categories for Mini App ticket form
     */
    public function categories(): void
    {
        $lang = $this->resolveLang((string) $this->request->query('lang', ''));
        $initData = (string) $this->request->query('initData', '');

        $telegramUser = $this->validateInitData($initData);
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }

        $profile = $this->getMiniAppProfileByTelegramId((int) ($telegramUser['id'] ?? 0));
        if (!$profile || empty($profile['role_type'])) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'onboarding_required')], 403);
            return;
        }

        $user = $this->resolveMiniAppUserByProfile($telegramUser, $profile);
        if (!$user) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'link_before_use')], 403);
            return;
        }

        $categories = $this->db->select(
            "SELECT id, name, color
             FROM categories
             WHERE company_id = ? AND is_active = 1
             ORDER BY sort_order ASC, name ASC",
            [$this->companyId]
        );

        $this->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Create new ticket from Mini App
     */
    public function createTicket(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $input = is_array($input) ? $input : [];
        $lang = $this->resolveLang($input['lang'] ?? null);

        $initData = $input['initData'] ?? '';
        $subject = trim((string)($input['subject'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $selectedCategoryId = (int)($input['category_id'] ?? 0);
        $hasAttachment = !empty($input['has_attachment']);
        
        error_log("[MiniApp] createTicket called with subject: {$subject}");
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            error_log("[MiniApp] Invalid Telegram data");
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }
        
        error_log("[MiniApp] Telegram user validated: " . $telegramUser['id']);

        $profile = $this->getMiniAppProfileByTelegramId((int) ($telegramUser['id'] ?? 0));
        if (!$profile || empty($profile['role_type'])) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'onboarding_required')], 403);
            return;
        }

        $user = $this->resolveMiniAppUserByProfile($telegramUser, $profile);
        if (!$user) {
            error_log("[MiniApp] User not found, chat_id: " . $telegramUser['id']);
            $this->json(['success' => false, 'message' => $this->msg($lang, 'user_not_found')], 403);
            return;
        }
        
        error_log("[MiniApp] User found: " . $user['email']);
        
        if (strlen($subject) < 5) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'subject_min')], 422);
            return;
        }
        
        if (strlen($description) < 10 && !$hasAttachment) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'description_min')], 422);
            return;
        }

        if ($selectedCategoryId <= 0) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'category_required')], 422);
            return;
        }

        $selectedCategory = $this->db->selectOne(
            "SELECT id FROM categories WHERE id = ? AND company_id = ? AND is_active = 1 LIMIT 1",
            [$selectedCategoryId, $this->companyId]
        );
        if (!$selectedCategory) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_category')], 422);
            return;
        }
        
        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId);
        $ticketNumber = $ticketModel->generateTicketNumber();
        
        try {
            $classifier = new TicketClassifier($this->db, $this->companyId);
            $classification = $classifier->classify($subject . ' ' . $description);
        } catch (\Exception $e) {
            $classification = ['category_id' => null, 'priority' => 'medium', 'confidence' => 0];
        }
        
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = $classification['priority'] ?? 'medium';
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }
        $aiPriority = $classification['priority'] ?? null;
        if ($aiPriority && !in_array($aiPriority, $validPriorities)) {
            $aiPriority = null;
        }

        $categoryId = $selectedCategoryId;
        $this->miniAppLog(
            'createTicket routing start'
            . ' profile_role=' . (string) ($profile['role_type'] ?? '')
            . ' requester_user_id=' . (int) ($user['id'] ?? 0)
            . ' category_id=' . $categoryId
        );

        $workflowResolved = null;
        $assignedTo = null;
        try {
            $workflowRouter = new WorkflowRouter($this->db, $this->companyId);
            $workflowResolved = $workflowRouter->resolveForTicket('telegram', $categoryId, (int) $user['id']);
            $assignedTo = $workflowResolved['assignee_id'] ?? null;
        } catch (\Throwable $e) {
            $this->miniAppLog('Workflow resolve failed: ' . $e->getMessage());
        }

        $categoryRuleAssignee = $this->resolveCategoryRoutingAssignee($profile, $user, $categoryId);
        if ($categoryRuleAssignee) {
            $assignedTo = $categoryRuleAssignee;
            if (is_array($workflowResolved)) {
                $workflowResolved['assignee_id'] = $assignedTo;
            }
            $this->miniAppLog('Category routing assignment resolved to user_id=' . $assignedTo);
        }

        $staffRoutingUserId = $this->resolveStaffRoutingUserId($profile, $user);
        $this->miniAppLog('Staff routing source user_id=' . (int) ($staffRoutingUserId ?? 0));
        if (!$assignedTo && $staffRoutingUserId) {
            $assignedTo = $this->resolveHierarchyFallbackAssignee($staffRoutingUserId);
            if ($assignedTo) {
                $this->miniAppLog('Hierarchy fallback assignment resolved to user_id=' . $assignedTo);
                if (is_array($workflowResolved)) {
                    $workflowResolved['assignee_id'] = $assignedTo;
                }
            }
        }
        $this->miniAppLog('Final assignment user_id=' . (int) ($assignedTo ?? 0));
        
        $ticketId = $ticketModel->create([
            'company_id' => $this->companyId,
            'ticket_number' => $ticketNumber,
            'subject' => $subject,
            'description' => $description,
            'status' => 'open',
            'priority' => $priority,
            'source' => 'telegram',
            'category_id' => $categoryId,
            'assigned_to' => $assignedTo,
            'requester_id' => $user['id'],
            'requester_email' => $user['email'],
            'requester_name' => $user['name'],
            'ai_suggested_category' => $classification['category_id'] ?? null,
            'ai_suggested_priority' => $aiPriority,
            'ai_confidence_score' => $classification['confidence'] ?? null,
        ]);

        if (is_array($workflowResolved)) {
            try {
                $workflowRouter ??= new WorkflowRouter($this->db, $this->companyId);
                $workflowRouter->startTicketWorkflow($ticketId, (int) $user['id'], $workflowResolved);
            } catch (\Throwable $e) {
                $this->miniAppLog('Workflow start failed: ' . $e->getMessage());
            }
        }
        
        $messageModel = new TicketMessage($this->db);
        $messageId = $messageModel->addReply($ticketId, $user['id'], $description, false, 'telegram');
        
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $this->companyId,
            $ticketId,
            $user['id'],
            'ticket_created',
            "Ticket {$ticketNumber} created via Telegram Mini App"
        );
        
        $this->notifyAgents($ticketId, $ticketNumber, $user, $subject);
        
        error_log("[MiniApp] Ticket created: {$ticketNumber}");
        
        $this->json([
            'success' => true,
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'message_id' => $messageId
        ]);
    }

    /**
     * Link email account to Telegram
     */
    public function linkEmail(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $input = is_array($input) ? $input : [];
        $lang = $this->resolveLang($input['lang'] ?? null);

        $initData = $input['initData'] ?? '';
        $email = trim((string)($input['email'] ?? ''));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_email')], 422);
            return;
        }

        $telegramUser = $this->validateInitData($initData);
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }

        $telegramId = (int)($telegramUser['id'] ?? 0);
        if ($telegramId <= 0) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_user')], 422);
            return;
        }

        $displayName = trim(($telegramUser['first_name'] ?? '') . ' ' . ($telegramUser['last_name'] ?? ''));
        if (empty($displayName) && !empty($telegramUser['username'])) {
            $displayName = $telegramUser['username'];
        }
        if (empty($displayName)) {
            $displayName = 'Telegram User';
        }

        $userModel = new User($this->db);
        $currentLinkedUser = $this->findLinkedUserByTelegramId($telegramId);
        $user = $userModel->findByEmail($email, $this->companyId);

        if ($user && !empty($user['telegram_chat_id']) && (string)$user['telegram_chat_id'] !== (string)$telegramId) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'email_already_linked')], 409);
            return;
        }

        if ($currentLinkedUser && (!$user || (int) $user['id'] === (int) $currentLinkedUser['id'])) {
            $updates = [
                'email' => $email,
            ];
            if (($currentLinkedUser['name'] ?? '') === 'Telegram User' && $displayName !== '') {
                $updates['name'] = $displayName;
            }
            if (!empty($telegramUser['username'])) {
                $updates['telegram_username'] = (string) $telegramUser['username'];
            }
            $this->db->update('users', $updates, 'id = ?', [(int) $currentLinkedUser['id']]);

            $this->saveMiniAppProfileRecord(
                $telegramId,
                (($currentLinkedUser['role'] ?? '') === 'customer') ? 'customer' : 'staff',
                (int) $currentLinkedUser['id'],
                $displayName
            );

            $this->json(['success' => true, 'message' => $this->msg($lang, 'link_success')]);
            return;
        }

        if (!$user) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'user_not_found')], 404);
            return;
        }

        if ($user['name'] === 'Telegram User' && $displayName) {
            $this->db->update('users', ['name' => $displayName], 'id = ?', [$user['id']]);
        }

        $username = $telegramUser['username'] ?? ($user['telegram_username'] ?? null);
        $userModel->linkTelegram((int)$user['id'], $telegramId, $username);
        $this->saveMiniAppProfileRecord(
            $telegramId,
            (($user['role'] ?? '') === 'customer') ? 'customer' : 'staff',
            (int) $user['id'],
            $displayName
        );

        $this->json(['success' => true, 'message' => $this->msg($lang, 'link_success')]);
    }
    
    /**
     * Get user's tickets
     */
    public function myTickets(): void
    {
        $initData = $_GET['initData'] ?? '';
        $lang = $this->resolveLang($_GET['lang'] ?? null);
        
        error_log("[MiniApp] myTickets called");
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }
        
        $profile = $this->getMiniAppProfileByTelegramId((int) ($telegramUser['id'] ?? 0));
        if (!$profile || empty($profile['role_type'])) {
            $this->json(['success' => false, 'tickets' => [], 'message' => $this->msg($lang, 'onboarding_required')], 403);
            return;
        }

        $user = $this->resolveMiniAppUserByProfile($telegramUser, $profile);
        if (!$user) {
            $this->json(['success' => false, 'tickets' => [], 'message' => $this->msg($lang, 'user_not_found')], 403);
            return;
        }
        
        $tickets = $this->db->select(
            "SELECT * FROM tickets 
             WHERE requester_id = ? AND company_id = ? AND status NOT IN ('closed') 
             ORDER BY created_at DESC 
             LIMIT 50",
            [$user['id'], $this->companyId]
        );
        
        error_log("[MiniApp] Found " . count($tickets) . " tickets");
        
        $this->json(['success' => true, 'tickets' => $tickets]);
    }
    
    /**
     * Get ticket details
     */
    public function getTicket(string $id): void
    {
        $initData = $_GET['initData'] ?? '';
        $lang = $this->resolveLang($_GET['lang'] ?? null);
        
        error_log("[MiniApp] getTicket called for ID: {$id}");
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }
        
        $profile = $this->getMiniAppProfileByTelegramId((int) ($telegramUser['id'] ?? 0));
        if (!$profile || empty($profile['role_type'])) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'onboarding_required')], 403);
            return;
        }

        $user = $this->resolveMiniAppUserByProfile($telegramUser, $profile);
        if (!$user) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'user_not_found')], 403);
            return;
        }
        
        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets WHERE id = ? AND requester_id = ? AND company_id = ?",
            [(int)$id, $user['id'], $this->companyId]
        );
        
        if (!$ticket) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'ticket_not_found')], 404);
            return;
        }
        
        $messageModel = new TicketMessage($this->db);
        $messages = $messageModel->getByTicket((int) $id, false);
        
        error_log("[MiniApp] Found ticket with " . count($messages) . " messages");
        
        $this->json([
            'success' => true,
            'ticket' => $ticket,
            'messages' => $messages
        ]);
    }
    
    /**
     * Send reply
     */
    public function sendReply(): void
    {
        $debugFile = dirname(__DIR__, 3) . '/storage/logs/miniapp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($debugFile, "[{$timestamp}] ===== SEND REPLY START =====\n", FILE_APPEND);
        file_put_contents($debugFile, "[{$timestamp}] Request method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $input = is_array($input) ? $input : [];
        $lang = $this->resolveLang($input['lang'] ?? null);
        file_put_contents($debugFile, "[{$timestamp}] Raw input: " . json_encode($input) . "\n", FILE_APPEND);
        
        $initData = $input['initData'] ?? '';
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $message = trim((string)($input['message'] ?? ''));
        $hasAttachment = !empty($input['has_attachment']);
        
        file_put_contents($debugFile, "[{$timestamp}] sendReply called for ticket: {$ticketId}, message length: " . strlen($message) . "\n", FILE_APPEND);
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }
        
        $profile = $this->getMiniAppProfileByTelegramId((int) ($telegramUser['id'] ?? 0));
        if (!$profile || empty($profile['role_type'])) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'onboarding_required')], 403);
            return;
        }

        $user = $this->resolveMiniAppUserByProfile($telegramUser, $profile);
        if (!$user) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'user_not_found')], 403);
            return;
        }
        
        if ($message === '' && !$hasAttachment) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'message_required')], 422);
            return;
        }
        
        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets WHERE id = ? AND requester_id = ? AND company_id = ?",
            [$ticketId, $user['id'], $this->companyId]
        );
        
        if (!$ticket) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'ticket_not_found')], 404);
            return;
        }
        
        $messageModel = new TicketMessage($this->db);
        $messageId = $messageModel->addReply($ticketId, $user['id'], $message, false, 'telegram');
        
        if (in_array($ticket['status'], ['resolved', 'closed'])) {
            $this->db->update('tickets', ['status' => 'open'], 'id = ?', [$ticketId]);
        }
        
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $this->companyId,
            $ticketId,
            $user['id'],
            'telegram_reply',
            'Customer replied via Telegram Mini App'
        );
        
        if ($message === '' && $hasAttachment) {
            $preview = $this->msg($lang, 'attachment_added');
        } else {
            $preview = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
        }
        $this->notifyAgents($ticketId, $ticket['ticket_number'], $user, $preview, 'telegram_reply');

        if (!empty($ticket['assigned_to'])) {
            $assignedUser = $this->db->selectOne(
                "SELECT name FROM users WHERE id = ? AND company_id = ?",
                [$ticket['assigned_to'], $this->companyId]
            );
            $assignedName = $assignedUser['name'] ?? 'Agent';
            $link = $this->app->url("tickets/{$ticketId}");
            $subject = htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES | ENT_HTML5);
            $requesterName = htmlspecialchars($ticket['requester_name'] ?? '', ENT_QUOTES | ENT_HTML5);
            $requesterEmail = htmlspecialchars($ticket['requester_email'] ?? '', ENT_QUOTES | ENT_HTML5);
            $assignedLabel = htmlspecialchars($assignedName, ENT_QUOTES | ENT_HTML5);
            $status = ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'open'));
            $priority = ucfirst($ticket['priority'] ?? 'medium');
            $previewSafe = htmlspecialchars($preview, ENT_QUOTES | ENT_HTML5);
            $text = "💬 <b>New Customer Reply</b>\n" .
                "🆔 <b>Ticket:</b> #{$ticket['ticket_number']}\n" .
                "📝 <b>Subject:</b> {$subject}\n" .
                "📌 <b>Status:</b> {$status}\n" .
                "🚦 <b>Priority:</b> {$priority}\n" .
                "👤 <b>Requester:</b> {$requesterName} ({$requesterEmail})\n" .
                "🎯 <b>Assigned to:</b> {$assignedLabel}\n" .
                "✉️ <b>Message:</b> {$previewSafe}\n" .
                "🔗 <b>Link:</b> {$link}";
            $notifier = new TelegramAgentNotifier($this->db, $this->companyId);
            $notifier->notifyAssignedAgent($ticket, $text);
            
            $debugFile = dirname(__DIR__, 3) . '/storage/logs/miniapp_debug.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($debugFile, "[{$timestamp}] Attempting to send push notification to agent ID: " . $ticket['assigned_to'] . "\n", FILE_APPEND);
            try {
                $pushService = new \App\Services\PushNotificationService($this->db);
                file_put_contents($debugFile, "[{$timestamp}] PushService created successfully\n", FILE_APPEND);
                
                $result = $pushService->sendToUsers(
                    [$ticket['assigned_to']],
                    'New Reply - Ticket #' . $ticket['ticket_number'],
                    'Customer replied: ' . $preview,
                    [
                        'ticketId' => $ticketId,
                        'ticketNumber' => $ticket['ticket_number'],
                        'type' => 'reply'
                    ]
                );
                file_put_contents($debugFile, "[{$timestamp}] Push notification result: " . json_encode($result) . "\n", FILE_APPEND);
            } catch (\Exception $e) {
                file_put_contents($debugFile, "[{$timestamp}] Web push notification exception: " . $e->getMessage() . " - " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
                file_put_contents($debugFile, "[{$timestamp}] Exception trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            }
        } else {
            $debugFile = dirname(__DIR__, 3) . '/storage/logs/miniapp_debug.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($debugFile, "[{$timestamp}] No assigned agent, skipping push notification\n", FILE_APPEND);
        }
        
        $debugFile = dirname(__DIR__, 3) . '/storage/logs/miniapp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($debugFile, "[{$timestamp}] Reply added successfully\n", FILE_APPEND);
        
        $this->json(['success' => true, 'message_id' => $messageId]);
    }
    
    /**
     * Upload image/document for ticket or reply
     */
    public function uploadFile(): void
    {
        $initData = $_POST['initData'] ?? '';
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $messageId = (int)($_POST['message_id'] ?? 0);
        $lang = $this->resolveLang($_POST['lang'] ?? null);
        
        error_log("[MiniApp] uploadFile called for ticket: {$ticketId}");
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_telegram_data')], 403);
            return;
        }
        
        $profile = $this->getMiniAppProfileByTelegramId((int) ($telegramUser['id'] ?? 0));
        if (!$profile || empty($profile['role_type'])) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'onboarding_required')], 403);
            return;
        }

        $user = $this->resolveMiniAppUserByProfile($telegramUser, $profile);
        if (!$user) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'user_not_found')], 403);
            return;
        }

        if ($ticketId <= 0 || $messageId <= 0) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'invalid_ticket_or_message')], 422);
            return;
        }

        $ticket = $this->db->selectOne(
            "SELECT id FROM tickets WHERE id = ? AND requester_id = ? AND company_id = ?",
            [$ticketId, $user['id'], $this->companyId]
        );

        if (!$ticket) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'ticket_not_found')], 404);
            return;
        }

        $messageRow = $this->db->selectOne(
            "SELECT id FROM ticket_messages WHERE id = ? AND ticket_id = ?",
            [$messageId, $ticketId]
        );

        if (!$messageRow) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'message_not_found')], 404);
            return;
        }
        
        if (!isset($_FILES['file'])) {
            $this->json(['success' => false, 'message' => $this->msg($lang, 'no_file_provided')], 422);
            return;
        }
        
        $file = $_FILES['file'];

        try {
            $uploader = new FileUploader();
            $uploaded = $uploader->upload($file, 'tickets/' . $ticketId);

            $this->db->insert('attachments', [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'user_id' => $user['id'],
                'filename' => $uploaded['filename'],
                'original_name' => $uploaded['original_name'],
                'mime_type' => $uploaded['mime_type'],
                'size' => $uploaded['size'],
                'path' => $uploaded['path'],
            ]);

            error_log("[MiniApp] File uploaded: {$uploaded['filename']}");

            $this->json([
                'success' => true,
                'filename' => $uploaded['filename'],
                'path' => $uploaded['path'],
                'url' => '/uploads/' . $uploaded['path'],
                'size' => $uploaded['size'],
                'mime_type' => $uploaded['mime_type'],
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
    
    /**
     * Notify agents about new ticket or reply
     */
    private function notifyAgents(int $ticketId, string $ticketNumber, array $user, string $message, string $type = 'new_ticket'): void
    {
        try {
            $ticket = $this->db->selectOne(
                "SELECT assigned_to FROM tickets WHERE id = ?",
                [$ticketId]
            );
            
            if (!$ticket) {
                return;
            }
            
            $agents = [];
            if (!empty($ticket['assigned_to'])) {
                $agents = $this->db->select(
                    "SELECT id FROM users WHERE id = ? AND is_active = 1",
                    [$ticket['assigned_to']]
                );
            }
            
            if (empty($agents)) {
                $agents = $this->db->select(
                    "SELECT id FROM users WHERE company_id = ? AND role IN ('admin','agent') AND is_active = 1",
                    [$this->companyId]
                );
            }
            
            $title = $type === 'new_ticket' 
                ? "New ticket #{$ticketNumber}" 
                : "New reply on #{$ticketNumber}";
            
            foreach ($agents as $agent) {
                $this->db->insert('notifications', [
                    'user_id' => $agent['id'],
                    'ticket_id' => $ticketId,
                    'type' => $type,
                    'title' => $title,
                    'message' => "{$user['name']}: {$message}",
                    'data' => json_encode([
                        'source' => 'telegram',
                        'requester' => $user['name'] ?? 'Customer',
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            error_log("[MiniApp] Failed to notify agents: " . $e->getMessage());
        }
    }
}
