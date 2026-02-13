<?php

namespace App\Services\Telegram;

use App\Core\Database;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Category;
use App\Models\ActivityLog;
use App\Services\AI\TicketClassifier;
use App\Services\Telegram\TelegramAgentNotifier;

class TelegramWebhook
{
    private Database $db;
    private int $companyId;
    private TelegramBot $bot;
    private array $config;

    // User state storage (in real app, use Redis or DB)
    private const STATE_NONE = 'none';
    private const STATE_AWAITING_EMAIL = 'awaiting_email';
    private const STATE_AWAITING_SUBJECT = 'awaiting_subject';
    private const STATE_AWAITING_DESCRIPTION = 'awaiting_description';
    private const STATE_AWAITING_TICKET_REPLY = 'awaiting_ticket_reply';

    public function __construct(Database $db, int $companyId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->loadConfig();

        if ($this->config) {
            $this->bot = new TelegramBot($db, $this->config['bot_token']);
        }
    }

    private function loadConfig(): void
    {
        error_log("[TelegramWebhook] Loading config for company_id: {$this->companyId}");

        $this->config = $this->db->selectOne(
            "SELECT * FROM telegram_configs WHERE company_id = ? AND is_active = 1",
            [$this->companyId]
        );

        if ($this->config) {
            error_log("[TelegramWebhook] Config loaded successfully, bot_token prefix: " . substr($this->config['bot_token'], 0, 10) . "...");
        } else {
            error_log("[TelegramWebhook] WARNING: No active config found for company {$this->companyId}");

            // Debug: check if any config exists
            $anyConfig = $this->db->selectOne(
                "SELECT id, company_id, is_active FROM telegram_configs WHERE company_id = ?",
                [$this->companyId]
            );
            if ($anyConfig) {
                error_log("[TelegramWebhook] Found config but is_active=" . $anyConfig['is_active']);
            } else {
                error_log("[TelegramWebhook] No config exists at all for company {$this->companyId}");
            }
        }
    }

    /**
     * Handle incoming webhook
     */
    public function handle(array $update): void
    {
        error_log("[TelegramWebhook] handle() called for company {$this->companyId}");

        if (!$this->config) {
            error_log("[TelegramWebhook] ERROR: No config found for company {$this->companyId}");
            return;
        }

        try {
            error_log("[TelegramWebhook] Config loaded, processing update");

            // Handle callback queries (button clicks)
            if (isset($update['callback_query'])) {
                error_log("[TelegramWebhook] Processing callback query");
                $this->handleCallbackQuery($update['callback_query']);
                return;
            }

            // Handle regular messages
            if (isset($update['message'])) {
                error_log("[TelegramWebhook] Processing message: " . ($update['message']['text'] ?? 'no text'));
                $this->handleMessage($update['message']);
            }
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] CRITICAL ERROR in handle(): " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            error_log("[TelegramWebhook] Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Handle incoming message
     */
    private function handleMessage(array $message): void
    {
        try {
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';
            $username = $message['from']['username'] ?? null;
            
            // Store Telegram user info for later use
            $telegramUserInfo = [
                'username' => $message['from']['username'] ?? null,
                'first_name' => $message['from']['first_name'] ?? '',
                'last_name' => $message['from']['last_name'] ?? '',
            ];

            error_log("[TelegramWebhook] handleMessage: chat_id={$chatId}, text='{$text}'");
            
            // Check for file attachments (photo, document, voice, audio)
            $hasFile = isset($message['photo']) || isset($message['document']) || isset($message['voice']) || isset($message['audio']);
            
            if ($hasFile) {
                error_log("[TelegramWebhook] Message contains file attachment");
                $this->handleFileMessage($chatId, $message, $telegramUserInfo);
                return;
            }

            // Get user state
            $state = $this->getUserState($chatId);

            // Handle commands
            if (strpos($text, '/') === 0) {
                error_log("[TelegramWebhook] Detected command, calling handleCommand");
                $this->handleCommand($chatId, $text, $telegramUserInfo);
                return;
            }

            // Handle state-based input
            switch ($state['state']) {
                case self::STATE_AWAITING_EMAIL:
                    $this->handleEmailInput($chatId, $text, $telegramUserInfo);
                    break;

                case self::STATE_AWAITING_SUBJECT:
                    $this->handleSubjectInput($chatId, $text, $state);
                    break;

                case self::STATE_AWAITING_DESCRIPTION:
                    $this->handleDescriptionInput($chatId, $text, $state);
                    break;

                case self::STATE_AWAITING_TICKET_REPLY:
                    $this->handleTicketReply($chatId, $text, $state);
                    break;

                default:
                    $this->bot->sendMessage($chatId,
                        "ខ្ញុំមិនយល់ដែលនោះទេ។ ប្រើ /help ដើម្បីមើលពាក្យបញ្ជាដែលមាន។");
            }
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] ERROR in handleMessage: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Handle commands
     */
    private function handleCommand(int $chatId, string $text, array $telegramUserInfo): void
    {
        try {
            $parts = explode(' ', trim($text));
            $command = strtolower($parts[0]);
            $args = array_slice($parts, 1);

            error_log("[TelegramWebhook] handleCommand: {$command} for chat_id: {$chatId}");

            switch ($command) {
                case '/start':
                    $this->handleStart($chatId, $telegramUserInfo);
                    break;

                case '/help':
                    $this->handleHelp($chatId);
                    break;

                case '/newticket':
                    $this->handleNewTicket($chatId);
                    break;

                case '/mytickets':
                    $this->handleMyTickets($chatId);
                    break;

                case '/status':
                    $this->handleStatus($chatId, $args[0] ?? null);
                    break;

                case '/link':
                    $this->handleLink($chatId, $telegramUserInfo);
                    break;

                default:
                    error_log("[TelegramWebhook] Unknown command: {$command}");
                    $this->bot->sendMessage($chatId,
                        "Unknown command. Use /help to see available commands.");
            }
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] ERROR in handleCommand: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Handle /start command
     */
    private function handleStart(int $chatId, array $telegramUserInfo): void
    {
        error_log("[TelegramWebhook] handleStart called for chat_id: {$chatId}, username: {$telegramUserInfo['username']}");

        try {
            $user = $this->getUserByChatId($chatId);
            $welcomeMsg = $this->config['welcome_message'] ??
                "Welcome to our Support Bot! I can help you create and manage support tickets.";

            error_log("[TelegramWebhook] User found: " . ($user ? 'yes' : 'no') . ", sending welcome message");

            if ($user) {
                // Create inline keyboard with Mini App button
                $keyboard = [
                    [['text' => '🚀 បើកកម្មវិធី', 'web_app' => ['url' => 'https://ticket.providawater.com/telegram-app.html']]]];
                
                $this->bot->sendMessageWithKeyboard($chatId,
                    "{$welcomeMsg}\n\n" .
                    "សូមស្វាគមន៍, <b>{$user['name']}</b>! គណនីរបស់អ្នកបានតភ្ជាប់រួចហើយ។\n\n" .
                    "ចុចប៊ូតុងខាងក្រោម ដើម្បីបើកកម្មវិធី ឬប្រើ /help ដើម្បីមើលពាក្យបញ្ជា។",
                    $keyboard);
            } else {
                $this->bot->sendMessage($chatId,
                    "{$welcomeMsg}\n\n" .
                    "ដើម្បីចាប់ផ្តើម ខ្ញុំត្រូវការតភ្ជាប់អាស័យដ្ឋានអ៊ីមែលរបស់អ្នក។\n" .
                    "ប្រើ /link ដើម្បីភ្ជាប់គណនីគាំទ្ភាពរបស់អ្នក។");
            }
            error_log("[TelegramWebhook] Welcome message sent successfully");
        } catch (\Exception $e) {
            error_log("[TelegramWebhook] ERROR in handleStart: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Try to send error message to user
            try {
                $this->bot->sendMessage($chatId, "Sorry, an error occurred. Please try again later.");
            } catch (\Exception $e2) {
                error_log("[TelegramWebhook] CRITICAL: Could not send error message: " . $e2->getMessage());
            }
        }
    }

    /**
     * Handle /help command
     */
    private function handleHelp(int $chatId): void
    {
        try {
            error_log("[TelegramWebhook] handleHelp called for chat_id: {$chatId}");
            $this->bot->sendMessage($chatId,
                "<b>ពាក្យបញ្ជាដែលមាន</b>\n\n" .
                "/start - ចាប់ផ្តើមម៉ាស៊ីនបង្គាប់\n" .
                "/link - ភ្ជាប់គណនីអ៊ីមែលរបស់អ្នក\n" .
                "/newticket - បង្កើតសំណើសុំជំនួយគាំទ្រថ្មី\n" .
                "/mytickets - មើលសំណើសុំជំនួយគាំទ្របស់អ្នក\n" .
                "/status [ticket#] - ពិនិត្យលក្ខខ័ណ្ឌសំណើសុំជំនួយ\n" .
                "/help - បង្ហាញសារលម្អិតនេះ");
            error_log("[TelegramWebhook] handleHelp completed successfully");
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] ERROR in handleHelp: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Handle /link command
     */
    private function handleLink(int $chatId, array $telegramUserInfo): void
    {
        try {
            error_log("[TelegramWebhook] handleLink called for chat_id: {$chatId}");
            // Store Telegram user info in state for later use during email linking
            $this->setUserState($chatId, self::STATE_AWAITING_EMAIL, ['telegram_user_info' => $telegramUserInfo]);
            $this->bot->sendMessage($chatId,
                "សូមបញ្ចូលអាស័យដ្ឋានអ៊ីមែលរបស់អ្នកដើម្បីភ្ជាប់គណនីរបស់អ្នក:");
            error_log("[TelegramWebhook] handleLink completed successfully");
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] ERROR in handleLink: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Handle email input for linking
     */
    private function handleEmailInput(int $chatId, string $email, array $telegramUserInfo): void
    {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->bot->sendMessage($chatId,
                "វាមិនហាក់ដូចជាអាស័យដ្ឋានអ៊ីមែលត្រឹមត្រូវទេ។ សូមព្យាយាមម្តងទៀត:");
            return;
        }

        // Get state data to retrieve stored Telegram user info
        $state = $this->getUserState($chatId);
        $storedUserInfo = $state['data']['telegram_user_info'] ?? $telegramUserInfo;
        
        // Build display name from Telegram info
        $displayName = trim(($storedUserInfo['first_name'] ?? '') . ' ' . ($storedUserInfo['last_name'] ?? ''));
        if (empty($displayName) && !empty($storedUserInfo['username'])) {
            $displayName = $storedUserInfo['username'];
        }
        if (empty($displayName)) {
            $displayName = 'Telegram User';
        }

        // Find or create user
        $userModel = new User($this->db);
        $user = $userModel->findByEmail($email, $this->companyId);

        if (!$user) {
            // Create new customer with Telegram name
            $userId = $this->db->insert('users', [
                'company_id' => $this->companyId,
                'email' => $email,
                'name' => $displayName,
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'role' => 'customer',
                'telegram_chat_id' => $chatId,
                'is_active' => 1,
            ]);
        } else {
            // Link existing user and update name if it was "Telegram User"
            if ($user['name'] === 'Telegram User') {
                $this->db->update('users', ['name' => $displayName], 'id = ?', [$user['id']]);
            }
            $userModel->linkTelegram($user['id'], $chatId);
        }

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "គណនីរបស់អ្នកបានតភ្ជាប់ដោយជោគជ័យ!\n\n" .
            "អ្នកក៏អាច:\n" .
            "- បង្កើតសំណើសុំជំនួយដោយ /newticket\n" .
            "- មើលសំណើសុំជំនួយរបស់អ្នក /mytickets\n" .
            "- ទទួលបានការជូនដំណឹងអំពីសំណើសុំជំនួយរបស់អ្នក");
    }

    /**
     * Handle /newticket command
     */
    private function handleNewTicket(int $chatId): void
    {
        $user = $this->getUserByChatId($chatId);

        if (!$user) {
            $this->bot->sendMessage($chatId,
                "សូមភ្ជាប់អ៊ីមែលរបស់អ្នកដំបូងដោយប្រើ /link");
            return;
        }

        $this->setUserState($chatId, self::STATE_AWAITING_SUBJECT);
        $this->bot->sendMessage($chatId,
            "ចូលក្នុងការបង្កើតសំណើសុំជំនួយថ្មី។\n\n" .
            "<b>ជំហាន 1/2:</b> តើប្រធានបទនៃបញ្ហារបស់អ្នកគឺជាអ្វី?");
    }

    /**
     * Handle subject input
     */
    private function handleSubjectInput(int $chatId, string $subject, array $state): void
    {
        if (strlen($subject) < 5) {
            $this->bot->sendMessage($chatId,
                "សូមផ្តល់ជូនប្រធានបទលម្អិត (យ៉ាងហោចណាស់ 5 តួអក្សរ):");
            return;
        }

        $this->setUserState($chatId, self::STATE_AWAITING_DESCRIPTION, ['subject' => $subject]);
        $this->bot->sendMessage($chatId,
            "<b>ជំហាន 2/2:</b> សូមពិពណ៌នាលម្អិតអំពីបញ្ហារបស់អ្នក:");
    }

    /**
     * Handle description input and create ticket
     */
    private function handleDescriptionInput(int $chatId, string $description, array $state): void
    {
        if (strlen($description) < 10) {
            $this->bot->sendMessage($chatId,
                "សូមផ្តល់ជូនព័ត៌មានលម្អិតលម្អិត (យ៉ាងហោចណាស់ 10 តួអក្សរ):");
            return;
        }

        $user = $this->getUserByChatId($chatId);
        $subject = $state['data']['subject'];

        // Create ticket
        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId);
        $ticketNumber = $ticketModel->generateTicketNumber();

        // AI Classification
        $classifier = new TicketClassifier($this->db, $this->companyId);
        $classification = $classifier->classify($subject . ' ' . $description);

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
            'category_id' => $classification['category_id'] ?? $this->config['default_category_id'],
            'requester_id' => $user['id'],
            'requester_email' => $user['email'],
            'requester_name' => $user['name'],
            'ai_suggested_category' => $classification['category_id'] ?? null,
            'ai_suggested_priority' => $aiPriority,
            'ai_confidence_score' => $classification['confidence'] ?? null,
        ]);

        // Add message
        $messageModel = new TicketMessage($this->db);
        $messageModel->addReply($ticketId, $user['id'], $description, false, 'telegram');

        // Log activity
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $this->companyId,
            $ticketId,
            $user['id'],
            'ticket_created',
            "Ticket {$ticketNumber} created via Telegram"
        );

        // Notify agents/admins about new ticket
        $this->notifyAgents(
            $ticketId,
            'new_ticket',
            "New ticket #{$ticketNumber}",
            "{$user['name']}: {$subject}",
            [
                'source' => 'telegram',
                'requester' => $user['name'] ?? 'Customer',
            ]
        );

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "សំណើសុំជំនួយរបស់អ្នកបានបង្កើត!\n\n" .
            "<b>លេខសំណើសុំជំនួយ:</b> {$ticketNumber}\n" .
            "<b>ប្រធានបទ:</b> {$subject}\n\n" .
            "យើងនឹងជូនដំណឹងឱ្យអ្នកនៅពេលដែលមានការឆ្លើយតប។");
    }

    /**
     * Handle /mytickets command
     */
    private function handleMyTickets(int $chatId): void
    {
        $user = $this->getUserByChatId($chatId);

        if (!$user) {
            $this->bot->sendMessage($chatId,
                "Please link your email first using /link");
            return;
        }

        $tickets = $this->db->select(
            "SELECT * FROM tickets
             WHERE company_id = ? AND requester_id = ? AND status NOT IN ('closed')
             ORDER BY created_at DESC
             LIMIT 10",
            [$this->companyId, $user['id']]
        );

        if (empty($tickets)) {
            $this->bot->sendMessage($chatId,
                "អ្នកមិនមានសំណើសុំជំនួយលើកទឹកលោកលើកទឹកលោក។\n\nប្រើ /newticket ដើម្បីបង្កើតលក្ខណ៍ដូច។");
            return;
        }

        // Build inline keyboard with tickets
        $keyboard = [];
        foreach ($tickets as $ticket) {
            $status = ucfirst(str_replace('_', ' ', $ticket['status']));
            $keyboard[] = [[
                'text' => "#{$ticket['ticket_number']} - {$status}",
                'callback_data' => "view_ticket:{$ticket['id']}",
            ]];
        }

        $this->bot->sendMessageWithKeyboard($chatId,
            $this->bot->formatTicketList($tickets),
            $keyboard);
    }

    /**
     * Handle /status command
     */
    private function handleStatus(int $chatId, ?string $ticketNumber): void
    {
        $user = $this->getUserByChatId($chatId);

        if (!$user) {
            $this->bot->sendMessage($chatId,
                "សូមភ្ជាប់អ៊ីមែលរបស់អ្នកដំបូងដោយប្រើ /link");
            return;
        }

        if (!$ticketNumber) {
            $this->bot->sendMessage($chatId,
                "សូមផ្តល់ជូនលេខសំណើសុំជំនួយ។ ឧទាហរណ៍: /status TKT-000001");
            return;
        }

        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets
             WHERE company_id = ? AND ticket_number = ? AND requester_id = ?",
            [$this->companyId, strtoupper($ticketNumber), $user['id']]
        );

        if (!$ticket) {
            $this->bot->sendMessage($chatId, "សំណើសុំជំនួយមិនត្រូវបានរកឃើញទេ។ ធានាថាអ្នកបានបញ្ចូលលេខសំណើសុំជំនួយត្រឹមត្រូវ។");
            return;
        }

        $keyboard = [
            [['text' => 'Reply to Ticket', 'callback_data' => "reply_ticket:{$ticket['id']}"]],
        ];

        $this->bot->sendMessageWithKeyboard($chatId,
            $this->bot->formatTicket($ticket),
            $keyboard);
    }

    /**
     * Handle callback queries
     */
    private function handleCallbackQuery(array $query): void
    {
        $chatId = $query['message']['chat']['id'];
        $data = $query['data'];

        // Acknowledge the callback
        $this->bot->answerCallbackQuery($query['id']);

        // Parse callback data
        $parts = explode(':', $data);
        $action = $parts[0];
        $param = $parts[1] ?? null;

        switch ($action) {
            case 'view_ticket':
                $this->viewTicket($chatId, (int) $param);
                break;

            case 'reply_ticket':
                $this->startTicketReply($chatId, (int) $param);
                break;
        }
    }

    /**
     * View ticket details
     */
    private function viewTicket(int $chatId, int $ticketId): void
    {
        $user = $this->getUserByChatId($chatId);

        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets WHERE id = ? AND requester_id = ?",
            [$ticketId, $user['id']]
        );

        if (!$ticket) {
            $this->bot->sendMessage($chatId, "សំណើសុំជំនួយមិនត្រូវបានរកឃើញទេ។");
            return;
        }

        // Get latest message
        $latestMessage = $this->db->selectOne(
            "SELECT tm.*, u.name as user_name, u.role
             FROM ticket_messages tm
             LEFT JOIN users u ON tm.user_id = u.id
             WHERE tm.ticket_id = ? AND tm.is_internal = 0
             ORDER BY tm.created_at DESC
             LIMIT 1",
            [$ticketId]
        );

        $text = $this->bot->formatTicket($ticket);

        if ($latestMessage) {
            $from = in_array($latestMessage['role'], ['admin', 'agent']) ? 'Support' : 'You';
            $text .= "\n\n<b>Latest Reply ({$from}):</b>\n" .
                htmlspecialchars(substr($latestMessage['message'], 0, 300)) .
                (strlen($latestMessage['message']) > 300 ? '...' : '');
        }

        $keyboard = [
            [['text' => 'Reply', 'callback_data' => "reply_ticket:{$ticketId}"]],
        ];

        $this->bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * Start ticket reply flow
     */
    private function startTicketReply(int $chatId, int $ticketId): void
    {
        $this->setUserState($chatId, self::STATE_AWAITING_TICKET_REPLY, ['ticket_id' => $ticketId]);
        $this->bot->sendMessage($chatId,
            "សូមសរសេរការឆ្លើយតបរបស់អ្នក:");
    }

    /**
     * Handle ticket reply
     */
    private function handleTicketReply(int $chatId, string $message, array $state): void
    {
        $user = $this->getUserByChatId($chatId);
        $ticketId = $state['data']['ticket_id'];

        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets WHERE id = ? AND requester_id = ?",
            [$ticketId, $user['id']]
        );

        if (!$ticket) {
            $this->clearUserState($chatId);
            $this->bot->sendMessage($chatId, "សំណើសុំជំនួយមិនត្រូវបានរកឃើញទេ។");
            return;
        }

        // Add reply
        $messageModel = new TicketMessage($this->db);
        $messageModel->addReply($ticketId, $user['id'], $message, false, 'telegram');

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
            'Customer replied via Telegram'
        );

        // Notify agents/admins about new reply
        $preview = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
        $this->notifyAgents(
            $ticketId,
            'telegram_reply',
            "New reply on #{$ticket['ticket_number']}",
            "{$user['name']}: {$preview}",
            [
                'source' => 'telegram',
                'requester' => $user['name'] ?? 'Customer',
            ]
        );

        if (!empty($ticket['assigned_to'])) {
            $assignedUser = $this->db->selectOne(
                "SELECT name FROM users WHERE id = ? AND company_id = ?",
                [$ticket['assigned_to'], $this->companyId]
            );
            $assignedName = $assignedUser['name'] ?? 'Agent';
            $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
            $link = $appUrl ? $appUrl . "/tickets/{$ticketId}" : '';
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
                "✉️ <b>Message:</b> {$previewSafe}";
            if ($link) {
                $text .= "\nLink: {$link}";
            }
            $notifier = new TelegramAgentNotifier($this->db, $this->companyId);
            $notifier->notifyAssignedAgent($ticket, $text);
        }

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "ការឆ្លើយតបរបស់អ្នកបានដំឡើងរៀងរាល់សំណើសុំជំនួយ #{$ticket['ticket_number']}។\n\n" .
            "យើងនឹងជូនដំណឹងឱ្យអ្នកនៅពេលដែលមានការឆ្លើយតប។");
    }

    /**
     * Send notification to user
     */
    public function sendTicketNotification(array $ticket, string $type = 'reply'): void
    {
        // Get user's Telegram chat ID
        $user = $this->db->selectOne(
            "SELECT telegram_chat_id FROM users WHERE id = ?",
            [$ticket['requester_id']]
        );

        if (!$user || !$user['telegram_chat_id']) {
            return;
        }

        $chatId = $user['telegram_chat_id'];

        switch ($type) {
            case 'reply':
                $text = "ការឆ្លើយតបថ្មីលើសំណើសុំជំនួយរបស់អ្នក #{$ticket['ticket_number']}!\n\n" .
                    "<b>ប្រធានបទ:</b> {$ticket['subject']}\n\n" .
                    "ពិនិត្យសំណើសុំជំនួយដើម្បីដំណើរការឆ្លើយតបពេញលេញ។";
                break;

            case 'resolved':
                $text = "សំណើសុំជំនួយរបស់អ្នក #{$ticket['ticket_number']} បានដោះស្រាយ!\n\n" .
                    "<b>ប្រធានបទ:</b> {$ticket['subject']}\n\n" .
                    "ប្រសិនបើអ្នកត្រូវការជំនួយលម្អិត អ្នកអាចឆ្លើយដើម្បីបើកឡើងវិញនូវសំណើសុំជំនួយ។";
                break;

            default:
                return;
        }

        $keyboard = [
            [['text' => 'មើលសំណើសុំជំនួយ', 'callback_data' => "view_ticket:{$ticket['id']}"]],
        ];

        $this->bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }
    
    /**
     * Handle file messages (images and other files)
     */
    private function handleFileMessage(int $chatId, array $message, array $telegramUserInfo): void
    {
        try {
            $user = $this->getUserByChatId($chatId);
            
            if (!$user) {
                $this->bot->sendMessage($chatId,
                    "សូមភ្ជាប់គណនីរបស់អ្នកដំបូងដោយប្រើ /link");
                return;
            }
            
            // Get user state to see if they're replying to a ticket
            $state = $this->getUserState($chatId);
            $caption = $message['caption'] ?? '';
            
            // Get file info based on type
            $fileId = null;
            $fileName = null;
            $mimeType = null;
            $fileSize = 0;
            
            if (isset($message['photo'])) {
                // Get largest photo
                $photo = end($message['photo']);
                $fileId = $photo['file_id'];
                $fileName = 'photo_' . time() . '.jpg';
                $mimeType = 'image/jpeg';
                $fileSize = $photo['file_size'] ?? 0;
            } elseif (isset($message['document'])) {
                $doc = $message['document'];
                $fileId = $doc['file_id'];
                $fileName = $doc['file_name'] ?? 'document_' . time();
                $mimeType = $doc['mime_type'] ?? 'application/octet-stream';
                $fileSize = $doc['file_size'] ?? 0;
            } elseif (isset($message['voice'])) {
                $voice = $message['voice'];
                $fileId = $voice['file_id'];
                $mimeType = $voice['mime_type'] ?? 'audio/ogg';
                $fileName = 'voice_' . time() . $this->guessExtension($mimeType, '.ogg');
                $fileSize = $voice['file_size'] ?? 0;
            } elseif (isset($message['audio'])) {
                $audio = $message['audio'];
                $fileId = $audio['file_id'];
                $mimeType = $audio['mime_type'] ?? 'audio/mpeg';
                $fileName = $audio['file_name'] ?? ('audio_' . time() . $this->guessExtension($mimeType, '.mp3'));
                $fileSize = $audio['file_size'] ?? 0;
            }
            
            if (!$fileId) {
                error_log("[TelegramWebhook] No valid file found in message");
                return;
            }
            
            error_log("[TelegramWebhook] Processing file: {$fileName}, size: {$fileSize}");
            
            // Check if user is replying to a ticket
            if ($state['state'] === self::STATE_AWAITING_TICKET_REPLY) {
                $ticketId = $state['data']['ticket_id'];
                $this->handleFileReply($chatId, $ticketId, $fileId, $fileName, $mimeType, $fileSize, $caption, $user);
            } else {
                // Not in a ticket context, inform user
                $this->bot->sendMessage($chatId,
                    "Please use /mytickets to select a ticket, then send your file.\nOr use /newticket to create a new ticket.");
            }
            
        } catch (\Exception $e) {
            error_log("[TelegramWebhook] ERROR in handleFileMessage: " . $e->getMessage());
            $this->bot->sendMessage($chatId, "Error processing the file.");
        }
    }
    
    /**
     * Handle file attachment when replying to ticket
     */
    private function handleFileReply(int $chatId, int $ticketId, string $fileId, string $fileName, string $mimeType, int $fileSize, string $caption, array $user): void
    {
        try {
            // Get file path from Telegram
            $fileInfo = $this->bot->getFile($fileId);
            
            if (!$fileInfo || !isset($fileInfo['file_path'])) {
                error_log("[TelegramWebhook] Failed to get file info from Telegram");
                $this->bot->sendMessage($chatId, "Error downloading the file.");
                return;
            }
            
            $filePath = $fileInfo['file_path'];
            $fileUrl = "https://api.telegram.org/file/bot{$this->config['bot_token']}/{$filePath}";
            
            error_log("[TelegramWebhook] File URL: {$fileUrl}");
            
            // Download file
            $fileContent = @file_get_contents($fileUrl);
            
            if ($fileContent === false) {
                error_log("[TelegramWebhook] Failed to download file");
                $this->bot->sendMessage($chatId, "Error downloading the file.");
                return;
            }
            
            // Create upload directory
            $uploadDir = BASE_PATH . "/public/uploads/tickets/{$ticketId}/" . date('Y/m');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $uniqueName = $baseName . '_' . uniqid() . ($ext ? '.' . $ext : '');
            $localPath = $uploadDir . '/' . $uniqueName;
            
            // Save file
            if (file_put_contents($localPath, $fileContent) === false) {
                error_log("[TelegramWebhook] Failed to save file");
                $this->bot->sendMessage($chatId, "Error saving the file.");
                return;
            }
            
            error_log("[TelegramWebhook] File saved: {$localPath}");
            
            // Relative path for database
            $relativePath = "tickets/{$ticketId}/" . date('Y/m') . '/' . $uniqueName;
            
            // Verify ticket belongs to user
            $ticket = $this->db->selectOne(
                "SELECT * FROM tickets WHERE id = ? AND requester_id = ?",
                [$ticketId, $user['id']]
            );
            
            if (!$ticket) {
                $this->clearUserState($chatId);
                $this->bot->sendMessage($chatId, "សំណើសុំជំនួយមិនត្រូវបានរកឃើញទេ។");
                return;
            }
            
            // Add message with attachment
            $messageText = $caption ?: $this->defaultAttachmentLabel($mimeType, $fileName);
            $messageModel = new TicketMessage($this->db);
            $messageId = $messageModel->addReply($ticketId, $user['id'], $messageText, false, 'telegram');
            
            // Save attachment to database
            $this->db->insert('attachments', [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'user_id' => $user['id'],
                'filename' => $uniqueName,
                'original_name' => $fileName,
                'mime_type' => $mimeType,
                'size' => strlen($fileContent),
                'path' => $relativePath,
            ]);
            
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
                'Customer sent file via Telegram: ' . $fileName
            );
            
            // Notify agents
            $preview = $caption ? substr($caption, 0, 100) . '...' : '[Attachment: ' . $fileName . ']';
            $this->notifyAgents(
                $ticketId,
                'telegram_reply',
                "New file on #{$ticket['ticket_number']}",
                "{$user['name']}: {$preview}",
                [
                    'source' => 'telegram',
                    'requester' => $user['name'] ?? 'Customer',
                    'has_attachment' => true,
                ]
            );

            if (!empty($ticket['assigned_to'])) {
                $assignedUser = $this->db->selectOne(
                    "SELECT name FROM users WHERE id = ? AND company_id = ?",
                    [$ticket['assigned_to'], $this->companyId]
                );
                $assignedName = $assignedUser['name'] ?? 'Agent';
                $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
                $link = $appUrl ? $appUrl . "/tickets/{$ticketId}" : '';
                $subject = htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES | ENT_HTML5);
                $requesterName = htmlspecialchars($ticket['requester_name'] ?? '', ENT_QUOTES | ENT_HTML5);
                $requesterEmail = htmlspecialchars($ticket['requester_email'] ?? '', ENT_QUOTES | ENT_HTML5);
                $assignedLabel = htmlspecialchars($assignedName, ENT_QUOTES | ENT_HTML5);
                $status = ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'open'));
                $priority = ucfirst($ticket['priority'] ?? 'medium');
                $previewSafe = htmlspecialchars($preview, ENT_QUOTES | ENT_HTML5);
                $text = "📎 <b>New Customer Attachment</b>\n" .
                    "🆔 <b>Ticket:</b> #{$ticket['ticket_number']}\n" .
                    "📝 <b>Subject:</b> {$subject}\n" .
                    "📌 <b>Status:</b> {$status}\n" .
                    "🚦 <b>Priority:</b> {$priority}\n" .
                    "👤 <b>Requester:</b> {$requesterName} ({$requesterEmail})\n" .
                    "🎯 <b>Assigned to:</b> {$assignedLabel}\n" .
                    "✉️ <b>Message:</b> {$previewSafe}";
                if ($link) {
                    $text .= "\nLink: {$link}";
                }
                $notifier = new TelegramAgentNotifier($this->db, $this->companyId);
                $notifier->notifyAssignedAgent($ticket, $text);
            }
            
            $this->clearUserState($chatId);
            $this->bot->sendMessage($chatId,
                "✅ File sent to ticket #{$ticket['ticket_number']} successfully!");
            
        } catch (\Exception $e) {
            error_log("[TelegramWebhook] ERROR in handleFileReply: " . $e->getMessage());
            $this->bot->sendMessage($chatId, "Error processing the file.");
        }
    }

    private function guessExtension(string $mimeType, string $fallback): string
    {
        if (stripos($mimeType, 'ogg') !== false) {
            return '.ogg';
        }

        if (stripos($mimeType, 'mpeg') !== false || stripos($mimeType, 'mp3') !== false) {
            return '.mp3';
        }

        if (stripos($mimeType, 'mp4') !== false || stripos($mimeType, 'm4a') !== false) {
            return '.m4a';
        }

        return $fallback;
    }

    private function defaultAttachmentLabel(string $mimeType, string $fileName): string
    {
        if (stripos($mimeType, 'image/') === 0) {
            return '[Image attachment: ' . $fileName . ']';
        }

        if (stripos($mimeType, 'audio/') === 0) {
            return '[Audio attachment: ' . $fileName . ']';
        }

        return '[Attachment: ' . $fileName . ']';
    }

    /**
     * Notify agents/admins in the dashboard
     */
    private function notifyAgents(int $ticketId, string $type, string $title, string $message, array $data = []): void
    {
        $ticket = $this->db->selectOne(
            "SELECT id, ticket_number, subject, assigned_to FROM tickets WHERE id = ?",
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

        foreach ($agents as $agent) {
            $this->db->insert('notifications', [
                'user_id' => $agent['id'],
                'ticket_id' => $ticketId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => json_encode($data),
            ]);
        }
    }

    // State management helpers
    private function getUserState(int $chatId): array
    {
        $state = $this->db->selectOne(
            "SELECT * FROM telegram_user_states WHERE chat_id = ? AND company_id = ?",
            [$chatId, $this->companyId]
        );

        if (!$state) {
            return ['state' => self::STATE_NONE, 'data' => []];
        }

        return [
            'state' => $state['state'],
            'data' => json_decode($state['data'], true) ?? [],
        ];
    }

    private function setUserState(int $chatId, string $state, array $data = []): void
    {
        $existing = $this->db->selectOne(
            "SELECT id FROM telegram_user_states WHERE chat_id = ? AND company_id = ?",
            [$chatId, $this->companyId]
        );

        if ($existing) {
            $this->db->update('telegram_user_states', [
                'state' => $state,
                'data' => json_encode($data),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('telegram_user_states', [
                'company_id' => $this->companyId,
                'chat_id' => $chatId,
                'state' => $state,
                'data' => json_encode($data),
            ]);
        }
    }

    private function clearUserState(int $chatId): void
    {
        $this->db->delete('telegram_user_states',
            'chat_id = ? AND company_id = ?',
            [$chatId, $this->companyId]
        );
    }

    private function getUserByChatId(int $chatId): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ? AND is_active = 1",
            [$chatId, $this->companyId]
        );
    }
}
