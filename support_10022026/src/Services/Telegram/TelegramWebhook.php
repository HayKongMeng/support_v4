<?php

namespace App\Services\Telegram;

use App\Core\Database;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Category;
use App\Models\ActivityLog;
use App\Services\AI\TicketClassifier;

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
    }

    /**
     * Handle incoming message
     */
    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? null;

        // Get user state
        $state = $this->getUserState($chatId);

        // Handle commands
        if (strpos($text, '/') === 0) {
            $this->handleCommand($chatId, $text, $username);
            return;
        }

        // Handle state-based input
        switch ($state['state']) {
            case self::STATE_AWAITING_EMAIL:
                $this->handleEmailInput($chatId, $text);
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
                    "I didn't understand that. Use /help to see available commands.");
        }
    }

    /**
     * Handle commands
     */
    private function handleCommand(int $chatId, string $text, ?string $username): void
    {
        $parts = explode(' ', trim($text));
        $command = strtolower($parts[0]);
        $args = array_slice($parts, 1);

        error_log("[TelegramWebhook] handleCommand: {$command} for chat_id: {$chatId}");

        switch ($command) {
            case '/start':
                $this->handleStart($chatId, $username);
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
                $this->handleLink($chatId);
                break;

            default:
                $this->bot->sendMessage($chatId,
                    "Unknown command. Use /help to see available commands.");
        }
    }

    /**
     * Handle /start command
     */
    private function handleStart(int $chatId, ?string $username): void
    {
        error_log("[TelegramWebhook] handleStart called for chat_id: {$chatId}, username: {$username}");

        $user = $this->getUserByChatId($chatId);
        $welcomeMsg = $this->config['welcome_message'] ??
            "Welcome to our Support Bot! I can help you create and manage support tickets.";

        error_log("[TelegramWebhook] User found: " . ($user ? 'yes' : 'no') . ", sending welcome message");

        try {
            if ($user) {
                $this->bot->sendMessage($chatId,
                    "{$welcomeMsg}\n\n" .
                    "Hello, <b>{$user['name']}</b>! Your account is already linked.\n\n" .
                    "Use /help to see available commands.");
            } else {
                $this->bot->sendMessage($chatId,
                    "{$welcomeMsg}\n\n" .
                    "To get started, I need to link your email address.\n" .
                    "Use /link to connect your support account.");
            }
            error_log("[TelegramWebhook] Welcome message sent successfully");
        } catch (\Exception $e) {
            error_log("[TelegramWebhook] ERROR sending message: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle /help command
     */
    private function handleHelp(int $chatId): void
    {
        $this->bot->sendMessage($chatId,
            "<b>Available Commands</b>\n\n" .
            "/start - Start the bot\n" .
            "/link - Link your email account\n" .
            "/newticket - Create a new support ticket\n" .
            "/mytickets - View your open tickets\n" .
            "/status [ticket#] - Check ticket status\n" .
            "/help - Show this help message");
    }

    /**
     * Handle /link command
     */
    private function handleLink(int $chatId): void
    {
        $this->setUserState($chatId, self::STATE_AWAITING_EMAIL);
        $this->bot->sendMessage($chatId,
            "Please enter your email address to link your account:");
    }

    /**
     * Handle email input for linking
     */
    private function handleEmailInput(int $chatId, string $email): void
    {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->bot->sendMessage($chatId,
                "That doesn't look like a valid email address. Please try again:");
            return;
        }

        // Find or create user
        $userModel = new User($this->db);
        $user = $userModel->findByEmail($email, $this->companyId);

        if (!$user) {
            // Create new customer
            $userId = $this->db->insert('users', [
                'company_id' => $this->companyId,
                'email' => $email,
                'name' => 'Telegram User',
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'role' => 'customer',
                'telegram_chat_id' => $chatId,
                'is_active' => 1,
            ]);
        } else {
            // Link existing user
            $userModel->linkTelegram($user['id'], $chatId);
        }

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "Your account has been linked successfully!\n\n" .
            "You can now:\n" .
            "- Create tickets with /newticket\n" .
            "- View your tickets with /mytickets\n" .
            "- Receive notifications about your tickets");
    }

    /**
     * Handle /newticket command
     */
    private function handleNewTicket(int $chatId): void
    {
        $user = $this->getUserByChatId($chatId);

        if (!$user) {
            $this->bot->sendMessage($chatId,
                "Please link your email first using /link");
            return;
        }

        $this->setUserState($chatId, self::STATE_AWAITING_SUBJECT);
        $this->bot->sendMessage($chatId,
            "Let's create a new ticket.\n\n" .
            "<b>Step 1/2:</b> What is the subject of your issue?");
    }

    /**
     * Handle subject input
     */
    private function handleSubjectInput(int $chatId, string $subject, array $state): void
    {
        if (strlen($subject) < 5) {
            $this->bot->sendMessage($chatId,
                "Please provide a more descriptive subject (at least 5 characters):");
            return;
        }

        $this->setUserState($chatId, self::STATE_AWAITING_DESCRIPTION, ['subject' => $subject]);
        $this->bot->sendMessage($chatId,
            "<b>Step 2/2:</b> Please describe your issue in detail:");
    }

    /**
     * Handle description input and create ticket
     */
    private function handleDescriptionInput(int $chatId, string $description, array $state): void
    {
        if (strlen($description) < 10) {
            $this->bot->sendMessage($chatId,
                "Please provide more details (at least 10 characters):");
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

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "Your ticket has been created!\n\n" .
            "<b>Ticket Number:</b> {$ticketNumber}\n" .
            "<b>Subject:</b> {$subject}\n\n" .
            "We'll notify you when there's a response.");
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
                "You don't have any open tickets.\n\nUse /newticket to create one.");
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
                "Please link your email first using /link");
            return;
        }

        if (!$ticketNumber) {
            $this->bot->sendMessage($chatId,
                "Please provide a ticket number. Example: /status TKT-000001");
            return;
        }

        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets
             WHERE company_id = ? AND ticket_number = ? AND requester_id = ?",
            [$this->companyId, strtoupper($ticketNumber), $user['id']]
        );

        if (!$ticket) {
            $this->bot->sendMessage($chatId,
                "Ticket not found. Make sure you entered the correct ticket number.");
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
            $this->bot->sendMessage($chatId, "Ticket not found.");
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
            "Please type your reply to the ticket:");
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
            $this->bot->sendMessage($chatId, "Ticket not found.");
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

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "Your reply has been added to ticket #{$ticket['ticket_number']}.\n\n" .
            "We'll notify you when there's a response.");
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
                $text = "New reply on your ticket #{$ticket['ticket_number']}!\n\n" .
                    "<b>Subject:</b> {$ticket['subject']}\n\n" .
                    "Check the ticket for the full response.";
                break;

            case 'resolved':
                $text = "Your ticket #{$ticket['ticket_number']} has been resolved!\n\n" .
                    "<b>Subject:</b> {$ticket['subject']}\n\n" .
                    "If you need further assistance, you can reply to reopen the ticket.";
                break;

            default:
                return;
        }

        $keyboard = [
            [['text' => 'View Ticket', 'callback_data' => "view_ticket:{$ticket['id']}"]],
        ];

        $this->bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
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
