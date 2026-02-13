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

class TelegramMiniAppController extends Controller
{
    private int $companyId = 1; // Default company ID

    /**
     * Validate Telegram WebApp initData
     */
    private function validateInitData(string $initData): ?array
    {
        $debugFile = dirname(__DIR__, 3) . '/storage/logs/miniapp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($debugFile, "[{$timestamp}] validateInitData called, initData length: " . strlen($initData) . "\n", FILE_APPEND);
        
        if (empty($initData)) {
            file_put_contents($debugFile, "[{$timestamp}] Empty initData\n", FILE_APPEND);
            return null;
        }
        
        // Get bot token
        $config = $this->db->selectOne(
            "SELECT bot_token FROM telegram_configs WHERE company_id = ? AND is_active = 1",
            [$this->companyId]
        );
        
        if (!$config) {
            file_put_contents($debugFile, "[{$timestamp}] No bot config found for company " . $this->companyId . "\n", FILE_APPEND);
            return null;
        }
        
        $botToken = $config['bot_token'];
        file_put_contents($debugFile, "[{$timestamp}] Bot config found, token: " . substr($botToken, 0, 20) . "...\n", FILE_APPEND);
        
        // Parse initData
        parse_str($initData, $data);
        file_put_contents($debugFile, "[{$timestamp}] initData parsed, keys: " . json_encode(array_keys($data)) . "\n", FILE_APPEND);
        
        if (!isset($data['hash'])) {
            file_put_contents($debugFile, "[{$timestamp}] No hash in initData\n", FILE_APPEND);
            return null;
        }
        
        $hash = $data['hash'];
        unset($data['hash']);
        
        // Create data-check-string
        ksort($data);
        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckArr);
        
        // Calculate secret key
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        
        // Calculate hash
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
        file_put_contents($debugFile, "[{$timestamp}] Hash check - received: " . substr($hash, 0, 10) . "..., calculated: " . substr($calculatedHash, 0, 10) . "...\n", FILE_APPEND);
        
        if ($calculatedHash !== $hash) {
            file_put_contents($debugFile, "[{$timestamp}] Hash validation FAILED\n", FILE_APPEND);
            return null; // Invalid
        }
        
        file_put_contents($debugFile, "[{$timestamp}] Hash validation PASSED\n", FILE_APPEND);
        
        // Return user data
        if (isset($data['user'])) {
            $telegramUser = json_decode($data['user'], true);
            file_put_contents($debugFile, "[{$timestamp}] User data decoded, user ID: " . ($telegramUser['id'] ?? 'UNKNOWN') . "\n", FILE_APPEND);
            return $telegramUser;
        }
        
        file_put_contents($debugFile, "[{$timestamp}] No user data in initData\n", FILE_APPEND);
        return null;
    }
    
    /**
     * Create new ticket from Mini App
     */
    public function createTicket(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $initData = $input['initData'] ?? '';
        $subject = $input['subject'] ?? '';
        $description = $input['description'] ?? '';
        
        error_log("[MiniApp] createTicket called with subject: {$subject}");
        
        // Validate Telegram data
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            error_log("[MiniApp] Invalid Telegram data");
            $this->json(['success' => false, 'message' => 'Invalid Telegram data'], 403);
            return;
        }
        
        error_log("[MiniApp] Telegram user validated: " . $telegramUser['id']);
        
        // Find user by telegram_chat_id
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ? AND is_active = 1",
            [$telegramUser['id'], $this->companyId]
        );
        
        if (!$user) {
            error_log("[MiniApp] User not found, chat_id: " . $telegramUser['id']);
            $this->json(['success' => false, 'message' => 'សូមភ្ជាប់គណនីរបស់អ្នកដំបូង ដោយប្រើ /link'], 403);
            return;
        }
        
        error_log("[MiniApp] User found: " . $user['email']);
        
        // Validate input
        if (strlen($subject) < 5) {
            $this->json(['success' => false, 'message' => 'ប្រធានបទត្រូវមានយ៉ាងហោចណាស់ 5 តួអក្សរ'], 422);
            return;
        }
        
        if (strlen($description) < 10) {
            $this->json(['success' => false, 'message' => 'ពិពណ៌នាត្រូវមានយ៉ាងហោចណាស់ 10 តួអក្សរ'], 422);
            return;
        }
        
        // Create ticket
        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId);
        $ticketNumber = $ticketModel->generateTicketNumber();
        
        // AI Classification
        try {
            $classifier = new TicketClassifier($this->db, $this->companyId);
            $classification = $classifier->classify($subject . ' ' . $description);
        } catch (\Exception $e) {
            $classification = ['category_id' => null, 'priority' => 'medium', 'confidence' => 0];
        }
        
        // Validate priority against allowed ENUM values
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = $classification['priority'] ?? 'medium';
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }
        $aiPriority = $classification['priority'] ?? null;
        if ($aiPriority && !in_array($aiPriority, $validPriorities)) {
            $aiPriority = null;
        }
        
        $ticketId = $ticketModel->create([
            'company_id' => $this->companyId,
            'ticket_number' => $ticketNumber,
            'subject' => $subject,
            'description' => $description,
            'status' => 'open',
            'priority' => $priority,
            'source' => 'telegram',
            'requester_id' => $user['id'],
            'requester_email' => $user['email'],
            'requester_name' => $user['name'],
            'ai_suggested_category' => $classification['category_id'] ?? null,
            'ai_suggested_priority' => $aiPriority,
            'ai_confidence_score' => $classification['confidence'] ?? null,
        ]);
        
        // Add message
        $messageModel = new TicketMessage($this->db);
        $messageId = $messageModel->addReply($ticketId, $user['id'], $description, false, 'telegram');
        
        // Log activity
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $this->companyId,
            $ticketId,
            $user['id'],
            'ticket_created',
            "Ticket {$ticketNumber} created via Telegram Mini App"
        );
        
        // Notify agents
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

        $initData = $input['initData'] ?? '';
        $email = trim((string)($input['email'] ?? ''));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'សូមបញ្ចូលអ៊ីមែលត្រឹមត្រូវ'], 422);
            return;
        }

        $telegramUser = $this->validateInitData($initData);
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => 'Invalid Telegram data'], 403);
            return;
        }

        $telegramId = (int)($telegramUser['id'] ?? 0);
        if ($telegramId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid Telegram user'], 422);
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
        $user = $userModel->findByEmail($email, $this->companyId);

        if ($user && !empty($user['telegram_chat_id']) && (string)$user['telegram_chat_id'] !== (string)$telegramId) {
            $this->json(['success' => false, 'message' => 'អ៊ីមែលនេះត្រូវបានភ្ជាប់ជាមួយ Telegram ផ្សេងហើយ'], 409);
            return;
        }

        if (!$user) {
            $this->db->insert('users', [
                'company_id' => $this->companyId,
                'email' => $email,
                'name' => $displayName,
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'role' => 'customer',
                'telegram_chat_id' => $telegramId,
                'telegram_username' => $telegramUser['username'] ?? null,
                'is_active' => 1,
            ]);

            $this->json(['success' => true, 'message' => 'គណនីរបស់អ្នកបានតភ្ជាប់ដោយជោគជ័យ!']);
            return;
        }

        if ($user['name'] === 'Telegram User' && $displayName) {
            $this->db->update('users', ['name' => $displayName], 'id = ?', [$user['id']]);
        }

        $username = $telegramUser['username'] ?? ($user['telegram_username'] ?? null);
        $userModel->linkTelegram((int)$user['id'], $telegramId, $username);

        $this->json(['success' => true, 'message' => 'គណនីរបស់អ្នកបានតភ្ជាប់ដោយជោគជ័យ!']);
    }
    
    /**
     * Get user's tickets
     */
    public function myTickets(): void
    {
        $initData = $_GET['initData'] ?? '';
        
        error_log("[MiniApp] myTickets called");
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => 'Invalid Telegram data'], 403);
            return;
        }
        
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ? AND is_active = 1",
            [$telegramUser['id'], $this->companyId]
        );
        
        if (!$user) {
            $this->json(['success' => false, 'tickets' => [], 'message' => 'User not found']);
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
        
        error_log("[MiniApp] getTicket called for ID: {$id}");
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => 'Invalid Telegram data'], 403);
            return;
        }
        
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ? AND is_active = 1",
            [$telegramUser['id'], $this->companyId]
        );
        
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found'], 403);
            return;
        }
        
        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets WHERE id = ? AND requester_id = ? AND company_id = ?",
            [(int)$id, $user['id'], $this->companyId]
        );
        
        if (!$ticket) {
            $this->json(['success' => false, 'message' => 'Ticket not found'], 404);
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
        // Write to debug file instead of error_log
        $debugFile = dirname(__DIR__, 3) . '/storage/logs/miniapp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($debugFile, "[{$timestamp}] ===== SEND REPLY START =====\n", FILE_APPEND);
        file_put_contents($debugFile, "[{$timestamp}] Request method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
        
        $input = json_decode(file_get_contents('php://input'), true);
        file_put_contents($debugFile, "[{$timestamp}] Raw input: " . json_encode($input) . "\n", FILE_APPEND);
        
        $initData = $input['initData'] ?? '';
        $ticketId = (int)($input['ticket_id'] ?? 0);
        $message = $input['message'] ?? '';
        
        file_put_contents($debugFile, "[{$timestamp}] sendReply called for ticket: {$ticketId}, message length: " . strlen($message) . "\n", FILE_APPEND);
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => 'Invalid Telegram data'], 403);
            return;
        }
        
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ? AND is_active = 1",
            [$telegramUser['id'], $this->companyId]
        );
        
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found'], 403);
            return;
        }
        
        if (empty($message)) {
            $this->json(['success' => false, 'message' => 'Message is required'], 422);
            return;
        }
        
        // Verify ticket belongs to user
        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets WHERE id = ? AND requester_id = ? AND company_id = ?",
            [$ticketId, $user['id'], $this->companyId]
        );
        
        if (!$ticket) {
            $this->json(['success' => false, 'message' => 'Ticket not found'], 404);
            return;
        }
        
        // Add reply
        $messageModel = new TicketMessage($this->db);
        $messageId = $messageModel->addReply($ticketId, $user['id'], $message, false, 'telegram');
        
        // Reopen if resolved/closed
        if (in_array($ticket['status'], ['resolved', 'closed'])) {
            $this->db->update('tickets', ['status' => 'open'], 'id = ?', [$ticketId]);
        }
        
        // Log activity
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $this->companyId,
            $ticketId,
            $user['id'],
            'telegram_reply',
            'Customer replied via Telegram Mini App'
        );
        
        // Notify agents
        $preview = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
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
            
            // Send web push notification to assigned agent
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
        
        error_log("[MiniApp] uploadFile called for ticket: {$ticketId}");
        
        $telegramUser = $this->validateInitData($initData);
        
        if (!$telegramUser) {
            $this->json(['success' => false, 'message' => 'Invalid Telegram data'], 403);
            return;
        }
        
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ? AND is_active = 1",
            [$telegramUser['id'], $this->companyId]
        );
        
        if (!$user) {
            $this->json(['success' => false, 'message' => 'User not found'], 403);
            return;
        }

        if ($ticketId <= 0 || $messageId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid ticket or message'], 422);
            return;
        }

        $ticket = $this->db->selectOne(
            "SELECT id FROM tickets WHERE id = ? AND requester_id = ? AND company_id = ?",
            [$ticketId, $user['id'], $this->companyId]
        );

        if (!$ticket) {
            $this->json(['success' => false, 'message' => 'Ticket not found'], 404);
            return;
        }

        $messageRow = $this->db->selectOne(
            "SELECT id FROM ticket_messages WHERE id = ? AND ticket_id = ?",
            [$messageId, $ticketId]
        );

        if (!$messageRow) {
            $this->json(['success' => false, 'message' => 'Message not found'], 404);
            return;
        }
        
        // Check file upload
        if (!isset($_FILES['file'])) {
            $this->json(['success' => false, 'message' => 'No file provided'], 422);
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
            
            // Prefer assigned agent if present
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
